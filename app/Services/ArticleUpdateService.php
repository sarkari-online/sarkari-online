<?php
/**
 * EduPulse - Article Monitoring & Incremental Update Engine (Phase 8)
 * Monitors published time-sensitive articles against official statutory releases,
 * generates fact-grounded update proposals via Gemini, verifies through FactChecker,
 * and maintains immutable revision history snapshots in 'article_updates'.
 */

namespace App\Services;

use App\AI\Gemini;
use App\AI\FactChecker;
use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use Exception;
use Throwable;

class ArticleUpdateService {

    private FactChecker $factChecker;

    public function __construct(?FactChecker $factChecker = null) {
        $this->factChecker = $factChecker ?: new FactChecker();
    }

    /**
     * Get published time-sensitive articles eligible for monitoring
     */
    public function getMonitoringCandidates(int $limit = 10): array {
        // Exclude static career guides unless they have active source URLs
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
                FROM articles a 
                JOIN categories c ON a.category_id = c.id 
                WHERE a.status = 'published' 
                  AND c.slug IN ('exam-results', 'admit-cards', 'exam-dates', 'government-jobs', 'higher-education', 'scholarships')
                ORDER BY a.updated_at ASC, a.id ASC 
                LIMIT " . (int)$limit;

        return Database::fetchAll($sql);
    }

    /**
     * Check an article against new source data and apply verified incremental updates
     */
    public function processArticleUpdate(int $articleId, array $newSourceData): array {
        $article = Database::fetchOne(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
             FROM articles a 
             JOIN categories c ON a.category_id = c.id 
             WHERE a.id = :id AND a.status = 'published' LIMIT 1",
            ['id' => $articleId]
        );

        if (!$article) {
            return ['success' => false, 'updated' => false, 'error' => "Published Article #{$articleId} not found."];
        }

        // 1. Guard against unnecessary evergreen rewriting
        if ($article['category_slug'] === 'career-guides' && empty($newSourceData['force_update'])) {
            return ['success' => true, 'updated' => false, 'reason' => 'Evergreen guide preserved without changes.'];
        }

        $sourceName = $newSourceData['source_name'] ?? $article['source_name'];
        $sourceUrl = $newSourceData['source_url'] ?? $article['source_url'];
        $newFacts = $newSourceData['new_facts'] ?? ($newSourceData['snippet'] ?? '');

        if (empty(trim($newFacts))) {
            return ['success' => true, 'updated' => false, 'reason' => 'No new factual data provided.'];
        }

        Logger::info("Analyzing update proposal for Article #{$articleId}: '{$article['title']}'");

        // 2. Ask Gemini for Incremental Update Proposal (Delta-Only)
        $proposal = $this->generateUpdateProposal($article, $newFacts, $sourceName, $sourceUrl);

        if (empty($proposal['has_meaningful_update'])) {
            Logger::info("No meaningful updates found for Article #{$articleId}. Preserving original.");
            return [
                'success' => true,
                'updated' => false,
                'reason' => $proposal['change_summary'] ?? 'Information is already current.'
            ];
        }

        // 3. Fact-Check the Proposed Content
        $factAudit = $this->factChecker->check([
            'id' => $articleId,
            'title' => $proposal['proposed_title'] ?? $article['title'],
            'content' => $proposal['proposed_content']
        ], [
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'reference' => $newSourceData['reference'] ?? $article['source_ref'],
            'notes' => $newFacts
        ]);

        $factScore = (int)($factAudit['factual_accuracy_score'] ?? 0);
        $recommendation = $factAudit['recommendation'] ?? 'review';

        if ($factScore < 90 || $recommendation === 'reject' || ($factAudit['hallucination_risk'] ?? '') === 'high') {
            Logger::warning("Article #{$articleId} update proposal rejected by FactChecker (Score: {$factScore}, Recommendation: {$recommendation})");
            return [
                'success' => false,
                'updated' => false,
                'reason' => 'Proposed update failed factual verification.',
                'fact_audit' => $factAudit
            ];
        }

        // 4. Archive Old Version in article_updates Table
        $now = date('Y-m-d H:i:s');
        Database::insert('article_updates', [
            'article_id' => $articleId,
            'old_content' => $article['content'],
            'new_content' => $proposal['proposed_content'],
            'reason' => Sanitizer::string($proposal['change_summary']),
            'source_url' => filter_var($sourceUrl, FILTER_VALIDATE_URL) ?: $article['source_url'],
            'created_at' => $now
        ]);

        // 5. Update Article Record (Preserve original_published_at, update updated_at)
        $updateFields = [
            'content' => Sanitizer::html($proposal['proposed_content']),
            'updated_at' => $now,
            'source_name' => Sanitizer::string($sourceName),
            'source_url' => filter_var($sourceUrl, FILTER_VALIDATE_URL) ?: $article['source_url']
        ];

        if (!empty($proposal['proposed_title']) && $proposal['proposed_title'] !== $article['title']) {
            $updateFields['title'] = Sanitizer::string($proposal['proposed_title']);
            $updateFields['meta_title'] = Sanitizer::string($proposal['proposed_title']);
        }

        if (!empty($proposal['proposed_excerpt'])) {
            $updateFields['excerpt'] = Sanitizer::string($proposal['proposed_excerpt']);
            $updateFields['meta_description'] = Sanitizer::string($proposal['proposed_excerpt']);
        }

        Database::update('articles', $updateFields, 'id = :id', ['id' => $articleId]);

        Logger::info("Article #{$articleId} successfully updated with verified revision", [
            'reason' => $proposal['change_summary'],
            'updated_at' => $now
        ]);

        return [
            'success' => true,
            'updated' => true,
            'article_id' => $articleId,
            'change_summary' => $proposal['change_summary'],
            'updated_at' => $now
        ];
    }

    /**
     * Generate delta update proposal via Gemini
     */
    public function generateUpdateProposal(array $article, string $newFacts, string $sourceName, string $sourceUrl): array {
        $prompt = <<<PROMPT
You are a senior Indian education news editor at Sarkari.online, an independent education and recruitment platform.
We have an existing published article that may need an incremental factual update based on new official releases.

RULES:
- NEVER rewrite an article just to make it look fresh.
- ONLY modify sections affected by verified new facts (e.g. date change, result declared, link active, corrigendum).
- PRESERVE the existing structure, clarity, and tone.
- If there are NO meaningful changes or new information is already covered, set has_meaningful_update to false.

EXISTING ARTICLE:
Title: {$article['title']}
Category: {$article['category_name']}
Current Content:
{$article['content']}

NEW OFFICIAL RELEASE DATA:
Source: {$sourceName} ({$sourceUrl})
New Information:
{$newFacts}

Respond ONLY in strict JSON matching this schema:
{
  "has_meaningful_update": true,
  "update_type": "date_amendment" | "result_activated" | "corrigendum" | "none",
  "change_summary": "Short explanation of exactly what changed (e.g. 'Updated examination date from May 10 to May 25, 2026 per official notice')",
  "proposed_title": "Updated headline if appropriate, or keep current",
  "proposed_excerpt": "Updated short summary if needed",
  "proposed_content": "Complete HTML content incorporating the new verified facts while preserving existing valid sections"
}
PROMPT;

        $gemini = new Gemini();
        $response = $gemini->generateJson($prompt, [
            'temperature' => 0.1,
            'stage' => 'article_update'
        ]);

        if (empty($response['data'])) {
            return [
                'has_meaningful_update' => false,
                'change_summary' => 'AI update proposal generation failed.'
            ];
        }

        return $response['data'];
    }

    /**
     * Get revision history for an article
     */
    public static function getUpdateHistory(int $articleId): array {
        $sql = "SELECT * FROM article_updates WHERE article_id = :aid ORDER BY id DESC";
        return Database::fetchAll($sql, ['aid' => $articleId]);
    }
}
