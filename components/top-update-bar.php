<?php
/**
 * Top Update Strip / Breaking Updates Ticker Component
 */
use App\Services\ArticleService;

$dbLatest = ArticleService::getLatestPublished(4);
$breakingUpdates = [];

if (!empty($dbLatest)) {
    foreach ($dbLatest as $item) {
        $breakingUpdates[] = [
            'tag' => strtoupper($item['category_name'] ?? 'UPDATE'),
            'title' => $item['title'],
            'time' => time_ago($item['published_at'] ?? 'now'),
            'url' => 'article/' . $item['slug'] . '/'
        ];
    }
} else {
    $breakingUpdates = MockData::getBreakingUpdates();
}
?>
<!-- Official Non-Affiliation Statutory Advisory Strip (AdSense Compliance) -->
<div class="statutory-advisory-strip" style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 0.725rem; color: #475569; padding: 4px 0; line-height: 1.4;">
    <div class="container" style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 5px;">
            <span style="font-size: 0.8rem;">&#9888;&#65039;</span>
            <span><strong>Statutory Advisory:</strong> <?= e(SITE_NAME) ?> is an independent educational news observatory and is not affiliated with the Government of India or any examination board.</span>
        </div>
        <div>
            <a href="<?= url('disclaimer/') ?>" style="color: #2563eb; font-weight: 600; text-decoration: none; white-space: nowrap;">Disclaimer &rarr;</a>
        </div>
    </div>
</div>

<div class="top-update-bar">
    <div class="container">
        <div class="top-update-inner">
            <div class="update-label">
                <span class="update-pulse-dot"></span>
                <span>Updates</span>
            </div>
            <div class="update-ticker" aria-live="polite">
                <div class="update-ticker-track">
                    <?php foreach ($breakingUpdates as $update): ?>
                        <a href="<?= url($update['url']) ?>" class="ticker-item">
                            <span class="badge badge-pill" style="font-size: 0.65rem; background: rgba(255,255,255,0.15); color: #fff;"><?= e($update['tag']) ?></span>
                            <span><?= e($update['title']) ?></span>
                            <span class="ticker-time">(<?= e($update['time']) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                    <!-- Duplicate for infinite seamless scroll -->
                    <?php foreach ($breakingUpdates as $update): ?>
                        <a href="<?= url($update['url']) ?>" class="ticker-item" aria-hidden="true" tabindex="-1">
                            <span class="badge badge-pill" style="font-size: 0.65rem; background: rgba(255,255,255,0.15); color: #fff;"><?= e($update['tag']) ?></span>
                            <span><?= e($update['title']) ?></span>
                            <span class="ticker-time">(<?= e($update['time']) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
