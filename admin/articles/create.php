<?php
/**
 * EduPulse - Admin Create Article (Phase 2)
 * Full editorial CMS editor with SEO metadata, Open Graph settings, and draft/publish controls.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;

$adminPageKey = 'article_create';
$adminPageTitle = 'Create New Article';

$categories = CategoryService::getAll();
$authors = Database::fetchAll("SELECT id, username, email FROM users WHERE status = 'active' ORDER BY username ASC");
$currentUser = Auth::user();

$errors = [];
$formData = [
    'title' => '',
    'slug' => '',
    'category_id' => '',
    'author_id' => $currentUser['id'] ?? '',
    'excerpt' => '',
    'content' => '',
    'status' => 'draft',
    'quality_score' => 90,
    'source_verified' => 1,
    'featured_image' => '',
    'featured_image_alt' => '',
    'meta_title' => '',
    'meta_description' => '',
    'canonical_url' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'source_name' => '',
    'source_url' => '',
    'source_ref' => ''
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateRequest()) {
        $errors['csrf'] = 'Security session expired. Please refresh and try again.';
    } else {
        $formData = array_merge($formData, $_POST);

        // Check if Save Draft or Publish was clicked
        if (isset($_POST['save_draft'])) {
            $formData['status'] = 'draft';
        } elseif (isset($_POST['publish_now'])) {
            $formData['status'] = 'published';
        }

        $validator = new Validator($_POST);
        $validator->required('title', 'Article Headline')
                  ->minLength('title', 10, 'Article Headline')
                  ->required('category_id', 'Category')
                  ->required('content', 'Article Content')
                  ->minLength('content', 20, 'Article Content');

        if ($validator->passes()) {
            $newId = ArticleService::create($formData);
            header("Location: " . url('admin/articles/edit.php?id=' . $newId . '&created=1'));
            exit;
        } else {
            $errors = $validator->errors();
        }
    }
}

include dirname(__DIR__) . '/components/header.php';
?>

<div style="max-width: 960px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">Create New Article</h2>
        <a href="<?= url('admin/articles/') ?>" class="btn btn-outline">← Back to Articles</a>
    </div>

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

    <form action="<?= url('admin/articles/create.php') ?>" method="POST" class="admin-table-box" style="padding: 2rem;">
        <?= CSRF::field() ?>

        <!-- Headline -->
        <div class="form-group">
            <label for="art-title" class="form-label">Headline / Article Title *</label>
            <input type="text" id="art-title" name="title" value="<?= e($formData['title']) ?>" class="form-input" placeholder="e.g. NEET UG 2026 Round 1 AIQ Counselling Dates Declared" required>
        </div>

        <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="art-slug" class="form-label">URL Slug (Auto-generated if blank)</label>
                <input type="text" id="art-slug" name="slug" value="<?= e($formData['slug']) ?>" class="form-input" placeholder="e.g. neet-ug-2026-counselling-dates">
            </div>
            <div class="form-group">
                <label for="art-cat" class="form-label">Category *</label>
                <select id="art-cat" name="category_id" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (string)$formData['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="art-author" class="form-label">Author</label>
                <select id="art-author" name="author_id" class="form-select">
                    <?php foreach ($authors as $auth): ?>
                        <option value="<?= $auth['id'] ?>" <?= (string)$formData['author_id'] === (string)$auth['id'] ? 'selected' : '' ?>>
                            <?= e($auth['username']) ?> (<?= e($auth['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Excerpt -->
        <div class="form-group">
            <label for="art-excerpt" class="form-label">Short Excerpt / Lead Summary</label>
            <textarea id="art-excerpt" name="excerpt" class="form-textarea" style="min-height: 75px;" placeholder="Brief summary displayed in hero cards, search results, and social embeds..."><?= e($formData['excerpt']) ?></textarea>
        </div>

        <!-- Body Content -->
        <div class="form-group">
            <label for="art-content" class="form-label">Article Body (HTML / Semantic Text) *</label>
            <textarea id="art-content" name="content" class="form-textarea" style="min-height: 280px; font-family: monospace; font-size: 0.9rem;" placeholder="<h2>Key Examination Schedule</h2><p>The official examination bulletin specifies...</p>" required><?= e($formData['content']) ?></textarea>
        </div>

        <!-- Featured Media -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Featured Media</h3>
        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="art-img" class="form-label">Featured Image URL / Path (Leave blank to use branded SVG template)</label>
                <input type="text" id="art-img" name="featured_image" value="<?= e($formData['featured_image']) ?>" class="form-input" placeholder="e.g. uploads/thumbnails/exam-results/neet.webp">
            </div>
            <div class="form-group">
                <label for="art-img-alt" class="form-label">Image Alt Text (SEO)</label>
                <input type="text" id="art-img-alt" name="featured_image_alt" value="<?= e($formData['featured_image_alt']) ?>" class="form-input" placeholder="e.g. Official NEET Counselling Schedule Notice">
            </div>
        </div>

        <!-- Official Sourcing -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Official Sourcing &amp; Verification</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="source-name" class="form-label">Source Authority Name</label>
                <input type="text" id="source-name" name="source_name" value="<?= e($formData['source_name']) ?>" class="form-input" placeholder="e.g. Medical Counselling Committee (MCC)">
            </div>
            <div class="form-group">
                <label for="source-url" class="form-label">Official Portal URL</label>
                <input type="url" id="source-url" name="source_url" value="<?= e($formData['source_url']) ?>" class="form-input" placeholder="https://mcc.nic.in">
            </div>
            <div class="form-group">
                <label for="source-ref" class="form-label">Official Notice Reference</label>
                <input type="text" id="source-ref" name="source_ref" value="<?= e($formData['source_ref']) ?>" class="form-input" placeholder="e.g. Ref No. U-12021/01/2026-MEC">
            </div>
        </div>

        <!-- SEO Metadata -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Search Engine Optimization (SEO) &amp; Open Graph</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="meta-title" class="form-label">Meta Title (≤60 chars)</label>
                <input type="text" id="meta-title" name="meta_title" value="<?= e($formData['meta_title']) ?>" class="form-input" placeholder="Defaults to Article Title">
            </div>
            <div class="form-group">
                <label for="canonical-url" class="form-label">Custom Canonical URL (Leave blank for default)</label>
                <input type="url" id="canonical-url" name="canonical_url" value="<?= e($formData['canonical_url']) ?>" class="form-input" placeholder="https://edupulse.in/article/...">
            </div>
        </div>

        <div class="form-group">
            <label for="meta-desc" class="form-label">Meta Description (≤155 chars)</label>
            <textarea id="meta-desc" name="meta_description" class="form-textarea" style="min-height: 60px;" placeholder="Defaults to Article Excerpt"><?= e($formData['meta_description']) ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="og-title" class="form-label">Open Graph (Facebook/Twitter) Title</label>
                <input type="text" id="og-title" name="og_title" value="<?= e($formData['og_title']) ?>" class="form-input" placeholder="Defaults to Meta Title">
            </div>
            <div class="form-group">
                <label for="og-img" class="form-label">Open Graph Image (1200x630)</label>
                <input type="text" id="og-img" name="og_image" value="<?= e($formData['og_image']) ?>" class="form-input" placeholder="Defaults to Featured Image">
            </div>
        </div>

        <!-- Publishing Controls -->
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 1.75rem 0 1rem 0; padding-top: 1rem; border-top: 1px solid var(--border-color);">Publishing Status &amp; Quality</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; align-items: center;">
            <div class="form-group">
                <label for="art-status" class="form-label">Status</label>
                <select id="art-status" name="status" class="form-select">
                    <option value="draft" <?= $formData['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="review" <?= $formData['status'] === 'review' ? 'selected' : '' ?>>Review Queue</option>
                    <option value="published" <?= $formData['status'] === 'published' ? 'selected' : '' ?>>Published Live</option>
                    <option value="rejected" <?= $formData['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label for="art-score" class="form-label">Quality Score (0–100)</label>
                <input type="number" id="art-score" name="quality_score" value="<?= (int)$formData['quality_score'] ?>" min="0" max="100" class="form-input">
            </div>
            <div class="form-group">
                <label for="art-ver" class="form-label">Source Verification</label>
                <select id="art-ver" name="source_verified" class="form-select">
                    <option value="1" <?= (int)$formData['source_verified'] === 1 ? 'selected' : '' ?>>Verified Primary Source</option>
                    <option value="0" <?= (int)$formData['source_verified'] === 0 ? 'selected' : '' ?>>Unverified</option>
                </select>
            </div>
        </div>

        <!-- Form Submission Actions -->
        <div style="margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= url('admin/articles/') ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" name="save_draft" value="1" class="btn btn-outline" style="border-color: var(--text-muted);">
                Save as Draft
            </button>
            <button type="submit" name="publish_now" value="1" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                Save &amp; Publish Live
            </button>
        </div>
    </form>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
