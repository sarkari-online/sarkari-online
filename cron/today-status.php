<?php
/**
 * Sarkari.online - Today Publishing Status Inspector
 */
require_once dirname(__DIR__) . '/config.php';
use App\Database\Database;
use App\Services\PublishingService;

$today = date('Y-m-d');
echo "\n=======================================================\n";
echo "📊 SARKARI.ONLINE PUBLISHING REPORT: " . date('d M Y, h:i A') . "\n";
echo "=======================================================\n";

try {
    $publishedToday = Database::fetchAll(
        "SELECT a.id, a.title, a.slug, a.status, a.published_at, a.quality_score, c.name AS category_name
         FROM articles a
         LEFT JOIN categories c ON a.category_id = c.id
         WHERE DATE(a.published_at) = CURRENT_DATE AND a.status = 'published'
         ORDER BY a.published_at DESC"
    );

    echo "\n✅ ARTICLES PUBLISHED TODAY (" . count($publishedToday) . " / 5 Limit):\n";
    if (empty($publishedToday)) {
        echo "   (Zero articles auto-published today so far.)\n";
    } else {
        foreach ($publishedToday as $i => $art) {
            $num = $i + 1;
            echo "   {$num}. [#{$art['id']}] {$art['title']}\n";
            echo "      • Category: {$art['category_name']} | Score: {$art['quality_score']}\n";
            echo "      • URL: https://sarkari.online/article/{$art['slug']}/\n";
            echo "      • Time: " . date('h:i A', strtotime($art['published_at'])) . "\n\n";
        }
    }

    $queueStatus = Database::fetchAll(
        "SELECT status, COUNT(*) as count FROM trends GROUP BY status"
    );
    echo "📋 TRENDS & QUEUE PIPELINE:\n";
    foreach ($queueStatus as $q) {
        echo "   • Status '{$q['status']}': {$q['count']}\n";
    }

    $drafts = Database::fetchAll(
        "SELECT id, title, status, quality_score FROM articles WHERE status IN ('draft', 'review', 'pending_review')"
    );
    echo "\n📝 DRAFTS IN REVIEW/PIPELINE: " . count($drafts) . "\n";
    foreach ($drafts as $d) {
        echo "   • [#{$d['id']}] {$d['title']} ({$d['status']} | Score: {$d['quality_score']})\n";
    }

    // Check Backlinks Syndicated
    $backlinkFile = dirname(__DIR__) . '/storage/cache/backlinks_syndicated.json';
    $backlinks = file_exists($backlinkFile) ? (json_decode(file_get_contents($backlinkFile), true) ?: []) : [];
    $todayBacklinks = array_filter($backlinks, fn($b) => str_starts_with($b['syndicated_at'] ?? '', $today));
    echo "\n🔗 DEV.TO BACKLINKS POSTED TODAY: " . count($todayBacklinks) . " / 2 Limit\n";
    foreach ($todayBacklinks as $tb) {
        echo "   • {$tb['title']} -> {$tb['devto_url']}\n";
    }

    echo "\n=======================================================\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
