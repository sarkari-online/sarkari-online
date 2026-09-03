<?php
/**
 * Sarkari.online - Approved Queue Lean Optimizer
 *
 * Keeps only the top 3 freshest & highest-scoring topics in the 'approved' queue
 * (matching the 3 daily publishing slots), and moves all remaining excess approved
 * topics back to 'detected' status so the user can manually publish them if desired.
 *
 * Usage:
 * php cron/clean-approved-queue.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🧹 SARKARI.ONLINE — APPROVED QUEUE LEAN OPTIMIZER\n";
echo "=================================================================\n\n";

$approvedCountBefore = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "1. Current Approved Queue count: {$approvedCountBefore}\n";

if ($approvedCountBefore <= 3) {
    echo "   ✓ Approved queue is already lean ({$approvedCountBefore} <= 3). No cleanup needed!\n\n";
    exit(0);
}

// 2. Fetch the top 3 freshest, highest-scoring approved trends to keep
$topApproved = Database::fetchAll("
    SELECT id, keyword, trend_score, created_at 
    FROM trends 
    WHERE status = 'approved' 
    ORDER BY trend_score DESC, created_at DESC 
    LIMIT 3
");

$keepIds = array_column($topApproved, 'id');
echo "2. Preserving Top 3 Priority Topics for tomorrow's publishing slots:\n";
foreach ($topApproved as $idx => $t) {
    $num = $idx + 1;
    echo "   [Slot {$num}] Trend #{$t['id']} (Score: {$t['trend_score']}): '{$t['keyword']}'\n";
}
echo "\n";

// 3. Move all other approved trends back to 'detected' status
$inPlaceholders = implode(',', array_map('intval', $keepIds));
Database::query("
    UPDATE trends 
    SET status = 'detected' 
    WHERE status = 'approved' 
      AND id NOT IN ({$inPlaceholders})
");

$approvedCountAfter = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
$detectedCountAfter = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected'");

echo "3. Cleanup Results:\n";
echo "   ✓ Excess topics moved to 'detected': " . ($approvedCountBefore - $approvedCountAfter) . "\n";
echo "   ✓ New Approved Queue (Lean)        : {$approvedCountAfter} (Target: 3)\n";
echo "   ✓ Total in Detected Queue          : {$detectedCountAfter} (Available for manual publishing)\n\n";

Logger::info("Approved queue cleaned: reduced from {$approvedCountBefore} to {$approvedCountAfter}. Excess moved to detected.");

echo "=================================================================\n";
echo "✅ OPTIMIZATION COMPLETE: Zero Gemini API waste!\n";
echo "   - Background generator will strictly consume from these 3 approved topics.\n";
echo "   - Background analyzer will pause because Approved Queue = 3.\n";
echo "   - All other topics are safely in 'Detected' for manual publishing.\n";
echo "=================================================================\n";
