<?php
/**
 * Sarkari.online - Single Trend Background Generator & Publisher
 * Generates verified article into Review Queue, waits for verification,
 * and auto-publishes within ~2-3 minutes.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\PipelineService;
use App\Services\PublishingService;
use App\Services\TrendService;
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

// If trend was already generated as an unpublished draft in review, purge old draft to regenerate fresh with latest authority facts & timetable
$existing = Database::fetchOne("SELECT id, status FROM articles WHERE trend_id = :tid LIMIT 1", ['tid' => $trendId]);
if ($existing && $existing['status'] === 'review') {
    echo "[" . date('Y-m-d H:i:s') . "] Purging outdated draft Article #{$existing['id']} to regenerate fresh with exact authority timetable...\n";
    Database::query("DELETE FROM article_checks WHERE article_id = :id", ['id' => $existing['id']]);
    Database::query("DELETE FROM articles WHERE id = :id", ['id' => $existing['id']]);
    Database::update('trends', ['processed_at' => null], 'id = :id', ['id' => $trendId]);
}

try {
    $pipeline = new PipelineService();
    $res = $pipeline->generateFromTrend($trendId, true);

    if (!empty($res['success']) && !empty($res['article_id'])) {
        $articleId = (int)$res['article_id'];
        echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: Article #{$articleId} successfully GENERATED and PUBLISHED LIVE!\n";
        Logger::info("CLI Background: Article #{$articleId} generated and published live directly from Trend #{$trendId}");
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
        Logger::error("CLI Background: Generation failed for Trend #{$trendId}: " . ($res['error'] ?? 'Unknown error'));
        TrendService::markStatus($trendId, 'approved');
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n";
    Logger::error("CLI Background exception for Trend #{$trendId}: " . $e->getMessage());
    TrendService::markStatus($trendId, 'approved');
}
