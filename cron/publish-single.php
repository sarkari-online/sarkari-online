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

// If trend was already generated (or if --force is requested), purge old draft to regenerate fresh with full authority facts & timetable
$existing = Database::fetchOne("SELECT id, status FROM articles WHERE trend_id = :tid LIMIT 1", ['tid' => $trendId]);
$forceRegen = in_array('--force', $argv, true);
if ($existing && ($existing['status'] === 'review' || $forceRegen)) {
    echo "[" . date('Y-m-d H:i:s') . "] Purging outdated Article #{$existing['id']} to regenerate fresh with full depth...\n";
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
        $err = $res['error'] ?? 'Unknown error';
        echo "[" . date('Y-m-d H:i:s') . "] FAILED: " . $err . "\n";
        Logger::error("CLI Background: Generation failed for Trend #{$trendId}: " . $err);

        $trend = Database::fetchOne("SELECT raw_payload FROM trends WHERE id = :id LIMIT 1", ['id' => $trendId]);
        $raw = !empty($trend['raw_payload']) ? (is_array($trend['raw_payload']) ? $trend['raw_payload'] : (json_decode($trend['raw_payload'], true) ?: [])) : [];
        $raw['last_publish_error'] = $err;
        $raw['last_publish_failed_at'] = date('Y-m-d H:i:s');
        Database::execute("UPDATE trends SET status = 'failed', raw_payload = :raw WHERE id = :id", [
            'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'id' => $trendId
        ]);
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n";
    Logger::error("CLI Background exception for Trend #{$trendId}: " . $e->getMessage());
    $trend = Database::fetchOne("SELECT raw_payload FROM trends WHERE id = :id LIMIT 1", ['id' => $trendId]);
    $raw = !empty($trend['raw_payload']) ? (is_array($trend['raw_payload']) ? $trend['raw_payload'] : (json_decode($trend['raw_payload'], true) ?: [])) : [];
    $raw['last_publish_error'] = $e->getMessage();
    $raw['last_publish_failed_at'] = date('Y-m-d H:i:s');
    Database::execute("UPDATE trends SET status = 'failed', raw_payload = :raw WHERE id = :id", [
        'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE),
        'id' => $trendId
    ]);
}
