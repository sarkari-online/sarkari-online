<?php
/**
 * Sarkari.online - Latest Government Jobs 2026 (Central & All States Hub)
 * High-speed, dynamic public sector recruitment directory with verified vacancies,
 * application deadlines, conducting authorities, and direct portal links.
 */

require_once __DIR__ . '/config.php';

use App\Services\JobDirectoryService;
use App\Services\StateJobService;

// Fetch all active jobs
$allJobs = JobDirectoryService::getActiveJobs(60);
$stats = JobDirectoryService::getDirectoryStats($allJobs);
$allStates = StateJobService::getAllStates();

// SEO Meta Variables
$pageTitle = 'Latest Government Jobs 2026: Online Form, Notification & Last Dates | ' . SITE_NAME;
$pageDesc = 'Explore latest government jobs 2026 across India. Verified recruitment alerts for SSC, Railway RRB, Banking, UPSC, Defence, and State PSCs with post counts and last dates.';
$pageKeywords = 'latest govt jobs 2026, sarkari result latest jobs, sarkari naukri 2026, central govt jobs, state govt jobs, rrb ntpc, ssc cgl, bpsc tre, bank of baroda recruitment';
$canonicalUrl = url('latest-jobs/');
$ogType = 'website';

// Generate Schema.org JSON-LD Structured Data
$jobListItems = [];
foreach (array_slice($allJobs, 0, 25) as $idx => $job) {
    $jobListItems[] = [
        '@type' => 'ListItem',
        'position' => $idx + 1,
        'item' => [
            '@type' => 'Article',
            'name' => $job['title'],
            'url' => $job['url'],
            'description' => "Official recruitment for {$job['authority']} ({$job['vacancies']}). {$job['last_date']}.",
            'datePublished' => !empty($job['published_at']) ? date('c', strtotime($job['published_at'])) : date('c')
        ]
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
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $jobListItems
            ]
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
                    'name' => 'Latest Jobs',
                    'item' => $canonicalUrl
                ]
            ]
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<style>
/* Latest Jobs Directory Styles */
.jobs-hub-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    padding: 2.25rem 0 2rem;
    border-bottom: 1px solid #334155;
}
.jobs-breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.jobs-breadcrumb a {
    color: #cbd5e1;
    text-decoration: none;
}
.jobs-breadcrumb a:hover {
    color: #ffffff;
    text-decoration: underline;
}
.jobs-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(37, 99, 235, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.4);
    color: #60a5fa;
    padding: 0.35rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.75rem;
}
.jobs-hero-title {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 0.6rem;
    color: #ffffff;
}
.jobs-hero-sub {
    font-size: 1rem;
    color: #94a3b8;
    max-width: 820px;
    line-height: 1.55;
    margin-bottom: 1.25rem;
}
.jobs-stats-strip {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    width: fit-content;
}
.jobs-stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.jobs-stat-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: #38bdf8;
}
.jobs-stat-lbl {
    font-size: 0.8rem;
    color: #cbd5e1;
}
.jobs-stat-sep {
    width: 1px;
    height: 18px;
    background: rgba(255, 255, 255, 0.15);
}

/* Filter & Search Bar */
.jobs-filter-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    margin: -1.5rem auto 1.5rem;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.06);
    position: relative;
    z-index: 10;
}
.jobs-search-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.jobs-search-input-wrap {
    position: relative;
    flex: 1;
}
.jobs-search-input-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}
.jobs-search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
    background: #f8fafc;
}
.jobs-search-input:focus {
    border-color: #2563eb;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.jobs-pills-row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.jobs-pill-btn {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
    padding: 0.4rem 0.9rem;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.jobs-pill-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.jobs-pill-btn.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

/* Jobs Feed Table / List (Clean Authentic Minimalist UI) */
.jobs-feed-container {
    margin-bottom: 2.5rem;
}
.jobs-list-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}
.job-item-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    transition: background-color 0.15s ease;
    text-decoration: none;
    color: inherit;
}
.job-item-card:last-child {
    border-bottom: none;
}
.job-item-card:hover {
    background-color: #f8fafc;
}

.job-main-info {
    flex: 1;
    min-width: 0;
}
.job-title-row {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.35rem;
}
.job-bullet {
    color: #dc2626;
    font-size: 1.25rem;
    line-height: 1;
    font-weight: bold;
    user-select: none;
    flex-shrink: 0;
}
.job-link-title {
    font-size: 1.02rem;
    font-weight: 700;
    color: #1e3a8a;
    line-height: 1.4;
    text-decoration: none;
}
.job-item-card:hover .job-link-title {
    color: #dc2626;
    text-decoration: underline;
}
.job-vacancy-badge {
    color: #15803d;
    font-size: 0.85rem;
    font-weight: 700;
    white-space: nowrap;
}
.job-meta-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.8rem;
    color: #64748b;
    flex-wrap: wrap;
    padding-left: 1.15rem;
}
.job-meta-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    color: #475569;
}
.job-state-tag {
    background: #f1f5f9;
    color: #334155;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.72rem;
    border: 1px solid #e2e8f0;
}
.job-action-col {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-shrink: 0;
}
.job-deadline-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
}
.job-deadline-badge.urgent {
    color: #dc2626;
    font-weight: 700;
}
.job-deadline-badge.closed {
    color: #64748b;
    background: #f1f5f9;
}
.job-deadline-badge.notice {
    color: #d97706;
    background: #fffbeb;
}
.job-apply-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 0.35rem 0.75rem;
    border-radius: 5px;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.job-item-card:hover .job-apply-btn {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}
.job-apply-btn.closed {
    color: #475569;
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.job-item-card:hover .job-apply-btn.closed {
    background: #64748b;
    color: #ffffff;
    border-color: #64748b;
}
.job-apply-btn.notice {
    color: #b45309;
    background: #fef3c7;
    border-color: #fde68a;
}
.job-item-card:hover .job-apply-btn.notice {
    background: #d97706;
    color: #ffffff;
    border-color: #d97706;
}

@media (max-width: 768px) {
    .job-item-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.85rem 1rem;
    }
    .job-meta-row {
        padding-left: 0;
    }
    .job-action-col {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px dashed #f1f5f9;
        padding-top: 0.5rem;
    }
    .jobs-hero-title {
        font-size: 1.65rem;
    }
}
</style>

<main class="site-main" style="padding-top: 0; width: 100%;">

    <!-- Hero Header -->
    <section class="jobs-hub-hero">
        <div class="container">
            <nav class="jobs-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= url() ?>">Home</a>
                <span>›</span>
                <span>Latest Government Jobs 2026</span>
            </nav>

            <div class="jobs-hero-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Live Statutory Recruitment Directory 2026</span>
            </div>

            <h1 class="jobs-hero-title">Latest Government Jobs 2026</h1>
            <p class="jobs-hero-sub">All India &amp; State Sarkari Vacancies — Direct official access to open public sector recruitment, application deadlines, post counts, and eligibility guidelines across Central and State boards.</p>

            <div class="jobs-stats-strip">
                <div class="jobs-stat-item">
                    <span class="jobs-stat-val"><?= $stats['total_vacancies'] ?></span>
                    <span class="jobs-stat-lbl">Active Posts</span>
                </div>
                <div class="jobs-stat-sep"></div>
                <div class="jobs-stat-item">
                    <span class="jobs-stat-val"><?= $stats['total_jobs'] ?>+</span>
                    <span class="jobs-stat-lbl">Open Drives</span>
                </div>
                <div class="jobs-stat-sep"></div>
                <div class="jobs-stat-item">
                    <span class="jobs-stat-val">100%</span>
                    <span class="jobs-stat-lbl">Verified .gov.in Sources</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div class="container" style="max-width: 1080px;">

        <!-- Filter & Search Toolbar -->
        <div class="jobs-filter-panel">
            <div class="jobs-search-row">
                <div class="jobs-search-input-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="jobSearchInput" class="jobs-search-input" placeholder="Search by job name, board (e.g. Bank of Baroda, SSC, BPSC, RRB, India Post)..." autocomplete="off">
                </div>
            </div>

            <div class="jobs-pills-row">
                <span style="font-size: 0.8rem; font-weight: 700; color: #64748b; margin-right: 4px;">Quick Filter:</span>
                <button type="button" class="jobs-pill-btn active" data-filter="all">All Jobs (<?= count($allJobs) ?>)</button>
                <button type="button" class="jobs-pill-btn" data-filter="central">Central / All India</button>
                <button type="button" class="jobs-pill-btn" data-filter="uttar-pradesh">Uttar Pradesh</button>
                <button type="button" class="jobs-pill-btn" data-filter="bihar">Bihar</button>
                <button type="button" class="jobs-pill-btn" data-filter="rajasthan">Rajasthan</button>
                <button type="button" class="jobs-pill-btn" data-filter="madhya-pradesh">Madhya Pradesh</button>
                <button type="button" class="jobs-pill-btn" data-filter="delhi">Delhi</button>
                <button type="button" class="jobs-pill-btn" data-filter="punjab">Punjab</button>
            </div>
        </div>

        <!-- Live Jobs List Section -->
        <div class="jobs-feed-container">
            <div class="jobs-list-card" id="jobsListWrap">
                <?php foreach ($allJobs as $job): 
                    $statusType = $job['status_tag']['type'] ?? 'active';
                    $statusLabel = $job['status_tag']['label'] ?? 'Apply Online';
                    $isUrgent = ($statusType === 'urgent') || str_contains(strtolower($job['last_date']), 'today');
                    $isClosed = ($statusType === 'closed');
                    $isNotice = ($statusType === 'notice');
                ?>
                <a href="<?= $job['url'] ?>" class="job-item-card" 
                   data-title="<?= e(strtolower($job['title'])) ?>" 
                   data-authority="<?= e(strtolower($job['authority'])) ?>"
                   data-state="<?= e(strtolower($job['state_slug'])) ?>">
                    
                    <div class="job-main-info">
                        <div class="job-title-row">
                            <span class="job-bullet" aria-hidden="true">&bull;</span>
                            <span class="job-link-title"><?= e($job['title']) ?></span>
                            <?php if (!empty($job['vacancies'])): ?>
                            <span class="job-vacancy-badge">(<?= e($job['vacancies']) ?>)</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="job-meta-row">
                            <span class="job-meta-tag"><?= e($job['authority']) ?></span>
                            <span>&bull;</span>
                            <span class="job-state-tag"><?= e($job['state_name']) ?></span>
                            <span>&bull;</span>
                            <span>Updated <?= !empty($job['published_at']) ? date('M d, Y', strtotime($job['published_at'])) : date('M d, Y') ?></span>
                        </div>
                    </div>

                    <div class="job-action-col">
                        <div class="job-deadline-badge <?= $isUrgent ? 'urgent' : ($isClosed ? 'closed' : ($isNotice ? 'notice' : 'normal')) ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><?= e($job['last_date']) ?></span>
                        </div>
                        <span class="job-apply-btn <?= $isClosed ? 'closed' : ($isNotice ? 'notice' : '') ?>">
                            <span><?= $isClosed ? 'View Details' : ($isNotice ? 'Check Notice' : 'Apply Online') ?></span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </div>

                </a>
                <?php endforeach; ?>
            </div>

            <!-- Zero Results Message -->
            <div id="noJobFound" style="display: none; text-align: center; padding: 3rem 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom: 0.5rem;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <h3 style="font-size: 1.1rem; color: #0f172a; margin-bottom: 0.25rem;">No recruitment notices match your filter</h3>
                <p style="font-size: 0.9rem; color: #64748b;">Try searching for a different keyword or select "All Jobs".</p>
            </div>
        </div>

        <!-- Official State Hubs Section -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.2rem;">Explore State Public Service Commissions (PSCs)</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Direct access to all 28 states &amp; UT recruitment gateways:</p>
                </div>
                <a href="<?= url('state-jobs/') ?>" style="font-size: 0.85rem; font-weight: 700; color: #2563eb; text-decoration: none;">View All 28 States Hub &rarr;</a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.6rem;">
                <?php foreach (array_slice($allStates, 0, 12) as $st): ?>
                <a href="<?= url('jobs/' . $st['slug'] . '/') ?>" style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #cbd5e1; padding: 0.55rem 0.85rem; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.85rem; font-weight: 600; transition: all 0.15s;">
                    <span><?= e($st['name']) ?></span>
                    <span style="font-size: 0.72rem; color: #64748b; background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 3px;"><?= e($st['code']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</main>

<script>
(function() {
    var searchInput = document.getElementById('jobSearchInput');
    var filterBtns = document.querySelectorAll('.jobs-pill-btn');
    var jobCards = document.querySelectorAll('.job-item-card');
    var noJobs = document.getElementById('noJobFound');

    var currentFilter = 'all';

    function filterJobs() {
        var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        var visibleCount = 0;

        jobCards.forEach(function(card) {
            var title = card.getAttribute('data-title') || '';
            var authority = card.getAttribute('data-authority') || '';
            var state = card.getAttribute('data-state') || '';

            var matchesFilter = false;
            if (currentFilter === 'all') {
                matchesFilter = true;
            } else if (currentFilter === 'central') {
                matchesFilter = (state === 'all-india');
            } else {
                matchesFilter = (state === currentFilter);
            }

            var matchesSearch = (query === '' || title.indexOf(query) !== -1 || authority.indexOf(query) !== -1);

            if (matchesFilter && matchesSearch) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noJobs) {
            noJobs.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterJobs);
        searchInput.addEventListener('keyup', filterJobs);
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter') || 'all';
            filterJobs();
        });
    });
})();
</script>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
