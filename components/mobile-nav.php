<?php
/**
 * Sticky Mobile Bottom Navigation Bar (App Bar)
 * Native app-like bottom navigation for mobile aspirants (< 768px).
 */
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

$mobileNavItems = [
    [
        'label' => 'Home',
        'url' => '',
        'icon' => 'home',
        'is_active' => ($currentUri === '/' || $currentUri === BASE_PATH || $currentUri === BASE_PATH . '/')
    ],
    [
        'label' => 'Alerts',
        'url' => 'category/admit-cards/',
        'icon' => 'bell',
        'is_active' => str_contains($currentUri, 'admit-cards') || str_contains($currentUri, 'exam-results')
    ],
    [
        'label' => 'Exam Dates',
        'url' => 'category/exam-dates/',
        'icon' => 'calendar',
        'is_active' => str_contains($currentUri, 'exam-dates')
    ],
    [
        'label' => 'Jobs',
        'url' => 'category/government-jobs/',
        'icon' => 'briefcase',
        'is_active' => str_contains($currentUri, 'government-jobs')
    ],
    [
        'label' => 'Search',
        'url' => 'search/',
        'icon' => 'search',
        'is_active' => str_contains($currentUri, 'search')
    ]
];
?>

<!-- Mobile App-Like Sticky Bottom Bar -->
<nav class="mobile-bottom-nav" aria-label="Mobile Quick Navigation">
    <div class="mobile-bottom-nav-inner">
        <?php foreach ($mobileNavItems as $item): ?>
            <a href="<?= url($item['url']) ?>" class="mobile-nav-item <?= $item['is_active'] ? 'active' : '' ?>" aria-label="<?= e($item['label']) ?>">
                <div class="mobile-nav-icon">
                    <?= icon($item['icon'], 'icon-sm') ?>
                </div>
                <span class="mobile-nav-label"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
