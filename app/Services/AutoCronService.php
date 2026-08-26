<?php
/**
 * Sarkari.online - In-Process Self-Healing Autonomous Cron Engine
 * Uses PHP-FPM fastcgi_finish_request() and shutdown handlers to run
 * trend ingestion, topic analysis, article generation, and publishing
 * 100% reliably in the background with zero impact on page load speed.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Env;
use App\AI\TopicAnalyzer;
use Throwable;

class AutoCronService {

    private const INTERVAL_FETCH     = 900;   // 15 mins
    private const INTERVAL_ANALYZE   = 1200;  // 20 mins
    private const INTERVAL_GENERATE  = 1800;  // 30 mins
    private const INTERVAL_PUBLISH   = 2700;  // 45 mins

    /**
     * Check schedule and register background runner
     */
    public static function checkAndRun(): void {
        try {
            $lockDir = dirname(__DIR__, 2) . '/storage/cache';
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0775, true);
            }

            $stateFile = $lockDir . '/cron_schedule_state.json';
            $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?: []) : [];

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

            if (empty($tasksDue)) {
                return;
            }

            // Update state timestamps immediately to prevent concurrency stampedes
            foreach ($tasksDue as $t) {
                $state[$t] = $now;
            }
            @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

            // Register background shutdown task (executes after browser connection closes)
            register_shutdown_function([self::class, 'executeBackgroundJobs'], $tasksDue);

        } catch (Throwable $e) {
            // Silently ignore to protect web request
        }
    }

    /**
     * Execute background jobs in detached background process or after fastcgi_finish_request
     */
    public static function executeBackgroundJobs(array $tasks): void {
        // Detach and close HTTP response to browser immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Prevent timeout for background processing
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
                }
            } catch (Throwable $e) {
                Logger::error("AutoCron task '{$task}' failed: " . $e->getMessage());
            }
        }
    }

    private static function runFetch(): void {
        $service = new TrendService();
        $count = count($service->fetchAllSources(10));
        Logger::info("AutoCron fetch completed: {$count} trends ingested");
    }

    private static function runAnalyze(): void {
        $maxPerRun = (int)Env::get('MAX_TRENDS_PER_RUN', 10);
        $minScore = (int)Env::get('MIN_TREND_SCORE', 75);
        $pendingTrends = TrendService::getPendingForAnalysis($maxPerRun);

        if (empty($pendingTrends)) return;

        $analyzer = new TopicAnalyzer();
        $existingArticles = ArticleService::getLatestPublished(20);

        foreach ($pendingTrends as $trend) {
            $trendId = (int)$trend['id'];
            TrendService::markStatus($trendId, 'analyzing');

            try {
                $sourceInfo = [
                    'source_name' => $trend['source_name'] ?? 'Statutory Agency',
                    'source_url'  => $trend['source_url'] ?? '',
                    'snippet'     => $trend['raw_payload'] ?? ''
                ];

                $analysis = $analyzer->analyze($trend['keyword'], $sourceInfo, $existingArticles);
                $isApproved = !empty($analysis['publish_recommendation']) && ($analysis['priority_score'] ?? 0) >= $minScore;

                if ($isApproved) {
                    $cat = CategoryService::findBySlug($analysis['category'] ?? 'career-guides');
                    TrendService::markStatus($trendId, 'approved', [
                        'category_id' => $cat['id'] ?? null,
                        'analysis_payload' => json_encode($analysis)
                    ]);
                } else {
                    TrendService::markStatus($trendId, 'rejected', [
                        'rejection_reason' => $analysis['reasoning'] ?? 'Editorial threshold not met'
                    ]);
                }
            } catch (Throwable $e) {
                TrendService::markStatus($trendId, 'failed', ['rejection_reason' => $e->getMessage()]);
            }
        }
    }

    private static function runGenerate(): void {
        $maxPerRun = (int)Env::get('MAX_TRENDS_PER_RUN', 5);
        $pipeline = new PipelineService();
        $pipeline->processApprovedTrends($maxPerRun);
    }

    private static function runPublish(): void {
        $maxBatch = (int)Env::get('AUTO_PUBLISH_DAILY_LIMIT', 5);
        $publishingService = new PublishingService();

        if (!$publishingService->isDailyLimitReached()) {
            $publishingService->processPublishQueue($maxBatch);
        }
    }
}
