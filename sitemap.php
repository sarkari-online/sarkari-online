<?php
/**
 * Sarkari.online - Dynamic XML Sitemap Engine
 * Standards-compliant XML sitemap adhering strictly to Google Search Central & W3C Sitemaps Protocol.
 * Clean, fast, accurate <lastmod> derived strictly from real database content modification timestamps.
 * Zero changefreq/priority bloat, canonical-only, automatic purge of non-published items.
 */
require_once __DIR__ . '/config.php';

use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Database\Database;

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    header('Cache-Control: public, max-age=3600'); // 1-hour cache
}

$categories = CategoryService::getAll();
$articles = ArticleService::getAllForSitemap();

// Determine the most recent meaningful content update for the homepage <lastmod>
$latestArticleTime = null;
if (!empty($articles)) {
    $firstArt = $articles[0];
    $latestArticleTime = (!empty($firstArt['updated_at']) && $firstArt['updated_at'] > ($firstArt['published_at'] ?? ''))
        ? $firstArt['updated_at']
        : ($firstArt['published_at'] ?? $firstArt['created_at'] ?? null);
}
$homeLastMod = $latestArticleTime ? date('Y-m-d', strtotime($latestArticleTime)) : '2026-08-20';

// Canonical static indexable pages with file modification timestamps
$staticPages = [
    ['url' => '', 'lastmod' => $homeLastMod],
    ['url' => 'about/', 'file' => __DIR__ . '/about.php'],
    ['url' => 'why-choose-us/', 'file' => __DIR__ . '/why-choose-us.php'],
    ['url' => 'editorial-policy/', 'file' => __DIR__ . '/editorial-policy.php'],
    ['url' => 'ai-policy/', 'file' => __DIR__ . '/ai-policy.php'],
    ['url' => 'contact/', 'file' => __DIR__ . '/contact.php'],
    ['url' => 'privacy-policy/', 'file' => __DIR__ . '/privacy-policy.php'],
    ['url' => 'terms/', 'file' => __DIR__ . '/terms.php'],
    ['url' => 'disclaimer/', 'file' => __DIR__ . '/disclaimer.php'],
    ['url' => 'tools/', 'file' => __DIR__ . '/tools/index.php'],
    ['url' => 'tools/7th-pay-commission-salary-calculator/', 'file' => __DIR__ . '/tools/7th-pay-commission-salary-calculator.php'],
    ['url' => 'tools/cgpa-to-percentage-calculator/', 'file' => __DIR__ . '/tools/cgpa-to-percentage-calculator.php']
];

$seenUrls = [];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- 1. Canonical Homepage & Static Pages -->
    <?php foreach ($staticPages as $page): 
        $fullUrl = url($page['url']);
        if (isset($seenUrls[$fullUrl])) continue;
        $seenUrls[$fullUrl] = true;

        $lastmod = $page['lastmod'] ?? (isset($page['file']) && file_exists($page['file']) ? date('Y-m-d', filemtime($page['file'])) : '2026-08-20');
    ?>
    <url>
        <loc><?= htmlspecialchars($fullUrl, ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
    </url>
    <?php endforeach; ?>

    <!-- 2. Canonical Category Archives -->
    <?php foreach ($categories as $cat): 
        $catUrl = url('category/' . $cat['slug'] . '/');
        if (isset($seenUrls[$catUrl])) continue;
        $seenUrls[$catUrl] = true;

        // Category lastmod from most recent published article in that category
        $catLastMod = null;
        foreach ($articles as $a) {
            if (($a['category_slug'] ?? '') === $cat['slug']) {
                $aTime = (!empty($a['updated_at']) && $a['updated_at'] > ($a['published_at'] ?? ''))
                    ? $a['updated_at']
                    : ($a['published_at'] ?? $a['created_at'] ?? null);
                if ($aTime) {
                    $catLastMod = date('Y-m-d', strtotime($aTime));
                    break;
                }
            }
        }
        $catLastMod = $catLastMod ?: (!empty($cat['created_at']) ? date('Y-m-d', strtotime($cat['created_at'])) : '2026-08-20');
    ?>
    <url>
        <loc><?= htmlspecialchars($catUrl, ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $catLastMod ?></lastmod>
    </url>
    <?php endforeach; ?>

    <!-- 3. Canonical Published Articles -->
    <?php foreach ($articles as $art): 
        $articleUrl = url('article/' . $art['slug'] . '/');
        if (isset($seenUrls[$articleUrl])) continue;
        $seenUrls[$articleUrl] = true;

        // Accurate lastmod: strictly actual content modification or original publication timestamp
        $rawTimestamp = (!empty($art['updated_at']) && $art['updated_at'] > ($art['published_at'] ?? ''))
            ? $art['updated_at']
            : ($art['published_at'] ?? $art['created_at'] ?? null);
        
        $lastmod = !empty($rawTimestamp) ? date('Y-m-d', strtotime($rawTimestamp)) : '2026-08-20';

        // News Sitemap eligibility: Published within last 48 hours and in news categories
        $pubTimestamp = !empty($art['published_at']) ? strtotime($art['published_at']) : 0;
        $isRecent = (time() - $pubTimestamp) <= (48 * 3600);
        $isNewsCategory = in_array($art['category_slug'] ?? '', ['exam-results', 'admit-cards', 'exam-dates', 'answer-keys', 'government-jobs', 'entrance-exams'], true);
        $isNewsEligible = $isRecent && $isNewsCategory;

        // Image markup: ONLY if featured_image exists
        $hasImage = !empty($art['featured_image']);
    ?>
    <url>
        <loc><?= htmlspecialchars($articleUrl, ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <?php if ($isNewsEligible): ?>
        <news:news>
            <news:publication>
                <news:name><?= htmlspecialchars(SITE_NAME, ENT_XML1, 'UTF-8') ?></news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date><?= date('c', $pubTimestamp) ?></news:publication_date>
            <news:title><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></news:title>
        </news:news>
        <?php endif; ?>
        <?php if ($hasImage): ?>
        <image:image>
            <image:loc><?= htmlspecialchars(url($art['featured_image']), ENT_XML1, 'UTF-8') ?></image:loc>
            <image:title><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>

</urlset>
