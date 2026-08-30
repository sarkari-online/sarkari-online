<?php
/**
 * Sarkari.online - Single Trend Background Generator & Publisher
 * Generates verified article into Review Queue, waits for verification,
 * and auto-publishes within ~3-5 minutes.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\PipelineService;
use App\Services\PublishingService;
use App\Helpers\Logger;

ini_set('memory_limit', '512M');
set_time_limit(360);

$trendId = (int)($argv[1] ?? 0);
if ($trendId <= 0) {
    echo "Usage: php publish-single.php <trend_id>\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting background generation for Trend #{$trendId}...\n";
Logger::info("CLI Background: Starting generation for Trend #{$trendId} into Review Queue");

try {
    $pipeline = new PipelineService();
    // Generates article with verified statutory facts and places in Review Queue (status='review')
    $res = $pipeline->generateFromTrend($trendId, true);

    if (!empty($res['success']) && !empty($res['article_id'])) {
        $articleId = (int)$res['article_id'];
        echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: Article #{$articleId} created in Review Queue.\n";
        Logger::info("CLI Background: Trend #{$trendId} placed in Review Queue as Article #{$articleId}");

        // Wait 120 seconds for factual settlement & human inspection window
        echo "[" . date('Y-m-d H:i:s') . "] Waiting 120s in Review Queue before final auto-publish verification...\n";
        sleep(120);

        $pubService = new PublishingService();
        $pubRes = $pubService->publish($articleId);
        if (!empty($pubRes['success'])) {
            echo "[" . date('Y-m-d H:i:s') . "] AUTO-PUBLISHED: Article #{$articleId} published live successfully.\n";
            Logger::info("CLI Background: Article #{$articleId} published live after Review verification.");
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] KEPT IN REVIEW: Article #{$articleId} held for review: " . implode(', ', $pubRes['reasons'] ?? []) . "\n";
            Logger::warning("CLI Background: Article #{$articleId} kept in Review Queue.");
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
        Logger::error("CLI Background: Generation failed for Trend #{$trendId}: " . ($res['error'] ?? 'Unknown error'));
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n";
    Logger::error("CLI Background exception for Trend #{$trendId}: " . $e->getMessage());
}
