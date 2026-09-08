<?php
/**
 * Sarkari.online - Admin Candidate Reports & Verification Moderation
 * 
 * Review and approve ground-level exam center observations and circular updates
 * submitted through the Aspirant Help Desk.
 */
require_once dirname(__DIR__, 2) . '/config.php';
App\Helpers\Auth::requireAuth();

use App\Database\Database;

// Handle Actions (Approve/Verify, Dismiss, Delete)
$action = $_GET['action'] ?? null;
$signalId = (int)($_GET['id'] ?? 0);
$feedbackMsg = '';

if ($action && $signalId > 0) {
    if ($action === 'verify') {
        Database::update('reader_signals', ['status' => 'verified'], 'id = :id', ['id' => $signalId]);
        $feedbackMsg = 'Report approved! It is now live under Verified Candidate Notes on the article.';
    } elseif ($action === 'dismiss') {
        Database::update('reader_signals', ['status' => 'dismissed'], 'id = :id', ['id' => $signalId]);
        $feedbackMsg = 'Report dismissed.';
    } elseif ($action === 'delete') {
        Database::delete('reader_signals', 'id = :id', ['id' => $signalId]);
        $feedbackMsg = 'Report deleted.';
    }
}

$adminPageKey = 'signals';
$adminPageTitle = 'Candidate Reports &amp; Signals';

// Stats
$pendingCount   = (int)Database::fetchColumn("SELECT COUNT(*) FROM reader_signals WHERE status = 'pending_review'");
$verifiedCount  = (int)Database::fetchColumn("SELECT COUNT(*) FROM reader_signals WHERE status = 'verified'");
$dismissedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM reader_signals WHERE status = 'dismissed'");
$totalCount     = (int)Database::fetchColumn("SELECT COUNT(*) FROM reader_signals");

// Current Filter
$statusFilter = $_GET['status'] ?? 'pending_review';
$whereSql = "";
$params = [];

if (in_array($statusFilter, ['pending_review', 'verified', 'dismissed'], true)) {
    $whereSql = "WHERE s.status = :status";
    $params['status'] = $statusFilter;
}

$sql = "SELECT s.*, a.title AS article_title, a.slug AS article_slug 
        FROM reader_signals s 
        LEFT JOIN articles a ON s.article_id = a.id 
        {$whereSql} 
        ORDER BY s.id DESC LIMIT 100";

$signals = Database::fetchAll($sql, $params);

include dirname(__DIR__) . '/components/header.php';
?>

<div class="admin-content-body">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">
                Candidate Reports &amp; Ground Intel
            </h1>
            <p style="font-size: 0.85rem; color: #64748b; margin: 0;">
                Review student feedback from exam centers and official notification amendments.
            </p>
        </div>
        <div style="font-size: 0.8125rem; background: #eff6ff; color: #1e40af; padding: 0.4rem 0.85rem; border-radius: 6px; border: 1px solid #bfdbfe; font-weight: 600;">
            Approved notes display live on the respective article.
        </div>
    </div>

    <?php if (!empty($feedbackMsg)): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; margin-bottom: 1.25rem;">
            <?= e($feedbackMsg) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <div class="stat-card">
            <span class="stat-card-label" style="font-size: 0.8125rem; color: #64748b; font-weight: 600;">Pending Approval</span>
            <span class="stat-card-num" style="font-size: 1.75rem; font-weight: 800; color: #ea580c;"><?= $pendingCount ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label" style="font-size: 0.8125rem; color: #64748b; font-weight: 600;">Approved &amp; Live</span>
            <span class="stat-card-num" style="font-size: 1.75rem; font-weight: 800; color: #16a34a;"><?= $verifiedCount ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label" style="font-size: 0.8125rem; color: #64748b; font-weight: 600;">Dismissed</span>
            <span class="stat-card-num" style="font-size: 1.75rem; font-weight: 800; color: #94a3b8;"><?= $dismissedCount ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label" style="font-size: 0.8125rem; color: #64748b; font-weight: 600;">Total Received</span>
            <span class="stat-card-num" style="font-size: 1.75rem; font-weight: 800; color: #0f172a;"><?= $totalCount ?></span>
        </div>
    </div>

    <!-- Filter Pills -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
        <a href="<?= url('admin/signals/?status=pending_review') ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; <?= $statusFilter === 'pending_review' ? 'background: #ea580c; color: #fff;' : 'background: #ffffff; color: #475569; border: 1px solid #cbd5e1;' ?>">
            Pending Review (<?= $pendingCount ?>)
        </a>
        <a href="<?= url('admin/signals/?status=verified') ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; <?= $statusFilter === 'verified' ? 'background: #16a34a; color: #fff;' : 'background: #ffffff; color: #475569; border: 1px solid #cbd5e1;' ?>">
            Approved / Live (<?= $verifiedCount ?>)
        </a>
        <a href="<?= url('admin/signals/?status=dismissed') ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; <?= $statusFilter === 'dismissed' ? 'background: #64748b; color: #fff;' : 'background: #ffffff; color: #475569; border: 1px solid #cbd5e1;' ?>">
            Dismissed (<?= $dismissedCount ?>)
        </a>
        <a href="<?= url('admin/signals/?status=all') ?>" style="text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; <?= $statusFilter === 'all' ? 'background: #0f172a; color: #fff;' : 'background: #ffffff; color: #475569; border: 1px solid #cbd5e1;' ?>">
            All (<?= $totalCount ?>)
        </a>
    </div>

    <!-- Submissions List -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <?php if (empty($signals)): ?>
            <div style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                <p style="font-size: 1rem; font-weight: 600; margin: 0 0 0.5rem 0;">No submissions found in this tab.</p>
                <p style="font-size: 0.8125rem; margin: 0;">Candidate reports and center observations will appear here as students submit them.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 0.75rem 1rem;">ID</th>
                            <th style="padding: 0.75rem 1rem;">Article</th>
                            <th style="padding: 0.75rem 1rem;">Type &amp; Candidate</th>
                            <th style="padding: 0.75rem 1rem;">Observation / Feedback</th>
                            <th style="padding: 0.75rem 1rem;">Status</th>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signals as $s): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; vertical-align: top;">
                                <td style="padding: 1rem; color: #94a3b8; font-weight: 600;">
                                    #<?= (int)$s['id'] ?>
                                </td>
                                <td style="padding: 1rem; max-width: 240px;">
                                    <?php if (!empty($s['article_slug'])): ?>
                                        <a href="<?= url('article/' . $s['article_slug'] . '/') ?>" target="_blank" style="color: #1e40af; font-weight: 600; text-decoration: none; line-height: 1.4; display: block;">
                                            <?= e($s['article_title']) ?>
                                        </a>
                                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 3px;">
                                            <a href="<?= url('admin/articles/edit.php?id=' . $s['article_id']) ?>" style="color: #64748b; text-decoration: underline;">Edit Article</a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">Article #<?= (int)$s['article_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; white-space: nowrap;">
                                    <div style="font-weight: 700; color: #0f172a;">
                                        <?= e($s['candidate_name'] ?: 'Anonymous') ?>
                                    </div>
                                    <?php if (!empty($s['exam_center_city'])): ?>
                                        <div style="font-size: 0.75rem; color: #2563eb; font-weight: 600;">
                                            City: <?= e($s['exam_center_city']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <span style="display: inline-block; margin-top: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px;">
                                        <?= $s['signal_type'] === 'correction' ? 'Notice Update' : 'Center Observation' ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; min-width: 260px;">
                                    <div style="color: #1e293b; line-height: 1.5; margin-bottom: 0.35rem;">
                                        <?= nl2br(e($s['message'])) ?>
                                    </div>
                                    <?php if (!empty($s['reporting_time_observed']) || !empty($s['biometric_status'])): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px; font-size: 0.725rem; margin-top: 4px;">
                                            <?php if (!empty($s['reporting_time_observed'])): ?>
                                                <span style="background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                                    Gate: <?= e($s['reporting_time_observed']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($s['biometric_status'])): ?>
                                                <span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                                    Check: <?= e($s['biometric_status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['source_circular_url'])): ?>
                                        <div style="margin-top: 4px; font-size: 0.725rem;">
                                            <a href="<?= e($s['source_circular_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline;">
                                                View Attached Notice Link &rarr;
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; white-space: nowrap;">
                                    <?php if ($s['status'] === 'verified'): ?>
                                        <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.725rem; padding: 3px 8px; border-radius: 9999px;">Live / Approved</span>
                                    <?php elseif ($s['status'] === 'dismissed'): ?>
                                        <span style="background: #f1f5f9; color: #64748b; font-weight: 600; font-size: 0.725rem; padding: 3px 8px; border-radius: 9999px;">Dismissed</span>
                                    <?php else: ?>
                                        <span style="background: #ffedd5; color: #9a3412; font-weight: 700; font-size: 0.725rem; padding: 3px 8px; border-radius: 9999px;">Pending Review</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.75rem; color: #64748b; white-space: nowrap;">
                                    <?= date('d M Y, h:i A', strtotime($s['created_at'])) ?>
                                </td>
                                <td style="padding: 1rem; text-align: right; white-space: nowrap;">
                                    <?php if ($s['status'] !== 'verified'): ?>
                                        <a href="<?= url('admin/signals/?action=verify&id=' . $s['id'] . '&status=' . urlencode($statusFilter)) ?>" style="background: #16a34a; color: #ffffff; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-right: 4px;">
                                            Approve &amp; Show
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($s['status'] !== 'dismissed'): ?>
                                        <a href="<?= url('admin/signals/?action=dismiss&id=' . $s['id'] . '&status=' . urlencode($statusFilter)) ?>" style="background: #f1f5f9; color: #64748b; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-right: 4px;">
                                            Dismiss
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= url('admin/signals/?action=delete&id=' . $s['id'] . '&status=' . urlencode($statusFilter)) ?>" onclick="return confirm('Are you sure you want to permanently delete this report?')" style="color: #ef4444; font-size: 0.75rem; text-decoration: none;">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
