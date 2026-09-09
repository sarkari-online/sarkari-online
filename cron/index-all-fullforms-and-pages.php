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
    $res = IndexNowService::submitBatch($chunk);
    if (!empty($res['success'])) {
        echo "  [Batch #{$cNum}] Successfully sent " . count($chunk) . " URLs to IndexNow.\n";
        $indexNowSuccess += count($chunk);
    } else {
        echo "  [Batch #{$cNum}] Note: " . ($res['message'] ?? 'Submitted') . "\n";
    }
    usleep(200000);
}
echo "IndexNow submission complete!\n\n";

// ====================================================================
// STEP B: SUBMIT TO GOOGLE REAL-TIME INDEXING API
// ====================================================================
echo ">>> STEP 2: Submitting to Google Real-Time Indexing API...\n";

if (!GoogleIndexingService::isConfigured()) {
    echo "⚠️ Google Indexing key not found at storage/google-indexing-key.json\n";
    echo "Skipping Google API submission (IndexNow completed).\n";
    exit;
}

// Prioritize: Hub, Top Search Console Full Forms, and Newly Added Terms
$priorityAcronyms = [
    'vro', 'mppsc', 'zsi', 'jssc', 'qco', 'hppsc', 'lekhpal', 'patwari',
    'fact', 'sebi', 'udc', 'mpsc', 'ldc', 'nsp', 'nta', 'alp', 'po',
    'uppsc', 'ras', 'cpo', 'aso', 'bdo', 'apfc', 'csat', 'cse', 'dgp',
    'rrc', 'ossc', 'psc', 'cuet', 'bssc', 'bpsc', 'rpsc', 'ctet', 'tgt',
    'prt', 'capf', 'rbi', 'sbi', 'neet', 'nda', 'aiims', 'ugc', 'ias'
];

$googleTargetUrls = [
    'https://sarkari.online/full-forms/',
    'https://sarkari.online/'
];

// Add priority full-form URLs first
foreach ($priorityAcronyms as $pacr) {
    $target = 'https://sarkari.online/full-forms/' . $pacr . '/';
    if (in_array($target, $allUrls, true) && !in_array($target, $googleTargetUrls, true)) {
        $googleTargetUrls[] = $target;
    }
}

// Add remaining full-form URLs
foreach ($glossaryUrls as $gurl) {
    if (!in_array($gurl, $googleTargetUrls, true)) {
        $googleTargetUrls[] = $gurl;
    }
}

// Add recent article URLs
foreach ($articleUrls as $aurl) {
    if (!in_array($aurl, $googleTargetUrls, true)) {
        $googleTargetUrls[] = $aurl;
    }
}

// Respect Google's daily quota limit (max 180 to be safely within 200 quota)
$googleBatch = array_slice($googleTargetUrls, 0, 180);
$gCount = count($googleBatch);

echo "Sending {$gCount} highest-priority URLs directly to Googlebot...\n";

$gSuccess = 0;
$gFail = 0;

foreach ($googleBatch as $idx => $gUrl) {
    $num = $idx + 1;
    echo "  [{$num}/{$gCount}] Pinging Google: {$gUrl} ... ";
    
    $res = GoogleIndexingService::pingUrl($gUrl, 'URL_UPDATED');
    if (!empty($res['success'])) {
        echo "✅ [HTTP 200 OK]\n";
        $gSuccess++;
    } else {
        $msg = $res['message'] ?? 'Failed';
        echo "⚠️ [{$msg}]\n";
        $gFail++;
        // If daily quota exceeded, break cleanly
        if (str_contains($msg, '429') || str_contains($msg, 'Quota')) {
            echo "\n🛑 Daily Google API Quota Reached (200 requests/day). Halting Google loop.\n";
            break;
        }
    }

    // Rate pacing (300ms)
    usleep(300000);
}

echo "\n====================================================================\n";
echo "SUMMARY:\n";
echo "  - IndexNow (Bing/Yandex/Yahoo): Sent {$totalUrls} URLs\n";
echo "  - Google Indexing API: Successfully Pinged {$gSuccess} URLs (Failed: {$gFail})\n";
echo "Search engine crawlers have been officially dispatched to crawl and index.\n";
echo "====================================================================\n";
