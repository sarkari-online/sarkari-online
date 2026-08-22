<?php
/**
 * EduPulse - Admin Edit Article (Phase 2)
 * Full editorial CMS editor with SEO metadata, Open Graph settings, live preview, and unpublish controls.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Database\Database;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;

$adminPageKey = 'articles';
$adminPageTitle = 'Edit Article';

$id = (int)($_GET['id'] ?? 0);
$article = ArticleService::getById($id);

if (!$article) {
    header("Location: " . url('admin/articles/'));
    exit;
}

$categories = CategoryService::getAll();
$authors = Database::fetchAll("SELECT id, username, email FROM users WHERE status = 'active' ORDER BY username ASC");
$errors = [];
$success = !empty($_GET['created']) ? 'Article created successfully!' : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateRequest()) {
        $errors['csrf'] = 'Security session expired. Please refresh and try again.';
    } else {
        $postData = $_POST;

        // Check if Save Draft or Publish was clicked
        if (isset($_POST['save_draft'])) {
            $postData['status'] = 'draft';
        } elseif (isset($_POST['publish_now'])) {
            $postData['status'] = 'published';
        }

        $validator = new Validator($_POST);
        $validator->required('title', 'Article Headline')
                  ->minLength('title', 10, 'Article Headline')
                  ->required('category_id', 'Category')
                  ->required('content', 'Article Content')
                  ->minLength('content', 20, 'Article Content');

        if ($validator->passes()) {
            $updateSuccess = ArticleService::update($id, $postData);
            if ($updateSuccess) {
                $success = 'Article updated successfully!';
                $article = ArticleService::getById($id);
            } else {
                $errors['general'] = 'Failed to update article in database.';
            }
        } else {
            $errors = $validator->errors();
        }
    }
}

include dirname(__DIR__) . '/components/header.php';
?>

<div style="max-width: 960px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">
                Edit Article #<?= $id ?>
            </h2>
            <span class="badge" style="font-size: 0.75rem; text-transform: uppercase;">
                Current Status: <?= e($article['status']) ?>
            </span>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <!-- Live / Preview Link -->
            <a href="<?= url('article/' . $article['slug'] . '/?preview=1') ?>" target="_blank" class="btn btn-outline" style="color: #6366f1;">
                <?= icon('external-link', 'icon-sm') ?> Preview Article
            </a>
            <a href="<?= url('admin/articles/') ?>" class="btn btn-outline">← Back to Articles</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="info-callout" style="background-color: var(--color-success-light); border-left-color: var(--color-success); color: var(--color-success); margin-bottom: 1.5rem;">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="info-callout" style="background-color: var(--color-danger-light); border-left-color: var(--color-danger); color: var(--color-danger); margin-bottom: 1.5rem;">
            <div>
                <strong>Please correct the following errors:</strong>
                <ul style="margin-top: 0.5rem; padding-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= url('admin/articles/edit.php?id=' . $id) ?>" method="POST" class="admin-table-box" style="padding: 2rem;">
        <?= CSRF::field() ?>

        <!-- Headline -->
        <div class="form-group">
            <label for="art-title" class="form-label">Headline / Article Title *</label>
            <input type="text" id="art-title" name="title" value="<?= e($article['title']) ?>" class="form-input" required>
        </div>

        <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="art-slug" class="form-label">URL Slug</label>
                <input type="text" id="art-slug" name="slug" value="<?= e($article['slug']) ?>" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="art-cat" class="form-label">Category *</label>
                <select id="art-cat" name="category_id" class="form-select" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (int)$article['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="art-author" class="form-label">Author</label>
                <select id="art-author" name="author_id" class="form-select">
                    <option value="">None / Editorial Desk</option>
                    <?php foreach ($authors as $auth): ?>
                        <option value="<?= $auth['id'] ?>" <?= (int)$article['author_id'] === (int)$auth['id'] ? 'selected' : '' ?>>
                            <?= e($auth['username']) ?> (<?= e($auth['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Excerpt -->
        <div class="form-group">
            <label for="art-excerpt" class="form-label">Short Excerpt / Lead Summary</label>
            <textarea id="art-excerpt" name="excerpt" class="form-textarea" style="min-height: 75px;"><?= e($article['excerpt']) ?></textarea>
        </div>

        <!-- Body Content -->
        <div class="form-group">
            <label for="art-content" class="form-label">Article Body (HTML / Semantic Text) *</label>
            <textarea id="art-content" name="content" class="form-textarea" style="min-height: 280px; font-family: monospace; font-size: 0.9rem;" required><?= e($article['content']) ?></textarea>
        </div>

        <!-- Featured Media -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Featured Media</h3>
        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="art-img" class="form-label">Featured Image URL / Path</label>
                <input type="text" id="art-img" name="featured_image" value="<?= e($article['featured_image']) ?>" class="form-input" placeholder="e.g. uploads/thumbnails/exam-results/neet.webp">
            </div>
            <div class="form-group">
                <label for="art-img-alt" class="form-label">Image Alt Text (SEO)</label>
                <input type="text" id="art-img-alt" name="featured_image_alt" value="<?= e($article['featured_image_alt']) ?>" class="form-input" placeholder="e.g. NEET Official Notice">
            </div>
        </div>

        <!-- Official Sourcing -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Official Sourcing &amp; Verification</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="source-name" class="form-label">Source Authority Name</label>
                <input type="text" id="source-name" name="source_name" value="<?= e($article['source_name']) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="source-url" class="form-label">Official Portal URL</label>
                <input type="url" id="source-url" name="source_url" value="<?= e($article['source_url']) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="source-ref" class="form-label">Official Notice Reference</label>
                <input type="text" id="source-ref" name="source_ref" value="<?= e($article['source_ref']) ?>" class="form-input">
            </div>
        </div>

        <!-- SEO Metadata -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Search Engine Optimization (SEO) &amp; Open Graph</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="meta-title" class="form-label">Meta Title (≤60 chars)</label>
                <input type="text" id="meta-title" name="meta_title" value="<?= e($article['meta_title']) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="canonical-url" class="form-label">Custom Canonical URL (Leave blank for default)</label>
                <input type="url" id="canonical-url" name="canonical_url" value="<?= e($article['canonical_url']) ?>" class="form-input">
            </div>
        </div>

        <div class="form-group">
            <label for="meta-desc" class="form-label">Meta Description (≤155 chars)</label>
            <textarea id="meta-desc" name="meta_description" class="form-textarea" style="min-height: 60px;"><?= e($article['meta_description']) ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="og-title" class="form-label">Open Graph (Facebook/Twitter) Title</label>
                <input type="text" id="og-title" name="og_title" value="<?= e($article['og_title']) ?>" class="form-input">
            </div>
            <div class="form-group">
                <label for="og-img" class="form-label">Open Graph Image URL</label>
                <input type="text" id="og-img" name="og_image" value="<?= e($article['og_image']) ?>" class="form-input">
            </div>
        </div>

        <!-- Publishing Status -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Publishing Status &amp; Quality</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="art-status" class="form-label">Status</label>
                <select id="art-status" name="status" class="form-select">
                    <option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="review" <?= $article['status'] === 'review' ? 'selected' : '' ?>>Review Queue</option>
                    <option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>Published Live</option>
                    <option value="rejected" <?= $article['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label for="art-score" class="form-label">Quality Score (0–100)</label>
                <input type="number" id="art-score" name="quality_score" value="<?= (int)$article['quality_score'] ?>" min="0" max="100" class="form-input">
            </div>
            <div class="form-group">
                <label for="art-ver" class="form-label">Source Verification</label>
                <select id="art-ver" name="source_verified" class="form-select">
                    <option value="1" <?= (int)$article['source_verified'] === 1 ? 'selected' : '' ?>>Verified Primary Source</option>
                    <option value="0" <?= (int)$article['source_verified'] === 0 ? 'selected' : '' ?>>Unverified</option>
                </select>
            </div>
        </div>

        <!-- Timestamps Info -->
        <div style="font-size: 0.8125rem; color: var(--text-muted); padding: 0.75rem 0; border-top: 1px dashed var(--border-color);">
            Created: <strong><?= format_date($article['created_at'], true) ?></strong> &bull;
            Published: <strong><?= format_date($article['published_at'], true) ?></strong> &bull;
            Last Updated: <strong><?= format_date($article['updated_at'], true) ?></strong>
        </div>

        <!-- Submission Actions -->
        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= url('admin/articles/') ?>" class="btn btn-outline">Cancel</a>
            <?php if ($article['status'] === 'published'): ?>
                <button type="submit" name="save_draft" value="1" class="btn btn-outline" style="border-color: #b45309; color: #b45309;">
                    Unpublish to Draft
                </button>
            <?php else: ?>
                <button type="submit" name="save_draft" value="1" class="btn btn-outline">
                    Save Draft
                </button>
            <?php endif; ?>
            <button type="submit" name="publish_now" value="1" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                <?= $article['status'] === 'published' ? 'Save & Update Live' : 'Publish Live Now' ?>
            </button>
        </div>
    </form>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
