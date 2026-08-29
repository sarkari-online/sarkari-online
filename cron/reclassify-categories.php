<?php
/**
 * Sarkari.online - Taxonomy Auto-Reclassifier
 * Ensures articles with "Scholarship", "Admit Card", "Result", "Jobs" match their true category.
 */
require_once dirname(__DIR__) . '/config.php';
use App\Database\Database;
use App\Services\CategoryService;

echo "Running Taxonomy Auto-Reclassifier...\n";

try {
    $articles = Database::fetchAll("SELECT id, title, slug, category_id FROM articles WHERE status = 'published'");
    $correctedCount = 0;

    foreach ($articles as $art) {
        $id = (int)$art['id'];
        $title = $art['title'];
        $currentCatId = (int)$art['category_id'];

        $resolvedCat = CategoryService::autoResolveCategory($title);
        $correctCatId = (int)($resolvedCat['id'] ?? $currentCatId);

        if ($correctCatId !== $currentCatId) {
            Database::update('articles', [
                'category_id' => $correctCatId,
                'updated_at'  => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);

            echo "  [FIXED] Article #{$id} '{$title}'\n";
            echo "          Category ID {$currentCatId} -> {$correctCatId} ({$resolvedCat['name']})\n";
            $correctedCount++;
        }
    }

    echo "Finished: {$correctedCount} article(s) reclassified.\n";

    // Clear cache
    $cacheDir = dirname(__DIR__) . '/storage/cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
