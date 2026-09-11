<?php
/**
 * Sarkari.online - A-to-Z Government & Examination Full Forms Hub
 * High-speed, responsive, institutional educational directory matching Sarkari.online design system.
 * Supports both master index listing (/full-forms/) and detailed single-term views (/full-forms/:slug/).
 */
require_once __DIR__ . '/config.php';

use App\Services\GlossaryService;
use App\Services\SalaryTableRenderer;
use App\Services\FaqSchemaRenderer;
use App\Database\Database;
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

    // Fetch verified entity facts (Phase B/C integration)
    $facts = null;
    try {
        $facts = Database::fetchOne("SELECT * FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => (int)$term['id']]);
    } catch (\Throwable $e) {}

    // High-CTR Dynamic SERP Title (Anti-spoiler for regional/posts, authoritative for national)
    $pageTitle = GlossaryService::generateMetaTitle($term, $facts);
    $pageDesc = GlossaryService::generateMetaDescription($term, $facts);
    $canonicalUrl = url("full-forms/{$term['slug']}/");
    $hubUrl = url('full-forms/');
    $ogType = 'article';

    $crumbs = [
        ['label' => 'Home', 'url' => url()],
        ['label' => 'Full Forms (A-Z)', 'url' => $hubUrl],
        ['label' => $term['acronym'], 'url' => null]
    ];

    // Structured Data: Schema.org DefinedTerm & BreadcrumbList (NO FAQPage per Google guidelines)
    $customHeadHtml = GlossaryService::generateDefinedTermSchema($term, $canonicalUrl, $hubUrl);

    $relatedTerms = GlossaryService::getRelatedTerms((int)$term['id'], $term['category'], 6);

    $salaryTableHtml = !empty($facts) ? SalaryTableRenderer::render($facts) : null;
    $faqBlockHtml = !empty($facts['faqs_json']) ? FaqSchemaRenderer::render($facts['faqs_json']) : null;

    // Internal link candidate resolver (Live published articles/updates)
    $matchedArticle = null;
    if (!empty($term['related_article_slug'])) {
        $matchedArticle = [
            'slug'  => $term['related_article_slug'],
            'title' => "Verified 2026 Examination Schedule & Application Guide for {$term['acronym']}"
        ];
    } else {
        try {
            $matched = Database::fetchOne(
                "SELECT slug, title FROM articles 
                 WHERE status = 'published' 
                   AND (title LIKE :acr OR title LIKE :fn OR slug LIKE :slg) 
                 ORDER BY published_at DESC LIMIT 1",
                [
                    'acr' => '%' . $term['acronym'] . '%',
                    'fn'  => '%' . $term['full_form_en'] . '%',
                    'slg' => '%' . $term['slug'] . '%'
                ]
            );
            if ($matched) {
                $matchedArticle = $matched;
            }
        } catch (\Throwable $e) {}
    }

    include __DIR__ . '/components/head.php';
    include __DIR__ . '/components/header.php';
    ?>

    <main class="site-main" style="padding: 2rem 0 5rem 0; background: #f8fafc;">
        <div class="container">
            
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
                        <?php 
                        $reviewDate = !empty($facts['last_verified_at']) 
                            ? $facts['last_verified_at'] 
                            : (!empty($term['last_reviewed_at']) ? $term['last_reviewed_at'] : 'now');
                        ?>
                        <span>Verified Statutory Lexicon &middot; Last Reviewed: <?= date('M d, Y', strtotime($reviewDate)) ?></span>
                    </div>
                </div>

                <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0; line-height: 1.25; letter-spacing: -0.02em;">
                    Full Form of <?= e($term['acronym']) ?>
                </h1>

                <!-- Google Position 0 Direct Answer Snippet Target Block -->
                <?= GlossaryService::renderDirectAnswerBlock($term) ?>

                <!-- Structured Compact Facts Table -->
                <?= GlossaryService::renderFactsTable($term) ?>

                <!-- Structured Factual Sections -->
                <div style="font-size: 0.95rem; color: #334155; line-height: 1.7;">
                    
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                        1. Official Mandate, Scope &amp; Background
                    </h2>
                    <p style="margin: 0 0 1.25rem 0;">
                        <?= nl2br(e($term['overview'])) ?>
                    </p>

                    <?php if (!empty($term['eligibility_criteria'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            2. Eligibility Criteria, Qualifications &amp; Age Limits
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['eligibility_criteria'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['selection_process'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            3. Examination Scheme &amp; Selection Procedure
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['selection_process'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['syllabus_snapshot'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            4. Core Syllabus &amp; Key Subjects
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['syllabus_snapshot'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Section 5: Salary & Pay Scale (Only rendered when verified facts are present) -->
                    <?php if (!empty($salaryTableHtml)): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            5. Salary, Pay Scale &amp; 7th CPC Allowances
                        </h2>
                        <?= $salaryTableHtml ?>
                    <?php endif; ?>

                    <!-- Section 6: Career Growth & Promotion Hierarchy -->
                    <?php if (!empty($facts['career_growth_summary'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            6. Career Growth &amp; Promotion Hierarchy
                        </h2>
                        <p style="margin: 0 0 1.25rem 0; background: #f8fafc; border-left: 4px solid #1e3a8a; padding: 0.85rem 1.15rem; border-radius: 0 8px 8px 0; color: #334155; line-height: 1.7;">
                            <?= nl2br(e($facts['career_growth_summary'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Section 7: Frequently Asked Questions & FAQPage Schema -->
                    <?php if (!empty($faqBlockHtml)): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 2rem 0 0.5rem 0;">
                            7. Frequently Asked Questions (FAQs)
                        </h2>
                        <?= $faqBlockHtml ?>
                    <?php endif; ?>

                </div>

                <!-- Internal Linking Engine / Live Exam Updates Connection -->
                <?php if (!empty($matchedArticle)): ?>
                    <div style="margin-top: 2rem; padding: 1.25rem 1.5rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;">
                        <span style="font-weight: 800; color: #1e3a8a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                            📌 LIVE RECRUITMENT &amp; EXAM UPDATES
                        </span>
                        <a href="<?= url('article/' . $matchedArticle['slug'] . '/') ?>" style="color: #1e3a8a; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; font-size: 1rem;">
                            <?= e($matchedArticle['title']) ?> &rarr;
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
$reqLetter = $_GET['letter'] ?? null;
$reqLetter = (!empty($reqLetter) && strtoupper($reqLetter) !== 'ALL') ? strtoupper(substr($reqLetter, 0, 1)) : null;

$allTerms = GlossaryService::getTerms(null, null, null, 350, 0);
$alphabetCounts = GlossaryService::getAlphabetCounts();
$categoryCounts = GlossaryService::getCategoryCounts();
$totalCount = GlossaryService::getTotalCount();

$pageTitle = "A to Z Govt & Exam Full Forms Directory | " . SITE_NAME;
$pageDesc = "Complete A-to-Z directory of Indian government exams, defence, banking, civil services, and technical full forms with eligibility on Sarkari.online.";
$canonicalUrl = url('full-forms/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Full Forms (A-Z)', 'url' => $canonicalUrl]
];

// DefinedTermSet Schema for Directory with individual DefinedTerm items
$customHeadHtml = GlossaryService::generateHubSchema($allTerms, $canonicalUrl);

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
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
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

        <!-- Live Search Input (Pure client-side filter, creates zero junk crawl URLs) -->
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

        <!-- Crawlable Alphabet Jump Bar (A to Z) with clean click interception -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 2rem; overflow-x: auto; white-space: nowrap; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <a href="<?= url('full-forms/') ?>" class="alpha-btn <?= empty($reqLetter) ? 'active' : '' ?>" data-letter="ALL" onclick="selectAlphabet('ALL', event)" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid #1e3a8a; background: <?= empty($reqLetter) ? '#1e3a8a' : '#ffffff' ?>; color: <?= empty($reqLetter) ? '#ffffff' : '#0f172a' ?>; text-decoration: none; display: inline-block; transition: all 0.15s ease;">
                    ALL (<?= $totalCount ?>)
                </a>
                <?php for ($i = 65; $i <= 90; $i++): 
                    $char = chr($i);
                    $hasTerms = !empty($alphabetCounts[$char]);
                    $isSel = ($reqLetter === $char);
                ?>
                    <?php if ($hasTerms): ?>
                        <a href="<?= url('full-forms/?letter=' . $char) ?>" class="alpha-btn <?= $isSel ? 'active' : '' ?>" data-letter="<?= $char ?>" onclick="selectAlphabet('<?= $char ?>', event)" style="padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid <?= $isSel ? '#1e3a8a' : '#cbd5e1' ?>; background: <?= $isSel ? '#1e3a8a' : '#ffffff' ?>; color: <?= $isSel ? '#ffffff' : '#0f172a' ?>; text-decoration: none; display: inline-block; transition: all 0.15s ease;">
                            <?= $char ?> <span style="font-size: 0.7rem; color: <?= $isSel ? '#bfdbfe' : '#64748b' ?>;">(<?= $alphabetCounts[$char] ?>)</span>
                        </a>
                    <?php else: ?>
                        <span class="alpha-btn disabled" data-letter="<?= $char ?>" style="padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid #f1f5f9; background: #f8fafc; color: #cbd5e1; display: inline-block; cursor: default;">
                            <?= $char ?>
                        </span>
                    <?php endif; ?>
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
let currentLetter = '<?= $reqLetter ?: "ALL" ?>';
let currentCategory = 'ALL';

function selectAlphabet(letter, event) {
    if (event) {
        event.preventDefault();
    }
    currentLetter = letter;
    document.querySelectorAll('.alpha-btn').forEach(btn => {
        if (btn.getAttribute('data-letter') === letter) {
            btn.style.background = '#1e3a8a';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#1e3a8a';
        } else if (!btn.classList.contains('disabled')) {
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
