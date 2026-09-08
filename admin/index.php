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
use App\Services\AutoCronService;

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

// 5. Fetch Today's Publishing Slot Status & Top 5 Priority Articles
$slotSchedule = AutoCronService::getISTSlotSchedule();
$completedSlotsToday = AutoCronService::getCompletedSlotsTodayCount();

$todayPipelineArticles = Database::fetchAll(
    "SELECT a.id, a.title, a.slug, a.status, a.lifecycle_status, a.quality_score,
            a.source_name, a.published_at, a.updated_at,
            c.name AS category_name, c.color AS category_color,
            0 AS is_trend,
            CASE 
                WHEN DATE(a.published_at) = CURRENT_DATE AND a.status = 'published' THEN 1
                WHEN a.status = 'review' THEN 2
                WHEN a.status = 'draft' AND a.quality_score >= 70 THEN 3
                ELSE 4
            END AS pipeline_priority
     FROM articles a 
     JOIN categories c ON a.category_id = c.id 
     WHERE (DATE(a.published_at) = CURRENT_DATE AND a.status = 'published')
        OR (a.status = 'review')
        OR (a.status = 'draft' AND a.quality_score >= 70)
     ORDER BY pipeline_priority ASC, a.quality_score DESC, a.id DESC
     LIMIT 5"
);

// If fewer than 5 articles, fill remaining slots from top approved/detected trends of today
if (count($todayPipelineArticles) < 5) {
    $needed = 5 - count($todayPipelineArticles);
    $topTrends = Database::fetchAll(
        "SELECT t.id, t.keyword AS title, '' AS slug, t.status, 'upcoming' AS lifecycle_status, 
                t.trend_score AS quality_score, t.source AS source_name, NULL AS published_at, t.detected_at AS updated_at,
                COALESCE(c.name, 'Government Jobs') AS category_name,
                COALESCE(c.color, '#2563eb') AS category_color,
                1 AS is_trend
         FROM trends t
         LEFT JOIN categories c ON t.category_id = c.id
         WHERE t.status IN ('approved', 'detected')
         ORDER BY (t.status = 'approved') DESC, t.trend_score DESC, t.id DESC
         LIMIT " . (int)$needed
    );
    foreach ($topTrends as $tr) {
        $todayPipelineArticles[] = $tr;
    }
}

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

<!-- Today's Publishing Pipeline & Top 5 Articles Widget -->
<div class="admin-table-box" style="margin-bottom: 2rem; border-top: 4px solid #3b82f6; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div class="admin-table-header" style="flex-wrap: wrap; gap: 1rem; padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 0.35rem 0; display: flex; align-items: center; gap: 0.6rem; color: #0f172a;">
                <?= icon('calendar', 'icon-md') ?> Today's Publishing Pipeline & Top 5 Priority Articles
            </h3>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Autonomous IST Publishing Slots (10:00 AM, 02:00 PM, 06:00 PM) & Active Queue
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.4rem 0.8rem; font-size: 0.82rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $slotSchedule['wait_minutes'] <= 60 ? '#f59e0b' : '#3b82f6' ?>;"></span>
                <span><strong>Next Slot:</strong> <?= e($slotSchedule['next_slot_name']) ?></span>
                <span style="color: #64748b;">(in ~<?= $slotSchedule['wait_minutes'] ?>m)</span>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.4rem 0.8rem; font-size: 0.82rem;">
                <strong>Slots Completed:</strong> <span style="color: #16a34a; font-weight: 700;"><?= $completedSlotsToday ?></span> / 3
            </div>
            <a href="<?= url('admin/review/') ?>" class="btn btn-sm btn-primary" style="display: flex; align-items: center; gap: 0.4rem;">
                <?= icon('check-circle', 'icon-xs') ?> Review Queue (<?= $reviewArticles ?>)
            </a>
        </div>
    </div>
    <div style="overflow-x: auto;">
        <table class="table" style="margin: 0;">
            <thead>
                <tr style="background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <th style="width: 50px; text-align: center;">Rank</th>
                    <th>Article Details</th>
                    <th>Category</th>
                    <th>Lifecycle Stage</th>
                    <th>Pipeline Status</th>
                    <th style="text-align: center;">Quality Score</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayPipelineArticles)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                            <?= icon('file-text', 'icon-lg') ?><br>
                            <p style="margin: 0.5rem 0 0 0; font-weight: 600;">No articles in today's pipeline yet.</p>
                            <span style="font-size: 0.85rem;">Autonomous worker will ingest trends and queue articles for today's slots.</span>
                        </td>
                    </tr>
                    <?php 
                    $slotLabels = [
                        0 => 'Slot 1 (10:00 AM IST)',
                        1 => 'Slot 2 (02:00 PM IST)',
                        2 => 'Slot 3 (06:00 PM IST)',
                        3 => 'Tomorrow Slot 1 (10:00 AM)',
                        4 => 'Tomorrow Slot 2 (02:00 PM)'
                    ];
                    foreach ($todayPipelineArticles as $idx => $art): 
                        $isPublishedToday = (date('Y-m-d', strtotime($art['published_at'] ?? '')) === date('Y-m-d') && $art['status'] === 'published');
                        $isTrend = !empty($art['is_trend']);
                        $targetSlot = $slotLabels[$idx] ?? "Slot " . ($idx + 1);
                    ?>
                        <tr style="<?= $isPublishedToday ? 'background: #f0fdf4;' : '' ?>">
                            <td style="text-align: center; font-weight: 800; color: #64748b;">
                                #<?= $idx + 1 ?>
                            </td>
                            <td>
                                <?php if ($isTrend): ?>
                                    <a href="<?= url('admin/trends/') ?>" style="font-weight: 700; color: #1e293b; text-decoration: none; font-size: 0.95rem; display: block; line-height: 1.4;">
                                        <?= e($art['title']) ?>
                                    </a>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.6rem;">
                                        <span style="background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 3px; font-weight: 600;">Autonomous Pipeline Topic</span>
                                        <?php if (!empty($art['source_name'])): ?>
                                            <span>• Source: <?= e($art['source_name']) ?></span>
                                        <?php endif; ?>
                                        <span>• Trend #<?= $art['id'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" style="font-weight: 700; color: var(--text-main); text-decoration: none; font-size: 0.95rem; display: block; line-height: 1.4;">
                                        <?= e($art['title']) ?>
                                    </a>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.6rem;">
                                        <span>ID: #<?= $art['id'] ?></span>
                                        <?php if (!empty($art['source_name'])): ?>
                                            <span>• Source: <?= e($art['source_name']) ?></span>
                                        <?php endif; ?>
                                        <span>• <?= date('h:i A', strtotime($art['updated_at'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= e($art['category_color'] ?? '#2563eb') ?>15; color: <?= e($art['category_color'] ?? '#2563eb') ?>; font-weight: 600;">
                                    <?= e($art['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $lc = strtolower($art['lifecycle_status'] ?? 'active');
                                $lcBadges = [
                                    'active' => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'ACTIVE (APPLY)'],
                                    'upcoming' => ['bg' => '#e0f2fe', 'color' => '#0369a1', 'label' => 'UPCOMING'],
                                    'closed' => ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'CLOSED'],
                                    'admit_card_released' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'ADMIT CARD OUT'],
                                    'exam_completed' => ['bg' => '#f3e8ff', 'color' => '#6b21a8', 'label' => 'EXAM DONE'],
                                    'result_released' => ['bg' => '#ffedd5', 'color' => '#c2410c', 'label' => 'RESULT OUT']
                                ];
                                $lcInfo = $lcBadges[$lc] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => strtoupper($lc)];
                                ?>
                                <span class="badge" style="background: <?= $lcInfo['bg'] ?>; color: <?= $lcInfo['color'] ?>; font-size: 0.72rem; font-weight: 700;">
                                    <?= $lcInfo['label'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isPublishedToday): ?>
                                    <span class="badge" style="background: #22c55e; color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        ● Published Live Today
                                    </span>
                                <?php elseif ($art['status'] === 'review'): ?>
                                    <span class="badge" style="background: #f59e0b; color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        ⚡ Ready in Queue (<?= $targetSlot ?>)
                                    </span>
                                <?php elseif ($art['status'] === 'draft'): ?>
                                    <span class="badge" style="background: #64748b; color: #ffffff; font-weight: 600;">
                                        Draft (Polished)
                                    </span>
                                <?php elseif ($isTrend): ?>
                                    <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        ⚡ Target: <?= $targetSlot ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= ucfirst($art['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; flex-direction: column; align-items: center;">
                                    <span style="font-size: 1rem; font-weight: 800; color: <?= $art['quality_score'] >= 85 ? '#16a34a' : ($art['quality_score'] >= 75 ? '#2563eb' : '#d97706') ?>;">
                                        <?= $art['quality_score'] ?>
                                    </span>
                                    <span style="font-size: 0.7rem; color: #94a3b8;">/ 100</span>
                                </div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                                    <?php if ($isTrend): ?>
                                        <a href="<?= url('admin/trends/') ?>" class="btn btn-xs btn-primary">
                                            Process ⚡
                                        </a>
                                    <?php elseif ($art['status'] === 'published'): ?>
                                        <a href="<?= url('article/' . $art['slug'] . '/') ?>" target="_blank" class="btn btn-xs btn-outline" style="color: #16a34a; border-color: #86efac;">
                                            View Live ↗
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" class="btn btn-xs btn-outline">
                                            Edit
                                        </a>
                                        <a href="<?= url('article/' . $art['slug'] . '/?preview=1') ?>" target="_blank" class="btn btn-xs btn-secondary">
                                            Preview
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
