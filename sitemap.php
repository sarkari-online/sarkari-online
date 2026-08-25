<?php
/**
 * Sarkari.online - Dynamic XML Sitemap Engine
 * Standards-compliant XML sitemap adhering strictly to Google Search Central & W3C Sitemaps Protocol.
 * Clean, fast, accurate <lastmod>, zero changefreq/priority bloat, canonical-only, automatic purge of non-published items.
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

// Determine the most recent content update for the homepage <lastmod>
$latestArticleTime = !empty($articles) ? ($articles[0]['updated_at'] ?? $articles[0]['published_at']) : null;
$homeLastMod = $latestArticleTime ? date('Y-m-d', strtotime($latestArticleTime)) : date('Y-m-d');

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
    ['url' => 'disclaimer/', 'file' => __DIR__ . '/disclaimer.php']
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

        $lastmod = $page['lastmod'] ?? (isset($page['file']) && file_exists($page['file']) ? date('Y-m-d', filemtime($page['file'])) : date('Y-m-d'));
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
        $catLastMod = !empty($cat['updated_at']) ? date('Y-m-d', strtotime($cat['updated_at'])) : null;
        foreach ($articles as $a) {
            if (($a['category_slug'] ?? '') === $cat['slug']) {
                $catLastMod = date('Y-m-d', strtotime($a['updated_at'] ?? $a['published_at']));
                break;
            }
        }
        $catLastMod = $catLastMod ?: date('Y-m-d');
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

        // Accurate lastmod: actual updated_at or published_at
        $rawTimestamp = !empty($art['updated_at']) ? $art['updated_at'] : $art['published_at'];
        $lastmod = !empty($rawTimestamp) ? date('Y-m-d', strtotime($rawTimestamp)) : date('Y-m-d');

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
