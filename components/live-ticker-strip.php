<?php
/**
 * Sarkari.online - Live Alerts Ticker Strip
 * High-speed headline ticker ribbon placed directly below hero or header.
 * 100% SVG-powered (no emojis), smooth CSS animation with pause-on-hover.
 */

use App\Database\Database;

$liveAlerts = Database::fetchAll("
    SELECT id, title, slug, published_at 
    FROM articles 
    WHERE status = 'published' 
    ORDER BY published_at DESC, id DESC 
    LIMIT 6
");

if (empty($liveAlerts)) {
    return;
}
?>

<div class="live-alerts-ribbon" role="region" aria-label="Breaking Education & Recruitment Alerts">
    <div class="container ribbon-inner">
        <div class="ribbon-label-group">
            <span class="ribbon-pulse-dot" aria-hidden="true"></span>
            <span class="ribbon-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </span>
            <span class="ribbon-badge-text">LIVE ALERTS</span>
        </div>

        <div class="ribbon-track-wrapper">
            <div class="ribbon-track">
                <?php foreach ($liveAlerts as $idx => $alert): ?>
                    <a href="<?= url('article/' . $alert['slug'] . '/') ?>" class="ribbon-item">
                        <span class="ribbon-item-prefix">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </span>
                        <span class="ribbon-item-title"><?= e($alert['title']) ?></span>
                        <span class="ribbon-item-time"><?= date('d M', strtotime($alert['published_at'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
