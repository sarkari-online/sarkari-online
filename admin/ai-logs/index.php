<?php
/**
 * EduPulse - Admin AI Operations & Audit Logs Console (Phase 9)
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\Sanitizer;

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_errors') {
    Database::query("DELETE FROM ai_logs WHERE success = 0");
    header('Location: ' . url('admin/ai-logs/'));
    exit;
}

$adminPageTitle = 'AI Operations & Audit Logs';
$adminPageKey = 'ai_logs';

$stageFilter = Sanitizer::string($_GET['stage'] ?? '');
$statusFilter = Sanitizer::string($_GET['status'] ?? '');

$where = [];
$params = [];

if ($stageFilter !== '') {
    $where[] = "pipeline_stage = :stage";
    $params['stage'] = $stageFilter;
}

if ($statusFilter === 'success') {
    $where[] = "success = 1";
} elseif ($statusFilter === 'failed') {
    $where[] = "success = 0";
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = (int)Database::fetchColumn("SELECT COUNT(*) FROM ai_logs {$whereClause}", $params);
$logs = Database::fetchAll("SELECT * FROM ai_logs {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
$totalPages = ceil($total / $perPage);

// Calculate Totals
$totalTokens = (int)Database::fetchColumn("SELECT SUM(tokens_used) FROM ai_logs");
$totalCalls = (int)Database::fetchColumn("SELECT COUNT(*) FROM ai_logs");
$failedCalls = (int)Database::fetchColumn("SELECT COUNT(*) FROM ai_logs WHERE success = 0");

include dirname(__DIR__) . '/components/header.php';
?>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem;">
    <div class="stat-card">
        <span class="stat-card-label">Total AI Invocations</span>
        <span class="stat-card-num"><?= number_format($totalCalls) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Total Tokens Tracked</span>
        <span class="stat-card-num" style="color: #2563eb;"><?= number_format($totalTokens) ?></span>
    </div>
    <div class="stat-card" style="border-left: 4px solid <?= $failedCalls > 0 ? '#ef4444' : '#22c55e' ?>;">
        <span class="stat-card-label" style="color: <?= $failedCalls > 0 ? '#dc2626' : '#16a34a' ?>;">Failed Calls</span>
        <span class="stat-card-num" style="color: <?= $failedCalls > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($failedCalls) ?></span>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0;">LLM Pipeline Execution Log</h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Full telemetry, token usage, prompt summaries, and failure diagnostics across all AI stages.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="<?= url('admin/ai-logs/') ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?>">All</a>
        <a href="<?= url('admin/ai-logs/?status=success') ?>" class="btn btn-sm <?= $statusFilter === 'success' ? 'btn-primary' : 'btn-outline' ?>" style="color: #16a34a;">Success Only</a>
        <a href="<?= url('admin/ai-logs/?status=failed') ?>" class="btn btn-sm <?= $statusFilter === 'failed' ? 'btn-primary' : 'btn-outline' ?>" style="color: var(--color-danger);">
            Failures Only (<?= $failedCalls ?>)
        </a>
        <?php if ($failedCalls > 0): ?>
        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Clear all <?= $failedCalls ?> historical failed call logs?');">
            <input type="hidden" name="action" value="clear_errors">
            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--text-muted); font-size: 0.75rem;">Clear Failed Logs</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="admin-table-box">
    <table class="table" style="margin: 0; font-size: 0.875rem;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pipeline Stage</th>
                <th>Status</th>
                <th>Tokens</th>
                <th>Prompt Summary</th>
                <th>Response / Error Diagnostics</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        No AI operations logged yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="color: var(--text-muted); font-family: monospace;">#<?= $l['id'] ?></td>
                        <td>
                            <span class="badge badge-secondary" style="font-family: monospace; font-size: 0.75rem;">
                                <?= e($l['pipeline_stage']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($l['success']): ?>
                                <span class="badge badge-success" style="font-size: 0.75rem;">Success</span>
                            <?php else: ?>
                                <span class="badge badge-danger" style="font-size: 0.75rem;">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: monospace; font-weight: 600;">
                            <?= number_format($l['tokens_used']) ?>
                        </td>
                        <td style="max-width: 260px;">
                            <div style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= e($l['prompt_summary']) ?>
                            </div>
                        </td>
                        <td style="max-width: 320px;">
                            <?php if (!empty($l['error_message'])): ?>
                                <div style="color: #b91c1c; font-size: 0.8rem; font-family: monospace;">
                                    <?= e($l['error_message']) ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace;">
                                    <?= e($l['response_summary']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                            <?= date('M d, H:i:s', strtotime($l['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination-container" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= url('admin/ai-logs/?page=' . $i . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')) ?>" 
               class="btn btn-sm <?= $page === $i ? 'btn-primary' : 'btn-outline' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
