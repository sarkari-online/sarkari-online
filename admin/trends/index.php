<?php
/**
 * EduPulse - Admin Trends Engine Console (Phase 9)
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Services\TrendService;
use App\Services\PipelineService;
use App\Services\PublishingService;

Auth::requireAuth();

$adminPageTitle = 'Trend Intelligence & Discovery';
$adminPageKey = 'trends';

$message = null;
$messageType = 'success';

// Process Actions (Approve / Reject / Reset / Publish Now / Clean Backlog)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $trendId = (int)($_POST['trend_id'] ?? 0);

        if ($action === 'clean_backlog') {
            $cleaned = TrendService::cleanRepetitiveBacklog();
            $purged = TrendService::purgeDuplicateTrends();
            $message = "Cleaned {$cleaned} repetitive topics from approved queue and purged {$purged} duplicate records.";
            $messageType = 'success';
        } elseif ($action === 'purge_duplicates') {
            $purged = TrendService::purgeDuplicateTrends();
            $message = "Successfully purged {$purged} duplicate trend records from database.";
            $messageType = 'success';
        } elseif ($trendId > 0) {
            if ($action === 'publish_now') {
                try {
                    TrendService::markStatus($trendId, 'approved', ['trend_score' => 99]);
                    
                    // Asynchronously dispatch to CLI worker to generate into Review Queue
                    $cliScript = dirname(__DIR__, 2) . '/cron/publish-single.php';
                    $cmd = "php " . escapeshellarg($cliScript) . " " . (int)$trendId . " > /dev/null 2>&1 &";
                    @exec($cmd);

                    header('Location: ' . url('admin/articles/?started=' . $trendId));
                    exit;
                } catch (\Throwable $e) {
                    $message = "Error initiating article review: " . $e->getMessage();
                    $messageType = "danger";
                }
            } elseif ($action === 'approve') {
                TrendService::markStatus($trendId, 'approved', ['trend_score' => 95]);
                $message = "Trend #{$trendId} manually approved for AI article generation.";
            } elseif ($action === 'reject') {
                TrendService::markStatus($trendId, 'rejected');
                $message = "Trend #{$trendId} rejected.";
            } elseif ($action === 'reanalyze') {
                TrendService::markStatus($trendId, 'detected');
                $message = "Trend #{$trendId} reset to detected for re-analysis.";
            }
        }
    }
}

// Auto-heal any stale analyzing trends older than 3 minutes
try {
    Database::query("UPDATE trends SET status = 'detected' WHERE status = 'analyzing' AND analyzed_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
} catch (Throwable $e) {}

// Fetch Trends with Pagination & Filter
$statusFilter = Sanitizer::string($_GET['status'] ?? '');
$params = [];
$where = [];

if ($statusFilter !== '') {
    if ($statusFilter === 'approved') {
        $where[] = "status IN ('approved', 'analyzing', 'generating')";
    } else {
        $where[] = "status = :status";
        $params['status'] = $statusFilter;
    }
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$total = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends {$whereClause}", $params);
$trends = Database::fetchAll("SELECT * FROM trends {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
$totalPages = ceil($total / $perPage);

// Stats
$detectedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'detected'");
$approvedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status IN ('approved', 'generating')");
$analyzingCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'analyzing'");
$generatingCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'generating'");
$publishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'published'");
$rejectedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'rejected'");

$today = date('Y-m-d');
// Only count AI-generated autonomous articles for quota display (manual articles are unlimited and exempt)
$todayAutoPublishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE DATE(published_at) = :today AND status = 'published' AND ai_generated = 1", ['today' => $today]);
$todayManualPublishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE DATE(published_at) = :today AND status = 'published' AND (ai_generated = 0 OR ai_generated IS NULL)", ['today' => $today]);
$completedSlotsToday = \App\Services\AutoCronService::getCompletedSlotsTodayCount();
$breakingCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'published' AND (trend_score >= 95 OR source LIKE '%statutory%' OR source LIKE '%nta%' OR source LIKE '%ssc%' OR source LIKE '%upsc%')");

include dirname(__DIR__) . '/components/header.php';
?>

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes pulseGlow { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
</style>

<!-- Trends Engine Explainer & Guide Banner -->
<div style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); color: #ffffff; border-radius: 12px; padding: 1.5rem 1.75rem; margin-bottom: 1.75rem; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);">
    <div style="display: flex; align-items: flex-start; gap: 1rem;">
        <div style="background: rgba(255,255,255,0.12); padding: 0.75rem; border-radius: 10px; color: #38bdf8; display: flex;">
            <?= icon('trending-up', 'icon-lg') ?>
        </div>
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.4rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #ffffff;">
                    Sarkari.online Editorial Radar &amp; Trend Intelligence Hub
                </h2>
                <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.8125rem; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 10px #10b981;"></span>
                    24/7 Smart Editorial Auto-Pilot Active
                </div>
            </div>
            <p style="font-size: 0.9rem; color: #cbd5e1; line-height: 1.6; margin: 0 0 0.85rem 0;">
                Real-time statutory notices (NTA, UPSC, SSC, CBSE, State Boards). System auto-publishes <strong>3 articles per day</strong> at fixed slots: <strong>10:00 AM, 2:00 PM, 6:00 PM IST</strong>. Manual publishing by admin is always unlimited and never counted against the daily quota.
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.8125rem; color: #94a3b8; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.75rem;">
                <div>🔴 <strong>Official Breaking</strong>: High-priority trend → placed at top of approved queue → publishes at next slot.</div>
                <div>🟢 <strong>Daily Scheduled</strong>: Auto publishes 1 article per slot (max 3/day: 10 AM, 2 PM, 6 PM).</div>
                <div>⚡ <strong>Publish Now</strong>: Manual admin publish — unlimited, never blocked.</div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Counter Grid -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card" style="border-left: 4px solid <?= $completedSlotsToday >= 3 ? '#16a34a' : '#2563eb' ?>;">
        <span class="stat-card-label" style="color: <?= $completedSlotsToday >= 3 ? '#16a34a' : '#2563eb' ?>;">Auto Slots Today</span>
        <span class="stat-card-num" style="color: <?= $completedSlotsToday >= 3 ? '#16a34a' : '#2563eb' ?>;"><?= $completedSlotsToday ?> / 3 <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">(<?= $todayAutoPublishedCount ?> published)</span></span>
        <span style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-top: 2px;">
            <?= $completedSlotsToday === 1 ? 'Slot 1 Done · Next: Slot 2 at 2 PM IST' : ($completedSlotsToday === 2 ? 'Slots 1 & 2 Done · Next: Slot 3 at 6 PM IST' : ($completedSlotsToday >= 3 ? 'All 3 Slots Complete Today' : 'Next: Slot 1 at 10 AM IST')) ?>
        </span>
    </div>
    <?php if ($todayManualPublishedCount > 0): ?>
    <div class="stat-card" style="border-left: 4px solid #7c3aed;">
        <span class="stat-card-label" style="color: #7c3aed;">Manual (Admin) Today</span>
        <span class="stat-card-num" style="color: #7c3aed;"><?= $todayManualPublishedCount ?></span>
        <span style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-top: 2px;">Never blocks auto slots</span>
    </div>
    <?php endif; ?>
    <?php if ($generatingCount > 0): ?>
        <div class="stat-card" style="border: 2px solid #f59e0b; background: #fffbeb;">
            <span class="stat-card-label" style="color: #b45309; font-weight: 800;">⚙️ Generating Live</span>
            <span class="stat-card-num" style="color: #d97706; animation: pulseGlow 1.5s infinite;"><?= $generatingCount ?></span>
        </div>
    <?php endif; ?>
    <div class="stat-card">
        <span class="stat-card-label">Approved Queue (Lean)</span>
        <span class="stat-card-num" style="color: #16a34a;"><?= $approvedCount ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Detected (Waiting)</span>
        <span class="stat-card-num" style="color: #0284c7;"><?= $detectedCount ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Total Discovered</span>
        <span class="stat-card-num" style="color: #475569;"><?= $total ?></span>
    </div>
</div>


<!-- Top Action Header & Filter Tabs -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
            Discovered Topic Feeds
        </h3>
        <span style="color: var(--text-muted); font-size: 0.8125rem;">
            Real-time examination notices ingested from official government portals.
        </span>
    </div>
    <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
        <a href="<?= url('admin/trends/') ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?>">All (<?= $total ?>)</a>
        <a href="<?= url('admin/trends/?status=approved') ?>" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-outline' ?>">Approved (<?= $approvedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=detected') ?>" class="btn btn-sm <?= $statusFilter === 'detected' ? 'btn-primary' : 'btn-outline' ?>">Detected (<?= $detectedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=published') ?>" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline' ?>">Published (<?= $publishedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=rejected') ?>" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-outline' ?>">Rejected (<?= $rejectedCount ?>)</a>
        <form method="POST" style="display: inline-block; margin: 0;">
            <?= CSRF::input() ?>
            <button type="submit" name="action" value="purge_duplicates" class="btn btn-sm btn-outline" style="color: #64748b; border-color: #cbd5e1; font-weight: 600;" title="Purge duplicate rejected entries to keep database clean" onclick="return confirm('Purge redundant duplicate trend records from database?');">
                🧹 Purge Duplicates
            </button>
        </form>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.25rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<!-- Trends Table Box -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table" style="margin-bottom: 0; width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700; width: 40%;">Topic &amp; Official URL</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700; text-align: center;">Score</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700;">Source Portal</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700;">Category</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700;">Detected Time</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700;">Status</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #475569; font-weight: 700; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trends)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                            No topic trends found matching the selected filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trends as $t): 
                        $isBreaking = TrendService::isOfficialBreaking($t);
                    ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.85rem 1rem; vertical-align: middle;">
                                <div style="margin-bottom: 0.35rem;">
                                    <?php if ($isBreaking): ?>
                                        <span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-size: 0.68rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>🔴 OFFICIAL BREAKING
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.68rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
                                            🟢 DAILY SCHEDULED
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <strong style="color: var(--text-main); font-size: 0.925rem; line-height: 1.4; display: block;">
                                    <?= e($t['keyword']) ?>
                                </strong>
                                <?php 
                                $officialAuth = \App\Services\AuthorityFactFetcherService::resolveAuthority($t['keyword'], $t['url'] ?? '');
                                ?>
                                <div style="font-size: 0.75rem; margin-top: 0.35rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <?php if (!empty($officialAuth['portal'])): ?>
                                        <a href="<?= e($officialAuth['portal']) ?>" target="_blank" rel="noopener noreferrer" style="color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 4px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="<?= e($officialAuth['name']) ?>">
                                            🏛️ <?= e(parse_url($officialAuth['portal'], PHP_URL_HOST)) ?> <?= icon('external-link', 'icon-xs') ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($t['url'])): ?>
                                        <a href="<?= e($t['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: #64748b; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 3px; text-decoration: none;" title="News Wire Source">
                                            <span>(Wire: <?= e(parse_url($t['url'], PHP_URL_HOST)) ?>)</span>
                                            <?= icon('external-link', 'icon-xs') ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center; vertical-align: middle;">
                                <?php
                                $s = (int)$t['trend_score'];
                                $sBg = $s >= 80 ? '#dcfce7; color: #15803d;' : ($s >= 60 ? '#fef3c7; color: #b45309;' : '#f1f5f9; color: #475569;');
                                ?>
                                <span class="badge" style="background: <?= $sBg ?> font-weight: 800; font-size: 0.8rem; padding: 3px 8px; border-radius: 6px;">
                                    <?= $s ?>
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; vertical-align: middle;">
                                <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                    <?= e($t['source']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; vertical-align: middle;">
                                <span class="badge badge-secondary" style="font-size: 0.75rem;">
                                    <?= e($t['category_hint'] ?: 'General') ?>
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; font-size: 0.785rem; color: var(--text-muted); vertical-align: middle; white-space: nowrap;">
                                <?= date('d M Y, h:i A', strtotime($t['detected_at'])) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; vertical-align: middle;">
                                <?php if ($t['status'] === 'analyzing'): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 800; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="display: inline-block; width: 8px; height: 8px; border: 2px solid #0369a1; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                                        ANALYZING TOPIC...
                                    </span>
                                <?php elseif ($t['status'] === 'generating'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; font-weight: 800; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="display: inline-block; width: 8px; height: 8px; border: 2px solid #b45309; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                                        GENERATING ARTICLE...
                                    </span>
                                <?php elseif ($t['status'] === 'published'): ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-weight: 800; font-size: 0.75rem; padding: 3px 8px; border-radius: 6px;">
                                        ✅ PUBLISHED
                                    </span>
                                <?php else: 
                                    $statusStyles = [
                                        'detected' => 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                                        'analyzing' => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                        'approved' => 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;',
                                        'rejected' => 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
                                        'generated' => 'background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;',
                                        'failed' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'
                                    ];
                                    $badgeStyle = $statusStyles[$t['status']] ?? 'background: #f1f5f9; color: #475569;';
                                ?>
                                    <span class="badge" style="<?= $badgeStyle ?> font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;">
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; vertical-align: middle; white-space: nowrap;">
                                <?php if ($t['status'] === 'analyzing'): ?>
                                    <span style="font-size: 0.75rem; color: #b45309; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        Writing Article &amp; Thumbnail...
                                    </span>
                                <?php elseif ($t['status'] === 'published'): ?>
                                    <?php 
                                    $pubArticle = \App\Database\Database::fetchOne("SELECT slug FROM articles WHERE trend_id = :tid LIMIT 1", ['tid' => $t['id']]);
                                    ?>
                                    <?php if ($pubArticle): ?>
                                        <a href="<?= e(url('article/' . $pubArticle['slug'] . '/')) ?>" target="_blank" class="btn btn-xs btn-outline" style="color: #0284c7; border-color: #0284c7; font-weight: 700; display: inline-flex; align-items: center; gap: 3px;">
                                            <?= icon('external-link', 'icon-xs') ?> View Live
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                        <?= CSRF::input() ?>
                                        <input type="hidden" name="trend_id" value="<?= $t['id'] ?>">

                                        <?php if ($t['status'] === 'approved' || $t['status'] === 'detected'): ?>
                                            <button type="submit" name="action" value="publish_now" class="btn btn-xs btn-success" style="font-weight: 700; display: inline-flex; align-items: center; gap: 3px; background: #16a34a; border-color: #15803d;">
                                                ⚡ Publish Now
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($t['status'] === 'detected' || $t['status'] === 'rejected'): ?>
                                            <button type="submit" name="action" value="approve" class="btn btn-xs btn-primary">
                                                Approve
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($t['status'] === 'detected' || $t['status'] === 'approved'): ?>
                                            <button type="submit" name="action" value="reject" class="btn btn-xs btn-outline" style="color: var(--color-danger);" onclick="return confirm('Reject this topic?');">
                                                Reject
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($t['status'] === 'failed'): ?>
                                            <button type="submit" name="action" value="reanalyze" class="btn btn-xs btn-secondary">
                                                Retry
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination-container" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= url('admin/trends/?page=' . $i . ($statusFilter ? '&status=' . urlencode($statusFilter) : '')) ?>" 
               class="btn btn-sm <?= $page === $i ? 'btn-primary' : 'btn-outline' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
