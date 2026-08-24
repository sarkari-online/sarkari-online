<?php
/**
 * EduPulse - Admin System Health & Diagnostic Console (Phase 9)
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\Env;

Auth::requireAuth();

$adminPageTitle = 'System Health & Diagnostics';
$adminPageKey = 'health';

// 1. Database Health Check
$dbStatus = ['name' => 'MySQL Database Engine', 'status' => 'pass', 'details' => ''];
try {
    $dbStart = microtime(true);
    $dbName = Env::get('DB_DATABASE', 'sarkari_online_db');
    $dbHost = Env::get('DB_HOST', '127.0.0.1');
    $dbPort = Env::get('DB_PORT', '3306');
    $tableCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db", ['db' => $dbName]);
    $dbTime = round((microtime(true) - $dbStart) * 1000, 2);
    $dbStatus['details'] = "Connected successfully to {$dbHost}:{$dbPort} ({$tableCount} tables, latency: {$dbTime}ms)";
} catch (Throwable $e) {
    $dbStatus['status'] = 'fail';
    $dbStatus['details'] = "Database connection error: " . $e->getMessage();
}

// 2. Gemini API Engine Check
$geminiStatus = ['name' => 'Gemini AI Client', 'status' => 'pass', 'details' => ''];
$apiKey = Env::get('GEMINI_API_KEY', '');
$model = Env::get('GEMINI_MODEL', 'gemini-1.5-flash');
if (empty($apiKey) || $apiKey === 'your_actual_gemini_api_key_here') {
    $geminiStatus['status'] = 'warn';
    $geminiStatus['details'] = "API Key not configured in .env. System running on simulated / mock telemetry mode.";
} else {
    $geminiStatus['details'] = "Configured with model '{$model}' (API key securely masked: " . substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) . ")";
}

// 3. Image Generation Subsystem Check
$imageStatus = ['name' => 'Branded Thumbnail Engine (PHP GD)', 'status' => 'pass', 'details' => ''];
if (!extension_loaded('gd')) {
    $imageStatus['status'] = 'fail';
    $imageStatus['details'] = "PHP GD extension is not installed.";
} else {
    $gdInfo = gd_info();
    $webp = !empty($gdInfo['WebP Support']) ? 'Supported' : 'Missing';
    $ft = !empty($gdInfo['FreeType Support']) ? 'Supported' : 'Missing';
    $imageStatus['details'] = "PHP GD active. WebP: {$webp}, FreeType Typography: {$ft}";
}

// 4. Storage Directory Permissions Check
$storageStatus = ['name' => 'Filesystem Storage & Uploads', 'status' => 'pass', 'details' => ''];
$dirsToCheck = [
    'storage/logs' => dirname(__DIR__, 2) . '/storage/logs',
    'storage/cache' => dirname(__DIR__, 2) . '/storage/cache',
    'storage/generated' => dirname(__DIR__, 2) . '/storage/generated',
    'uploads/thumbnails' => dirname(__DIR__, 2) . '/uploads/thumbnails'
];
$unwritable = [];
foreach ($dirsToCheck as $label => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    if (!is_writable($path)) {
        $unwritable[] = $label;
    }
}
if (!empty($unwritable)) {
    $storageStatus['status'] = 'fail';
    $storageStatus['details'] = "Directories not writable: " . implode(', ', $unwritable);
} else {
    $storageStatus['details'] = "All 4 core directories (logs, cache, generated, thumbnails) are writable (0755).";
}

// 5. Dynamic Sitemap Health Check
$sitemapStatus = ['name' => 'Dynamic XML Sitemap Engine', 'status' => 'pass', 'details' => ''];
$sitemapFile = dirname(__DIR__, 2) . '/sitemap.php';
if (!file_exists($sitemapFile)) {
    $sitemapStatus['status'] = 'fail';
    $sitemapStatus['details'] = "sitemap.php script is missing.";
} else {
    $pubCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");
    $sitemapStatus['details'] = "Dynamic sitemap online serving {$pubCount} published articles and category indexes.";
}

// 6. Cron Workers Activity Check
$cronStatus = ['name' => 'Automated Background Workers', 'status' => 'pass', 'details' => ''];
$lastLog = Database::fetchOne("SELECT created_at FROM ai_logs ORDER BY id DESC LIMIT 1");
if ($lastLog) {
    $cronStatus['details'] = "Latest automated pipeline transaction recorded at " . date('M d, Y H:i:s', strtotime($lastLog['created_at']));
} else {
    $cronStatus['details'] = "Cron workers initialized and ready for invocation.";
}

$checks = [$dbStatus, $geminiStatus, $imageStatus, $storageStatus, $sitemapStatus, $cronStatus];

include dirname(__DIR__) . '/components/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0;">Infrastructure Health &amp; Subsystems</h2>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
        Real-time telemetry and operational diagnostics across database, storage, AI engine, and media subsystems.
    </p>
</div>

<div class="admin-table-box" style="margin-bottom: 2rem;">
    <table class="table" style="margin: 0;">
        <thead>
            <tr>
                <th style="width: 25%;">Subsystem Component</th>
                <th style="width: 15%;">Status</th>
                <th>Diagnostic Telemetry Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $chk): ?>
                <tr>
                    <td>
                        <strong style="color: var(--text-main); font-size: 0.95rem;"><?= e($chk['name']) ?></strong>
                    </td>
                    <td>
                        <?php if ($chk['status'] === 'pass'): ?>
                            <span class="badge badge-success" style="font-size: 0.8rem;">
                                <?= icon('shield-check', 'icon-xs') ?> Operational
                            </span>
                        <?php elseif ($chk['status'] === 'warn'): ?>
                            <span class="badge badge-warning" style="font-size: 0.8rem;">
                                <?= icon('alert-triangle', 'icon-xs') ?> Warning
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger" style="font-size: 0.8rem;">
                                <?= icon('x', 'icon-xs') ?> Critical Issue
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="color: var(--text-main); font-size: 0.875rem;">
                        <?= e($chk['details']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
    <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.75rem;">Cron Invocation Commands</h3>
    <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
        Recommended crontab configuration for automated daily execution on Linux/macOS servers:
    </p>
    <pre style="background: #0f172a; color: #38bdf8; padding: 1.25rem; border-radius: 8px; font-size: 0.8rem; overflow-x: auto; line-height: 1.6;">
# 1. Fetch emerging trends every 30 minutes
*/30 * * * * php <?= dirname(__DIR__, 2) ?>/cron/fetch-trends.php >> <?= dirname(__DIR__, 2) ?>/storage/logs/cron.log 2>&1

# 2. Analyze detected trends via Gemini AI every 30 minutes
15,45 * * * * php <?= dirname(__DIR__, 2) ?>/cron/analyze-trends.php >> <?= dirname(__DIR__, 2) ?>/storage/logs/cron.log 2>&1

# 3. Generate articles from approved trends hourly
0 * * * * php <?= dirname(__DIR__, 2) ?>/cron/generate-articles.php >> <?= dirname(__DIR__, 2) ?>/storage/logs/cron.log 2>&1

# 4. Controlled auto-publishing every 2 hours
30 */2 * * * php <?= dirname(__DIR__, 2) ?>/cron/publish-articles.php >> <?= dirname(__DIR__, 2) ?>/storage/logs/cron.log 2>&1

# 5. Monitor published time-sensitive articles for updates twice daily
0 9,17 * * * php <?= dirname(__DIR__, 2) ?>/cron/monitor-articles.php >> <?= dirname(__DIR__, 2) ?>/storage/logs/cron.log 2>&1
    </pre>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
