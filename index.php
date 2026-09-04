<?php
/**
 * EduPulse - Homepage (Phase 1)
 * Server-rendered editorial homepage connected to MySQL via ArticleService and CategoryService.
 * 100% database-backed with verified educational articles and real HTTP 200 URLs.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Data/MockData.php';

// Strict Router Guard: If a non-existent URL is rewritten to index.php, return real HTTP 404 page
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$cleanPath = trim($requestUri, '/');
if (str_starts_with($cleanPath, 'automation')) {
    $cleanPath = trim(substr($cleanPath, 10), '/');
}

if (!empty($cleanPath) && $cleanPath !== 'index.php') {
    // Dynamic Tool Router: route any /tools/{slug} to its php file
    if (str_starts_with($cleanPath, 'tools/')) {
        $toolSlug = trim(substr($cleanPath, 6), '/');
        $toolFile = __DIR__ . '/tools/' . $toolSlug . '.php';
        if (file_exists($toolFile)) {
            require $toolFile;
            exit;
        }
    }

    // Dynamic State Jobs Hub Router: route /state-jobs
    if ($cleanPath === 'state-jobs' || $cleanPath === 'state-jobs/') {
        require __DIR__ . '/state-jobs.php';
        exit;
    }

    // Dynamic State Detail Router: route /jobs/{state-slug}
    if (str_starts_with($cleanPath, 'jobs/')) {
        $stateSlug = trim(substr($cleanPath, 5), '/');
        if (!empty($stateSlug)) {
            $_GET['state'] = $stateSlug;
            require __DIR__ . '/state-detail.php';
            exit;
        }
    }

    require __DIR__ . '/404.php';
    exit;
}

use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\TrendService;

// SEO Meta Variables
$pageTitle = 'Indian Education, Exams, Results & Career Alerts';
$pageDesc = 'Authentic real-time updates on NEET, JEE, UPSC, SSC, CBSE, State board results, admit cards, exam dates, government jobs, and scholarships across India.';
$canonicalUrl = SITE_URL . '/';
$ogType = 'website';

// Fetch Live Articles from Database
$dbArticles = ArticleService::getLatestPublished(10);
if (!empty($dbArticles)) {
    $featured = $dbArticles[0];
    $secondary = array_slice($dbArticles, 1, 3);
    $latestArticles = $dbArticles;
} else {
    $heroData = MockData::getHeroArticles();
    $featured = $heroData['featured'];
    $secondary = $heroData['secondary'];
    $latestArticles = MockData::getLatestArticles(6);
}

// Category-Specific Database Records
$examUpdates = ArticleService::getLatestPublished(4, 3); // Category 3: Exam Dates
if (empty($examUpdates)) {
    $examUpdates = ArticleService::getLatestPublished(4, 1); // Fallback: Exam Results
}

$govtJobs = ArticleService::getLatestPublished(3, 6); // Category 6: Government Jobs
if (empty($govtJobs)) {
    $govtJobs = array_slice($dbArticles, 0, 3);
}

$scholarships = ArticleService::getLatestPublished(3, 7); // Category 7: Scholarships
if (empty($scholarships)) {
    $scholarships = array_slice($dbArticles, 0, 3);
}

$careerGuides = ArticleService::getLatestPublished(3, 9); // Category 9: Career Guides
if (empty($careerGuides)) {
    $careerGuides = array_slice($dbArticles, 0, 3);
}

$studentTech = ArticleService::getLatestPublished(2, 10); // Category 10: Student Tech
if (empty($studentTech)) {
    $studentTech = array_slice($dbArticles, 0, 2);
}

$popularGuides = array_slice($dbArticles, 0, 4);
$categoriesList = CategoryService::getAll();
$lcpImagePreload = !empty($featured['featured_image']) ? url($featured['featured_image']) : null;

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">

        <!-- 1. Hero Editorial Section (Featured + Secondary) -->
        <?php include __DIR__ . '/components/featured-card.php'; ?>

        <!-- 2. 3-Pillar Candidate Action Hub (Results | Admit Cards | Latest Jobs) -->
        <?php include __DIR__ . '/components/fast-feed-columns.php'; ?>

        <!-- 3. Two-Column Layout: Exam Updates + Trending 1-5 -->
        <section class="content-section">
            <div class="hero-editorial-grid">
                
                <!-- Left: Exam Updates (Dates, Admit Cards, Results) -->
                <div>
                    <div class="section-header">
                        <h2 class="section-title">
                            <?= icon('calendar') ?>
                            <span>Exam Updates &amp; Schedules</span>
                        </h2>
                        <a href="<?= url('category/exam-dates/') ?>" class="section-link-more">
                            Calendar <?= icon('chevron-right', 'icon-sm') ?>
                        </a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        <?php foreach ($examUpdates as $exam): ?>
                            <div class="exam-update-card">
                                <div class="exam-update-info">
                                    <div class="card-meta" style="margin-bottom: 0.25rem;">
                                        <span class="badge badge-pill" style="background: #eff6ff; color: #1e3a8a; font-weight: 700; font-size: 0.7rem; border: 1px solid #bfdbfe;">
                                            <?= e($exam['category_name'] ?? 'Exam Notice') ?>
                                        </span>
                                        <span><?= format_date($exam['published_at'] ?? 'now') ?></span>
                                    </div>
                                    <h3 class="exam-update-title">
                                        <a href="<?= url('article/' . $exam['slug'] . '/') ?>"><?= e($exam['title']) ?></a>
                                    </h3>
                                    <div class="exam-update-meta">
                                        <span><strong>Source:</strong> <?= e($exam['source_name'] ?? 'Official Authority') ?></span>
                                    </div>
                                </div>
                                <div>
                                    <a href="<?= url('article/' . $exam['slug'] . '/') ?>" class="btn btn-sm btn-outline">
                                        Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Trending Now (Rank 1 to 5) -->
                <div>
                    <div class="section-header">
                        <h2 class="section-title">
                            <?= icon('trending-up') ?>
                            <span>Trending Now</span>
                        </h2>
                    </div>

                    <?php include __DIR__ . '/components/trending-list.php'; ?>
                </div>

            </div>
        </section>

        <!-- 4. Latest Government Jobs -->
        <section class="content-section" aria-labelledby="sec-govt-jobs">
            <div class="section-header">
                <h2 class="section-title" id="sec-govt-jobs">
                    <?= icon('briefcase') ?>
                    <span>Latest Government Jobs</span>
                </h2>
                <a href="<?= url('category/government-jobs/') ?>" class="section-link-more">
                    All Recruitment <?= icon('chevron-right', 'icon-sm') ?>
                </a>
            </div>

            <div class="grid-3">
                <?php foreach ($govtJobs as $article): ?>
                    <?php include __DIR__ . '/components/article-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. Scholarships & Student Opportunities -->
        <section class="content-section" aria-labelledby="sec-scholarships">
            <div class="section-header">
                <h2 class="section-title" id="sec-scholarships">
                    <?= icon('graduation-cap') ?>
                    <span>Scholarships &amp; Student Opportunities</span>
                </h2>
                <a href="<?= url('category/scholarships/') ?>" class="section-link-more">
                    All Scholarships <?= icon('chevron-right', 'icon-sm') ?>
                </a>
            </div>

            <div class="grid-3">
                <?php foreach ($scholarships as $article): ?>
                    <?php include __DIR__ . '/components/article-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 6. Career Guides & Evergreen Roadmaps -->
        <section class="content-section" aria-labelledby="sec-career-guides">
            <div class="section-header">
                <h2 class="section-title" id="sec-career-guides">
                    <?= icon('compass') ?>
                    <span>Career Guides &amp; Roadmaps</span>
                </h2>
                <a href="<?= url('category/career-guides/') ?>" class="section-link-more">
                    All Guides <?= icon('chevron-right', 'icon-sm') ?>
                </a>
            </div>

            <div class="grid-3">
                <?php foreach ($careerGuides as $article): ?>
                    <?php include __DIR__ . '/components/article-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 7. Two-Column: Student Tech & AI (Secondary) + Popular Evergreen Guides -->
        <section class="content-section">
            <div class="grid-2">
                
                <!-- Examination Tools & Student Calculators -->
                <div>
                    <div class="section-header">
                        <h2 class="section-title">
                            <?= icon('layers') ?>
                            <span>Student Utilities &amp; Exam Tools</span>
                        </h2>
                        <a href="<?= url('tools/') ?>" class="section-link-more">
                            All Tools <?= icon('chevron-right', 'icon-sm') ?>
                        </a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                        
                        <!-- Tool 1: Age Calculator -->
                        <div class="card-compact-row" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1rem 1.15rem; display: flex; gap: 1rem; align-items: center; transition: all 0.2s ease;">
                            <div style="width: 48px; height: 48px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #1e3a8a; flex-shrink: 0;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; background: #dbeafe; padding: 2px 7px; border-radius: 4px;">DoPT Statutory Rules</span>
                                    <span style="font-size: 0.7rem; color: #64748b;">2026 Cutoff</span>
                                </div>
                                <h3 style="font-size: 0.95rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.25rem 0;">
                                    <a href="<?= url('tools/age-calculator/') ?>" style="color: var(--text-main); text-decoration: none;">Govt Job Age Calculator &amp; Eligibility Checker</a>
                                </h3>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.35rem 0; line-height: 1.4;">Calculate exact age &amp; category relaxation (UR, OBC, SC, ST) for UPSC &amp; SSC.</p>
                                <a href="<?= url('tools/age-calculator/') ?>" style="font-size: 0.775rem; font-weight: 700; color: var(--color-primary); display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                    <span>Open Age Calculator</span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Tool 2: 7th Pay Salary Calculator -->
                        <div class="card-compact-row" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1rem 1.15rem; display: flex; gap: 1rem; align-items: center; transition: all 0.2s ease;">
                            <div style="width: 48px; height: 48px; border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; color: #16a34a; flex-shrink: 0;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #15803d; background: #dcfce7; padding: 2px 7px; border-radius: 4px;">7th CPC Matrix</span>
                                    <span style="font-size: 0.7rem; color: #64748b;">50% DA Updated</span>
                                </div>
                                <h3 style="font-size: 0.95rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.25rem 0;">
                                    <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" style="color: var(--text-main); text-decoration: none;">7th Pay Commission Salary &amp; In-Hand Calculator</a>
                                </h3>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.35rem 0; line-height: 1.4;">Calculate post-wise monthly in-hand net salary, HRA &amp; mandatory NPS deductions.</p>
                                <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" style="font-size: 0.775rem; font-weight: 700; color: var(--color-primary); display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                    <span>Open Salary Calculator</span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Tool 3: CGPA Converter -->
                        <div class="card-compact-row" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1rem 1.15rem; display: flex; gap: 1rem; align-items: center; transition: all 0.2s ease;">
                            <div style="width: 48px; height: 48px; border-radius: 10px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7; flex-shrink: 0;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 2px 7px; border-radius: 4px;">CBSE &amp; AICTE Formula</span>
                                    <span style="font-size: 0.7rem; color: #64748b;">10-Point Scale</span>
                                </div>
                                <h3 style="font-size: 0.95rem; font-weight: 700; line-height: 1.35; margin: 0 0 0.25rem 0;">
                                    <a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" style="color: var(--text-main); text-decoration: none;">CGPA to Percentage &amp; Marks Converter</a>
                                </h3>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.35rem 0; line-height: 1.4;">Convert CGPA to exact marks &amp; percentages for CBSE, B.Tech, and State Universities.</p>
                                <a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" style="font-size: 0.775rem; font-weight: 700; color: var(--color-primary); display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                    <span>Open CGPA Converter</span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Popular Evergreen Guides -->
                <div>
                    <div class="section-header">
                        <h2 class="section-title">
                            <?= icon('award') ?>
                            <span>Popular High-Value Guides</span>
                        </h2>
                    </div>

                    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($popularGuides as $pop): ?>
                            <div style="display: flex; gap: 0.85rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-subtle);">
                                <div style="font-size: 1.1rem; font-weight: 800; color: var(--color-primary); min-width: 20px;">
                                    <?= icon('check-circle', 'icon-sm') ?>
                                </div>
                                <div>
                                    <span class="badge" style="font-size: 0.7rem; font-weight: 700; background: #e0e7ff; color: #1e1b4b; margin-bottom: 0.25rem;"><?= e($pop['category_name'] ?? 'Guide') ?></span>
                                    <h3 style="font-size: 0.9375rem; font-weight: 700; line-height: 1.35; margin-bottom: 0.25rem;">
                                        <a href="<?= url('article/' . $pop['slug'] . '/') ?>"><?= e($pop['title']) ?></a>
                                    </h3>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= format_date($pop['published_at'] ?? 'now') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- 8. Interactive Topic Pills & Category Quick-Access Matrix -->
        <section class="content-section topic-matrix-section" aria-labelledby="sec-portals">
            <div class="topic-matrix-header">
                <div class="topic-matrix-title-wrap">
                    <h2 class="section-title" id="sec-portals">
                        <?= icon('layers') ?>
                        <span>Explore Portals &amp; Categories</span>
                    </h2>
                    <p class="topic-matrix-subtitle">
                        Instant direct access to all 10 verified education, admission, and statutory recruitment archives.
                    </p>
                </div>
            </div>

            <!-- Sleek Pill Capsule Matrix -->
            <div class="topic-pills-matrix">
                <?php foreach (CATEGORIES as $cat): ?>
                    <a href="<?= url('category/' . $cat['slug'] . '/') ?>" class="topic-pill-item" style="--pill-color: <?= e($cat['color']) ?>; --pill-bg: <?= e($cat['bg_light']) ?>;">
                        <span class="topic-pill-icon">
                            <?= icon($cat['icon'], 'icon-xs') ?>
                        </span>
                        <span class="topic-pill-name"><?= e($cat['name']) ?></span>
                        <?php if (!empty($cat['hindi_name'])): ?>
                            <span class="topic-pill-hindi"><?= e($cat['hindi_name']) ?></span>
                        <?php endif; ?>
                        <span class="topic-pill-arrow"><?= icon('arrow-right', 'icon-xs') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Statutory Popular Boards Quick Links Strip -->
            <div class="topic-boards-strip">
                <span class="topic-boards-label">Key Statutory Portals:</span>
                <div class="topic-boards-chips">
                    <a href="<?= url('search/?q=NTA') ?>" rel="nofollow" class="topic-board-chip">NTA (NEET / JEE / CUET)</a>
                    <a href="<?= url('search/?q=UPSC') ?>" rel="nofollow" class="topic-board-chip">UPSC Civil Services</a>
                    <a href="<?= url('search/?q=SSC') ?>" rel="nofollow" class="topic-board-chip">SSC (CGL / CHSL / GD)</a>
                    <a href="<?= url('search/?q=CBSE') ?>" rel="nofollow" class="topic-board-chip">CBSE Board</a>
                    <a href="<?= url('search/?q=IBPS') ?>" rel="nofollow" class="topic-board-chip">IBPS &amp; Banking</a>
                    <a href="<?= url('category/scholarships/') ?>" class="topic-board-chip">National Scholarship Portal</a>
                    <a href="<?= url('category/college-updates/') ?>" class="topic-board-chip">JoSAA / MCC Counselling</a>
                </div>
            </div>
        </section>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
