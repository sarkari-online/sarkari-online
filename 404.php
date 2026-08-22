<?php
/**
 * EduPulse - 404 Not Found Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

http_response_code(404);

$pageTitle = '404 — Page Not Found';
$pageDesc = 'The page or education update you are looking for could not be located. Search our database of verified exam notices, results, and career guides.';
$canonicalUrl = url('404.php');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => '404 Not Found', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container container-narrow">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <div class="error-404-container">
            <div class="error-404-code">404</div>
            <h1 class="error-404-title">Update Not Found or Relocated</h1>
            <p class="error-404-desc">
                The exam notification, result portal, or article you were seeking might have been moved, renamed, or updated with a newer cycle notice.
            </p>

            <form action="<?= url('search/') ?>" method="GET" class="search-page-form" style="max-width: 480px; margin: 0 auto 2rem auto;">
                <input type="search" name="q" class="search-page-input" placeholder="Search exams, results, admit cards..." required>
                <button type="submit" class="btn btn-primary">
                    <?= icon('search', 'icon-sm') ?> Search
                </button>
            </form>

            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">
                    Popular Education Portals
                </h3>
                <div class="search-tag-list" style="justify-content: center;">
                    <a href="<?= url('category/exam-results/') ?>" class="search-tag-chip">Exam Results</a>
                    <a href="<?= url('category/admit-cards/') ?>" class="search-tag-chip">Admit Cards</a>
                    <a href="<?= url('category/government-jobs/') ?>" class="search-tag-chip">Govt Jobs</a>
                    <a href="<?= url('category/scholarships/') ?>" class="search-tag-chip">Scholarships</a>
                    <a href="<?= url('category/entrance-exams/') ?>" class="search-tag-chip">Entrance Exams</a>
                </div>
            </div>

            <a href="<?= url() ?>" class="btn btn-outline">
                <?= icon('chevron-left', 'icon-sm') ?> Return to Homepage
            </a>
        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
