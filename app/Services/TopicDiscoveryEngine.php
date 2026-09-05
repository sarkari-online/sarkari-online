<?php
/**
 * Sarkari.online - Topic Discovery & Semantic Intent Consolidation Engine
 * Manages combinatorial validation, semantic intent normalization, duplicate clustering,
 * search volume attribution (without fabricated data), and tiered publishing gates.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\AuthorityVerificationService;

class TopicDiscoveryEngine {

    /**
     * Stop-words and noisy synonym modifiers to strip for semantic intent clustering
     */
    private const INTENT_NOISE_WORDS = [
        'link', 'direct link', 'check here', 'online', 'download', 'today', 'live updates',
        'declared', 'announced', 'out', 'active now', 'official link', 'pdf', 'click here'
    ];

    /**
     * Normalize a topic to its core semantic intent for deduplication
     */
    public static function extractCanonicalIntent(string $keyword): string {
        $clean = mb_strtolower(trim($keyword));
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);

        foreach (self::INTENT_NOISE_WORDS as $w) {
            $clean = preg_replace('/\b' . preg_quote($w, '/') . '\b/i', '', $clean);
        }

        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean);
    }

    /**
     * Check if a candidate topic overlaps >80% with an existing published article's search intent
     */
    public static function checkDuplicateSearchIntent(string $keyword): array {
        $canonical = self::extractCanonicalIntent($keyword);
        $candidateWords = array_filter(explode(' ', $canonical), fn($w) => strlen($w) > 2);

        if (empty($candidateWords)) {
            return ['is_duplicate' => false, 'matched_id' => null, 'overlap' => 0];
        }

        // Check against recent published articles
        $articles = Database::fetchAll("SELECT id, title, slug FROM articles WHERE status = 'published' ORDER BY id DESC LIMIT 200");

        foreach ($articles as $art) {
            $artCanonical = self::extractCanonicalIntent($art['title']);
            $artWords = array_filter(explode(' ', $artCanonical), fn($w) => strlen($w) > 2);

            if (empty($artWords)) {
                continue;
            }

            $intersection = array_intersect($candidateWords, $artWords);
            $overlap = count($intersection) / max(count($candidateWords), count($artWords));

            if ($overlap >= 0.80) {
                return [
                    'is_duplicate' => true,
                    'matched_id' => (int)$art['id'],
                    'matched_title' => $art['title'],
                    'overlap' => round($overlap * 100, 1)
                ];
            }
        }

        return ['is_duplicate' => false, 'matched_id' => null, 'overlap' => 0];
    }

    /**
     * Evaluate Hard Quality Gates that override any score
     * 
     * @param string $keyword
     * @param array $sourceInfo
     * @return array ['pass' => bool, 'reason' => string|null]
     */
    public static function evaluateHardQualityGates(string $keyword, array $sourceInfo = []): array {
        $kwLower = mb_strtolower($keyword);

        // 1. Expired Historical Cycles
        if (preg_match('/\b(202[0-4])\b/i', $kwLower)) {
            return ['pass' => false, 'reason' => 'Expired historical cycle (pre-2025).'];
        }

        // 2. Administrative Noise / Speculation / PIL Hearing Fluff
        $fluffPatterns = [
            'grievance portal', 'feedback form', 'helpline number', 'complaint cell',
            'sc told', 'plea filed', 'high court stays', 'pil in supreme court', 'hearing postponed',
            'says cm', 'minister says', 'cabinet discusses', 'paper leak probe', 'fir registered',
            'dress code row', 'mobile phone ban', 'school bag policy'
        ];
        foreach ($fluffPatterns as $fp) {
            if (str_contains($kwLower, $fp)) {
                return ['pass' => false, 'reason' => "Administrative noise / non-actionable gossip ('{$fp}')."];
            }
        }

        // 3. Authority Source Tier Check
        $sourceUrl = $sourceInfo['source_url'] ?? ($sourceInfo['url'] ?? '');
        if (!empty($sourceUrl)) {
            $auth = AuthorityVerificationService::verify($sourceUrl);
            if (!$auth['is_valid'] && empty($sourceInfo['skip_auth_check'])) {
                // Not completely fatal if discovered via search trends, but flagged
                Logger::info("TopicDiscovery: Non-statutory source for '{$keyword}': {$sourceUrl}");
            }
        }

        // 4. Duplicate Intent Check
        $dupCheck = self::checkDuplicateSearchIntent($keyword);
        if ($dupCheck['is_duplicate']) {
            return [
                'pass' => false,
                'reason' => "Search intent duplicates Article #{$dupCheck['matched_id']} ('{$dupCheck['matched_title']}') with {$dupCheck['overlap']}% overlap."
            ];
        }

        return ['pass' => true, 'reason' => null];
    }

    /**
     * Classify priority score into publishing tiers:
     * - 90+ Priority
     * - 80–89 Approved
     * - 65–79 Review/Wait
     * - <65 Reject
     */
    public static function classifyPublishingTier(int $score): array {
        if ($score >= 90) {
            return [
                'tier' => 'priority',
                'action' => 'fast_track_publish',
                'status' => 'approved',
                'description' => 'Tier 1 Mega National Milestone (90+ Priority Queue)'
            ];
        }

        if ($score >= 80) {
            return [
                'tier' => 'approved',
                'action' => 'standard_publish',
                'status' => 'approved',
                'description' => 'Tier 2 Sectoral / High-Demand Guide (80–89 Approved Queue)'
            ];
        }

        if ($score >= 65) {
            return [
                'tier' => 'review_wait',
                'action' => 'hold_for_signal',
                'status' => 'detected', // Kept in detected state until notice confirmation
                'description' => 'Needs active notice confirmation or higher demand signal (65–79 Review/Wait)'
            ];
        }

        return [
            'tier' => 'rejected',
            'action' => 'reject',
            'status' => 'rejected',
            'description' => 'Low search intent or insufficient audience demand (<65 Reject)'
        ];
    }

    /**
     * Build standard Search Volume Attribution payload (Zero fabricated numbers)
     */
    public static function buildSearchVolumePayload(?int $exactVolume, string $source, string $scale, string $confidence = 'high'): array {
        return [
            'exact_keyword_volume' => [
                'value' => $exactVolume, // explicitly null if unverified
                'source' => $source,
                'period' => 'monthly',
                'country' => 'IN',
                'confidence' => $confidence
            ],
            'cluster_demand' => [
                'scale' => $scale, // e.g. 'mega_1m_plus', 'high_100k_500k', 'medium_20k_100k'
                'basis' => 'Official examination commission registration volume'
            ]
        ];
    }
}
