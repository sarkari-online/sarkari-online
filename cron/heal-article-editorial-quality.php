<?php
/**
 * Sarkari.online - Editorial Quality & Structural Differentiation Healer
 * Rewrites articles according to IntentStructureMap so each article has a distinct
 * structural layout and authoritative journalistic tone (AdSense E-E-A-T Compliant).
 * 
 * Usage:
 *   php cron/heal-article-editorial-quality.php --dry-run=true --batch=3
 *   php cron/heal-article-editorial-quality.php --id=725
 *   php cron/heal-article-editorial-quality.php --batch=10
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only access permitted.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\ArticleRewriteService;
use App\Services\IntentClassifierService;
use App\Services\IntentStructureMap;

$options = getopt('', ['dry-run::', 'batch::', 'id::', 'intent::']);
$isDryRun = isset($options['dry-run']) && ($options['dry-run'] === 'true' || $options['dry-run'] === '1');
$batchLimit = (int)($options['batch'] ?? 3);
$targetId = isset($options['id']) ? (int)$options['id'] : null;
$filterIntent = $options['intent'] ?? null;

echo "========================================================================\n";
echo "🚀 Sarkari.online: Editorial Quality & Intent-Structure Healer\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Simulate Only, No DB Write)" : "LIVE PRODUCTION REWRITE") . "\n";
echo "Batch Limit: {$batchLimit}\n";
echo "========================================================================\n\n";

$sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_name, a.source_url, a.published_at,
               c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'";
$params = [];

if ($targetId) {
    $sql .= " AND a.id = :id";
    $params['id'] = $targetId;
}

$sql .= " ORDER BY a.id DESC LIMIT " . $batchLimit;

$articles = Database::fetchAll($sql, $params);
echo "Fetched " . count($articles) . " published article(s) to process.\n\n";

$rewriteService = new ArticleRewriteService();
$classifier = new IntentClassifierService();

$processed = 0;
foreach ($articles as $art) {
    $artId = (int)$art['id'];
    $title = $art['title'];
    $slug = $art['slug'];

    $intentEnum = $classifier->classify($title, $art['excerpt'] ?? '');
    $intentKey = strtoupper($intentEnum->value);

    if ($filterIntent && strtoupper($filterIntent) !== $intentKey) {
        continue;
    }

    echo "------------------------------------------------------------------------\n";
    echo "▶ Processing Article #{$artId}: [{$intentKey}] {$title}\n";
    echo "  Slug: https://sarkari.online/article/{$slug}/\n";
    
    $sections = IntentStructureMap::getSections($intentKey);
    echo "  Defined Structure (" . count($sections) . " sections): " . implode(' → ', $sections) . "\n\n";

    $result = $rewriteService->rewriteArticle($art);

    if ($result && !empty($result['content'])) {
        $wordCount = str_word_count(strip_tags($result['content']));
        echo "  ✅ Rewritten Successfully! Total Words: {$wordCount}\n";
        echo "  New Excerpt: " . mb_substr($result['excerpt'], 0, 100) . "...\n";

        if ($isDryRun) {
            echo "  [DRY-RUN] Preview of generated HTML (first 400 chars):\n";
            echo "  " . mb_substr(strip_tags($result['content']), 0, 400) . "...\n\n";
        } else {
            Database::execute(
                "UPDATE articles SET content = :content, excerpt = :excerpt, updated_at = NOW() WHERE id = :id",
                [
                    'content' => $result['content'],
                    'excerpt' => $result['excerpt'],
                    'id'      => $artId
                ]
            );
            echo "  💾 Successfully saved to database!\n\n";
        }
        $processed++;
    } else {
        echo "  ❌ Failed to generate rewrite for #{$artId}.\n\n";
    }

    sleep(1); // rate limiting between articles
}

echo "========================================================================\n";
echo "✨ Batch Complete: {$processed} article(s) processed in " . ($isDryRun ? "DRY-RUN" : "LIVE") . " mode.\n";
echo "========================================================================\n";
