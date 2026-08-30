<?php
/**
 * Sarkari.online - Editorial Quality & Verification Console
 * Autonomous 100% Automated Publishing Engine Monitor
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Services\ArticleService;
use App\Services\PublishingService;

Auth::requireAuth();

$adminPageTitle = 'Editorial & Verification Console';
$adminPageKey = 'review';
$message = null;
$messageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $articleId = (int)($_POST['article_id'] ?? 0);
        if ($articleId > 0) {
            if ($action === 'approve_publish') {
                $pubService = new PublishingService();
                $res = $pubService->publish($articleId);
                if (!empty($res['success'])) {
                    $message = "Article #{$articleId} published live successfully!";
                } else {
                    $reasons = implode('; ', $res['reasons'] ?? ['Gatekeeper check failed']);
                    $message = "Cannot publish Article #{$articleId}: {$reasons}";
                    $messageType = 'warning';
                }
            } elseif ($action === 'reject') {
                Database::query("DELETE FROM article_checks WHERE article_id = :id", ['id' => $articleId]);
                Database::query("DELETE FROM articles WHERE id = :id", ['id' => $articleId]);
                $message = "Article #{$articleId} deleted from database.";
                $messageType = 'danger';
            }
        }
    }
}

$sql = "SELECT a.*, c.name AS category_name
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'review'
        ORDER BY a.quality_score DESC, a.id DESC";
$reviewArticles = Database::fetchAll($sql);
$total = count($reviewArticles);

$publishedCount = Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");

include dirname(__DIR__) . '/components/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 style="font-size:1.35rem; font-weight:800; margin:0 0 0.25rem; color:#0f172a;">Editorial &amp; Verification Console</h2>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            Autonomous AI Pipeline Status &amp; Quality Telemetry
        </p>
    </div>
    <div style="display:flex; gap:0.6rem; align-items:center;">
        <span style="background:#ecfdf5; color:#065f46; font-size:0.8125rem; padding:0.4rem 0.85rem; font-weight:700; border-radius:999px; border:1px solid #a7f3d0; display:inline-flex; align-items:center; gap:0.35rem;">
            <span style="width:8px; height:8px; background:#10b981; border-radius:50%; display:inline-block;"></span>
            Auto-Publishing Active
        </span>
        <span style="background:#f1f5f9; color:#475569; font-size:0.8125rem; padding:0.4rem 0.85rem; font-weight:700; border-radius:999px;">
            <?= (int)$publishedCount ?> Articles Live
        </span>
    </div>
</div>

<?php if (!empty($_GET['started'])): ?>
    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:0.95rem 1.25rem; margin-bottom:1.25rem; color:#065f46; font-size:0.875rem; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <strong>⚡ Article Generation Initiated!</strong>
            <div style="margin-top:0.25rem; color:#047857;">Verified facts are being gathered. The draft will appear below shortly and will automatically publish live within ~3-5 minutes once verified.</div>
        </div>
        <a href="<?= url('admin/review/') ?>" class="btn btn-sm btn-outline" style="border-color:#10b981; color:#065f46; background:#fff;">Refresh</a>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div style="background:<?= $messageType === 'success' ? '#ecfdf5' : ($messageType === 'danger' ? '#fef2f2' : '#fff7ed') ?>; border:1px solid <?= $messageType === 'success' ? '#a7f3d0' : ($messageType === 'danger' ? '#fecaca' : '#fed7aa') ?>; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1.25rem; color:<?= $messageType === 'success' ? '#065f46' : ($messageType === 'danger' ? '#991b1b' : '#92400e') ?>; font-weight:600; font-size:0.875rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<?php if (empty($reviewArticles)): ?>
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:3.5rem 2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="width:64px; height:64px; background:#ecfdf5; border:1.5px solid #a7f3d0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; color:#059669; font-size:1.75rem;">
            ✓
        </div>
        <h3 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0 0 0.4rem;">Pipeline is Running Autonomously</h3>
        <p style="color:#64748b; font-size:0.9rem; margin:0 auto 1.5rem; max-width:480px; line-height:1.5;">
            All generated articles are automatically source-verified by the AI Engine and published live without manual intervention.
        </p>
        <a href="<?= url('admin/articles/index.php') ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 1.25rem; font-size:0.875rem;">
            <?= icon('file-text', 'icon-sm') ?> View All Live Articles (<?= (int)$publishedCount ?>)
        </a>
    </div>
<?php else: ?>
    <div class="admin-table-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="overflow-x:auto;">
            <table class="table" style="margin:0; width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <th style="padding:0.85rem 1rem; font-size:0.8125rem; font-weight:700; color:#475569; text-transform:uppercase;">Article</th>
                        <th style="padding:0.85rem 1rem; font-size:0.8125rem; font-weight:700; color:#475569; text-transform:uppercase;">Category</th>
                        <th style="padding:0.85rem 1rem; font-size:0.8125rem; font-weight:700; color:#475569; text-transform:uppercase;">Score</th>
                        <th style="padding:0.85rem 1rem; font-size:0.8125rem; font-weight:700; color:#475569; text-transform:uppercase;">Source</th>
                        <th style="padding:0.85rem 1rem; font-size:0.8125rem; font-weight:700; color:#475569; text-transform:uppercase; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviewArticles as $art): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:1rem;">
                                <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" style="font-weight:700; color:#0f172a; font-size:0.925rem; text-decoration:none;">
                                    <?= e($art['title']) ?>
                                </a>
                                <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">
                                    ID #<?= $art['id'] ?> · Created <?= date('M d, H:i', strtotime($art['created_at'])) ?>
                                </div>
                            </td>
                            <td style="padding:1rem; vertical-align:middle;">
                                <span class="badge badge-secondary" style="font-size:0.75rem;">
                                    <?= e($art['category_name']) ?>
                                </span>
                            </td>
                            <td style="padding:1rem; vertical-align:middle;">
                                <span style="font-weight:800; color:<?= $art['quality_score'] >= 90 ? '#16a34a' : '#d97706' ?>; font-size:0.95rem;">
                                    <?= $art['quality_score'] ?>/100
                                </span>
                            </td>
                            <td style="padding:1rem; vertical-align:middle; font-size:0.8125rem; color:#475569;">
                                <?= e($art['source_name'] ?: 'Official Portal') ?>
                            </td>
                            <td style="padding:1rem; vertical-align:middle; text-align:right;">
                                <div style="display:inline-flex; gap:0.35rem; align-items:center;">
                                    <a href="<?= url('article/' . $art['slug'] . '/?preview=1') ?>" target="_blank" class="btn btn-xs btn-outline">
                                        Preview
                                    </a>
                                    <form method="POST" style="margin:0; display:inline;">
                                        <?= CSRF::input() ?>
                                        <input type="hidden" name="article_id" value="<?= $art['id'] ?>">
                                        <button type="submit" name="action" value="approve_publish" class="btn btn-xs btn-primary">
                                            Publish
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-xs btn-outline" style="color:#dc2626; border-color:#fecaca;" onclick="return confirm('Delete this article?');">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
