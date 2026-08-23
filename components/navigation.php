<?php
/**
 * Navigation Component (Desktop)
 * Features primary direct category links + sleek 'More' dropdown for secondary categories.
 */
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';

$primaryNavLinks = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Results', 'url' => 'category/exam-results/'],
    ['label' => 'Admit Cards', 'url' => 'category/admit-cards/'],
    ['label' => 'Exam Dates', 'url' => 'category/exam-dates/'],
    ['label' => 'Govt Jobs', 'url' => 'category/government-jobs/'],
    ['label' => 'Scholarships', 'url' => 'category/scholarships/'],
];

$moreNavLinks = [
    ['label' => 'Career Guides', 'url' => 'category/career-guides/', 'icon' => 'trending-up', 'desc' => 'Syllabus, Exam Patterns & Roadmaps'],
    ['label' => 'Entrance Exams', 'url' => 'category/entrance-exams/', 'icon' => 'compass', 'desc' => 'NEET, JEE, CTET & State CETs'],
    ['label' => 'Student Tech & AI', 'url' => 'category/student-technology/', 'icon' => 'cpu', 'desc' => 'NSDC, Free Cloud & AI Certifications']
];

$isMoreActive = false;
foreach ($moreNavLinks as $ml) {
    if (str_contains($currentUrl, trim($ml['url'], '/'))) {
        $isMoreActive = true;
        break;
    }
}
?>
<nav class="desktop-nav" aria-label="Main Navigation">
    <?php foreach ($primaryNavLinks as $link): 
        $isActive = ($link['url'] === '' && ($currentUrl === '/' || $currentUrl === BASE_PATH || $currentUrl === BASE_PATH . '/'))
                    || ($link['url'] !== '' && str_contains($currentUrl, trim($link['url'], '/')));
    ?>
        <a href="<?= url($link['url']) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
            <?= e($link['label']) ?>
        </a>
    <?php endforeach; ?>

    <!-- Sleek 'More' Dropdown -->
    <div class="nav-dropdown-wrapper">
        <button type="button" class="nav-link nav-dropdown-btn <?= $isMoreActive ? 'active' : '' ?>" aria-expanded="false" aria-haspopup="true">
            <span>More</span>
            <span class="nav-dropdown-chevron-icon"><?= icon('chevron-right', 'icon-xs') ?></span>
        </button>
        <div class="nav-dropdown-menu">
            <?php foreach ($moreNavLinks as $ml): 
                $isItemActive = str_contains($currentUrl, trim($ml['url'], '/'));
            ?>
                <a href="<?= url($ml['url']) ?>" class="nav-dropdown-item <?= $isItemActive ? 'active' : '' ?>">
                    <div class="nav-dropdown-item-icon">
                        <?= icon($ml['icon'], 'icon-sm') ?>
                    </div>
                    <div class="nav-dropdown-item-text">
                        <div class="nav-dropdown-item-title"><?= e($ml['label']) ?></div>
                        <div class="nav-dropdown-item-desc"><?= e($ml['desc']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
