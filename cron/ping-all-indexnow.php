<?php
/**
 * Sarkari.online - Bulk IndexNow Batch Submitter
 * Submits all published articles and core portal URLs to IndexNow (Bing, Yahoo, Yandex, ChatGPT Search).
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\IndexNowService;

if (php_sapi_name() !== 'cli') {
    die("Access Denied: CLI only.\n");
}

echo "========================================================\n";
echo "   Sarkari.online - Bulk IndexNow Batch Submitter\n";
echo "   (Bing, Yahoo, Yandex, Microsoft Copilot, ChatGPT Search)\n";
echo "========================================================\n\n";

if (!IndexNowService::isConfigured()) {
    echo "⚠️ Warning: IndexNow key verification file not found locally, but will attempt production ping...\n";
}

$articles = Database::fetchAll(
    "SELECT id, title, slug FROM articles WHERE status = 'published' ORDER BY id DESC"
);

$urls = [
    'https://sarkari.online/',
    'https://sarkari.online/latest-jobs/',
    'https://sarkari.online/state-jobs/',
    'https://sarkari.online/category/exam-results/',
    'https://sarkari.online/category/admit-cards/',
    'https://sarkari.online/category/exam-dates/',
    'https://sarkari.online/category/government-jobs/',
    'https://sarkari.online/tools/',
    'https://sarkari.online/tools/7th-pay-commission-salary-calculator/',
    'https://sarkari.online/tools/cgpa-to-percentage-calculator/',
    'https://sarkari.online/tools/age-calculator/'
];

foreach ($articles as $a) {
    $urls[] = 'https://sarkari.online/article/' . $a['slug'] . '/';
}

$urls = array_values(array_unique($urls));
$total = count($urls);

echo "Found " . count($articles) . " published articles + " . (count($urls) - count($articles)) . " portal pages.\n";
echo "Total canonical URLs to ping: {$total}\n\n";

// Batch submit in chunks of 50
$chunks = array_chunk($urls, 50);
$successCount = 0;
$failCount = 0;

foreach ($chunks as $idx => $batch) {
    $batchNum = $idx + 1;
    $batchSize = count($batch);
    echo "Submitting Batch #{$batchNum} ({$batchSize} URLs)... ";

    $res = IndexNowService::pingBatch($batch);
    if ($res['success']) {
        echo "✅ [HTTP " . ($res['status_code'] ?? 200) . " Accepted]\n";
        $successCount += $batchSize;
    } else {
        echo "⚠️ [" . ($res['error'] ?? 'Submission failed') . "]\n";
        $failCount += $batchSize;
    }

    usleep(500000); // 0.5s pause
}

echo "\n========================================================\n";
echo "Done! Submitted: {$successCount} URLs, Failed: {$failCount}\n";
echo "Search engines notified: Microsoft Bing, Yahoo, Yandex, ChatGPT Search.\n";
echo "========================================================\n";
