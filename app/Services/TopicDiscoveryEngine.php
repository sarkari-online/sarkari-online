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
        try {
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
        } catch (\Throwable $e) {
            Logger::error("checkDuplicateSearchIntent error: " . $e->getMessage());
            return ['is_duplicate' => false, 'matched_id' => null, 'overlap' => 0];
        }
    }

    /**
     * Determine the lifecycle state of a topic (ACTIVE, HISTORICAL_BENCHMARK, EVERGREEN_PREPARATION, EVERGREEN_SYLLABUS, EVERGREEN_CAREER, CLOSED, ARCHIVE_CANDIDATE)
     */
    public static function resolveTopicLifecycle(string $keyword): string {
        $kwLower = mb_strtolower($keyword);

        // Historical value intents that are permanently valuable to aspirants
        if (preg_match('/(cut\s*off|cutoff|merit list|scorecard|result|marksheet)/i', $kwLower)) {
            return preg_match('/\b(202[0-5])\b/i', $kwLower) ? 'HISTORICAL_BENCHMARK' : 'ACTIVE';
        }

        if (preg_match('/(previous year paper|model paper|question paper|answer key|response sheet)/i', $kwLower)) {
            return 'EVERGREEN_PREPARATION';
        }

        if (preg_match('/(syllabus|exam pattern|negative marking|marking scheme)/i', $kwLower)) {
            return 'EVERGREEN_SYLLABUS';
        }

        if (preg_match('/(salary|pay matrix|pay scale|in-hand salary|grade pay|job profile|eligibility|age limit)/i', $kwLower)) {
            return 'EVERGREEN_CAREER';
        }

        // Concluded transactional registration/apply forms for expired years
        if (preg_match('/\b(202[0-4])\b/i', $kwLower) && preg_match('/(apply online|registration form|admit card download)/i', $kwLower)) {
            return 'CLOSED';
        }

        return 'ACTIVE';
    }

    /**
     * Evaluate Hard Quality Gates that override any score
     * 
     * @param string $keyword
     * @param array $sourceInfo
     * @return array ['pass' => bool, 'reason' => string|null, 'lifecycle' => string]
     */
    public static function evaluateHardQualityGates(string $keyword, array $sourceInfo = []): array {
        $kwLower = mb_strtolower($keyword);
        $lifecycle = self::resolveTopicLifecycle($keyword);

        // 1. Check Lifecycle — Never auto-reject historical results, cutoffs, papers, syllabus, or salaries!
        // Only reject if an expired cycle actively claims an active registration/apply-online window
        if ($lifecycle === 'CLOSED') {
            return [
                'pass' => false,
                'reason' => 'Application/Registration cycle concluded (CLOSED). Historical benchmarks and syllabus remain preserved under historical/evergreen lifecycle.',
                'lifecycle' => 'CLOSED'
            ];
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
                return ['pass' => false, 'reason' => "Administrative noise / non-actionable gossip ('{$fp}').", 'lifecycle' => $lifecycle];
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
                'reason' => "Search intent duplicates Article #{$dupCheck['matched_id']} ('{$dupCheck['matched_title']}') with {$dupCheck['overlap']}% overlap.",
                'lifecycle' => $lifecycle
            ];
        }

        return ['pass' => true, 'reason' => null, 'lifecycle' => $lifecycle];
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
     * Build standard Search Volume Attribution payload with 5 distinct signals
     * (Applicant/registration counts must NEVER be represented as search volume;
     * discovery clustering is explicitly treated as an opportunity heuristic)
     */
    public static function buildSearchVolumePayload(?int $exactVolume, string $opportunityTier, string $applicantScale, string $confidence = 'unverified'): array {
        return [
            'exact_keyword_volume' => [
                'value' => $exactVolume, // null if actual volume is unverified
                'confidence' => $confidence
            ],
            'cluster_discovery_opportunity' => [
                'opportunity_tier' => $opportunityTier,
                'signal_type' => 'discovery_opportunity_clustering',
                'basis' => 'national_search_intent_matrix'
            ],
            'applicant_scale' => [
                'applicant_tier' => $applicantScale,
                'source' => 'official_commission_historical_filings'
            ],
            'trend_signal' => 'active_cycle',
            'official_event_signal' => 'statutory_portal_bulletin'
        ];
    }
}
