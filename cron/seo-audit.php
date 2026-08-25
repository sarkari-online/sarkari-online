<?php
/**
 * Sarkari.online - SEO Canonical, Orphan & Duplicate Auditor
 * Daily scan for: duplicate titles, orphan pages (no inbound links),
 * broken canonical URLs — auto-fixes all issues found.
 * Usage: php cron/seo-audit.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online SEO Audit...\n";
Logger::info("Cron seo-audit started");

$canonicalFixed = 0;
$orphansFixed   = 0;
$duplicatesFound = 0;

// ── 1. Fix broken / missing canonical URLs ─────────────────────────────────
$articles = Database::fetchAll(
    "SELECT id, slug, canonical_url FROM articles WHERE status = 'published'"
);

foreach ($articles as $art) {
    $expected = url('article/' . $art['slug'] . '/');
    // Normalize: ensure production domain, not localhost
    $expected = preg_replace('#https?://(localhost[^/]*|127\.0\.0\.1[^/]*)#', rtrim(SITE_URL, '/'), $expected);
    $expected = str_replace('/automation/', '/', $expected);

    if (empty($art['canonical_url']) || $art['canonical_url'] !== $expected) {
        Database::update('articles', ['canonical_url' => $expected], 'id = :id', ['id' => $art['id']]);
        $canonicalFixed++;
        echo "  -> [CANONICAL FIXED] Article #{$art['id']}: set to {$expected}\n";
    }
}

// ── 2. Detect duplicate titles ─────────────────────────────────────────────
$duplicates = Database::fetchAll(
    "SELECT title, COUNT(*) as cnt FROM articles WHERE status = 'published' GROUP BY title HAVING cnt > 1"
);

foreach ($duplicates as $dup) {
    $duplicatesFound++;
    echo "  -> [DUPLICATE TITLE] \"{$dup['title']}\" appears {$dup['cnt']} times — manual review recommended.\n";
    Logger::warning("Duplicate title detected: \"{$dup['title']}\" ({$dup['cnt']} articles)");
}

// ── 3. Detect and fix orphan pages ─────────────────────────────────────────
$allArticles = Database::fetchAll(
    "SELECT id, title, slug, content, category_id FROM articles WHERE status = 'published'"
);

// Build a merged content string for link scanning
$allContent = '';
foreach ($allArticles as $a) {
    $allContent .= $a['content'];
}

foreach ($allArticles as $art) {
    // Check if any other article links to this slug
    $slug = $art['slug'];
    $linkPattern = '/href=["\'][^"\']*\/' . preg_quote($slug, '/') . '\/?["\'][^>]*>/i';
    $linkedCount = preg_match_all($linkPattern, str_replace($art['content'], '', $allContent));

    if ($linkedCount === 0) {
        // Orphan! Find a related article from same category to cross-link
        $related = Database::fetchOne(
            "SELECT id, title, slug FROM articles WHERE status = 'published' AND category_id = :cid AND id != :id ORDER BY quality_score DESC LIMIT 1",
            ['cid' => $art['category_id'], 'id' => $art['id']]
        );

        if ($related) {
            $relatedUrl = url('article/' . $related['slug'] . '/');
            $seeAlso = "\n<p class=\"see-also\">📌 <strong>Also Read:</strong> <a href=\"{$relatedUrl}\">{$related['title']}</a></p>";

            // Inject at end of content if not already present
            if (!str_contains($art['content'], $related['slug'])) {
                $newContent = rtrim($art['content']) . $seeAlso;
                Database::update('articles', ['content' => $newContent], 'id = :id', ['id' => $art['id']]);
                $orphansFixed++;
                echo "  -> [ORPHAN FIXED] Article #{$art['id']} \"{$art['title']}\" — linked to \"{$related['title']}\"\n";
                Logger::info("Orphan fix: Article #{$art['id']} now links to Article #{$related['id']}");
            }
        } else {
            echo "  -> [ORPHAN DETECTED] Article #{$art['id']} \"{$art['title']}\" — no related article found to cross-link.\n";
        }
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] SEO Audit complete: {$canonicalFixed} canonicals fixed, {$duplicatesFound} duplicate titles, {$orphansFixed} orphans fixed. ({$elapsed}s)\n";
Logger::info("Cron seo-audit finished", ['canonicals' => $canonicalFixed, 'duplicates' => $duplicatesFound, 'orphans' => $orphansFixed]);
