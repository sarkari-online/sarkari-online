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
    private const INTERVAL_ANALYZE   = 300;   // 5 mins
    private const INTERVAL_GENERATE  = 120;   // 2 mins (Fast responsive generation of approved topics)
    private const INTERVAL_PUBLISH   = 120;   // 2 mins (Fast responsive auto-publishing)
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
            $json = json_encode($state);
            Database::query(
                "INSERT INTO settings (`key`, `value`) VALUES ('cron_schedule_state', :val1)
                 ON DUPLICATE KEY UPDATE `value` = :val2",
                ['val1' => $json, 'val2' => $json]
            );
        } catch (Throwable $e) {
            Logger::error("AutoCronService saveScheduleState failed: " . $e->getMessage());
        }

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
    }

    private static function replenishEvergreenQueue(): void {
        // Disabled: Rely strictly on real-time statutory notices and exam news
        return;
    }

    private static function runAnalyze(): void {
        Logger::info('AutoCron: Starting analyze-trends');

        $maxPerRun = 4; // Analyze detected trends to compute quality & intent scores
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

            // 30-Day Anti-Repeat Protection: Never allow similar topics within 30 days
            if (TrendService::isRecentlyCovered($trend['keyword'], 30)) {
                TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Topic covered within last 30 days']]);
                Logger::info("AutoCron analyze: Trend #{$trendId} rejected (covered within last 30 days)");
                continue;
            }

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

                // 🛑 USER-CONTROLLED APPROVAL FLOW:
                // If score is bad or not recommended -> AUTO-REJECT!
                // If score is good -> Keep in 'detected' with score updated so Admin can review and click Approve!
                // Exception: True official breaking notices auto-approve & auto-publish immediately.
                if (!$isRecommended || $priorityScore < $minScore) {
                    TrendService::markStatus($trendId, 'rejected', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Trend #{$trendId} AUTO-REJECTED (low score: {$priorityScore})");
                } elseif (TrendService::isOfficialBreaking($trend)) {
                    TrendService::markStatus($trendId, 'approved', [
                        'category_id' => $categoryId,
                        'trend_score' => 99,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Official Breaking Trend #{$trendId} auto-approved for immediate publication");
                } else {
                    // Update score and category, but leave in 'detected' for Human Review/Approval!
                    Database::update('trends', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'status'      => 'detected',
                        'raw_payload' => json_encode($analysis)
                    ], 'id = :id', ['id' => $trendId]);
                    Logger::info("AutoCron: Trend #{$trendId} analyzed (score: {$priorityScore}). Awaiting admin review.");
                }
            } catch (Throwable $e) {
                Logger::error("AutoCron analyze trend #{$trendId} error: " . $e->getMessage());
                TrendService::markStatus($trendId, 'detected'); // reset to retry next cycle
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

        // Guard 1: Check if there is an official breaking notice in queue (Bypasses steady 5-limit)
        $hasBreaking = false;
        $approvedTrends = Database::fetchAll("SELECT * FROM trends WHERE status = 'approved' ORDER BY trend_score DESC LIMIT 5");
        foreach ($approvedTrends as $at) {
            if (TrendService::isOfficialBreaking($at)) {
                $hasBreaking = true;
                break;
            }
        }

        if ($publishingService->isDailyLimitReached() && !$hasBreaking) {
            Logger::info('AutoCron generate: Daily publishing quota (5/day) is full and no breaking notice. Sleeping generation until midnight to save API tokens.');
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
