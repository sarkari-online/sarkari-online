<?php
/**
 * Sidebar Component
 * Displays Trending Stories, Category Quick Links, and Latest Alerts.
 */
use App\Services\ArticleService;

$sidebarTrending = ArticleService::getLatestPublished(4);
$sidebarLatest = ArticleService::getLatestPublished(4);
?>
<aside class="article-sidebar" aria-label="Secondary Sidebar">
    
    <!-- Widget 1: Trending Right Now -->
    <div class="sidebar-widget">
        <h3 class="widget-title">
            <?= icon('trending-up') ?>
            <span>Trending Right Now</span>
        </h3>
        <div class="trending-numbered-list" style="border: none; box-shadow: none;">
            <?php 
            $rank = 1;
            foreach ($sidebarTrending as $item): 
            ?>
                <div class="trending-item-row" style="padding-left: 0; padding-right: 0;">
                    <div class="trending-rank-num" style="font-size: 1.25rem; min-width: 22px;"><?= $rank++ ?></div>
                    <div class="trending-item-content">
                        <h4 class="trending-item-title" style="font-size: 0.875rem;">
                            <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                                <?= e($item['title']) ?>
                            </a>
                        </h4>
                        <div class="trending-item-meta">
                            <span><?= format_date($item['published_at'] ?? 'now') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Widget 2: Latest Verified Alerts -->
    <div class="sidebar-widget">
        <h3 class="widget-title">
            <?= icon('bolt') ?>
            <span>Latest Updates</span>
        </h3>
        <div class="sidebar-articles-list">
            <?php foreach ($sidebarLatest as $item): ?>
                <div class="sidebar-article-item">
                    <span class="badge" style="align-self: flex-start; font-size: 0.65rem; background: <?= e($item['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($item['category_color'] ?? '#1e3a8a') ?>;">
                        <?= e($item['category_name']) ?>
                    </span>
                    <h4 class="sidebar-article-title">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                            <?= e($item['title']) ?>
                        </a>
                    </h4>
                    <span class="sidebar-article-date"><?= format_date($item['published_at']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Widget 3: Quick Category Directory -->
    <div class="sidebar-widget">
        <h3 class="widget-title">
            <?= icon('compass') ?>
            <span>Key Portals</span>
        </h3>
        <ul style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
            <?php foreach (array_slice(CATEGORIES, 0, 6) as $cat): ?>
                <li>
                    <a href="<?= url('category/' . $cat['slug'] . '/') ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0.5rem; border-radius: var(--radius-xs); color: var(--text-main); font-weight: 600;">
                        <span><?= e($cat['name']) ?></span>
                        <?= icon('chevron-right', 'icon-sm', ['style' => 'color: var(--text-muted);']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

</aside>
