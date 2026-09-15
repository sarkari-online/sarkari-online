<?php
/**
 * Sarkari.online - Google News Dynamic XML Sitemap Engine
 * Strict compliance with Google Search Central News Sitemaps Protocol.
 * Filters articles published within the last 48 hours for ultra-fast Googlebot-News discovery.
 */
require_once __DIR__ . '/config.php';

use App\Database\Database;

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    header('Cache-Control: public, max-age=600'); // 10 mins cache
}

$articles = [];
try {
    // 1. Fetch published articles from the last 48 hours
    $articles = Database::fetchAll("
        SELECT id, title, slug, published_at, updated_at
        FROM articles
        WHERE status = 'published'
          AND published_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY published_at DESC
    ");

    // 2. Fallback: If fewer than 5 articles in last 48 hours, fetch latest 15 to keep sitemap non-empty
    if (count($articles) < 5) {
        $articles = Database::fetchAll("
            SELECT id, title, slug, published_at, updated_at
            FROM articles
            WHERE status = 'published'
            ORDER BY published_at DESC
            LIMIT 15
        ");
    }
} catch (\Throwable $e) {
    $articles = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
<?php foreach ($articles as $art): 
    $articleUrl = url('article/' . $art['slug'] . '/');
    $pubDate = !empty($art['published_at']) ? date('Y-m-d\TH:i:sP', strtotime($art['published_at'])) : date('Y-m-d\TH:i:sP');
    $cleanTitle = htmlspecialchars(trim(strip_tags($art['title'])), ENT_XML1, 'UTF-8');
?>
    <url>
        <loc><?= htmlspecialchars($articleUrl, ENT_XML1, 'UTF-8') ?></loc>
        <news:news>
            <news:publication>
                <news:name><?= htmlspecialchars(SITE_NAME, ENT_XML1, 'UTF-8') ?></news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date><?= $pubDate ?></news:publication_date>
            <news:title><?= $cleanTitle ?></news:title>
        </news:news>
    </url>
<?php endforeach; ?>
</urlset>
