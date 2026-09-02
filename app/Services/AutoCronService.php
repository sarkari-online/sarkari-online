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

        // Circuit Breaker pre-check: skip cycle if Gemini is in cooldown
        if (\App\AI\Gemini::isCircuitBreakerActive()) {
            Logger::info('AutoCron analyze: Gemini API circuit breaker is currently active. Skipping analysis cycle.');
            if (php_sapi_name() === 'cli') {
                echo "[" . date('Y-m-d H:i:s') . "] ⏸️ AutoCron analyze: Gemini circuit breaker active (cooldown). Skipping cycle.\n";
            }
            return;
        }

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

                // 🚀 100% FULL AUTONOMOUS PIPELINE:
                // If score is low or not recommended -> AUTO-REJECT!
                // If score is high (>= minScore) -> AUTO-APPROVE for instant autonomous generation & publishing!
                if (!$isRecommended || $priorityScore < $minScore) {
                    TrendService::markStatus($trendId, 'rejected', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Trend #{$trendId} AUTO-REJECTED (low score: {$priorityScore})");
                } else {
                    TrendService::markStatus($trendId, 'approved', [
                        'category_id' => $categoryId,
                        'trend_score' => $priorityScore,
                        'raw_payload' => $analysis
                    ]);
                    Logger::info("AutoCron: Trend #{$trendId} AUTO-APPROVED (score: {$priorityScore}) for autonomous generation");
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

    /**
     * Get the current active publishing slot status for Indian Standard Time (IST)
     * Daily Slots:
     * - Slot 1: 10:00 AM IST (Morning announcement peak)
     * - Slot 2: 02:00 PM IST (14:00 - Afternoon update peak)
     * - Slot 3: 06:00 PM IST (18:00 - Evening bulletin peak)
     */
    public static function getISTSlotSchedule(): array {
        $nowH = (int)date('H'); // 0 to 23 in Asia/Kolkata
        $nowM = (int)date('i');
        $nowMinutes = ($nowH * 60) + $nowM;

        $slot1Minutes = 10 * 60;       // 10:00 AM = 600 mins
        $slot2Minutes = 14 * 60;       // 02:00 PM = 840 mins
        $slot3Minutes = 18 * 60;       // 06:00 PM = 1080 mins

        if ($nowMinutes < $slot1Minutes) {
            $unlockedSlots = 0;
            $nextSlotName = 'Morning Slot 1 (10:00 AM IST)';
            $waitMinutes = $slot1Minutes - $nowMinutes;
        } elseif ($nowMinutes < $slot2Minutes) {
            $unlockedSlots = 1;
            $nextSlotName = 'Noon Slot 2 (02:00 PM IST)';
            $waitMinutes = $slot2Minutes - $nowMinutes;
        } elseif ($nowMinutes < $slot3Minutes) {
            $unlockedSlots = 2;
            $nextSlotName = 'Evening Slot 3 (06:00 PM IST)';
            $waitMinutes = $slot3Minutes - $nowMinutes;
        } else {
            $unlockedSlots = 3;
            $nextSlotName = 'Morning Slot 1 Tomorrow (10:00 AM IST)';
            $waitMinutes = (24 * 60 - $nowMinutes) + $slot1Minutes;
        }

        return [
            'unlocked_slots' => $unlockedSlots,
            'max_daily'      => 3,
            'next_slot_name' => $nextSlotName,
            'wait_minutes'   => $waitMinutes,
            'current_time'   => date('h:i A') . ' IST'
        ];
    }

    private static function runGenerate(): void {
        Logger::info('AutoCron: Checking generation pipeline');
        if (php_sapi_name() === 'cli') {
            echo "[" . date('Y-m-d H:i:s') . "] ⚡ AutoCron: Checking generation pipeline...\n";
        }

        // Circuit Breaker pre-check
        if (\App\AI\Gemini::isCircuitBreakerActive()) {
            Logger::info('AutoCron generate: Gemini API circuit breaker is currently active. Skipping generation cycle.');
            if (php_sapi_name() === 'cli') {
                echo "[" . date('Y-m-d H:i:s') . "] ⏸️ AutoCron generate: Gemini circuit breaker active (cooldown). Skipping cycle.\n";
            }
            return;
        }

        $publishingService = new PublishingService();

        // 1. Daily Quota Check: Max 3 articles per day
        $todayCount = $publishingService->getPublishedTodayCount();
        $maxDaily = 3;
        if ($todayCount >= $maxDaily) {
            $msg = "AutoCron generate: Daily publishing quota ({$todayCount}/{$maxDaily}) reached for today. Resting until tomorrow 10:00 AM IST.";
            Logger::info($msg);
            if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
            return;
        }

        // 2. Fixed Publishing Slots (10:00 AM, 02:00 PM, 06:00 PM IST)
        $schedule = self::getISTSlotSchedule();
        if ($todayCount >= $schedule['unlocked_slots']) {
            $msg = "AutoCron pacing: {$todayCount}/{$maxDaily} published today. Next slot: {$schedule['next_slot_name']} (unlocks in ~{$schedule['wait_minutes']}m).";
            Logger::info($msg);
            if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
            return;
        }

        // 3. Auto-heal any stale unpublishable drafts older than 6 hours
        try {
            Database::query("UPDATE articles SET status = 'archived' WHERE status IN ('draft', 'pending_review') AND created_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
        } catch (Throwable $e) {}

        // 4. Generate next top approved trend directly to LIVE status
        $slotNum = $todayCount + 1;
        $slotNames = [1 => 'Morning (10:00 AM)', 2 => 'Noon (02:00 PM)', 3 => 'Evening (06:00 PM)'];
        $slotLabel = $slotNames[$slotNum] ?? "Slot {$slotNum}";
        $msg = "AutoCron: Autonomous {$slotLabel} [{$slotNum}/{$maxDaily}] active. Generating top approved trend...";
        Logger::info($msg);
        if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";

        $pipeline = new PipelineService();
        $results = $pipeline->processApprovedTrends(1);
        if (php_sapi_name() === 'cli') {
            echo "[" . date('Y-m-d H:i:s') . "] Generation result: " . json_encode($results) . "\n";
        }
    }

    private static function runPublish(): void {
        Logger::info('AutoCron: Starting publish-articles');
        $maxBatch          = (int)Env::get('AUTO_PUBLISH_DAILY_LIMIT', 3);
        $publishingService = new PublishingService();

        if (!$publishingService->isDailyLimitReached()) {
            $publishingService->processPublishQueue($maxBatch);
        }
    }
}
