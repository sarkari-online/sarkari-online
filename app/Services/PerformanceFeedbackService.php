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
    public const DEFAULT_HEURISTICS = [
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
     * @param array|null $syntheticArticles Optional in-memory articles array for deterministic synthetic testing
     * @param bool $persistToDb Whether to persist discovered seeds to DB (false during testing)
     * @return array Summary of actions executed
     */
    public static function evaluate(array $customHeuristics = [], ?array $syntheticArticles = null, bool $persistToDb = true): array {
        $heuristics = array_merge(self::DEFAULT_HEURISTICS, $customHeuristics);

        $summary = [
            'total_analyzed' => 0,
            'refreshed' => 0,
            'refreshed_articles' => [],
            'expanded' => 0,
            'expanded_seeds' => [],
            'cannibalization_flagged' => 0,
            'cannibalization_pairs' => [],
            'retired' => 0,
            'retired_articles' => [],
            'discovered_queries' => 0
        ];

        try {
            // Fetch articles from database or use provided synthetic dataset
            if ($syntheticArticles !== null) {
                $articles = $syntheticArticles;
            } else {
                AnalyticsService::ensureTableExists();
                $articles = Database::fetchAll(
                    "SELECT a.id, a.title, a.slug, a.category_id, a.published_at, a.updated_at,
                            COUNT(pv.id) as lifetime_views,
                            SUM(CASE WHEN pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_7d_views,
                            SUM(CASE WHEN pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND pv.viewed_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as previous_7d_views
                     FROM articles a
                     LEFT JOIN page_views pv ON pv.article_id = a.id
                     WHERE a.status = 'published'
                     GROUP BY a.id
                     ORDER BY lifetime_views DESC"
                );
            }

            if (empty($articles)) {
                return $summary;
            }

            $summary['total_analyzed'] = count($articles);
            $now = time();

            foreach ($articles as $art) {
                $artId = (int)$art['id'];
                $lifetimeViews = (int)($art['lifetime_views'] ?? ($art['view_count'] ?? 0));
                $recentViews = (int)($art['recent_7d_views'] ?? 0);
                $previousViews = (int)($art['previous_7d_views'] ?? 0);
                $ageDays = ($now - strtotime($art['published_at'])) / 86400;

                // Determine trend direction (growing, stable, declining)
                $trendDirection = 'stable';
                if ($recentViews > ($previousViews * 1.3) && $recentViews >= 5) {
                    $trendDirection = 'growing';
                } elseif ($recentViews < ($previousViews * 0.7) && $previousViews >= 5) {
                    $trendDirection = 'declining';
                }

                // Determine query intent class and lifecycle
                $lifecycle = TopicDiscoveryEngine::resolveTopicLifecycle($art['title']);

                // --- HEURISTIC ACTION A: EXPAND & NEW DISCOVERY ---
                // Requires: Sample size met, top-performing views, and growing or stable trend
                if ($lifetimeViews >= $heuristics['expand_view_threshold'] && in_array($trendDirection, ['growing', 'stable'], true)) {
                    $expandedSeeds = self::generateSubTopicSeeds($art['title']);
                    if (count($expandedSeeds) >= $heuristics['min_query_diversity']) {
                        foreach ($expandedSeeds as $seed) {
                            $summary['discovered_queries']++;
                            $summary['expanded_seeds'][] = [
                                'seed' => $seed,
                                'origin_article_id' => $artId,
                                'trend_direction' => $trendDirection
                            ];

                            if ($persistToDb) {
                                $hash = TrendService::normalizedHash($seed);
                                $exists = Database::fetchOne("SELECT id FROM trends WHERE normalized_hash = :hash LIMIT 1", ['hash' => $hash]);
                                if (!$exists) {
                                    Database::insert('trends', [
                                        'keyword' => $seed,
                                        'normalized_hash' => $hash,
                                        'source' => 'Performance Feedback Loop (Expanded Intent)',
                                        'url' => SITE_URL . '/article/' . ($art['slug'] ?? '') . '/',
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
                            } else {
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
                        $summary['refreshed_articles'][] = [
                            'article_id' => $artId,
                            'title' => $art['title'],
                            'outdated_year' => $ym[1]
                        ];
                    }
                }

                // --- HEURISTIC ACTION C: RETIRE ---
                // Only for concluded event notices older than retire_dormant_days with 0 traffic in observation window
                if ($ageDays > $heuristics['retire_dormant_days'] && $recentViews === 0 && $lifecycle === 'CLOSED') {
                    Logger::info("PerformanceFeedback: Article #{$artId} ('{$art['title']}') is a dormant concluded notice (>180d, 0 views). Flagged as ARCHIVE_CANDIDATE.");
                    $summary['retired']++;
                    $summary['retired_articles'][] = [
                        'article_id' => $artId,
                        'title' => $art['title'],
                        'lifecycle' => 'CLOSED'
                    ];
                }
            }

            // --- HEURISTIC ACTION D: MERGE / Cannibalization Detection ---
            $cannibalizationResult = self::detectCannibalization($articles, $heuristics['cannibalization_threshold']);
            $summary['cannibalization_flagged'] = $cannibalizationResult['count'];
            $summary['cannibalization_pairs'] = $cannibalizationResult['pairs'];

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
    public static function generateSubTopicSeeds(string $title): array {
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
    public static function detectCannibalization(array $articles, float $threshold): array {
        $flagged = 0;
        $pairs = [];
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
                    $flagged++;
                    $pairs[] = [
                        'article_1' => ['id' => $a1['id'], 'title' => $a1['title']],
                        'article_2' => ['id' => $a2['id'], 'title' => $a2['title']],
                        'overlap' => round($overlap * 100, 1)
                    ];
                    Logger::warning("PerformanceFeedback: Search intent cannibalization ({$overlap}) detected between Article #{$a1['id']} ('{$a1['title']}') and Article #{$a2['id']} ('{$a2['title']}').");
                }
            }
        }

        return ['count' => $flagged, 'pairs' => $pairs];
    }
}
