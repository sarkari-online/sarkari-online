<?php
/**
 * EduPulse - Content Generation Cron Worker (Phase 5)
 * CLI executable: Selects 'approved' trends and runs the full creation pipeline
 * (Research → Generator → FactCheck → Editor → Linker → SEO → Quality Gate → Database Storage).
 * 
 * Usage: php cron/generate-articles.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\PipelineService;
use App\Helpers\Env;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Article Generation Worker...\n";
Logger::info("Cron generate-articles started");

$maxPerRun = 1; // Strictly process 1 slot per run (enforces 45-min spacing gap)
$pipeline = new PipelineService();

try {
    $results = $pipeline->processApprovedTrends($maxPerRun);
    $generatedCount = 0;
    $failedCount = 0;

    foreach ($results as $res) {
        if (!empty($res['success'])) {
            $generatedCount++;
            echo "  -> Created Article #{$res['article_id']} (Status: {$res['status']}, Score: {$res['quality_score']})\n";
        } else {
            $failedCount++;
            echo "  -> Failed Trend #{$res['trend_id']}: {$res['error']}\n";
        }
    }

    // Optional sweep of review queue only if the current IST time slot has remaining capacity
    $pubService = new \App\Services\PublishingService();
    $todayCount = $pubService->getPublishedTodayCount();
    $schedule   = \App\Services\AutoCronService::getISTSlotSchedule();

    if ($todayCount < $schedule['unlocked_slots'] && !$pubService->isDailyLimitReached()) {
        $remainingInSlot = max(0, $schedule['unlocked_slots'] - $todayCount);
        $pubResult = $pubService->processPublishQueue($remainingInSlot);
        if (!empty($pubResult['items'])) {
            foreach ($pubResult['items'] as $pItem) {
                if (!empty($pItem['success'])) {
                    echo "  -> Auto-Published Review Article #{$pItem['article_id']}: '{$pItem['title']}'\n";
                }
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Completed: {$generatedCount} generated, {$failedCount} failed in {$elapsed}s.\n";
    Logger::info("Cron generate-articles finished", [
        'generated' => $generatedCount,
        'failed' => $failedCount,
        'elapsed' => $elapsed
    ]);

    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'generate-articles.php') {
        exit(0);
    }
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
    Logger::critical("Cron generate-articles failed: " . $e->getMessage());

    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'generate-articles.php') {
        exit(1);
    }
}
