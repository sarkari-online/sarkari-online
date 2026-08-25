<?php
/**
 * Sarkari.online - Pinterest Visual Media RSS Syndication Engine
 * High-resolution visual feed specifically formatted for Pinterest Rich Pins & Auto-Pin syndication.
 * Adheres strictly to Media RSS (MRSS) 2.0 specifications with high-res 1200x675 WebP thumbnails.
 */
require_once __DIR__ . '/config.php';

use App\Services\ArticleService;
use App\Database\Database;

if (!headers_sent()) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    header('Cache-Control: public, max-age=1800'); // 30-minute caching
}

$sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.username 
        FROM articles a 
        JOIN categories c ON a.category_id = c.id 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE a.status = 'published' 
        ORDER BY a.published_at DESC 
        LIMIT 30";

$articles = Database::fetchAll($sql);

$lastBuildDate = !empty($articles) ? date('r', strtotime($articles[0]['published_at'])) : date('r');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" 
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:wfw="http://wellformedweb.org/CommentAPI/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title>Sarkari.online — Exam Blueprints, Syllabus &amp; Career Infographics</title>
    <atom:link href="<?= url('pinterest-feed.xml') ?>" rel="self" type="application/rss+xml" />
    <link><?= SITE_URL ?>/</link>
    <description>Authentic educational infographics, exam roadmaps, syllabus breakdowns, and government job updates for Indian students on Pinterest.</description>
    <lastBuildDate><?= $lastBuildDate ?></lastBuildDate>
    <language>en-IN</language>
    <image>
        <url><?= asset('sarkari-logo-transparent.png') ?></url>
        <title>Sarkari.online</title>
        <link><?= SITE_URL ?>/</link>
    </image>

    <?php foreach ($articles as $art): 
        $articleUrl = url('article/' . $art['slug'] . '/');
        $pubDate = date('r', strtotime($art['published_at']));
        $catName = $art['category_name'] ?? 'Education';
        $catSlug = $art['category_slug'] ?? 'exam-results';
        
        // High-res visual image URL (Fallback to dynamic branded thumbnail)
        $imageUrl = !empty($art['featured_image']) ? url($art['featured_image']) : url('assets/images/default-share.jpg');
        $imageAlt = !empty($art['featured_image_alt']) ? $art['featured_image_alt'] : $art['title'];

        // Targeted Pinterest Visual Hashtags
        $hashtags = '#SarkariOnline #ExamAlerts #IndianEducation';
        if (str_contains($catSlug, 'job') || str_contains($catSlug, 'recruitment')) {
            $hashtags .= ' #GovtJobs #SarkariResult #JobSearchIndia #UPSC #SSC';
        } elseif (str_contains($catSlug, 'entrance') || str_contains($catSlug, 'neet') || str_contains($catSlug, 'jee') || str_contains($catSlug, 'gate')) {
            $hashtags .= ' #NEETUG #JEEMain #EntranceExam #StudyInspo #ExamPreparation';
        } elseif (str_contains($catSlug, 'scholarship')) {
            $hashtags .= ' #Scholarships #NSP #StudentFunding #HigherEducation';
        } elseif (str_contains($catSlug, 'career')) {
            $hashtags .= ' #CareerGuide #StudyTips #SyllabusBlueprint #CareerGoals';
        } elseif (str_contains($catSlug, 'tech')) {
            $hashtags .= ' #StudentTech #DigiLocker #EdTech #StudentLife';
        } else {
            $hashtags .= ' #SarkariResult #AdmitCard #ExamDates';
        }

        $pinDescription = strip_tags($art['excerpt'] ?? $art['title']) . ' ' . $hashtags;
    ?>
    <item>
        <title><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></title>
        <link><?= $articleUrl ?></link>
        <guid isPermaLink="true"><?= $articleUrl ?></guid>
        <dc:creator><![CDATA[<?= $art['username'] ?? 'Sarkari.online Editorial' ?>]]></dc:creator>
        <pubDate><?= $pubDate ?></pubDate>
        <category><![CDATA[<?= $catName ?>]]></category>
        <description><![CDATA[<?= htmlspecialchars($pinDescription, ENT_QUOTES, 'UTF-8') ?>]]></description>
        <enclosure url="<?= $imageUrl ?>" type="image/webp" length="45000" />
        <media:content url="<?= $imageUrl ?>" medium="image" type="image/webp" width="1200" height="675">
            <media:title><?= htmlspecialchars($art['title'], ENT_XML1, 'UTF-8') ?></media:title>
            <media:description><?= htmlspecialchars($imageAlt, ENT_XML1, 'UTF-8') ?></media:description>
        </media:content>
    </item>
    <?php endforeach; ?>

</channel>
</rss>
