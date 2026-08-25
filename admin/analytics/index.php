<?php
/**
 * Sarkari.online - Live Traffic & Real-Time Analytics Dashboard
 * 100% In-House, Zero Third-Party Tracker.
 * Owner IP (38.254.176.x) & Admin sessions are automatically filtered out.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Services\AnalyticsService;
use App\Helpers\Auth;

Auth::requireAuth();

$adminPageTitle = 'Live Traffic & Analytics';
$adminPageKey = 'analytics';

if (isset($_GET['action']) && $_GET['action'] === 'clear_data') {
    \App\Database\Database::query("TRUNCATE TABLE page_views");
    header('Location: ' . url('admin/analytics/?cleared=1'));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'toggle_wifi') {
    AnalyticsService::toggleWifiFilter();
    header('Location: ' . url('admin/analytics/?toggled=wifi'));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'toggle_mobile') {
    AnalyticsService::toggleMobileFilter();
    header('Location: ' . url('admin/analytics/?toggled=mobile'));
    exit;
}

$range = $_GET['range'] ?? 'today';
$wifiFilter = AnalyticsService::isWifiFilterEnabled();
$mobileFilter = AnalyticsService::isMobileFilterEnabled();
$summary = AnalyticsService::getDashboardSummary();
$dailyTrend = AnalyticsService::getDailyTrend(14);
$topArticles = AnalyticsService::getTopArticles(10, $range);
$sources = AnalyticsService::getTrafficSources($range);
$devices = AnalyticsService::getDeviceBreakdown($range);
$recentVisitors = AnalyticsService::getRecentVisitorLogs(25);

// Calculate max views for trend bar scaling
$maxDailyViews = 1;
foreach ($dailyTrend as $d) {
    if ($d['page_views'] > $maxDailyViews) {
        $maxDailyViews = $d['page_views'];
    }
}

include dirname(__DIR__) . '/components/header.php';
?>

<div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Live Traffic &amp; Real-Time Audience</h1>
        <p style="margin: 0.25rem 0 0; color: #64748b; font-size: 0.875rem;">100% In-house telemetry tracking genuine student visitors across India.</p>
    </div>

    <!-- Time Range & Reset Action -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="?action=clear_data" onclick="return confirm('Clear all test traffic data and start fresh from 0?');" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; text-decoration: none; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">
            Reset Test Data
        </a>
        <div style="display: flex; background: #e2e8f0; border-radius: 8px; padding: 3px; gap: 2px;">
            <a href="?range=today" style="padding: 6px 14px; border-radius: 6px; font-size: 0.8125rem; font-weight: 700; text-decoration: none; <?= $range === 'today' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.08);' : 'color: #64748b;' ?>">Today</a>
            <a href="?range=7days" style="padding: 6px 14px; border-radius: 6px; font-size: 0.8125rem; font-weight: 700; text-decoration: none; <?= $range === '7days' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.08);' : 'color: #64748b;' ?>">Past 7 Days</a>
        </div>
    </div>
</div>

<!-- Independent Wi-Fi & Jio Mobile Toggle Control Panel -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Wi-Fi Toggle Card -->
    <div style="background: <?= $wifiFilter ? '#fef2f2' : '#f0fdf4' ?>; border: 1px solid <?= $wifiFilter ? '#fecaca' : '#bbf7d0' ?>; border-radius: 10px; padding: 1.15rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div>
            <div style="font-weight: 800; font-size: 0.9375rem; color: <?= $wifiFilter ? '#991b1b' : '#166534' ?>; display: flex; align-items: center; gap: 8px;">
                📶 Wi-Fi Tracking:
                <span style="font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: <?= $wifiFilter ? '#ef4444' : '#22c55e' ?>; color: #ffffff;">
                    <?= $wifiFilter ? '🔴 EXCLUDED (Not Counted)' : '🟢 INCLUDED (Tracking Active)' ?>
                </span>
            </div>
            <div style="font-size: 0.8125rem; color: #64748b; margin-top: 4px;">
                Network IP: <code>38.254.176.x</code>
            </div>
        </div>
        <a href="?action=toggle_wifi" style="padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.8125rem; text-decoration: none; background: <?= $wifiFilter ? '#16a34a' : '#dc2626' ?>; color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap;">
            <?= $wifiFilter ? '🟢 Include (Track Me)' : '🔴 Exclude (Stop Tracking)' ?>
        </a>
    </div>

    <!-- Mobile / Jio Toggle Card -->
    <div style="background: <?= $mobileFilter ? '#fef2f2' : '#f0fdf4' ?>; border: 1px solid <?= $mobileFilter ? '#fecaca' : '#bbf7d0' ?>; border-radius: 10px; padding: 1.15rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
        <div>
            <div style="font-weight: 800; font-size: 0.9375rem; color: <?= $mobileFilter ? '#991b1b' : '#166534' ?>; display: flex; align-items: center; gap: 8px;">
                📱 Jio / Mobile Tracking:
                <span style="font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: <?= $mobileFilter ? '#ef4444' : '#22c55e' ?>; color: #ffffff;">
                    <?= $mobileFilter ? '🔴 EXCLUDED (Not Counted)' : '🟢 INCLUDED (Tracking Active)' ?>
                </span>
            </div>
            <div style="font-size: 0.8125rem; color: #64748b; margin-top: 4px;">
                IPs: <code>152.58.x.x</code> &amp; <code>20.1.1.x</code>
            </div>
        </div>
        <a href="?action=toggle_mobile" style="padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.8125rem; text-decoration: none; background: <?= $mobileFilter ? '#16a34a' : '#dc2626' ?>; color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap;">
            <?= $mobileFilter ? '🟢 Include (Track Me)' : '🔴 Exclude (Stop Tracking)' ?>
        </a>
    </div>
</div>

<!-- 4 Key Performance Indicators (KPIs) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <!-- Today Unique Visitors -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Today's Unique Visitors</div>
        <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 0.35rem;">
            <span style="font-size: 2.25rem; font-weight: 900; color: #0284c7; line-height: 1;"><?= number_format($summary['today_unique']) ?></span>
            <?php if ($summary['growth_pct'] != 0): ?>
                <span style="font-size: 0.8125rem; font-weight: 700; color: <?= $summary['growth_pct'] > 0 ? '#16a34a' : '#dc2626' ?>;">
                    <?= $summary['growth_pct'] > 0 ? '↑ +' : '↓ ' ?><?= $summary['growth_pct'] ?>%
                </span>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.8125rem; color: #94a3b8; margin-top: 0.35rem;">Unique human students today</div>
    </div>

    <!-- Yesterday Unique Visitors -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Yesterday's Unique Visitors</div>
        <div style="font-size: 2.25rem; font-weight: 900; color: #334155; line-height: 1; margin-top: 0.35rem;">
            <?= number_format($summary['yesterday_unique']) ?>
        </div>
        <div style="font-size: 0.8125rem; color: #94a3b8; margin-top: 0.35rem;"><?= number_format($summary['yesterday_views']) ?> pageviews recorded</div>
    </div>

    <!-- Today's Total Pageviews -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Today's Total Pageviews</div>
        <div style="font-size: 2.25rem; font-weight: 900; color: #0f172a; line-height: 1; margin-top: 0.35rem;">
            <?= number_format($summary['today_views']) ?>
        </div>
        <div style="font-size: 0.8125rem; color: #94a3b8; margin-top: 0.35rem;">Total articles read today</div>
    </div>

    <!-- Live Online Now -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: pulse 2s infinite;"></span>
            Active Online Now
        </div>
        <div style="font-size: 2.25rem; font-weight: 900; color: #16a34a; line-height: 1; margin-top: 0.35rem;">
            <?= number_format($summary['live_now']) ?>
        </div>
        <div style="font-size: 0.8125rem; color: #94a3b8; margin-top: 0.35rem;">Active in last 15 minutes</div>
    </div>
</div>

<!-- 14-Day Traffic Trend Bar Chart -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
        <span>📊 14-Day Daily Traffic Trend</span>
        <div style="display: flex; gap: 1rem; font-size: 0.75rem; font-weight: 600; color: #64748b;">
            <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #0284c7; border-radius: 2px;"></span> Unique Visitors</span>
            <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #93c5fd; border-radius: 2px;"></span> Pageviews</span>
        </div>
    </div>

    <!-- Chart Bars Grid -->
    <div style="display: grid; grid-template-columns: repeat(14, 1fr); gap: 8px; height: 140px; align-items: flex-end; padding-top: 20px; border-bottom: 1px solid #e2e8f0;">
        <?php foreach ($dailyTrend as $d): 
            $viewHeightPct = max(8, round(($d['page_views'] / $maxDailyViews) * 100));
            $uniqueHeightPct = max(4, round(($d['unique_visitors'] / $maxDailyViews) * 100));
        ?>
            <div style="display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; position: relative;" title="<?= $d['date'] ?>: <?= $d['unique_visitors'] ?> unique, <?= $d['page_views'] ?> views">
                <div style="width: 100%; max-width: 22px; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; position: relative;">
                    <!-- Pageviews Bar (Light Blue) -->
                    <div style="width: 100%; height: <?= $viewHeightPct ?>%; background: #bae6fd; border-radius: 4px 4px 0 0; position: relative;">
                        <!-- Unique Visitors Bar (Deep Blue) -->
                        <div style="width: 100%; height: <?= min(100, round(($d['unique_visitors'] / max(1, $d['page_views'])) * 100)) ?>%; background: #0284c7; border-radius: 0 0 0 0; position: absolute; bottom: 0;"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart Date Labels -->
    <div style="display: grid; grid-template-columns: repeat(14, 1fr); gap: 8px; margin-top: 8px;">
        <?php foreach ($dailyTrend as $d): ?>
            <div style="font-size: 0.6875rem; color: #94a3b8; text-align: center; font-weight: 600;">
                <?= $d['label'] ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Grid: Top Articles & Breakdown -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Top 10 Visited Articles -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; font-size: 1rem; font-weight: 800; color: #0f172a;">
            📄 Top Visited Articles (<?= $range === 'today' ? 'Today' : 'Past 7 Days' ?>)
        </div>

        <?php if (empty($topArticles)): ?>
            <div style="padding: 2.5rem; text-align: center; color: #94a3b8; font-size: 0.875rem;">
                No external visitor article reads recorded yet for this period. As visitors arrive from Google, top articles will populate here automatically.
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                        <th style="padding: 10px 14px;">#</th>
                        <th style="padding: 10px 14px;">Article</th>
                        <th style="padding: 10px 14px;">Category</th>
                        <th style="padding: 10px 14px; text-align: right;">Unique Readers</th>
                        <th style="padding: 10px 14px; text-align: right;">Total Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topArticles as $idx => $art): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 14px; color: #94a3b8; font-weight: 700;"><?= $idx + 1 ?></td>
                            <td style="padding: 10px 14px;">
                                <a href="<?= url('article/' . $art['slug'] . '/') ?>" target="_blank" style="color: #0284c7; text-decoration: none; font-weight: 600;">
                                    <?= e($art['page_title']) ?>
                                </a>
                            </td>
                            <td style="padding: 10px 14px;">
                                <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #475569;">
                                    <?= e($art['category_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #334155;"><?= number_format($art['unique_readers']) ?></td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 800; color: #0f172a;"><?= number_format($art['total_views']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Traffic Sources & Devices Column -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Traffic Sources -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.9375rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                🧭 Traffic Sources
            </div>
            <?php if (empty($sources)): ?>
                <div style="color: #94a3b8; font-size: 0.8125rem; text-align: center; padding: 1rem 0;">No source data yet</div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($sources as $s): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 4px;">
                                <span style="font-weight: 600; color: #334155;"><?= e($s['type']) ?></span>
                                <span style="font-weight: 700; color: #0f172a;"><?= $s['percentage'] ?>% (<?= $s['count'] ?>)</span>
                            </div>
                            <div style="height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $s['percentage'] ?>%; background: #0284c7; border-radius: 999px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Devices Breakdown -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.9375rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                📱 Device Distribution
            </div>
            <?php if (empty($devices)): ?>
                <div style="color: #94a3b8; font-size: 0.8125rem; text-align: center; padding: 1rem 0;">No device data yet</div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($devices as $d): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 4px;">
                                <span style="font-weight: 600; color: #334155;"><?= e($d['device']) ?></span>
                                <span style="font-weight: 700; color: #0f172a;"><?= $d['percentage'] ?>% (<?= $d['count'] ?>)</span>
                            </div>
                            <div style="height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $d['percentage'] ?>%; background: #10b981; border-radius: 999px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Real-Time Live Visitor Log Table (with IP Addresses) -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
        <div style="font-size: 1rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: pulse 2s infinite;"></span>
            🌐 Live Visitor Stream (Real IP Addresses &amp; Activity)
        </div>
        <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Showing last 25 real-time hits</span>
    </div>

    <?php if (empty($recentVisitors)): ?>
        <div style="padding: 2.5rem; text-align: center; color: #94a3b8; font-size: 0.875rem;">
            No live visitors in stream yet. As real students browse Sarkari.online, their IP addresses, devices, and exact pages will stream here in real-time.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                        <th style="padding: 10px 14px;">Time</th>
                        <th style="padding: 10px 14px;">IP Address</th>
                        <th style="padding: 10px 14px;">Page Visited</th>
                        <th style="padding: 10px 14px;">Source</th>
                        <th style="padding: 10px 14px;">Device / Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVisitors as $v): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 14px; color: #64748b; font-size: 0.8125rem; white-space: nowrap;">
                                <?= date('h:i:s A', strtotime($v['viewed_at'])) ?>
                            </td>
                            <td style="padding: 10px 14px; font-weight: 700; font-family: monospace; color: #0284c7;">
                                <?= e($v['ip_address']) ?>
                            </td>
                            <td style="padding: 10px 14px; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <a href="<?= e($v['page_url']) ?>" target="_blank" style="color: #0f172a; text-decoration: none; font-weight: 600;">
                                    <?= e($v['page_title'] ?: $v['page_url']) ?>
                                </a>
                            </td>
                            <td style="padding: 10px 14px;">
                                <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #334155;">
                                    <?= e(ucfirst($v['referrer_type'])) ?>
                                </span>
                            </td>
                            <td style="padding: 10px 14px; color: #64748b; font-size: 0.8125rem;">
                                <?= e($v['os'] ?? 'OS') ?> • <?= e($v['browser'] ?? 'Browser') ?> (<?= e(ucfirst($v['device_type'])) ?>)
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.9); }
}
</style>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
