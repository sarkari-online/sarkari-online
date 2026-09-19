<?php
/**
 * Sarkari.online - One-Shot Database Sanitizer for &amp; and Duplicate Headings/Titles
 *
 * FIXES:
 * 1. Removes double-encoded &amp;amp; and stray &amp; from article titles, excerpts, and content
 * 2. Deduplicates repeating heading suffixes like:
 *    "Direct Response Sheet &amp; Question Paper Links for NEET PG 2026 Answer Key &amp; Response Sheet for NEET PG 2026 Answer Key & Response Sheet"
 *    -> "Direct Response Sheet & Question Paper Links for NEET PG 2026 Answer Key & Response Sheet"
 * 3. Normalizes all H2/H3 tags and ensures Table of Contents and Page Titles render clean & symbols
 *
 * Usage:
 *   php cron/fix-amp-and-duplicate-titles.php --dry-run
 *   php cron/fix-amp-and-duplicate-titles.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

$options  = getopt('', ['dry-run::']);
$isDryRun = isset($options['dry-run']);

echo "==========================================================\n";
echo "🧹 One-Shot Sanitizer: Clean &amp; and Duplicate Headings\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (no DB save)" : "LIVE DATABASE UPDATE") . "\n";
echo "==========================================================\n\n";

function cleanTitleString(string $str): string {
    // 1. Decode multiple layers of HTML entities down to raw characters
    $clean = $str;
    for ($i = 0; $i < 3; $i++) {
        $prev = $clean;
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($clean === $prev) break;
    }

    // 2. Remove repetitive "for [Exam] for [Exam]" patterns
    // e.g. "for NEET PG 2026 Answer Key & Response Sheet for NEET PG 2026 Answer Key & Response Sheet"
    $clean = preg_replace('/(\bfor\s+[^:\-–|]+?)(?:\s+\1)+/i', '$1', $clean);

    // 3. Remove duplicate words or phrases joined together
    $clean = preg_replace('/\b(\w[\w\s&]{4,30})\s+\1\b/i', '$1', $clean);

    $clean = preg_replace('/\s+/', ' ', $clean);
    return trim($clean);
}

function cleanContentHtml(string $html): string {
    if (empty($html)) return '';

    // 1. First, replace obvious double-encoded HTML entity artifacts
    $html = str_replace(
        ['&amp;amp;', '&amp;quot;', '&amp;#039;', '&amp;nbsp;', '&amp;ndash;', '&amp;mdash;', '&amp;lt;', '&amp;gt;'],
        ['&amp;',     '&quot;',     '&#039;',     '&nbsp;',     '&ndash;',     '&mdash;',     '&lt;',     '&gt;'],
        $html
    );

    // 2. Clean all H2 and H3 tags specifically
    $html = preg_replace_callback('/<h([23])\b([^>]*)>(.*?)<\/h\1>/is', function ($m) {
        $level = $m[1];
        $attrs = $m[2];
        $inner = $m[3];

        // Decode entities inside heading text to examine real content
        $decoded = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Deduplicate repeating "for [Exam] for [Exam]"
        $deduped = preg_replace('/(\bfor\s+[^<\(\)]+?)(?:\s+\1)+/i', '$1', $decoded);

        // Deduplicate repetitive phrase blocks within the same heading
        // e.g. "NEET PG 2026 Answer Key & Response Sheet for NEET PG 2026 Answer Key & Response Sheet"
        $deduped = preg_replace('/\b([A-Za-z0-9\s&]{6,40})\s+for\s+\1\b/i', '$1', $deduped);

        // Also fix any duplicate "for [Exam] for [Exam]"
        $deduped = preg_replace('/(\bfor\s+[^<\(\)]+?)\s+for\s+\1/i', '$1', $deduped);

        $cleanHeading = trim(preg_replace('/\s+/', ' ', $deduped));

        // Re-encode safely with double_encode = false so & becomes &amp; but never &amp;amp;
        $encodedHeading = htmlspecialchars($cleanHeading, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        // Clean any &amp; inside attributes if present
        $cleanAttrs = preg_replace('/&amp;amp;/i', '&amp;', $attrs);

        return "<h{$level}{$cleanAttrs}>{$encodedHeading}</h{$level}>";
    }, $html);

    // 3. Clean any lingering &amp;amp; throughout the HTML
    $html = preg_replace('/&amp;amp;/i', '&amp;', $html);

    return $html;
}

// ─── Step 1: Sanitize Articles ───────────────────────────────────────────────
$articles = Database::fetchAll("SELECT id, title, excerpt, content FROM articles ORDER BY id ASC");
$totalArticles = count($articles);
echo "📋 Processing {$totalArticles} articles...\n\n";

$updatedCount = 0;
$titleFixCount = 0;
$contentFixCount = 0;

foreach ($articles as $art) {
    $id = (int)$art['id'];
    $oldTitle = (string)$art['title'];
    $oldExcerpt = (string)($art['excerpt'] ?? '');
    $oldContent = (string)($art['content'] ?? '');

    $newTitle = cleanTitleString($oldTitle);
    $newExcerpt = cleanTitleString($oldExcerpt);
    $newContent = cleanContentHtml($oldContent);

    $titleChanged = ($newTitle !== $oldTitle);
    $excerptChanged = ($newExcerpt !== $oldExcerpt);
    $contentChanged = ($newContent !== $oldContent);

    if ($titleChanged || $excerptChanged || $contentChanged) {
        $updatedCount++;
        echo "🔧 [Article #{$id}]";
        if ($titleChanged) {
            $titleFixCount++;
            echo " Title fixed: \"{$oldTitle}\" -> \"{$newTitle}\" |";
        }
        if ($contentChanged) {
            $contentFixCount++;
            echo " Headings/Content cleaned |";
        }
        echo "\n";

        if (!$isDryRun) {
            Database::execute(
                "UPDATE articles SET title = :title, excerpt = :excerpt, content = :content, updated_at = updated_at WHERE id = :id",
                [
                    'title'   => $newTitle,
                    'excerpt' => $newExcerpt,
                    'content' => $newContent,
                    'id'      => $id
                ]
            );
        }
    }
}

// ─── Step 2: Sanitize Category Names if any &amp; exist ─────────────────────
try {
    $categories = Database::fetchAll("SELECT id, name FROM categories WHERE name LIKE '%&amp;%' OR name LIKE '%&amp;amp;%'");
    foreach ($categories as $cat) {
        $catId = (int)$cat['id'];
        $cleanCat = cleanTitleString($cat['name']);
        if ($cleanCat !== $cat['name']) {
            echo "🔧 [Category #{$catId}] \"{$cat['name']}\" -> \"{$cleanCat}\"\n";
            if (!$isDryRun) {
                Database::execute("UPDATE categories SET name = :name WHERE id = :id", ['name' => $cleanCat, 'id' => $catId]);
            }
        }
    }
} catch (\Throwable $e) {}

// ─── Step 3: Sanitize Glossary Terms if any &amp; exist ──────────────────────
try {
    $terms = Database::fetchAll("SELECT id, term, acronym FROM glossary_terms WHERE term LIKE '%&amp;%' OR acronym LIKE '%&amp;%'");
    foreach ($terms as $term) {
        $termId = (int)$term['id'];
        $cleanTerm = cleanTitleString($term['term']);
        $cleanAcr  = cleanTitleString($term['acronym']);
        if ($cleanTerm !== $term['term'] || $cleanAcr !== $term['acronym']) {
            echo "🔧 [Glossary #{$termId}] \"{$term['term']}\" -> \"{$cleanTerm}\"\n";
            if (!$isDryRun) {
                Database::execute("UPDATE glossary_terms SET term = :t, acronym = :a WHERE id = :id", ['t' => $cleanTerm, 'a' => $cleanAcr, 'id' => $termId]);
            }
        }
    }
} catch (\Throwable $e) {}

echo "\n==========================================================\n";
echo "✅ SANITIZATION COMPLETE\n";
echo "==========================================================\n";
echo "Total Articles Scanned:  {$totalArticles}\n";
echo "Total Articles Updated:  {$updatedCount}\n";
echo "Titles Cleaned:          {$titleFixCount}\n";
echo "Content/Headings Cleaned:{$contentFixCount}\n";
echo "Mode:                    " . ($isDryRun ? "DRY-RUN (no changes made)" : "LIVE DATABASE SAVED") . "\n";
echo "==========================================================\n";
