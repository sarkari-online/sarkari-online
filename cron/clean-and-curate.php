<?php
/**
 * EduPulse - Content Quality & Taxonomy Clean-and-Curate Engine
 * Removes non-English/Devanagari articles, purges irrelevant non-student news,
 * auto-corrects category taxonomy across all articles, and refreshes thumbnails.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\CategoryService;
use App\Services\ThumbnailService;
use App\Services\TrendService;
use App\Helpers\Logger;

if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== Env::get('CRON_SECRET', 'edupulse_secure_cron_2026'))) {
    die("Access Denied.\n");
}

echo "===============================================================\n";
echo "   Sarkari.online - Senior Editorial Quality & Taxonomy Audit\n";
echo "===============================================================\n\n";

$allArticles = Database::fetchAll("SELECT * FROM articles ORDER BY id ASC");
$deletedCount = 0;
$reCategorizedCount = 0;
$thumbnailRefreshedCount = 0;

$thumbnailService = new ThumbnailService();

foreach ($allArticles as $art) {
    $id = (int)$art['id'];
    $title = $art['title'];
    $content = $art['content'] ?? '';
    $currentCatId = (int)$art['category_id'];

    // Rule 1: Check for Devanagari / Hindi characters
    $hasHindi = preg_match('/[\x{0900}-\x{097F}]/u', $title) || preg_match('/[\x{0900}-\x{097F}]/u', $content);

    // Rule 2: Check for student relevance
    $isRelevant = TrendService::isEducationRelevant($title, $art['excerpt'] ?? '');

    // Irrelevant blacklisted non-student themes
    $tLower = mb_strtolower($title);
    $isIrrelevantPressRelease = str_contains($tLower, 'khelo india dialogue') || 
                               str_contains($tLower, 'startup summit') || 
                               str_contains($tLower, 'fifth space') ||
                               str_contains($tLower, 'swachhata') ||
                               str_contains($tLower, 'suji k.p.');

    if ($hasHindi || !$isRelevant || $isIrrelevantPressRelease) {
        echo "❌ [DELETE] Article #{$id}: '{$title}' (Reason: " . ($hasHindi ? 'Hindi text' : 'Irrelevant non-student release') . ")\n";
        
        // Remove thumbnail file if exists
        if (!empty($art['featured_image'])) {
            $imgPath = dirname(__DIR__) . '/' . ltrim($art['featured_image'], '/');
            if (file_exists($imgPath)) {
                @unlink($imgPath);
            }
        }

        // Delete from database
        Database::delete('article_checks', 'article_id = :id', ['id' => $id]);
        Database::delete('articles', 'id = :id', ['id' => $id]);
        if (!empty($art['trend_id'])) {
            Database::delete('trends', 'id = :tid', ['tid' => $art['trend_id']]);
        }
        $deletedCount++;
        continue;
    }

    // Rule 3: Auto-Resolve Correct Category
    $autoCat = CategoryService::autoResolveCategory($title, $content);
    $newCatId = (int)($autoCat['id'] ?? $currentCatId);

    if ($newCatId !== $currentCatId) {
        echo "🔄 [RE-CATEGORIZE] Article #{$id}: '{$title}' -> Old Cat: {$currentCatId} -> New Cat: {$newCatId} ({$autoCat['name']})\n";
        Database::update('articles', ['category_id' => $newCatId, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        $reCategorizedCount++;
    }

    // Rule 4: Refresh thumbnail to match category and clear any stale metadata
    try {
        $thumbnailService->generateForArticle($id);
        $thumbnailRefreshedCount++;
    } catch (\Throwable $e) {
        // Thumbnail error ignore
    }
}

// Also purge Hindi / irrelevant trends from trends table
$allTrends = Database::fetchAll("SELECT id, keyword, snippet FROM trends");
$purgedTrends = 0;
foreach ($allTrends as $tr) {
    $trHindi = preg_match('/[\x{0900}-\x{097F}]/u', $tr['keyword']) || preg_match('/[\x{0900}-\x{097F}]/u', $tr['snippet'] ?? '');
    $trRel = TrendService::isEducationRelevant($tr['keyword'], $tr['snippet'] ?? '');
    if ($trHindi || !$trRel) {
        Database::delete('trends', 'id = :id', ['id' => $tr['id']]);
        $purgedTrends++;
    }
}

echo "\n===============================================================\n";
echo "Audit Summary:\n";
echo " - Deleted Irrelevant / Hindi Articles: {$deletedCount}\n";
echo " - Purged Irrelevant Trends: {$purgedTrends}\n";
echo " - Re-categorized Correctly: {$reCategorizedCount}\n";
echo " - Thumbnails Refreshed: {$thumbnailRefreshedCount}\n";
echo "===============================================================\n";
