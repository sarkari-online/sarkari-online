<?php
/**
 * EduPulse - Production Admin Dashboard (Phase 9)
 * Clean, focused view on Publishing Slot Schedule & Top 5 Priority Articles
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

$adminPageTitle = 'Publishing Slots & Operational Intelligence';
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

// 2. Fetch Publishing Slot Status & Top 5 Priority Articles
$slotSchedule = AutoCronService::getISTSlotSchedule();
$completedSlotsToday = AutoCronService::getCompletedSlotsTodayCount();

// First, fetch today's published articles, review queue, and high-quality drafts
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

// If fewer than 5 articles in pipeline, fill remaining slots from top approved trends of today
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
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 2rem;">
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

<!-- Scheduled Publishing Slots & Top 5 Articles Widget -->
<div class="admin-table-box" style="margin-bottom: 2.5rem; border-top: 4px solid #3b82f6; box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.08); border-radius: 8px;">
    <div class="admin-table-header" style="flex-wrap: wrap; gap: 1rem; padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
        <div>
            <h3 style="font-size: 1.2rem; font-weight: 800; margin: 0 0 0.35rem 0; display: flex; align-items: center; gap: 0.6rem; color: #0f172a;">
                <?= icon('calendar', 'icon-md') ?> Scheduled Publishing Slots & Top 5 Priority Articles
            </h3>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Showing the designated articles & verified topics queued for today's 3 scheduled IST slots (10:00 AM, 02:00 PM, 06:00 PM)
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.45rem 0.85rem; font-size: 0.82rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <span style="display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: <?= $slotSchedule['wait_minutes'] <= 30 ? '#f59e0b' : '#3b82f6' ?>;"></span>
                <span><strong>Next Slot:</strong> <?= e($slotSchedule['next_slot_name']) ?></span>
                <span style="color: #64748b;">(in ~<?= $slotSchedule['wait_minutes'] ?>m)</span>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.45rem 0.85rem; font-size: 0.82rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <strong>Slots Completed Today:</strong> <span style="color: #16a34a; font-weight: 800;"><?= $completedSlotsToday ?></span> / 3
            </div>
            <a href="<?= url('admin/trends/') ?>" class="btn btn-sm btn-outline" style="display: flex; align-items: center; gap: 0.4rem;">
                <?= icon('trending-up', 'icon-xs') ?> All Trends (<?= $trendsToday ?>)
            </a>
        </div>
    </div>
    <div style="overflow-x: auto;">
        <table class="table" style="margin: 0;">
            <thead>
                <tr style="background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0;">
                    <th style="width: 170px;">Scheduled Slot</th>
                    <th>Article / Topic to Publish</th>
                    <th style="width: 150px;">Category</th>
                    <th style="width: 140px;">Lifecycle Stage</th>
                    <th style="width: 160px;">Slot Status</th>
                    <th style="width: 100px; text-align: center;">Score</th>
                    <th style="width: 130px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayPipelineArticles)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                            <?= icon('file-text', 'icon-lg') ?><br>
                            <p style="margin: 0.5rem 0 0 0; font-weight: 700; font-size: 1rem; color: #1e293b;">No articles currently in pipeline.</p>
                            <span style="font-size: 0.85rem; color: #64748b;">The autonomous worker is actively monitoring government portals for breaking notifications.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $slotLabels = [
                        0 => ['title' => 'Slot 1 (10:00 AM IST)', 'sub' => 'Today Morning Slot', 'color' => '#2563eb', 'bg' => '#eff6ff'],
                        1 => ['title' => 'Slot 2 (02:00 PM IST)', 'sub' => 'Today Afternoon Slot', 'color' => '#0891b2', 'bg' => '#ecfeff'],
                        2 => ['title' => 'Slot 3 (06:00 PM IST)', 'sub' => 'Today Evening Slot', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
                        3 => ['title' => 'Slot 1 (Tomorrow)', 'sub' => 'Tomorrow 10:00 AM IST', 'color' => '#475569', 'bg' => '#f8fafc'],
                        4 => ['title' => 'Slot 2 (Tomorrow)', 'sub' => 'Tomorrow 02:00 PM IST', 'color' => '#475569', 'bg' => '#f8fafc']
                    ];
                    foreach ($todayPipelineArticles as $idx => $art): 
                        $isPublishedToday = (date('Y-m-d', strtotime($art['published_at'] ?? '')) === date('Y-m-d') && $art['status'] === 'published');
                        $isTrend = !empty($art['is_trend']);
                        $slotMeta = $slotLabels[$idx] ?? ['title' => "Slot " . ($idx + 1), 'sub' => 'Scheduled Pipeline', 'color' => '#475569', 'bg' => '#f8fafc'];
                    ?>
                        <tr style="<?= $isPublishedToday ? 'background: #f0fdf4;' : ($idx === $completedSlotsToday ? 'background: #fffbeb;' : '') ?>">
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 800; font-size: 0.85rem; color: <?= $slotMeta['color'] ?>; background: <?= $slotMeta['bg'] ?>; border: 1px solid <?= $slotMeta['color'] ?>30; padding: 3px 8px; border-radius: 4px; display: inline-block;">
                                        <?= e($slotMeta['title']) ?>
                                    </span>
                                    <span style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">
                                        <?= e($slotMeta['sub']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($isTrend): ?>
                                    <a href="<?= url('admin/trends/') ?>" style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 0.95rem; display: block; line-height: 1.4;">
                                        <?= e($art['title']) ?>
                                    </a>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem; display: flex; align-items: center; gap: 0.6rem;">
                                        <span style="background: #e0f2fe; color: #0369a1; padding: 1px 7px; border-radius: 3px; font-weight: 600; font-size: 0.72rem;">Top Priority Topic</span>
                                        <?php if (!empty($art['source_name'])): ?>
                                            <span>• Source: <strong><?= e($art['source_name']) ?></strong></span>
                                        <?php endif; ?>
                                        <span>• Trend #<?= $art['id'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= url('admin/articles/edit.php?id=' . $art['id']) ?>" style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 0.95rem; display: block; line-height: 1.4;">
                                        <?= e($art['title']) ?>
                                    </a>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem; display: flex; align-items: center; gap: 0.6rem;">
                                        <span>Article ID: #<?= $art['id'] ?></span>
                                        <?php if (!empty($art['source_name'])): ?>
                                            <span>• Source: <strong><?= e($art['source_name']) ?></strong></span>
                                        <?php endif; ?>
                                        <span>• Updated: <?= date('h:i A', strtotime($art['updated_at'])) ?></span>
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
                                        ⚡ In Review Queue
                                    </span>
                                <?php elseif ($art['status'] === 'draft'): ?>
                                    <span class="badge" style="background: #64748b; color: #ffffff; font-weight: 600;">
                                        Draft (Polished)
                                    </span>
                                <?php elseif ($isTrend): ?>
                                    <span class="badge" style="background: #dbeafe; color: #1e40af; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        ⚡ Ready for Auto-Publish
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= ucfirst($art['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; flex-direction: column; align-items: center;">
                                    <span style="font-size: 1.05rem; font-weight: 800; color: <?= $art['quality_score'] >= 85 ? '#16a34a' : ($art['quality_score'] >= 75 ? '#2563eb' : '#d97706') ?>;">
                                        <?= $art['quality_score'] ?>
                                    </span>
                                    <span style="font-size: 0.7rem; color: #94a3b8;">/ 100</span>
                                </div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                                    <?php if ($isTrend): ?>
                                        <a href="<?= url('admin/trends/') ?>" class="btn btn-xs btn-primary">
                                            Publish Now ⚡
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

<?php include __DIR__ . '/components/footer.php'; ?>
