<?php
/**
 * Sarkari.online — Previous Year Question Papers & Official Answer Key Hub
 * Honest, authoritative resource directory linking to official government portals only.
 */

require_once __DIR__ . '/config.php';
use App\Helpers\Sanitizer;

$examSlug = Sanitizer::string($_GET['exam'] ?? '');

// Comprehensive exam directory with accurate, research-verified data
$examDirectory = [
    'upsc-cse' => [
        'slug'            => 'upsc-cse',
        'name'            => 'UPSC Civil Services Examination (CSE)',
        'short_name'      => 'UPSC CSE / IAS',
        'body'            => 'Union Public Service Commission (UPSC)',
        'body_short'      => 'UPSC',
        'color'           => '#1e3a8a',
        'bg'              => '#eff6ff',
        'portal'          => 'https://upsc.gov.in/examinations/previous-question-papers',
        'portal_label'    => 'upsc.gov.in',
        'papers_available'=> true,
        'access_type'     => 'free_public',
        'access_note'     => 'Official free PDFs — No login required',
        'stages'          => ['Prelims (GS Paper I & II / CSAT)', 'Mains (9 Papers — GS I, II, III, IV, Essay, Optional ×2)', 'Personality Test (Interview)'],
        'questions'       => ['Prelims GS I: 100 MCQ', 'Prelims CSAT: 80 MCQ', 'Mains: Descriptive (250 marks each paper)'],
        'marking'         => ['Prelims: +2 for correct, −0.66 for wrong', 'Mains: No negative marking', 'Interview: 275 marks'],
        'duration'        => ['Prelims: 2 hours each paper', 'Mains: 3 hours each paper'],
        'medium'          => 'English & Hindi',
        'years_available' => '2013 – 2024',
        'total_papers'    => '50+ papers',
        'description'     => 'UPSC is the ONLY major exam body in India that maintains a genuine, permanent, free public archive of all previous year question papers. No login required. Papers go back 10+ years.',
        'related_article' => 'upsc-cse-2026',
        'icon'            => 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
    ],
    'ssc-cgl' => [
        'slug'            => 'ssc-cgl',
        'name'            => 'SSC Combined Graduate Level (CGL)',
        'short_name'      => 'SSC CGL',
        'body'            => 'Staff Selection Commission (SSC)',
        'body_short'      => 'SSC',
        'color'           => '#0f766e',
        'bg'              => '#f0fdfa',
        'portal'          => 'https://ssc.gov.in',
        'portal_label'    => 'ssc.gov.in',
        'papers_available'=> false,
        'access_type'     => 'candidate_login',
        'access_note'     => 'Via candidate login only during 7-day objection window',
        'stages'          => ['Tier I — Computer Based Test (CBT)', 'Tier II — CBT (Paper I & II)', 'Tier III — Descriptive (offline)', 'Tier IV — Skill/Document Verification'],
        'questions'       => ['Tier I: 100 MCQ (4 sections × 25)', 'Tier II Paper I: 150 MCQ', 'Tier II Paper II: 100 MCQ'],
        'marking'         => ['Tier I: +2 for correct, −0.5 for wrong', 'Tier II: +3 for correct, −1 for wrong', 'Tier III: No negative marking'],
        'duration'        => ['Tier I: 60 minutes', 'Tier II: 135 minutes (Paper I), 120 minutes (Paper II)'],
        'medium'          => 'English & Hindi',
        'years_available' => 'Memory-based compilations available on coaching sites (not official PDFs)',
        'total_papers'    => 'No official archive',
        'description'     => 'SSC does NOT publish official question paper PDFs publicly. Papers are accessible only via candidate login (Roll No + Password) during the objection challenge window (typically 7 days). Memory-based papers on coaching sites are candidate reconstructions — not official.',
        'related_article' => 'ssc-cgl-2026',
        'icon'            => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    ],
    'rrb-ntpc' => [
        'slug'            => 'rrb-ntpc',
        'name'            => 'RRB Non-Technical Popular Categories (NTPC)',
        'short_name'      => 'RRB NTPC',
        'body'            => 'Railway Recruitment Boards (RRB)',
        'body_short'      => 'RRB',
        'color'           => '#b45309',
        'bg'              => '#fffbeb',
        'portal'          => 'https://www.rrbcdg.gov.in',
        'portal_label'    => 'rrbcdg.gov.in',
        'papers_available'=> false,
        'access_type'     => 'cycle_limited',
        'access_note'     => 'Published by regional RRBs during active cycle only',
        'stages'          => ['CBT Stage 1 (Prelims)', 'CBT Stage 2 (Mains)', 'Skill Test / Typing Test', 'Document Verification'],
        'questions'       => ['Stage 1: 100 MCQ (Mathematics 30, GI&RA 30, General Awareness 40)', 'Stage 2: 120 MCQ (Mathematics 35, GI&RA 35, General Awareness 50)'],
        'marking'         => ['+1 for correct answer', '−1/3 for wrong answer in both stages'],
        'duration'        => ['Stage 1: 90 minutes', 'Stage 2: 90 minutes'],
        'medium'          => 'English, Hindi & 14 Regional Languages',
        'years_available' => 'Removed after each cycle. Check regional RRB sites during active exams.',
        'total_papers'    => 'No permanent official archive',
        'description'     => 'RRBs (21 regional boards) publish answer keys and question papers only during the active objection window. After the cycle ends, papers are removed from official sites permanently. Find your regional RRB at indianrailways.gov.in.',
        'related_article' => 'rrb-ntpc-2026',
        'icon'            => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    ],
    'ibps-po' => [
        'slug'            => 'ibps-po',
        'name'            => 'IBPS Probationary Officer (PO / MT)',
        'short_name'      => 'IBPS PO',
        'body'            => 'Institute of Banking Personnel Selection (IBPS)',
        'body_short'      => 'IBPS',
        'color'           => '#6d28d9',
        'bg'              => '#f5f3ff',
        'portal'          => 'https://ibps.in',
        'portal_label'    => 'ibps.in',
        'papers_available'=> false,
        'access_type'     => 'no_archive',
        'access_note'     => 'IBPS uses randomized CBT item banks — no fixed paper set exists',
        'stages'          => ['Prelims — CBT (Online)', 'Mains — CBT (Online)', 'Interview + Final Merit'],
        'questions'       => ['Prelims: 100 MCQ (English 30, Quant 35, Reasoning 35)', 'Mains: 155 MCQ + 1 Descriptive (Essay/Letter)'],
        'marking'         => ['Prelims: +1 correct, −0.25 wrong', 'Mains: +1 correct, −0.25 wrong', 'Sectional cutoffs apply'],
        'duration'        => ['Prelims: 60 minutes (sectional time limit)', 'Mains: 180 minutes (MCQ) + 30 minutes (Descriptive)'],
        'medium'          => 'English & Hindi',
        'years_available' => 'No official archive — coaching site papers are memory-based',
        'total_papers'    => 'No official archive',
        'description'     => 'IBPS uses a large randomized question bank for Computer-Based Testing. There is no single "set" paper for any shift — questions are drawn dynamically. IBPS does NOT publish question papers. Candidate-specific response sheets are accessible via login for a limited window.',
        'related_article' => 'ibps-po-2026',
        'icon'            => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    ],
    'nda-na' => [
        'slug'            => 'nda-na',
        'name'            => 'National Defence Academy & Naval Academy (NDA & NA)',
        'short_name'      => 'NDA & NA',
        'body'            => 'Union Public Service Commission (UPSC)',
        'body_short'      => 'UPSC',
        'color'           => '#b91c1c',
        'bg'              => '#fef2f2',
        'portal'          => 'https://upsc.gov.in/examinations/previous-question-papers',
        'portal_label'    => 'upsc.gov.in',
        'papers_available'=> true,
        'access_type'     => 'free_public',
        'access_note'     => 'Official free PDFs — No login required (conducted by UPSC)',
        'stages'          => ['Written Examination (Mathematics + General Ability Test)', 'SSB Interview (Intelligence & Personality Test)'],
        'questions'       => ['Mathematics: 120 MCQ (300 marks)', 'GAT: 150 MCQ (600 marks) — English, GK, Physics, Chemistry, Maths, History, Geography, Current Events'],
        'marking'         => ['Mathematics: +2.5 correct, −0.83 wrong', 'GAT: +4 correct, −1.33 wrong'],
        'duration'        => ['Mathematics: 2.5 hours', 'GAT: 2.5 hours'],
        'medium'          => 'English & Hindi',
        'years_available' => '2013 – 2024',
        'total_papers'    => '20+ papers',
        'description'     => 'NDA papers are conducted by UPSC and are part of the same official public archive. Both Mathematics and General Ability Test (GAT) papers are freely downloadable from the UPSC official website. No login required.',
        'related_article' => 'nda-2026',
        'icon'            => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    ],
    'neet-ug' => [
        'slug'            => 'neet-ug',
        'name'            => 'National Eligibility cum Entrance Test (NEET UG)',
        'short_name'      => 'NEET UG',
        'body'            => 'National Testing Agency (NTA)',
        'body_short'      => 'NTA',
        'color'           => '#15803d',
        'bg'              => '#f0fdf4',
        'portal'          => 'https://neet.nta.nic.in',
        'portal_label'    => 'neet.nta.nic.in',
        'papers_available'=> false,
        'access_type'     => 'cycle_limited',
        'access_note'     => 'Official papers accessible only during objection window via candidate login',
        'stages'          => ['Single-Stage Pen & Paper Examination (OMR-based)'],
        'questions'       => ['180 MCQ total: Physics 45, Chemistry 45, Biology (Botany 45 + Zoology 45)', 'Each subject has Section A (35 MCQ mandatory) + Section B (15 MCQ, attempt any 10)'],
        'marking'         => ['+4 for correct, −1 for wrong', 'Unattempted: 0 marks'],
        'duration'        => ['3 hours 20 minutes (200 minutes)'],
        'medium'          => '13 languages including English, Hindi, and regional languages',
        'years_available' => 'Memory-based compilations. NTA releases official papers only during objection window.',
        'total_papers'    => 'No permanent official archive',
        'description'     => 'NTA conducts NEET UG as a pen-and-paper OMR exam. Official question papers and answer keys are released on the NTA CDN during the answer key challenge window (usually 2-3 days). These CDN URLs expire after the window closes. No permanent public archive exists.',
        'related_article' => 'neet-ug-2026',
        'icon'            => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    ],
];

$currentExam = !empty($examSlug) && isset($examDirectory[$examSlug]) ? $examDirectory[$examSlug] : null;

if (!empty($examSlug) && !$currentExam) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// SEO
if ($currentExam) {
    $pageTitle  = "{$currentExam['name']} Previous Year Papers — Official Portal & Exam Pattern | Sarkari.online";
    $pageDesc   = "Find official {$currentExam['name']} previous year question papers, exam pattern, marking scheme, and direct access to {$currentExam['portal_label']}. Conducted by {$currentExam['body']}.";
    $pageKeywords = strtolower("{$currentExam['short_name']} previous year papers, {$currentExam['short_name']} question paper, {$currentExam['body_short']} official papers, {$currentExam['short_name']} answer key, {$currentExam['short_name']} exam pattern");
} else {
    $pageTitle  = 'Previous Year Question Papers & Official Answer Keys 2026 — Sarkari.online';
    $pageDesc   = 'Find genuine previous year question papers, exam patterns, marking schemes, and direct official portal links for UPSC CSE, SSC CGL, RRB NTPC, IBPS PO, NDA, and NEET. Verified from official government sources only.';
    $pageKeywords = 'previous year question papers, pyq download, upsc previous year papers, ssc cgl previous papers, rrb ntpc papers, ibps po papers, nda previous papers, neet previous papers, official answer key';
}

$canonicalUrl = url($currentExam ? "previous-year-papers/{$examSlug}/" : 'previous-year-papers/');
$ogType = 'website';

// JSON-LD
$schemaItems = [];
foreach ($examDirectory as $exam) {
    $schemaItems[] = [
        '@type' => 'ItemPage',
        'name' => $exam['name'] . ' Previous Year Question Papers',
        'description' => $exam['description'],
        'url' => url("previous-year-papers/{$exam['slug']}/"),
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
            'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
            'hasPart' => $schemaItems,
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => 'Which exam commissions officially publish free previous year question papers?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Only UPSC (for CSE/IAS and NDA/NA) maintains a genuine, permanent, freely downloadable public archive of previous year question papers at upsc.gov.in — no login required. SSC, RRB, IBPS, and NTA do not maintain permanent public archives.']],
                ['@type' => 'Question', 'name' => 'Where can I download UPSC previous year question papers for free?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Visit upsc.gov.in/examinations/previous-question-papers — this is the official UPSC archive with all previous year papers from 2013 onwards, completely free and without any login.']],
                ['@type' => 'Question', 'name' => 'How to access SSC CGL previous year papers?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'SSC publishes papers only during the objection challenge window (7 days). Log in at ssc.gov.in with your Roll Number and password. After the window closes, papers are removed. Memory-based papers on coaching sites are candidate reconstructions, not official government PDFs.']],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main pyq-main">
<div class="pyq-hero">
    <div class="container">
        <nav class="pyq-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url() ?>">Home</a>
            <span aria-hidden="true">›</span>
            <?php if ($currentExam): ?>
                <a href="<?= url('previous-year-papers/') ?>">Previous Year Papers</a>
                <span aria-hidden="true">›</span>
                <span><?= e($currentExam['short_name']) ?></span>
            <?php else: ?>
                <span>Previous Year Papers</span>
            <?php endif; ?>
        </nav>

        <?php if ($currentExam): ?>
            <div class="pyq-hero-exam">
                <span class="pyq-body-badge" style="background:<?= e($currentExam['bg']) ?>;color:<?= e($currentExam['color']) ?>;"><?= e($currentExam['body_short']) ?></span>
                <h1 class="pyq-hero-title"><?= e($currentExam['name']) ?></h1>
                <p class="pyq-hero-desc"><?= e($currentExam['description']) ?></p>
                <div class="pyq-hero-actions">
                    <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer" class="pyq-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Visit <?= e($currentExam['portal_label']) ?> — Official Archive
                    </a>
                    <a href="<?= url('previous-year-papers/') ?>" class="pyq-btn-ghost">
                        ← All Exams
                    </a>
                </div>
                <?php if ($currentExam['access_type'] === 'free_public'): ?>
                    <div class="pyq-access-badge pyq-access-free">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        Free public access — No login required
                    </div>
                <?php elseif ($currentExam['access_type'] === 'candidate_login'): ?>
                    <div class="pyq-access-badge pyq-access-login">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Accessible via candidate login during objection window only
                    </div>
                <?php else: ?>
                    <div class="pyq-access-badge pyq-access-limited">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= e($currentExam['access_note']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pyq-hero-main">
                <h1 class="pyq-hero-title">Previous Year Question Papers</h1>
                <p class="pyq-hero-desc">Genuine resource directory with official portal links, exam patterns, and marking schemes. We only link to government-verified sources — no misleading PDFs, no third-party redirects.</p>
                <div class="pyq-hero-stats">
                    <div class="pyq-stat">
                        <span class="pyq-stat-num">6</span>
                        <span class="pyq-stat-label">Exams Covered</span>
                    </div>
                    <div class="pyq-stat-divider"></div>
                    <div class="pyq-stat">
                        <span class="pyq-stat-num">100%</span>
                        <span class="pyq-stat-label">Official Sources</span>
                    </div>
                    <div class="pyq-stat-divider"></div>
                    <div class="pyq-stat">
                        <span class="pyq-stat-num">Free</span>
                        <span class="pyq-stat-label">No Registration</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container pyq-container">

<?php if (!$currentExam): ?>
    <!-- EXAM GRID -->
    <section class="pyq-section">
        <div class="pyq-section-header">
            <h2>Browse by Examination Commission</h2>
            <p>Click any exam to see the official portal link, exam pattern, and marking scheme.</p>
        </div>
        <div class="pyq-grid">
            <?php foreach ($examDirectory as $exam): ?>
            <a href="<?= url('previous-year-papers/' . $exam['slug'] . '/') ?>" class="pyq-card" style="--card-color:<?= e($exam['color']) ?>;--card-bg:<?= e($exam['bg']) ?>;">
                <div class="pyq-card-top">
                    <span class="pyq-card-body"><?= e($exam['body_short']) ?></span>
                    <?php if ($exam['access_type'] === 'free_public'): ?>
                        <span class="pyq-pill pyq-pill-green">Free Official PDFs</span>
                    <?php elseif ($exam['access_type'] === 'candidate_login'): ?>
                        <span class="pyq-pill pyq-pill-amber">Login Required</span>
                    <?php else: ?>
                        <span class="pyq-pill pyq-pill-gray">No Archive</span>
                    <?php endif; ?>
                </div>
                <h3 class="pyq-card-title"><?= e($exam['name']) ?></h3>
                <p class="pyq-card-desc"><?= e(mb_substr($exam['description'], 0, 110)) ?>…</p>
                <div class="pyq-card-footer">
                    <span class="pyq-card-portal">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                        <?= e($exam['portal_label']) ?>
                    </span>
                    <span class="pyq-card-arrow">View Details →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- UPSC CALLOUT — Only one with real free papers -->
    <section class="pyq-callout">
        <div class="pyq-callout-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="pyq-callout-content">
            <h3>Only UPSC provides genuinely free official PDFs</h3>
            <p>India's most important exam is also the most transparent. <strong>UPSC officially publishes all previous year question papers</strong> for CSE (IAS/IPS/IFS) and NDA — free to download, no login, permanent archive going back to 2013.</p>
        </div>
        <a href="<?= url('previous-year-papers/upsc-cse/') ?>" class="pyq-callout-btn">Access UPSC Papers →</a>
    </section>

    <!-- FAQ SECTION -->
    <section class="pyq-section">
        <div class="pyq-section-header">
            <h2>Frequently Asked Questions</h2>
        </div>
        <div class="pyq-faq">
            <details class="pyq-faq-item" open>
                <summary class="pyq-faq-q">Which exam commissions officially publish free previous year question papers?</summary>
                <div class="pyq-faq-a">
                    <p><strong>Only UPSC</strong> (for CSE/IAS and NDA/NA) maintains a genuine, permanent, freely downloadable public archive at <a href="https://upsc.gov.in/examinations/previous-question-papers" target="_blank" rel="noopener noreferrer">upsc.gov.in</a> — no login required.</p>
                    <p>SSC, RRB, IBPS, and NTA do <strong>not</strong> maintain permanent public archives. Their papers are either accessible only via candidate login during a short objection window (7 days), or removed after the recruitment cycle ends.</p>
                </div>
            </details>
            <details class="pyq-faq-item">
                <summary class="pyq-faq-q">How to access SSC CGL previous year papers officially?</summary>
                <div class="pyq-faq-a">
                    <p>SSC releases papers during the <strong>answer key challenge window</strong> (approximately 7 days after the exam). To access:</p>
                    <ol>
                        <li>Visit <a href="https://ssc.gov.in" target="_blank" rel="noopener noreferrer">ssc.gov.in</a></li>
                        <li>Log in with your Roll Number and Date of Birth / Password from your Admit Card</li>
                        <li>Download your shift-specific question paper and response sheet</li>
                    </ol>
                    <p>After the window closes, papers are permanently removed. <em>Memory-based compilations on coaching websites are candidate reconstructions — not official government PDFs.</em></p>
                </div>
            </details>
            <details class="pyq-faq-item">
                <summary class="pyq-faq-q">Why does IBPS not publish question papers?</summary>
                <div class="pyq-faq-a">
                    <p>IBPS uses Computer-Based Testing (CBT) with a <strong>randomized question bank</strong>. Each candidate sees a different set of questions drawn dynamically — there is no single "set" paper for any shift. Publishing papers would compromise the item bank. IBPS does not publish question papers; candidate-specific response sheets are login-gated and time-limited.</p>
                </div>
            </details>
            <details class="pyq-faq-item">
                <summary class="pyq-faq-q">Are NEET UG previous year papers available officially?</summary>
                <div class="pyq-faq-a">
                    <p>NTA releases NEET official answer keys and question papers on their CDN during the <strong>answer key challenge window</strong> (typically 2-3 days). These CDN links expire after the window closes. Visit <a href="https://neet.nta.nic.in" target="_blank" rel="noopener noreferrer">neet.nta.nic.in</a> during your exam cycle to access them via candidate login.</p>
                    <p>No permanent public archive exists on NTA's website.</p>
                </div>
            </details>
        </div>
    </section>

<?php else: ?>
    <!-- SINGLE EXAM PAGE -->
    <div class="pyq-exam-layout">

        <!-- EXAM PATTERN TABLE -->
        <div class="pyq-detail-card">
            <div class="pyq-detail-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Exam Pattern & Structure
            </div>
            <div class="pyq-detail-body">
                <div class="pyq-pattern-grid">
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Stages / Tiers</span>
                        <ul class="pyq-pattern-list">
                            <?php foreach ($currentExam['stages'] as $s): ?>
                                <li><?= e($s) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Questions & Marks</span>
                        <ul class="pyq-pattern-list">
                            <?php foreach ($currentExam['questions'] as $q): ?>
                                <li><?= e($q) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Marking Scheme</span>
                        <ul class="pyq-pattern-list">
                            <?php foreach ($currentExam['marking'] as $m): ?>
                                <li><?= e($m) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Duration</span>
                        <ul class="pyq-pattern-list">
                            <?php foreach ($currentExam['duration'] as $d): ?>
                                <li><?= e($d) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Language Medium</span>
                        <ul class="pyq-pattern-list"><li><?= e($currentExam['medium']) ?></li></ul>
                    </div>
                    <div class="pyq-pattern-item">
                        <span class="pyq-pattern-label">Papers Available</span>
                        <ul class="pyq-pattern-list">
                            <li><?= e($currentExam['years_available']) ?></li>
                            <li><?= e($currentExam['total_papers']) ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCESS GUIDE -->
        <div class="pyq-detail-card">
            <div class="pyq-detail-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                How to Access Official Papers
            </div>
            <div class="pyq-detail-body">
                <?php if ($currentExam['access_type'] === 'free_public'): ?>
                <div class="pyq-access-guide pyq-guide-free">
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">1</span>
                        <div><strong>Visit the Official UPSC Archive</strong><br>Go to <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer"><?= e($currentExam['portal_label']) ?></a> — no login or registration needed.</div>
                    </div>
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">2</span>
                        <div><strong>Select Exam & Year</strong><br>Scroll to find <?= e($currentExam['short_name']) ?> papers. Papers are listed by exam name and year.</div>
                    </div>
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">3</span>
                        <div><strong>Download Directly</strong><br>Click the PDF link to download. Files are hosted on <code>upsc.gov.in/sites/default/files/</code> — 100% official.</div>
                    </div>
                    <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer" class="pyq-btn-primary" style="margin-top:1rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Open UPSC Official Archive
                    </a>
                </div>
                <?php elseif ($currentExam['access_type'] === 'candidate_login'): ?>
                <div class="pyq-access-guide pyq-guide-login">
                    <div class="pyq-guide-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Papers available only during the 7-day objection challenge window — accessible via candidate login only.
                    </div>
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">1</span>
                        <div><strong>Wait for the Objection Window</strong><br>After the exam, <?= e($currentExam['body_short']) ?> announces a challenge window (typically 7 days). Watch for notification on <?= e($currentExam['portal_label']) ?>.</div>
                    </div>
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">2</span>
                        <div><strong>Log in With Your Credentials</strong><br>Use your Roll Number + Password/Date of Birth from your Admit Card at <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer"><?= e($currentExam['portal_label']) ?></a>.</div>
                    </div>
                    <div class="pyq-guide-step">
                        <span class="pyq-step-num">3</span>
                        <div><strong>Download Before Window Closes</strong><br>Save your question paper and response sheet immediately — they are permanently removed after the window ends.</div>
                    </div>
                    <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer" class="pyq-btn-primary" style="margin-top:1rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Visit <?= e($currentExam['portal_label']) ?>
                    </a>
                </div>
                <?php else: ?>
                <div class="pyq-access-guide pyq-guide-login">
                    <div class="pyq-guide-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= e($currentExam['access_note']) ?>
                    </div>
                    <p style="color:var(--text-body);font-size:0.9rem;line-height:1.6;margin:0;"><?= e($currentExam['description']) ?></p>
                    <a href="<?= e($currentExam['portal']) ?>" target="_blank" rel="noopener noreferrer" class="pyq-btn-primary" style="margin-top:1rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Visit Official Portal
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- OTHER EXAMS SIDEBAR -->
        <div class="pyq-detail-card">
            <div class="pyq-detail-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Other Examinations
            </div>
            <div class="pyq-detail-body" style="padding:0;">
                <?php foreach ($examDirectory as $exam): if ($exam['slug'] === $currentExam['slug']) continue; ?>
                <a href="<?= url('previous-year-papers/' . $exam['slug'] . '/') ?>" class="pyq-sidebar-exam">
                    <div>
                        <span class="pyq-sidebar-body" style="color:<?= e($exam['color']) ?>;"><?= e($exam['body_short']) ?></span>
                        <span class="pyq-sidebar-name"><?= e($exam['short_name']) ?></span>
                    </div>
                    <?php if ($exam['access_type'] === 'free_public'): ?>
                        <span class="pyq-pill pyq-pill-green" style="font-size:0.65rem;">Free PDFs</span>
                    <?php elseif ($exam['access_type'] === 'candidate_login'): ?>
                        <span class="pyq-pill pyq-pill-amber" style="font-size:0.65rem;">Login</span>
                    <?php else: ?>
                        <span class="pyq-pill pyq-pill-gray" style="font-size:0.65rem;">No Archive</span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
<?php endif; ?>

</div><!-- /.pyq-container -->
</main>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
