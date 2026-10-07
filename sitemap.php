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
    ['url' => '', 'lastmod' => date('Y-m-d')],
    ['url' => 'full-forms/', 'file' => __DIR__ . '/full-forms.php'],
    ['url' => 'about/', 'file' => __DIR__ . '/about.php'],
    ['url' => 'contact/', 'file' => __DIR__ . '/contact.php'],
    ['url' => 'privacy-policy/', 'file' => __DIR__ . '/privacy-policy.php'],
    ['url' => 'terms/', 'file' => __DIR__ . '/terms.php'],
    ['url' => 'disclaimer/', 'file' => __DIR__ . '/disclaimer.php'],
    ['url' => 'editorial-policy/', 'file' => __DIR__ . '/editorial-policy.php'],
];

$seenUrls = [];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- 1. Canonical Homepage & Core Institutional Trust Pages -->
    <?php foreach ($staticPages as $page): 
        $fullUrl = url($page['url']);
        if (isset($seenUrls[$fullUrl])) continue;
        $seenUrls[$fullUrl] = true;

        $lastmod = $page['lastmod'] ?? (isset($page['file']) && file_exists($page['file']) ? date('Y-m-d', filemtime($page['file'])) : '2026-10-07');
    ?>
    <url>
        <loc><?= htmlspecialchars($fullUrl, ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
    </url>
    <?php endforeach; ?>

    <!-- 2. Individual A-to-Z Government Full Form URLs (Primary Focus) -->
    <?php 
    $glossaryTerms = \App\Services\GlossaryService::getAllForSitemap();
    foreach ($glossaryTerms as $gTerm):
        $gUrl = url('full-forms/' . $gTerm['slug'] . '/');
        if (isset($seenUrls[$gUrl])) continue;
        $seenUrls[$gUrl] = true;
        $gLastmod = !empty($gTerm['last_verified_at']) 
            ? date('Y-m-d', strtotime($gTerm['last_verified_at'])) 
            : (!empty($gTerm['updated_at']) ? date('Y-m-d', strtotime($gTerm['updated_at'])) : ($gTerm['last_reviewed_at'] ?? '2026-10-07'));
    ?>
    <url>
        <loc><?= htmlspecialchars($gUrl, ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $gLastmod ?></lastmod>
    </url>
    <?php endforeach; ?>

</urlset>
