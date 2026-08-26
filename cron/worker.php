<?php
/**
 * Sarkari.online - 24/7 Autonomous Background Daemon Worker
 * Runs continuously in background under Supervisord.
 * Automatically ticks every 60 seconds to fetch trends, generate articles, and publish live.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\AutoCronService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Sarkari.online 24/7 Background Daemon Worker Started...\n";
Logger::info("Sarkari.online 24/7 Background Daemon Worker Started");

// Ensure memory limit for long-running daemon
ini_set('memory_limit', '512M');
set_time_limit(0);

$cycle = 0;

while (true) {
    $cycle++;
    try {
        AutoCronService::checkAndRun();
    } catch (\Throwable $e) {
        Logger::error("Worker cycle #{$cycle} error: " . $e->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] Cycle error: " . $e->getMessage() . "\n";
    }

    // Sleep for 60 seconds before next schedule check
    sleep(60);
}
