<?php
/**
 * EduPulse - Search Results Page (Phase 1)
 * MySQL Fulltext and LIKE search queries via ArticleService.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Data/MockData.php';

use App\Services\ArticleService;

$query = trim($_GET['q'] ?? '');
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$searchData = ArticleService::search($query, $currentPage, 6);

// Fallback to mock search if empty query or 0 results in dev
if (empty($searchData['items']) && !empty($query)) {
    $mockSearch = MockData::searchArticles($query, $currentPage, 6);
    if (!empty($mockSearch['items'])) {
        $searchData = $mockSearch;
    }
}

$articles = $searchData['items'];
$total = $searchData['total'];
$totalPages = $searchData['total_pages'];

// SEO Setup: Prevent indexing of internal search results (Google Search Quality Guideline)
$pageTitle = !empty($query) ? 'Search results for "' . $query . '"' : 'Search All Education Updates';
$pageDesc = 'Search across verified notifications for NEET, JEE, UPSC, SSC, state board results, admit cards, and scholarships.';
$canonicalUrl = url('search/');
$metaRobots = 'noindex, follow';
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Search', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <!-- Breadcrumbs -->
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <!-- Search Bar Header Card -->
        <div class="search-page-box">
            <h1 style="font-size: 1.5rem; margin-bottom: 1rem; color: var(--text-main);">
                Search Education &amp; Career Updates
            </h1>
            <form action="<?= url('search/') ?>" method="GET" class="search-page-form">
                <input type="search" name="q" value="<?= e($query) ?>" class="search-page-input" placeholder="Search keywords (e.g. NEET UG, Cutoff, SSC, Admit Card)..." required autofocus>
                <button type="submit" class="btn btn-primary">
                    <?= icon('search', 'icon-sm') ?> Search
                </button>
            </form>
        </div>

        <div class="article-layout-grid">
            
            <!-- Left Results Area -->
            <div>
                <?php if (!empty($query)): ?>
                    <div class="search-results-info">
                        Showing <strong><?= e((string)$total) ?></strong> verified results for <em>"<?= e($query) ?>"</em>
                    </div>
                <?php endif; ?>

                <?php if (!empty($articles)): ?>
                    <div class="grid-2">
                        <?php foreach ($articles as $article): ?>
                            <?php include __DIR__ . '/components/article-card.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrapper" aria-label="Search Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="<?= url('search/?q=' . urlencode($query) . '&page=' . ($currentPage - 1)) ?>" class="page-btn">
                                    <?= icon('chevron-left', 'icon-sm') ?> Prev
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= url('search/?q=' . urlencode($query) . '&page=' . $i) ?>" class="page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?= url('search/?q=' . urlencode($query) . '&page=' . ($currentPage + 1)) ?>" class="page-btn">
                                    Next <?= icon('chevron-right', 'icon-sm') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="search-empty-state">
                        <div class="empty-icon"><?= icon('alert-triangle', 'icon-lg') ?></div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">No results found for "<?= e($query) ?>"</h3>
                        <p style="color: var(--text-muted); max-width: 440px; margin: 0 auto 1.5rem auto;">
                            We could not locate any reports matching your search. Try searching for broader terms like "NEET", "JEE", "UPSC", or "Scholarship".
                        </p>
                        <div class="search-tag-list" style="justify-content: center;">
                            <a href="<?= url('search/?q=NEET+UG') ?>" rel="nofollow" class="search-tag-chip">NEET UG 2026</a>
                            <a href="<?= url('search/?q=JEE+Advanced') ?>" rel="nofollow" class="search-tag-chip">JEE Cutoff</a>
                            <a href="<?= url('search/?q=SSC+CGL') ?>" rel="nofollow" class="search-tag-chip">SSC CGL</a>
                            <a href="<?= url('search/?q=Admit+Card') ?>" rel="nofollow" class="search-tag-chip">Admit Cards</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <?php include __DIR__ . '/components/sidebar.php'; ?>

        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
