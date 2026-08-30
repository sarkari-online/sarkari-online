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

// 3. Check Latest 3 Published Articles
$published = Database::fetchAll("SELECT id, title, slug, status, published_at FROM articles WHERE status = 'published' ORDER BY id DESC LIMIT 3");
echo "3. LATEST 3 PUBLISHED LIVE ARTICLES:\n";
foreach ($published as $p) {
    echo "   - Article #{$p['id']}: {$p['title']}\n";
    echo "     Slug: {$p['slug']} | Published At: {$p['published_at']}\n";
}

echo "\n=================================================================\n";
