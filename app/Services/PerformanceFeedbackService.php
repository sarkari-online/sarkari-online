<?php
/**
 * Sarkari.online - Post-Publishing Performance Feedback Loop Service
 * Evaluates real-time performance telemetry (impressions, clicks, views, traffic trends)
 * and executes lifecycle actions: Refresh, Expand, Merge, Retire, and Discovered Query Feed.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\TopicDiscoveryEngine;

class PerformanceFeedbackService {

    /**
     * Run performance evaluation across published articles
     * 
     * @return array Summary of actions executed
     */
    public static function evaluate(): array {
        $summary = [
            'refreshed' => 0,
            'expanded' => 0,
            'cannibalization_flagged' => 0,
            'retired' => 0,
            'discovered_queries' => 0
        ];

        try {
            // 1. Fetch published articles with their traffic metrics
            $articles = Database::fetchAll(
                "SELECT a.id, a.title, a.slug, a.category_id, a.published_at, a.updated_at,
                        COUNT(ae.id) as view_count
                 FROM articles a
                 LEFT JOIN analytics_events ae ON ae.article_id = a.id
                 WHERE a.status = 'published'
                 GROUP BY a.id
                 ORDER BY view_count DESC"
            );

            if (empty($articles)) {
                return $summary;
            }

            $now = time();
            $highTrafficThreshold = 20; // top performing threshold

            foreach ($articles as $art) {
                $artId = (int)$art['id'];
                $views = (int)$art['view_count'];
                $ageDays = ($now - strtotime($art['published_at'])) / 86400;

                // Action A: EXPAND — High-performing article -> Spin off long-tail sub-topic seeds
                if ($views >= $highTrafficThreshold) {
                    $expandedSeeds = self::generateSubTopicSeeds($art['title']);
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
                                    'feedback_action' => 'expand_sub_intent'
                                ])
                            ]);
                            $summary['expanded']++;
                        }
                    }
                }

                // Action B: REFRESH — Article older than 30 days with steady traffic -> Update timestamp & check year
                if ($ageDays >= 30 && $views > 5) {
                    $currentYear = date('Y');
                    $titleYear = '';
                    if (preg_match('/\b(202[45])\b/', $art['title'], $ym)) {
                        // Title still has old year, schedule for title update
                        Logger::info("PerformanceFeedback: Article #{$artId} has outdated year {$ym[1]} in title.");
                        $summary['refreshed']++;
                    }
                }

                // Action C: RETIRE — Cyclical notice older than 180 days with 0 traffic -> Log for archive
                if ($ageDays > 180 && $views === 0) {
                    // Check if it was an event/admit card notice
                    if (str_contains(mb_strtolower($art['title']), 'admit card') || str_contains(mb_strtolower($art['title']), 'exam date')) {
                        Logger::info("PerformanceFeedback: Article #{$artId} ('{$art['title']}') is a dormant cyclical notice (>180d, 0 views). Candidate for archival.");
                        $summary['retired']++;
                    }
                }
            }

            // Action D: MERGE / Cannibalization Detection
            $summary['cannibalization_flagged'] = self::detectCannibalization($articles);

            Logger::info("PerformanceFeedback completed: " . json_encode($summary));
            return $summary;

        } catch (\Throwable $e) {
            Logger::error("PerformanceFeedback error: " . $e->getMessage());
            return $summary;
        }
    }

    /**
     * Generate logical long-tail sub-topic seeds from a winning article
     */
    private static function generateSubTopicSeeds(string $title): array {
        $seeds = [];
        $cleanTitle = trim(preg_replace('/\s*[-–—|].*$/', '', $title)); // remove site suffix

        // If title is about an exam, generate deep-dive sub-intents
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
     * Detect pairs of articles with >80% overlapping search intent (Cannibalization)
     */
    private static function detectCannibalization(array $articles): int {
        $flagged = 0;
        $count = min(count($articles), 60); // Check top 60 recent/popular articles

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

                if ($overlap >= 0.85) {
                    Logger::warning("PerformanceFeedback: Search intent cannibalization detected between Article #{$a1['id']} ('{$a1['title']}') and Article #{$a2['id']} ('{$a2['title']}'). Suggested action: Merge subordinate to canonical.");
                    $flagged++;
                }
            }
        }

        return $flagged;
    }
}
