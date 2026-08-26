<?php
/**
 * Sarkari.online - 24/7 Autonomous Background Daemon Worker
 * Runs under Supervisord. Every 60s directly executes the pipeline
 * (fetch -> analyze -> generate -> publish) based on schedule intervals.
 *
 * NOTE: Calls AutoCronService::checkAndRun() which in CLI mode
 * directly executes tasks (no shutdown_function workaround needed).
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\AutoCronService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Sarkari.online 24/7 Background Daemon Worker Started...\n";
Logger::info("Sarkari.online 24/7 Background Daemon Worker Started");

ini_set('memory_limit', '512M');
set_time_limit(0);
ignore_user_abort(true);

$cycle = 0;

while (true) {
    $cycle++;
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] Worker tick #{$cycle}...\n";

    try {
        // checkAndRun() in CLI mode directly executes all due tasks in-process
        AutoCronService::checkAndRun();
    } catch (\Throwable $e) {
        Logger::error("Worker cycle #{$cycle} error: " . $e->getMessage());
        echo "[{$ts}] Error on cycle #{$cycle}: " . $e->getMessage() . "\n";
    }

    sleep(60);
}
