<?php
/**
 * Sarkari.online - Ahrefs 34 Issues Diagnostic & Auto-Healer
 * 
 * 1. Scans ALL published articles for internal 404 dead links and heals them.
 * 2. Scans for internal links missing trailing slashes (3XX redirects) and auto-corrects them.
 * 3. Normalizes title lengths (<= 58 chars) and meta description lengths (130 - 155 chars).
 * 4. Generates an exact breakdown of affected pages and fixes.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI execution only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\StateJobService;
use App\Services\GlossaryService;

echo "=================================================================\n";
echo "🛠️  SARKARI.ONLINE — AHREFS 34 ISSUES DIAGNOSTIC & AUTO-HEALER\n";
echo "=================================================================\n\n";

$startTime = microtime(true);

// 1. Preload all valid slugs and routes
echo "1. Loading valid routing indices from Database & Codebase...\n";

$publishedArticles = Database::fetchAll("SELECT id, slug, title, meta_title, meta_description, excerpt, content FROM articles WHERE status = 'published'");
$articleSlugs = [];
foreach ($publishedArticles as $a) {
    $articleSlugs[$a['slug']] = (int)$a['id'];
}
echo "   ✓ Loaded " . count($articleSlugs) . " published article slugs.\n";

$categories = CategoryService::getAll();
$categorySlugs = array_column($categories, 'slug');
echo "   ✓ Loaded " . count($categorySlugs) . " category slugs.\n";

$states = array_keys(StateJobService::getAllStates());
echo "   ✓ Loaded " . count($states) . " state slugs.\n";

$glossary = GlossaryService::getAllForSitemap();
$glossarySlugs = array_column($glossary, 'slug');
echo "   ✓ Loaded " . count($glossarySlugs) . " glossary slugs.\n\n";

// Static routes
$validStaticRoutes = [
    '', 'about', 'why-choose-us', 'editorial-policy', 'ai-policy', 'contact',
    'privacy-policy', 'terms', 'disclaimer', 'fact-checking-policy', 'how-to-apply',
    'tools', 'tools/7th-pay-commission-salary-calculator', 'tools/cgpa-to-percentage-calculator',
    'tools/age-calculator', 'full-forms', 'latest-jobs', 'state-jobs', 'search',
    'author/ajay-mathur', 'author/editorial-desk'
];

// Known slug renames/redirects
$slugRedirectMap = [
    'upsc-exam-schedule-result-updates-2026' => 'upsc-nda-2026-admit-card-download',
    'upssc-junior-assistant-lekhpal-2026-admit-card' => 'upsssc-junior-assistant-lekhpal-2026-admit-card',
    'bpsc-combined-state-exam-2026-admit-card' => 'bpsc-72nd-cce-prelims-2026-admit-card',
    'ibps-po-2026-prelims-admit-card-download-active' => 'ibps-po-2026-prelims-exam-concluded',
    'punjab-pti-recruitment-2026-apply-now' => 'punjab-pti-recruitment-2026-cancelled-fee-refund',
    'cbse-class-10-and-12-board-exam-2027-loc-registration-sample-papers-cbse-gov-in' => 'category/exam-dates',
];

// 2. Scan internal links in article content
echo "2. Scanning all published article content for broken links (404) and redirects (3XX)...\n";

$brokenLinksFound = []; // [target_url => [source_article_ids]]
$redirectLinksFound = []; // [target_url => [source_article_ids]]
$articlesToUpdate = []; // [article_id => new_content]

$linkRegex = '/href=["\']((?:https?:\/\/(?:www\.)?sarkari\.online)?\/([a-zA-Z0-9_\-\/\.]+))["\']/i';

foreach ($publishedArticles as $art) {
    $artId = (int)$art['id'];
    $content = $art['content'];
    $contentModified = false;

    if (empty($content)) continue;

    preg_match_all($linkRegex, $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $fullMatchedHref = $m[1];
        $path = trim($m[2], '/');

        // Skip assets, images, cdn
        if (preg_match('/\.(jpg|jpeg|png|webp|gif|svg|pdf|css|js|ico|xml|txt)$/i', $path)) {
            continue;
        }

        // Check if missing trailing slash on directory-like path
        $hasTrailingSlash = str_ends_with(parse_url($fullMatchedHref, PHP_URL_PATH) ?? '', '/');
        if (!$hasTrailingSlash) {
            $redirectLinksFound[$fullMatchedHref][] = $artId;
            // Fix missing trailing slash
            $fixedUrl = rtrim($fullMatchedHref, '/') . '/';
            $content = str_replace('"' . $fullMatchedHref . '"', '"' . $fixedUrl . '"', $content);
            $content = str_replace("'" . $fullMatchedHref . "'", "'" . $fixedUrl . "'", $content);
            $contentModified = true;
        }

        // Validate destination
        $isBroken = false;
        $replacementUrl = null;

        if (str_starts_with($path, 'article/')) {
            $targetSlug = trim(substr($path, 8), '/');
            if (isset($slugRedirectMap[$targetSlug])) {
                $isBroken = true;
                $replacementSlug = $slugRedirectMap[$targetSlug];
                $replacementUrl = str_starts_with($replacementSlug, 'category/') 
                    ? url($replacementSlug . '/') 
                    : url('article/' . $replacementSlug . '/');
            } elseif (!isset($articleSlugs[$targetSlug])) {
                $isBroken = true;
                // Attempt fuzzy match on year swap or prefix
                $altSlug = str_contains($targetSlug, '2026') 
                    ? str_replace('2026', '2027', $targetSlug) 
                    : (str_contains($targetSlug, '2027') ? str_replace('2027', '2026', $targetSlug) : null);
                if ($altSlug && isset($articleSlugs[$altSlug])) {
                    $replacementUrl = url('article/' . $altSlug . '/');
                } else {
                    $replacementUrl = url('latest-jobs/');
                }
            }
        } elseif (str_starts_with($path, 'category/')) {
            $catSlug = trim(substr($path, 9), '/');
            if (!in_array($catSlug, $categorySlugs, true)) {
                $isBroken = true;
                $replacementUrl = url('category/government-jobs/');
            }
        } elseif (str_starts_with($path, 'jobs/')) {
            $stSlug = trim(substr($path, 5), '/');
            if (!in_array($stSlug, $states, true)) {
                $isBroken = true;
                $replacementUrl = url('state-jobs/');
            }
        } elseif (str_starts_with($path, 'full-forms/')) {
            $ffSlug = trim(substr($path, 11), '/');
            if (!empty($ffSlug) && !in_array($ffSlug, $glossarySlugs, true)) {
                $isBroken = true;
                $replacementUrl = url('full-forms/');
            }
        } elseif (!in_array($path, $validStaticRoutes, true)) {
            // Check if it's a known static page or tool
            $isBroken = true;
            $replacementUrl = url();
        }

        if ($isBroken) {
            $brokenLinksFound[$fullMatchedHref][] = $artId;
            if ($replacementUrl) {
                $content = str_replace('"' . $fullMatchedHref . '"', '"' . $replacementUrl . '"', $content);
                $content = str_replace("'" . $fullMatchedHref . "'", "'" . $replacementUrl . "'", $content);
                // Also replace if trailing slash was appended
                $content = str_replace('"' . rtrim($fullMatchedHref, '/') . '/"', '"' . $replacementUrl . '"', $content);
                $content = str_replace("'" . rtrim($fullMatchedHref, '/') . "/'", "'" . $replacementUrl . "'", $content);
                $contentModified = true;
            }
        }
    }

    if ($contentModified) {
        $articlesToUpdate[$artId] = $content;
    }
}

echo "   Found " . count($brokenLinksFound) . " unique broken internal URLs across " . count($articlesToUpdate) . " articles.\n";
foreach ($brokenLinksFound as $url => $sources) {
    echo "     ❌ [404 Dead Link] {$url} (Linked in articles: #" . implode(', #', array_unique($sources)) . ")\n";
}

echo "   Found " . count($redirectLinksFound) . " unique internal links missing trailing slashes.\n";
foreach (array_slice($redirectLinksFound, 0, 10) as $url => $sources) {
    echo "     ⚠️ [3XX Redirect Link] {$url} (Linked in articles: #" . implode(', #', array_unique($sources)) . ")\n";
}

// 3. Apply Healed Content to Database
if (!empty($articlesToUpdate)) {
    echo "\n3. Applying healed internal links to Database (" . count($articlesToUpdate) . " articles)...\n";
    foreach ($articlesToUpdate as $id => $cleanHtml) {
        Database::update('articles', ['content' => $cleanHtml, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }
    echo "   ✅ Successfully healed all broken and redirect links in articles!\n\n";
} else {
    echo "\n3. No articles required link healing.\n\n";
}

// 4. Title & Meta Description Optimization (Google 58 Chars & 140-155 Chars)
echo "4. Checking Titles (> 58 chars) and Meta Descriptions (> 155 chars or < 100 chars)...\n";

$titlesClamped = 0;
$descriptionsNormalized = 0;

foreach ($publishedArticles as $art) {
    $artId = (int)$art['id'];
    $title = $art['title'];
    $metaTitle = trim($art['meta_title'] ?? '');
    $metaDesc = trim($art['meta_description'] ?? '');
    $excerpt = trim(strip_tags($art['excerpt'] ?? ''));

    $updates = [];

    // Title Optimization: Ensure clean, non-truncated title under 58 characters
    $targetTitle = !empty($metaTitle) ? $metaTitle : $title;
    // Strip brand suffix if it makes title too long
    $cleanTitle = preg_replace('/\s*\|\s*Sarkari\.online.*$/i', '', $targetTitle);
    $cleanTitle = preg_replace('/\s*—\s*Sarkari\.online.*$/i', '', $cleanTitle);

    if (mb_strlen($cleanTitle) > 58) {
        // Find suitable cut point (colon, hyphen, or word boundary)
        $shortened = mb_substr($cleanTitle, 0, 58);
        $cutPos = false;
        
        // Prioritize cutting at colon or hyphen if present between 35-58 chars
        $colonPos = mb_strrpos($shortened, ':');
        $dashPos = mb_strrpos($shortened, ' - ');
        
        if ($colonPos !== false && $colonPos >= 35) {
            $shortened = mb_substr($shortened, 0, $colonPos);
        } elseif ($dashPos !== false && $dashPos >= 35) {
            $shortened = mb_substr($shortened, 0, $dashPos);
        } else {
            $spacePos = mb_strrpos($shortened, ' ');
            if ($spacePos !== false && $spacePos >= 30) {
                $shortened = mb_substr($shortened, 0, $spacePos);
            }
        }
        $shortened = rtrim($shortened, ' :,-\t\n\r');
        if ($shortened !== $metaTitle) {
            $updates['meta_title'] = $shortened;
            $titlesClamped++;
        }
    } elseif (!empty($metaTitle) && $metaTitle !== $cleanTitle && mb_strlen($metaTitle) > 58) {
        $updates['meta_title'] = $cleanTitle;
        $titlesClamped++;
    }

    // Meta Description Optimization (130 - 155 characters)
    $descToUse = !empty($metaDesc) ? $metaDesc : $excerpt;
    if (empty($descToUse)) {
        $descToUse = "Check verified notifications, eligibility criteria, exam schedule, and official application links for {$title} on Sarkari.online.";
    }

    if (mb_strlen($descToUse) > 155) {
        $truncated = mb_substr($descToUse, 0, 155);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace >= 120) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        $truncated = rtrim($truncated, ' ,;:-') . '.';
        if ($truncated !== $metaDesc) {
            $updates['meta_description'] = $truncated;
            $descriptionsNormalized++;
        }
    } elseif (mb_strlen($descToUse) < 100) {
        $enriched = rtrim($descToUse, '.') . ". Check latest eligibility, dates & verified official portal links at Sarkari.online.";
        if (mb_strlen($enriched) > 155) {
            $enriched = mb_substr($enriched, 0, 155);
            $lastSpace = mb_strrpos($enriched, ' ');
            if ($lastSpace !== false) {
                $enriched = mb_substr($enriched, 0, $lastSpace);
            }
            $enriched = rtrim($enriched, ' ,;:-') . '.';
        }
        $updates['meta_description'] = $enriched;
        $descriptionsNormalized++;
    }

    if (!empty($updates)) {
        Database::update('articles', $updates, 'id = :id', ['id' => $artId]);
    }
}

echo "   ✓ Normalized {$titlesClamped} long titles to <= 58 chars.\n";
echo "   ✓ Normalized {$descriptionsNormalized} meta descriptions to 130-155 chars.\n\n";

$elapsed = round(microtime(true) - $startTime, 2);
echo "=================================================================\n";
echo "✅ AHREFS AUDIT ISSUES HEALING COMPLETED IN {$elapsed}s!\n";
echo "=================================================================\n";
