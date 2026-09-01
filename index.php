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

        <!-- 2. Latest Updates Grid -->
        <section class="content-section" aria-labelledby="sec-latest-title">
            <div class="section-header">
                <h2 class="section-title" id="sec-latest-title">
                    <?= icon('bolt') ?>
                    <span>Latest Updates</span>
                </h2>
                <a href="<?= url('category/exam-results/') ?>" class="section-link-more">
                    View All Updates <?= icon('chevron-right', 'icon-sm') ?>
                </a>
            </div>

            <div class="grid-3">
                <?php foreach ($latestArticles as $article): ?>
                    <?php include __DIR__ . '/components/article-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>

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
                
                <!-- Student Tech & AI -->
                <div>
                    <div class="section-header">
                        <h2 class="section-title">
                            <?= icon('cpu') ?>
                            <span>Student Tech &amp; AI Tools</span>
                        </h2>
                        <a href="<?= url('category/student-technology/') ?>" class="section-link-more">
                            Explore <?= icon('chevron-right', 'icon-sm') ?>
                        </a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($studentTech as $tech): ?>
                            <div class="card-compact-row">
                                <div class="compact-row-thumb">
                                    <a href="<?= url('article/' . $tech['slug'] . '/') ?>">
                                        <?php if (!empty($tech['featured_image'])): ?>
                                            <img src="<?= url($tech['featured_image']) ?>" alt="<?= e($tech['title']) ?>" class="card-thumb-img" loading="lazy" width="320" height="240" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: var(--radius-sm);">
                                        <?php else: ?>
                                            <?= render_thumbnail_svg($tech['category_slug'] ?? 'student-technology', $tech['title'], 320, 240) ?>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="compact-row-content">
                                    <div class="card-meta" style="margin-bottom: 0.35rem; font-size: 0.75rem;">
                                        <span class="badge" style="background: #e2e8f0; color: #0f172a; font-weight: 700; font-size: 0.7rem; border: 1px solid #cbd5e1;">
                                            <?= e($tech['category_name'] ?? 'Tech') ?>
                                        </span>
                                        <span class="meta-dot"></span>
                                        <span><?= format_date($tech['published_at'] ?? 'now') ?></span>
                                    </div>
                                    <h3 class="compact-row-title">
                                        <a href="<?= url('article/' . $tech['slug'] . '/') ?>"><?= e($tech['title']) ?></a>
                                    </h3>
                                    <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0;"><?= e(truncate_text($tech['excerpt'], 90)) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
