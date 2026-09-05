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
use App\Services\SettingsService;
use Throwable;

class AutoCronService {

    private const INTERVAL_FETCH     = 1800;  // 30 mins
    private const INTERVAL_ANALYZE   = 1800;  // 30 mins (Conserves AI quota; only analyzes when topics are needed)
    private const INTERVAL_GENERATE  = 1800;  // 30 mins (Slot guard controls actual publish time; no need to hammer every 2 mins)
    private const INTERVAL_PUBLISH   = 1800;  // 30 mins (Slot guard controls actual publish time; no need to hammer every 2 mins)
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
            $isAnalyzeDue = (($now - ($state['analyze'] ?? 0)) >= self::INTERVAL_ANALYZE);
            // Auto-Replenish: If approved queue has 0 items and detected topics exist, analyze automatically in background without waiting 30m
            if (!$isAnalyzeDue && ($now - ($state['analyze'] ?? 0)) >= 60) {
                try {
                    $apprCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved' LIMIT 1");
                    $detCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected' LIMIT 1");
                    if ($apprCount === 0 && $detCount > 0) {
                        $isAnalyzeDue = true;
                    }
                } catch (Throwable $e) {}
            }

            if ($isAnalyzeDue) {
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

        // 1. Quota Guard: Cap approved queue strictly at 3 (Lean 1-day buffer for the 3 daily slots)
        $approvedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
        if ($approvedCount >= 3) {
            $msg = "AutoCron analyze: Target lean approved buffer ({$approvedCount}/3) full. Skipping AI analysis to preserve Gemini quota.";
            Logger::info($msg);
            if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] ⏸️ {$msg}\n";
            return;
        }

        // 2. Daily Rest Guard: If today's 3 scheduled slots are already completed and we have at least 2 approved trends for tomorrow, rest!
        $completedSlots = self::getCompletedSlotsTodayCount();
        if ($completedSlots >= 3 && $approvedCount >= 2) {
            $msg = "AutoCron analyze: Today's 3 scheduled slots completed and tomorrow's pipeline is stocked ({$approvedCount} approved). Resting AI until tomorrow.";
            Logger::info($msg);
            if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] ⏸️ {$msg}\n";
            return;
        }

        // 3. Only analyze the exact number needed to top up the buffer to 3 (max 2 per run)
        $needed = max(0, 3 - $approvedCount);
        if ($needed <= 0) {
            return;
        }
        $maxPerRun = min(2, $needed);
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

            // Reject generic synthetic placeholder topics
            if (TrendService::isGenericPlaceholderKeyword($trend['keyword'])) {
                TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Generic placeholder topic rejected']]);
                Logger::info("AutoCron analyze: Trend #{$trendId} rejected (generic placeholder topic)");
                continue;
            }

            // Strict Student Search-Action Intent Gate (Pre-AI check to save API quota)
            $intentCheck = TrendService::isHighStudentActionIntent($trend['keyword']);
            if (!$intentCheck['pass']) {
                TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => $intentCheck['reason']]]);
                Logger::info("AutoCron analyze: Trend #{$trendId} rejected (" . $intentCheck['reason'] . ")");
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
                $errLower = strtolower($e->getMessage());
                if (str_contains($errLower, 'circuit breaker') || str_contains($errLower, 'quota') || str_contains($errLower, '429') || str_contains($errLower, 'rate limit') || str_contains($errLower, '404') || str_contains($errLower, 'not available')) {
                    Logger::warning("AutoCron analyze halted: AI rate-limit/quota circuit breaker tripped.");
                    \App\AI\Gemini::setCircuitBreaker(900, "Analyze quota limit protection");
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

    /**
     * Get the state of autonomous scheduled slot executions for today (IST)
     * Autonomous 3-Slot Daily Schedule:
     * - Slot 1: 10:00 AM IST (Morning announcement peak)
     * - Slot 2: 02:00 PM IST (14:00 - Afternoon update peak)
     * - Slot 3: 06:00 PM IST (18:00 - Evening bulletin peak)
     *
     * In-between manual articles published by the user/admin are completely exempt
     * and NEVER count against or block these 3 scheduled slots.
     */
    public static function getDailySlotsState(): array {
        $today = date('Y-m-d');
        $default = [
            'date' => $today,
            'completed_slots' => [],
            'slot_history' => []
        ];

        try {
            $val = SettingsService::get('cron_daily_slots_state', null);
            if (!empty($val)) {
                $decoded = is_array($val) ? $val : json_decode($val, true);
                if (is_array($decoded) && ($decoded['date'] ?? '') === $today) {
                    $default = $decoded;
                }
            }
        } catch (Throwable $e) {}

        // Ground-Truth Verification: Inspect actual articles published today in MySQL
        // If an article was already published in a slot window today, that slot is guaranteed completed!
        try {
            $todayArticles = Database::fetchAll(
                "SELECT id, published_at FROM articles 
                 WHERE DATE(published_at) = :today 
                   AND status = 'published' 
                   AND (ai_generated = 1 OR trend_id IS NOT NULL)
                 ORDER BY published_at ASC",
                ['today' => $today]
            );

            foreach ($todayArticles as $art) {
                $h = (int)date('H', strtotime($art['published_at']));
                $slotMatched = null;
                if ($h >= 10 && $h < 14) {
                    $slotMatched = 1; // Morning Slot 1 (10:00 AM to 01:59 PM IST)
                } elseif ($h >= 14 && $h < 18) {
                    $slotMatched = 2; // Noon Slot 2 (02:00 PM to 05:59 PM IST)
                } elseif ($h >= 18) {
                    $slotMatched = 3; // Evening Slot 3 (06:00 PM to 11:59 PM IST)
                }

                if ($slotMatched && !in_array($slotMatched, $default['completed_slots'], true)) {
                    $default['completed_slots'][] = $slotMatched;
                }
            }
            sort($default['completed_slots']);
        } catch (Throwable $e) {}

        return $default;
    }

    /**
     * Record a scheduled slot as completed for today
     */
    public static function recordSlotCompleted(int $slotNum, ?int $articleId = null): void {
        $state = self::getDailySlotsState();
        if (!in_array($slotNum, $state['completed_slots'] ?? [], true)) {
            $state['completed_slots'][] = $slotNum;
            sort($state['completed_slots']);
        }
        $state['slot_history'][$slotNum] = [
            'executed_at' => date('Y-m-d H:i:s'),
            'article_id'  => $articleId
        ];
        try {
            SettingsService::set('cron_daily_slots_state', json_encode($state), 'json', 'Daily autonomous slot execution state');
        } catch (Throwable $e) {
            Logger::error("Failed to record slot {$slotNum} completion: " . $e->getMessage());
        }
    }

    /**
     * Get next unlocked slot that hasn't executed today (1, 2, or 3), or null if all unlocked are done
     */
    public static function getNextPendingSlot(): ?int {
        $schedule = self::getISTSlotSchedule();
        $unlocked = (int)($schedule['unlocked_slots'] ?? 0);
        if ($unlocked <= 0) {
            return null;
        }

        $state = self::getDailySlotsState();
        $completed = $state['completed_slots'] ?? [];

        for ($s = 1; $s <= $unlocked; $s++) {
            if (!in_array($s, $completed, true)) {
                return $s;
            }
        }

        return null;
    }

    /**
     * Count of scheduled slots completed today (0, 1, 2, or 3)
     */
    public static function getCompletedSlotsTodayCount(): int {
        $state = self::getDailySlotsState();
        return count($state['completed_slots'] ?? []);
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

        $schedule = self::getISTSlotSchedule();
        $pendingSlot = self::getNextPendingSlot();

        // If no unlocked slots are pending today, log pacing and exit
        if ($pendingSlot === null) {
            $completedCount = self::getCompletedSlotsTodayCount();
            if ($completedCount >= 3) {
                $msg = "AutoCron pacing: All 3 autonomous daily slots (10 AM, 2 PM, 6 PM) completed for today. Resting until tomorrow 10:00 AM IST.";
            } else {
                $msg = "AutoCron pacing: {$completedCount}/3 scheduled slots completed today. Next slot: {$schedule['next_slot_name']} (unlocks in ~{$schedule['wait_minutes']}m).";
            }
            Logger::info($msg);
            if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
            return;
        }

        // Auto-heal any stale unpublishable drafts older than 6 hours
        try {
            Database::query("UPDATE articles SET status = 'archived' WHERE status IN ('draft', 'pending_review') AND created_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
        } catch (Throwable $e) {}

        // Generate next top approved trend directly to LIVE status for this pending slot
        $slotNames = [1 => 'Morning (10:00 AM IST)', 2 => 'Noon (02:00 PM IST)', 3 => 'Evening (06:00 PM IST)'];
        $slotLabel = $slotNames[$pendingSlot] ?? "Slot {$pendingSlot}";
        $msg = "AutoCron: Autonomous {$slotLabel} [Slot {$pendingSlot}/3] active. Generating top approved trend...";
        Logger::info($msg);
        if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";

        $pipeline = new PipelineService();
        $results = $pipeline->processApprovedTrends(1);
        if (php_sapi_name() === 'cli') {
            echo "[" . date('Y-m-d H:i:s') . "] Generation result: " . json_encode($results) . "\n";
        }

        if (!empty($results)) {
            foreach ($results as $res) {
                if (!empty($res['success']) && !empty($res['article_id'])) {
                    self::recordSlotCompleted($pendingSlot, (int)$res['article_id']);
                    $compMsg = "AutoCron: Scheduled {$slotLabel} successfully COMPLETED with Article #{$res['article_id']}";
                    Logger::info($compMsg);
                    if (php_sapi_name() === 'cli') echo "[" . date('Y-m-d H:i:s') . "] ✅ {$compMsg}\n";
                    break;
                }
            }
        }
    }

    private static function runPublish(): void {
        $pendingSlot = self::getNextPendingSlot();
        if ($pendingSlot === null) {
            return;
        }

        $publishingService = new PublishingService();
        $pubResult = $publishingService->processPublishQueue(1);
        if (!empty($pubResult['items'])) {
            foreach ($pubResult['items'] as $item) {
                if (!empty($item['success']) && !empty($item['article_id'])) {
                    self::recordSlotCompleted($pendingSlot, (int)$item['article_id']);
                    Logger::info("AutoCron: Scheduled Slot {$pendingSlot} published from review queue with Article #{$item['article_id']}");
                    break;
                }
            }
        }
    }
}
