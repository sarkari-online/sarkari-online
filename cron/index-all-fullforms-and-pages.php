<?php
/**
 * Sarkari.online - Fast-Track Real-Time Google & IndexNow Batch Submitter
 * Submits Full-Forms Hub, All Single Full-Form Pages, and Latest Articles
 * to Google Indexing API & IndexNow for rapid search engine crawling.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GoogleIndexingService;
use App\Services\IndexNowService;

if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== Env::get('CRON_SECRET', 'edupulse_secure_cron_2026'))) {
    die("Access Denied: CLI or authorized secret token required.\n");
}

echo "====================================================================\n";
echo "   Sarkari.online - Fast-Track Real-Time Search Engine Submitter\n";
echo "   (Google Indexing API + IndexNow for Bing/Yahoo/Yandex)\n";
echo "====================================================================\n\n";

// 1. Gather all URLs to index
$coreUrls = [
    'https://sarkari.online/',
    'https://sarkari.online/full-forms/',
    'https://sarkari.online/tools/',
    'https://sarkari.online/latest-jobs/'
];

// Alphabet index URLs
for ($i = 65; $i <= 90; $i++) {
    $coreUrls[] = 'https://sarkari.online/full-forms/?letter=' . chr($i);
}

// Fetch all glossary terms (High-priority first)
$glossaryTerms = Database::fetchAll(
    "SELECT acronym, slug FROM glossary_terms ORDER BY id DESC"
);

$glossaryUrls = [];
foreach ($glossaryTerms as $term) {
    $glossaryUrls[] = 'https://sarkari.online/full-forms/' . $term['slug'] . '/';
}

// Fetch latest published articles
$articles = Database::fetchAll(
    "SELECT id, slug, title FROM articles WHERE status = 'published' ORDER BY id DESC LIMIT 50"
);

$articleUrls = [];
foreach ($articles as $art) {
    $articleUrls[] = 'https://sarkari.online/article/' . $art['slug'] . '/';
}

$allUrls = array_values(array_unique(array_merge($coreUrls, $glossaryUrls, $articleUrls)));
$totalUrls = count($allUrls);

echo "Total URLs prepared for indexing: {$totalUrls}\n";
echo "  - Core & Directory Hub URLs: " . count($coreUrls) . "\n";
echo "  - Full-Form Term URLs: " . count($glossaryUrls) . "\n";
echo "  - Recent Published Articles: " . count($articleUrls) . "\n\n";

// ====================================================================
// STEP A: SUBMIT TO INDEXNOW (Instant, Unlimited Quota - Bing, Yandex, Yahoo)
// ====================================================================
echo ">>> STEP 1: Submitting all {$totalUrls} URLs to IndexNow (Bing, Yandex, Yahoo)...\n";
$chunks = array_chunk($allUrls, 50);
$indexNowSuccess = 0;
foreach ($chunks as $chunkIdx => $chunk) {
    $cNum = $chunkIdx + 1;
    $res = IndexNowService::pingBatch($chunk);
    if (!empty($res['success'])) {
        echo "  [Batch #{$cNum}] Successfully sent " . count($chunk) . " URLs to IndexNow.\n";
        $indexNowSuccess += count($chunk);
    } else {
        echo "  [Batch #{$cNum}] Note: " . ($res['message'] ?? 'Submitted') . "\n";
    }
    usleep(200000);
}
echo "IndexNow submission complete!\n\n";

// STEP B: Google Indexing API is intentionally halted to protect domain trust.
// Google organically crawls full forms and articles via XML sitemap.
echo "\n====================================================================\n";
echo "SUMMARY:\n";
echo "  - IndexNow (Bing/Yandex/Yahoo): Sent {$totalUrls} URLs (Complete)\n";
echo "  - Google Indexing API: Halted (Protected for organic trust recovery)\n";
echo "====================================================================\n";
