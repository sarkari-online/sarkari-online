<?php
/**
 * EduPulse - Dynamic RSS 2.0 / Atom Syndication Feed Engine
 * Generates standards-compliant XML feed for Dlvr.it, IFTTT, Make.com, Buffer & News Readers.
 */
require_once __DIR__ . '/config.php';

use App\Database\Database;

// Set correct RSS XML content type & caching headers
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=1800'); // 30 mins cache

$articles = Database::fetchAll(
    "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.username AS author_name 
     FROM articles a
     JOIN categories c ON a.category_id = c.id
     LEFT JOIN users u ON a.author_id = u.id
     WHERE a.status = 'published'
     ORDER BY a.published_at DESC
     LIMIT 30"
);

$lastBuildDate = !empty($articles) ? date(DATE_RSS, strtotime($articles[0]['published_at'])) : date(DATE_RSS);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" 
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:wfw="http://wellformedweb.org/CommentAPI/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
     xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title><?= htmlspecialchars(SITE_NAME . ' — Latest Exam Results, Admit Cards & Jobs Alerts', ENT_XML1, 'UTF-8') ?></title>
    <atom:link href="<?= htmlspecialchars(url('feed/'), ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml" />
    <link><?= htmlspecialchars(SITE_URL . '/', ENT_XML1, 'UTF-8') ?></link>
    <description><?= htmlspecialchars(SITE_DESCRIPTION, ENT_XML1, 'UTF-8') ?></description>
    <lastBuildDate><?= $lastBuildDate ?></lastBuildDate>
    <language>en-IN</language>
    <sy:updatePeriod>hourly</sy:updatePeriod>
    <sy:updateFrequency>1</sy:updateFrequency>
    <generator>Sarkari.online RSS Syndication Engine</generator>
    <image>
        <url><?= htmlspecialchars(asset('sarkari-logo-transparent.png'), ENT_XML1, 'UTF-8') ?></url>
        <title><?= htmlspecialchars(SITE_NAME, ENT_XML1, 'UTF-8') ?></title>
        <link><?= htmlspecialchars(SITE_URL . '/', ENT_XML1, 'UTF-8') ?></link>
        <width>185</width>
        <height>48</height>
    </image>

    <?php foreach ($articles as $art): 
        $articleUrl = url('article/' . $art['slug'] . '/');
        $pubDate = date(DATE_RSS, strtotime($art['published_at'] ?? $art['created_at']));
        $author = !empty($art['author_name']) ? ucfirst($art['author_name']) : 'Sarkari.online Editorial Desk';
        $category = $art['category_name'] ?? 'Education';
        $excerpt = $art['excerpt'] ?? '';
        $thumbUrl = !empty($art['featured_image']) ? url($art['featured_image']) : '';
        
        // Content body formatted for syndication with canonical attribution
        $cleanContent = $art['content_html'] ?? $art['content'] ?? $excerpt;
        $cleanContent = strip_tags($cleanContent, '<p><br><h2><h3><ul><ol><li><strong><em><table><thead><tbody><tr><th><td><blockquote><a>');
        
        // Append official source backlink
        $syndicationFooter = "<hr><p><em>Originally published and verified at <a href=\"{$articleUrl}\">Sarkari.online — " . htmlspecialchars($art['title'], ENT_QUOTES, 'UTF-8') . "</a>. For live updates, dates, and official notification PDFs, visit the official coverage on <a href=\"https://sarkari.online/\">Sarkari.online</a>.</em></p>";
        $fullBody = $cleanContent . $syndicationFooter;
    ?>
    <item>
        <title><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></title>
        <link><?= htmlspecialchars($articleUrl, ENT_XML1, 'UTF-8') ?></link>
        <guid isPermaLink="true"><?= htmlspecialchars($articleUrl, ENT_XML1, 'UTF-8') ?></guid>
        <comments><?= htmlspecialchars($articleUrl . '#comments', ENT_XML1, 'UTF-8') ?></comments>
        <dc:creator><![CDATA[<?= $author ?>]]></dc:creator>
        <pubDate><?= $pubDate ?></pubDate>
        <category><![CDATA[<?= $category ?>]]></category>
        <description><![CDATA[<?= $excerpt ?>]]></description>
        <content:encoded><![CDATA[<?= $fullBody ?>]]></content:encoded>
        <?php if (!empty($thumbUrl)): ?>
            <media:content url="<?= htmlspecialchars($thumbUrl, ENT_XML1, 'UTF-8') ?>" medium="image">
                <media:title type="html"><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></media:title>
            </media:content>
            <enclosure url="<?= htmlspecialchars($thumbUrl, ENT_XML1, 'UTF-8') ?>" length="25000" type="image/webp" />
        <?php endif; ?>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
