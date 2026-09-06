<?php
/**
 * Sarkari.online - 24/7 Autonomous Temporal Lifecycle & Deadline Revalidation Cron
 *
 * Runs every 30 minutes to check expiring/expired deadlines in Asia/Kolkata,
 * verifies official portals for extensions, transitions past deadlines to CLOSED,
 * strips active CTAs, and preserves all historical recruitment facts.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\TemporalRevalidationService;
use App\Services\TemporalFactService;
use App\Helpers\Logger;

$limit = 20;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, 8);
    }
}

$nowIST = TemporalFactService::nowIST();
echo "[" . $nowIST->format('Y-m-d H:i:s') . " IST] ⏱️ Starting Temporal Lifecycle Revalidation Engine (Limit: {$limit})...\n";

try {
    $stats = TemporalRevalidationService::revalidateAll($limit, $nowIST);

    echo "[" . date('Y-m-d H:i:s') . "] Revalidation complete:\n";
    echo "  - Articles Scanned: {$stats['scanned']}\n";
    echo "  - Transitioned to CLOSED: {$stats['closed']}\n";
    echo "  - Officially Extended: {$stats['extended']}\n";
    echo "  - Total Updated: {$stats['updated']}\n";

    if (!empty($stats['errors'])) {
        echo "  - Errors encountered (" . count($stats['errors']) . "):\n";
        foreach ($stats['errors'] as $err) {
            echo "    * {$err}\n";
        }
    }
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Temporal Lifecycle Revalidation failed: " . $e->getMessage() . "\n";
    Logger::critical("cron/temporal-lifecycle.php failed: " . $e->getMessage());
    exit(1);
}
