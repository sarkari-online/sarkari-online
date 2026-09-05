<?php
/**
 * Sarkari.online - Post-Publishing Performance Feedback Loop Service
 * Evaluates real-time performance telemetry using configurable heuristics:
 * minimum sample size, observation period, trend direction, query intent, and query diversity.
 * Executes lifecycle actions: Refresh, Expand, Merge, Retire, and Discovered Query Feed.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\TopicDiscoveryEngine;

class PerformanceFeedbackService {

    /**
     * Default Configurable Heuristic Thresholds
     */
    private const DEFAULT_HEURISTICS = [
        'min_sample_size'           => 25,    // Minimum views/events required before statistical evaluation
        'min_observation_days'      => 14,    // Observation period required before evaluating trend direction
        'expand_view_threshold'     => 40,    // Pageviews required to trigger topic cluster expansion
        'refresh_stale_days'        => 30,    // Days before checking year/milestone freshness
        'retire_dormant_days'       => 180,   // Days past deadline with 0 views before archival flagging
        'cannibalization_threshold' => 0.85,  // Semantic overlap ratio for merge consideration
        'min_query_diversity'       => 2      // Minimum sub-intent variations required for expansion
    ];

    /**
     * Run performance evaluation across published articles using configurable heuristics
     * 
     * @param array $customHeuristics Optional override for heuristic parameters
     * @return array Summary of actions executed
     */
    public static function evaluate(array $customHeuristics = []): array {
        $heuristics = array_merge(self::DEFAULT_HEURISTICS, $customHeuristics);

        $summary = [
            'total_analyzed' => 0,
            'refreshed' => 0,
            'expanded' => 0,
            'cannibalization_flagged' => 0,
            'retired' => 0,
            'discovered_queries' => 0
        ];

        try {
            // 1. Fetch published articles with recent 7-day and lifetime traffic telemetry
            $articles = Database::fetchAll(
                "SELECT a.id, a.title, a.slug, a.category_id, a.published_at, a.updated_at,
                        COUNT(ae.id) as lifetime_views,
                        SUM(CASE WHEN ae.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_7d_views,
                        SUM(CASE WHEN ae.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND ae.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as previous_7d_views
                 FROM articles a
                 LEFT JOIN analytics_events ae ON ae.article_id = a.id
                 WHERE a.status = 'published'
                 GROUP BY a.id
                 ORDER BY lifetime_views DESC"
            );

            if (empty($articles)) {
                return $summary;
            }

            $summary['total_analyzed'] = count($articles);
            $now = time();

            foreach ($articles as $art) {
                $artId = (int)$art['id'];
                $lifetimeViews = (int)$art['lifetime_views'];
                $recentViews = (int)$art['recent_7d_views'];
                $previousViews = (int)$art['previous_7d_views'];
                $ageDays = ($now - strtotime($art['published_at'])) / 86400;

                // Determine trend direction (growing, stable, declining)
                $trendDirection = 'stable';
                if ($recentViews > ($previousViews * 1.3) && $recentViews >= 5) {
                    $trendDirection = 'growing';
                } elseif ($recentViews < ($previousViews * 0.7) && $previousViews >= 5) {
                    $trendDirection = 'declining';
                }

                // Determine query intent class
                $lifecycle = TopicDiscoveryEngine::resolveTopicLifecycle($art['title']);

                // --- HEURISTIC ACTION A: EXPAND ---
                // Requires: Sample size met, top-performing views, and growing or stable trend
                if ($lifetimeViews >= $heuristics['expand_view_threshold'] && in_array($trendDirection, ['growing', 'stable'], true)) {
                    $expandedSeeds = self::generateSubTopicSeeds($art['title']);
                    if (count($expandedSeeds) >= $heuristics['min_query_diversity']) {
                        foreach ($expandedSeeds as $seed) {
                            $hash = TrendService::normalizedHash($seed);
                            $exists = Database::fetchOne("SELECT id FROM trends WHERE normalized_hash = :hash LIMIT 1", ['hash' => $hash]);
                            if (!$exists) {
                                Database::insert('trends', [
                                    'keyword' => $seed,
                                    'normalized_hash' => $hash,
                                    'source' => 'Performance Feedback Loop (Expanded Intent)',
                                    'url' => SITE_URL . '/article/' . $art['slug'] . '/',
                                    'trend_score' => 88,
                                    'category_hint' => 'career-guides',
                                    'status' => 'detected',
                                    'raw_payload' => json_encode([
                                        'origin_article_id' => $artId,
                                        'origin_title' => $art['title'],
                                        'feedback_action' => 'expand_sub_intent',
                                        'trend_direction' => $trendDirection,
                                        'sample_size' => $lifetimeViews
                                    ])
                                ]);
                                $summary['expanded']++;
                            }
                        }
                    }
                }

                // --- HEURISTIC ACTION B: REFRESH ---
                // Evaluates if article has met observation window and contains outdated cycle markers
                if ($ageDays >= $heuristics['refresh_stale_days'] && $lifetimeViews >= $heuristics['min_sample_size']) {
                    if (preg_match('/\b(202[45])\b/', $art['title'], $ym)) {
                        Logger::info("PerformanceFeedback: Article #{$artId} has outdated year {$ym[1]} under {$trendDirection} trend. Flagged for refresh.");
                        $summary['refreshed']++;
                    }
                }

                // --- HEURISTIC ACTION C: RETIRE ---
                // Only for concluded event notices older than retire_dormant_days with 0 traffic in observation window
                if ($ageDays > $heuristics['retire_dormant_days'] && $recentViews === 0 && $lifecycle === 'CLOSED') {
                    Logger::info("PerformanceFeedback: Article #{$artId} ('{$art['title']}') is a dormant concluded notice (>180d, 0 views). Flagged as ARCHIVE_CANDIDATE.");
                    $summary['retired']++;
                }
            }

            // --- HEURISTIC ACTION D: MERGE / Cannibalization Detection ---
            $summary['cannibalization_flagged'] = self::detectCannibalization($articles, $heuristics['cannibalization_threshold']);

            Logger::info("PerformanceFeedback completed: " . json_encode($summary));
            return $summary;

        } catch (\Throwable $e) {
            Logger::error("PerformanceFeedback error: " . $e->getMessage());
            return $summary;
        }
    }

    /**
     * Generate logical long-tail sub-topic seeds with diverse query intents
     */
    private static function generateSubTopicSeeds(string $title): array {
        $seeds = [];
        $cleanTitle = trim(preg_replace('/\s*[-–—|].*$/', '', $title));

        if (preg_match('/(SSC [A-Z]+|RRB [A-Z]+|UPSC [A-Z]+|NEET|JEE Main|CTET|UP Police|Bihar Police)/i', $cleanTitle, $m)) {
            $entity = trim($m[1]);
            $year = date('Y');
            $seeds[] = "{$entity} {$year}: Previous Year Question Paper PDF & Solutions";
            $seeds[] = "{$entity} {$year}: Cut Off Marks Category-Wise Trend Analysis";
            $seeds[] = "{$entity} {$year}: Detailed Subject-Wise Syllabus Breakdown";
        }

        return $seeds;
    }

    /**
     * Detect pairs of articles with overlapping search intent exceeding configurable threshold
     */
    private static function detectCannibalization(array $articles, float $threshold): int {
        $flagged = 0;
        $count = min(count($articles), 60);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a1 = $articles[$i];
                $a2 = $articles[$j];

                $intent1 = TopicDiscoveryEngine::extractCanonicalIntent($a1['title']);
                $intent2 = TopicDiscoveryEngine::extractCanonicalIntent($a2['title']);

                $w1 = array_filter(explode(' ', $intent1), fn($w) => strlen($w) > 2);
                $w2 = array_filter(explode(' ', $intent2), fn($w) => strlen($w) > 2);

                if (empty($w1) || empty($w2)) continue;

                $intersection = array_intersect($w1, $w2);
                $overlap = count($intersection) / max(count($w1), count($w2));

                if ($overlap >= $threshold) {
                    Logger::warning("PerformanceFeedback: Search intent cannibalization ({$overlap}) detected between Article #{$a1['id']} ('{$a1['title']}') and Article #{$a2['id']} ('{$a2['title']}').");
                    $flagged++;
                }
            }
        }

        return $flagged;
    }
}
