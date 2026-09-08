<?php
/**
 * Sarkari.online - Fact-Checking & Verification Methodology Policy
 * High-transparency public trust document adhering to Google Search Quality Rater Guidelines (E-E-A-T).
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Fact-Checking Policy & Verification Methodology';
$pageDesc = 'Learn how Sarkari.online verifies government notifications, statutory circulars, exam dates, and answer keys with a zero-tolerance policy for rumors.';
$canonicalUrl = url('fact-checking-policy/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Fact-Checking Policy', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding: 2.5rem 0 5rem 0; background: #f8fafc;">
    <div class="container" style="max-width: 920px; margin: 0 auto;">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <header class="static-page-header" style="margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem;">
                <span style="display: inline-block; background: #eff6ff; color: #1e40af; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.65rem; border-radius: 9999px; margin-bottom: 0.75rem;">
                    Institutional Integrity &amp; Transparency
                </span>
                <h1 class="static-page-title" style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0; line-height: 1.25;">
                    Fact-Checking Policy &amp; Verification Methodology
                </h1>
                <p class="static-page-subtitle" style="font-size: 1.05rem; color: #64748b; margin: 0; line-height: 1.6;">
                    The rigorous 4-tier verification protocol governing every public examination bulletin, vacancy circular, and recruitment schedule published on <?= e(SITE_NAME) ?>.
                </p>
                <p style="font-size: 0.8125rem; color: #94a3b8; margin: 0.75rem 0 0 0;">
                    Last Revised: September 2026 | Effective Sitewide
                </p>
            </header>

            <div class="static-page-content" style="color: #334155; font-size: 1rem; line-height: 1.75;">
                
                <h2 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 2rem 0 1rem 0;">1. Our Foundational Verification Mandate</h2>
                <p>
                    Competitive examinations in India—whether conducted by UPSC, SSC, State PSCs, NTA, or Banking Boards—directly impact the lives and livelihoods of tens of millions of aspirants. A single inaccurate reporting of an application deadline, age-cutoff formula, or exam timing can disqualify a hardworking candidate.
                </p>
                <p>
                    Because of these high stakes, <strong><?= e(SITE_NAME) ?></strong> operates under an uncompromising mandate: <em>No notification, answer key, or result date is published unless it is backed by primary documentary evidence from statutory authorities.</em>
                </p>

                <h2 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 2.5rem 0 1rem 0;">2. The 3-Tier Source Hierarchy (Zero Unverified Media Leaks)</h2>
                <p>
                    Our editorial desk strictly classifies news sources into three tiers. Content is drafted exclusively from Tier-1 statutory sources:
                </p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin: 1.25rem 0;">
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        <li style="margin-bottom: 0.75rem;">
                            <strong style="color: #0f172a;">Tier 1 — Statutory Government Authorities (Primary Only):</strong>
                            Official portals ending in <code>.gov.in</code>, <code>.nic.in</code>, and <code>.ac.in</code> (e.g., upsc.gov.in, ssc.gov.in, nta.ac.in, natboard.edu.in), official gazette notifications, Supreme Court orders, and digitally signed departmental circulars.
                        </li>
                        <li style="margin-bottom: 0.75rem;">
                            <strong style="color: #0f172a;">Tier 2 — Institutional Press Releases:</strong>
                            Formal press communiqués issued by Press Information Bureau (PIB), central ministries, or state public service commissions.
                        </li>
                        <li style="margin-bottom: 0;">
                            <strong style="color: #dc2626;">Barred — Speculative Third-Party Reports:</strong>
                            Unofficial WhatsApp forwards, speculative YouTube date predictions, coaching institute guesses, and media aggregators are strictly barred from serving as factual foundations.
                        </li>
                    </ul>
                </div>

                <h2 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 2.5rem 0 1rem 0;">3. Automated 8-Dimension Quality &amp; Safety Gate</h2>
                <p>
                    Before any article transitions to public visibility, it is subjected to an autonomous multi-layer fact-verification and safety gate:
                </p>
                <ol style="padding-left: 1.25rem; margin: 1rem 0;">
                    <li style="margin-bottom: 0.5rem;"><strong>Factual Grounding Score:</strong> Cross-matches dates, shift hours, fees, and quota categories against authoritative extracted tables. If factual accuracy is below 70%, the article is immediately rejected.</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Entity-Scoped Confidence Gate:</strong> Scans the Title and H1 to ensure milestones claimed as "Confirmed" or "Out Now" are factually validated in the body. If an event is pending or tentative, titles must reflect "Status &amp; Schedule" rather than false confirmation.</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Intent Outline Lock:</strong> Enforces clean structural boundaries. For instance, Admit Card, Answer Key, and Result articles are strictly barred from containing legacy application form boilerplate or redundant eligibility paragraphs.</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Hallucination Defense:</strong> Identifies obsolete boards or renamed agencies (e.g., verifying that MPESB is cited rather than defunct PEB) and blocks phantom exam schedules.</li>
                </ol>

                <h2 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 2.5rem 0 1rem 0;">4. Temporal Revalidation &amp; Post-Deadline Closing</h2>
                <p>
                    Statutory dates evolve rapidly—application deadlines get extended, admit cards get released, and objection windows close. Our automated lifecycle engine re-evaluates active articles every 30 minutes:
                </p>
                <ul>
                    <li>When an application deadline passes, the article state is transitioned to <code>CLOSED</code>, removing active "Apply" call-to-actions to prevent reader confusion.</li>
                    <li>Structured <code>JobPosting</code> data is automatically unlinked once recruitment registration closes.</li>
                    <li>When official updates arrive, original URLs are updated incrementally rather than proliferating thin duplicate pages.</li>
                </ul>

                <h2 id="corrections" style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 2.5rem 0 1rem 0;">5. Transparent Corrections &amp; Update Policy</h2>
                <p>
                    We hold ourselves accountable to our readers. If a statutory agency amends a circular, or if an inadvertent typographical error occurs in an article, we correct it promptly and transparently:
                </p>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem; margin: 1.25rem 0;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #166534; margin: 0 0 0.5rem 0;">How to Request an Editorial Correction:</h3>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9375rem; color: #15803d;">
                        If you spot a factual discrepancy or have an updated official circular to share, please contact our Editorial Desk:
                    </p>
                    <p style="margin: 0; font-size: 0.9375rem; font-weight: 600; color: #14532d;">
                        Email: <a href="mailto:official.sarkarionline@gmail.com" style="color: #166534; text-decoration: underline;">official.sarkarionline@gmail.com</a>
                    </p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.8125rem; color: #15803d;">
                        Please include the article URL and a link to the official gazette/portal notice. High-urgency corrections on active exam dates are reviewed with a target turnaround of under 2 hours.
                    </p>
                </div>

            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
