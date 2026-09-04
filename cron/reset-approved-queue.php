<?php
/**
 * Sarkari.online - Approved Queue Reset & High-Intent Purge
 *
 * Purges stale, low-intent, political, administrative, and outdated topics from the
 * 'approved' queue, resetting the queue to 0 so that tomorrow's publishing slots
 * are filled strictly by freshly fetched high-intent official notifications.
 *
 * Usage:
 * php cron/reset-approved-queue.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\TrendService;

echo "=================================================================\n";
echo "🧹 SARKARI.ONLINE — APPROVED QUEUE RESET & HIGH-INTENT PURGE\n";
echo "=================================================================\n\n";

$approvedTrends = Database::fetchAll("SELECT id, keyword, trend_score, created_at FROM trends WHERE status = 'approved' ORDER BY id DESC");
$totalApproved = count($approvedTrends);

echo "1. Found {$totalApproved} topics currently in 'approved' status.\n\n";

if ($totalApproved === 0) {
    echo "   ✓ Approved queue is already clean (0 items). No action needed!\n";
} else {
    echo "2. Purging stale & low-intent topics from Approved Queue:\n";
    $purged = 0;

    foreach ($approvedTrends as $t) {
        $tid = (int)$t['id'];
        $kw = $t['keyword'];
        $check = TrendService::isHighStudentActionIntent($kw);
        $reason = $check['pass'] ? 'Purged for fresh morning high-intent topic selection' : $check['reason'];

        Database::query(
            "UPDATE trends SET status = 'rejected', raw_payload = JSON_SET(COALESCE(raw_payload, '{}'), '$.reason', :r) WHERE id = :id",
            ['r' => 'Reset: ' . $reason, 'id' => $tid]
        );

        echo "   - Purged Trend #{$tid}: '{$kw}'\n";
        echo "     Reason: {$reason}\n";
        $purged++;
    }

    echo "\n   ✅ Successfully purged {$purged} topics from Approved Queue!\n\n";
    Logger::info("Approved queue reset: Purged {$purged} topics to enable fresh high-intent topic selection.");
}

$remainingApproved = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
$totalDetected = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected'");

echo "3. Queue Status After Reset:\n";
echo "   - Approved Queue (Waiting for Generation): {$remainingApproved}\n";
echo "   - Detected Queue (Pending Analysis)      : {$totalDetected}\n\n";

echo "=================================================================\n";
echo "✅ QUEUE RESET COMPLETE!\n";
echo "   - Tomorrow's Slot 1 (10:00 AM IST) will strictly analyze and publish\n";
echo "     fresh, high-intent student notices (Admit Cards, Results, Apply Online).\n";
echo "=================================================================\n";
