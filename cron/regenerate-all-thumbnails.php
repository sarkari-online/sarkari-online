<?php
/**
 * Sarkari.online - CLI Thumbnail Batch Re-Generator
 * Regenerates all published article thumbnails using the 5 specialized
 * editorial designs matching the approved brand identity mockup.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\ThumbnailService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🎨 Starting Batch Thumbnail Re-generation...\n";

$thumbService = new ThumbnailService();

try {
    $articles = Database::fetchAll(
        "SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug AS category_slug, c.name AS category_name
         FROM articles a
         JOIN categories c ON a.category_id = c.id
         WHERE a.status = 'published'
         ORDER BY a.id ASC"
    );

    $total = count($articles);
    echo "Found {$total} published articles to regenerate.\n\n";
    $successCount = 0;
    $errorCount = 0;

    foreach ($articles as $idx => $art) {
        $num = $idx + 1;
        $id = (int)$art['id'];
        $title = $art['title'];
        $cat = $art['category_slug'] ?? 'general';

        echo "[$num/{$total}] Article #{$id} [{$cat}]: \"{$title}\"... ";

        try {
            $res = $thumbService->generateForArticle($id);
            if (!empty($res['success'])) {
                $successCount++;
                echo "✅ REGENERATED -> {$res['relative_path']}\n";
            } else {
                $errorCount++;
                echo "❌ FAILED (" . ($res['error'] ?? 'Unknown error') . ")\n";
            }
        } catch (\Throwable $e) {
            $errorCount++;
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            Logger::error("Thumbnail regeneration failed for #{$id}: " . $e->getMessage());
        }
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Summary: {$successCount} thumbnails successfully regenerated, {$errorCount} errors.\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (\Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
