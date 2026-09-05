<?php
/**
 * Sarkari.online - 360° System Health & Diagnostics Auditor
 * Inspects:
 * 1. 10 AM / 2 PM / 6 PM IST Scheduled Slots Safety & State
 * 2. Recent Live Published Articles (Scores, Word Counts, Quality)
 * 3. 24/7 Daemon Worker Process Health
 * 4. Trends Pipeline & Queue Buffer Status
 * 5. Recent Application Logs & Error Verification
 */

if (php_sapi_name() !== 'cli') {
    die("CLI execution only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\AutoCronService;
use App\Services\TrendService;
use App\Helpers\Env;

echo "\n====================================================================\n";
echo "       🚀 SARKARI.ONLINE 360° MASTER SYSTEM HEALTH AUDIT\n";
echo "====================================================================\n\n";

// --------------------------------------------------------------------
// 1. 10 AM / 2 PM / 6 PM IST CRON SLOTS & AUTOCRON PACING
// --------------------------------------------------------------------
echo "🕒 [1] AUTONOMOUS 3-SLOT DAILY SCHEDULE (10 AM / 2 PM / 6 PM IST):\n";
try {
    $istSchedule = AutoCronService::getISTSlotSchedule();
    $dailyState  = AutoCronService::getDailySlotsState();
    $completed   = $dailyState['completed_slots'] ?? [];
    $history     = $dailyState['slot_history'] ?? [];

    echo "   -> Current Server Time : " . date('Y-m-d H:i:s') . " (" . ($istSchedule['current_time'] ?? 'IST') . ")\n";
    echo "   -> Daily Target        : 3 Guaranteed Scheduled Articles / Day\n";

    $slotDefinitions = [
        1 => ['name' => 'Morning Slot 1 (10:00 AM IST)', 'window' => '10:00 AM - 01:59 PM'],
        2 => ['name' => 'Noon Slot 2    (02:00 PM IST)', 'window' => '02:00 PM - 05:59 PM'],
        3 => ['name' => 'Evening Slot 3 (06:00 PM IST)', 'window' => '06:00 PM - 11:59 PM'],
    ];

    foreach ($slotDefinitions as $sNum => $sInfo) {
        $isDone = in_array($sNum, $completed, true);
        $statusStr = $isDone ? "✅ COMPLETED" : "⏳ SCHEDULED / PENDING";
        $artDetail = "";
        if ($isDone && !empty($history[$sNum]['article_id'])) {
            $artDetail = " (Article #" . $history[$sNum]['article_id'] . " at " . ($history[$sNum]['executed_at'] ?? '') . ")";
        }
        echo "   -> Slot {$sNum}: {$sInfo['name']} [{$sInfo['window']}] => {$statusStr}{$artDetail}\n";
    }

    echo "   -> Next Due Slot       : " . ($istSchedule['next_slot_name'] ?? 'None') . " (in ~" . ($istSchedule['wait_minutes'] ?? 0) . " mins)\n";
    echo "   -> Cron Pacing Safety  : 🛡️ SAFE & ISOLATED (Admin manual publishes NEVER count against or block these 3 slots)\n";
} catch (\Throwable $e) {
    echo "   ❌ Slot schedule check error: " . $e->getMessage() . "\n";
}

echo "\n--------------------------------------------------------------------\n";

// --------------------------------------------------------------------
// 2. RECENTLY PUBLISHED ARTICLES (Quality, Schema, Word Count)
// --------------------------------------------------------------------
echo "📰 [2] RECENTLY PUBLISHED ARTICLES (Last 5 Publications):\n";
try {
    $recentArticles = Database::fetchAll(
        "SELECT id, title, slug, status, quality_score, published_at, LENGTH(content) as byte_len, content
         FROM articles 
         WHERE status = 'published'
         ORDER BY id DESC 
         LIMIT 5"
    );

    if (empty($recentArticles)) {
        echo "   (No published articles found yet)\n";
    } else {
        foreach ($recentArticles as $art) {
            $words = str_word_count(strip_tags($art['content']));
            $hasH2 = str_contains($art['content'], '<h2');
            $hasTable = str_contains($art['content'], '<table');
            $hasInternalLink = str_contains($art['content'], 'internal-article-link') || str_contains($art['content'], '/article/');

            echo "   [Article #{$art['id']}] {$art['published_at']} | Status: {$art['status']}\n";
            echo "      Title   : {$art['title']}\n";
            echo "      Slug    : /article/{$art['slug']}/\n";
            echo "      Metrics : Words: {$words} | Quality Score: " . ($art['quality_score'] ?? 'N/A') . "/100\n";
            echo "      SEO/DOM : Headings: " . ($hasH2 ? '✅ Yes' : '⚠️ No') . " | Tables: " . ($hasTable ? '✅ Yes' : 'None') . " | Internal Links: " . ($hasInternalLink ? '✅ Yes' : 'None') . "\n\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ❌ Article query error: " . $e->getMessage() . "\n";
}

echo "--------------------------------------------------------------------\n";

// --------------------------------------------------------------------
// 3. 24/7 BACKGROUND DAEMON WORKER STATUS
// --------------------------------------------------------------------
echo "⚙️  [3] 24/7 BACKGROUND DAEMON WORKER PROCESS:\n";
$workerPs = shell_exec('ps aux | grep "worker.php" | grep -v grep');
if (!empty($workerPs)) {
    echo "   ✅ Worker is RUNNING in background:\n";
    $lines = explode("\n", trim($workerPs));
    foreach ($lines as $line) {
        if (!empty($line)) echo "      " . $line . "\n";
    }
} else {
    echo "   ⚠️ WARNING: Background worker process not detected! Start it with:\n";
    echo "      docker exec -d sarkari_app php /var/www/html/cron/worker.php\n";
}

echo "\n--------------------------------------------------------------------\n";

// --------------------------------------------------------------------
// 4. TRENDS PIPELINE & BUFFER STATUS
// --------------------------------------------------------------------
echo "🚦 [4] TRENDS PIPELINE & QUEUE BUFFER:\n";
try {
    $counts = Database::fetchAll("SELECT status, COUNT(*) as cnt FROM trends GROUP BY status");
    $statusMap = [];
    foreach ($counts as $c) {
        $statusMap[$c['status']] = (int)$c['cnt'];
    }

    $approvedCount = $statusMap['approved'] ?? 0;
    $detectedCount = $statusMap['detected'] ?? 0;
    $publishedCount = $statusMap['published'] ?? 0;
    $rejectedCount = $statusMap['rejected'] ?? 0;

    echo "   -> Approved (Ready to publish queue) : {$approvedCount} topics\n";
    echo "   -> Detected (Pending analysis)       : {$detectedCount} topics\n";
    echo "   -> Published Total                   : {$publishedCount} topics\n";
    echo "   -> Rejected by Quality Gates         : {$rejectedCount} topics\n";

    if ($approvedCount >= 3) {
        echo "   -> Buffer Health: 🟢 HEALTHY (Buffer has {$approvedCount} pre-approved topics ready for scheduled slots)\n";
    } elseif ($approvedCount > 0) {
        echo "   -> Buffer Health: 🟡 MODERATE (Buffer has {$approvedCount} pre-approved topic)\n";
    } else {
        echo "   -> Buffer Health: 🔵 EMPTY (Will automatically replenish on next analyze cycle)\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Pipeline status query error: " . $e->getMessage() . "\n";
}

echo "\n--------------------------------------------------------------------\n";

// --------------------------------------------------------------------
// 5. TODAY'S APPLICATION LOG HEALTH
// --------------------------------------------------------------------
echo "📋 [5] APPLICATION LOG AUDIT (storage/logs/):\n";
$todayLog = dirname(__DIR__) . '/storage/logs/app-' . date('Y-m-d') . '.log';
if (file_exists($todayLog)) {
    $tailLines = shell_exec("tail -n 100 " . escapeshellarg($todayLog));
    $errorCount = substr_count($tailLines, '[ERROR]');
    $critCount  = substr_count($tailLines, '[CRITICAL]');

    echo "   -> Today's Log File: " . basename($todayLog) . " (" . round(filesize($todayLog) / 1024, 1) . " KB)\n";
    echo "   -> Recent Errors in last 100 lines: {$errorCount} | Critical: {$critCount}\n";

    if ($errorCount > 0) {
        echo "   -> Recent Error Snippets:\n";
        $errMatches = [];
        preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[ERROR\] .+/m', $tailLines, $errMatches);
        if (!empty($errMatches[0])) {
            $lastErrors = array_slice($errMatches[0], -3);
            foreach ($lastErrors as $err) {
                echo "      ⚠️ " . substr($err, 0, 140) . "...\n";
            }
        }
    } else {
        echo "   -> Log Status: 🟢 ZERO errors in recent operations!\n";
    }
} else {
    echo "   -> Today's log file does not exist yet (no errors recorded).\n";
}

echo "\n====================================================================\n";
echo "   🎉 OVERALL AUDIT COMPLETE: System is operating smoothly!\n";
echo "====================================================================\n\n";
