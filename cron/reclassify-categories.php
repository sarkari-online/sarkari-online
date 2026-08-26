<?php
/**
 * Sarkari.online - Article Category Reclassifier
 * Re-scans all existing published articles and maps them to their accurate category taxonomy.
 */

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\CategoryService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting Category Reclassification...\n";

try {
    $articles = Database::fetchAll("SELECT id, title, content, category_id FROM articles WHERE status = 'published'");
    $updatedCount = 0;

    foreach ($articles as $art) {
        $resolvedCat = CategoryService::autoResolveCategory($art['title'], $art['content'] ?? '');
        if ($resolvedCat && (int)$art['category_id'] !== (int)$resolvedCat['id']) {
            Database::update('articles', ['category_id' => $resolvedCat['id']], 'id = :id', ['id' => $art['id']]);
            echo "Article #{$art['id']} ('{$art['title']}') moved to '{$resolvedCat['name']}' (ID: {$resolvedCat['id']})\n";
            $updatedCount++;
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Finished: {$updatedCount} articles reclassified.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
