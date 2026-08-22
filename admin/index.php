<?php
/**
 * EduPulse - Production Admin Dashboard (Phase 9)
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Services\ArticleService;
use App\Services\TrendService;

Auth::requireAuth();

$adminPageTitle = 'Operational Intelligence Dashboard';
$adminPageKey = 'dashboard';

// 1. Calculate Dashboard Metrics
$totalArticles = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles");
$publishedArticles = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");
$draftArticles = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'draft'");
$reviewArticles = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'review'");
$rejectedArticles = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'rejected'");

$trendsToday = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE DATE(detected_at) = CURRENT_DATE");
$articlesToday = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE DATE(published_at) = CURRENT_DATE");
$avgQualityScore = round((float)Database::fetchColumn("SELECT AVG(quality_score) FROM articles WHERE quality_score > 0"), 1);
$geminiFailures = (int)Database::fetchColumn("SELECT COUNT(*) FROM ai_logs WHERE success = 0");

// 2. Fetch Recent Detected Trends
$recentTrends = Database::fetchAll("SELECT * FROM trends ORDER BY id DESC LIMIT 8");

// 3. Fetch Recent Review Queue Articles
$reviewQueue = Database::fetchAll(
    "SELECT a.*, c.name AS category_name, c.color AS category_color 
     FROM articles a 
     JOIN categories c ON a.category_id = c.id 
     WHERE a.status = 'review' 
     ORDER BY a.quality_score DESC, a.id DESC LIMIT 5"
);

// 4. Fetch Recent Articles
$recentArticles = Database::fetchAll(
    "SELECT a.*, c.name AS category_name, c.color AS category_color 
     FROM articles a 
     JOIN categories c ON a.category_id = c.id 
     ORDER BY a.id DESC LIMIT 6"
);

include __DIR__ . '/components/header.php';
?>

<!-- Metric Summary Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card">
        <span class="stat-card-label">Total Articles</span>
        <span class="stat-card-num"><?= number_format($totalArticles) ?></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid #16a34a;">
        <span class="stat-card-label" style="color: #16a34a;">Published Live</span>
        <span class="stat-card-num" style="color: #16a34a;"><?= number_format($publishedArticles) ?></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid #f59e0b;">
        <span class="stat-card-label" style="color: #d97706;">Review Queue</span>
        <span class="stat-card-num" style="color: #d97706;"><?= number_format($reviewArticles) ?></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid #64748b;">
        <span class="stat-card-label">Drafts</span>
        <span class="stat-card-num"><?= number_format($draftArticles) ?></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid #dc2626;">
        <span class="stat-card-label" style="color: #dc2626;">Rejected</span>
        <span class="stat-card-num" style="color: #dc2626;"><?= number_format($rejectedArticles) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Trends Today</span>
        <span class="stat-card-num"><?= number_format($trendsToday) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Articles Today</span>
        <span class="stat-card-num"><?= number_format($articlesToday) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Avg Quality Score</span>
        <span class="stat-card-num" style="color: #2563eb;"><?= $avgQualityScore ?: 'N/A' ?><small style="font-size: 0.9rem; color: #64748b;">/100</small></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid <?= $geminiFailures > 0 ? '#ef4444' : '#22c55e' ?>;">
        <span class="stat-card-label" style="color: <?= $geminiFailures > 0 ? '#dc2626' : '#16a34a' ?>;">AI Failures</span>
        <span class="stat-card-num" style="color: <?= $geminiFailures > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($geminiFailures) ?></span>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Review Queue Section -->
    <div class="admin-table-box">
        <div class="admin-table-header">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <?= icon('shield-check', 'icon-sm') ?> Pending Editorial Review
            </h3>
            <a href="<?= url('admin/review/') ?>" class="btn btn-sm btn-outline">View All (<?= $reviewArticles ?>)</a>
        </div>
        <table class="table" style="margin: 0;">
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Score</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviewQueue)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No articles currently pending review.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviewQueue as $rq): ?>
                        <tr>
                            <td>
                                <a href="<?= url('admin/articles/edit.php?id=' . $rq['id']) ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                    <?= e(mb_substr($rq['title'], 0, 48)) ?>...
                                </a>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Category: <?= e($rq['category_name']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $rq['quality_score'] >= 90 ? '#dcfce7; color: #166534;' : '#fef3c7; color: #92400e;' ?>">
                                    <?= $rq['quality_score'] ?>/100
                                </span>
                            </td>
                            <td>
                                <a href="<?= url('admin/review/') ?>" class="btn btn-xs btn-primary">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Active Trends Section -->
    <div class="admin-table-box">
        <div class="admin-table-header">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <?= icon('trending-up', 'icon-sm') ?> Emerging Topic Trends
            </h3>
            <a href="<?= url('admin/trends/') ?>" class="btn btn-sm btn-outline">View Trends</a>
        </div>
        <table class="table" style="margin: 0;">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Source</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTrends)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No trends detected yet. Run trend worker.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTrends as $t): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 600;"><?= e(mb_substr($t['keyword'], 0, 36)) ?></span>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= date('M d, H:i', strtotime($t['detected_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem;"><?= e($t['source']) ?></span>
                            </td>
                            <td>
                                <?php
                                $statusStyles = [
                                    'detected' => 'background: #f1f5f9; color: #475569;',
                                    'analyzing' => 'background: #e0f2fe; color: #0369a1;',
                                    'approved' => 'background: #dcfce7; color: #166534;',
                                    'rejected' => 'background: #fee2e2; color: #991b1b;',
                                    'generated' => 'background: #ede9fe; color: #5b21b6;',
                                    'published' => 'background: #dbeafe; color: #1e40af;',
                                    'failed' => 'background: #fef2f2; color: #b91c1c;'
                                ];
                                $style = $statusStyles[$t['status']] ?? 'background: #f1f5f9; color: #475569;';
                                ?>
                                <span class="badge" style="<?= $style ?> font-size: 0.75rem;">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Articles List -->
<div class="admin-table-box">
    <div class="admin-table-header">
        <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <?= icon('file-text', 'icon-sm') ?> Recently Created Articles
        </h3>
        <a href="<?= url('admin/articles/') ?>" class="btn btn-sm btn-outline">All Articles</a>
    </div>
    <table class="table" style="margin: 0;">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Quality Score</th>
                <th>Published / Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentArticles as $art): ?>
                <tr>
                    <td>
                        <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                            <?= e($art['title']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge" style="background: <?= e($art['category_color'] ?? '#2563eb') ?>15; color: <?= e($art['category_color'] ?? '#2563eb') ?>;">
                            <?= e($art['category_name']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($art['status'] === 'published'): ?>
                            <span class="badge badge-success">Published</span>
                        <?php elseif ($art['status'] === 'review'): ?>
                            <span class="badge badge-warning">Review</span>
                        <?php elseif ($art['status'] === 'rejected'): ?>
                            <span class="badge badge-danger">Rejected</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= $art['quality_score'] ?></strong>/100
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                        <?= !empty($art['published_at']) ? date('M d, Y H:i', strtotime($art['published_at'])) : date('M d, Y H:i', strtotime($art['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.4rem;">
                            <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" class="btn btn-xs btn-outline">Edit</a>
                            <?php if ($art['status'] === 'published'): ?>
                                <a href="<?= url('article/' . $art['slug'] . '/') ?>" target="_blank" class="btn btn-xs btn-secondary">View</a>
                            <?php else: ?>
                                <a href="<?= url('article/' . $art['slug'] . '/?preview=1') ?>" target="_blank" class="btn btn-xs btn-secondary">Preview</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
