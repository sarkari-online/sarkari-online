<?php
/**
 * Sarkari.online - Major Announcement Hero Card
 * Matches exact executive redesign mockup (homepage_redesign_1790864147830.jpg)
 * Prominent white card with thick 4px Vibrant Orange border, orange pill badge,
 * large bold typography, and direct article link.
 */

if (!isset($featuredArticle)) {
    $featuredArticle = $featured ?? ($hotArticles[0] ?? null);
}

if (!$featuredArticle) {
    return;
}

$title = $featuredArticle['title'] ?? 'Official Government Examination & Recruitment Notification';
$slug = $featuredArticle['slug'] ?? '';
$publishedAt = $featuredArticle['published_at'] ?? 'now';
$timeAgo = function_exists('human_time_ago') ? human_time_ago($publishedAt) : date('d M Y', strtotime($publishedAt));
$sourceName = $featuredArticle['source_name'] ?? 'Official Authority';
?>

<section class="hero-announcement-section" style="margin-bottom: 2rem;">
    <div class="hero-announcement-card" style="background: #ffffff; border: 4px solid #f57c00; border-radius: 16px; padding: 2rem 2.25rem; box-shadow: 0 4px 20px rgba(245, 124, 0, 0.08); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        
        <!-- Top Badge -->
        <div style="margin-bottom: 1.15rem;">
            <span style="background: #f57c00; color: #ffffff; padding: 6px 18px; border-radius: 9999px; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.3px; display: inline-block;">
                Major announcement
            </span>
        </div>

        <!-- Headline -->
        <h1 style="font-size: 2.15rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0; line-height: 1.28; letter-spacing: -0.02em;">
            <a href="<?= url('article/' . $slug . '/') ?>" style="color: #0f172a; text-decoration: none; transition: color 0.15s ease;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';">
                <?= e($title) ?>
            </a>
        </h1>

        <!-- Meta Line -->
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.875rem; color: #78909c; font-weight: 500;">
            <span>by sarkari.online</span>
            <span>&bull;</span>
            <span><?= e($timeAgo) ?></span>
            <?php if (!empty($sourceName) && $sourceName !== 'Sarkari.online Editorial Desk'): ?>
                <span>&bull;</span>
                <span style="color: #546e7a; font-weight: 600;">Authority: <?= e($sourceName) ?></span>
            <?php endif; ?>
        </div>

    </div>
</section>
