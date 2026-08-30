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
            $message = "Cleaned {$cleaned} repetitive topics from the approved queue.";
            $messageType = 'success';
        } elseif ($trendId > 0) {
            if ($action === 'publish_now') {
                try {
                    TrendService::markStatus($trendId, 'approved', ['trend_score' => 99]);
                    $pipeline = new PipelineService();
                    $res = $pipeline->generateFromTrend($trendId);
                    if (!empty($res['success']) && !empty($res['article_id'])) {
                        $pubService = new PublishingService();
                        $pubRes = $pubService->publish((int)$res['article_id']);
                        if (!empty($pubRes['success'])) {
                            $message = "⚡ Trend #{$trendId} generated and published live immediately!";
                            $messageType = "success";
                        } else {
                            $message = "Draft generated as Article #{$res['article_id']} but held in review: " . implode(', ', $pubRes['reasons'] ?? []);
                            $messageType = "warning";
                        }
                    } else {
                        $message = "Generation failed: " . ($res['error'] ?? 'Unknown error');
                        $messageType = "danger";
                    }
                } catch (\Throwable $e) {
                    $message = "Error publishing trend: " . $e->getMessage();
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

// Fetch Trends with Pagination & Filter
$statusFilter = Sanitizer::string($_GET['status'] ?? '');
$params = [];
$where = [];

if ($statusFilter !== '') {
    $where[] = "status = :status";
    $params['status'] = $statusFilter;
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
$approvedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
$publishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'published'");
$rejectedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'rejected'");

$today = date('Y-m-d');
$todayPublishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE DATE(published_at) = :today AND status = 'published'", ['today' => $today]);
$breakingCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'published' AND (trend_score >= 95 OR source LIKE '%statutory%' OR source LIKE '%nta%' OR source LIKE '%ssc%' OR source LIKE '%upsc%')");

include dirname(__DIR__) . '/components/header.php';
?>

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
                Real-time statutory notices (NTA, UPSC, SSC, CBSE, State Boards). System maintains <strong>5 Daily Diverse Pillars</strong> (Jobs, Entrance, Results, Tech, Scholarships) and gives <strong>Instant Fast-Track Pass</strong> to Official Breaking Alerts!
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.8125rem; color: #94a3b8; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.75rem;">
                <div>🔴 <strong>Official Breaking</strong>: Instant government notification (bypasses daily cap).</div>
                <div>🟢 <strong>Daily Scheduled</strong>: 5 diverse daily pillar guides.</div>
                <div>⚡ <strong>Publish Now</strong>: Manual 1-click generation and live publishing.</div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Counter Grid -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <span class="stat-card-label">Daily Regular Quota</span>
        <span class="stat-card-num" style="color: #1e3a8a;"><?= $todayPublishedCount ?> / 5</span>
    </div>
    <div class="stat-card">
        <span class="stat-card-label">Official Breaking</span>
        <span class="stat-card-num" style="color: #dc2626;"><?= $breakingCount ?></span>
    </div>
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
        <a href="<?= url('admin/trends/?status=detected') ?>" class="btn btn-sm <?= $statusFilter === 'detected' ? 'btn-primary' : 'btn-outline' ?>">Detected (<?= $detectedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=approved') ?>" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-outline' ?>">Approved (<?= $approvedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=published') ?>" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline' ?>">Published (<?= $publishedCount ?>)</a>
        <a href="<?= url('admin/trends/?status=rejected') ?>" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-outline' ?>">Rejected (<?= $rejectedCount ?>)</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.25rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<!-- Trends Table Box -->
<div class="admin-table-box">
    <div style="overflow-x: auto;">
        <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
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
                                <?php if (!empty($t['url'])): ?>
                                    <div style="font-size: 0.75rem; margin-top: 0.25rem;">
                                        <a href="<?= e($t['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                            <span><?= e(mb_substr($t['url'], 0, 48)) ?>...</span>
                                            <?= icon('external-link', 'icon-xs') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
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
                                <?= date('d M Y, H:i', strtotime($t['detected_at'])) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; vertical-align: middle;">
                                <?php
                                $statusStyles = [
                                    'detected' => 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;',
                                    'analyzing' => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                    'approved' => 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;',
                                    'rejected' => 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
                                    'generated' => 'background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe;',
                                    'published' => 'background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;',
                                    'failed' => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'
                                ];
                                $badgeStyle = $statusStyles[$t['status']] ?? 'background: #f1f5f9; color: #475569;';
                                ?>
                                <span class="badge" style="<?= $badgeStyle ?> font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; vertical-align: middle; white-space: nowrap;">
                                <form method="POST" style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                    <?= CSRF::input() ?>
                                    <input type="hidden" name="trend_id" value="<?= $t['id'] ?>">

                                    <?php if ($t['status'] === 'approved' || $t['status'] === 'detected'): ?>
                                        <button type="submit" name="action" value="publish_now" class="btn btn-xs btn-success" style="font-weight: 700; display: inline-flex; align-items: center; gap: 3px; background: #16a34a; border-color: #15803d;" onclick="return confirm('Generate and publish this article live immediately?');">
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
