<?php
/**
 * Sarkari.online - Live Queue & Approved Topics Status Checker
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "=================================================================\n";
echo "📊 SARKARI.ONLINE — LIVE QUEUE & APPROVED STATUS CHECKER\n";
echo "=================================================================\n\n";

// 1. Check Approved Trends
$approved = Database::fetchAll("SELECT id, keyword, status, trend_score, detected_at, processed_at FROM trends WHERE status = 'approved' ORDER BY id DESC");
echo "1. TRENDS IN 'APPROVED' STATUS: " . count($approved) . "\n";
if (empty($approved)) {
    echo "   (No trends currently waiting in approved status)\n";
} else {
    foreach ($approved as $t) {
        echo "   - Trend #{$t['id']}: {$t['keyword']}\n";
        echo "     Score: {$t['trend_score']} | Status: {$t['status']} | Processed: " . ($t['processed_at'] ?? 'Pending AI generation') . "\n";
    }
}
echo "\n";

// 2. Check Articles in Review Queue
$inReview = Database::fetchAll("SELECT id, title, slug, status, quality_score, created_at FROM articles WHERE status = 'review' ORDER BY id DESC");
echo "2. ARTICLES IN 'REVIEW QUEUE' (status = 'review'): " . count($inReview) . "\n";
if (empty($inReview)) {
    echo "   (Review Queue is currently empty)\n";
} else {
    foreach ($inReview as $r) {
        echo "   - Article #{$r['id']}: {$r['title']}\n";
        echo "     Quality Score: {$r['quality_score']}/100 | Created: {$r['created_at']}\n";
    }
}
echo "\n";

// 3. Check Today's Published Articles & Quota Progress
$todayPublished = Database::fetchAll("
    SELECT id, title, slug, status, published_at, created_at, quality_score 
    FROM articles 
    WHERE status = 'published' AND DATE(published_at) = CURRENT_DATE 
    ORDER BY published_at DESC
");

echo "3. TODAY'S PUBLISHED ARTICLES (" . count($todayPublished) . " / 5 Daily Quota):\n";
if (empty($todayPublished)) {
    echo "   (No articles published today yet)\n";
} else {
    foreach ($todayPublished as $idx => $p) {
        $num = $idx + 1;
        $timeStr = !empty($p['published_at']) ? date('d M Y, h:i:s A', strtotime($p['published_at'])) . ' IST' : 'N/A';
        echo "   {$num}. Article #{$p['id']}: {$p['title']}\n";
        echo "      Published At : {$timeStr}\n";
        echo "      Created At   : {$p['created_at']}\n";
        echo "      Quality Score: {$p['quality_score']}/100\n";
    }
}

if (!empty($todayPublished[0]['published_at'])) {
    $lastTime = strtotime($todayPublished[0]['published_at']);
    $minsAgo = round((time() - $lastTime) / 60);
    echo "\n🕒 LAST ARTICLE PUBLISHED: " . date('h:i:s A', $lastTime) . " IST ({$minsAgo} minutes ago)\n";
}

$remaining = max(0, 5 - count($todayPublished));
echo "🎯 REMAINING DAILY QUOTA : {$remaining} article" . ($remaining === 1 ? '' : 's') . "\n";
echo "\n=================================================================\n";
