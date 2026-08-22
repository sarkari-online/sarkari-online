<?php
/**
 * EduPulse - Dynamic XML Sitemap Generator
 * Dynamically outputs valid XML sitemap based on live published articles and category archives.
 */
require_once __DIR__ . '/config.php';

use App\Services\ArticleService;
use App\Services\CategoryService;

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
}

$categories = CategoryService::getAll();
$articles = ArticleService::getAllForSitemap();

$staticPages = [
    ['url' => '', 'changefreq' => 'hourly', 'priority' => '1.0'],
    ['url' => 'about/', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['url' => 'why-choose-us/', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['url' => 'editorial-policy/', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['url' => 'ai-policy/', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['url' => 'contact/', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['url' => 'privacy-policy/', 'changefreq' => 'monthly', 'priority' => '0.3'],
    ['url' => 'terms/', 'changefreq' => 'monthly', 'priority' => '0.3'],
    ['url' => 'disclaimer/', 'changefreq' => 'monthly', 'priority' => '0.3']
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- 1. Homepage & Static Pages -->
    <?php foreach ($staticPages as $page): ?>
        <url>
            <loc><?= e(url($page['url'])) ?></loc>
            <changefreq><?= e($page['changefreq']) ?></changefreq>
            <priority><?= e($page['priority']) ?></priority>
        </url>
    <?php endforeach; ?>

    <!-- 2. Category Archives -->
    <?php foreach ($categories as $cat): ?>
        <url>
            <loc><?= e(url('category/' . $cat['slug'] . '/')) ?></loc>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>

    <!-- 3. Published Articles -->
    <?php foreach ($articles as $art): 
        $lastmod = !empty($art['updated_at']) ? date('Y-m-d', strtotime($art['updated_at'])) : date('Y-m-d', strtotime($art['published_at'] ?? 'now'));
    ?>
        <url>
            <loc><?= e(url('article/' . $art['slug'] . '/')) ?></loc>
            <lastmod><?= e($lastmod) ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    <?php endforeach; ?>

</urlset>
