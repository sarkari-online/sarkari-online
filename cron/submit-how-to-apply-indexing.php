<?php
declare(strict_types=1);

/**
 * Sarkari.online - Fast-Track Real-Time Submitter for How-To-Apply Guide
 * Submits URL to Google Indexing API and IndexNow.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\GoogleIndexingService;
use App\Services\IndexNowService;

$slug = $argv[1] ?? 'ctet-2026';
$url  = url('how-to-apply/' . trim($slug, '/') . '/');

echo "================================================================================\n";
echo "🚀 SARKARI.ONLINE — FAST-TRACK INDEXING SUBMITTER\n";
echo "Target URL: {$url}\n";
echo "Timestamp : " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

// 1. Google Indexing API
echo "1. Pinging Google Indexing API (Real-Time)...\n";
try {
    $resGoogle = GoogleIndexingService::pingUrl($url, 'URL_UPDATED');
    if ($resGoogle['success']) {
        echo "   ✅ Google Indexing API: SUCCESS (HTTP " . ($resGoogle['status_code'] ?? 200) . ")\n";
    } else {
        echo "   ⚠️ Google Indexing API: " . ($resGoogle['message'] ?? 'Failed') . "\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Google Indexing Error: " . $e->getMessage() . "\n";
}

// 2. IndexNow (Bing, Yandex, Naver)
echo "\n2. Pinging IndexNow API (Bing / Yahoo / Seznam)...\n";
try {
    $resIndexNow = IndexNowService::pingUrl($url);
    if (!empty($resIndexNow['success'])) {
        echo "   ✅ IndexNow: SUCCESS (HTTP " . ($resIndexNow['status_code'] ?? 200) . ")\n";
    } else {
        echo "   ⚠️ IndexNow: " . ($resIndexNow['message'] ?? 'Failed') . "\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ IndexNow Error: " . $e->getMessage() . "\n";
}

echo "\n================================================================================\n";
echo "✅ Submission process completed.\n";
echo "================================================================================\n";
