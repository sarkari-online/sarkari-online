<?php
/**
 * EduPulse - Admin Statutory Sources Console (Phase 9)
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;

Auth::requireAuth();

$adminPageTitle = 'Statutory Authority Sources';
$adminPageKey = 'sources';

$message = null;
$messageType = 'success';

// Handle Actions (Toggle Status / Add New)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'toggle') {
            $sourceId = (int)($_POST['source_id'] ?? 0);
            $curr = Database::fetchOne("SELECT is_active FROM sources WHERE id = :id", ['id' => $sourceId]);
            if ($curr) {
                $newVal = $curr['is_active'] ? 0 : 1;
                Database::update('sources', ['is_active' => $newVal, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $sourceId]);
                $message = "Source status updated successfully.";
            }
        } elseif ($action === 'create') {
            $name = Sanitizer::string($_POST['name'] ?? '');
            $baseUrl = filter_var($_POST['base_url'] ?? '', FILTER_VALIDATE_URL);
            $adapter = Sanitizer::string($_POST['adapter_class'] ?? 'App\\Services\\TrendSources\\OfficialSourcesAdapter');

            if (empty($name) || !$baseUrl) {
                $message = "Source Name and a valid Base URL are required.";
                $messageType = 'danger';
            } else {
                Database::insert('sources', [
                    'name' => $name,
                    'base_url' => $baseUrl,
                    'adapter_class' => $adapter,
                    'is_active' => 1,
                    'robots_checked_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $message = "New statutory authority '{$name}' registered.";
            }
        }
    }
}

$sources = Database::fetchAll("SELECT * FROM sources ORDER BY id ASC");

include dirname(__DIR__) . '/components/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0;">Registered Authority Sources</h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Official statutory portals and education commission feeds monitored by the trend engine.
        </p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.5rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Sources Table -->
    <div class="admin-table-box">
        <div class="admin-table-header">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">Active Statutory Portals</h3>
        </div>
        <table class="table" style="margin: 0;">
            <thead>
                <tr>
                    <th>Agency / Authority</th>
                    <th>Official Portal URL</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $s): ?>
                    <tr>
                        <td>
                            <strong style="font-size: 0.95rem; color: var(--text-main);"><?= e($s['name']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                <?= e($s['adapter_class'] ?? 'Default Adapter') ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?= e($s['base_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); font-size: 0.875rem;">
                                <?= e($s['base_url']) ?> <?= icon('external-link', 'icon-xs') ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="margin: 0;">
                                <?= CSRF::input() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="source_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-xs <?= $s['is_active'] ? 'btn-outline' : 'btn-primary' ?>">
                                    <?= $s['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Register New Source Box -->
    <div class="admin-table-box" style="padding: 1.25rem;">
        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem;">Register New Authority</h3>
        <form method="POST">
            <?= CSRF::input() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.8125rem; font-weight: 700;">Agency / Authority Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. AICTE Official" required>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size: 0.8125rem; font-weight: 700;">Official Portal Base URL</label>
                <input type="url" name="base_url" class="form-control" placeholder="https://aicte-india.org" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8125rem; font-weight: 700;">Adapter Class</label>
                <input type="text" name="adapter_class" class="form-control" value="App\Services\TrendSources\OfficialSourcesAdapter">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Add Statutory Authority</button>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
