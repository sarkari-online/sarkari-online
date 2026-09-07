<?php
/**
 * Sarkari.online - A-to-Z Government & Examination Full Forms Hub
 * High-speed, responsive, institutional educational directory matching Sarkari.online design system.
 * Supports both master index listing (/full-forms/) and detailed single-term views (/full-forms/:slug/).
 */
require_once __DIR__ . '/config.php';

use App\Services\GlossaryService;
use App\Helpers\Sanitizer;
use App\Helpers\SEOHelper;

$termSlug = $_GET['term'] ?? '';
$termSlug = strtolower(trim($termSlug, '/'));

// -----------------------------------------------------------------------------
// CASE A: DETAIL PAGE VIEW (/full-forms/:slug/)
// -----------------------------------------------------------------------------
if (!empty($termSlug)) {
    $term = GlossaryService::getBySlug($termSlug);
    if (!$term) {
        http_response_code(404);
        include __DIR__ . '/404.php';
        exit;
    }

    $pageTitle = "Full Form of {$term['acronym']} — {$term['full_form_en']} ({$term['full_form_hi']}) | " . SITE_NAME;
    $pageDesc = "What is the official full form of {$term['acronym']}? Complete full form is {$term['full_form_en']} ({$term['full_form_hi']}). Official overview, eligibility criteria, selection process, and authority portal.";
    $canonicalUrl = url("full-forms/{$term['slug']}/");
    $ogType = 'article';

    $crumbs = [
        ['label' => 'Home', 'url' => url()],
        ['label' => 'Full Forms (A-Z)', 'url' => url('full-forms/')],
        ['label' => $term['acronym'], 'url' => null]
    ];

    // Structured Data: Schema.org DefinedTerm & BreadcrumbList (NO FAQPage per Google policy)
    $definedTermSchema = json_encode([
        "@context" => "https://schema.org",
        "@type" => "DefinedTerm",
        "name" => $term['acronym'],
        "termCode" => $term['acronym'],
        "description" => $term['full_form_en'] . (!empty($term['full_form_hi']) ? " (" . $term['full_form_hi'] . ")" : "") . ". " . $term['overview'],
        "inDefinedTermSet" => [
            "@type" => "DefinedTermSet",
            "name" => "Sarkari.online Indian Government & Examination Acronym Glossary",
            "url" => url('full-forms/')
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $breadcrumbSchema = json_encode([
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => [
            ["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => url()],
            ["@type" => "ListItem", "position" => 2, "name" => "Full Forms (A-Z)", "item" => url('full-forms/')],
            ["@type" => "ListItem", "position" => 3, "name" => $term['acronym'], "item" => $canonicalUrl]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $customHeadHtml = "<script type=\"application/ld+json\">{$definedTermSchema}</script>\n<script type=\"application/ld+json\">{$breadcrumbSchema}</script>";

    $relatedTerms = GlossaryService::getRelatedTerms((int)$term['id'], $term['category'], 6);

    include __DIR__ . '/components/head.php';
    include __DIR__ . '/components/header.php';
    ?>

    <main class="site-main" style="padding: 2rem 0 5rem 0; background: #f8fafc;">
        <div class="container" style="max-width: 960px;">
            
            <!-- Breadcrumbs -->
            <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
                <ol style="display: flex; flex-wrap: wrap; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                    <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                    <li><a href="<?= url('full-forms/') ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Full Forms (A-Z)</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                    <li style="color: #0f172a; font-weight: 600;"><?= e($term['acronym']) ?></li>
                </ol>
            </nav>

            <!-- Main Detail Card -->
            <article style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); margin-bottom: 2rem;">
                
                <!-- Category Badge & Verification Strip -->
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 1.25rem;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 9px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?>
                    </span>
                    <div style="font-size: 0.75rem; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 9px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <span>Verified Statutory Lexicon &middot; Last Reviewed: <?= date('M d, Y', strtotime($term['last_reviewed_at'])) ?></span>
                    </div>
                </div>

                <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0; line-height: 1.25; letter-spacing: -0.02em;">
                    Full Form of <?= e($term['acronym']) ?>
                </h1>

                <!-- Direct Answer Official Definition Box (High Authority Snippet) -->
                <div style="background: #f8fafc; border-left: 4px solid #1e3a8a; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0 2rem 0;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.45rem; display: flex; align-items: center; gap: 6px;">
                        <span>🏛️ OFFICIAL DIRECT DEFINITION</span>
                    </div>
                    <div style="font-size: 1.45rem; font-weight: 800; color: #0f172a; line-height: 1.3; margin-bottom: 0.35rem;">
                        <?= e($term['full_form_en']) ?>
                    </div>
                    <?php if (!empty($term['full_form_hi'])): ?>
                        <div style="font-size: 1.15rem; font-weight: 700; color: #1e3a8a; margin-bottom: 0.85rem; font-family: 'Noto Sans Devanagari', sans-serif;">
                            हिंदी अर्थ: <?= e($term['full_form_hi']) ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1.15rem; padding-top: 1.15rem; border-top: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Conducting / Regulatory Authority</span>
                            <strong style="color: #0f172a; font-size: 0.925rem;"><?= e($term['conducting_body'] ?? 'Government of India') ?></strong>
                        </div>
                        <?php if (!empty($term['official_portal'])): ?>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">Official Authority Portal</span>
                                <a href="<?= e($term['official_portal']) ?>" target="_blank" rel="noopener noreferrer" style="color: #1e3a8a; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 0.925rem;">
                                    <span><?= parse_url($term['official_portal'], PHP_URL_HOST) ?></span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Structured Factual Sections -->
                <div style="font-size: 0.95rem; color: #334155; line-height: 1.7;">
                    
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.4rem;">
                        1. Official Mandate, Scope &amp; Background
                    </h2>
                    <p style="margin: 0 0 1.25rem 0;">
                        <?= nl2br(e($term['overview'])) ?>
                    </p>

                    <?php if (!empty($term['eligibility_criteria'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.4rem;">
                            2. Eligibility Criteria, Qualifications &amp; Age Limits
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['eligibility_criteria'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['selection_process'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.4rem;">
                            3. Examination Scheme &amp; Selection Procedure
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['selection_process'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['syllabus_snapshot'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.4rem;">
                            4. Core Syllabus &amp; Key Subjects
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['syllabus_snapshot'])) ?>
                        </p>
                    <?php endif; ?>

                </div>

                <!-- Internal Linking Engine / Live Exam Updates Connection -->
                <?php if (!empty($term['related_article_slug'])): ?>
                    <div style="margin-top: 2rem; padding: 1.25rem 1.5rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;">
                        <span style="font-weight: 800; color: #1e3a8a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                            📌 LIVE NOTIFICATIONS &amp; DATES
                        </span>
                        <a href="<?= url('article/' . $term['related_article_slug'] . '/') ?>" style="color: #1e3a8a; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; font-size: 1rem;">
                            Check Verified 2026 Examination Schedule &amp; Application Guide for <?= e($term['acronym']) ?> &rarr;
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Back to Directory CTA -->
                <div style="margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                    <a href="<?= url('full-forms/') ?>" style="color: #1e3a8a; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.875rem;">
                        &larr; <span>Back to A-to-Z Full Forms Directory</span>
                    </a>
                    <a href="<?= url('tools/') ?>" style="color: #64748b; font-weight: 600; text-decoration: none; font-size: 0.875rem;">
                        Explore Student Calculators &amp; Tools &rarr;
                    </a>
                </div>

            </article>

            <!-- Related Acronyms Grid -->
            <?php if (!empty($relatedTerms)): ?>
                <div style="margin-top: 2.5rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                        Related <?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?> Acronyms &amp; Full Forms
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                        <?php foreach ($relatedTerms as $rt): ?>
                            <a href="<?= url('full-forms/' . $rt['slug'] . '/') ?>" style="display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem; text-decoration: none; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); transition: all 0.15s ease;" onmouseover="this.style.borderColor='#1e3a8a'; this.style.boxShadow='0 4px 12px rgba(30, 58, 138, 0.08)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 2px rgba(15, 23, 42, 0.04)';">
                                <span style="font-size: 1.1rem; font-weight: 800; color: #1e3a8a; display: block; margin-bottom: 0.25rem;">
                                    <?= e($rt['acronym']) ?>
                                </span>
                                <span style="font-size: 0.85rem; font-weight: 600; color: #1e293b; line-height: 1.4; display: block;">
                                    <?= e($rt['full_form_en']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php
    include __DIR__ . '/components/footer.php';
    exit;
}

// -----------------------------------------------------------------------------
// CASE B: MASTER DIRECTORY HUB VIEW (/full-forms/)
// -----------------------------------------------------------------------------
$allTerms = GlossaryService::getTerms(null, null, null, 250, 0);
$alphabetCounts = GlossaryService::getAlphabetCounts();
$categoryCounts = GlossaryService::getCategoryCounts();
$totalCount = GlossaryService::getTotalCount();

$pageTitle = "A-to-Z Government & Examination Full Forms Directory | " . SITE_NAME;
$pageDesc = "Complete A-to-Z directory of Indian government exams, defence forces, banking bodies, civil services, and technical degrees in English & Hindi with official eligibility criteria.";
$canonicalUrl = url('full-forms/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Full Forms (A-Z)', 'url' => $canonicalUrl]
];

// DefinedTermSet Schema for Directory
$definedTermSetSchema = json_encode([
    "@context" => "https://schema.org",
    "@type" => "DefinedTermSet",
    "name" => "Sarkari.online Indian Government & Examination Acronym Directory",
    "url" => $canonicalUrl,
    "description" => $pageDesc,
    "publisher" => [
        "@type" => "Organization",
        "name" => SITE_NAME,
        "url" => SITE_URL
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$customHeadHtml = "<script type=\"application/ld+json\">{$definedTermSetSchema}</script>";

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding: 2rem 0 5rem 0; background: #f8fafc;">
    <div class="container">
        
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
            <ol style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li style="color: #0f172a; font-weight: 600;">Full Forms (A-Z)</li>
            </ol>
        </nav>

        <!-- Institutional Clean Header -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
            <div style="max-width: 860px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; margin-bottom: 0.85rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    <span>Official Statutory &amp; Academic Lexicon &middot; <?= $totalCount ?> Terms Indexed (A-Z)</span>
                </div>
                <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.6rem 0; color: #0f172a; letter-spacing: -0.02em;">
                    A-to-Z Government &amp; Examination Full Forms Directory
                </h1>
                <p style="font-size: 0.95rem; color: #64748b; line-height: 1.6; margin: 0;">
                    Authentic dictionary of central ministries, competitive examinations, defense forces, banking institutions, and civil services across India with bilingual full forms (English &amp; Hindi) and statutory eligibility criteria.
                </p>
            </div>
        </div>

        <!-- Live Search Input -->
        <div style="margin-bottom: 1.5rem; position: relative; max-width: 680px;">
            <input type="text" id="glossarySearchInput" placeholder="Search by acronym, full name, or Hindi meaning (e.g. UPSC, SSC, NEET, Police, Bank)..." style="width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); transition: border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#1e3a8a'; this.style.boxShadow='0 0 0 3px rgba(30, 58, 138, 0.12)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 1px 2px rgba(15, 23, 42, 0.04)';" oninput="filterGlossaryCards()">
            <svg style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>

        <!-- Category Filter Pills Bar -->
        <div style="margin-bottom: 1rem; overflow-x: auto; white-space: nowrap; padding-bottom: 0.25rem;">
            <div style="display: inline-flex; gap: 8px;">
                <button type="button" class="cat-btn active" data-category="ALL" onclick="selectCategory('ALL')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; border: 1px solid #1e3a8a; background: #1e3a8a; color: #ffffff; cursor: pointer; transition: all 0.15s ease;">
                    All Categories
                </button>
                <button type="button" class="cat-btn" data-category="civil_services" onclick="selectCategory('civil_services')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Civil Services
                </button>
                <button type="button" class="cat-btn" data-category="defence" onclick="selectCategory('defence')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Defence
                </button>
                <button type="button" class="cat-btn" data-category="banking" onclick="selectCategory('banking')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Banking
                </button>
                <button type="button" class="cat-btn" data-category="railway" onclick="selectCategory('railway')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Railways
                </button>
                <button type="button" class="cat-btn" data-category="police" onclick="selectCategory('police')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Police &amp; Security
                </button>
                <button type="button" class="cat-btn" data-category="teaching" onclick="selectCategory('teaching')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Teaching &amp; Academic
                </button>
                <button type="button" class="cat-btn" data-category="engineering" onclick="selectCategory('engineering')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Engineering &amp; PSUs
                </button>
                <button type="button" class="cat-btn" data-category="medical" onclick="selectCategory('medical')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Medical &amp; Health
                </button>
                <button type="button" class="cat-btn" data-category="entrance" onclick="selectCategory('entrance')" style="padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid #e2e8f0; background: #ffffff; color: #334155; cursor: pointer; transition: all 0.15s ease;">
                    Entrance &amp; Law
                </button>
            </div>
        </div>

        <!-- Horizontal Alphabet Jump Bar (A to Z) -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 2rem; overflow-x: auto; white-space: nowrap; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <button type="button" class="alpha-btn active" data-letter="ALL" onclick="selectAlphabet('ALL')" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid #1e3a8a; background: #1e3a8a; color: #ffffff; cursor: pointer; transition: all 0.15s ease;">
                    ALL (<?= $totalCount ?>)
                </button>
                <?php for ($i = 65; $i <= 90; $i++): 
                    $char = chr($i);
                    $hasTerms = !empty($alphabetCounts[$char]);
                ?>
                    <button type="button" class="alpha-btn" data-letter="<?= $char ?>" onclick="selectAlphabet('<?= $char ?>')" style="padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid <?= $hasTerms ? '#cbd5e1' : '#f1f5f9' ?>; background: <?= $hasTerms ? '#ffffff' : '#f8fafc' ?>; color: <?= $hasTerms ? '#0f172a' : '#cbd5e1' ?>; cursor: <?= $hasTerms ? 'pointer' : 'default' ?>; transition: all 0.15s ease;" <?= $hasTerms ? '' : 'disabled' ?>>
                        <?= $char ?> <?= $hasTerms ? "<span style='font-size: 0.7rem; color: #64748b;'>({$alphabetCounts[$char]})</span>" : '' ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Cards Grid -->
        <div id="glossaryGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
            <?php foreach ($allTerms as $item): ?>
                <div class="glossary-card" data-letter="<?= e($item['letter']) ?>" data-category="<?= e($item['category']) ?>" data-search="<?= strtolower(e($item['acronym'] . ' ' . $item['full_form_en'] . ' ' . ($item['full_form_hi'] ?? '') . ' ' . ($item['conducting_body'] ?? ''))) ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03); transition: all 0.2s ease-in-out;" onmouseover="this.style.borderColor='#1e3a8a'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 18px -4px rgba(30, 58, 138, 0.12)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(15, 23, 42, 0.03)';">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.3rem; font-weight: 800; color: #1e3a8a; letter-spacing: -0.01em;">
                                <?= e($item['acronym']) ?>
                            </span>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                <?= e(ucfirst(str_replace('_', ' ', $item['category']))) ?>
                            </span>
                        </div>

                        <h2 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0 0 0.35rem 0; line-height: 1.35;">
                            <?= e($item['full_form_en']) ?>
                        </h2>

                        <?php if (!empty($item['full_form_hi'])): ?>
                            <div style="font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 0.75rem; font-family: 'Noto Sans Devanagari', sans-serif;">
                                हिंदी: <?= e($item['full_form_hi']) ?>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.85rem; color: #64748b; line-height: 1.55; margin: 0 0 1.25rem 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e($item['overview']) ?>
                        </p>
                    </div>

                    <div>
                        <?php if (!empty($item['conducting_body'])): ?>
                            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.85rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 4px;">
                                <span>Authority:</span>
                                <strong style="color: #1e293b; font-weight: 600;"><?= e($item['conducting_body']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <a href="<?= url('full-forms/' . $item['slug'] . '/') ?>" style="display: inline-flex; width: 100%; align-items: center; justify-content: space-between; padding: 0.55rem 0.85rem; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; color: #1e3a8a; font-weight: 700; font-size: 0.825rem; text-decoration: none; transition: background 0.15s ease, border-color 0.15s ease;" onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
                            <span>View Full Details &amp; Criteria</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem 1rem; background: #ffffff; border-radius: 10px; border: 1px dashed #cbd5e1; margin-top: 1.5rem;">
            <p style="font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">No matching full forms found</p>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Try searching for another acronym or click 'ALL' to reset the filters.</p>
        </div>

    </div>
</main>

<script>
let currentLetter = 'ALL';
let currentCategory = 'ALL';

function selectAlphabet(letter) {
    currentLetter = letter;
    document.querySelectorAll('.alpha-btn').forEach(btn => {
        if (btn.getAttribute('data-letter') === letter) {
            btn.style.background = '#1e3a8a';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#1e3a8a';
        } else if (!btn.disabled) {
            btn.style.background = '#ffffff';
            btn.style.color = '#0f172a';
            btn.style.borderColor = '#cbd5e1';
        }
    });
    filterGlossaryCards();
}

function selectCategory(category) {
    currentCategory = category;
    document.querySelectorAll('.cat-btn').forEach(btn => {
        if (btn.getAttribute('data-category') === category) {
            btn.style.background = '#1e3a8a';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#1e3a8a';
            btn.style.fontWeight = '700';
        } else {
            btn.style.background = '#ffffff';
            btn.style.color = '#334155';
            btn.style.borderColor = '#e2e8f0';
            btn.style.fontWeight = '600';
        }
    });
    filterGlossaryCards();
}

function filterGlossaryCards() {
    const q = document.getElementById('glossarySearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.glossary-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const cardLetter = card.getAttribute('data-letter');
        const cardCategory = card.getAttribute('data-category');
        const cardSearch = card.getAttribute('data-search');

        const matchLetter = (currentLetter === 'ALL' || cardLetter === currentLetter);
        const matchCategory = (currentCategory === 'ALL' || cardCategory === currentCategory);
        const matchSearch = (!q || cardSearch.includes(q));

        if (matchLetter && matchCategory && matchSearch) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noMsg = document.getElementById('noResultsMsg');
    if (noMsg) {
        noMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
