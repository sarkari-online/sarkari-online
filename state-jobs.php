<?php
/**
 * Sarkari.online - State Government Jobs Directory (Main Hub)
 * Hand-crafted, high-authority regional employment hub with verified official portal links.
 */

require_once __DIR__ . '/config.php';

use App\Services\StateJobService;

$allStates = StateJobService::getAllStates();
$regions = StateJobService::getStatesByRegion();

$pageTitle = 'State Govt Jobs 2026: All States Portal | ' . SITE_NAME;
$pageDesc = 'Explore latest state government jobs 2026 across UP, Bihar, Rajasthan, Delhi, MP, and all Indian states with direct official portals and verified job alerts.';
$pageKeywords = 'state govt jobs 2026, rajyawar sarkari naukri, up sarkari naukri, bihar govt jobs, rajasthan sarkari vacancy, mp vyapam jobs, haryana hssc recruitment, delhi dsssb jobs';
$canonicalUrl = url('state-jobs/');
$ogType = 'website';

// Schema JSON-LD
$hubItems = [];
foreach ($allStates as $st) {
    $hubItems[] = [
        '@type' => 'ItemPage',
        'name' => "{$st['name']} Government Jobs 2026",
        'description' => $st['meta_desc'],
        'url' => url('jobs/' . $st['slug'] . '/')
    ];
}

$schemaJson = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'CollectionPage',
            '@id' => $canonicalUrl,
            'name' => $pageTitle,
            'description' => $pageDesc,
            'url' => $canonicalUrl,
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL
            ],
            'hasPart' => $hubItems
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => SITE_URL . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'State Govt Jobs',
                    'item' => $canonicalUrl
                ]
            ]
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main state-hub-main" style="padding-top: 0; width: 100%; max-width: 100%;">

    <!-- Hero Section -->
    <section class="state-hub-hero" style="width: 100%; margin: 0;">
        <div class="container">
            <nav class="state-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= url() ?>">Home</a>
                <span class="sep" aria-hidden="true">›</span>
                <span class="curr">State Government Jobs</span>
            </nav>

            <div class="state-hero-content">
                <div class="state-hero-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Official State Employment Gateway 2026</span>
                </div>
                <h1 class="state-hero-title">State Government Jobs 2026</h1>
                <p class="state-hero-sub">राज्यवार सरकारी नौकरी — Explore verified public recruitment notifications, state public service commissions (PSCs), subordinate selection boards, and police recruitment departments across India.</p>

                <!-- Search Input for States -->
                <div class="state-search-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="stateFilterInput" placeholder="Type your state (e.g. Uttar Pradesh, Bihar, Rajasthan)..." aria-label="Filter states" autocomplete="off">
                    <button type="button" id="stateFilterClear" style="display:none; background:none; border:none; color:#94a3b8; cursor:pointer; padding:0 4px; font-size:16px; font-weight:bold; line-height:1;" aria-label="Clear search">✕</button>
                </div>

                <!-- High-Level Trust Metrics -->
                <div class="state-stats-row">
                    <div class="state-stat-pill">
                        <span class="stat-num"><?= count($allStates) ?>+</span>
                        <span class="stat-lbl">States &amp; UTs</span>
                    </div>
                    <div class="stat-div"></div>
                    <div class="state-stat-pill">
                        <span class="stat-num">100%</span>
                        <span class="stat-lbl">Verified .gov.in Portals</span>
                    </div>
                    <div class="stat-div"></div>
                    <div class="state-stat-pill">
                        <span class="stat-num">Real-Time</span>
                        <span class="stat-lbl">Automatic Sync</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Body -->
    <div class="container state-hub-container">

        <!-- Top Priority Quick Links (Most Popular States) -->
        <section class="state-popular-section">
            <div class="section-title-wrap">
                <h2 class="section-heading">High-Traffic State Employment Hubs</h2>
                <span class="section-tag">Most Searched</span>
            </div>
            <div class="popular-states-pills">
                <?php 
                $popularSlugs = ['uttar-pradesh', 'bihar', 'rajasthan', 'delhi', 'madhya-pradesh', 'haryana'];
                foreach ($popularSlugs as $pSlug): 
                    if (!isset($allStates[$pSlug])) continue;
                    $pState = $allStates[$pSlug];
                ?>
                <a href="<?= url('jobs/' . $pState['slug'] . '/') ?>" class="popular-state-chip">
                    <span class="chip-code"><?= e($pState['code']) ?></span>
                    <span class="chip-name"><?= e($pState['name']) ?></span>
                    <span class="chip-hi"><?= e($pState['name_hi']) ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Regions Grid -->
        <div id="stateGridContainer">
            <?php foreach ($regions as $regionName => $regionStates): ?>
            <section class="state-region-group" data-region="<?= e($regionName) ?>">
                <div class="region-header">
                    <h2 class="region-title"><?= e($regionName) ?></h2>
                    <span class="region-count"><?= count($regionStates) ?> States &amp; UTs</span>
                </div>

                <div class="state-cards-grid">
                    <?php foreach ($regionStates as $st): ?>
                    <a href="<?= url('jobs/' . $st['slug'] . '/') ?>" class="state-card" data-name="<?= e(mb_strtolower($st['name'] . ' ' . $st['name_hi'] . ' ' . $st['code'] . ' ' . $st['capital'] . ' ' . implode(' ', $st['match_keywords']), 'UTF-8')) ?>">
                        <div class="state-card-header">
                            <div class="state-card-identity">
                                <span class="state-code-badge" style="background-color: <?= e($st['bg'] ?? '#eff6ff') ?>; color: <?= e($st['color'] ?? '#1e3a8a') ?>; border-color: <?= e($st['color'] ?? '#1e3a8a') ?>40;">
                                    <?= e($st['code']) ?>
                                </span>
                                <div>
                                    <h3 class="state-name"><?= e($st['name']) ?></h3>
                                    <span class="state-name-hi"><?= e($st['name_hi']) ?></span>
                                </div>
                            </div>
                            <span class="state-capital"><?= e($st['capital']) ?></span>
                        </div>

                        <p class="state-tagline"><?= e($st['tagline']) ?></p>

                        <!-- Conducting Bodies Chips -->
                        <div class="state-commissions-row">
                            <?php foreach (array_slice($st['conducting_bodies'], 0, 3) as $body): ?>
                            <span class="commission-tag" title="<?= e($body['name']) ?>"><?= e($body['abbr']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($st['conducting_bodies']) > 3): ?>
                            <span class="commission-tag more">+<?= count($st['conducting_bodies']) - 3 ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="state-card-footer">
                            <span class="portal-cta-label">Explore Jobs</span>
                            <span class="portal-cta-arrow">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>

        <!-- Zero Results State for JS search -->
        <div id="noStatesFound" class="state-no-results" style="display: none;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h3>No state found matching your query</h3>
            <p>Please check your spelling or choose from the list above.</p>
        </div>

        <!-- Academic & Statutory Trust Notice -->
        <div class="state-trust-banner">
            <div class="trust-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 12 15 16 10"/></svg>
            </div>
            <div class="trust-content">
                <h4>Verified Government Source Attribution</h4>
                <p>Sarkari.online indexes employment notices strictly from authorized state public service commissions, subordinate boards, and statutory departments. All application submissions and examination registrations must be completed exclusively on respective official <code>.gov.in</code> and <code>.nic.in</code> portals.</p>
            </div>
        </div>

    </div>
</main>

<script>
(function() {
    function initStateSearch() {
        var searchInput = document.getElementById('stateFilterInput');
        var clearBtn = document.getElementById('stateFilterClear');
        if (!searchInput) return;

        var stateCards = document.querySelectorAll('.state-card');
        var regionGroups = document.querySelectorAll('.state-region-group');
        var popularSection = document.querySelector('.state-popular-section');
        var noResults = document.getElementById('noStatesFound');

        function doFilter() {
            var rawVal = searchInput.value || '';
            var query = rawVal.trim().toLowerCase();

            if (clearBtn) {
                clearBtn.style.display = query.length > 0 ? 'inline-block' : 'none';
            }

            if (popularSection) {
                popularSection.style.display = query.length > 0 ? 'none' : 'block';
            }

            var totalVisible = 0;

            regionGroups.forEach(function(group) {
                var groupCards = group.querySelectorAll('.state-card');
                var groupVisible = 0;

                groupCards.forEach(function(card) {
                    var dataName = (card.getAttribute('data-name') || '').toLowerCase();
                    var cardText = (card.textContent || card.innerText || '').toLowerCase();

                    if (query === '' || dataName.indexOf(query) !== -1 || cardText.indexOf(query) !== -1) {
                        card.style.display = 'flex';
                        groupVisible++;
                        totalVisible++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                group.style.display = groupVisible > 0 ? 'block' : 'none';
            });

            if (noResults) {
                noResults.style.display = (totalVisible === 0 && query.length > 0) ? 'block' : 'none';
            }
        }

        searchInput.addEventListener('input', doFilter);
        searchInput.addEventListener('keyup', doFilter);
        searchInput.addEventListener('paste', function() {
            setTimeout(doFilter, 50);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                doFilter();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStateSearch);
    } else {
        initStateSearch();
    }
})();
</script>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
