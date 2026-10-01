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
use App\Services\CrawlEfficiencyService;
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

    // ── HTTP Crawl Efficiency & Cache Validation Headers (Googlebot 304 & ETag) ──
    $termTimes = [
        !empty($term['updated_at']) ? strtotime($term['updated_at']) : 0,
        !empty($term['last_reviewed_at']) ? strtotime($term['last_reviewed_at']) : 0,
        !empty($facts['last_verified_at']) ? strtotime($facts['last_verified_at']) : 0,
        !empty($facts['updated_at']) ? strtotime($facts['updated_at']) : 0
    ];
    $latestTimestamp = max($termTimes) ?: time();
    $termModTime = date('Y-m-d H:i:s', $latestTimestamp);
    CrawlEfficiencyService::handleConditionalGet('ff-' . $term['slug'], $termModTime);

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

    // Internal link candidate resolver (Live published articles/updates only — prevents 301 circular redirects)
    $matchedArticle = null;
    if (!empty($term['related_article_slug'])) {
        try {
            $matchedArticle = Database::fetchOne(
                "SELECT slug, title FROM articles WHERE slug = :slg AND status = 'published' LIMIT 1",
                ['slg' => $term['related_article_slug']]
            );
        } catch (\Throwable $e) {}
    }

    if (!$matchedArticle) {
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
            } else {
                // Crawl Velocity Anchor: Link to latest published update to eliminate dead ends
                $matchedArticle = Database::fetchOne(
                    "SELECT slug, title FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 1"
                );
            }
        } catch (\Throwable $e) {}
    }

    include __DIR__ . '/components/head.php';
    include __DIR__ . '/components/header.php';
    ?>

    <main class="site-main" style="padding: 2rem 0 5rem 0; background: #f4f6f9;">
        <div class="container" style="max-width: 1240px; margin: 0 auto; padding: 0 1rem;">
            
            <!-- Breadcrumbs -->
            <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
                <ol style="display: flex; flex-wrap: wrap; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #546e7a;">
                    <li><a href="<?= url() ?>" style="color: #1a237e; text-decoration: none; font-weight: 600;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                    <li><a href="<?= url('full-forms/') ?>" style="color: #1a237e; text-decoration: none; font-weight: 600;">Full Forms</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                    <li style="color: #1a237e; font-weight: 700;"><?= e($term['acronym']) ?></li>
                </ol>
            </nav>

            <!-- 2-COLUMN PREMIUM EDITORIAL LAYOUT (Matches Mockup) -->
            <div class="fullform-editorial-grid" style="display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 2rem; align-items: start;">
                
                <!-- LEFT COLUMN: MAIN ARTICLE -->
                <article style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
                    
                    <!-- Category Badge & Last Updated -->
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 1rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: #ffffff; background: #1a237e; padding: 4px 12px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?>
                        </span>
                        <div style="font-size: 0.78rem; color: #78909c; font-weight: 500;">
                            Last reviewed: <?= date('d M Y', $latestTimestamp) ?>
                        </div>
                    </div>

                    <!-- Authority H1 Headline -->
                    <h1 style="font-size: 2.15rem; font-weight: 800; color: #1a237e; margin: 0 0 1.25rem 0; line-height: 1.25; letter-spacing: -0.02em;">
                        <?= e($term['acronym']) ?> Full Form: <?= e($term['full_form_en']) ?>
                    </h1>

                    <!-- QUICK FACTS BOX WITH THICK ORANGE LEFT BORDER (Brand Logo Identity) -->
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-left: 5px solid #f57c00; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.75rem;">
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #78909c; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Conducting Body</span>
                                <strong style="font-size: 1rem; color: #1a237e; font-weight: 700;"><?= e($term['conducting_body'] ?? 'Government of India') ?></strong>
                            </div>
                            <?php if (!empty($term['official_portal'])): ?>
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #78909c; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Official Portal</span>
                                <a href="<?= e($term['official_portal']) ?>" target="_blank" rel="noopener noreferrer" style="color: #1a237e; font-weight: 700; font-size: 1rem; text-decoration: underline; text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span><?= parse_url($term['official_portal'], PHP_URL_HOST) ?: 'Official Portal' ?></span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #78909c; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Category / Domain</span>
                                <strong style="font-size: 1rem; color: #0f172a; font-weight: 700;"><?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?></strong>
                            </div>
                            <?php if (!empty($term['full_form_hi'])): ?>
                            <div>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #78909c; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">हिंदी अर्थ (Hindi Meaning)</span>
                                <strong style="font-size: 1rem; color: #0f172a; font-weight: 700; font-family: 'Noto Sans Devanagari', sans-serif;"><?= e($term['full_form_hi']) ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- QUICK ANSWER HIGHLIGHTED CALLOUT BOX (Ice Blue Background) -->
                    <div style="background: #eef5fc; border: 1px solid #d0e3f7; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
                        <p style="margin: 0; font-size: 1.05rem; line-height: 1.65; color: #1a237e;">
                            <strong style="color: #0d1642;"><?= e($term['acronym']) ?> stands for</strong> <strong><?= e($term['full_form_en']) ?></strong><?php if (!empty($term['full_form_hi'])): ?> (हिंदी में: <strong><?= e($term['full_form_hi']) ?></strong>)<?php endif; ?>. <?= e($term['overview'] ? explode('.', $term['overview'])[0] . '.' : '') ?>
                        </p>
                    </div>

                    <!-- Factual Sections -->
                    <div style="font-size: 0.975rem; color: #37474f; line-height: 1.75;">
                        
                        <!-- Section: Overview -->
                        <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                            Overview
                        </h2>
                        <p style="margin: 0 0 1.25rem 0;">
                            <?= nl2br(e($term['overview'])) ?>
                        </p>

                        <!-- Section: Eligibility & Age Limits -->
                        <?php if (!empty($term['eligibility_criteria'])): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Eligibility &amp; Age Limits
                            </h2>
                            <?= GlossaryService::renderSectionBulletList($term['eligibility_criteria']) ?>
                        <?php endif; ?>

                        <!-- Section: Selection Process -->
                        <?php if (!empty($term['selection_process'])): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Selection Process
                            </h2>
                            <?= GlossaryService::renderSectionBulletList($term['selection_process']) ?>
                        <?php endif; ?>

                        <!-- Section: Syllabus Highlights -->
                        <?php if (!empty($term['syllabus_snapshot'])): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Syllabus Highlights
                            </h2>
                            <?= GlossaryService::renderSectionBulletList($term['syllabus_snapshot']) ?>
                        <?php endif; ?>

                        <!-- Section: Salary & Pay Scale -->
                        <?php if (!empty($salaryTableHtml)): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Salary &amp; Pay Scale
                            </h2>
                            <?= $salaryTableHtml ?>
                        <?php endif; ?>

                        <!-- Section: Career Growth -->
                        <?php if (!empty($facts['career_growth_summary'])): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Career Growth
                            </h2>
                            <div style="background: #fafafa; border-left: 4px solid #f57c00; padding: 1rem 1.25rem; border-radius: 0 8px 8px 0; color: #37474f; line-height: 1.7; margin-bottom: 1.5rem;">
                                <?= nl2br(e($facts['career_growth_summary'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Section: FAQs -->
                        <?php if (!empty($faqBlockHtml)): ?>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1a237e; margin: 2.25rem 0 0.85rem 0; padding-left: 0.85rem; border-left: 4px solid #1a237e;">
                                Frequently Asked Questions
                            </h2>
                            <?= $faqBlockHtml ?>
                        <?php endif; ?>

                    </div>

                    <!-- Internal Linking to Active Recruitment -->
                    <?php if (!empty($matchedArticle)): ?>
                        <div style="margin-top: 2rem; padding: 1.25rem 1.5rem; background: #e8eaf6; border: 1px solid #c5cae9; border-radius: 8px;">
                            <span style="font-weight: 800; color: #1a237e; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                                📢 LIVE RECRUITMENT &amp; EXAM NOTICE
                            </span>
                            <a href="<?= url('article/' . $matchedArticle['slug'] . '/') ?>" style="color: #1a237e; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; font-size: 1.05rem;">
                                <?= e($matchedArticle['title']) ?> &rarr;
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Bottom Navigation -->
                    <div style="margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                        <a href="<?= url('full-forms/') ?>" style="color: #1a237e; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem;">
                            &larr; <span>Back to A-to-Z Full Forms Directory</span>
                        </a>
                        <a href="<?= url('tools/') ?>" style="color: #546e7a; font-weight: 600; text-decoration: none; font-size: 0.875rem;">
                            Explore Student Calculators &rarr;
                        </a>
                    </div>

                </article>

                <!-- RIGHT COLUMN: STICKY EDITORIAL SIDEBAR (Mockup) -->
                <aside class="fullform-sidebar" style="position: sticky; top: 90px;">
                    
                    <!-- Related Full Forms Card -->
                    <?php if (!empty($relatedTerms)): ?>
                        <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 1.5rem;">
                            <h3 style="font-size: 1.05rem; font-weight: 800; color: #1a237e; margin: 0 0 1rem 0; padding-bottom: 0.65rem; border-bottom: 2px solid #f57c00; display: flex; align-items: center; justify-content: space-between;">
                                <span>Related Full Forms</span>
                                <span style="font-size: 0.7rem; font-weight: 700; color: #78909c; text-transform: uppercase;"><?= e(ucfirst(str_replace('_', ' ', $term['category']))) ?></span>
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($relatedTerms as $rt): ?>
                                    <a href="<?= url('full-forms/' . $rt['slug'] . '/') ?>" style="display: block; padding: 0.85rem 1rem; border: 1px solid #e0e0e0; border-radius: 8px; text-decoration: none; background: #fafafa; transition: all 0.2s ease;" onmouseover="this.style.borderColor='#1a237e'; this.style.background='#ffffff'; this.style.boxShadow='0 4px 12px rgba(26,35,126,0.1)';" onmouseout="this.style.borderColor='#e0e0e0'; this.style.background='#fafafa'; this.style.boxShadow='none';">
                                        <strong style="color: #1a237e; font-size: 1.05rem; display: block; margin-bottom: 2px;">
                                            <?= e($rt['acronym']) ?>
                                        </strong>
                                        <span style="color: #546e7a; font-size: 0.825rem; font-weight: 500; display: block; line-height: 1.35;">
                                            <?= e($rt['full_form_en']) ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Student Tools Callout Card -->
                    <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                        <div style="font-size: 0.72rem; font-weight: 800; color: #f57c00; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.35rem;">
                            STUDENT UTILITIES
                        </div>
                        <h4 style="font-size: 0.95rem; font-weight: 800; color: #1a237e; margin: 0 0 0.5rem 0;">
                            Salary &amp; Eligibility Calculators
                        </h4>
                        <p style="font-size: 0.825rem; color: #546e7a; line-height: 1.5; margin: 0 0 1rem 0;">
                            Calculate in-hand salary as per 7th CPC (50% DA, HRA), calculate exact age, or convert CGPA to percentage.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" style="display: block; padding: 8px 12px; background: #1a237e; color: #ffffff; font-size: 0.8rem; font-weight: 700; text-decoration: none; border-radius: 6px; text-align: center;">
                                7th Pay Calculator &rarr;
                            </a>
                            <a href="<?= url('tools/age-calculator/') ?>" style="display: block; padding: 8px 12px; background: #fafafa; border: 1px solid #e0e0e0; color: #1a237e; font-size: 0.8rem; font-weight: 700; text-decoration: none; border-radius: 6px; text-align: center;">
                                Age Calculator &rarr;
                            </a>
                        </div>
                    </div>

                </aside>

            </div>

        </div>
    </main>

    <style>
    @media (max-width: 992px) {
        .fullform-editorial-grid {
            grid-template-columns: 1fr !important;
        }
        .fullform-sidebar {
            position: static !important;
            margin-top: 1.5rem !important;
        }
    }
    </style>

    <?php
    include __DIR__ . '/components/footer.php';
    exit;
}

// -----------------------------------------------------------------------------
// CASE B: MASTER DIRECTORY HUB VIEW (/full-forms/)
// -----------------------------------------------------------------------------
$reqLetter = $_GET['letter'] ?? null;
$reqLetter = (!empty($reqLetter) && strtoupper($reqLetter) !== 'ALL') ? strtoupper(substr($reqLetter, 0, 1)) : null;

$reqCategory = $_GET['category'] ?? null;
$reqCategory = (!empty($reqCategory) && strtolower($reqCategory) !== 'all') ? strtolower(trim($reqCategory)) : null;

$reqSearch = $_GET['q'] ?? null;
$reqSearch = !empty($reqSearch) ? trim($reqSearch) : null;

// Pagination configuration (18 terms per page — clean 3x6 or 2x9 grid)
$perPage = 18;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

$totalFilteredCount = GlossaryService::getTermsCount($reqLetter, $reqCategory, $reqSearch);
$totalPages = (int)ceil($totalFilteredCount / $perPage);
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$allTerms = GlossaryService::getTerms($reqLetter, $reqCategory, $reqSearch, $perPage, $offset);
$alphabetCounts = GlossaryService::getAlphabetCounts();
$categoryCounts = GlossaryService::getCategoryCounts();
$totalCount = GlossaryService::getTotalCount();

// ── HTTP Crawl Efficiency & Cache Validation Headers (Googlebot 304 & ETag) ──
$hubModTime = Database::fetchValue("SELECT MAX(updated_at) FROM glossary_terms") ?: 'now';
CrawlEfficiencyService::handleConditionalGet('ff-hub-' . ($reqLetter ?: 'all') . '-p' . $currentPage, $hubModTime);

$pageSuffix = ($currentPage > 1) ? " (Page {$currentPage})" : "";
$letterSuffix = !empty($reqLetter) ? " - Letter {$reqLetter}" : "";
$pageTitle = "A to Z Govt & Exam Full Forms Directory{$letterSuffix}{$pageSuffix} | " . SITE_NAME;
$pageDesc = "Complete A-to-Z directory of Indian government exams, defence, banking, civil services, and technical full forms with eligibility on Sarkari.online.{$pageSuffix}";

// Canonical is STRICTLY the clean hub URL to prevent indexation bloat of 280+ faceted filter variations
$canonicalUrl = url('full-forms/');
$ogType = 'website';

// Faceted Navigation Rule: If any filter, search, or pagination is active, do NOT index the filter page!
$hasActiveFilter = !empty($reqLetter) || !empty($reqCategory) || !empty($reqSearch) || $currentPage > 1;
if ($hasActiveFilter) {
    $metaRobots = 'noindex, follow';
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, follow');
    }
}

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Full Forms (A-Z)', 'url' => url('full-forms/')]
];
if (!empty($reqLetter)) {
    $crumbs[] = ['label' => "Letter {$reqLetter}", 'url' => null];
}

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

        <!-- Directory Header (Executive Redesign Hero Card) -->
        <div class="directory-hero-card" style="background: #ffffff; border: 4px solid #f57c00; border-radius: 16px; padding: 2.25rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(245, 124, 0, 0.08);">
            <div style="margin-bottom: 1rem;">
                <span style="background: #f57c00; color: #ffffff; padding: 6px 18px; border-radius: 9999px; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.3px; display: inline-block;">
                    Official Examination &amp; Career Lexicon &middot; <?= $totalCount ?> Terms (A-Z)
                </span>
            </div>
            <h1 style="font-size: 2.25rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.85rem 0; color: #0f172a; letter-spacing: -0.02em;">
                A-to-Z Government &amp; Examination Full Forms Directory
            </h1>
            <p style="font-size: 1rem; color: #546e7a; line-height: 1.6; margin: 0; max-width: 860px;">
                Verified reference directory of competitive examinations, government agencies, defense forces, banking institutions, and civil services across India with bilingual acronyms, eligibility criteria, and direct portal links.
            </p>
        </div>

        <!-- Live Search Input -->
        <div style="margin-bottom: 1.5rem; position: relative; max-width: 680px;">
            <input type="text" id="glossarySearchInput" placeholder="Search acronym, full form, or exam (e.g. UPSC, SSC, NEET, Police, Bank)..." style="width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; outline: none; background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: border-color 0.15s ease, box-shadow 0.15s ease;" onfocus="this.style.borderColor='#1a237e'; this.style.boxShadow='0 0 0 3px rgba(26, 35, 126, 0.12)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)';" oninput="filterGlossaryCards()">
            <svg style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #78909c;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>

        <!-- Category Filter Pills Bar -->
        <div style="margin-bottom: 1.25rem; overflow-x: auto; white-space: nowrap; padding-bottom: 0.25rem;">
            <div style="display: inline-flex; gap: 8px;">
                <?php
                $catList = [
                    'ALL' => 'All Categories',
                    'civil_services' => 'Civil Services',
                    'defence' => 'Defence',
                    'banking' => 'Banking',
                    'railway' => 'Railways',
                    'police' => 'Police & Security',
                    'teaching' => 'Teaching & Academic',
                    'engineering' => 'Engineering & PSUs',
                    'medical' => 'Medical & Health',
                    'entrance' => 'Entrance & Law'
                ];
                foreach ($catList as $catKey => $catLabel):
                    $isCatActive = ($catKey === 'ALL' && empty($reqCategory)) || ($reqCategory === $catKey);
                    $catParams = [];
                    if (!empty($reqLetter)) $catParams['letter'] = $reqLetter;
                    if ($catKey !== 'ALL') $catParams['category'] = $catKey;
                    $catUrl = url('full-forms/' . (!empty($catParams) ? '?' . http_build_query($catParams) : ''));
                ?>
                    <a href="<?= $catUrl ?>" class="cat-btn <?= $isCatActive ? 'active' : '' ?>" style="padding: 6px 14px; border-radius: 9999px; font-size: 0.8rem; font-weight: <?= $isCatActive ? '700' : '600' ?>; border: 1px solid <?= $isCatActive ? '#1a237e' : '#e0e0e0' ?>; background: <?= $isCatActive ? '#1a237e' : '#ffffff' ?>; color: <?= $isCatActive ? '#ffffff' : '#37474f' ?>; text-decoration: none; display: inline-block; transition: all 0.15s ease;">
                        <?= e($catLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Crawlable Alphabet Jump Bar (A to Z) with Exact Mockup Rounded Navy Buttons -->
        <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 2rem; overflow-x: auto; white-space: nowrap; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <?php
                $allAlphaParams = [];
                if (!empty($reqCategory)) $allAlphaParams['category'] = $reqCategory;
                $allAlphaUrl = url('full-forms/' . (!empty($allAlphaParams) ? '?' . http_build_query($allAlphaParams) : ''));
                $isAllActive = empty($reqLetter);
                ?>
                <a href="<?= $allAlphaUrl ?>" class="az-pill-btn <?= $isAllActive ? 'active' : '' ?>" style="display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 16px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; text-decoration: none; transition: all 0.15s ease; background: <?= $isAllActive ? '#f57c00' : '#1a237e' ?>; color: #ffffff;" onmouseover="if(!this.classList.contains('active')) this.style.background='#f57c00';" onmouseout="if(!this.classList.contains('active')) this.style.background='#1a237e';">
                    ALL (<?= $totalCount ?>)
                </a>
                <?php for ($i = 65; $i <= 90; $i++): 
                    $char = chr($i);
                    $hasTerms = !empty($alphabetCounts[$char]);
                    $isSel = ($reqLetter === $char);
                    $charParams = ['letter' => $char];
                    if (!empty($reqCategory)) $charParams['category'] = $reqCategory;
                    $charUrl = url('full-forms/?' . http_build_query($charParams));
                ?>
                    <?php if ($hasTerms): ?>
                        <a href="<?= $charUrl ?>" class="az-pill-btn <?= $isSel ? 'active' : '' ?>" data-letter="<?= $char ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; font-weight: 800; font-size: 0.95rem; text-decoration: none; transition: all 0.15s ease; background: <?= $isSel ? '#f57c00' : '#1a237e' ?>; color: #ffffff;" onmouseover="if(!this.classList.contains('active')) this.style.background='#f57c00';" onmouseout="if(!this.classList.contains('active')) this.style.background='#1a237e';">
                            <?= $char ?>
                        </a>
                    <?php else: ?>
                        <span class="az-pill-btn disabled" data-letter="<?= $char ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; background: #f1f5f9; color: #94a3b8; cursor: default;">
                            <?= $char ?>
                        </span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Cards Grid -->
        <div id="glossaryGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
            <?php foreach ($allTerms as $item): ?>
                <div class="glossary-card" data-letter="<?= e($item['letter']) ?>" data-category="<?= e($item['category']) ?>" data-search="<?= strtolower(e($item['acronym'] . ' ' . $item['full_form_en'] . ' ' . ($item['full_form_hi'] ?? '') . ' ' . ($item['conducting_body'] ?? ''))) ?>" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-top: 3px solid var(--color-primary); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-xs); transition: all 0.2s ease-in-out;" onmouseover="this.style.borderTopColor='var(--color-accent)'; this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-card-hover)';" onmouseout="this.style.borderTopColor='var(--color-primary)'; this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-xs)';">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <span style="font-size: 1.35rem; font-weight: 800; color: var(--color-primary); letter-spacing: -0.01em;">
                                <?= e($item['acronym']) ?>
                            </span>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #ffffff; background: var(--color-primary); padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                <?= e(ucfirst(str_replace('_', ' ', $item['category']))) ?>
                            </span>
                        </div>

                        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--text-heading); margin: 0 0 0.35rem 0; line-height: 1.35;">
                            <?= e($item['full_form_en']) ?>
                        </h2>

                        <?php if (!empty($item['full_form_hi'])): ?>
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-body); margin-bottom: 0.75rem; font-family: 'Noto Sans Devanagari', sans-serif;">
                                हिंदी: <?= e($item['full_form_hi']) ?>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 1.25rem 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e($item['overview']) ?>
                        </p>
                    </div>

                    <div>
                        <?php if (!empty($item['conducting_body'])): ?>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.85rem; padding-top: 0.5rem; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 4px;">
                                <span>Authority:</span>
                                <strong style="color: var(--text-main); font-weight: 600;"><?= e($item['conducting_body']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <a href="<?= url('full-forms/' . $item['slug'] . '/') ?>" style="display: inline-flex; width: 100%; align-items: center; justify-content: space-between; padding: 0.65rem 1rem; border-radius: 6px; background: #1a237e; color: #ffffff; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.2s ease;" onmouseover="this.style.background='#f57c00';" onmouseout="this.style.background='#1a237e';">
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

        <!-- Server-Side Crawlable Pagination Bar -->
        <?php if ($totalPages > 1): 
            $paginationBaseParams = [];
            if (!empty($reqLetter)) $paginationBaseParams['letter'] = $reqLetter;
            if (!empty($reqCategory)) $paginationBaseParams['category'] = $reqCategory;
            if (!empty($reqSearch)) $paginationBaseParams['q'] = $reqSearch;

            $buildPageUrl = function(int $pageNum) use ($paginationBaseParams): string {
                $params = $paginationBaseParams;
                if ($pageNum > 1) {
                    $params['page'] = $pageNum;
                }
                $qs = !empty($params) ? '?' . http_build_query($params) : '';
                return url('full-forms/' . $qs);
            };
        ?>
            <nav class="pagination-wrapper" aria-label="Full Forms Directory Pagination" style="margin-top: 3rem; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $buildPageUrl($currentPage - 1) ?>" class="page-btn" style="padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-heading); font-weight: 600; text-decoration: none; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 4px;">
                        &larr; Prev
                    </a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                if ($startPage > 1): ?>
                    <a href="<?= $buildPageUrl(1) ?>" class="page-btn" style="padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-heading); font-weight: 600; text-decoration: none; font-size: 0.875rem;">1</a>
                    <?php if ($startPage > 2): ?>
                        <span style="color: var(--text-light); padding: 0 4px;">&hellip;</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): 
                    $isActive = ($i === $currentPage);
                ?>
                    <a href="<?= $buildPageUrl($i) ?>" class="page-btn <?= $isActive ? 'active' : '' ?>" style="padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid <?= $isActive ? 'var(--color-primary)' : 'var(--border-color)' ?>; background: <?= $isActive ? 'var(--color-primary)' : 'var(--bg-surface)' ?>; color: <?= $isActive ? '#ffffff' : 'var(--text-heading)' ?>; font-weight: <?= $isActive ? '700' : '600' ?>; text-decoration: none; font-size: 0.875rem;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span style="color: var(--text-light); padding: 0 4px;">&hellip;</span>
                    <?php endif; ?>
                    <a href="<?= $buildPageUrl($totalPages) ?>" class="page-btn" style="padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-heading); font-weight: 600; text-decoration: none; font-size: 0.875rem;"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= $buildPageUrl($currentPage + 1) ?>" class="page-btn" style="padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-heading); font-weight: 600; text-decoration: none; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 4px;">
                        Next &rarr;
                    </a>
                <?php endif; ?>
            </nav>
            <div style="text-align: center; margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                Showing page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalFilteredCount ?> total acronyms)
            </div>
        <?php endif; ?>

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
