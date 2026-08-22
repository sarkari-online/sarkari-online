<?php
/**
 * EduPulse - Trend Ingestion Cron Worker (Phase 4)
 * CLI executable: Fetches emerging trends from registered adapters (Google Trends, RSS feeds, Statutory Portals),
 * normalizes keywords, executes deduplication filters, and stores qualified candidates in the 'trends' queue.
 * 
 * Usage: php cron/fetch-trends.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\TrendService;
use App\Helpers\Env;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Trend Ingestion Worker...\n";
Logger::info("Cron fetch-trends started");

$maxPerSource = (int)Env::get('MAX_TRENDS_PER_RUN', 10);
$service = new TrendService();

try {
    $recordedTrends = $service->fetchAllSources($maxPerSource);
    $count = count($recordedTrends);

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Completed successfully. Ingested {$count} new qualified trends in {$elapsed}s.\n";
    Logger::info("Cron fetch-trends finished", ['count' => $count, 'elapsed' => $elapsed]);

    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'fetch-trends.php') {
        exit(0);
    }
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    Logger::critical("Cron fetch-trends error: " . $e->getMessage());
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'fetch-trends.php') {
        exit(1);
    }
}
