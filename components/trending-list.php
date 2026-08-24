<?php
/**
 * Trending 1-5 Numbered List Component
 */
use App\Services\ArticleService;

$trendingItems = ArticleService::getLatestPublished(5);
if (empty($trendingItems)) {
    $trendingItems = MockData::getTrendingNow();
}
?>
<div class="trending-numbered-list" aria-label="Trending Updates List">
    <?php 
    $rank = 1;
    foreach ($trendingItems as $item): 
    ?>
        <div class="trending-item-row">
            <div class="trending-rank-num"><?= $rank++ ?></div>
            <div class="trending-item-content">
                <div class="trending-item-meta">
                    <span class="badge" style="background-color: <?= e($item['category_bg_light'] ?? '#eff6ff') ?>; color: #0f172a; font-weight: 700; font-size: 0.68rem; border: 1px solid <?= e($item['category_color'] ?? '#cbd5e1') ?>40;">
                        <?= e($item['category_name'] ?? 'Education') ?>
                    </span>
                    <span><?= format_date($item['published_at'] ?? 'now') ?></span>
                </div>
                <h3 class="trending-item-title">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                        <?= e($item['title']) ?>
                    </a>
                </h3>
            </div>
        </div>
    <?php endforeach; ?>
</div>
