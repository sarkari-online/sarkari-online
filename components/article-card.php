<?php
/**
 * Standard Article Card Component
 * Parameters passed via $article array:
 * - title, slug, excerpt, category_slug, category_name, category_color, published_at, read_time
 */
if (!isset($article)) return;

$cardCatSlug = $article['category_slug'] ?? $article['category'] ?? 'exam-results';
$cardCatName = $article['category_name'] ?? 'Education';
$cardCatColor = $article['category_color'] ?? '#1e3a8a';
?>
<article class="article-card">
    <div class="article-card-thumb">
        <a href="<?= url('article/' . $article['slug'] . '/') ?>" aria-label="<?= e($article['title']) ?>">
            <?php if (!empty($article['featured_image'])): 
                $cardImgAlt = !empty($article['featured_image_alt']) ? $article['featured_image_alt'] : ($article['title'] ?? 'Sarkari Job Notification');
                $cardImgTitle = $article['title'] ?? $cardImgAlt;
            ?>
                <img src="<?= url($article['featured_image']) ?>" alt="<?= e($cardImgAlt) ?>" title="<?= e($cardImgTitle) ?>" class="card-thumb-img" loading="lazy" decoding="async" width="640" height="360" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            <?php else: ?>
                <?= render_thumbnail_svg($cardCatSlug, $article['title'], 640, 360) ?>
            <?php endif; ?>
        </a>
    </div>
    <div class="article-card-body">
        <div class="card-meta">
            <a href="<?= url('category/' . $cardCatSlug . '/') ?>" class="badge" style="background-color: <?= e($cardCatColor) ?>15; color: <?= e($cardCatColor) ?>;">
                <?= e($cardCatName) ?>
            </a>
            <span class="meta-dot"></span>
            <time datetime="<?= e($article['published_at'] ?? '') ?>" class="notranslate"><?= format_date($article['published_at'] ?? 'now') ?></time>
        </div>

        <h3 class="article-card-title">
            <a href="<?= url('article/' . $article['slug'] . '/') ?>">
                <?= e($article['title']) ?>
            </a>
        </h3>

        <?php if (!empty($article['excerpt'])): ?>
            <p class="article-card-excerpt">
                <?= e(truncate_text($article['excerpt'], 110)) ?>
            </p>
        <?php endif; ?>

        <div class="card-footer-meta">
            <span class="notranslate"><?= icon('clock', 'icon-sm') ?> <?= e($article['read_time'] ?? '3 min read') ?></span>
            <a href="<?= url('article/' . $article['slug'] . '/') ?>" class="btn btn-sm btn-outline">
                Read Update <?= icon('chevron-right', 'icon-sm') ?>
            </a>
        </div>
    </div>
</article>
