<?php
/**
 * EduPulse - Bulk Google Indexing Batch Submitter
 * Submits all currently published articles to Google Real-Time Indexing API in one shot.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GoogleIndexingService;

if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== Env::get('CRON_SECRET', 'edupulse_secure_cron_2026'))) {
    die("Access Denied: CLI or authorized secret token required.\n");
}

echo "========================================================\n";
echo "   EduPulse - Bulk Google Indexing Batch Submitter\n";
echo "========================================================\n\n";

if (!GoogleIndexingService::isConfigured()) {
    die("❌ Error: Google Indexing key file not installed at storage/google-indexing-key.json\n");
}

$articles = Database::fetchAll(
    "SELECT id, title, slug, published_at FROM articles WHERE status = 'published' ORDER BY id ASC"
);

$total = count($articles);
echo "Found {$total} published articles ready for Google Indexing.\n\n";

$successCount = 0;
$failCount = 0;

// Also ping Homepage
echo "Submitting Homepage: " . SITE_URL . "/ ... ";
$hpResult = GoogleIndexingService::pingUrl(SITE_URL . '/', 'URL_UPDATED');
if ($hpResult['success']) {
    echo "✅ [HTTP 200 OK]\n";
    $successCount++;
} else {
    echo "⚠️ [" . ($hpResult['message'] ?? 'Failed') . "]\n";
    $failCount++;
}

foreach ($articles as $index => $art) {
    $num = $index + 1;
    $canonical = url('article/' . $art['slug'] . '/');
    echo "[{$num}/{$total}] Submitting: {$art['title']} ... ";

    $res = GoogleIndexingService::pingUrl($canonical, 'URL_UPDATED');
    if ($res['success']) {
        echo "✅ [HTTP 200 OK]\n";
        $successCount++;
    } else {
        echo "⚠️ [" . ($res['message'] ?? 'Failed') . "]\n";
        $failCount++;
    }

    // Polite rate pacing (0.3s delay)
    usleep(300000);
}

echo "\n========================================================\n";
echo "Batch Completed! Successfully submitted: {$successCount} URLs | Failed: {$failCount}\n";
echo "Googlebot has been notified for all published articles.\n";
echo "========================================================\n";
