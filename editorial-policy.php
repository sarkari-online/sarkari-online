<?php
/**
 * Sarkari.online - Editorial & Fact-Checking Policy (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Editorial & Fact-Checking Policy';
$pageDesc = 'Our strict editorial guidelines, fact-checking methodology, source hierarchy, and correction standards for Indian educational publishing.';
$canonicalUrl = url('editorial-policy/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Editorial Policy', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">Editorial &amp; Fact-Checking Policy</h1>
                <p class="static-page-subtitle">Setting the gold standard for accuracy, integrity, and verified sourcing in Indian educational publishing.</p>
            </header>

            <div class="static-page-content">
                <h2>1. Core Editorial Principles</h2>
                <p>
                    Educational reporting directly influences the career and academic decisions of millions of young Indians. Therefore, <strong><?= e(SITE_NAME) ?></strong> enforces uncompromising standards of factual accuracy, neutral tone, and transparent accountability.
                </p>

                <h2>2. Hierarchy of Primary Sources</h2>
                <p>
                    Our editorial team and automated intelligence pipelines only accept source documents from authorized tiers:
                </p>
                <ul>
                    <li><strong>Tier 1 (Mandatory for Exam &amp; Results Data):</strong> Official statutory portals ending in <code>.gov.in</code>, <code>.nic.in</code>, <code>.ac.in</code>, official gazette notifications, or Supreme Court/High Court educational verdicts.</li>
                    <li><strong>Tier 2 (Admissions &amp; Universities):</strong> Verified press releases from NIRF-ranked institutions, AICTE, UGC, or State Education Secretariats.</li>
                    <li><strong>Tier 3 (Industry &amp; Career Insights):</strong> Recognized industry hiring surveys (e.g. NASSCOM, India Skills Report) or primary interviews with domain experts.</li>
                </ul>

                <div class="info-callout">
                    <div>
                        <strong>Strict Prohibition on Unverified Rumors:</strong> Social media leaks, unofficial answer keys from coaching centers without qualification, or speculative "expected date" clickbait are strictly barred from publication.
                    </div>
                </div>

                <h2>3. Multi-Layer Fact Verification Workflow</h2>
                <ol>
                    <li><strong>Origin Validation:</strong> Cross-checking that any notification number, file reference, or PDF signature aligns with the issuing body’s official portal.</li>
                    <li><strong>Data Integrity:</strong> Comparing cutoff percentiles, category quotas (UR, EWS, OBC-NCL, SC, ST, PwD), and eligibility criteria across historical and current cycles.</li>
                    <li><strong>Clarity &amp; Accessibility:</strong> Translating bureaucratic notices into step-by-step guidance without altering legal or procedural conditions.</li>
                </ol>

                <h2>4. Corrections &amp; Updates Standard</h2>
                <p>
                    When an official body amends an exam schedule, alters answer key challenges, or issues a revised merit list, we update the original report immediately. We clearly append an <em>"Updated [Date &amp; Time]"</em> indicator and detail the nature of the update.
                </p>
                <p>
                    To report an error, readers can email <a href="mailto:official.sarkarionline@gmail.com">official.sarkarionline@gmail.com</a> with supporting official links.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
