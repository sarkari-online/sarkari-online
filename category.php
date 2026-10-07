<?php
/**
 * EduPulse - Category Archive Page (Phase 1)
 * Retrieves category records and paginated articles from MySQL via CategoryService & ArticleService.
 */
require_once __DIR__ . '/config.php';
use App\Services\CategoryService;
use App\Services\ArticleService;
use App\Services\CrawlEfficiencyService;

// Universal Category Retirement: 301 Redirect all /category/* URLs to Full Forms directory
header("Location: " . url('full-forms/'), true, 301);
exit;

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$categoryData = ArticleService::getByCategory($slug, $currentPage, 16);

$articles = $categoryData['items'];
$totalPages = $categoryData['total_pages'];

// ── HTTP Crawl Efficiency & Cache Validation Headers (Googlebot 304 & ETag) ──
$latestArticleMod = !empty($articles[0]['updated_at']) 
    ? $articles[0]['updated_at'] 
    : (!empty($articles[0]['published_at']) ? $articles[0]['published_at'] : 'now');
CrawlEfficiencyService::handleConditionalGet('cat-' . $slug . '-p' . $currentPage, $latestArticleMod);

// SEO Setup
$pageSuffix = ($currentPage > 1) ? " (Page {$currentPage})" : "";
$pageTitle = $category['name'] . " Updates, Notifications & Direct Links{$pageSuffix}";
$pageDesc = $category['description'] ?? 'Verified updates and official notifications.';
$canonicalUrl = url('category/' . $slug . '/');
if ($currentPage > 1) {
    $metaRobots = 'noindex, follow';
}
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Categories', 'url' => ''],
    ['label' => $category['name'], 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <!-- Breadcrumbs -->
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <!-- Category Header (Executive Redesign) -->
        <header class="category-hero-card" style="background: #ffffff; border: 1px solid #e0e0e0; border-left: 5px solid #f57c00; border-radius: 14px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem;">
                <span style="font-size: 0.72rem; font-weight: 700; color: #ffffff; background: #1a237e; padding: 4px 12px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= e($category['name']) ?> Archive
                </span>
                <span style="font-size: 0.75rem; color: #546e7a; font-weight: 600;">
                    <?= e((string)$categoryData['total']) ?> Verified Updates Published
                </span>
            </div>
            <h1 style="font-size: 2.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0; line-height: 1.25; letter-spacing: -0.02em; display: flex; align-items: center; gap: 0.5rem;">
                <?= icon($category['icon'] ?? 'award', 'icon-lg', ['style' => 'color: #1a237e;']) ?>
                <span><?= e($category['name']) ?></span>
            </h1>
            <p style="font-size: 1rem; color: #546e7a; line-height: 1.6; margin: 0; max-width: 860px;">
                <?= e($category['description']) ?>
            </p>
        </header>

        <!-- Main Content + Sidebar Grid -->
        <div class="article-layout-grid">
            
            <!-- Left: Article Grid & Pagination -->
            <div>
                <?php if (!empty($articles)): ?>
                    <div class="grid-2">
                        <?php foreach ($articles as $article): 
                            $article['category'] = $slug;
                            $article['category_name'] = $category['name'];
                            $article['category_color'] = $category['color'] ?? '#1e3a8a';
                        ?>
                            <?php include __DIR__ . '/components/article-card.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrapper" aria-label="Category Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="<?= url('category/' . $slug . '/?page=' . ($currentPage - 1)) ?>" class="page-btn">
                                    <?= icon('chevron-left', 'icon-sm') ?> Prev
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="<?= url('category/' . $slug . '/?page=' . $i) ?>" class="page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?= url('category/' . $slug . '/?page=' . ($currentPage + 1)) ?>" class="page-btn">
                                    Next <?= icon('chevron-right', 'icon-sm') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="search-empty-state">
                        <div class="empty-icon"><?= icon('info', 'icon-lg') ?></div>
                        <h3>No updates in this section yet</h3>
                        <p style="color: var(--text-muted);">Check back soon as our education desk publishes verified notices.</p>
                        <a href="<?= url() ?>" class="btn btn-primary" style="margin-top: 1rem;">Back to Home</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Sidebar -->
            <?php include __DIR__ . '/components/sidebar.php'; ?>

        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
