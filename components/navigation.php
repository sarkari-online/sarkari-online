<?php
/**
 * Navigation Component (Desktop)
 * Features primary direct category links + clean 2-column institutional Mega Menu for Utilities & Portals.
 */
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';

$primaryNavLinks = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Results', 'url' => 'category/exam-results/'],
    ['label' => 'Admit Cards', 'url' => 'category/admit-cards/'],
    ['label' => 'Exam Dates', 'url' => 'category/exam-dates/'],
    ['label' => 'Latest Jobs', 'url' => 'latest-jobs/'],
    ['label' => 'Scholarships', 'url' => 'category/scholarships/'],
];

$toolsLinks = [
    [
        'label' => 'Age Calculator',
        'url'   => 'tools/age-calculator/',
        'icon'  => 'calendar',
        'desc'  => 'Calculate Exact Age & Category Relaxation'
    ],
    [
        'label' => '7th Pay Salary Calculator',
        'url'   => 'tools/7th-pay-commission-salary-calculator/',
        'icon'  => 'file-text',
        'desc'  => 'In-Hand Pay, 50% DA, HRA & NPS Deductions'
    ],
    [
        'label' => 'CGPA to % Converter',
        'url'   => 'tools/cgpa-to-percentage-calculator/',
        'icon'  => 'book-open',
        'desc'  => 'CBSE, AICTE, AKTU & 10-Point Scales'
    ],
    [
        'label' => 'Student Tools Hub',
        'url'   => 'tools/',
        'icon'  => 'layers',
        'desc'  => 'Explore All Calculators & Utilities'
    ]
];

$portalLinks = [
    [
        'label' => 'State Govt Jobs',
        'url'   => 'state-jobs/',
        'icon'  => 'award',
        'desc'  => 'UP, Bihar, Rajasthan, MP & All States 2026'
    ],
    [
        'label' => 'College Updates',
        'url'   => 'category/college-updates/',
        'icon'  => 'graduation-cap',
        'desc'  => 'UGC Norms, PhD Admissions & Cutoffs'
    ],
    [
        'label' => 'Entrance Exams',
        'url'   => 'category/entrance-exams/',
        'icon'  => 'compass',
        'desc'  => 'NEET, JEE, CTET & State CETs'
    ],
    [
        'label' => 'Career Guides',
        'url'   => 'category/career-guides/',
        'icon'  => 'trending-up',
        'desc'  => 'Syllabus, Exam Patterns & Roadmaps'
    ],
    [
        'label' => 'Student Tech & AI',
        'url'   => 'category/student-technology/',
        'icon'  => 'cpu',
        'desc'  => 'NSDC, Free Cloud & AI Certifications'
    ]
];

$isMoreActive = false;
foreach (array_merge($toolsLinks, $portalLinks) as $ml) {
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

    <!-- Professional 2-Column Mega Menu -->
    <div class="nav-dropdown-wrapper">
        <button type="button" class="nav-link nav-dropdown-btn <?= $isMoreActive ? 'active' : '' ?>" aria-expanded="false" aria-haspopup="true">
            <span>More</span>
            <span class="nav-dropdown-chevron-icon"><?= icon('chevron-right', 'icon-xs') ?></span>
        </button>
        
        <div class="nav-dropdown-menu nav-mega-menu">
            <div class="mega-menu-grid">
                
                <!-- Column 1: Examination & Student Tools -->
                <div class="mega-menu-col">
                    <div class="mega-menu-col-header">
                        <span class="mega-col-title">Examination Tools</span>
                        <span class="mega-col-badge">Interactive</span>
                    </div>
                    <div class="mega-menu-items">
                        <?php foreach ($toolsLinks as $tl): 
                            $isItemActive = str_contains($currentUrl, trim($tl['url'], '/'));
                        ?>
                            <a href="<?= url($tl['url']) ?>" class="nav-dropdown-item <?= $isItemActive ? 'active' : '' ?>">
                                <div class="nav-dropdown-item-icon">
                                    <?= icon($tl['icon'], 'icon-sm') ?>
                                </div>
                                <div class="nav-dropdown-item-text">
                                    <div class="nav-dropdown-item-title"><?= e($tl['label']) ?></div>
                                    <div class="nav-dropdown-item-desc"><?= e($tl['desc']) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Column 2: Academic & Career Portals -->
                <div class="mega-menu-col">
                    <div class="mega-menu-col-header">
                        <span class="mega-col-title">Academic &amp; Career Portals</span>
                        <span class="mega-col-badge">Live Updates</span>
                    </div>
                    <div class="mega-menu-items">
                        <?php foreach ($portalLinks as $pl): 
                            $isItemActive = str_contains($currentUrl, trim($pl['url'], '/'));
                        ?>
                            <a href="<?= url($pl['url']) ?>" class="nav-dropdown-item <?= $isItemActive ? 'active' : '' ?>">
                                <div class="nav-dropdown-item-icon">
                                    <?= icon($pl['icon'], 'icon-sm') ?>
                                </div>
                                <div class="nav-dropdown-item-text">
                                    <div class="nav-dropdown-item-title"><?= e($pl['label']) ?></div>
                                    <div class="nav-dropdown-item-desc"><?= e($pl['desc']) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Mega Menu Bottom Verification Strip -->
            <div class="mega-menu-footer">
                <span class="mega-footer-note">Statutory Data &middot; Mapped to DoPT &amp; University Rules</span>
                <a href="<?= url('tools/') ?>" class="mega-footer-link">View All Tools &rarr;</a>
            </div>
        </div>
    </div>
</nav>
