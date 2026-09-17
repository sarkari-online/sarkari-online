<?php
/**
 * Sarkari.online Deep Website Audit Script
 * Run on VPS: docker exec -it sarkari_app php cron/deep_audit.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once __DIR__ . '/../config.php';

use App\Database\Database;

echo "=======================================================================\n";
echo "  SARKARI.ONLINE DEEP AUDIT - " . date('d M Y, h:i A') . "\n";
echo "=======================================================================\n\n";

// ─── 1. DATABASE STATS ─────────────────────────────────────────────────────
echo "━━━ [1/7] DATABASE STATS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$stats = Database::fetchOne("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft
FROM articles");
echo "  Total Articles   : {$stats['total']}\n";
echo "  Published        : {$stats['published']}\n";
echo "  Draft            : {$stats['draft']}\n\n";

// ─── 2. POLICY VIOLATION SCAN ──────────────────────────────────────────────
echo "━━━ [2/7] POLICY VIOLATION SCAN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
// TRUE policy violations — exact phrases only (no word-fragment false positives)
$violationKeywords = [
    'murder',
    'terrorist',
    'porn',
    'nude',
    'casino',
    'leaked answer key',  // specific fraud phrase
    'leaked paper',       // exact fraud phrase (lone "leaked" is OK — warns students)
    'get rich quick',
    'lottery winner',
];

// Word-boundary sensitive — flag only if NOT in educational/govt context
$conditionalKeywords = [
    // 'kill' false-positives in: skill, skillset, skill-based
    '~\bkill\b(?!.{0,30}skill)~i' => 'kill (not in skill context)',
    // 'sex' false-positives in: gender/sex form fields
    '~\bsex\b(?! ?[:/\-])~i'       => 'sex (not in form field context)',
    // 'weapon' — only flag truly violent context, not educational metaphors
    '~\bweapon(?:s|ize|ized)?\b(?!.{0,60}(?:knowledge|rank|best|secret|phishing|cyber))~i'
                                  => 'weapon (violent context)',
    // 'hack' — only flag malicious context
    '~\bhack(?:er|ing|ed)?\b(?!.{0,40}(?:to |life |quick |trick))~i' 
                                  => 'hack (malicious context)',
];

$articles = Database::fetchAll("SELECT id, title, slug, content FROM articles WHERE status='published' ORDER BY id ASC");
$violations = [];

foreach ($articles as $art) {
    $contentLower = strtolower(strip_tags($art['content']));
    $titleLower   = strtolower($art['title']);
    $found = [];

    // Simple exact-phrase check
    foreach ($violationKeywords as $kw) {
        if (strpos($contentLower, $kw) !== false || strpos($titleLower, $kw) !== false) {
            $found[] = $kw;
        }
    }

    // Word-boundary / context-aware regex check
    foreach ($conditionalKeywords as $pattern => $label) {
        if (preg_match($pattern, $contentLower) || preg_match($pattern, $titleLower)) {
            $found[] = $label;
        }
    }

    if ($found) {
        $violations[] = "  ⚠️  #{$art['id']} [{$art['slug']}] → " . implode(', ', $found);
    }
}

if (empty($violations)) {
    echo "  ✅ CLEAN: Zero policy violations across all " . count($articles) . " published articles.\n\n";
} else {
    echo "  Found " . count($violations) . " articles — review each manually:\n";
    foreach ($violations as $v) echo $v . "\n";
    echo "\n  NOTE: Check context before treating as violation — educational metaphors are OK.\n\n";
}

// ─── 3. STALE DATE DETECTION ───────────────────────────────────────────────
echo "━━━ [3/7] STALE DATE DETECTION (Past dates as 'upcoming') ━━━━━━━━━━━━\n";

$pastMonths = [
    'january 2026','february 2026','march 2026','april 2026',
    'may 2026','june 2026','july 2026','august 2026',
    'january 2025','february 2025','march 2025','april 2025',
    'may 2025','june 2025','july 2025','august 2025',
    'september 2025','october 2025','november 2025','december 2025',
];
$applyKeywords = ['apply','register','deadline','last date','open','start','begin','upcoming','form fill'];

$staleIssues = [];
foreach ($articles as $art) {
    $cLower = strtolower(strip_tags($art['content']));
    $issues = [];
    foreach ($pastMonths as $pm) {
        $pos = strpos($cLower, $pm);
        if ($pos !== false) {
            $window = substr($cLower, max(0, $pos - 150), 300);
            foreach ($applyKeywords as $ak) {
                if (strpos($window, $ak) !== false) {
                    $issues[] = "'{$pm}' near apply-context";
                    break;
                }
            }
        }
    }
    // Also check for "2024 expected" / "2025 expected" patterns
    if (preg_match('/202[45]\s*(expected|tentative|will be|to be announced)/i', $art['content'], $m)) {
        $issues[] = "Stale year reference: '{$m[0]}'";
    }
    if ($issues) {
        $staleIssues[] = "  ⚠️  #{$art['id']} [{$art['slug']}]\n    Title: " . substr($art['title'],0,80) . "\n    Issues: " . implode('; ', $issues);
    }
}

if (empty($staleIssues)) {
    echo "  ✅ CLEAN: No stale/past dates found in upcoming context.\n\n";
} else {
    echo "  Found " . count($staleIssues) . " articles with potential stale date issues:\n";
    foreach ($staleIssues as $s) echo $s . "\n\n";
}

// ─── 4. DUPLICATE TITLE CHECK ──────────────────────────────────────────────
echo "━━━ [4/7] DUPLICATE TITLE DETECTION ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$dupTitles = Database::fetchAll("SELECT title, COUNT(*) as cnt FROM articles WHERE status='published' GROUP BY title HAVING cnt > 1");
if (empty($dupTitles)) {
    echo "  ✅ CLEAN: All titles are unique.\n\n";
} else {
    foreach ($dupTitles as $d) echo "  ⚠️  '{$d['title']}' appears {$d['cnt']} times\n";
    echo "\n";
}

// ─── 5. THIN CONTENT CHECK ─────────────────────────────────────────────────
echo "━━━ [5/7] THIN CONTENT CHECK (< 400 words) ━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$thin = [];
$allWords = [];
foreach ($articles as $art) {
    $wc = str_word_count(strip_tags($art['content']));
    $allWords[] = $wc;
    if ($wc < 400) {
        $thin[] = "  ⚠️  #{$art['id']} [{$art['slug']}]: {$wc} words";
    }
}
if (empty($thin)) {
    echo "  ✅ CLEAN: All articles have 400+ words.\n\n";
} else {
    echo "  Found " . count($thin) . " thin articles:\n";
    foreach ($thin as $t) echo $t . "\n";
    echo "\n";
}

// ─── 6. CONTENT STRUCTURE CHECK ────────────────────────────────────────────
echo "━━━ [6/7] CONTENT STRUCTURE CHECK ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$structureIssues = [];
foreach ($articles as $art) {
    $content = $art['content'];
    $issues = [];
    
    // Check for empty/missing H2 headings
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content, $h2m);
    $h2count = count($h2m[0]);
    if ($h2count < 3) {
        $issues[] = "Only {$h2count} H2 headings (should have 4+)";
    }
    
    // Check for broken HTML (unmatched tags)
    $openTags = preg_match_all('/<(p|div|table|ul|ol)[^>]*>/i', $content);
    $closeTags = preg_match_all('/<\/(p|div|table|ul|ol)>/i', $content);
    $tagBalance = abs($openTags - $closeTags);
    if ($tagBalance > 10) {
        $issues[] = "Possible broken HTML (open/close tag diff: {$tagBalance})";
    }
    
    if ($issues) {
        $structureIssues[] = "  ⚠️  #{$art['id']} [{$art['slug']}]: " . implode('; ', $issues);
    }
}
if (empty($structureIssues)) {
    echo "  ✅ CLEAN: All articles have proper content structure.\n\n";
} else {
    echo "  Found " . count($structureIssues) . " structure issues:\n";
    foreach ($structureIssues as $s) echo $s . "\n";
    echo "\n";
}

// ─── 7. CONTENT QUALITY SUMMARY ────────────────────────────────────────────
echo "━━━ [7/7] CONTENT QUALITY SUMMARY ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$avg = count($allWords) ? round(array_sum($allWords)/count($allWords)) : 0;
sort($allWords);
echo "  Total Published  : " . count($articles) . " articles\n";
echo "  Avg Word Count   : {$avg} words\n";
echo "  Min Word Count   : " . (count($allWords) ? min($allWords) : 0) . " words\n";
echo "  Max Word Count   : " . (count($allWords) ? max($allWords) : 0) . " words\n";

// Articles by word count buckets
$b1 = $b2 = $b3 = $b4 = 0;
foreach ($allWords as $wc) {
    if ($wc < 400) $b1++;
    elseif ($wc < 800) $b2++;
    elseif ($wc < 1500) $b3++;
    else $b4++;
}
echo "\n  Word Count Distribution:\n";
echo "    < 400 words   : {$b1} articles\n";
echo "    400–800 words : {$b2} articles\n";
echo "    800–1500 words: {$b3} articles\n";
echo "    1500+ words   : {$b4} articles\n";

// Top 5 shortest articles
echo "\n  📉 5 Shortest Articles:\n";
$sorted = $articles;
usort($sorted, fn($a, $b) => str_word_count(strip_tags($a['content'])) <=> str_word_count(strip_tags($b['content'])));
foreach (array_slice($sorted, 0, 5) as $a) {
    $wc = str_word_count(strip_tags($a['content']));
    echo "    [{$wc} words] " . substr($a['title'], 0, 70) . "\n";
}

echo "\n=======================================================================\n";
echo "  AUDIT COMPLETE - " . date('d M Y, h:i A') . "\n";
echo "=======================================================================\n";
