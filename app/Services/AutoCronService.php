<?php
/**
 * Sarkari.online - In-Process Self-Healing Autonomous Cron Engine
 * Runs trend fetch, topic analysis, article generation, and publishing
 * 100% reliably in-process with zero impact on page load speed.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Env;
use App\AI\TopicAnalyzer;
use App\Services\TrendService;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\PipelineService;
use App\Services\PublishingService;
use Throwable;

class AutoCronService {

    private const INTERVAL_FETCH     = 900;   // 15 mins
    private const INTERVAL_ANALYZE   = 600;   // 10 mins
    private const INTERVAL_GENERATE  = 900;   // 15 mins
    private const INTERVAL_PUBLISH   = 900;   // 15 mins
    private const INTERVAL_BACKLINKS = 14400; // 4 hours

    /**
     * Fetch schedule state from database with file fallback
     */
    private static function getScheduleState(): array {
        try {
            $dbVal = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'cron_schedule_state' LIMIT 1");
            if (!empty($dbVal)) {
                $decoded = json_decode($dbVal, true);
                if (is_array($decoded)) return $decoded;
            }
        } catch (Throwable $e) {}

        $stateFile = dirname(__DIR__, 2) . '/storage/cache/cron_schedule_state.json';
        if (file_exists($stateFile)) {
            return json_decode(@file_get_contents($stateFile), true) ?: [];
        }
        return [];
    }

    /**
     * Persist schedule state in database (immune to filesystem permission issues)
     */
    private static function saveScheduleState(array $state): void {
        try {
            Database::query(
                "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES ('cron_schedule_state', :val, NOW())
                 ON DUPLICATE KEY UPDATE `value` = :val, `updated_at` = NOW()",
                ['val' => json_encode($state)]
            );
        } catch (Throwable $e) {}

        try {
            $lockDir = dirname(__DIR__, 2) . '/storage/cache';
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0775, true);
            }
            @file_put_contents($lockDir . '/cron_schedule_state.json', json_encode($state, JSON_PRETTY_PRINT));
        } catch (Throwable $e) {}
    }

    public static function checkAndRun(): void {
        // Strict Guard: ONLY execute in CLI background daemon (NEVER on web requests)
        if (php_sapi_name() !== 'cli') {
            return;
        }

        try {
            $state = self::getScheduleState();

            $now = time();
            $tasksDue = [];

            if (($now - ($state['fetch'] ?? 0)) >= self::INTERVAL_FETCH) {
                $tasksDue[] = 'fetch';
            }
            if (($now - ($state['analyze'] ?? 0)) >= self::INTERVAL_ANALYZE) {
                $tasksDue[] = 'analyze';
            }
            if (($now - ($state['generate'] ?? 0)) >= self::INTERVAL_GENERATE) {
                $tasksDue[] = 'generate';
            }
            if (($now - ($state['publish'] ?? 0)) >= self::INTERVAL_PUBLISH) {
                $tasksDue[] = 'publish';
            }
            if (($now - ($state['backlinks'] ?? 0)) >= self::INTERVAL_BACKLINKS) {
                $tasksDue[] = 'backlinks';
            }

            if (empty($tasksDue)) {
                return;
            }

            // Update timestamps first to prevent race condition
            foreach ($tasksDue as $t) {
                $state[$t] = $now;
            }
            self::saveScheduleState($state);

            // In CLI daemon — execute directly in same process
            self::executeBackgroundJobs($tasksDue);

        } catch (Throwable $e) {
            // Silently ignore to protect daemon
        }
    }

    public static function executeBackgroundJobs(array $tasks): void {
        if (php_sapi_name() !== 'cli' && function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        @ignore_user_abort(true);
        @set_time_limit(300);

        foreach ($tasks as $task) {
            try {
                switch ($task) {
                    case 'fetch':
                        self::runFetch();
                        break;
                    case 'analyze':
                        self::runAnalyze();
                        break;
                    case 'generate':
                        self::runGenerate();
                        break;
                    case 'publish':
                        self::runPublish();
                        break;
                    case 'backlinks':
                        self::runBacklinks();
                        break;
                }
            } catch (Throwable $e) {
                Logger::error("AutoCron task '{$task}' failed: " . $e->getMessage());
            }
        }
    }

    private static function runBacklinks(): void {
        Logger::info('AutoCron: Starting High-DA Backlink Syndication');
        try {
            $service = new BacklinkService();
            $service->syndicateLatest(2);
        } catch (Throwable $e) {
            Logger::error('AutoCron Backlink error: ' . $e->getMessage());
        }
    }

    private static function runFetch(): void {
        Logger::info('AutoCron: Starting fetch-trends');
        $service = new TrendService();
        $count = count($service->fetchAllSources(10));
        Logger::info("AutoCron fetch completed: {$count} trends ingested");

        // Self-Healing Replenisher: When breaking news is slow, replenish high-search evergreen topics
        self::replenishEvergreenQueue();
    }

    private static function replenishEvergreenQueue(): void {
        try {
            // Check specifically for pending evergreen/search-intent topics in the pipeline
            $pendingCount = (int)Database::fetchValue(
                "SELECT COUNT(*) FROM trends 
                 WHERE status IN ('detected', 'approved') 
                   AND (source LIKE '%evergreen%' OR category_hint IN ('student-technology', 'scholarships', 'college-updates', 'career-guides'))"
            );
            if ($pendingCount < 2) {
                $adapter = new \App\Services\TrendSources\EvergreenTopicsAdapter();
                $evergreenList = $adapter->fetch(3);
                foreach ($evergreenList as $item) {
                    if (!TrendService::existsAsArticle($item['keyword'])) {
                        $hash = TrendService::normalizedHash($item['keyword']);
                        $existing = Database::fetchOne("SELECT id, status FROM trends WHERE normalized_hash = :hash LIMIT 1", ['hash' => $hash]);
                        if ($existing) {
                            if ($existing['status'] === 'rejected' || $existing['status'] === 'detected') {
                                TrendService::markStatus((int)$existing['id'], 'detected');
                            }
                        } else {
                            Database::insert('trends', [
                                'keyword' => $item['keyword'],
                                'normalized_hash' => $hash,
                                'source' => $item['source'],
                                'url' => $item['url'],
                                'trend_score' => $item['trend_score'],
                                'category_hint' => $item['category_hint'],
                                'status' => 'detected',
                                'raw_payload' => json_encode($item['raw_payload'] ?? [])
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('AutoCron: replenishEvergreenQueue error: ' . $e->getMessage());
        }
    }

    private static function runAnalyze(): void {
        Logger::info('AutoCron: Starting analyze-trends');
        $maxPerRun = 2; // Smart Pacing: Analyze max 2 topics per cycle to conserve API
        $minScore  = (int)Env::get('MIN_TREND_SCORE', 60);
        $pendingTrends = TrendService::getPendingForAnalysis($maxPerRun);

        if (empty($pendingTrends)) {
            Logger::info('AutoCron analyze: no detected trends to process');
            return;
        }

        Logger::info('AutoCron analyze: processing ' . count($pendingTrends) . ' trends');
        $analyzer = new TopicAnalyzer();
        $existingArticles = ArticleService::getLatestPublished(20);

        foreach ($pendingTrends as $trend) {
            $trendId = (int)$trend['id'];
            TrendService::markStatus($trendId, 'analyzing');

            try {
                $rawPayload = !empty($trend['raw_payload'])
                    ? (is_array($trend['raw_payload']) ? $trend['raw_payload'] : (json_decode($trend['raw_payload'], true) ?: []))
                    : [];

                $sourceInfo = [
                    'source_name' => $trend['source'] ?? 'Statutory Agency',
                    'source_url'  => $trend['url'] ?? ($rawPayload['url'] ?? ''),
                    'snippet'     => $rawPayload['snippet'] ?? ($trend['category_hint'] ?? '')
                ];

                $analysis      = $analyzer->analyze($trend['keyword'], $sourceInfo, $existingArticles);
                $isRecommended = !empty($analysis['publish_recommendation']);
                $priorityScore = (int)($analysis['priority_score'] ?? 0);
                $categorySlug  = $analysis['category'] ?? ($trend['category_hint'] ?: 'career-guides');

                // Map to nearest DB category (with fallback)
                $cat = CategoryService::getBySlug($categorySlug);
                if (!$cat) {
                    $cat = CategoryService::autoResolveCategory($trend['keyword']);
                }
                $categoryId = $cat ? (int)$cat['id'] : null;

                if ($isRecommended && $priorityScore >= $minScore) {
                    TrendService::markStatus($trendId, 'approved', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Trend #{$trendId} APPROVED (score: {$priorityScore})");
                } else {
                    TrendService::markStatus($trendId, 'rejected', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Trend #{$trendId} REJECTED (score: {$priorityScore})");
                }
            } catch (Throwable $e) {
                Logger::error("AutoCron analyze trend #{$trendId} error: " . $e->getMessage());
                TrendService::markStatus($trendId, 'detected'); // reset to retry next cycle
                if (str_contains(strtolower($e->getMessage()), 'circuit breaker') || str_contains(strtolower($e->getMessage()), 'quota') || str_contains(strtolower($e->getMessage()), '429')) {
                    Logger::warning("AutoCron analyze halted: AI rate-limit/quota circuit breaker tripped.");
                    break; // STOP analyzing further trends this cycle!
                }
            }

            sleep(2); // rate limit pacing
        }
    }

    private static function runGenerate(): void {
        Logger::info('AutoCron: Checking generation pipeline');

        $publishingService = new PublishingService();

        // Guard 1: If today's daily limit (5/day) is already reached, do NOT consume API
        if ($publishingService->isDailyLimitReached()) {
            Logger::info('AutoCron generate: Daily publishing quota (5/day) is full. Sleeping generation until midnight to save API tokens.');
            return;
        }

        // Guard 2: Lean Review Queue — If Review Queue has >= 1 pending draft, pause generation (no queue pile-up)
        try {
            $pendingDrafts = (int)Database::fetchValue("SELECT COUNT(*) FROM articles WHERE status IN ('draft', 'pending_review', 'review')");
            if ($pendingDrafts >= 1) {
                Logger::info("AutoCron generate: {$pendingDrafts} draft already in Review Queue. Pausing generation until current draft is published.");
                return;
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Smart Pacing: Generate max 1 article per cycle
        Logger::info('AutoCron: Generating 1 approved article...');
        $pipeline = new PipelineService();
        $pipeline->processApprovedTrends(1);
    }

    private static function runPublish(): void {
        Logger::info('AutoCron: Starting publish-articles');
        $maxBatch          = (int)Env::get('AUTO_PUBLISH_DAILY_LIMIT', 5);
        $publishingService = new PublishingService();

        if (!$publishingService->isDailyLimitReached()) {
            $publishingService->processPublishQueue($maxBatch);
        }
    }
}
