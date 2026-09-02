<?php
/**
 * EduPulse - Admin System Settings Console (Phase 9)
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Env;
use App\Helpers\Sanitizer;
use App\Services\SettingsService;

Auth::requireAuth();

$adminPageTitle = 'Platform Settings & Thresholds';
$adminPageKey = 'settings';

$message = null;
$messageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $minQuality = max(50, min(100, (int)($_POST['min_quality_score'] ?? 90)));
        $dailyLimit = max(1, min(50, (int)($_POST['auto_publish_daily_limit'] ?? 3)));
        $maxTrends = max(1, min(20, (int)($_POST['max_trends_per_run'] ?? 5)));
        $minTrendScore = max(50, min(100, (int)($_POST['min_trend_score'] ?? 75)));
        $contactEmail = Sanitizer::email($_POST['contact_email'] ?? 'official.sarkarionline@gmail.com');

        SettingsService::set('MIN_QUALITY_SCORE', $minQuality, 'integer', 'Minimum quality score required for auto-publishing');
        SettingsService::set('AUTO_PUBLISH_DAILY_LIMIT', $dailyLimit, 'integer', 'Maximum automatic articles published per day');
        SettingsService::set('MAX_TRENDS_PER_RUN', $maxTrends, 'integer', 'Max trends processed per cron iteration');
        SettingsService::set('MIN_TREND_SCORE', $minTrendScore, 'integer', 'Minimum trend discovery score threshold');
        SettingsService::set('EDITORIAL_CONTACT_EMAIL', $contactEmail, 'string', 'Editorial desk contact email');

        $message = "System settings updated successfully.";
    }
}

$curMinQuality = (int)SettingsService::get('MIN_QUALITY_SCORE', Env::get('MIN_QUALITY_SCORE', 90));
$curDailyLimit = (int)SettingsService::get('AUTO_PUBLISH_DAILY_LIMIT', Env::get('AUTO_PUBLISH_DAILY_LIMIT', 3));
$curMaxTrends = (int)SettingsService::get('MAX_TRENDS_PER_RUN', Env::get('MAX_TRENDS_PER_RUN', 5));
$curMinTrendScore = (int)SettingsService::get('MIN_TREND_SCORE', Env::get('MIN_TREND_SCORE', 75));
$curContactEmail = (string)SettingsService::get('EDITORIAL_CONTACT_EMAIL', 'official.sarkarionline@gmail.com');
if (empty($curContactEmail) || str_contains($curContactEmail, 'edupulse.in')) {
    $curContactEmail = 'official.sarkarionline@gmail.com';
    SettingsService::set('EDITORIAL_CONTACT_EMAIL', $curContactEmail, 'string', 'Editorial desk contact email');
}

include dirname(__DIR__) . '/components/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0;">Platform Automation Controls</h2>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
        Configure quality score gates, daily publishing quotas, and trend discovery parameters.
    </p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.5rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<div style="max-width: 760px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.75rem;">
    <form method="POST">
        <?= CSRF::input() ?>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">
                Minimum Quality Score for Auto-Publishing (Default: 90)
            </label>
            <input type="number" name="min_quality_score" class="form-control" min="50" max="100" value="<?= $curMinQuality ?>" required>
            <div class="form-help">Articles scoring below this 8-dimension matrix threshold will be held in the Review Queue.</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">
                Daily Auto-Publishing Quota Limit (Default: 3)
            </label>
            <input type="number" name="auto_publish_daily_limit" class="form-control" min="1" max="50" value="<?= $curDailyLimit ?>" required>
            <div class="form-help">Maximum automatic articles published per day (Paced across 3 fixed IST slots: 10:00 AM, 02:00 PM, 06:00 PM). Manual admin publishing is always unlimited.</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">
                Max Trends Processed Per Worker Iteration (Default: 5)
            </label>
            <input type="number" name="max_trends_per_run" class="form-control" min="1" max="20" value="<?= $curMaxTrends ?>" required>
            <div class="form-help">Controls batch sizes for fetch, analyze, and generation cron jobs.</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">
                Minimum Trend Viability Score (Default: 75)
            </label>
            <input type="number" name="min_trend_score" class="form-control" min="50" max="100" value="<?= $curMinTrendScore ?>" required>
            <div class="form-help">Candidate topics scoring below this will be rejected during TopicAnalyzer evaluation.</div>
        </div>

        <div class="form-group" style="margin-bottom: 1.75rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.9rem;">
                Editorial Desk Contact Email
            </label>
            <input type="email" name="contact_email" class="form-control" value="<?= e($curContactEmail) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
            Save Platform Settings
        </button>
    </form>
</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
