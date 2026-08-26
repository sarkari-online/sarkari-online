<?php
/**
 * Sarkari.online - Standalone Keyword Volume & Search Intelligence Planner
 * Private Standalone Tool: IP Protected (Strict Owner Network Only) + Robots Disallowed
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

// Optional fallback access via secret key query parameter ?key=sarkari_secret_planner
$secretKey = $_GET['key'] ?? '';
if (!$isOwnerIp && $secretKey !== 'sarkari_secret_planner') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{font-family:sans-serif;background:#0f172a;color:#94a3b8;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;}h1{color:#f87171;font-size:2rem;margin-bottom:0.5rem;}p{font-size:1rem;color:#64748b;}</style></head><body><div><h1>403 Forbidden</h1><p>Private Internal Utility — Access Restricted.</p></div></body></html>';
    exit;
}

// 2. Load Core Configuration for Gemini & Helpers
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
    <title>Sarkari.online — Keyword Intelligence &amp; Volume Planner</title>
    <!-- Strict De-indexing Directives -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0a0f1d;
            --bg-card: #131b2e;
            --bg-card-hover: #18233c;
            --border-color: #1e293b;
            --primary: #38bdf8;
            --primary-glow: rgba(56, 189, 248, 0.25);
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); line-height: 1.5; padding-bottom: 4rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        
        /* Navbar */
        .navbar { background: rgba(10, 15, 29, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-color); padding: 1rem 0; position: sticky; top: 0; z-index: 50; }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: #fff; font-weight: 800; font-size: 1.15rem; }
        .logo span { color: var(--primary); }
        .badge-ip { background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .badge-ip-dot { width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981; }

        /* Search Hero */
        .hero { padding: 3rem 0 2rem; text-align: center; }
        .hero h1 { font-size: 2.25rem; font-weight: 800; margin-bottom: 0.5rem; background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-muted); font-size: 1rem; max-width: 600px; margin: 0 auto 2rem; }

        .search-box-wrap { max-width: 750px; margin: 0 auto 1.5rem; position: relative; }
        .search-form { display: flex; gap: 0.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.4rem 0.5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35); transition: border-color 0.2s, box-shadow 0.2s; }
        .search-form:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .search-input { flex: 1; background: transparent; border: none; outline: none; color: #fff; font-size: 1.05rem; font-weight: 600; padding: 0.75rem 1rem; }
        .search-input::placeholder { color: #64748b; font-weight: 400; }
        .search-btn { background: var(--primary); color: #0a0f1d; border: none; outline: none; border-radius: 10px; font-weight: 800; font-size: 0.95rem; padding: 0 1.75rem; cursor: pointer; transition: background 0.2s, transform 0.1s; }
        .search-btn:hover { background: #7dd3fc; }
        .search-btn:active { transform: scale(0.98); }

        /* Quick Pills */
        .quick-pills { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; }
        .quick-pill { background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); color: #94a3b8; text-decoration: none; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.8125rem; font-weight: 600; transition: all 0.15s; }
        .quick-pill:hover { background: rgba(56, 189, 248, 0.1); border-color: var(--primary); color: var(--primary); }

        /* Metric Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .metric-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; position: relative; overflow: hidden; }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--primary), transparent); }
        .metric-label { font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem; }
        .metric-val { font-size: 2.25rem; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 0.35rem; }
        .metric-sub { font-size: 0.8125rem; color: #64748b; font-weight: 500; }

        /* Competition Badges */
        .badge-comp { display: inline-flex; align-items: center; gap: 6px; padding: 0.35rem 0.85rem; border-radius: 8px; font-weight: 800; font-size: 0.875rem; }
        .badge-comp-low { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge-comp-med { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge-comp-high { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        /* Chart Card */
        .chart-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem; }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .chart-title { font-size: 1.15rem; font-weight: 800; color: #fff; }
        .bar-chart-container { display: flex; align-items: flex-end; gap: 1rem; height: 200px; padding: 1rem 0 0; border-bottom: 1px solid var(--border-color); }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 0.5rem; }
        .bar-fill { width: 100%; background: linear-gradient(180deg, var(--primary) 0%, rgba(56, 189, 248, 0.3) 100%); border-radius: 6px 6px 0 0; transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1); min-height: 8px; position: relative; }
        .bar-fill:hover { background: linear-gradient(180deg, #7dd3fc 0%, rgba(125, 211, 252, 0.5) 100%); }
        .bar-fill:hover::after { content: attr(data-val); position: absolute; top: -30px; left: 50%; transform: translateX(-50%); background: #000; color: #fff; font-size: 0.75rem; font-weight: 700; padding: 3px 7px; border-radius: 5px; white-space: nowrap; }
        .bar-label { font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 0.5rem; }

        /* Table Card */
        .table-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; overflow: hidden; }
        .table-wrap { overflow-x: auto; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th { padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
        td { padding: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.04); color: #cbd5e1; font-weight: 500; }
        tr:hover td { background: var(--bg-card-hover); color: #fff; }
        .btn-copy { background: rgba(255, 255, 255, 0.08); color: #e2e8f0; border: none; padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: background 0.15s; }
        .btn-copy:hover { background: var(--primary); color: #0a0f1d; }

        /* Autocomplete Pills */
        .suggestions-box { margin-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .suggestions-title { font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); margin-right: 0.5rem; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="<?= url('planner/') ?>" class="logo">
                <span>⚡</span> Sarkari Keyword Planner
            </a>
            <div class="badge-ip">
                <span class="badge-ip-dot"></span>
                Authorized IP: <?= e($clientIp) ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Search -->
        <div class="hero">
            <h1>Google Search Volume &amp; Keyword Planner</h1>
            <p>Direct Google Search intent metrics, monthly search volumes, competition indexes, and related high-traffic search queries.</p>

            <div class="search-box-wrap">
                <form action="<?= url('planner/') ?>" method="POST" class="search-form">
                    <input type="text" name="q" class="search-input" placeholder="e.g. NEET UG 2026, UPSC Notification, NSP Scholarship..." value="<?= e($query) ?>" required autofocus>
                    <button type="submit" class="search-btn">Search Volume</button>
                </form>
            </div>

            <!-- Quick Suggestions -->
            <div class="quick-pills">
                <span style="font-size: 0.8125rem; color: #64748b; font-weight: 600; align-self: center;">Popular:</span>
                <a href="?q=NEET+UG+2026" class="quick-pill">NEET UG 2026</a>
                <a href="?q=JEE+Main+2026" class="quick-pill">JEE Main 2026</a>
                <a href="?q=UPSC+CSE+Notification" class="quick-pill">UPSC CSE 2026</a>
                <a href="?q=NSP+Scholarship+2026" class="quick-pill">NSP Scholarship</a>
                <a href="?q=UGC+PhD+Admission" class="quick-pill">UGC PhD Guidelines</a>
                <a href="?q=SSC+CGL+Syllabus" class="quick-pill">SSC CGL Syllabus</a>
            </div>
        </div>

        <?php if ($results): ?>
            <!-- Results Section -->
            <div style="margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; color: #fff;">
                            "<?= e($results['keyword']) ?>"
                        </h2>
                        <span style="font-size: 0.8125rem; color: #64748b;">Target Country: India (gl=IN) &bull; Verified Google Demand Telemetry</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <span class="badge-comp <?= $results['competition'] === 'LOW' ? 'badge-comp-low' : ($results['competition'] === 'HIGH' ? 'badge-comp-high' : 'badge-comp-med') ?>">
                            <?= e($results['competition']) ?> COMPETITION (<?= $results['competition_index'] ?>/100)
                        </span>
                    </div>
                </div>

                <!-- 4 KPI Cards -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Avg. Monthly Searches</div>
                        <div class="metric-val" style="color: #38bdf8;"><?= $results['monthly_searches_formatted'] ?></div>
                        <div class="metric-sub"><?= number_format($results['monthly_searches']) ?> exact searches / month</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Search Intent Type</div>
                        <div class="metric-val" style="color: #f59e0b; font-size: 1.75rem;"><?= e($results['search_intent']) ?></div>
                        <div class="metric-sub">Direct student search behavior pattern</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Top of Page Bid (Low Range)</div>
                        <div class="metric-val" style="color: #34d399;">₹<?= number_format($results['cpc_low'], 2) ?></div>
                        <div class="metric-sub">Estimated minimum CPC</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Top of Page Bid (High Range)</div>
                        <div class="metric-val" style="color: #a78bfa;">₹<?= number_format($results['cpc_high'], 2) ?></div>
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
                        <span style="font-size: 0.8125rem; color: #64748b;">Sept 2025 – Aug 2026 Historical Curve</span>
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
                        <div class="suggestions-box" style="margin: 0;">
                            <span class="suggestions-title">⚡ Google Autocomplete Expansions:</span>
                            <?php foreach ($results['google_suggestions'] as $sug): ?>
                                <a href="?q=<?= urlencode($sug) ?>" class="quick-pill" style="border-radius: 8px;">
                                    🔍 <?= e($sug) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Related High-Traffic Keyword Opportunities Table -->
                <?php if (!empty($results['related_ideas'])): ?>
                    <div class="table-card" style="margin-top: 2rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: #fff;">
                                💡 Related High-Intent Keyword Opportunities
                            </h3>
                            <span style="font-size: 0.8125rem; color: #64748b;">Top Related Student Searches</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Keyword Phrase</th>
                                        <th>Monthly Volume</th>
                                        <th>Competition</th>
                                        <th>Estimated CPC</th>
                                        <th>Search Intent</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['related_ideas'] as $idea): 
                                        $kw = is_array($idea) ? ($idea['keyword'] ?? '') : (string)$idea;
                                        $vol = is_array($idea) ? (int)($idea['monthly_searches'] ?? 0) : 0;
                                        $comp = is_array($idea) ? ($idea['competition'] ?? 'MEDIUM') : 'MEDIUM';
                                        $cpc = is_array($idea) ? (float)($idea['cpc_inr'] ?? 0) : 0;
                                        $intent = is_array($idea) ? ($idea['intent'] ?? 'Informational') : 'Informational';
                                    ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #fff;">
                                                <a href="?q=<?= urlencode($kw) ?>" style="color: #38bdf8; text-decoration: none;">
                                                    <?= e($kw) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong style="color: #f8fafc;"><?= number_format($vol) ?></strong>
                                                <span style="font-size: 0.75rem; color: #64748b;">/ mo</span>
                                            </td>
                                            <td>
                                                <span class="badge-comp <?= $comp === 'LOW' ? 'badge-comp-low' : ($comp === 'HIGH' ? 'badge-comp-high' : 'badge-comp-med') ?>" style="padding: 2px 8px; font-size: 0.75rem;">
                                                    <?= e($comp) ?>
                                                </span>
                                            </td>
                                            <td>₹<?= number_format($cpc, 2) ?></td>
                                            <td>
                                                <span style="background: rgba(255,255,255,0.06); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem;">
                                                    <?= e($intent) ?>
                                                </span>
                                            </td>
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
