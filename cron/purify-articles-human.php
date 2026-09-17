<?php
/**
 * Sarkari.online — Section-Level Structural Article Purifier
 * 
 * Uses unified ArticleQualityEngine to safely clean existing published and draft articles:
 * 1. Analyzes quality first (dry-run reporting, issue signals, cleanliness score)
 * 2. Applies DOM-safe structural refactoring (single-line & multiline HTML)
 * 3. Preserves all verified dates, tables, official links, and factual meaning
 * 4. Strips generic AI openers, 5-step download templates, preachy advice, stress FAQs, boilerplate disclaimers
 * 5. Safe batch execution with rollback/error handling
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\ArticleQualityEngine;
use App\Services\HumanizerService;

$options = getopt('', ['slug:', 'all', 'drafts', 'dry-run::', 'batch::', 'id:']);
$targetSlug = $options['slug'] ?? null;
$targetId = isset($options['id']) ? (int)$options['id'] : null;
$includeDrafts = isset($options['drafts']);
$dryRun = isset($options['dry-run']) ? filter_var($options['dry-run'], FILTER_VALIDATE_BOOLEAN) : false;
$batch = isset($options['batch']) ? (int)$options['batch'] : 0;

echo "================================================================================\n";
echo "🧹 SARKARI.ONLINE — UNIFIED EDITORIAL ARTICLE QUALITY ENGINE (REPROCESSOR)\n";
echo "Mode   : " . ($dryRun ? "🔍 DRY-RUN (Audit & Analysis Only)" : "⚡ LIVE UPDATE (DB will be updated)") . "\n";
echo "Scope  : " . ($includeDrafts ? "Drafts & Published" : "Published Articles Only") . "\n";
if ($targetSlug) echo "Target : Slug '{$targetSlug}'\n";
if ($targetId)   echo "Target : Article ID #{$targetId}\n";
echo "================================================================================\n\n";

$statusFilter = $includeDrafts ? "status IN ('published', 'draft', 'review')" : "status = 'published'";
$sql = "SELECT id, title, slug, content, source_url, status FROM articles WHERE {$statusFilter}";
$params = [];

if ($targetSlug) {
    $sql .= " AND slug = :slug";
    $params['slug'] = $targetSlug;
} elseif ($targetId) {
    $sql .= " AND id = :id";
    $params['id'] = $targetId;
} else {
    $sql .= " ORDER BY id DESC";
    if ($batch > 0) {
        $sql .= " LIMIT {$batch}";
    } elseif (!isset($options['all'])) {
        $sql .= " LIMIT 15";
    }
}

$articles = Database::fetchAll($sql, $params);
echo "Found " . count($articles) . " article(s) to evaluate.\n\n";

$cleanedCount = 0;
$alreadyCleanCount = 0;
$errorCount = 0;

foreach ($articles as $art) {
    $id = (int)$art['id'];
    $title = $art['title'];
    $slug = $art['slug'];
    $content = $art['content'] ?? '';
    $sourceUrl = $art['source_url'] ?? '';

    // Step 1: Pre-cleanup Quality Analysis
    $analysis = ArticleQualityEngine::analyzeQuality($content, $title);

    echo "📄 [#{$id}] {$title}\n";
    echo "   Initial Cleanliness Score: {$analysis['score']}/100\n";

    if (!$analysis['needs_refactor']) {
        echo "   ✅ Already human-editorial grade. No changes required.\n\n";
        $alreadyCleanCount++;
        continue;
    }

    echo "   ⚠️ Issues detected:\n";
    foreach ($analysis['issues'] as $code => $desc) {
        echo "      - [{$code}] {$desc}\n";
    }

    // Step 2: Safe Content Refactoring via ArticleQualityEngine
    try {
        $refactor = ArticleQualityEngine::refactorContent($content, $title, $sourceUrl);
        $newContent = $refactor['content'];

        // Extra Safety Check: Ensure essential tables were not lost
        $origTables = substr_count(strtolower($content), '<table');
        $newTables = substr_count(strtolower($newContent), '<table');
        if ($origTables > 0 && $newTables < $origTables) {
            echo "   ❌ SAFETY GATE FAILED: Table count mismatch ({$newTables} vs original {$origTables}). Preserving original.\n\n";
            $errorCount++;
            continue;
        }

        // Post-cleanup Quality Check
        $postAnalysis = ArticleQualityEngine::analyzeQuality($newContent, $title);
        $delta = mb_strlen($newContent) - mb_strlen($content);

        echo "   ✂️ Refactored: Score {$analysis['score']}/100 → {$postAnalysis['score']}/100 (Size delta: {$delta} chars)\n";
        if (!empty($refactor['changes'])) {
            echo "   Actions applied:\n";
            foreach ($refactor['changes'] as $action) {
                echo "      ✓ {$action}\n";
            }
        }

        if (!$dryRun) {
            Database::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                ['content' => $newContent, 'id' => $id]
            );
            echo "   💾 SAVED: Database updated successfully.\n";
        } else {
            echo "   🔍 DRY-RUN: Database NOT modified.\n";
        }

        $cleanedCount++;
    } catch (\Throwable $e) {
        echo "   ❌ Error processing article #{$id}: " . $e->getMessage() . "\n";
        $errorCount++;
    }

    echo "\n";
}

echo "================================================================================\n";
echo "📊 REPROCESSOR RUN SUMMARY\n";
echo "Total Evaluated : " . count($articles) . "\n";
echo "Refactored      : {$cleanedCount}\n";
echo "Already Clean   : {$alreadyCleanCount}\n";
echo "Errors / Blocked: {$errorCount}\n";
echo "================================================================================\n";
