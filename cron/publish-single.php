<?php
/**
 * Sarkari.online - Single Trend Background Generator & Publisher
 * Executes full pipeline (Fact Fetch -> Generator -> Polish -> SEO -> Publish)
 * via CLI in background with zero HTTP web timeouts.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\PipelineService;
use App\Services\PublishingService;
use App\Helpers\Logger;

ini_set('memory_limit', '512M');
set_time_limit(300);

$trendId = (int)($argv[1] ?? 0);
if ($trendId <= 0) {
    echo "Usage: php publish-single.php <trend_id>\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting background publish for Trend #{$trendId}...\n";
Logger::info("CLI Background: Starting single trend generation for Trend #{$trendId}");

try {
    $pipeline = new PipelineService();
    $res = $pipeline->generateFromTrend($trendId, true);

    if (!empty($res['success']) && !empty($res['article_id'])) {
        $articleId = (int)$res['article_id'];
        $pubService = new PublishingService();
        $pubRes = $pubService->publish($articleId);
        if (!empty($pubRes['success'])) {
            echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: Article #{$articleId} published live.\n";
            Logger::info("CLI Background: Trend #{$trendId} successfully published live as Article #{$articleId}");
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] HELD IN REVIEW: Article #{$articleId} held: " . implode(', ', $pubRes['reasons'] ?? []) . "\n";
            Logger::warning("CLI Background: Article #{$articleId} held in review");
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
        Logger::error("CLI Background: Failed to generate Trend #{$trendId}: " . ($res['error'] ?? 'Unknown error'));
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    Logger::error("CLI Background exception for Trend #{$trendId}: " . $e->getMessage());
}
