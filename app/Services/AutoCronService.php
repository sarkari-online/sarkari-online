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

    private const INTERVAL_FETCH     = 180;   // 3 mins
    private const INTERVAL_ANALYZE   = 120;   // 2 mins
    private const INTERVAL_GENERATE  = 240;   // 4 mins
    private const INTERVAL_PUBLISH   = 300;   // 5 mins

    public static function checkAndRun(): void {
        // Strict Guard: ONLY execute in CLI background daemon (NEVER on web requests)
        if (php_sapi_name() !== 'cli') {
            return;
        }

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

            // Update timestamps first to prevent race condition
            foreach ($tasksDue as $t) {
                $state[$t] = $now;
            }
            @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

            if (php_sapi_name() === 'cli') {
                // In CLI daemon — execute directly in same process
                self::executeBackgroundJobs($tasksDue);
            } else {
                // In web context — defer execution after HTTP response is sent
                register_shutdown_function([self::class, 'executeBackgroundJobs'], $tasksDue);
            }

        } catch (Throwable $e) {
            // Silently ignore to protect web request
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
                }
            } catch (Throwable $e) {
                Logger::error("AutoCron task '{$task}' failed: " . $e->getMessage());
            }
        }
    }

    private static function runFetch(): void {
        Logger::info('AutoCron: Starting fetch-trends');
        $service = new TrendService();
        $count = count($service->fetchAllSources(10));
        Logger::info("AutoCron fetch completed: {$count} trends ingested");
    }

    private static function runAnalyze(): void {
        Logger::info('AutoCron: Starting analyze-trends');
        $maxPerRun = (int)Env::get('MAX_TRENDS_PER_RUN', 10);
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
                TrendService::markStatus($trendId, 'detected'); // reset to retry next cycle
                Logger::error("AutoCron analyze trend #{$trendId} error: " . $e->getMessage());
            }

            sleep(2); // rate limit pacing
        }
    }

    private static function runGenerate(): void {
        Logger::info('AutoCron: Starting generate-articles');
        $maxPerRun = (int)Env::get('MAX_TRENDS_PER_RUN', 5);
        $pipeline  = new PipelineService();
        $pipeline->processApprovedTrends($maxPerRun);
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
