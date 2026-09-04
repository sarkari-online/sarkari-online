<?php
/**
 * Sarkari.online - Delete Duplicate UPSC Article & Reset Slot 1 to Article #44
 *
 * 1. Permanently deletes Article #45 ('upsc-exam-schedule-result-updates-2026')
 *    and its associated checks and thumbnail.
 * 2. Maps Slot 1 (10:00 AM IST) cleanly to Article #44 ('upsc-nda-2026-admit-card-download')
 *    so Slot 1 remains completed for today and the system cleanly waits for Slot 2 (02:00 PM IST).
 * 3. Purges any leftover generic placeholder trends from DB.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\AutoCronService;

echo "=================================================================\n";
echo "🗑️  SARKARI.ONLINE — DELETE DUPLICATE UPSC ARTICLE & MAP SLOT 1\n";
echo "=================================================================\n\n";

$targetSlug = 'upsc-exam-schedule-result-updates-2026';

// 1. Locate the target article
$article = Database::fetchOne(
    "SELECT id, title, slug, trend_id, featured_image, published_at FROM articles WHERE slug = :slug LIMIT 1",
    ['slug' => $targetSlug]
);

if (!$article) {
    // Fallback search: any second UPSC article published today that is NOT upsc-nda-2026-admit-card-download
    $article = Database::fetchOne(
        "SELECT id, title, slug, trend_id, featured_image, published_at 
         FROM articles 
         WHERE slug != 'upsc-nda-2026-admit-card-download'
           AND (slug LIKE '%upsc-exam-schedule%' OR title LIKE '%UPSC%Schedule%')
           AND DATE(published_at) = CURRENT_DATE 
         ORDER BY id DESC LIMIT 1"
    );
}

if (!$article) {
    echo "ℹ️ Target article '{$targetSlug}' is already deleted or not found in the database.\n\n";
} else {
    $articleId = (int)$article['id'];
    echo "1. Found Duplicate Article to Delete:\n";
    echo "   - ID          : #{$articleId}\n";
    echo "   - Title       : {$article['title']}\n";
    echo "   - Slug        : {$article['slug']}\n";
    echo "   - Published At: {$article['published_at']}\n";
    echo "   - Trend ID    : " . ($article['trend_id'] ?? 'None') . "\n\n";

    // Delete article_checks
    Database::delete('article_checks', 'article_id = :id', ['id' => $articleId]);
    echo "   ✓ Deleted article checks\n";

    // Delete thumbnail if present on disk
    if (!empty($article['featured_image'])) {
        $thumbPath = dirname(__DIR__) . '/' . ltrim($article['featured_image'], '/');
        if (file_exists($thumbPath)) {
            @unlink($thumbPath);
            echo "   ✓ Deleted thumbnail file: {$article['featured_image']}\n";
        }
    }

    // Reject originating trend
    if (!empty($article['trend_id'])) {
        Database::query(
            "UPDATE trends SET status = 'rejected', raw_payload = JSON_SET(COALESCE(raw_payload, '{}'), '$.reason', 'Deleted: Duplicate UPSC article deleted by admin') WHERE id = :tid",
            ['tid' => (int)$article['trend_id']]
        );
        echo "   ✓ Marked originating Trend #{$article['trend_id']} as rejected\n";
    }

    // Delete the article record
    Database::delete('articles', 'id = :id', ['id' => $articleId]);
    echo "   ✅ SUCCESS: Article #{$articleId} ('{$article['slug']}') has been PERMANENTLY DELETED!\n\n";
}

// 2. Map Slot 1 history to Article #44 (upsc-nda-2026-admit-card-download)
$ndaArticle = Database::fetchOne(
    "SELECT id, title, slug, published_at FROM articles WHERE slug = 'upsc-nda-2026-admit-card-download' OR (title LIKE '%NDA%' AND DATE(published_at) = CURRENT_DATE) ORDER BY id ASC LIMIT 1"
);

if ($ndaArticle) {
    echo "2. Found Today's Original UPSC Article:\n";
    echo "   - ID          : #{$ndaArticle['id']}\n";
    echo "   - Title       : {$ndaArticle['title']}\n";
    echo "   - Slug        : {$ndaArticle['slug']}\n";
    echo "   - URL         : https://sarkari.online/article/{$ndaArticle['slug']}/\n\n";

    // Update daily slots state so Slot 1 points cleanly to this article
    AutoCronService::recordSlotCompleted(1, (int)$ndaArticle['id']);
    echo "   ✓ Scheduled Slot 1 (10:00 AM IST) successfully mapped to Article #{$ndaArticle['id']}.\n";
} else {
    echo "2. Note: Original NDA article not found by exact slug; Slot 1 state preserved.\n";
}

// 3. Purge all remaining generic placeholder trends from DB
echo "\n3. Purging synthetic placeholder trends from trends queue...\n";
Database::query("
    UPDATE trends 
    SET status = 'rejected',
        raw_payload = JSON_SET(COALESCE(raw_payload, '{}'), '$.reason', 'Purged: Synthetic placeholder topic')
    WHERE status IN ('detected', 'approved', 'analyzing')
      AND (
          keyword LIKE '%Exam Schedule, Result and Recruitment Update%'
          OR keyword LIKE '%Latest Notification%Exam Schedule%'
          OR keyword LIKE '%Latest Notification September 2026%'
          OR keyword LIKE '%Latest Notification August 2026%'
      )
");
echo "   ✓ Synthetic placeholder trends purged.\n\n";

// 4. Print updated status
$schedule = AutoCronService::getISTSlotSchedule();
$slotsState = AutoCronService::getDailySlotsState();
$completedSlots = $slotsState['completed_slots'] ?? [];

echo "4. Updated Publishing Schedule Status:\n";
echo "   - Slot 1 (10:00 AM IST): " . (in_array(1, $completedSlots, true) ? "✅ COMPLETED (Article #" . ($slotsState['slot_history'][1]['article_id'] ?? 'N/A') . ")" : "⏳ PENDING") . "\n";
echo "   - Slot 2 (02:00 PM IST): " . (in_array(2, $completedSlots, true) ? "✅ COMPLETED" : "⏳ UNLOCKS AT 02:00 PM (in ~{$schedule['wait_minutes']}m)") . "\n";
echo "   - Slot 3 (06:00 PM IST): " . (in_array(3, $completedSlots, true) ? "✅ COMPLETED" : "⏳ UNLOCKS AT 06:00 PM") . "\n";
echo "   Next Scheduled Trigger : {$schedule['next_slot_name']}\n";

echo "\n=================================================================\n";
echo "✅ DUPLICATE ARTICLE DELETION & SLOT SYNCHRONIZATION COMPLETE!\n";
echo "=================================================================\n";
