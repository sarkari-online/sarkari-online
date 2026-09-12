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
echo "   Sarkari.online - Google Indexing API Safety Guard\n";
echo "========================================================\n\n";

echo "🛑 NOTICE: Google Indexing API is strictly restricted to JobPosting and BroadcastEvent schemas.\n";
echo "Bulk submitting standard articles triggers Google algorithmic spam and index suppression.\n";
echo "Organic indexation is managed via Google Search Console and XML Sitemap (https://sarkari.online/sitemap.xml).\n\n";
echo "Execution aborted to protect domain trust.\n";
exit(0);

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
