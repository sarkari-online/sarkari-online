<?php
/**
 * Sarkari.online - Global Search Results Page
 * Searches verified published articles and statutory full forms / examination glossary.
 * Zero mock data — 100% database-backed real results to prevent 404 errors.
 */
require_once __DIR__ . '/config.php';

use App\Services\ArticleService;
use App\Services\GlossaryService;

$query = trim($_GET['q'] ?? '');
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// 1. Search Live Published Articles (100% Real Database Only)
$searchData = !empty($query) ? ArticleService::search($query, $currentPage, 6) : ['items' => [], 'total' => 0, 'total_pages' => 0];
$articles = $searchData['items'] ?? [];
$total = (int)($searchData['total'] ?? 0);
$totalPages = (int)($searchData['total_pages'] ?? 0);

// 2. Search Full Forms & Acronym Glossary (A-Z Directory)
$glossaryMatches = [];
if (!empty($query)) {
    try {
        $glossaryMatches = GlossaryService::getTerms(null, null, $query, 6);
    } catch (\Throwable $e) {}
}

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, follow', true);
}

// SEO Setup: Prevent indexing of internal search results while allowing link crawling
$pageTitle = !empty($query) ? 'Search: ' . $query . ' | ' . SITE_NAME : 'Search Education Updates | ' . SITE_NAME;
$pageDesc = 'Search across verified notifications for NEET, JEE, UPSC, SSC, state board results, admit cards, and full forms.';
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

        <!-- Search Bar Header Card (Executive Redesign) -->
        <div class="search-page-box" style="background: #ffffff; border: 1px solid #e0e0e0; border-left: 5px solid #f57c00; border-radius: 12px; padding: 1.75rem 2rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
            <h1 style="font-size: 1.65rem; font-weight: 800; margin: 0 0 1rem 0; color: #1a237e; letter-spacing: -0.01em;">
                Search Education &amp; Career Updates
            </h1>
            <form action="<?= url('search/') ?>" method="GET" class="search-page-form" style="display: flex; gap: 8px;">
                <input type="search" name="q" value="<?= e($query) ?>" class="search-page-input" placeholder="Search keywords (e.g. NORCET, NEET UG, Cutoff, SSC, Admit Card, Full Forms)..." required autofocus style="flex: 1; padding: 0.8rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.15s;" onfocus="this.style.borderColor='#1a237e'" onblur="this.style.borderColor='#cbd5e1'">
                <button type="submit" style="background: #1a237e; color: #ffffff; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: background 0.15s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;" onmouseover="this.style.background='#f57c00'" onmouseout="this.style.background='#1a237e'">
                    <?= icon('search', 'icon-sm') ?> Search
                </button>
            </form>
        </div>

        <div class="article-layout-grid">
            
            <!-- Left Results Area -->
            <div>
                <?php if (!empty($query)): ?>
                    <div class="search-results-info" style="margin-bottom: 1.25rem; font-size: 0.95rem; color: #546e7a;">
                        <?php 
                        $totalCombined = $total + count($glossaryMatches);
                        ?>
                        Showing <strong style="color: #1a237e;"><?= e((string)$totalCombined) ?></strong> verified result<?= $totalCombined === 1 ? '' : 's' ?> for <em>"<?= e($query) ?>"</em>
                    </div>
                <?php endif; ?>

                <!-- Full Form / Glossary Matches Section (Executive Styling) -->
                <?php if (!empty($glossaryMatches)): ?>
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-top: 4px solid #f57c00; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 1px solid #f0f0f0; padding-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                            <h2 style="font-size: 1.15rem; font-weight: 800; color: #1a237e; margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="background: #1a237e; color: #fff; width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">A-Z</span>
                                Government &amp; Exam Full Form Matches
                            </h2>
                            <a href="<?= url('full-forms/') ?>" style="font-size: 0.85rem; font-weight: 700; color: #1a237e; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.color='#f57c00'" onmouseout="this.style.color='#1a237e'">
                                View All Full Forms &rarr;
                            </a>
                        </div>
                        <div style="display: grid; gap: 0.85rem;">
                            <?php foreach ($glossaryMatches as $gt): ?>
                                <div style="background: #fafafa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.15rem 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 1.25rem; flex-wrap: wrap; transition: all 0.15s ease;" onmouseover="this.style.borderColor='#1a237e'; this.style.background='#ffffff';" onmouseout="this.style.borderColor='#e0e0e0'; this.style.background='#fafafa';">
                                    <div style="flex: 1; min-width: 260px;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                            <span style="font-weight: 900; font-size: 1.2rem; color: #1a237e; letter-spacing: 0.02em;"><?= e($gt['acronym']) ?></span>
                                            <span style="background: #e8eaf6; color: #1a237e; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;"><?= e($gt['category']) ?></span>
                                            <?php if (!empty($gt['conducting_body'])): ?>
                                                <span style="color: #78909c; font-size: 0.8rem; font-weight: 600;">&bull; <?= e($gt['conducting_body']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.4rem;">
                                            <?= e($gt['full_form_en']) ?>
                                            <?php if (!empty($gt['full_form_hi'])): ?>
                                                <span style="color: #546e7a; font-weight: 500; font-size: 0.925rem;">(<?= e($gt['full_form_hi']) ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($gt['overview'])): ?>
                                            <p style="font-size: 0.85rem; color: #546e7a; margin: 0; line-height: 1.5; max-width: 680px;">
                                                <?= e(mb_substr(strip_tags($gt['overview']), 0, 160)) ?>...
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= url('full-forms/' . $gt['slug'] . '/') ?>" style="font-weight: 700; white-space: nowrap; padding: 0.6rem 1.2rem; border-radius: 6px; background: #1a237e; color: #ffffff; text-decoration: none; font-size: 0.85rem; transition: background 0.15s ease;" onmouseover="this.style.background='#f57c00'" onmouseout="this.style.background='#1a237e'">
                                        View Details &rarr;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Article Results -->
                <?php if (!empty($articles)): ?>
                    <?php if (!empty($glossaryMatches)): ?>
                        <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem;">
                            Latest News &amp; Recruitment Updates
                        </h2>
                    <?php endif; ?>

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

                <?php elseif (empty($glossaryMatches)): ?>
                    <div class="search-empty-state">
                        <div class="empty-icon"><?= icon('alert-triangle', 'icon-lg') ?></div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">No results found for "<?= e($query) ?>"</h3>
                        <p style="color: var(--text-muted); max-width: 440px; margin: 0 auto 1.5rem auto;">
                            We could not locate any reports matching your search. Try searching for broader terms like "NEET", "JEE", "UPSC", or "Scholarship".
                        </p>
                        <div class="search-tag-list" style="justify-content: center;">
                            <a href="<?= url('full-forms/') ?>" class="search-tag-chip">A-Z Full Forms</a>
                            <a href="<?= url('category/entrance-exams/') ?>" class="search-tag-chip">NEET UG 2026</a>
                            <a href="<?= url('category/exam-results/') ?>" class="search-tag-chip">JEE Cutoff</a>
                            <a href="<?= url('category/government-jobs/') ?>" class="search-tag-chip">SSC CGL</a>
                            <a href="<?= url('category/admit-cards/') ?>" class="search-tag-chip">Admit Cards</a>
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
