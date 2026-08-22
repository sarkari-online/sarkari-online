<?php
/**
 * EduPulse - Admin Article Management List (Phase 2)
 * Supports status filtering, category filtering, search, pagination, quick publish/unpublish, preview, and deletion.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;

$adminPageKey = 'articles';
$adminPageTitle = 'Manage Articles';

$message = '';
$error = '';

// Handle Status Toggle or Delete Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateRequest()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            if (ArticleService::delete($id)) {
                $message = "Article #{$id} deleted successfully.";
            } else {
                $error = 'Failed to delete article.';
            }
        } elseif ($action === 'publish' && $id > 0) {
            if (ArticleService::toggleStatus($id, 'published')) {
                $message = "Article #{$id} published live successfully.";
            }
        } elseif ($action === 'unpublish' && $id > 0) {
            if (ArticleService::toggleStatus($id, 'draft')) {
                $message = "Article #{$id} moved to drafts.";
            }
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$status = Sanitizer::string($_GET['status'] ?? null) ?: null;
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$search = Sanitizer::string($_GET['search'] ?? null) ?: null;

$categories = CategoryService::getAll();
$articleData = ArticleService::getAllAdmin($page, 15, $status, $categoryId, $search);
$articles = $articleData['items'];
$total = $articleData['total'];
$totalPages = $articleData['total_pages'];

include dirname(__DIR__) . '/components/header.php';
?>

<?php if (!empty($message)): ?>
    <div class="info-callout" style="background-color: var(--color-success-light); border-left-color: var(--color-success); color: var(--color-success); margin-bottom: 1.5rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="info-callout" style="background-color: var(--color-danger-light); border-left-color: var(--color-danger); color: var(--color-danger); margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Controls -->
<div class="admin-table-box" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <form action="<?= url('admin/articles/') ?>" method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; align-items: center;">
        <div>
            <input type="search" name="search" value="<?= e($search) ?>" class="form-input" placeholder="Search title or slug...">
        </div>
        <div>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="review" <?= $status === 'review' ? 'selected' : '' ?>>Review Queue</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div>
            <select name="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= url('admin/articles/') ?>" class="btn btn-outline">Reset</a>
        </div>
    </form>
</div>

<!-- Articles Table -->
<div class="admin-table-box">
    <div class="admin-table-header">
        <div style="font-weight: 700; color: var(--text-main);">
            Total Records: <strong><?= e((string)$total) ?></strong>
        </div>
        <a href="<?= url('admin/articles/create.php') ?>" class="btn btn-sm btn-primary">
            <?= icon('plus', 'icon-sm') ?> Create New Article
        </a>
    </div>

    <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Title &amp; URL Slug</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Dates</th>
                    <th style="text-align: right; width: 220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($articles)): ?>
                    <?php foreach ($articles as $art): ?>
                        <tr>
                            <td>#<?= (int)$art['id'] ?></td>
                            <td>
                                <strong>
                                    <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" style="color: var(--text-main);">
                                        <?= e(truncate_text($art['title'], 65)) ?>
                                    </a>
                                </strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                    /article/<?= e($art['slug']) ?>/
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= e($art['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($art['category_color'] ?? '#1e3a8a') ?>;">
                                    <?= e($art['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($art['status'] === 'published'): ?>
                                    <span class="badge badge-verified">Published</span>
                                <?php elseif ($art['status'] === 'review'): ?>
                                    <span class="badge" style="background: var(--color-warning-light); color: var(--color-warning);">Review</span>
                                <?php elseif ($art['status'] === 'draft'): ?>
                                    <span class="badge">Draft</span>
                                <?php else: ?>
                                    <span class="badge" style="background: var(--color-danger-light); color: var(--color-danger);">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: <?= $art['quality_score'] >= 90 ? 'var(--color-success)' : ($art['quality_score'] >= 80 ? 'var(--color-warning)' : 'var(--color-danger)') ?>;">
                                    <?= (int)$art['quality_score'] ?>
                                </span>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">
                                <div>Pub: <?= format_date($art['published_at'] ?? $art['created_at']) ?></div>
                                <?php if (!empty($art['updated_at'])): ?>
                                    <div>Upd: <?= time_ago($art['updated_at']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                    <!-- Edit -->
                                    <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.45rem;" title="Edit Article">
                                        <?= icon('edit', 'icon-sm') ?>
                                    </a>

                                    <!-- Preview (Works for draft & published) -->
                                    <a href="<?= url('article/' . $art['slug'] . '/?preview=1') ?>" target="_blank" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.45rem; color: #6366f1;" title="Preview Article">
                                        <?= icon('external-link', 'icon-sm') ?>
                                    </a>

                                    <!-- Quick Publish / Unpublish Toggle -->
                                    <form action="<?= url('admin/articles/') ?>" method="POST" style="display: inline;">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
                                        <?php if ($art['status'] === 'published'): ?>
                                            <input type="hidden" name="action" value="unpublish">
                                            <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.45rem; font-size: 0.75rem; color: #b45309;" title="Move to Drafts">
                                                Unpublish
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="publish">
                                            <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.45rem; font-size: 0.75rem; color: var(--color-success);" title="Publish Live">
                                                Publish
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Delete -->
                                    <form action="<?= url('admin/articles/') ?>" method="POST" onsubmit="return confirm('Delete article #<?= $art['id'] ?> (<?= e(addslashes($art['title'])) ?>)? This action cannot be undone.');" style="display: inline;">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.45rem; color: var(--color-danger);" title="Delete Permanently">
                                            <?= icon('trash', 'icon-sm') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No articles found. <a href="<?= url('admin/articles/create.php') ?>">Create a new article</a>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: center;">
            <div class="pagination-wrapper" style="margin-top: 0;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="<?= url('admin/articles/?page=' . $p . '&status=' . urlencode($status ?? '') . '&category_id=' . urlencode((string)($categoryId ?? '')) . '&search=' . urlencode($search ?? '')) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
