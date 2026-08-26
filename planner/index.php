<?php
/**
 * Sarkari.online - Standalone Keyword Volume & Search Intelligence Planner
 * Ultra-Clean Light Theme UI calibrated strictly to Google Keyword Planner Data & Brackets.
 */

// 1. Strict IP Whitelist Guard (Owner Network Only)
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains($clientIp, ',')) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

$isOwnerIp = str_starts_with($clientIp, '38.254.176.')
    || str_starts_with($clientIp, '152.58.')
    || str_starts_with($clientIp, '20.1.1.')
    || in_array($clientIp, ['127.0.0.1', '::1', 'localhost'], true);

// Fallback access via secret key ?key=sarkari_secret_planner
$secretKey = $_GET['key'] ?? '';
if (!$isOwnerIp && $secretKey !== 'sarkari_secret_planner') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{font-family:sans-serif;background:#f8fafc;color:#475569;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;}h1{color:#e11d48;font-size:2rem;margin-bottom:0.5rem;}p{font-size:1rem;color:#64748b;}</style></head><body><div><h1>403 Forbidden</h1><p>Private Internal Tool — Access Restricted.</p></div></body></html>';
    exit;
}

// 2. Load Core Configuration & Services
require_once dirname(__DIR__) . '/config.php';
use App\Services\KeywordPlannerService;

$query = trim($_POST['q'] ?? $_GET['q'] ?? '');
$results = null;

if (!empty($query)) {
    $results = KeywordPlannerService::analyzeKeyword($query, 'IN');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keyword Planner &amp; Search Volume Intelligence — Sarkari.online</title>
    <!-- Strict De-indexing Directives -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --border-subtle: #f1f5f9;
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --primary-light: #e8f0fe;
            --text-main: #202124;
            --text-muted: #5f6368;
            --text-light: #80868b;
            --success: #137333;
            --success-bg: #e6f4ea;
            --warning: #b06000;
            --warning-bg: #fef7e0;
            --danger: #c5221f;
            --danger-bg: #fce8e6;
            --shadow-sm: 0 1px 2px rgba(60, 64, 67, 0.08);
            --shadow-md: 0 1px 3px 0 rgba(60, 64, 67, 0.15), 0 4px 8px 3px rgba(60, 64, 67, 0.05);
            --shadow-lg: 0 4px 12px rgba(60, 64, 67, 0.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); line-height: 1.5; padding-bottom: 4rem; -webkit-font-smoothing: antialiased; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        
        /* Top Navigation */
        .navbar { background: #ffffff; border-bottom: 1px solid var(--border-color); padding: 0.85rem 0; position: sticky; top: 0; z-index: 50; box-shadow: var(--shadow-sm); }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; color: var(--text-main); font-weight: 800; font-size: 1.15rem; }
        .logo-icon { background: #e8f0fe; color: var(--primary); width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 1px solid #d2e3fc; }
        .logo span { color: var(--primary); }
        .badge-ip { background: var(--success-bg); border: 1px solid #ceead6; color: var(--success); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .badge-ip-dot { width: 7px; height: 7px; border-radius: 50%; background: #137333; }

        /* Hero Search Area */
        .hero { padding: 3rem 0 2rem; text-align: center; }
        .hero-badge { display: inline-flex; align-items: center; gap: 6px; background: #e8f0fe; border: 1px solid #d2e3fc; color: var(--primary); font-size: 0.8125rem; font-weight: 700; padding: 0.35rem 0.85rem; border-radius: 20px; margin-bottom: 0.85rem; }
        .hero h1 { font-size: 2.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.4rem; letter-spacing: -0.02em; }
        .hero p { color: var(--text-muted); font-size: 1rem; max-width: 620px; margin: 0 auto 1.75rem; }

        .search-box-wrap { max-width: 760px; margin: 0 auto 1.25rem; }
        .search-form { display: flex; gap: 0.5rem; background: #ffffff; border: 2px solid #dadce0; border-radius: 14px; padding: 0.4rem 0.5rem; box-shadow: var(--shadow-lg); transition: all 0.2s; }
        .search-form:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.15); }
        .search-input { flex: 1; background: transparent; border: none; outline: none; color: var(--text-main); font-size: 1.05rem; font-weight: 600; padding: 0.75rem 1rem; }
        .search-input::placeholder { color: var(--text-light); font-weight: 400; }
        .search-btn { background: var(--primary); color: #ffffff; border: none; outline: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; padding: 0 1.75rem; cursor: pointer; transition: background 0.15s, transform 0.1s; }
        .search-btn:hover { background: var(--primary-hover); }
        .search-btn:active { transform: scale(0.98); }

        /* Quick Search Pills */
        .quick-pills { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; }
        .quick-pill { background: #ffffff; border: 1px solid var(--border-color); color: var(--text-muted); text-decoration: none; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8125rem; font-weight: 600; transition: all 0.15s; box-shadow: var(--shadow-sm); }
        .quick-pill:hover { background: var(--primary-light); border-color: #d2e3fc; color: var(--primary); }

        /* Broaden Search Box */
        .broaden-box { background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; box-shadow: var(--shadow-sm); }
        .broaden-label { font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
        .broaden-pill { background: #f1f3f4; border: 1px solid #dadce0; color: var(--text-main); text-decoration: none; padding: 0.25rem 0.75rem; border-radius: 16px; font-size: 0.8125rem; font-weight: 600; transition: all 0.15s; }
        .broaden-pill:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

        /* Metric KPI Cards */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .metric-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--primary); }
        .metric-card.accent-green::before { background: var(--success); }
        .metric-card.accent-amber::before { background: var(--warning); }
        .metric-card.accent-purple::before { background: #8430ce; }
        .metric-label { font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.4rem; }
        .metric-val { font-size: 2rem; font-weight: 800; color: var(--text-main); line-height: 1.1; margin-bottom: 0.35rem; letter-spacing: -0.02em; }
        .metric-sub { font-size: 0.8125rem; color: var(--text-muted); font-weight: 500; }

        /* Competition Badges */
        .badge-comp { display: inline-flex; align-items: center; gap: 6px; padding: 0.35rem 0.85rem; border-radius: 8px; font-weight: 800; font-size: 0.8125rem; }
        .badge-comp-low { background: var(--success-bg); color: var(--success); border: 1px solid #ceead6; }
        .badge-comp-med { background: var(--warning-bg); color: var(--warning); border: 1px solid #feefc3; }
        .badge-comp-high { background: var(--danger-bg); color: var(--danger); border: 1px solid #fad2cf; }

        /* Chart Card */
        .chart-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem; }
        .chart-title { font-size: 1.15rem; font-weight: 800; color: var(--text-main); }
        .bar-chart-container { display: flex; align-items: flex-end; gap: 1rem; height: 180px; padding: 1rem 0 0; border-bottom: 1px solid var(--border-color); }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 0.5rem; }
        .bar-fill { width: 100%; background: linear-gradient(180deg, #1a73e8 0%, #8ab4f8 100%); border-radius: 6px 6px 0 0; transition: height 0.5s ease-out; min-height: 8px; position: relative; }
        .bar-fill:hover { background: linear-gradient(180deg, #1557b0 0%, #4285f4 100%); }
        .bar-fill:hover::after { content: attr(data-val); position: absolute; top: -30px; left: 50%; transform: translateX(-50%); background: #202124; color: #ffffff; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 5px; white-space: nowrap; box-shadow: var(--shadow-md); }
        .bar-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 0.5rem; }

        /* Table Card (Google Keyword Planner Style) */
        .table-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; overflow: hidden; box-shadow: var(--shadow-md); }
        .table-wrap { overflow-x: auto; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        th { padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 2px solid var(--border-color); background: #f8fafc; }
        td { padding: 0.95rem 1rem; border-bottom: 1px solid var(--border-subtle); color: var(--text-main); font-weight: 500; }
        tr:hover td { background: #f8fafc; }
        .btn-copy { background: #f1f3f4; color: var(--text-main); border: 1px solid #dadce0; padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
        .btn-copy:hover { background: var(--primary); color: #ffffff; border-color: var(--primary); }

        /* Google Autocomplete Pills */
        .suggestions-box { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .suggestions-title { font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); margin-right: 0.5rem; }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="<?= url('planner/') ?>" class="logo">
                <div class="logo-icon">📊</div>
                Sarkari <span>Keyword Planner</span>
            </a>
            <div class="badge-ip">
                <span class="badge-ip-dot"></span>
                Authorized IP: <?= e($clientIp) ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Search Section -->
        <div class="hero">
            <div class="hero-badge">
                <span>⚡</span> Google Keyword Planner Data &amp; Search Volume Engine
            </div>
            <h1>Google Search Volume &amp; Traffic Estimator</h1>
            <p>Accurate Google Ads Keyword Planner brackets, monthly search volumes, competition levels, and related keyword ideas.</p>

            <div class="search-box-wrap">
                <form action="<?= url('planner/') ?>" method="POST" class="search-form">
                    <input type="text" name="q" class="search-input" placeholder="e.g. UGC Notifications, NEET UG 2026, NSP Scholarship..." value="<?= e($query) ?>" required autofocus>
                    <button type="submit" class="search-btn">Get Results</button>
                </form>
            </div>

            <!-- Quick Suggestions -->
            <div class="quick-pills">
                <span style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600; align-self: center;">Popular:</span>
                <a href="?q=UGC+Notifications" class="quick-pill">UGC Notifications</a>
                <a href="?q=NEET+UG+2026" class="quick-pill">NEET UG 2026</a>
                <a href="?q=JEE+Main+2026" class="quick-pill">JEE Main 2026</a>
                <a href="?q=UPSC+CSE+Notification" class="quick-pill">UPSC CSE 2026</a>
                <a href="?q=NSP+Scholarship+2026" class="quick-pill">NSP Scholarship</a>
                <a href="?q=SSC+CGL+Syllabus" class="quick-pill">SSC CGL Syllabus</a>
            </div>
        </div>

        <?php if ($results): ?>
            <!-- Broaden Your Search Pills (Google Keyword Planner Style) -->
            <?php if (!empty($results['broaden_search_tags'])): ?>
                <div class="broaden-box">
                    <span class="broaden-label">Broaden your search:</span>
                    <?php foreach ($results['broaden_search_tags'] as $tag): 
                        $cleanTag = trim(ltrim($tag, '+'));
                    ?>
                        <a href="?q=<?= urlencode($cleanTag) ?>" class="broaden-pill">
                            <?= e($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Results Section -->
            <div style="margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em;">
                            "<?= e($results['keyword']) ?>"
                        </h2>
                        <span style="font-size: 0.8125rem; color: var(--text-muted);">Target: Google Search India (gl=IN) &bull; Official Keyword Planner Metrics</span>
                    </div>
                    <div>
                        <span class="badge-comp <?= $results['competition'] === 'Low' ? 'badge-comp-low' : ($results['competition'] === 'High' ? 'badge-comp-high' : 'badge-comp-med') ?>">
                            <?= e($results['competition']) ?> Competition (<?= $results['competition_index'] ?>/100)
                        </span>
                    </div>
                </div>

                <!-- 4 Metric KPI Cards (Google Planner Brackets) -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Avg. Monthly Searches (Google Bracket)</div>
                        <div class="metric-val" style="color: var(--primary);"><?= e($results['range_bracket']) ?></div>
                        <div class="metric-sub">~<?= number_format($results['monthly_searches']) ?> exact searches / month</div>
                    </div>
                    <div class="metric-card accent-green">
                        <div class="metric-label">Three-Month &amp; YoY Change</div>
                        <div class="metric-val" style="color: var(--success); font-size: 1.75rem;">
                            <?= e($results['three_month_change']) ?> / <?= e($results['yoy_change']) ?>
                        </div>
                        <div class="metric-sub">Quarterly / Annual Search Volume Trend</div>
                    </div>
                    <div class="metric-card accent-amber">
                        <div class="metric-label">Top of Page Bid (Low Range)</div>
                        <div class="metric-val" style="color: var(--warning);">₹<?= number_format($results['cpc_low'], 2) ?></div>
                        <div class="metric-sub">Estimated minimum advertiser bid</div>
                    </div>
                    <div class="metric-card accent-purple">
                        <div class="metric-label">Top of Page Bid (High Range)</div>
                        <div class="metric-val" style="color: #8430ce;">₹<?= number_format($results['cpc_high'], 2) ?></div>
                        <div class="metric-sub">Commercial advertiser value</div>
                    </div>
                </div>

                <!-- 12-Month Trend Bar Chart -->
                <?php 
                $maxTrend = !empty($results['monthly_trend']) ? max($results['monthly_trend']) : 1;
                ?>
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">📊 12-Month Search Volume Trend (Seasonality Breakdown)</div>
                        <span style="font-size: 0.8125rem; color: var(--text-muted);">Sept 2025 – Aug 2026 Historical Curve</span>
                    </div>
                    <div class="bar-chart-container">
                        <?php foreach ($results['monthly_trend'] as $idx => $vol): 
                            $heightPct = max(8, round(($vol / $maxTrend) * 100));
                            $monthLabel = $results['months_labels'][$idx] ?? ('M' . ($idx + 1));
                        ?>
                            <div class="bar-col">
                                <div class="bar-fill" style="height: <?= $heightPct ?>%;" data-val="<?= number_format($vol) ?>"></div>
                                <span class="bar-label"><?= e($monthLabel) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Google Suggest Pills -->
                <?php if (!empty($results['google_suggestions'])): ?>
                    <div class="chart-card" style="padding: 1.25rem 1.75rem;">
                        <div class="suggestions-box">
                            <span class="suggestions-title">⚡ Google Autocomplete Expansions:</span>
                            <?php foreach ($results['google_suggestions'] as $sug): ?>
                                <a href="?q=<?= urlencode($sug) ?>" class="quick-pill">
                                    🔍 <?= e($sug) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Related High-Traffic Keyword Opportunities Table (Google Ads Keyword Planner Columns) -->
                <?php if (!empty($results['related_ideas'])): ?>
                    <div class="table-card" style="margin-top: 2rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main);">
                                💡 Keyword Ideas (<?= count($results['related_ideas']) ?> available)
                            </h3>
                            <span style="font-size: 0.8125rem; color: var(--text-muted);">Google Keyword Planner Match</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Keyword (by relevance)</th>
                                        <th>Avg. Monthly Searches</th>
                                        <th>Three Month Change</th>
                                        <th>YoY Change</th>
                                        <th>Competition</th>
                                        <th>Top of page bid</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Seed Keyword Row -->
                                    <tr style="background: #e8f0fe;">
                                        <td style="font-weight: 800; color: var(--primary);">
                                            <?= e($results['keyword']) ?>
                                            <span style="font-size: 0.7rem; background: var(--primary); color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Provided</span>
                                        </td>
                                        <td><strong><?= e($results['range_bracket']) ?></strong></td>
                                        <td><?= e($results['three_month_change']) ?></td>
                                        <td><?= e($results['yoy_change']) ?></td>
                                        <td>
                                            <span class="badge-comp badge-comp-low" style="padding: 2px 8px; font-size: 0.75rem;">
                                                <?= e($results['competition']) ?>
                                            </span>
                                        </td>
                                        <td>₹<?= number_format($results['cpc_low'], 2) ?> – ₹<?= number_format($results['cpc_high'], 2) ?></td>
                                        <td style="text-align: right;">
                                            <button onclick="navigator.clipboard.writeText('<?= addslashes($results['keyword']) ?>'); this.innerText='Copied!';" class="btn-copy">
                                                Copy
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Related Ideas Rows -->
                                    <?php foreach ($results['related_ideas'] as $idea): 
                                        $kw = is_array($idea) ? ($idea['keyword'] ?? '') : (string)$idea;
                                        $bracket = is_array($idea) ? ($idea['range_bracket'] ?? '1K - 10K') : '1K - 10K';
                                        $threeM = is_array($idea) ? ($idea['three_month_change'] ?? '0%') : '0%';
                                        $yoy = is_array($idea) ? ($idea['yoy_change'] ?? '0%') : '0%';
                                        $comp = is_array($idea) ? ($idea['competition'] ?? 'Low') : 'Low';
                                        $cpc = is_array($idea) ? (float)($idea['cpc_inr'] ?? 0) : 0;
                                    ?>
                                        <tr>
                                            <td style="font-weight: 700; color: var(--text-main);">
                                                <a href="?q=<?= urlencode($kw) ?>" style="color: var(--primary); text-decoration: none;">
                                                    <?= e($kw) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong><?= e($bracket) ?></strong>
                                            </td>
                                            <td><?= e($threeM) ?></td>
                                            <td><?= e($yoy) ?></td>
                                            <td>
                                                <span class="badge-comp <?= strtolower($comp) === 'low' ? 'badge-comp-low' : (strtolower($comp) === 'high' ? 'badge-comp-high' : 'badge-comp-med') ?>" style="padding: 2px 8px; font-size: 0.75rem;">
                                                    <?= e($comp) ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 600;">₹<?= number_format($cpc, 2) ?></td>
                                            <td style="text-align: right;">
                                                <button onclick="navigator.clipboard.writeText('<?= addslashes($kw) ?>'); this.innerText='Copied!';" class="btn-copy">
                                                    Copy
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

</body>
</html>
