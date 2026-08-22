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
                        <a href="<?= url($update['url']) ?>" class="ticker-item" aria-hidden="true">
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
