<?php
/**
 * Sarkari.online - Comprehensive HTML Sitemap & Directory
 * Central 1-Click Directory for All Examination Notices, Govt Jobs, State Hubs, Tools & Guides.
 * Drastically flattens Googlebot Click-Depth down to 1-2 clicks across the entire network.
 */
require_once __DIR__ . '/config.php';

use App\Database\Database;
use App\Services\CategoryService;
use App\Services\StateJobService;

// SEO Metadata
$pageTitle = 'HTML Sitemap: All Govt Jobs, Exams & Articles Directory | Sarkari.online';
$pageDesc = 'Comprehensive HTML sitemap of Sarkari.online. Browse all government recruitment notices, admit cards, exam results, state job hubs, tools, and guides.';
$pageKeywords = 'sarkari sitemap, sarkari.online directory, all govt jobs, admit card links, exam result links, sarkari result directory';
$canonicalUrl = url('sitemap/');
$ogType = 'website';

// 1. Fetch all categories
$categories = [];
try {
    $categories = CategoryService::getAll();
} catch (\Throwable $e) {
    // Fallback if DB offline
}

// 2. Fetch all published articles grouped by category
$articlesByCategory = [];
$totalArticlesCount = 0;
try {
    $sql = "SELECT a.id, a.title, a.slug, a.published_at, a.updated_at, c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM articles a
            JOIN categories c ON a.category_id = c.id
            WHERE a.status = 'published'
            ORDER BY c.sort_order ASC, c.name ASC, a.published_at DESC";
    $allArticles = Database::fetchAll($sql);
    $totalArticlesCount = count($allArticles);

    foreach ($allArticles as $art) {
        $catName = $art['category_name'] ?? 'Other Notifications';
        $articlesByCategory[$catName][] = $art;
    }
} catch (\Throwable $e) {
    // Fallback if DB offline
}

// 3. Fetch State Hubs
$allStates = [];
try {
    $allStates = StateJobService::getAllStates();
} catch (\Throwable $e) {}

// 4. Static Guides & Tools Configuration
$guides = [
    ['title' => 'NSP Scholarship 2026 Online Application & Renewal Guide', 'slug' => 'nsp-2026', 'desc' => 'Step-by-step registration for pre-matric, post-matric & merit-cum-means schemes.'],
    ['title' => 'SSC OTR (One-Time Registration) 2026 Process & Live Photo Guidelines', 'slug' => 'ssc-2026', 'desc' => 'Complete guide for registering on ssc.gov.in with live webcam verification.'],
    ['title' => 'IGNOU Admission & Re-Registration January 2026 Portal Guide', 'slug' => 'ignou-2026', 'desc' => 'Samarth portal submission guide for UG, PG, Diploma & Certificate courses.'],
    ['title' => 'HSSC CET 2026 Group C & D Registration & Correction Window', 'slug' => 'hssc-2026', 'desc' => 'One-Time Registration (OTR) Haryana portal form submission & fee steps.'],
    ['title' => 'IAF Agniveervayu Intake 01/2026 Online Application Guide', 'slug' => 'iaf-2026', 'desc' => 'Air Force Agniveer Vayu intake form, document specs & eligibility steps.'],
    ['title' => 'GATE 2026 Application, Document Upload & City Selection Guide', 'slug' => 'gate-2026', 'desc' => 'IIT Guwahati GOAPS registration, photograph specs and paper selection.'],
];

$tools = [
    ['title' => '7th Pay Commission Salary Calculator', 'url' => 'tools/7th-pay-commission-salary-calculator/', 'desc' => 'Calculate Pay Matrix Level 1 to 18, Basic Pay, DA (50%), HRA, TA & In-Hand Salary.'],
    ['title' => 'CGPA to Percentage & Marks Calculator', 'url' => 'tools/cgpa-to-percentage-calculator/', 'desc' => 'Convert CBSE 9.5 multiplier or University 10-point scale CGPA to official aggregate percentage.'],
    ['title' => 'Govt Job Age Calculator & Eligibility Tool', 'url' => 'tools/age-calculator/', 'desc' => 'Compute exact age in years, months, and days as of any recruitment cutoff date with category relaxations.'],
];

// 5. Popular Full Forms Highlights (Dynamic from DB to eliminate any 404 broken links)
$topFullForms = [];
try {
    $dbTerms = \App\Database\Database::fetchAll(
        "SELECT acronym, slug FROM glossary_terms ORDER BY id ASC LIMIT 40"
    );
    if (!empty($dbTerms)) {
        $topFullForms = $dbTerms;
    }
} catch (\Throwable $e) {}

// Structured Data Schema
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
                'url' => SITE_URL . '/'
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
                    'name' => 'HTML Sitemap',
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
/* HTML Sitemap Layout & Typography */
.sitemap-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    padding: 2.5rem 0 2rem;
    border-bottom: 1px solid #334155;
}
.sitemap-breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.sitemap-breadcrumb a {
    color: #cbd5e1;
    text-decoration: none;
}
.sitemap-breadcrumb a:hover {
    color: #ffffff;
    text-decoration: underline;
}
.sitemap-badge-pill {
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
    letter-spacing: 0.05em;
    margin-bottom: 0.85rem;
}
.sitemap-hero h1 {
    font-size: 2.1rem;
    font-weight: 800;
    line-height: 1.25;
    margin: 0 0 0.75rem;
    color: #ffffff;
}
.sitemap-hero p {
    font-size: 1rem;
    color: #cbd5e1;
    max-width: 820px;
    line-height: 1.6;
    margin: 0 0 1.5rem;
}

/* Quick Nav Anchor Bar */
.sitemap-quick-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}
.sitemap-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sitemap-nav-link:hover {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

/* Content Sections */
.sitemap-main-wrap {
    padding: 2.5rem 0 3.5rem;
    background: #f8fafc;
}
.sitemap-section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.75rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.sitemap-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
}
.sitemap-section-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.sitemap-count-badge {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
}

/* Grid & Links */
.sitemap-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.sitemap-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
}
.sitemap-grid-4 {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}

/* Category Item Card */
.sitemap-cat-card {
    display: block;
    padding: 1rem 1.15rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sitemap-cat-card:hover {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
    transform: translateY(-2px);
}
.sitemap-cat-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1e3a8a;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}
.sitemap-cat-desc {
    font-size: 0.82rem;
    color: #64748b;
    line-height: 1.4;
    margin: 0;
}

/* Article Lists */
.sitemap-cat-group {
    margin-bottom: 1.75rem;
}
.sitemap-cat-group-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sitemap-article-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 0.6rem 1.25rem;
}
.sitemap-article-item {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    font-size: 0.9rem;
    line-height: 1.45;
}
.sitemap-bullet {
    color: #3b82f6;
    font-weight: bold;
    flex-shrink: 0;
}
.sitemap-article-link {
    color: #334155;
    text-decoration: none;
    transition: color 0.15s ease;
}
.sitemap-article-link:hover {
    color: #2563eb;
    text-decoration: underline;
}
.sitemap-article-date {
    font-size: 0.75rem;
    color: #94a3b8;
    white-space: nowrap;
    flex-shrink: 0;
}

/* State & Guide Cards */
.sitemap-box-card {
    display: block;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sitemap-box-card:hover {
    border-color: #2563eb;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}
.sitemap-box-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.25rem;
}
.sitemap-box-desc {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.4;
    margin: 0;
}

/* Glossary Chips */
.sitemap-chip-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.sitemap-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    color: #1e293b;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sitemap-chip:hover {
    background: #1e3a8a;
    border-color: #1e3a8a;
    color: #ffffff;
}

@media (max-width: 768px) {
    .sitemap-hero h1 {
        font-size: 1.6rem;
    }
    .sitemap-grid-2, .sitemap-article-list {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Hero Section -->
<section class="sitemap-hero">
    <div class="container">
        <nav class="sitemap-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url() ?>">Home</a>
            <span>&rsaquo;</span>
            <span style="color: #ffffff; font-weight: 600;">HTML Sitemap</span>
        </nav>
        
        <div class="sitemap-badge-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span>Central Portal Index &middot; Fast 1-Click Access</span>
        </div>

        <h1>Sarkari.online Directory &amp; HTML Sitemap</h1>
        <p>
            Complete architectural index of all examination schedules, government recruitment notifications, state public service commissions, step-by-step application forms, candidate eligibility calculators, and official portals across India.
        </p>

        <!-- Quick Jump Navigation -->
        <div class="sitemap-quick-nav">
            <a href="#categories" class="sitemap-nav-link">Exams &amp; Categories</a>
            <a href="#articles" class="sitemap-nav-link">All Articles (<?= $totalArticlesCount ?>)</a>
            <a href="#states" class="sitemap-nav-link">State Job Hubs</a>
            <a href="#guides" class="sitemap-nav-link">Application Guides</a>
            <a href="#tools" class="sitemap-nav-link">Calculators &amp; Tools</a>
            <a href="#glossary" class="sitemap-nav-link">Full Forms Directory</a>
            <a href="#institutional" class="sitemap-nav-link">Editorial &amp; Legal</a>
        </div>
    </div>
</section>

<!-- Main Sitemap Content -->
<main class="sitemap-main-wrap">
    <div class="container">

        <!-- 1. Categories Grid -->
        <section id="categories" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Examination &amp; Recruitment Categories</span>
                </h2>
                <span class="sitemap-count-badge"><?= count($categories) ?> Main Hubs</span>
            </div>
            
            <div class="sitemap-grid-2">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= url('category/' . $cat['slug'] . '/') ?>" class="sitemap-cat-card">
                            <div class="sitemap-cat-name">
                                <span><?= e($cat['name']) ?></span>
                                <span style="font-size: 0.8rem; color: #2563eb;">&rarr;</span>
                            </div>
                            <p class="sitemap-cat-desc"><?= e($cat['description'] ?? 'Explore latest notifications, circulars and official updates.') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="<?= url('category/government-jobs/') ?>" class="sitemap-cat-card">
                        <div class="sitemap-cat-name">Government Jobs</div>
                        <p class="sitemap-cat-desc">SSC, UPSC, Railway, Banking and PSU Recruitment alerts.</p>
                    </a>
                <?php endif; ?>
                <a href="<?= url('latest-jobs/') ?>" class="sitemap-cat-card" style="border-left: 4px solid #2563eb;">
                    <div class="sitemap-cat-name">
                        <span>Latest Govt Jobs 2026 Directory</span>
                        <span style="font-size: 0.8rem; color: #2563eb;">&rarr;</span>
                    </div>
                    <p class="sitemap-cat-desc">Central &amp; State job feed with vacancy breakdowns, eligibility, and last dates.</p>
                </a>
            </div>
        </section>

        <!-- 2. All Published Articles Grouped by Category -->
        <section id="articles" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>All Published Articles &amp; Notifications</span>
                </h2>
                <span class="sitemap-count-badge"><?= $totalArticlesCount ?> Indexed Articles</span>
            </div>

            <?php if (!empty($articlesByCategory)): ?>
                <?php foreach ($articlesByCategory as $categoryTitle => $catArticles): ?>
                    <div class="sitemap-cat-group">
                        <div class="sitemap-cat-group-title">
                            <span><?= e($categoryTitle) ?></span>
                            <span style="font-size: 0.78rem; font-weight: 600; color: #64748b;"><?= count($catArticles) ?> articles</span>
                        </div>
                        <ul class="sitemap-article-list">
                            <?php foreach ($catArticles as $article): 
                                $pubDate = !empty($article['published_at']) ? date('d M Y', strtotime($article['published_at'])) : '';
                            ?>
                                <li class="sitemap-article-item">
                                    <span class="sitemap-bullet">&bull;</span>
                                    <a href="<?= url('article/' . $article['slug'] . '/') ?>" class="sitemap-article-link" title="<?= e($article['title']) ?>">
                                        <?= e($article['title']) ?>
                                    </a>
                                    <?php if ($pubDate): ?>
                                        <span class="sitemap-article-date">(<?= $pubDate ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #64748b;">All articles are accessible directly via their respective categories and RSS feeds.</p>
            <?php endif; ?>
        </section>

        <!-- 3. State Government Jobs Portals -->
        <section id="states" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                    <span>State Government Job Hubs</span>
                </h2>
                <span class="sitemap-count-badge">12 State Portals</span>
            </div>

            <div class="sitemap-grid-3">
                <a href="<?= url('state-jobs/') ?>" class="sitemap-box-card" style="border-left: 4px solid #1e3a8a; background: #eff6ff;">
                    <div class="sitemap-box-title" style="color: #1e3a8a;">All States Recruitment Hub &rarr;</div>
                    <p class="sitemap-box-desc">Master index of state public service commissions, police boards &amp; subordinate exams.</p>
                </a>

                <?php if (!empty($allStates)): ?>
                    <?php foreach ($allStates as $stSlug => $st): ?>
                        <a href="<?= url('jobs/' . $stSlug . '/') ?>" class="sitemap-box-card">
                            <div class="sitemap-box-title"><?= e($st['name']) ?> (<?= e($st['code']) ?>)</div>
                            <p class="sitemap-box-desc"><?= e($st['tagline'] ?? 'State government recruitment and commission notices.') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="<?= url('jobs/uttar-pradesh/') ?>" class="sitemap-box-card"><div class="sitemap-box-title">Uttar Pradesh (UP)</div></a>
                    <a href="<?= url('jobs/bihar/') ?>" class="sitemap-box-card"><div class="sitemap-box-title">Bihar (BR)</div></a>
                    <a href="<?= url('jobs/rajasthan/') ?>" class="sitemap-box-card"><div class="sitemap-box-title">Rajasthan (RJ)</div></a>
                    <a href="<?= url('jobs/madhya-pradesh/') ?>" class="sitemap-box-card"><div class="sitemap-box-title">Madhya Pradesh (MP)</div></a>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4. Step-by-Step Application Guides -->
        <section id="guides" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <span>Online Form Application Guides</span>
                </h2>
                <a href="<?= url('how-to-apply/') ?>" class="sitemap-count-badge" style="text-decoration: none;">View All Guides &rarr;</a>
            </div>

            <div class="sitemap-grid-2">
                <?php foreach ($guides as $guide): ?>
                    <a href="<?= url('how-to-apply/' . $guide['slug'] . '/') ?>" class="sitemap-box-card">
                        <div class="sitemap-box-title"><?= e($guide['title']) ?></div>
                        <p class="sitemap-box-desc"><?= e($guide['desc']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. Calculators & Candidate Tools -->
        <section id="tools" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></svg>
                    <span>Candidate Utility &amp; Eligibility Tools</span>
                </h2>
                <a href="<?= url('tools/') ?>" class="sitemap-count-badge" style="text-decoration: none;">Tools Directory &rarr;</a>
            </div>

            <div class="sitemap-grid-3">
                <?php foreach ($tools as $tool): ?>
                    <a href="<?= url($tool['url']) ?>" class="sitemap-box-card">
                        <div class="sitemap-box-title"><?= e($tool['title']) ?></div>
                        <p class="sitemap-box-desc"><?= e($tool['desc']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 6. Full Forms & Abbreviations Directory -->
        <section id="glossary" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>Government &amp; Exam Full Forms Directory</span>
                </h2>
                <a href="<?= url('full-forms/') ?>" class="sitemap-count-badge" style="text-decoration: none;">A to Z Index (130+) &rarr;</a>
            </div>
            
            <p style="font-size: 0.88rem; color: #64748b; margin-top: -0.5rem; margin-bottom: 1rem;">
                Explore verified expansions, Hindi meanings, eligibility criteria, conducting bodies, and salary details for top national exam acronyms:
            </p>

            <div class="sitemap-chip-wrap">
                <?php foreach ($topFullForms as $tf): ?>
                    <a href="<?= url('full-forms/' . e($tf['slug']) . '/') ?>" class="sitemap-chip">
                        <span><?= e($tf['acronym']) ?> Full Form</span>
                    </a>
                <?php endforeach; ?>
                <a href="<?= url('full-forms/') ?>" class="sitemap-chip" style="background: #1e3a8a; color: #ffffff; border-color: #1e3a8a;">
                    <span>Browse All 130+ Full Forms (A-Z) &rarr;</span>
                </a>
            </div>
        </section>

        <!-- 7. Institutional, Editorial & Legal Pages -->
        <section id="institutional" class="sitemap-section-card">
            <div class="sitemap-section-header">
                <h2 class="sitemap-section-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Institutional, Editorial Standards &amp; Feeds</span>
                </h2>
            </div>

            <div class="sitemap-grid-3">
                <a href="<?= url('about/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">About Editorial Desk</div>
                    <p class="sitemap-box-desc">Mission, public service mandate, and independent governance framework.</p>
                </a>
                <a href="<?= url('editorial-policy/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Editorial Policy &amp; Standards</div>
                    <p class="sitemap-box-desc">Guidelines for secondary source verification and statutory cross-referencing.</p>
                </a>
                <a href="<?= url('fact-checking-policy/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Fact-Checking &amp; Corrections Policy</div>
                    <p class="sitemap-box-desc">Rigorous verification methodology and formal corrections submission workflow.</p>
                </a>
                <a href="<?= url('ai-policy/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">AI Transparency Code</div>
                    <p class="sitemap-box-desc">Principles governing algorithmic assistance, source provenance, and human review.</p>
                </a>
                <a href="<?= url('why-choose-us/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Why Choose Sarkari.online</div>
                    <p class="sitemap-box-desc">Speed, primary source rigor, and clean candidate-first user experience.</p>
                </a>
                <a href="<?= url('contact/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Contact &amp; Grievance Redressal</div>
                    <p class="sitemap-box-desc">Editorial board contact details, address, and formal candidate support.</p>
                </a>
                <a href="<?= url('disclaimer/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Statutory Disclaimer</div>
                    <p class="sitemap-box-desc">Non-affiliation notice clarifying independent educational status.</p>
                </a>
                <a href="<?= url('privacy-policy/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Privacy Policy</div>
                    <p class="sitemap-box-desc">Candidate data handling, cookie policies, and compliance standards.</p>
                </a>
                <a href="<?= url('terms/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Terms of Service</div>
                    <p class="sitemap-box-desc">User terms, intellectual property, and acceptable usage conditions.</p>
                </a>
                <a href="<?= url('sitemap.xml') ?>" class="sitemap-box-card" target="_blank">
                    <div class="sitemap-box-title">XML Sitemap (Google Search Central)</div>
                    <p class="sitemap-box-desc">Machine-readable index adhering to sitemaps.org standard for search engine crawlers.</p>
                </a>
                <a href="<?= url('feed/') ?>" class="sitemap-box-card" target="_blank">
                    <div class="sitemap-box-title">RSS / Atom Syndication Feed</div>
                    <p class="sitemap-box-desc">Real-time syndicated XML feed for feed readers and alert aggregators.</p>
                </a>
                <a href="<?= url('author/ajay-mathur/') ?>" class="sitemap-box-card">
                    <div class="sitemap-box-title">Author: Ajay Mathur (Lead Editor)</div>
                    <p class="sitemap-box-desc">Author credentials, public sector expertise, and verified publications.</p>
                </a>
            </div>
        </section>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
