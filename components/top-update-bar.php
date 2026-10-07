<?php
/**
 * Top Update Strip / Breaking Updates Ticker Component
 */
use App\Database\Database;

$featuredTickerTerms = [];
try {
    $featuredTickerTerms = Database::fetchAll("
        SELECT acronym, slug, full_form_en, category 
        FROM glossary_terms 
        ORDER BY updated_at DESC, id ASC 
        LIMIT 6
    ");
} catch (\Throwable $e) {}

$breakingUpdates = [];
if (!empty($featuredTickerTerms)) {
    foreach ($featuredTickerTerms as $item) {
        $breakingUpdates[] = [
            'tag' => 'FULL FORM',
            'title' => $item['acronym'] . ': ' . $item['full_form_en'],
            'time' => 'Verified',
            'url' => 'full-forms/' . $item['slug'] . '/'
        ];
    }
} else {
    $breakingUpdates = [
        ['tag' => 'FULL FORM', 'title' => 'UPSC: Union Public Service Commission', 'time' => 'Verified', 'url' => 'full-forms/upsc/'],
        ['tag' => 'FULL FORM', 'title' => 'SSC: Staff Selection Commission', 'time' => 'Verified', 'url' => 'full-forms/ssc/'],
        ['tag' => 'FULL FORM', 'title' => 'RRB: Railway Recruitment Board', 'time' => 'Verified', 'url' => 'full-forms/rrb/'],
    ];
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
                        <a href="<?= url($update['url']) ?>" class="ticker-item" title="<?= e($update['title']) ?>">
                            <span class="badge badge-pill" style="font-size: 0.65rem; background: rgba(255,255,255,0.15); color: var(--bg-page);"><?= e($update['tag']) ?></span>
                            <span><?= e($update['title']) ?></span>
                            <span class="ticker-time">(<?= e($update['time']) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                    <!-- Duplicate for infinite seamless scroll -->
                    <?php foreach ($breakingUpdates as $update): ?>
                        <a href="<?= url($update['url']) ?>" class="ticker-item" aria-hidden="true" tabindex="-1" title="<?= e($update['title']) ?>">
                            <span class="badge badge-pill" style="font-size: 0.65rem; background: rgba(255,255,255,0.15); color: var(--bg-page);"><?= e($update['tag']) ?></span>
                            <span><?= e($update['title']) ?></span>
                            <span class="ticker-time">(<?= e($update['time']) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
