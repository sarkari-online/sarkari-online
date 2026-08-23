<?php
/**
 * Sarkari.online - Admin Inquiries & Leads Management
 */
require_once dirname(__DIR__, 2) . '/config.php';
App\Helpers\Auth::requireAuth();

use App\Database\Database;

// Handle Actions (Mark Read / Unread / Delete)
$action = $_GET['action'] ?? null;
$msgId = (int)($_GET['id'] ?? 0);

if ($action && $msgId > 0) {
    if ($action === 'mark_read') {
        Database::update('contact_messages', ['status' => 'read'], 'id = :id', ['id' => $msgId]);
    } elseif ($action === 'mark_unread') {
        Database::update('contact_messages', ['status' => 'unread'], 'id = :id', ['id' => $msgId]);
    } elseif ($action === 'delete') {
        Database::delete('contact_messages', 'id = :id', ['id' => $msgId]);
    }
    header('Location: ' . url('admin/messages/'));
    exit;
}

$adminPageKey = 'messages';
$adminPageTitle = 'Inquiries & Leads Inbox';

// Fetch Statistics
$totalMessages = (int)Database::fetchColumn("SELECT count(*) FROM contact_messages");
$unreadMessages = (int)Database::fetchColumn("SELECT count(*) FROM contact_messages WHERE status = 'unread'");
$todayMessages = (int)Database::fetchColumn("SELECT count(*) FROM contact_messages WHERE DATE(created_at) = CURRENT_DATE");

// Fetch All Messages
$messages = Database::fetchAll("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 100");

include dirname(__DIR__) . '/components/header.php';
?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                Inquiries &amp; Leads Inbox
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">
                All contact submissions, editorial corrections, and grievance requests from students and readers.
            </p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-top: 1.5rem;">
        <div class="stat-card">
            <span class="stat-card-label">Unread Messages</span>
            <span class="stat-card-num" style="color: #ea580c;"><?= $unreadMessages ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Received Today</span>
            <span class="stat-card-num" style="color: #2563eb;"><?= $todayMessages ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Total Submissions</span>
            <span class="stat-card-num"><?= $totalMessages ?></span>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="admin-table-box" style="margin-top: 1.5rem;">
        <div class="admin-table-header">
            <h2 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0;">
                All Submissions (<?= count($messages) ?>)
            </h2>
        </div>

        <?php if (empty($messages)): ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;">No inquiries received yet.</p>
                <p style="font-size: 0.875rem;">Messages submitted via <a href="<?= url('contact/') ?>" target="_blank">Contact Page</a> will appear here in real time.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); text-align: left;">
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted); width: 60px;">Status</th>
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted); width: 140px;">Received</th>
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted); width: 180px;">Sender</th>
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted); width: 150px;">Subject</th>
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted);">Message</th>
                            <th style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-muted); text-align: right; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); background: <?= $msg['status'] === 'unread' ? '#fff7ed' : '#ffffff' ?>;">
                                <td style="padding: 1rem;">
                                    <?php if ($msg['status'] === 'unread'): ?>
                                        <span class="badge" style="background: #f97316; color: #fff; font-size: 0.7rem; font-weight: 700;">NEW</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #e2e8f0; color: #64748b; font-size: 0.7rem;">READ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.8125rem; color: var(--text-muted);">
                                    <?= date('d M Y', strtotime($msg['created_at'])) ?><br>
                                    <span style="font-size: 0.75rem;"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
                                </td>
                                <td style="padding: 1rem;">
                                    <strong style="color: var(--text-main); display: block;"><?= e($msg['name']) ?></strong>
                                    <a href="mailto:<?= e($msg['email']) ?>" style="font-size: 0.8125rem; color: var(--color-primary); text-decoration: none;">
                                        <?= e($msg['email']) ?>
                                    </a>
                                </td>
                                <td style="padding: 1rem;">
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-weight: 600; font-size: 0.75rem;">
                                        <?= e($msg['subject']) ?>
                                    </span>
                                    <?php if (!empty($msg['article_url'])): ?>
                                        <div style="margin-top: 0.35rem;">
                                            <a href="<?= e($msg['article_url']) ?>" target="_blank" style="font-size: 0.75rem; color: #0284c7; text-decoration: underline;">
                                                View Article Link ↗
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="color: var(--text-main); line-height: 1.5; white-space: pre-wrap; word-break: break-word; max-height: 120px; overflow-y: auto; background: #f8fafc; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color);">
                                        <?= e($msg['message']) ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.35rem;">
                                        IP: <?= e($msg['ip_address'] ?? 'Unknown') ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; text-align: right;">
                                    <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                        <?php if ($msg['status'] === 'unread'): ?>
                                            <a href="<?= url('admin/messages/?action=mark_read&id=' . $msg['id']) ?>" class="btn btn-sm btn-outline" title="Mark as Read" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                ✓ Read
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= url('admin/messages/?action=mark_unread&id=' . $msg['id']) ?>" class="btn btn-sm btn-outline" title="Mark as Unread" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #64748b;">
                                                Unread
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= url('admin/messages/?action=delete&id=' . $msg['id']) ?>" onclick="return confirm('Are you sure you want to delete this message?');" class="btn btn-sm btn-outline" title="Delete" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #ef4444; border-color: #fca5a5;">
                                            ✕
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
