<?php
/**
 * Sarkari.online - Fast-Track Indexing Submitter for Recent Articles (Sep 7 to Present)
 *
 * Submits all recently published articles directly to:
 * 1. Google Real-Time Indexing API (for instant Googlebot crawling)
 * 2. IndexNow API (for Microsoft Bing, Yahoo, Yandex, ChatGPT Search)
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GoogleIndexingService;
use App\Services\IndexNowService;

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

echo "================================================================================\n";
echo "🚀 SARKARI.ONLINE — RECENT ARTICLES INSTANT SEARCH INDEXING SUBMITTER\n";
echo "================================================================================\n\n";

$isGoogleConfigured = GoogleIndexingService::isConfigured();
echo "Google Indexing Key : " . ($isGoogleConfigured ? "✅ ACTIVE (storage/google-indexing-key.json)" : "⚠️ NOT CONFIGURED") . "\n";
echo "IndexNow Key        : " . (IndexNowService::isConfigured() ? "✅ ACTIVE" : "⚠️ NOT CONFIGURED") . "\n\n";

// Fetch all articles published since Sep 06, 2026
$articles = Database::fetchAll(
    "SELECT id, title, slug, published_at, updated_at 
     FROM articles 
     WHERE status = 'published' AND published_at >= '2026-09-06 00:00:00' 
     ORDER BY published_at DESC"
);

$total = count($articles);
echo "Found {$total} articles published since Sep 06/07, 2026 ready for indexing.\n\n";

if ($total === 0) {
    echo "No matching articles found.\n";
    exit(0);
}

$urlsToPing = [];
$urlsToPing[] = SITE_URL . '/';
$urlsToPing[] = SITE_URL . '/latest-jobs/';
$urlsToPing[] = SITE_URL . '/full-forms/';

foreach ($articles as $art) {
    $urlsToPing[] = url('article/' . $art['slug'] . '/');
}

// -----------------------------------------------------------------------------
// STEP 1: GOOGLE INDEXING API (HALTED FOR DOMAIN RECOVERY)
// -----------------------------------------------------------------------------
echo "--------------------------------------------------------------------------------\n";
echo "🛑 STEP 1: Google Real-Time Indexing API [HALTED]\n";
echo "   Google restricts Indexing API exclusively to JobPosting & BroadcastEvent.\n";
echo "   General articles are indexed organically via XML Sitemap & Search Console.\n";
echo "--------------------------------------------------------------------------------\n";

// -----------------------------------------------------------------------------
// STEP 2: SUBMIT BATCH TO INDEXNOW (Bing, Yahoo, Yandex, Naver)
// -----------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------------------\n";
echo "📡 STEP 2: Submitting to IndexNow (Bing / Yahoo / Yandex)\n";
echo "--------------------------------------------------------------------------------\n";

$indexNowUrls = array_slice($urlsToPing, 0, 100);
$inRes = IndexNowService::pingBatch($indexNowUrls);

if (!empty($inRes['success'])) {
    echo "\033[32m✅ IndexNow successfully submitted " . count($indexNowUrls) . " URLs!\033[0m\n";
} else {
    echo "\033[33m⚠️ IndexNow response: " . ($inRes['message'] ?? ($inRes['error'] ?? 'Check endpoint')) . "\033[0m\n";
}

echo "\n================================================================================\n";
echo "🎉 FAST-TRACK INDEXING DISPATCH COMPLETED\n";
echo "   Google Indexing API : {$gSuccess} successful pings (Failed: {$gFail})\n";
echo "   IndexNow Protocol   : " . count($indexNowUrls) . " URLs submitted\n";
echo "================================================================================\n";
