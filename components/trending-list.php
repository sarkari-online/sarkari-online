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
                    <span class="badge" style="background-color: <?= e($item['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($item['category_color'] ?? '#1e3a8a') ?>; font-size: 0.65rem;">
                        <?= e($item['category_name'] ?? 'Education') ?>
                    </span>
                    <span><?= format_date($item['published_at'] ?? 'now') ?></span>
                </div>
                <h4 class="trending-item-title">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                        <?= e($item['title']) ?>
                    </a>
                </h4>
            </div>
        </div>
    <?php endforeach; ?>
</div>
