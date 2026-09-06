<?php
/**
 * Sarkari.online - Deterministic Temporal Classification & Backfill CLI Tool
 *
 * Runs classification across existing articles without modifying content.
 * DEFAULTS TO --dry-run TO GUARANTEE ZERO UNINTENDED DATABASE WRITES.
 * Only writes when invoked with --execute.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\TemporalBackfillService;
use App\Services\TemporalFactService;

$isExecute = in_array('--execute', $argv, true);
$dryRun = !$isExecute;

$limit = 100;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, 8);
    }
}

$nowIST = TemporalFactService::nowIST();
echo "========================================================================\n";
echo "   SARKARI.ONLINE - TEMPORAL CLASSIFICATION & BACKFILL INSPECTOR\n";
echo "========================================================================\n";
echo "Mode: " . ($dryRun ? "\033[33mDRY-RUN (Zero DB writes)\033[0m" : "\033[31mEXECUTE (Applying updates)\033[0m") . "\n";
echo "Timestamp: " . $nowIST->format('Y-m-d H:i:s') . " IST\n";
echo "Limit: {$limit} articles\n\n";

try {
    $result = TemporalBackfillService::processArticles($limit, $dryRun);

    echo "--- CLASSIFICATION SUMMARY ---\n";
    echo "Total Articles Inspected : {$result['total_inspected']}\n";
    echo "  - Closed               : {$result['counts']['closed']}\n";
    echo "  - Active (Verified)    : {$result['counts']['active']}\n";
    echo "  - Evergreen (Prep/PYQ) : {$result['counts']['evergreen']}\n";
    echo "  - Historical (Past Yr) : {$result['counts']['historical']}\n";
    echo "  - Draft (Safe/Unverif) : {$result['counts']['draft']}\n";
    echo "  - Flagged for Review   : {$result['counts']['needs_review']}\n\n";

    if (!empty($result['items'])) {
        echo "Sample Classification Details (First 10):\n";
        $slice = array_slice($result['items'], 0, 10);
        foreach ($slice as $item) {
            $flag = $item['needs_review'] ? " \033[33m[NEEDS REVIEW]\033[0m" : "";
            echo "  [#{$item['id']}] -> \033[32m{$item['target_state']}\033[0m: {$item['title']}{$flag}\n";
            echo "       Reason: {$item['reason']}\n";
        }
    }

    if ($dryRun) {
        echo "\n\033[33m[DRY-RUN COMPLETED]\033[0m No database records were modified.\n";
        echo "To apply changes after review, run with: php cron/backfill-temporal-facts.php --execute\n";
    } else {
        echo "\n\033[32m[EXECUTE COMPLETED]\033[0m All classifications and verified temporal facts have been persisted.\n";
    }
} catch (Throwable $e) {
    echo "\n❌ Backfill inspector failed: " . $e->getMessage() . "\n";
    exit(1);
}
