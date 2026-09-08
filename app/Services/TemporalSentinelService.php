<?php
/**
 * Sarkari.online - Autonomous Sentinel & Self-Healing Anomaly Detector
 *
 * Continuously audits all published articles for semantic and lifecycle anomalies,
 * intercepts invalid state transitions before database commits, and automatically
 * rolls back and repairs any false transitions without requiring manual intervention.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Services\TemporalFactService;
use DateTimeImmutable;
use Throwable;

class TemporalSentinelService {

    /**
     * Non-exam topics that must NEVER transition to exam event stages (admit card, exam completed, result)
     */
    public const INELIGIBLE_EXAM_KEYWORDS = [
        'counselling',
        'counseling',
        'seat allotment',
        'allotment',
        'admission',
        'scholarship',
        'fellowship',
        'guide',
        'preparation',
        'syllabus',
        'eligibility',
        'otr',
        'one time registration',
        'how to',
        'correction',
        'selection process',
        'full form',
        'calculator',
        'salary',
        'age limit',
        'documents required',
        'verification steps'
    ];

    /**
     * Validate a proposed lifecycle transition against semantic invariants before persisting
     */
    public static function validateTransition(array $article, string $targetStatus, array $context = []): array {
        $title = $article['title'] ?? '';
        $slug = $article['slug'] ?? '';
        $combined = mb_strtolower($title . ' ' . $slug);

        // 1. Invariant: Non-exam articles can NEVER transition to Admit Card Released
        if ($targetStatus === TemporalFactService::LIFECYCLE_ADMIT_CARD_RELEASED) {
            foreach (self::INELIGIBLE_EXAM_KEYWORDS as $kw) {
                if (str_contains($combined, $kw)) {
                    return [
                        'allowed' => false,
                        'reason' => "Semantic Invariant Violation: Article '{$title}' is an educational/counselling guide ('{$kw}') and cannot have an Admit Card."
                    ];
                }
            }

            // Verify specific exam token presence in portal text if provided
            $portalText = $context['portal_text'] ?? '';
            if (!empty($portalText)) {
                $examTokens = self::extractExamTokens($title);
                if (empty($examTokens)) {
                    return [
                        'allowed' => false,
                        'reason' => "Identification Failure: No distinct exam acronym found in title to ground portal match."
                    ];
                }

                $tokenRegex = '(?:' . implode('|', $examTokens) . ')';
                $admitPattern = '/(?:' . $tokenRegex . '.{0,120}?(?:admit card|hall ticket|call letter)\s+(?:is\s+)?(?:released|out|available|download|live)|(?:admit card|hall ticket|call letter)\s+(?:is\s+)?(?:released|out|available|download|live).{0,120}?' . $tokenRegex . ')/is';

                if (!preg_match($admitPattern, $portalText)) {
                    return [
                        'allowed' => false,
                        'reason' => "Proximity Mismatch: Portal text does not mention the specific exam token (" . implode(', ', $examTokens) . ") near admit card."
                    ];
                }
            }
        }

        // 2. Invariant: Non-exam articles can NEVER transition to Result Released
        if ($targetStatus === TemporalFactService::LIFECYCLE_RESULT_RELEASED) {
            foreach (['guide', 'syllabus', 'eligibility', 'otr', 'full form', 'calculator', 'salary'] as $kw) {
                if (str_contains($combined, $kw)) {
                    return [
                        'allowed' => false,
                        'reason' => "Semantic Invariant Violation: Evergreen guide ('{$kw}') cannot have a Result Released transition."
                    ];
                }
            }
        }

        return ['allowed' => true, 'reason' => 'Passed all invariant checks'];
    }

    /**
     * Autonomous Self-Healing Sweeper:
     * Scans corpus for anomalies and immediately self-repairs any false transitions
     */
    public static function autoRepairCorpus(): array {
        $repairs = [];

        try {
            // Check 1: Ineligible articles trapped in ADMIT_CARD_RELEASED
            $candidates = Database::fetchAll(
                "SELECT id, title, slug, content, lifecycle_status FROM articles WHERE lifecycle_status = 'admit_card_released'"
            );

            foreach ($candidates as $art) {
                $id = (int)$art['id'];
                $combined = mb_strtolower($art['title'] . ' ' . $art['slug']);

                foreach (self::INELIGIBLE_EXAM_KEYWORDS as $kw) {
                    if (str_contains($combined, $kw)) {
                        $repaired = self::repairFalseAdmitCardTransition($id, $art, $kw);
                        if ($repaired) {
                            $repairs[] = $repaired;
                        }
                        break;
                    }
                }
            }

            // Check 2: Articles with false "— Admit Card Released" in title but not an exam
            $mismatchedTitles = Database::fetchAll(
                "SELECT id, title, slug, content, lifecycle_status FROM articles WHERE title LIKE '%Admit Card Released%'"
            );

            foreach ($mismatchedTitles as $art) {
                $id = (int)$art['id'];
                $combined = mb_strtolower($art['title'] . ' ' . $art['slug']);

                foreach (self::INELIGIBLE_EXAM_KEYWORDS as $kw) {
                    if (str_contains($combined, $kw)) {
                        $repaired = self::repairFalseAdmitCardTransition($id, $art, $kw);
                        if ($repaired) {
                            $repairs[] = $repaired;
                        }
                        break;
                    }
                }
            }

        } catch (Throwable $e) {
            Logger::error("TemporalSentinelService autoRepairCorpus error: " . $e->getMessage());
        }

        if (!empty($repairs)) {
            Logger::info("TemporalSentinelService: Autonomously repaired " . count($repairs) . " articles.");
        }

        return [
            'repaired_count' => count($repairs),
            'repairs' => $repairs
        ];
    }

    /**
     * Helper: Automatically rollback a false Admit Card transition
     */
    private static function repairFalseAdmitCardTransition(int $articleId, array $articleData, string $violatingKeyword): ?array {
        $title = $articleData['title'];
        $cleanTitle = preg_replace('/\s*(?:—|-|:)?\s*Admit Card Released.*$/i', '', $title);

        $content = $articleData['content'];
        // Strip false admit card banner if injected
        $cleanContent = preg_replace('/<div[^>]*notice-box[^>]*>.*?Admit Card Released!.*?<\/div>/is', '', $content);

        // Fetch last authentic content snapshot before false transition if available
        $lastSnapshot = Database::fetchOne(
            "SELECT old_content FROM article_updates WHERE article_id = :id AND reason LIKE '%Admit Card%' ORDER BY id DESC LIMIT 1",
            ['id' => $articleId]
        );

        if (!empty($lastSnapshot['old_content'])) {
            $cleanContent = $lastSnapshot['old_content'];
        }

        // Record autonomous repair action in article_updates
        Database::insert('article_updates', [
            'article_id' => $articleId,
            'old_content' => $content,
            'new_content' => $cleanContent,
            'reason' => "Autonomous Sentinel Self-Healing: Detected and auto-repaired false admit card transition on ineligible topic ('{$violatingKeyword}'). Restored factual state.",
            'source_url' => 'https://sarkari.online',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Revert article in database
        Database::update('articles', [
            'title' => Sanitizer::string($cleanTitle),
            'content' => Sanitizer::html($cleanContent),
            'lifecycle_status' => TemporalFactService::LIFECYCLE_CLOSED,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $articleId]);

        // Purge fake admit card date from facts
        Database::delete('article_temporal_facts', 'article_id = :id AND fact_name = :fn', [
            'id' => $articleId,
            'fn' => 'admit_card_date'
        ]);

        return [
            'article_id' => $articleId,
            'original_title' => $title,
            'repaired_title' => $cleanTitle,
            'keyword_flagged' => $violatingKeyword,
            'action' => 'reverted_to_closed'
        ];
    }

    /**
     * Helper: Extract distinct exam acronyms / tokens from title
     */
    public static function extractExamTokens(string $title): array {
        $examTokens = [];
        if (preg_match_all('/\b([A-Za-z0-9]{2,12})\b/i', $title, $mTokens)) {
            $stopWords = ['2026', '2025', '2024', 'exam', 'cbt', 'recruitment', 'post', 'posts', 'date', 'dates', 'online', 'apply', 'form', 'link', 'hall', 'card', 'pass', 'admit', 'the', 'and', 'for', 'all', 'out', 'new', 'update', 'updates', 'notice', 'official', 'status', 'portal', 'schedule', 'tier', 'session', 'round'];
            foreach ($mTokens[1] as $token) {
                $tLower = strtolower($token);
                if (!in_array($tLower, $stopWords, true) && strlen($token) >= 3) {
                    $examTokens[] = preg_quote($token, '/');
                }
            }
        }
        return array_values(array_unique($examTokens));
    }
}
