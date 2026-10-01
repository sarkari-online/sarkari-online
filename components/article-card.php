<?php
/**
 * Standard Article Card Component
 * Parameters passed via $article array:
 * - title, slug, excerpt, category_slug, category_name, category_color, published_at, read_time
 */
if (!isset($article)) return;

$cardCatSlug = $article['category_slug'] ?? $article['category'] ?? 'exam-results';
$cardCatName = $article['category_name'] ?? 'Education';
$cardCatColor = $article['category_color'] ?? '#1a237e';

$imgSrc = !empty($article['featured_image']) ? url($article['featured_image']) : null;
if ($imgSrc) {
    $localPath = dirname(__DIR__) . '/' . ltrim($article['featured_image'], '/');
    $imgVersion = file_exists($localPath) ? filemtime($localPath) : time();
    $imgSrc .= '?v=' . $imgVersion;
}
?>
<article class="article-card" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.03); display: flex; flex-direction: column; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(26,35,126,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 1px 4px rgba(0,0,0,0.03)';">
    <div class="article-card-thumb" style="aspect-ratio: 16 / 9; overflow: hidden; background: #0f172a; position: relative;">
        <a href="<?= url('article/' . $article['slug'] . '/') ?>" aria-label="<?= e($article['title']) ?>" title="<?= e($article['title']) ?>">
            <?php if (!empty($imgSrc)): 
                $cardImgAlt = !empty($article['featured_image_alt']) ? $article['featured_image_alt'] : ($article['title'] ?? 'Sarkari Job Notification');
                $cardImgTitle = $article['title'] ?? $cardImgAlt;
            ?>
                <img src="<?= e($imgSrc) ?>" alt="<?= e($cardImgAlt) ?>" title="<?= e($cardImgTitle) ?>" class="card-thumb-img" loading="lazy" decoding="async" width="640" height="360" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            <?php else: ?>
                <?= render_thumbnail_svg($cardCatSlug, $article['title'], 640, 360) ?>
            <?php endif; ?>
        </a>
    </div>
    <div class="article-card-body" style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
        <div class="card-meta" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.65rem;">
            <a href="<?= url('category/' . $cardCatSlug . '/') ?>" style="font-size: 0.72rem; font-weight: 700; color: #1a237e; background: #e8eaf6; padding: 3px 9px; border-radius: 4px; text-transform: uppercase; text-decoration: none;" title="<?= e($cardCatName) ?> Category Archives">
                <?= e($cardCatName) ?>
            </a>
            <time datetime="<?= e($article['published_at'] ?? '') ?>" style="font-size: 0.78rem; color: #78909c; font-weight: 500;"><?= format_date($article['published_at'] ?? 'now') ?></time>
        </div>

        <h3 class="article-card-title" style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0 0 0.65rem 0; line-height: 1.35; flex: 1;">
            <a href="<?= url('article/' . $article['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';" title="<?= e($article['title']) ?>">
                <?= e($article['title']) ?>
            </a>
        </h3>

        <?php if (!empty($article['excerpt'])): ?>
            <p class="article-card-excerpt" style="font-size: 0.85rem; color: #546e7a; line-height: 1.5; margin: 0 0 1rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= e(truncate_text($article['excerpt'], 110)) ?>
            </p>
        <?php endif; ?>

        <div class="card-footer-meta" style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f0f0f0; padding-top: 0.75rem; margin-top: auto;">
            <span style="font-size: 0.78rem; color: #78909c;"><?= icon('clock', 'icon-xs') ?> <?= e($article['read_time'] ?? '3 min read') ?></span>
            <a href="<?= url('article/' . $article['slug'] . '/') ?>" style="font-size: 0.8rem; font-weight: 700; color: #1a237e; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="<?= e($article['title']) ?> — Read Full Article">
                <span>Read Notice</span> &rarr;
            </a>
        </div>
    </div>
</article>
