<?php
/**
 * Sarkari.online - Today's Publishing & Evergreen Status Checker
 * Run: docker exec sarkari_app php /var/www/html/cron/evergreen-status.php
 */
if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\PublishingService;

echo "=======================================================\n";
echo "📊 SARKARI.ONLINE — TODAY'S PUBLISHING & EVERGREEN STATUS\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================================\n\n";

$service = new PublishingService();
$slotCounts = $service->getPublishedSlotCounts();

echo "🎯 1. DAILY QUOTA SLOTS STATUS:\n";
echo "   - Total Published Today: {$slotCounts['total']} / {$slotCounts['max_total']} (Daily Cap: 5)\n";
echo "   - Official Updates     : {$slotCounts['official']} / {$slotCounts['max_official']} (Cap: 3)\n";
echo "   - Evergreen / Guides   : {$slotCounts['search_intent']} / {$slotCounts['max_search_intent']} (Cap: 2)\n\n";

// Fetch today's published articles
$todayArticles = Database::fetchAll(
    "SELECT a.id, a.title, a.slug, a.status, a.published_at, a.quality_score, c.name AS category_name, c.slug AS category_slug
     FROM articles a
     JOIN categories c ON a.category_id = c.id
     WHERE DATE(a.published_at) = CURRENT_DATE
     ORDER BY a.published_at DESC"
);

echo "📄 2. ARTICLES PUBLISHED TODAY (" . count($todayArticles) . "):\n";
if (empty($todayArticles)) {
    echo "   (None published today yet)\n";
} else {
    foreach ($todayArticles as $i => $art) {
        $type = in_array($art['category_slug'], ['entrance-exams', 'scholarships', 'college-updates', 'career-guides', 'student-technology']) ? '🌲 Evergreen / Guide' : '🏛️ Official Update';
        echo "   [" . ($i+1) . "] #{$art['id']} [{$type}] {$art['title']}\n";
        echo "       Category: {$art['category_name']} | QS: {$art['quality_score']} | Time: {$art['published_at']}\n";
    }
}
echo "\n";

// Check pending drafts or generated articles
$pending = Database::fetchAll(
    "SELECT a.id, a.title, a.status, a.quality_score, a.created_at, c.name AS category_name, c.slug AS category_slug
     FROM articles a
     JOIN categories c ON a.category_id = c.id
     WHERE a.status IN ('draft', 'review', 'pending')
     ORDER BY a.id DESC LIMIT 10"
);

echo "⏳ 3. ARTICLES PENDING IN QUEUE (" . count($pending) . "):\n";
if (empty($pending)) {
    echo "   (Queue is clean - no pending drafts waiting)\n";
} else {
    foreach ($pending as $p) {
        $type = in_array($p['category_slug'], ['entrance-exams', 'scholarships', 'college-updates', 'career-guides', 'student-technology']) ? '🌲 Evergreen' : '🏛️ Official';
        echo "   - #{$p['id']} [{$p['status']}] [{$type}] {$p['title']} (QS: {$p['quality_score']})\n";
    }
}
echo "\n";

// Check evergreen topics in trends table
$evergreenTrends = Database::fetchAll(
    "SELECT id, keyword, status, trend_score, created_at
     FROM trends
     WHERE source = 'evergreen_guides' OR category_hint IN ('career-guides', 'scholarships', 'higher-education')
     ORDER BY id DESC LIMIT 5"
);

echo "🔍 4. EVERGREEN TOPICS IN TREND ENGINE:\n";
foreach ($evergreenTrends as $et) {
    echo "   - #{$et['id']} [{$et['status']}] {$et['keyword']} (Score: {$et['trend_score']})\n";
}
echo "\n=======================================================\n";
