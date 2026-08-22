<?php
/**
 * Sarkari.online - AI Usage & Automation Policy (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Artificial Intelligence (AI) Usage & Transparency Policy';
$pageDesc = 'Sarkari.online commitment to ethical AI utilization, rigorous factual verification pipelines, and human editorial oversight.';
$canonicalUrl = url('ai-policy/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'AI Usage Policy', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">AI Usage &amp; Automation Policy</h1>
                <p class="static-page-subtitle">Ensuring trust, human oversight, and absolute factual accuracy in modern AI-assisted educational publishing.</p>
            </header>

            <div class="static-page-content">
                <h2>1. Purpose of This Policy</h2>
                <p>
                    At <strong><?= e(SITE_NAME) ?></strong>, we utilize advanced computational language technologies and artificial intelligence strictly as an editorial productivity tool—not as a substitute for factual integrity or editorial responsibility. This document transparently outlines how AI is utilized and the strict safeguards in place.
                </p>

                <h2>2. How AI Is Used on <?= e(SITE_NAME) ?></h2>
                <ul>
                    <li><strong>Trend &amp; Notification Aggregation:</strong> Scanning official portals and regulatory feeds to detect breaking exam releases rapidly.</li>
                    <li><strong>Formatting &amp; Schema Generation:</strong> Structuring complex official PDFs into readable bullet points, responsive tables, and search-friendly FAQs.</li>
                    <li><strong>Linguistic Refinement:</strong> Ensuring clear, jargon-free English so students and parents from diverse linguistic backgrounds across India can easily understand eligibility rules.</li>
                </ul>

                <h2>3. Strict Prohibitions &amp; What AI NEVER Does</h2>
                <div class="info-callout" style="background-color: var(--color-danger-light); border-left-color: var(--color-danger); color: #991b1b;">
                    <div>
                        <p><strong>Zero Unchecked Publishing:</strong> No article, result date, cutoff score, or vacancy figure is ever published based on AI output alone.</p>
                        <p><strong>No Fabricated Content:</strong> Any synthetic hallucinations, unverified speculation, or statistical guesswork are blocked by our multi-tier Quality Engine.</p>
                    </div>
                </div>

                <h2>4. The 8-Point Quality &amp; Verification Gate</h2>
                <p>
                    Before any piece of assisted content reaches our publication database, it must pass a rigorous evaluation measuring:
                </p>
                <ol>
                    <li><strong>Fact Accuracy (25% weight):</strong> Direct mathematical and date concordance with official gazettes.</li>
                    <li><strong>Original Value (20% weight):</strong> Actionable context beyond simple copy-paste notices.</li>
                    <li><strong>Search Intent &amp; Clarity (15% weight):</strong> Direct answers to what candidates need to do next.</li>
                    <li><strong>Completeness (10% weight):</strong> Inclusion of eligibility, fees, deadlines, and documentation.</li>
                    <li><strong>Source Quality (10% weight):</strong> Primary official <code>.gov.in</code> / <code>.nic.in</code> / <code>.ac.in</code> links.</li>
                    <li><strong>Readability (10% weight):</strong> Mobile-first formatting and clean heading hierarchy.</li>
                    <li><strong>SEO &amp; Structure (5% weight):</strong> Valid schema markup and semantic tags.</li>
                    <li><strong>Internal Linking (5% weight):</strong> Contextual cross-links to relevant syllabus and prep guides.</li>
                </ol>

                <h2>5. Human Editorial Accountability</h2>
                <p>
                    Our professional editorial team retains full and final responsibility for every word published under the <?= e(SITE_NAME) ?> brand. If any inadvertent discrepancy occurs, it is immediately corrected under our <a href="<?= url('editorial-policy/') ?>">Editorial Policy</a>.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
