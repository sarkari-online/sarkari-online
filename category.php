<?php
/**
 * EduPulse - Category Archive Page (Phase 1)
 * Retrieves category records and paginated articles from MySQL via CategoryService & ArticleService.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Data/MockData.php';

use App\Services\CategoryService;
use App\Services\ArticleService;

$slug = $_GET['slug'] ?? 'exam-results';
$slug = trim($slug, '/');

// Fetch Category from DB or Config fallback
$category = CategoryService::getBySlug($slug) ?: get_category($slug);

if (!$category) {
    $category = get_category('exam-results');
    $slug = 'exam-results';
}

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$categoryData = ArticleService::getByCategory($slug, $currentPage, 6);

// Fallback to MockData if database category has 0 articles
if (empty($categoryData['items'])) {
    $categoryData = MockData::getCategoryArticles($slug, $currentPage, 6);
}

$articles = $categoryData['items'];
$totalPages = $categoryData['total_pages'];

// SEO Setup
$pageTitle = $category['name'] . ' Updates, Notifications & Direct Links';
$pageDesc = $category['description'] ?? 'Verified updates and official notifications.';
$canonicalUrl = url('category/' . $slug . '/');
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

        <!-- Category Header -->
        <header class="category-page-header">
            <div class="card-meta" style="margin-bottom: 0.5rem;">
                <span class="badge" style="background-color: <?= e($category['color'] ?? '#1e3a8a') ?>15; color: <?= e($category['color'] ?? '#1e3a8a') ?>; font-size: 0.75rem;">
                    <?= e($category['name']) ?> Archive
                </span>
                <span class="meta-dot"></span>
                <span><?= e((string)$categoryData['total']) ?> Verified Updates Published</span>
            </div>
            <h1 class="category-page-title" style="color: <?= e($category['color'] ?? '#1e3a8a') ?>;">
                <?= icon($category['icon'] ?? 'award') ?>
                <span><?= e($category['name']) ?></span>
            </h1>
            <p class="category-page-desc">
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
