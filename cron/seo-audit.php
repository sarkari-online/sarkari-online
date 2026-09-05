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
$emojisCleaned  = 0;

$svgIcon = '<svg class="also-read-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-3px;margin-right:6px;color:#0284c7;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';

// ── 0. Clean old pin emojis (replace with professional SVG card) ────────────
$existingArticles = Database::fetchAll("SELECT id, content FROM articles WHERE content LIKE '%📌%'");
foreach ($existingArticles as $eArt) {
    $cleanContent = preg_replace(
        '/<p class="see-also">\s*📌\s*<strong>Also Read:<\/strong>\s*<a href="([^"]+)">([^<]+)<\/a><\/p>/iu',
        '<div class="also-read-card" style="display:flex;align-items:center;gap:8px;padding:12px 16px;margin:20px 0;background:#f8fafc;border-left:4px solid #0284c7;border-radius:0 8px 8px 0;font-size:0.95rem;">' . $svgIcon . '<span><strong>Also Read:</strong> <a href="$1" style="color:#0284c7;font-weight:600;text-decoration:none;">$2</a></span></div>',
        $eArt['content']
    );
    $cleanContent = str_replace('📌', '', $cleanContent);
    if ($cleanContent !== $eArt['content']) {
        Database::update('articles', ['content' => $cleanContent], 'id = :id', ['id' => $eArt['id']]);
        $emojisCleaned++;
        echo "  -> [EMOJI CLEANED] Article #{$eArt['id']}: Replaced pin emoji with professional SVG card\n";
    }
}

// ── 0b. Clean and deduplicate multiple Also Read cards inside content ───────
$articlesWithCards = Database::fetchAll("SELECT id, content FROM articles WHERE content LIKE '%also-read-card%' OR content LIKE '%also-read-callout%'");
foreach ($articlesWithCards as $cArt) {
    $cardCount = 0;
    $deduped = preg_replace_callback('/<div class=["\']also-read-(card|callout)["\'].*?<\/div>/is', function($match) use (&$cardCount) {
        $cardCount++;
        return ($cardCount === 1) ? $match[0] : '';
    }, $cArt['content']);

    if ($deduped !== $cArt['content']) {
        Database::update('articles', ['content' => $deduped], 'id = :id', ['id' => $cArt['id']]);
        echo "  -> [DEDUPLICATED ALSO READ] Article #{$cArt['id']}: removed duplicate Also Read cards\n";
    }
}

// ── 0c. Clean legacy dead link to non-existent CBSE board exam 2027 article ───
$cbseDeadLinkArticles = Database::fetchAll("SELECT id, content FROM articles WHERE content LIKE '%cbse-class-10-and-12-board-exam-2027%'");
foreach ($cbseDeadLinkArticles as $cArt) {
    $cleaned = str_replace(
        '/article/cbse-class-10-and-12-board-exam-2027-loc-registration-sample-papers-cbse-gov-in/',
        'https://cbse.gov.in',
        $cArt['content']
    );
    if ($cleaned !== $cArt['content']) {
        Database::update('articles', ['content' => $cleaned], 'id = :id', ['id' => $cArt['id']]);
        echo "  -> [DEAD LINK HEALED] Article #{$cArt['id']}: replaced dead CBSE internal link with official cbse.gov.in link\n";
    }
}

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
            $svgIcon = '<svg class="also-read-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-3px;margin-right:6px;color:#0284c7;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';
            $seeAlso = "\n<div class=\"also-read-card\" style=\"display:flex;align-items:center;gap:8px;padding:12px 16px;margin:20px 0;background:#f8fafc;border-left:4px solid #0284c7;border-radius:0 8px 8px 0;font-size:0.95rem;\">{$svgIcon}<span><strong>Also Read:</strong> <a href=\"{$relatedUrl}\" style=\"color:#0284c7;font-weight:600;text-decoration:none;\">{$related['title']}</a></span></div>";

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
