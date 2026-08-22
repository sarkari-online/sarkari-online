<?php
/**
 * EduPulse - Content Generation Pipeline Orchestrator (Phase 5)
 * Coordinates the full automated article creation lifecycle:
 * Approved Trend → Source Research → ArticleGenerator → FactChecker → ContentEditor
 * → InternalLinker → SEOGenerator → 8-Dimension Quality Calculation → Safety Gates → Storage.
 */

namespace App\Services;

use App\AI\ArticleGenerator;
use App\AI\FactChecker;
use App\AI\ContentEditor;
use App\AI\InternalLinker;
use App\AI\SEOGenerator;
use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use Exception;
use Throwable;

class PipelineService {

    private ArticleGenerator $generator;
    private FactChecker $checker;
    private ContentEditor $editor;
    private InternalLinker $linker;
    private SEOGenerator $seoGen;

    public function __construct(
        ?ArticleGenerator $generator = null,
        ?FactChecker $checker = null,
        ?ContentEditor $editor = null,
        ?InternalLinker $linker = null,
        ?SEOGenerator $seoGen = null
    ) {
        $this->generator = $generator ?: new ArticleGenerator();
        $this->checker = $checker ?: new FactChecker();
        $this->editor = $editor ?: new ContentEditor();
        $this->linker = $linker ?: new InternalLinker();
        $this->seoGen = $seoGen ?: new SEOGenerator();
    }

    /**
     * Generate complete article from an approved trend
     */
    public function generateFromTrend(int $trendId): array {
        $trend = Database::fetchOne("SELECT * FROM trends WHERE id = :id", ['id' => $trendId]);
        if (!$trend) {
            throw new Exception("Trend #{$trendId} not found in database.");
        }

        // Prevent duplicate generation for same trend
        if ($trend['status'] === 'generated' || $trend['status'] === 'published') {
            $existing = Database::fetchOne("SELECT id, title, slug FROM articles WHERE trend_id = :tid LIMIT 1", ['tid' => $trendId]);
            if ($existing) {
                Logger::info("Trend #{$trendId} already generated as Article #{$existing['id']}");
                return ['success' => true, 'article_id' => (int)$existing['id'], 'status' => 'already_generated'];
            }
        }

        // Prevent generating duplicate article if topic is already published
        if (TrendService::existsAsArticle($trend['keyword'])) {
            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Similar article already published']]);
            Logger::info("Trend #{$trendId} skipped: similar article already exists in publication library.");
            return ['success' => false, 'trend_id' => $trendId, 'error' => 'Similar article already exists in publication library.'];
        }

        Logger::info("Starting content generation pipeline for Trend #{$trendId}: '{$trend['keyword']}'");

        // Parse raw payload / source information
        $rawPayload = [];
        if (!empty($trend['raw_payload'])) {
            $decoded = json_decode($trend['raw_payload'], true);
            if (is_array($decoded)) {
                $rawPayload = $decoded;
            }
        }

        $categorySlug = $trend['category_hint'] ?: 'exam-results';
        $cat = CategoryService::getBySlug($categorySlug);
        $categoryId = $cat ? (int)$cat['id'] : ($trend['category_id'] ?: 1);

        $sourceData = [
            'keyword' => $trend['keyword'],
            'source_name' => $rawPayload['source_attribution']['name'] ?? $trend['source'],
            'source_url' => $trend['url'],
            'reference' => $rawPayload['source_attribution']['reference'] ?? '',
            'notes' => $rawPayload['reasoning'] ?? $trend['keyword']
        ];

        // 1. Generate Draft
        $angle = $rawPayload['suggested_original_angle'] ?? 'Comprehensive student instructions';
        $genResult = $this->generator->generate($trend['keyword'], $sourceData, $categorySlug, $angle);
        usleep(2500000); // 2.5s pause to respect Gemini Free Tier RPM limits

        // 2. Fact Check
        $factAudit = $this->checker->check([
            'title' => $genResult['title'],
            'content' => $genResult['content']
        ], $sourceData);
        usleep(2500000);

        // 3. Editorial Polish & Format
        $polished = $this->editor->polish($genResult['title'], $genResult['content'], $categorySlug);
        usleep(2500000);

        // 4. Contextual Internal Linking
        $availableArticles = ArticleService::getLatestPublished(20);
        $linking = $this->linker->link($polished['edited_content'], $availableArticles);
        usleep(2500000);

        // 5. Search Engine Optimization (SEO)
        $seoData = $this->seoGen->generate($polished['edited_title'], $linking['linked_content'], $categorySlug);

        // 6. Calculate 8-Dimension Quality Score (Total 100 points)
        $quality = $this->calculateQualityScore([
            'fact_check' => $factAudit,
            'editor' => $polished,
            'linking' => $linking,
            'seo' => $seoData,
            'source_data' => $sourceData,
            'gen_result' => $genResult
        ]);

        // 7. Evaluate Safety Gates
        $safetyPass = $this->evaluateSafetyGates($factAudit, $rawPayload, $quality);

        // Determine target status
        $finalScore = $quality['total_score'];
        $finalStatus = 'draft';

        if (!$safetyPass['pass']) {
            $finalStatus = 'rejected';
            Logger::warning("Article rejected by safety gate: " . implode(', ', $safetyPass['reasons']));
        } elseif ($finalScore >= 90) {
            // High quality review ready (publish ready)
            $finalStatus = 'review';
        } elseif ($finalScore >= 80) {
            $finalStatus = 'review';
        } elseif ($finalScore >= 70) {
            $finalStatus = 'draft';
        } else {
            $finalStatus = 'rejected';
        }

        // 8. Persist Article in Database
        $author = Database::fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $authorId = $author ? (int)$author['id'] : null;

        $articleId = ArticleService::create([
            'trend_id' => $trendId,
            'title' => $polished['edited_title'],
            'slug' => $seoData['slug_suggestion'],
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'excerpt' => $seoData['excerpt'],
            'content' => $linking['linked_content'],
            'status' => $finalStatus,
            'quality_score' => $finalScore,
            'ai_generated' => 1,
            'source_verified' => !empty($sourceData['source_url']) ? 1 : 0,
            'source_name' => $sourceData['source_name'],
            'source_url' => $sourceData['source_url'],
            'source_ref' => $sourceData['reference'],
            'meta_title' => $seoData['seo_title'],
            'meta_description' => $seoData['meta_description'],
            'canonical_url' => url('article/' . $seoData['slug_suggestion'] . '/'),
            'og_title' => $seoData['seo_title'],
            'og_description' => $seoData['meta_description']
        ]);

        // 9. Record Detailed Quality Breakdown in article_checks table
        Database::insert('article_checks', [
            'article_id' => $articleId,
            'check_type' => 'quality_breakdown',
            'score' => $finalScore,
            'notes' => json_encode([
                'dimensions' => $quality['breakdown'],
                'safety_gate' => $safetyPass,
                'fact_recommendation' => $factAudit['recommendation'] ?? 'pass',
                'flagged_issues_count' => count($factAudit['flagged_issues'] ?? [])
            ], JSON_UNESCAPED_UNICODE),
            'checker' => 'ai',
            'checked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 10. Generate Branded WebP Thumbnail (Phase 6)
        $thumbnailService = new ThumbnailService();
        $thumbResult = $thumbnailService->generateForArticle($articleId);

        if (empty($thumbResult['success'])) {
            // Safety rule: if thumbnail generation fails, article must not be published/review ready
            $finalStatus = 'draft';
            Database::update('articles', ['status' => 'draft'], 'id = :id', ['id' => $articleId]);
            Logger::warning("Article #{$articleId} downgraded to draft because thumbnail generation failed.");
        }

        // 11. Update Trend Status to 'generated'
        TrendService::markStatus($trendId, 'generated', [
            'raw_payload' => array_merge($rawPayload, [
                'generated_article_id' => $articleId,
                'quality_score' => $finalScore,
                'thumbnail_path' => $thumbResult['relative_path'] ?? null
            ])
        ]);

        Logger::info("Pipeline successfully generated Article #{$articleId} (Status: {$finalStatus}, Quality Score: {$finalScore}) for Trend #{$trendId}");

        return [
            'success' => true,
            'article_id' => $articleId,
            'trend_id' => $trendId,
            'title' => $polished['edited_title'],
            'status' => $finalStatus,
            'quality_score' => $finalScore,
            'safety_pass' => $safetyPass['pass'],
            'thumbnail' => $thumbResult['relative_path'] ?? null
        ];
    }

    /**
     * Calculate 8-Part Quality Score (100 Points Total)
     * 
     * Fact accuracy       25
     * Original value      20
     * Search intent       15
     * Completeness        10
     * Source quality      10
     * Readability         10
     * SEO                  5
     * Internal linking      5
     */
    public function calculateQualityScore(array $data): array {
        $factAudit = $data['fact_check'] ?? [];
        $editor = $data['editor'] ?? [];
        $linking = $data['linking'] ?? [];
        $seo = $data['seo'] ?? [];
        $source = $data['source_data'] ?? [];
        $gen = $data['gen_result'] ?? [];

        // 1. Fact Accuracy (Max 25 pts)
        $factRaw = (int)($factAudit['factual_accuracy_score'] ?? 90);
        $factScore = round(($factRaw / 100) * 25);

        // 2. Original Value (Max 20 pts)
        $copiedRisk = $factAudit['copied_risk'] ?? 'low';
        $origScore = match($copiedRisk) {
            'low' => 20,
            'medium' => 12,
            default => 5
        };

        // 3. Search Intent (Max 15 pts)
        $intentScore = 15;
        if (empty($gen['title']) || strlen($gen['title']) < 15) {
            $intentScore = 8;
        }

        // 4. Completeness (Max 10 pts)
        $compScore = 0;
        if (!empty($gen['dates_table'])) $compScore += 4;
        if (!empty($gen['key_takeaways'])) $compScore += 3;
        if (!empty($gen['content']) && strlen($gen['content']) > 400) $compScore += 3;

        // 5. Source Quality (Max 10 pts)
        $srcScore = !empty($source['source_url']) ? 10 : 5;

        // 6. Readability (Max 10 pts)
        $readabilityRaw = (int)($editor['readability_score'] ?? 85);
        $readScore = round(($readabilityRaw / 100) * 10);

        // 7. SEO (Max 5 pts)
        $seoScore = 0;
        if (!empty($seo['seo_title']) && strlen($seo['seo_title']) <= 60) $seoScore += 2;
        if (!empty($seo['meta_description']) && strlen($seo['meta_description']) >= 100) $seoScore += 2;
        if (!empty($seo['slug_suggestion'])) $seoScore += 1;

        // 8. Internal Linking (Max 5 pts)
        $linksCount = (int)($linking['count'] ?? 0);
        $linkScore = min(5, $linksCount * 2.5);

        $total = $factScore + $origScore + $intentScore + $compScore + $srcScore + $readScore + $seoScore + $linkScore;
        $total = min(100, max(0, (int)$total));

        return [
            'total_score' => $total,
            'breakdown' => [
                'fact_accuracy' => $factScore,
                'original_value' => $origScore,
                'search_intent' => $intentScore,
                'completeness' => $compScore,
                'source_quality' => $srcScore,
                'readability' => $readScore,
                'seo' => $seoScore,
                'internal_linking' => $linkScore
            ]
        ];
    }

    /**
     * Safety Gates Evaluation
     */
    public function evaluateSafetyGates(array $factAudit, array $rawPayload, array $quality): array {
        $reasons = [];
        $pass = true;

        // Rule 1: Factual verification fails
        if (($factAudit['recommendation'] ?? '') === 'reject') {
            $pass = false;
            $reasons[] = 'Fact verification failed with reject recommendation';
        }

        // Rule 2: Hallucination risk high
        if (($factAudit['hallucination_risk'] ?? '') === 'high') {
            $pass = false;
            $reasons[] = 'High hallucination risk detected in drafted claims';
        }

        // Rule 3: Duplicate risk high
        if (($rawPayload['duplicate_risk'] ?? '') === 'high') {
            $pass = false;
            $reasons[] = 'High duplicate risk against existing catalog';
        }

        // Rule 4: Critical flagged issues
        if (!empty($factAudit['flagged_issues'])) {
            foreach ($factAudit['flagged_issues'] as $issue) {
                if (($issue['severity'] ?? '') === 'critical') {
                    $pass = false;
                    $reasons[] = 'Critical factual discrepancy: ' . ($issue['claim'] ?? '');
                }
            }
        }

        // Rule 5: Minimum fact accuracy
        if (($quality['breakdown']['fact_accuracy'] ?? 0) < 18) { // Less than 72% fact accuracy
            $pass = false;
            $reasons[] = 'Fact accuracy score below minimum safety threshold';
        }

        return [
            'pass' => $pass,
            'reasons' => $reasons
        ];
    }

    /**
     * Run batch article generation on approved trends
     */
    public function processApprovedTrends(int $limit = 5): array {
        $sql = "SELECT id FROM trends WHERE status = 'approved' ORDER BY trend_score DESC, id ASC LIMIT " . (int)$limit;
        $approved = Database::fetchAll($sql);

        $results = [];
        foreach ($approved as $index => $tr) {
            if ($index > 0) {
                // Pause 5 seconds between articles to stay comfortably under Google Free Tier RPM limit
                sleep(5);
            }
            try {
                $res = $this->generateFromTrend((int)$tr['id']);
                $results[] = $res;
            } catch (Throwable $e) {
                Logger::error("Failed to generate article for Trend #{$tr['id']}: " . $e->getMessage());
                $results[] = ['success' => false, 'trend_id' => (int)$tr['id'], 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
