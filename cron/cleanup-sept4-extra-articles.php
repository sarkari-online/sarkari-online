<?php
/**
 * ONE-TIME CLEANUP — Sept 4, 2026
 * Deletes 5 articles that were auto-published outside scheduled slot windows.
 * Keeps Article #666 (UPSC NDA — correct 10 AM slot article).
 * Resets slot state so only Slot 1 (10 AM) shows as completed.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\AutoCronService;
use App\Services\SettingsService;

echo "=================================================================\n";
echo "CLEANUP — DELETE 5 EXTRA AUTO-PUBLISHED ARTICLES (Sept 4)\n";
echo "=================================================================\n\n";

// These 5 articles published OUTSIDE the slot schedule — must be deleted
$toDelete = [669, 671, 673, 674, 675];
$keepId   = 666; // UPSC NDA — correct 10 AM slot article

foreach ($toDelete as $articleId) {
    $article = Database::fetchOne(
        "SELECT id, title, slug, featured_image, trend_id FROM articles WHERE id = :id LIMIT 1",
        ['id' => $articleId]
    );

    if (!$article) {
        echo "   Article #{$articleId} not found (maybe already deleted).\n";
        continue;
    }

    echo "Deleting Article #{$articleId}: {$article['slug']}\n";

    Database::delete('article_checks', 'article_id = :id', ['id' => $articleId]);

    if (!empty($article['featured_image'])) {
        $thumbPath = dirname(__DIR__) . '/' . ltrim($article['featured_image'], '/');
        if (file_exists($thumbPath)) {
            @unlink($thumbPath);
            echo "   Deleted thumbnail.\n";
        }
    }

    // Reset originating trend back to 'approved' so it can be picked at next slot
    if (!empty($article['trend_id'])) {
        Database::query(
            "UPDATE trends SET status = 'approved' WHERE id = :tid",
            ['tid' => (int)$article['trend_id']]
        );
        echo "   Trend #{$article['trend_id']} reset to approved.\n";
    }

    Database::delete('articles', 'id = :id', ['id' => $articleId]);
    echo "   SUCCESS: Article #{$articleId} DELETED.\n\n";
}

// Reset slot state: only Slot 1 done
$today = date('Y-m-d');
$state = [
    'date'            => $today,
    'completed_slots' => [1],
    'slot_history'    => [
        1 => [
            'executed_at' => '2026-09-04 09:17:59',
            'article_id'  => $keepId
        ]
    ]
];
SettingsService::set('cron_daily_slots_state', json_encode($state), 'json', 'Daily autonomous slot execution state');

echo "=================================================================\n";
echo "Slot state reset:\n";
echo "   Slot 1 (10:00 AM): COMPLETED => Article #{$keepId} (UPSC NDA)\n";
echo "   Slot 2 (02:00 PM): PENDING   => Will auto-publish at 2:00 PM IST\n";
echo "   Slot 3 (06:00 PM): PENDING   => Will auto-publish at 6:00 PM IST\n";
echo "\nCLEANUP COMPLETE!\n";
echo "=================================================================\n";
