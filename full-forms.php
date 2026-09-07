<?php
/**
 * Sarkari.online - A-to-Z Government & Examination Full Forms Hub
 * High-speed, responsive, minimalist educational directory.
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

    $pageTitle = "Full Form of {$term['acronym']} — {$term['full_form_en']} ({$term['full_form_hi']})";
    $pageDesc = "What is the full form of {$term['acronym']}? The complete full form is {$term['full_form_en']} ({$term['full_form_hi']}). Check overview, eligibility criteria, exam selection process, and official portal updates.";
    $canonicalUrl = url("full-forms/{$term['slug']}/");
    $ogType = 'article';

    $crumbs = [
        ['label' => 'Home', 'url' => url()],
        ['label' => 'Full Forms (A-Z)', 'url' => url('full-forms/')],
        ['label' => $term['acronym'], 'url' => null]
    ];

    // Structured Data: Schema.org DefinedTerm & BreadcrumbList (NO FAQPage per Google 2026 guidelines)
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
            <article style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 2rem;">
                
                <!-- Category Badge & Last Reviewed -->
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 1.25rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #0284c7; background: #e0f2fe; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?>
                    </span>
                    <div style="font-size: 0.75rem; color: #64748b; display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <span>Verified by Editorial Desk &middot; Last Reviewed: <?= date('M d, Y', strtotime($term['last_reviewed_at'])) ?></span>
                    </div>
                </div>

                <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0; line-height: 1.25;">
                    Full Form of <?= e($term['acronym']) ?>
                </h1>

                <!-- Direct Answer Snippet Box (Position 0 Target) -->
                <div style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 10px; padding: 1.5rem; margin: 1.5rem 0 2rem 0;">
                    <div style="font-size: 0.75rem; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
                        ⚡ Direct Answer &amp; Core Meaning
                    </div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1.3; margin-bottom: 0.35rem;">
                        <?= e($term['full_form_en']) ?>
                    </div>
                    <?php if (!empty($term['full_form_hi'])): ?>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 0.75rem;">
                            हिंदी अर्थ: <?= e($term['full_form_hi']) ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0f2fe; font-size: 0.875rem;">
                        <div>
                            <span style="color: #64748b; display: block; font-size: 0.75rem; font-weight: 600;">Conducting / Regulatory Body</span>
                            <strong style="color: #0f172a;"><?= e($term['conducting_body'] ?? 'Government of India') ?></strong>
                        </div>
                        <?php if (!empty($term['official_portal'])): ?>
                            <div>
                                <span style="color: #64748b; display: block; font-size: 0.75rem; font-weight: 600;">Official Authority Portal</span>
                                <a href="<?= e($term['official_portal']) ?>" target="_blank" rel="noopener noreferrer" style="color: #0284c7; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <span><?= parse_url($term['official_portal'], PHP_URL_HOST) ?></span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Structured Fact Sections -->
                <div style="font-size: 0.95rem; color: #334155; line-height: 1.7;">
                    
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0;">
                        1. What is <?= e($term['acronym']) ?>? (Overview &amp; Role)
                    </h2>
                    <p style="margin: 0 0 1.25rem 0;">
                        <?= nl2br(e($term['overview'])) ?>
                    </p>

                    <?php if (!empty($term['eligibility_criteria'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0;">
                            2. Eligibility Criteria &amp; Age Limits
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['eligibility_criteria'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['selection_process'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0;">
                            3. Examination &amp; Selection Process
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['selection_process'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($term['syllabus_snapshot'])): ?>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 1.75rem 0 0.5rem 0;">
                            4. Core Syllabus &amp; Key Subjects
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['syllabus_snapshot'])) ?>
                        </p>
                    <?php endif; ?>

                </div>

                <!-- Internal Linking Engine / Live Exam Updates Connection -->
                <?php if (!empty($term['related_article_slug'])): ?>
                    <div style="margin-top: 2rem; padding: 1.25rem; background: #f8fafc; border-left: 4px solid #0284c7; border-radius: 0 8px 8px 0;">
                        <span style="font-weight: 800; color: #0284c7; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                            📌 LIVE NOTIFICATIONS &amp; DATES
                        </span>
                        <a href="<?= url('article/' . $term['related_article_slug'] . '/') ?>" style="color: #0f172a; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; font-size: 1rem;">
                            Check Verified 2026 Examination Schedule &amp; Application Guide for <?= e($term['acronym']) ?> &rarr;
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Back to Directory CTA -->
                <div style="margin-top: 2.5rem; pt: 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <a href="<?= url('full-forms/') ?>" style="color: #0284c7; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.875rem;">
                        &larr; <span>Back to A-to-Z Full Forms Directory</span>
                    </a>
                    <a href="<?= url('tools/') ?>" style="color: #64748b; font-weight: 600; text-decoration: none; font-size: 0.875rem;">
                        Explore Student Tools &rarr;
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
                            <a href="<?= url('full-forms/' . $rt['slug'] . '/') ?>" style="display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.15s ease;" onmouseover="this.style.borderColor='#0284c7';" onmouseout="this.style.borderColor='#e2e8f0';">
                                <span style="font-size: 1rem; font-weight: 800; color: #0284c7; display: block; margin-bottom: 0.25rem;">
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
$allTerms = GlossaryService::getTerms(null, null, null, 200, 0);
$alphabetCounts = GlossaryService::getAlphabetCounts();
$categoryCounts = GlossaryService::getCategoryCounts();
$totalCount = GlossaryService::getTotalCount();

$pageTitle = "A-to-Z Government & Examination Full Forms Directory — Sarkari.online";
$pageDesc = "Complete A-to-Z dictionary of Indian government exams, defense ranks, banking terms, civil services, and educational degrees in English & Hindi with official eligibility criteria.";
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
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="max-width: 840px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; margin-bottom: 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>Official Statutory &amp; Academic Lexicon &middot; <?= $totalCount ?> Terms Indexed</span>
                </div>
                <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.6rem 0; color: #0f172a;">
                    A-to-Z Government &amp; Examination Full Forms Directory
                </h1>
                <p style="font-size: 0.95rem; color: #64748b; line-height: 1.6; margin: 0;">
                    Comprehensive dictionary of competitive exams, central ministries, defense ranks, banking bodies, and academic degrees across India with bilingual full forms (English &amp; Hindi).
                </p>
            </div>
        </div>

        <!-- Live Search Input -->
        <div style="margin-bottom: 1.5rem; position: relative; max-width: 600px;">
            <input type="text" id="glossarySearchInput" placeholder="Search full form (e.g. UPSC, SSC, NEET, Police, Bank)..." style="width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.03);" oninput="filterGlossaryCards()">
            <svg style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>

        <!-- Horizontal Alphabet Filter Bar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 2rem; overflow-x: auto; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <button type="button" class="alpha-btn active" data-letter="ALL" onclick="selectAlphabet('ALL')" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid #0284c7; background: #0284c7; color: #ffffff; cursor: pointer;">
                    ALL (<?= $totalCount ?>)
                </button>
                <?php for ($i = 65; $i <= 90; $i++): 
                    $char = chr($i);
                    $hasTerms = !empty($alphabetCounts[$char]);
                ?>
                    <button type="button" class="alpha-btn" data-letter="<?= $char ?>" onclick="selectAlphabet('<?= $char ?>')" style="padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; border: 1px solid <?= $hasTerms ? '#e2e8f0' : '#f1f5f9' ?>; background: <?= $hasTerms ? '#ffffff' : '#f8fafc' ?>; color: <?= $hasTerms ? '#0f172a' : '#cbd5e1' ?>; cursor: <?= $hasTerms ? 'pointer' : 'default' ?>;" <?= $hasTerms ? '' : 'disabled' ?>>
                        <?= $char ?> <?= $hasTerms ? "<span style='font-size: 0.7rem; color: #64748b;'>({$alphabetCounts[$char]})</span>" : '' ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Cards Grid -->
        <div id="glossaryGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
            <?php foreach ($allTerms as $item): ?>
                <div class="glossary-card" data-letter="<?= e($item['letter']) ?>" data-category="<?= e($item['category']) ?>" data-search="<?= strtolower(e($item['acronym'] . ' ' . $item['full_form_en'] . ' ' . ($item['full_form_hi'] ?? '') . ' ' . ($item['conducting_body'] ?? ''))) ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.15s ease-in-out;" onmouseover="this.style.borderColor='#0284c7'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)';">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.25rem; font-weight: 800; color: #0284c7; letter-spacing: -0.01em;">
                                <?= e($item['acronym']) ?>
                            </span>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                <?= e(ucfirst(str_replace('_', ' ', $item['category']))) ?>
                            </span>
                        </div>

                        <h2 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0 0 0.35rem 0; line-height: 1.35;">
                            <?= e($item['full_form_en']) ?>
                        </h2>

                        <?php if (!empty($item['full_form_hi'])): ?>
                            <div style="font-size: 0.9rem; font-weight: 600; color: #475569; margin-bottom: 0.75rem;">
                                हिंदी: <?= e($item['full_form_hi']) ?>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; margin: 0 0 1.25rem 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e($item['overview']) ?>
                        </p>
                    </div>

                    <a href="<?= url('full-forms/' . $item['slug'] . '/') ?>" style="display: inline-flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.85rem; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; color: #0284c7; font-weight: 700; font-size: 0.825rem; text-decoration: none; transition: background 0.15s ease;" onmouseover="this.style.background='#e0f2fe';" onmouseout="this.style.background='#f8fafc';">
                        <span>Read Full Details &amp; Criteria</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem 1rem; background: #ffffff; border-radius: 10px; border: 1px dashed #cbd5e1; margin-top: 1.5rem;">
            <p style="font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">No matching full forms found</p>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Try searching for another acronym or click 'ALL' to reset the filter.</p>
        </div>

    </div>
</main>

<script>
let currentLetter = 'ALL';

function selectAlphabet(letter) {
    currentLetter = letter;
    document.querySelectorAll('.alpha-btn').forEach(btn => {
        if (btn.getAttribute('data-letter') === letter) {
            btn.style.background = '#0284c7';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#0284c7';
        } else if (!btn.disabled) {
            btn.style.background = '#ffffff';
            btn.style.color = '#0f172a';
            btn.style.borderColor = '#e2e8f0';
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
        const cardSearch = card.getAttribute('data-search');

        const matchLetter = (currentLetter === 'ALL' || cardLetter === currentLetter);
        const matchSearch = (!q || cardSearch.includes(q));

        if (matchLetter && matchSearch) {
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
