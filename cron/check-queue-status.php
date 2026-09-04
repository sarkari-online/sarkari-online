<?php
/**
 * Sarkari.online - Live Queue & Approved Topics Status Checker
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "=================================================================\n";
echo "📊 SARKARI.ONLINE — LIVE QUEUE & APPROVED STATUS CHECKER\n";
echo "=================================================================\n\n";

// 1. Check Approved Trends
$approved = Database::fetchAll("SELECT id, keyword, status, trend_score, detected_at, processed_at FROM trends WHERE status = 'approved' ORDER BY id DESC");
echo "1. TRENDS IN 'APPROVED' STATUS: " . count($approved) . "\n";
if (empty($approved)) {
    echo "   (No trends currently waiting in approved status)\n";
} else {
    foreach ($approved as $t) {
        echo "   - Trend #{$t['id']}: {$t['keyword']}\n";
        echo "     Score: {$t['trend_score']} | Status: {$t['status']} | Processed: " . ($t['processed_at'] ?? 'Pending AI generation') . "\n";
    }
}
echo "\n";

// 2. Check Articles in Review Queue
$inReview = Database::fetchAll("SELECT id, title, slug, status, quality_score, created_at FROM articles WHERE status = 'review' ORDER BY id DESC");
echo "2. ARTICLES IN 'REVIEW QUEUE' (status = 'review'): " . count($inReview) . "\n";
if (empty($inReview)) {
    echo "   (Review Queue is currently empty)\n";
} else {
    foreach ($inReview as $r) {
        echo "   - Article #{$r['id']}: {$r['title']}\n";
        echo "     Quality Score: {$r['quality_score']}/100 | Created: {$r['created_at']}\n";
    }
}
echo "\n";

// 3. Check Autonomous 3-Slot Schedule & Today's Publications
$slotSchedule = \App\Services\AutoCronService::getISTSlotSchedule();
$slotsState   = \App\Services\AutoCronService::getDailySlotsState();
$completedSlots = $slotsState['completed_slots'] ?? [];
$pendingSlot  = \App\Services\AutoCronService::getNextPendingSlot();

echo "3. 🕒 AUTONOMOUS 3-SLOT DAILY SCHEDULE (10 AM | 2 PM | 6 PM IST):\n";
$slotLabels = [
    1 => 'Morning Slot 1 (10:00 AM IST)',
    2 => 'Noon Slot 2 (02:00 PM IST)',
    3 => 'Evening Slot 3 (06:00 PM IST)'
];
foreach ($slotLabels as $sNum => $label) {
    $done = in_array($sNum, $completedSlots, true);
    $history = $slotsState['slot_history'][$sNum] ?? null;
    $statusText = $done ? "✅ COMPLETED (" . ($history['executed_at'] ?? 'today') . " -> Article #" . ($history['article_id'] ?? 'N/A') . ")" : ($sNum <= $slotSchedule['unlocked_slots'] ? "⚡ DUE TO EXECUTE NOW" : "⏳ UNLOCKS LATER");
    echo "   - [Slot {$sNum}] {$label}: {$statusText}\n";
}
echo "   Next Action: " . ($pendingSlot !== null ? "Slot {$pendingSlot} is DUE NOW" : "All currently unlocked slots completed. Next slot: " . $slotSchedule['next_slot_name']) . "\n";
echo "   ℹ️ Note: Manual articles published by admin are 100% UNLIMITED and do not affect scheduled slots.\n\n";

$todayPublished = Database::fetchAll("
    SELECT id, title, slug, status, published_at, created_at, quality_score, ai_generated 
    FROM articles 
    WHERE status = 'published' AND DATE(published_at) = CURRENT_DATE 
    ORDER BY published_at DESC
");

echo "4. TODAY'S TOTAL PUBLISHED ARTICLES (" . count($todayPublished) . " Total Live Today):\n";
if (empty($todayPublished)) {
    echo "   (No articles published today yet)\n";
} else {
    foreach ($todayPublished as $idx => $p) {
        $num = $idx + 1;
        $timeStr = !empty($p['published_at']) ? date('d M Y, h:i:s A', strtotime($p['published_at'])) . ' IST' : 'N/A';
        echo "   {$num}. Article #{$p['id']}: {$p['title']}\n";
        echo "      Published At : {$timeStr}\n";
        echo "      Quality Score: {$p['quality_score']}/100\n";
    }
}

// 4. Check 24/7 Autonomous Daemon Worker Status
echo "4. 🤖 24/7 AUTONOMOUS DAEMON WORKER STATUS:\n";
$workerRunning = false;
$workerPids = [];
if (function_exists('exec')) {
    @exec("ps aux | grep '[w]orker.php'", $output);
    if (!empty($output)) {
        $workerRunning = true;
        foreach ($output as $line) {
            echo "   [ACTIVE] " . trim($line) . "\n";
        }
    }
}

if (!$workerRunning) {
    echo "   ⚠️ WARNING: Background worker (cron/worker.php) is NOT running!\n";
    echo "   Run this to start it: docker exec -d sarkari_app php /var/www/html/cron/worker.php\n";
} else {
    echo "   ✅ Background worker daemon is running actively.\n";
}

$stateVal = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'cron_schedule_state' LIMIT 1");
$state = !empty($stateVal) ? json_decode($stateVal, true) : [];
echo "\n   Schedule State (Last Executed Timestamps):\n";
echo "   - Fetch Trends     : " . (!empty($state['fetch']) ? date('d M, h:i A', $state['fetch']) . ' (' . round((time() - $state['fetch'])/60) . 'm ago)' : 'Never') . "\n";
echo "   - Analyze Trends   : " . (!empty($state['analyze']) ? date('d M, h:i A', $state['analyze']) . ' (' . round((time() - $state['analyze'])/60) . 'm ago)' : 'Never') . "\n";
echo "   - Generate Articles: " . (!empty($state['generate']) ? date('d M, h:i A', $state['generate']) . ' (' . round((time() - $state['generate'])/60) . 'm ago)' : 'Never') . "\n";
echo "   - Auto-Publish     : " . (!empty($state['publish']) ? date('d M, h:i A', $state['publish']) . ' (' . round((time() - $state['publish'])/60) . 'm ago)' : 'Never') . "\n";

echo "\n=================================================================\n";
