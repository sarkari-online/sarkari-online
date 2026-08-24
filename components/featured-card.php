<?php
/**
 * Featured Hero Cards Component
 * Renders large editorial card (left) + secondary compact cards (right).
 */
if (!isset($featured) || !isset($secondary)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(4);
    if (!empty($dbHero)) {
        $featured = $dbHero[0];
        $secondary = array_slice($dbHero, 1, 3);
    } else {
        $heroData = MockData::getHeroArticles();
        $featured = $heroData['featured'];
        $secondary = $heroData['secondary'];
    }
}
?>
<section class="hero-editorial-grid" aria-label="Featured Story and Top Updates">
    
    <!-- Left: Large Featured Article -->
    <article class="hero-card-featured">
        <div class="card-media hero-media-large">
            <a href="<?= url('article/' . $featured['slug'] . '/') ?>" aria-label="<?= e($featured['title']) ?>">
                <?php if (!empty($featured['featured_image'])): ?>
                    <img src="<?= url($featured['featured_image']) ?>" alt="<?= e($featured['featured_image_alt'] ?? $featured['title']) ?>" class="card-thumb-img" loading="eager" fetchpriority="high" decoding="async" width="800" height="450" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                <?php else: ?>
                    <?= render_thumbnail_svg($featured['category_slug'] ?? $featured['category'] ?? 'exam-results', $featured['title'], 800, 450) ?>
                <?php endif; ?>
            </a>
        </div>
        <div class="card-body">
            <div class="card-meta">
                <?php
                $featCatSlug = $featured['category_slug'] ?? $featured['category'] ?? 'exam-results';
                $featCatName = $featured['category_name'] ?? 'Education';
                $featCatColor = $featured['category_color'] ?? '#1e3a8a';
                ?>
                <a href="<?= url('category/' . $featCatSlug . '/') ?>" class="badge" style="background-color: <?= e($featCatColor) ?>15; color: <?= e($featCatColor) ?>;">
                    <?= e($featCatName) ?>
                </a>
                <span class="meta-dot"></span>
                <span><?= format_date($featured['published_at'] ?? 'now') ?></span>
                <?php if (!empty($featured['updated_at'])): ?>
                    <span class="meta-dot"></span>
                    <span class="badge badge-live" style="font-size: 0.685rem;">Updated <?= time_ago($featured['updated_at']) ?></span>
                <?php endif; ?>
            </div>

            <h1 class="card-title-hero">
                <a href="<?= url('article/' . $featured['slug'] . '/') ?>">
                    <?= e($featured['title']) ?>
                </a>
            </h1>

            <p class="card-excerpt">
                <?= e($featured['excerpt'] ?? '') ?>
            </p>

            <div class="card-footer-meta">
                <span class="card-author"><?= icon('user', 'icon-sm') ?> <?= e($featured['author'] ?? $featured['author_username'] ?? 'Sarkari.online Editorial') ?></span>
                <span><?= icon('clock', 'icon-sm') ?> <?= e($featured['read_time'] ?? '4 min read') ?></span>
            </div>
        </div>
    </article>

    <!-- Right: Secondary Trending Articles -->
    <div class="hero-secondary-stack">
        <div class="hero-secondary-header">
            <h2 class="section-title" style="font-size: 1.05rem; margin-bottom: 0;">
                <?= icon('zap') ?>
                <span>Top Highlights</span>
            </h2>
            <span class="badge badge-pill" style="font-size: 0.7rem; font-weight: 700; background: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">
                Verified Notices
            </span>
        </div>

        <?php foreach ($secondary as $item): 
            $itemCatSlug = $item['category_slug'] ?? $item['category'] ?? 'exam-results';
            $itemCatName = $item['category_name'] ?? 'Education';
            $itemCatColor = $item['category_color'] ?? '#1e3a8a';
        ?>
            <article class="card-compact-row">
                <div class="compact-row-thumb">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>" aria-label="<?= e($item['title']) ?>">
                        <?php if (!empty($item['featured_image'])): ?>
                            <img src="<?= url($item['featured_image']) ?>" alt="<?= e($item['featured_image_alt'] ?? $item['title']) ?>" class="card-thumb-img" loading="lazy" decoding="async" width="360" height="202" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        <?php else: ?>
                            <?= render_thumbnail_svg($itemCatSlug, $item['title'], 360, 202) ?>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="compact-row-content">
                    <div class="card-meta" style="margin-bottom: 0.35rem; font-size: 0.75rem;">
                        <a href="<?= url('category/' . $itemCatSlug . '/') ?>" class="badge" style="background-color: <?= e($itemCatColor) ?>15; color: <?= e($itemCatColor) ?>; font-size: 0.68rem; font-weight: 700;">
                            <?= e($itemCatName) ?>
                        </a>
                        <span class="meta-dot"></span>
                        <span style="color: var(--text-muted); font-size: 0.75rem;"><?= format_date($item['published_at'] ?? 'now') ?></span>
                    </div>
                    <h3 class="compact-row-title">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                            <?= e($item['title']) ?>
                        </a>
                    </h3>
                    <?php if (!empty($item['excerpt'])): ?>
                        <p class="compact-row-excerpt"><?= e(truncate_text($item['excerpt'], 75)) ?></p>
                    <?php endif; ?>
                    <div class="compact-row-footer">
                        <span><?= icon('clock', 'icon-sm') ?> <?= e($item['read_time'] ?? '3 min read') ?></span>
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="compact-read-more">
                            Read Notice <?= icon('chevron-right', 'icon-xs') ?>
                        </a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

</section>
