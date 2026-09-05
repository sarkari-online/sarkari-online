<?php
/**
 * Sarkari.online - Performance Feedback Loop CLI Cron
 * Evaluates published content performance, detects cannibalization,
 * flags candidates for refresh/retire, and expands winning clusters into new discovery seeds.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI execution only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\PerformanceFeedbackService;
use App\Helpers\Logger;

$start = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Performance Feedback Loop...\n";

$result = PerformanceFeedbackService::evaluate();

$elapsed = round(microtime(true) - $start, 2);
echo "[" . date('Y-m-d H:i:s') . "] Performance Feedback Loop Completed in {$elapsed}s:\n";
echo "  -> Refreshed Candidates: " . ($result['refreshed'] ?? 0) . "\n";
echo "  -> Expanded Sub-Topic Seeds: " . ($result['expanded'] ?? 0) . "\n";
echo "  -> Cannibalization Pairs Flagged: " . ($result['cannibalization_flagged'] ?? 0) . "\n";
echo "  -> Dormant Notices Flagged to Retire: " . ($result['retired'] ?? 0) . "\n";
