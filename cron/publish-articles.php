<?php
/**
 * EduPulse - Controlled Automatic Publishing Cron Worker (Phase 7)
 * CLI executable: Selects verified, high-quality review articles (quality_score >= 90),
 * executes 10-point gatekeeper verification, enforces daily limits, and publishes articles live.
 * 
 * Usage: php cron/publish-articles.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\PublishingService;
use App\Helpers\Env;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Controlled Publishing Worker...\n";
Logger::info("Cron publish-articles started");

$maxBatch = (int)Env::get('AUTO_PUBLISH_DAILY_LIMIT', 5);
$publishingService = new PublishingService();

try {
    if ($publishingService->isDailyLimitReached()) {
        $publishedCount = $publishingService->getPublishedTodayCount();
        echo "[" . date('Y-m-d H:i:s') . "] Daily publishing limit reached ({$publishedCount}/{$maxBatch} articles published today). No new articles will be auto-published.\n";
        Logger::info("Cron publish-articles skipped: daily limit reached");

        if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'publish-articles.php') {
            exit(0);
        }
    } else {
        $result = $publishingService->processPublishQueue($maxBatch);
        $publishedCount = 0;
        $rejectedCount  = 0;
        $heldCount      = 0;

        if (!empty($result['items'])) {
            foreach ($result['items'] as $item) {
                $conf   = isset($item['verification_confidence']) ? " [{$item['verification_confidence']}% confidence]" : '';
                $action = $item['action'] ?? '';

                if (!empty($item['success'])) {
                    $publishedCount++;
                    echo "  -> [PUBLISHED] Article #{$item['article_id']}: '{$item['title']}' — Source verified{$conf}\n";
                } elseif ($action === 'deleted_from_db') {
                    $rejectedCount++;
                    $reason = implode('; ', $item['reasons'] ?? []);
                    echo "  -> [REJECTED + DELETED] Article #{$item['article_id']}: {$reason}{$conf}\n";
                } else {
                    $heldCount++;
                    $verdict = $item['verification_verdict'] ?? 'unknown';
                    $reason  = implode('; ', $item['reasons'] ?? []);
                    echo "  -> [HELD IN REVIEW] Article #{$item['article_id']}: {$verdict}{$conf} — {$reason}\n";
                }
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] Done: {$publishedCount} published (source-verified), {$rejectedCount} deleted (failed verification), {$heldCount} held for manual review. ({$elapsed}s)\n";
        Logger::info("Cron publish-articles completed", [
            'published'    => $publishedCount,
            'rejected_deleted' => $rejectedCount,
            'held_review'  => $heldCount,
            'elapsed'      => $elapsed
        ]);

        if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'publish-articles.php') {
            exit(0);
        }
    }
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
    Logger::critical("Cron publish-articles failed: " . $e->getMessage());

    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'publish-articles.php') {
        exit(1);
    }
}
