<?php
/**
 * EduPulse - Production Admin Dashboard (Phase 9)
 * Clean, minimal layout focusing on core operational metrics
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

$slotSchedule = AutoCronService::getISTSlotSchedule();
$completedSlotsToday = AutoCronService::getCompletedSlotsTodayCount();

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

<!-- Clean Quick Status & Navigation Bar -->
<div class="admin-table-box" style="padding: 1.75rem 2rem; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 0.35rem 0; color: #0f172a;">
                <?= icon('shield-check', 'icon-md') ?> Autonomous Operations Center
            </h3>
            <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                System is running normally. Background daemons are actively scanning authority portals & trends.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= url('admin/trends/') ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                <?= icon('trending-up', 'icon-xs') ?> Trends Engine (<?= $trendsToday ?>)
            </a>
            <a href="<?= url('admin/articles/') ?>" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                <?= icon('file-text', 'icon-xs') ?> All Articles (<?= $totalArticles ?>)
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
