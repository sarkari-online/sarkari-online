<?php
/**
 * Sarkari.online - Master Quality & AdSense Healer Script
 * Cleans dangling colons, strips intent-misaligned sections via IntentSanitizer,
 * rebuilds corrupted milestone tables from exam_cycles, and deduplicates boilerplate.
 * 
 * Usage:
 *   php cron/humanize-existing-content.php --slug=cbse-datesheet-2027-class-10-12 --dry-run=true
 *   php cron/humanize-existing-content.php --slug=neet-pg-2026-answer-key-response-sheet --dry-run=true
 *   php cron/humanize-existing-content.php --articles --batch=10 --dry-run=true
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only access permitted.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use App\Services\HumanizerService;
use App\Services\IntentClassifierService;
use App\Services\IntentSanitizer;
use App\Services\MilestoneTableRenderer;

$options = getopt('', ['glossary', 'articles', 'limit::', 'batch::', 'slug::', 'dry-run::', 'all']);
$isGlossary = isset($options['glossary']);
$isArticles = isset($options['articles']);
$targetSlug = $options['slug'] ?? null;
$limit = (int)($options['batch'] ?? ($options['limit'] ?? 10));
$dryRun = !isset($options['dry-run']) || in_array(strtolower((string)$options['dry-run']), ['1', 'true', 'yes'], true);

if (!$isGlossary && !$isArticles && !$targetSlug) {
    echo "Usage:\n";
    echo "  php cron/humanize-existing-content.php --slug=<slug> [--dry-run=true|false]\n";
    echo "  php cron/humanize-existing-content.php --articles --batch=10 [--dry-run=true|false]\n";
    echo "  php cron/humanize-existing-content.php --glossary [--dry-run=true|false]\n";
    exit(0);
}

$gemini = new Gemini();
$classifier = new IntentClassifierService($gemini);
$intentSanitizer = new IntentSanitizer();
$tableRenderer = new MilestoneTableRenderer();

echo "======================================================================\n";
echo "🛡️ SARKARI.ONLINE MASTER QUALITY & ADSENSE HEALER\n";
echo "Mode: " . ($dryRun ? "🔍 DRY RUN (Zero database writes)" : "⚡ LIVE RUN (Database will be updated)") . "\n";
echo "======================================================================\n\n";

if ($isArticles || $targetSlug) {
    $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_url,
                   ec.id as cycle_id, ec.current_phase, ec.facts_json
            FROM articles a
            LEFT JOIN exam_cycle_articles eca ON eca.article_id = a.id
            LEFT JOIN exam_cycles ec ON ec.id = eca.exam_cycle_id
            WHERE a.status = 'published'";
    $params = [];
    if ($targetSlug) {
        $sql .= " AND a.slug = :slug";
        $params['slug'] = $targetSlug;
    } else {
        $sql .= " ORDER BY a.id DESC LIMIT " . $limit;
    }

    $articles = Database::fetchAll($sql, $params);
    echo "Found " . count($articles) . " article(s) to inspect.\n\n";

    $report = [];

    foreach ($articles as $art) {
        $artId = (int)$art['id'];
        $title = $art['title'];
        $slug  = $art['slug'];
        $content = $art['content'];
        $originalLength = mb_strlen($content);

        echo "----------------------------------------------------------------------\n";
        echo "📄 PROCESSING ARTICLE #{$artId}: {$title}\n";
        echo "   Slug: /article/{$slug}/\n";

        // Step 1: Detect Article Intent
        $intentEnum = $classifier->classify($title, $art['excerpt'] ?? '');
        $intentStr  = $intentEnum->value;
        echo "   [1] Detected Intent: " . strtoupper($intentStr) . "\n";

        // Step 2: IntentSanitizer (Strip unallowed H2 sections)
        $sanitizedResult = $intentSanitizer->sanitize($intentStr, $content);
        $contentAfterSanitizer = $sanitizedResult['html'];
        $removedSections = $sanitizedResult['removed_sections'];
        echo "   [2] IntentSanitizer:\n";
        if (!empty($removedSections)) {
            foreach ($removedSections as $rSec) {
                echo "       ✂️ STRIPPED: {$rSec}\n";
            }
        } else {
            echo "       ✅ All sections aligned with IntentStructureMap.\n";
        }

        // Step 3: Milestone Table Rebuilding (Zero Patching)
        $facts = [];
        if (!empty($art['facts_json'])) {
            $facts = json_decode($art['facts_json'], true) ?: [];
        }
        $tableRebuildStatus = "No table updated";

        if (!empty($art['cycle_id']) && !empty($facts)) {
            $cycle = [
                'id'            => $art['cycle_id'],
                'current_phase' => $art['current_phase'],
                'facts_json'    => $art['facts_json']
            ];
            $newTableHtml = $tableRenderer->render($cycle, $intentStr);
            if (!empty($newTableHtml)) {
                // Replace the first milestone table in content
                if (preg_match('/<div class=["\']table-responsive["\']>\s*<table\b[^>]*>.*?<\/table>\s*<\/div>|<table\b[^>]*>.*?<\/table>/is', $contentAfterSanitizer, $tblMatch)) {
                    $contentAfterSanitizer = substr_replace($contentAfterSanitizer, $newTableHtml, strpos($contentAfterSanitizer, $tblMatch[0]), strlen($tblMatch[0]));
                    $tableRebuildStatus = "Rebuilt from exam_cycle #{$art['cycle_id']} facts_json";
                    echo "   [3] Milestone Table: ✅ REBUILT from linked exam_cycle #{$art['cycle_id']}\n";
                }
            }
        } else {
            // Orphaned article fallback: Deduplicate identical <tr> rows in existing tables
            $dedupCount = 0;
            $contentAfterSanitizer = preg_replace_callback('/<table\b[^>]*>(.*?)<\/table>/is', function($tblMatches) use (&$dedupCount) {
                $tbody = $tblMatches[1];
                if (preg_match_all('/<tr\b[^>]*>.*?<\/tr>/is', $tbody, $trMatches)) {
                    $seenRows = [];
                    foreach ($trMatches[0] as $trHtml) {
                        $cleanRowText = trim(preg_replace('/\s+/', ' ', strip_tags($trHtml)));
                        if (isset($seenRows[$cleanRowText])) {
                            $tbody = str_replace($trHtml, '', $tbody);
                            $dedupCount++;
                        } else {
                            $seenRows[$cleanRowText] = true;
                        }
                    }
                }
                return "<table" . substr($tblMatches[0], 6, strpos($tblMatches[0], '>') - 6) . ">{$tbody}</table>";
            }, $contentAfterSanitizer);

            if ($dedupCount > 0) {
                $tableRebuildStatus = "Deduplicated {$dedupCount} identical table rows (orphaned fallback)";
                echo "   [3] Milestone Table: ⚠️ Orphaned article fallback — deduplicated {$dedupCount} duplicate rows\n";
            } else {
                echo "   [3] Milestone Table: ✅ Table integrity verified\n";
            }
        }

        // Step 4: Dangling Colon Healing (LLM primary, defensive regex fallback)
        $colonResult = HumanizerService::healDanglingColons($contentAfterSanitizer, $facts, $gemini);
        $contentAfterColons = $colonResult['html'];
        $colonStats = $colonResult['stats'];
        echo "   [4] Dangling Colon Healing:\n";
        echo "       - LLM Healed with Lists: {$colonStats['llm_healed']}\n";
        echo "       - Removed Incomplete Intro Sentences: {$colonStats['removed']}\n";
        echo "       - Regex Fallback (. instead of :): {$colonStats['regex_fallback']}\n";

        // Step 5: Deduplicate Twin Paragraphs (> 70% textual similarity)
        $dedupResult = HumanizerService::deduplicateParagraphs($contentAfterColons, 0.70);
        $contentAfterDedup = $dedupResult['html'];
        $dupRemovedCount = $dedupResult['duplicates_removed'];
        echo "   [5] Paragraph Deduplication: Removed {$dupRemovedCount} repeated/twin paragraph(s)\n";

        // Step 6: Master Humanizer Polish (contractions, opening hook, anti-AI tone)
        $finalContent = HumanizerService::humanize($contentAfterDedup, $title, $art['source_url'] ?? '', $intentStr);
        $finalLength = mb_strlen($finalContent);
        echo "   [6] Final Polish: Original {$originalLength} chars -> Cleaned {$finalLength} chars\n";

        // Record Article Summary
        $report[] = [
            'id'               => $artId,
            'title'            => $title,
            'slug'             => $slug,
            'intent'           => $intentStr,
            'removed_sections' => $removedSections,
            'table_status'     => $tableRebuildStatus,
            'colon_stats'      => $colonStats,
            'dups_removed'     => $dupRemovedCount,
            'original_content' => $content,
            'cleaned_content'  => $finalContent,
        ];

        // Step 7: Apply to DB if live run
        if (!$dryRun) {
            Database::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                ['content' => $finalContent, 'id' => $artId]
            );
            echo "   💾 SAVED: Article #{$artId} updated in database.\n";
        } else {
            echo "   🔍 DRY-RUN: Database unchanged.\n";
        }
        echo "\n";
    }

    // Print Consolidated Output Summary
    echo "======================================================================\n";
    echo "📊 CONSOLIDATED EXECUTION DELIVERABLES\n";
    echo "======================================================================\n";
    foreach ($report as $idx => $r) {
        echo "\n--- DELIVERABLE FOR [{$r['slug']}] ---\n";
        echo "1. IntentSanitizer Stripped Sections (" . count($r['removed_sections']) . "):\n";
        if (empty($r['removed_sections'])) {
            echo "   None (clean structure)\n";
        } else {
            foreach ($r['removed_sections'] as $s) {
                echo "   - {$s}\n";
            }
        }
        echo "2. Table Rebuild: {$r['table_status']}\n";
        echo "3. Dangling Colon Stats:\n";
        echo "   - LLM List Healed: {$r['colon_stats']['llm_healed']}\n";
        echo "   - Incomplete Sentences Removed: {$r['colon_stats']['removed']}\n";
        echo "   - Regex Fallbacks (.): {$r['colon_stats']['regex_fallback']}\n";
        echo "4. Twin Paragraphs Stripped: {$r['dups_removed']}\n";

        // Show Before / After Snippet Diff
        echo "5. Before/After Headings Structure:\n";
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $r['original_content'], $origH2);
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $r['cleaned_content'], $cleanH2);
        echo "   [Original Headings (" . count($origH2[1]) . ")]:\n";
        foreach ($origH2[1] as $h) {
            echo "     * " . trim(strip_tags($h)) . "\n";
        }
        echo "   [Cleaned Headings (" . count($cleanH2[1]) . ")]:\n";
        foreach ($cleanH2[1] as $h) {
            echo "     * " . trim(strip_tags($h)) . "\n";
        }
    }
}
