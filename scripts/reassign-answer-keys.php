<?php
/**
 * Sarkari.online - Reassign Answer Key Articles & Trends to 'answer-keys' Category
 * Scans published and review articles to move all Answer Key / Response Sheet notices
 * into the dedicated 'Answer Keys' category archive.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\CategoryService;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🔄 SARKARI.ONLINE — ANSWER KEYS RE-CATEGORIZATION ENGINE\n";
echo "=================================================================\n\n";

// 1. Ensure 'answer-keys' category exists
$category = CategoryService::getBySlug('answer-keys');
if (!$category) {
    echo "Creating 'Answer Keys' category...\n";
    $catId = CategoryService::create([
        'name' => 'Answer Keys',
        'slug' => 'answer-keys',
        'description' => 'Provisional and final answer keys, response sheets, and question paper challenge updates.',
        'color' => '#7c3aed',
        'bg_light' => '#f5f3ff',
        'icon' => 'check-circle',
        'sort_order' => 4
    ]);
    $category = CategoryService::getById($catId);
}

$targetCatId = (int)$category['id'];
echo "Target Category: '{$category['name']}' (ID: {$targetCatId}, Slug: {$category['slug']})\n\n";

// 2. Find articles matching Answer Key keywords that are currently misclassified
$sql = "
    SELECT id, title, slug, category_id 
    FROM articles 
    WHERE (
        title LIKE '%answer key%' 
        OR title LIKE '%answer-key%' 
        OR title LIKE '%response sheet%' 
        OR title LIKE '%objection%' 
        OR title LIKE '%omr sheet%'
        OR title LIKE '%key challenge%'
        OR title LIKE '%tentative key%'
        OR title LIKE '%provisional key%'
    )
";
$articles = Database::fetchAll($sql);

$reassignedCount = 0;
$alreadyCorrect = 0;

echo "Scanning articles for Answer Key keywords...\n";

foreach ($articles as $art) {
    $artId = (int)$art['id'];
    $currentCatId = (int)$art['category_id'];

    if ($currentCatId === $targetCatId) {
        $alreadyCorrect++;
        continue;
    }

    // Move article to Answer Keys
    Database::update('articles', [
        'category_id' => $targetCatId,
        'updated_at'  => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $artId]);

    echo "  [MOVED] Article #{$artId}: '{$art['title']}'\n";
    echo "          Category changed from ID {$currentCatId} -> ID {$targetCatId} (Answer Keys)\n\n";

    $reassignedCount++;
    Logger::info("Reassigned Article #{$artId} to 'answer-keys' category", [
        'title' => $art['title'],
        'from_cat' => $currentCatId,
        'to_cat' => $targetCatId
    ]);
}

// 3. Also update approved trends in the queue matching Answer Key keywords
$trendsUpdated = Database::query("
    UPDATE trends 
    SET category_hint = 'answer-keys',
        category_id   = :catId 
    WHERE (
        keyword LIKE '%answer key%' 
        OR keyword LIKE '%answer-key%' 
        OR keyword LIKE '%response sheet%'
    ) AND category_id != :catId2
", ['catId' => $targetCatId, 'catId2' => $targetCatId]);

echo "-----------------------------------------------------------------\n";
echo "✅ COMPLETED SUMMARY:\n";
echo "   - Articles moved to 'Answer Keys' : {$reassignedCount}\n";
echo "   - Articles already in 'Answer Keys': {$alreadyCorrect}\n";
echo "   - Total Answer Key articles live   : " . ($reassignedCount + $alreadyCorrect) . "\n";
echo "-----------------------------------------------------------------\n";
echo "Now visit: https://sarkari.online/category/answer-keys/\n";
