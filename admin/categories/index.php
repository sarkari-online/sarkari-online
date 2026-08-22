<?php
/**
 * EduPulse - Admin Category Management
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Services\CategoryService;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;

$adminPageKey = 'categories';
$adminPageTitle = 'Manage Categories';

$message = '';
$error = '';

// Handle Create / Update / Delete
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateRequest()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $validator = new Validator($_POST);
            $validator->required('name', 'Category Name');
            if ($validator->passes()) {
                CategoryService::create($_POST);
                $message = 'Category created successfully.';
            } else {
                $error = $validator->firstError();
            }
        } elseif ($action === 'update') {
            $catId = (int)($_POST['id'] ?? 0);
            $validator = new Validator($_POST);
            $validator->required('name', 'Category Name');
            if ($catId > 0 && $validator->passes()) {
                CategoryService::update($catId, $_POST);
                $message = 'Category updated successfully.';
            } else {
                $error = $validator->firstError() ?: 'Invalid category ID.';
            }
        } elseif ($action === 'delete') {
            $catId = (int)($_POST['id'] ?? 0);
            if ($catId > 0) {
                if (CategoryService::delete($catId)) {
                    $message = 'Category deleted successfully.';
                } else {
                    $error = 'Cannot delete category because articles are assigned to it.';
                }
            }
        }
    }
}

$categories = CategoryService::getAll();

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

<div class="grid-2">
    <!-- Categories List -->
    <div class="admin-table-box">
        <div class="admin-table-header">
            <h3 style="font-size: 1.05rem; font-weight: 800;">Taxonomy Categories (<?= count($categories) ?>)</h3>
        </div>
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Slug</th>
                        <th>Sort</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: <?= e($cat['color']) ?>;"></span>
                                    <strong><?= e($cat['name']) ?></strong>
                                </div>
                            </td>
                            <td style="font-family: monospace; font-size: 0.8125rem; color: var(--text-muted);">
                                <?= e($cat['slug']) ?>
                            </td>
                            <td><?= (int)$cat['sort_order'] ?></td>
                            <td style="text-align: right;">
                                <form action="<?= url('admin/categories/') ?>" method="POST" onsubmit="return confirm('Delete this category? Only empty categories can be deleted.');" style="display: inline;">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-danger); padding: 0.2rem 0.5rem;" title="Delete">
                                        <?= icon('trash', 'icon-sm') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create New Category Form -->
    <div class="admin-table-box" style="padding: 1.5rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.25rem;">Add New Category</h3>
        
        <form action="<?= url('admin/categories/') ?>" method="POST">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="cat-name" class="form-label">Category Name *</label>
                <input type="text" id="cat-name" name="name" class="form-input" placeholder="e.g. Higher Education Alerts" required>
            </div>

            <div class="form-group">
                <label for="cat-slug" class="form-label">Slug (Optional)</label>
                <input type="text" id="cat-slug" name="slug" class="form-input" placeholder="e.g. higher-education-alerts">
            </div>

            <div class="form-group">
                <label for="cat-desc" class="form-label">Description</label>
                <textarea id="cat-desc" name="description" class="form-textarea" style="min-height: 80px;" placeholder="Brief archive description for SEO..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="cat-color" class="form-label">Theme Color</label>
                    <input type="color" id="cat-color" name="color" value="#1e3a8a" class="form-input" style="height: 42px; padding: 2px;">
                </div>
                <div class="form-group">
                    <label for="cat-sort" class="form-label">Sort Order</label>
                    <input type="number" id="cat-sort" name="sort_order" value="10" class="form-input">
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Create Category
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
