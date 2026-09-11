<?php
declare(strict_types=1);

/**
 * Sarkari.online — 6:00 PM Slot & Publishing Pipeline Forensic Diagnostic
 * Usage:
 *   php cron/check-6pm-publishing.php
 *   php cron/check-6pm-publishing.php --trigger=true
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\AutoCronService;
use App\Services\TrendService;
use App\Services\PipelineService;
use App\Services\PublishingService;
use App\AI\Gemini;

$options = getopt('', ['trigger::', 'force::']);
$doTrigger = isset($options['trigger']) && ($options['trigger'] === 'true' || $options['trigger'] === '1');
$doForce = isset($options['force']) && ($options['force'] === 'true' || $options['force'] === '1');

echo "\n" . str_repeat('=', 80) . "\n";
echo "🔍 SARKARI.ONLINE — AUTONOMOUS PUBLISHING PIPELINE DIAGNOSTIC\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . " IST (Timezone: " . date_default_timezone_get() . ")\n";
echo str_repeat('=', 80) . "\n\n";

// 1. Slot Schedule Status
$schedule = AutoCronService::getISTSlotSchedule();
$slotsState = AutoCronService::getDailySlotsState();
$completedCount = AutoCronService::getCompletedSlotsTodayCount();
$nextPendingSlot = AutoCronService::getNextPendingSlot();

echo "⏰ [1] IST SLOT PACING & SCHEDULE\n";
echo str_repeat('-', 80) . "\n";
echo "  • Current IST Time    : {$schedule['current_time']}\n";
echo "  • Unlocked Slots Today: {$schedule['unlocked_slots']} / 3\n";
echo "  • Completed Slots     : " . json_encode($slotsState['completed_slots'] ?? []) . " ({$completedCount}/3)\n";
echo "  • Next Pending Slot   : " . ($nextPendingSlot !== null ? "Slot #{$nextPendingSlot} ({$schedule['next_slot_name']})" : "None (All unlocked slots completed)") . "\n";
echo "\n";

// 2. Today's Published Articles
$today = date('Y-m-d');
$todayArticles = Database::fetchAll(
    "SELECT id, title, status, category_id, published_at, ai_generated 
     FROM articles 
     WHERE DATE(published_at) = :today 
     ORDER BY published_at DESC",
    ['today' => $today]
);

echo "📰 [2] ARTICLES PUBLISHED TODAY ({$today})\n";
echo str_repeat('-', 80) . "\n";
echo "  Total Published Today: " . count($todayArticles) . "\n";
if (!empty($todayArticles)) {
    foreach ($todayArticles as $a) {
        $aiBadge = $a['ai_generated'] ? '[AI Auto]' : '[Manual]';
        echo "  • [#{$a['id']}] {$aiBadge} [{$a['status']}] {$a['title']} | Published: {$a['published_at']}\n";
    }
} else {
    echo "  ⚠️ No articles published yet today!\n";
}
echo "\n";

// 3. Trends Pipeline Status
$approvedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved' LIMIT 1");
$detectedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected' LIMIT 1");
$analyzingCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'analyzing' LIMIT 1");
$processingCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'processing' LIMIT 1");

echo "📊 [3] TRENDS & TOPICS PIPELINE QUEUE\n";
echo str_repeat('-', 80) . "\n";
echo "  • Approved Trends (Ready to generate) : {$approvedCount}\n";
echo "  • Detected Trends (Waiting analysis)  : {$detectedCount}\n";
echo "  • In Analysis / Processing           : {$analyzingCount} / {$processingCount}\n";

$topApproved = Database::fetchAll(
    "SELECT id, keyword, source, trend_score, status, analyzed_at 
     FROM trends 
     WHERE status = 'approved' 
     ORDER BY trend_score DESC, id DESC 
     LIMIT 3"
);
if (!empty($topApproved)) {
    echo "  Top Approved Candidates:\n";
    foreach ($topApproved as $t) {
        echo "    - [#{$t['id']}] Score: {$t['trend_score']} | Keyword: {$t['keyword']} (Source: {$t['source']})\n";
    }
} else {
    echo "  ⚠️ No approved trends available in the queue!\n";
}
echo "\n";

// 4. Gemini Circuit Breaker Status
$cbActive = Gemini::isCircuitBreakerActive();
echo "⚡ [4] AI ENGINE (GEMINI) HEALTH\n";
echo str_repeat('-', 80) . "\n";
echo "  • Circuit Breaker Active: " . ($cbActive ? "🚨 YES (In Cooldown / Rate Limited)" : "✅ NO (Normal Operation)") . "\n";
echo "\n";

// 5. Cron Schedule Internal State
$schedState = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'cron_schedule_state' LIMIT 1");
echo "🕒 [5] AUTOCRON TASK INTERVAL TIMESTAMPS\n";
echo str_repeat('-', 80) . "\n";
if ($schedState) {
    $decodedState = json_decode($schedState, true);
    if (is_array($decodedState)) {
        $now = time();
        foreach ($decodedState as $task => $ts) {
            $diffM = round(($now - $ts) / 60, 1);
            echo "  • " . str_pad($task, 22) . ": Last ran {$diffM} mins ago (" . date('H:i:s', $ts) . ")\n";
        }
    }
} else {
    echo "  No state recorded in settings table.\n";
}
echo "\n";

// 6. Execution Trigger Option
if ($doTrigger || $doForce) {
    echo str_repeat('=', 80) . "\n";
    echo "🚀 EXECUTING MANUAL GENERATION / PUBLISH TRIGGER FOR SLOT #3...\n";
    echo str_repeat('=', 80) . "\n";

    if ($approvedCount === 0) {
        echo "Approved queue is empty! Running AutoCron fetch & analyze first to replenish...\n";
        $trendService = new TrendService();
        $fetched = $trendService->fetchAllSources(5);
        echo "Fetched " . count($fetched) . " fresh trends.\n";
    }

    // Direct invocation
    echo "Calling PipelineService to process approved trends...\n";
    $pipeline = new PipelineService();
    $results = $pipeline->processApprovedTrends(1);
    echo "Pipeline execution result: " . json_encode($results, JSON_PRETTY_PRINT) . "\n\n";

    if (!empty($results[0]['success']) && !empty($results[0]['article_id']) && ($results[0]['status'] ?? '') === 'published') {
        AutoCronService::recordSlotCompleted(3, (int)$results[0]['article_id']);
        echo "✅ Slot #3 successfully marked completed with Article #{$results[0]['article_id']}!\n";
    } else {
        echo "⚠️ Notice: Pipeline finished. Check details above.\n";
    }
} else {
    echo str_repeat('=', 80) . "\n";
    echo "💡 RECOMMENDATION / ACTION:\n";
    if ($nextPendingSlot === 3) {
        echo "  Slot #3 (06:00 PM IST) is currently UNLOCKED and PENDING!\n";
        if ($approvedCount === 0) {
            echo "  Root Cause: Approved trends queue is 0. Run with --trigger=true to fetch, analyze, and generate now.\n";
        } else {
            echo "  Approved trends exist. Run with --trigger=true to trigger the generation immediately:\n";
            echo "  docker exec -i sarkari_app php /var/www/html/cron/check-6pm-publishing.php --trigger=true\n";
        }
    } elseif ($completedCount >= 3) {
        echo "  Slot #3 was already completed earlier today! Check article table above.\n";
    } else {
        echo "  Run diagnostic with --trigger=true to force a run if needed.\n";
    }
    echo str_repeat('=', 80) . "\n\n";
}
