<?php
/**
 * EduPulse - Homepage (Phase 1)
 * Server-rendered editorial homepage connected to MySQL via ArticleService and CategoryService.
 * 100% database-backed with verified educational articles and real HTTP 200 URLs.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Data/MockData.php';

// Strict Router Guard: If a non-existent URL is rewritten to index.php, return real HTTP 404 page
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$cleanPath = trim($requestUri, '/');
if (str_starts_with($cleanPath, 'automation')) {
    $cleanPath = trim(substr($cleanPath, 10), '/');
}

if (!empty($cleanPath) && $cleanPath !== 'index.php') {
    // Cloudflare Email Protection Graceful Redirect: prevent 404 on email-protection
    if (str_starts_with($cleanPath, 'cdn-cgi/')) {
        header('Location: ' . url('contact/'), true, 301);
        exit;
    }

    // Google AdSense ads.txt Direct High-Priority Handler
    if ($cleanPath === 'ads.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo "google.com, pub-7182678086129554, DIRECT, f08c47fec0942fa0\n";
        exit;
    }


    // Historical 301 Redirect for legacy deleted CBSE test slug & deleted articles
    if (str_starts_with($cleanPath, 'article/cbse-class-10-and-12-board-exam-2027')) {
        header('Location: ' . url('category/exam-dates/'), true, 301);
        exit;
    }
    if (str_starts_with($cleanPath, 'article/upsc-exam-schedule-result-updates-2026')) {
        header('Location: ' . url('article/upsc-nda-2026-admit-card-download/'), true, 301);
        exit;
    }


    // Dynamic Tool Router: route any /tools/{slug} to its php file
    if (str_starts_with($cleanPath, 'tools/')) {
        $toolSlug = trim(substr($cleanPath, 6), '/');
        $toolFile = __DIR__ . '/tools/' . $toolSlug . '.php';
        if (file_exists($toolFile)) {
            require $toolFile;
            exit;
        }
    }

    // Dynamic Latest Jobs Directory Router: route /latest-jobs
    if ($cleanPath === 'latest-jobs' || $cleanPath === 'latest-jobs/') {
        require __DIR__ . '/latest-jobs.php';
        exit;
    }

    // Dynamic State Jobs Hub Router: route /state-jobs
    if ($cleanPath === 'state-jobs' || $cleanPath === 'state-jobs/') {
        require __DIR__ . '/state-jobs.php';
        exit;
    }

    // Dynamic State Detail Router: route /jobs/{state-slug}
    if (str_starts_with($cleanPath, 'jobs/')) {
        $stateSlug = trim(substr($cleanPath, 5), '/');
        if (!empty($stateSlug)) {
            $_GET['state'] = $stateSlug;
            require __DIR__ . '/state-detail.php';
            exit;
        }
    }

    // Dynamic Full Forms Directory Router: route /full-forms or /full-forms/{term}
    if ($cleanPath === 'full-forms' || $cleanPath === 'full-forms/') {
        require __DIR__ . '/full-forms.php';
        exit;
    }
    if (str_starts_with($cleanPath, 'full-forms/')) {
        $termSlug = trim(substr($cleanPath, 11), '/');
        if (!empty($termSlug)) {
            $_GET['term'] = $termSlug;
        }
        require __DIR__ . '/full-forms.php';
        exit;
    }

    // Dynamic Application Guides Directory Hub & Router: route /how-to-apply or /how-to-apply/{slug}
    if ($cleanPath === 'how-to-apply' || $cleanPath === 'how-to-apply/') {
        require __DIR__ . '/how-to-apply.php';
        exit;
    }
    if (str_starts_with($cleanPath, 'how-to-apply/')) {
        $guideSlug = trim(substr($cleanPath, 13), '/');
        if (!empty($guideSlug)) {
            $_GET['slug'] = $guideSlug;
        }
        require __DIR__ . '/how-to-apply.php';
        exit;
    }

    // Dynamic Author Profile Router: route /author or /author/{slug}
    if ($cleanPath === 'author' || $cleanPath === 'author/') {
        require __DIR__ . '/author.php';
        exit;
    }
    // Editorial & Fact-Checking Policy Routers
    if ($cleanPath === 'fact-checking-policy' || $cleanPath === 'fact-checking-policy/') {
        require __DIR__ . '/fact-checking-policy.php';
        exit;
    }
    if ($cleanPath === 'editorial-policy' || $cleanPath === 'editorial-policy/') {
        require __DIR__ . '/editorial-policy.php';
        exit;
    }

    if (str_starts_with($cleanPath, 'author/')) {
        $authorSlug = trim(substr($cleanPath, 7), '/');
        if (!empty($authorSlug)) {
            $_GET['slug'] = $authorSlug;
        }
        require __DIR__ . '/author.php';
        exit;
    }

    // Institutional & Utility Static Page Routers
    $staticPageRoutes = [
        'about' => 'about.php',
        'why-choose-us' => 'why-choose-us.php',
        'contact' => 'contact.php',
        'privacy-policy' => 'privacy-policy.php',
        'terms' => 'terms.php',
        'disclaimer' => 'disclaimer.php',
        'ai-policy' => 'ai-policy.php',
        'sitemap' => 'html-sitemap.php',
        'search' => 'search.php',
        'tools' => 'tools/index.php',
        'feed' => 'feed.php',
        'pinterest-feed' => 'pinterest-feed.php',
    ];
    $normPath = rtrim($cleanPath, '/');
    if (isset($staticPageRoutes[$normPath]) && file_exists(__DIR__ . '/' . $staticPageRoutes[$normPath])) {
        require __DIR__ . '/' . $staticPageRoutes[$normPath];
        exit;
    }

    require __DIR__ . '/404.php';
    exit;
}

use App\Database\Database;
use App\Services\GlossaryService;
use App\Services\CrawlEfficiencyService;

// SEO Meta Variables - Dedicated to Full Forms & Statutory Lexicon
$pageTitle = 'Sarkari.online — Government & Examination Full Forms (A-Z Directory)';
$pageDesc = 'Explore authentic expansions, Hindi meanings, eligibility rules, and selection schemes for UPSC, SSC, RRB, NEET, NDA, and 200+ Indian government examinations.';
$pageKeywords = 'full form, full forms list, upsc full form, ssc full form, rrb full form, neet full form, government exam full forms, sarkari result full form, exam acronyms a to z';
$canonicalUrl = SITE_URL . '/';
$ogType = 'website';

// ── HTTP Crawl Efficiency & Cache Validation Headers ──
$homepageModTime = Database::fetchValue("SELECT GREATEST(COALESCE(MAX(updated_at), '1970-01-01'), COALESCE(MAX(created_at), '1970-01-01')) FROM glossary_terms") ?: 'now';
CrawlEfficiencyService::handleConditionalGet('home-glossary-index', $homepageModTime);

// Fetch Total Full Forms
$totalTermsCount = 212;
try {
    $countDb = (int)Database::fetchValue("SELECT COUNT(*) FROM glossary_terms");
    if ($countDb > 0) $totalTermsCount = $countDb;
} catch (\Throwable $e) {}

// Top National Categories
$featuredCategories = [
    ['slug' => 'civil_services', 'name' => 'Civil Services & Administration', 'icon' => 'award', 'count' => 'UPSC, IAS, IPS, IFS'],
    ['slug' => 'defense_police', 'name' => 'Defense & Armed Forces', 'icon' => 'shield', 'count' => 'NDA, CDS, AFCAT, CAPF'],
    ['slug' => 'staff_selection', 'name' => 'Staff Selection & SSC', 'icon' => 'briefcase', 'count' => 'SSC, CGL, CHSL, MTS, GD'],
    ['slug' => 'railway', 'name' => 'Railways (RRB)', 'icon' => 'compass', 'count' => 'RRB, NTPC, ALP, RPF'],
    ['slug' => 'banking_insurance', 'name' => 'Banking & Insurance', 'icon' => 'layers', 'count' => 'RBI, SBI, IBPS, LIC, NABARD'],
    ['slug' => 'medical_engineering', 'name' => 'Entrance & Higher Education', 'icon' => 'graduation-cap', 'count' => 'NEET, JEE, GATE, UGC, NTA'],
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding-top: 2rem;">
    <div class="container">
        
        <!-- 1. Master Directory Hero Card -->
        <?php include __DIR__ . '/components/featured-card.php'; ?>

        <!-- 2. A-Z Full Forms Alphabet Bar -->
        <?php include __DIR__ . '/components/home-alphabet-bar.php'; ?>

        <!-- 3. High-Value Full Forms Hub & Category Grid -->
        <?php include __DIR__ . '/components/home-glossary-hub.php'; ?>

        <!-- 4. Domain & Sector Quick Directory -->
        <section class="content-section" style="margin: 2.5rem 0;">
            <div class="section-header" style="border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
                <h2 class="section-title" style="font-size: 1.25rem; font-weight: 800; color: #1a237e;">
                    Browse Full Forms by Sector &amp; Domain
                </h2>
                <span style="font-size: 0.85rem; color: #64748b;">Curated across all central and state regulatory sectors</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.25rem;">
                <?php foreach ($featuredCategories as $fc): ?>
                    <a href="<?= url('full-forms/?category=' . $fc['slug']) ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #1a237e; border-radius: 10px; padding: 1.25rem; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 15px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)';">
                        <div>
                            <div style="font-size: 1rem; font-weight: 800; color: #1a237e; margin-bottom: 0.35rem;">
                                <?= htmlspecialchars($fc['name']) ?>
                            </div>
                            <div style="font-size: 0.825rem; color: #64748b; line-height: 1.5;">
                                <?= htmlspecialchars($fc['count']) ?>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; font-size: 0.775rem; font-weight: 700; color: #f57c00; display: inline-flex; align-items: center; gap: 4px;">
                            <span>Explore Sector</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 5. Frequently Asked Questions (FAQ) Section -->
        <section class="content-section" aria-labelledby="sec-home-faq" style="margin-top: 2.5rem; margin-bottom: 2rem;">
            <div class="section-header" style="margin-bottom: 1.25rem;">
                <h2 class="section-title" id="sec-home-faq" style="font-size: 1.25rem; font-weight: 800; color: #1a237e;">
                    Frequently Asked Questions — Full Forms Directory
                </h2>
                <p style="font-size: 0.875rem; color: #64748b; margin-top: 0.25rem;">
                    Answers to common questions about Indian government exam acronyms, statutory bodies, and meanings.
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin-bottom: 0.4rem;">
                        What is Sarkari.online's A-Z Full Forms Directory?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        Sarkari.online provides an authoritative, complete directory of government, exam, and institutional acronyms across India. Each full form entry includes the official English expansion, Hindi translation (अर्थ), conducting body, eligibility criteria, and regulatory portal links.
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin-bottom: 0.4rem;">
                        Are these full form expansions officially verified?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        Yes, every acronym and statutory definition on Sarkari.online is verified directly against official gazettes of the Government of India, DoPT rules, and commission notifications.
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin-bottom: 0.4rem;">
                        How can students find any specific full form quickly?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        Candidates can either use the search bar above to type any 2 to 6 letter acronym (e.g. UPSC, SSC, NDA, NEET, RRB) or click on any letter in the A-Z alphabet bar to jump directly to all matching organizations.
                    </p>
                </div>
            </div>
        </section>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
