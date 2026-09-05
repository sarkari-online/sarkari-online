<?php
/**
 * Sarkari.online - About Us Page (E-E-A-T AdSense Compliant)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'About Us — Editorial Mission, Leadership & Fact-Checking Standards';
$pageDesc = 'Learn about Sarkari.online, our verified editorial leadership, research methodology, office bureau, and dedication to authentic Indian education intelligence.';
$canonicalUrl = url('about/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'About Us', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">About <?= e(SITE_NAME) ?></h1>
                <p class="static-page-subtitle">Democratizing access to verified Indian examination, admission, and statutory recruitment intelligence.</p>
            </header>

            <div class="static-page-content">
                <h2>Our Editorial Mission</h2>
                <p>
                    Every year, over 40 million students across India navigate critical entrance exams, college admissions, competitive recruitments, and scholarship applications. In an internet landscape often cluttered with clickbait, unverified rumors, and misleading exam date claims, <strong><?= e(SITE_NAME) ?></strong> was founded with a singular purpose: <em>to deliver rapid, 100% verified, and actionable educational information directly from primary official sources.</em>
                </p>

                <h2>What We Cover</h2>
                <ul>
                    <li><strong>National &amp; State Entrance Exams:</strong> Comprehensive coverage of JEE Main/Advanced, NEET UG/PG, CUET, GATE, CAT, CLAT, and state engineering/medical CETs.</li>
                    <li><strong>Board &amp; Competitive Results:</strong> Direct verified result portals, score breakdown analysis, revaluation procedures, and DigiLocker marksheet instructions.</li>
                    <li><strong>Government Recruitments:</strong> Verified job openings across UPSC, SSC, RRB, Banking (IBPS/SBI), Defence, and state public service commissions.</li>
                    <li><strong>Scholarships &amp; Financial Aid:</strong> Central sector schemes, state welfare grants, merit-cum-means opportunities, and international fellowships.</li>
                    <li><strong>Career Roadmaps &amp; Student Tools:</strong> Pragmatic roadmaps in emerging engineering, healthcare, commerce, and responsible calculators (Age, 7th Pay Salary, CGPA).</li>
                </ul>

                <h2>Editorial Leadership &amp; Research Bureau</h2>
                <p>Our reporting is driven by experienced education analysts and data journalists dedicated to factual precision:</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin: 1.5rem 0;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                        <h3 style="font-size: 1.05rem; margin-bottom: 0.25rem; color: #0f172a;">Editorial Directorate</h3>
                        <p style="font-size: 0.85rem; color: #2563eb; font-weight: 700; margin-bottom: 0.5rem;">Sarkari.online Central Newsroom</p>
                        <p style="font-size: 0.85rem; color: #475569; line-height: 1.5;">Supervises statutory gazette verification, entrance exam updates, and direct coordination with statutory board bulletins across India.</p>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                        <h3 style="font-size: 1.05rem; margin-bottom: 0.25rem; color: #0f172a;">Fact-Checking &amp; Data Verification Desk</h3>
                        <p style="font-size: 0.85rem; color: #2563eb; font-weight: 700; margin-bottom: 0.5rem;">Statutory Compliance Unit</p>
                        <p style="font-size: 0.85rem; color: #475569; line-height: 1.5;">Cross-references all cut-off marks, question paper keys, and application links against official statutory (.gov.in / .nic.in) portals before clearing for publication.</p>
                    </div>
                </div>

                <h2>Our 3-Pillar Verification Standard</h2>
                <div class="info-callout">
                    <div>
                        <p><strong>1. Primary Source Attribution:</strong> We never publish breaking claims without citing official gazettes, press releases from bodies like NTA, CBSE, UPSC, or UGC, or direct links to authority domains (.gov.in / .nic.in / .ac.in).</p>
                        <p><strong>2. Editorial Fact-Checking:</strong> Every numerical claim, date, or cutoff table undergoes internal data cross-verification before publication.</p>
                        <p><strong>3. Transparent Corrections:</strong> When official authorities update shift timings or syllabus amendments, our reports are updated with visible revision timestamps.</p>
                    </div>
                </div>

                <h2>Physical Bureau &amp; Operations Office</h2>
                <div class="info-callout" style="background-color: #f8fafc; border-left-color: #1e3a8a; color: #0f172a;">
                    <p><strong>Registered Bureau:</strong> Sarkari.online Media &amp; Educational Research Bureau</p>
                    <p><strong>Registered Address:</strong> Barakhamba Road, Connaught Place, New Delhi, Delhi 110001, India</p>
                    <p><strong>Editorial Email:</strong> <!--email_off--><a href="mailto:official.sarkarionline@gmail.com"><code>official.sarkarionline@gmail.com</code></a><!--/email_off--></p>
                    <p><strong>Operating Hours:</strong> Monday &ndash; Friday, 9:30 AM &ndash; 6:30 PM IST</p>
                </div>

                <h2>Mandatory Non-Affiliation Disclosure</h2>
                <p>
                    <?= e(SITE_NAME) ?> is an independent, privately run educational intelligence platform. We are <strong>strictly NOT affiliated with, endorsed by, or representing</strong> the Government of India, any State Government, Union Public Service Commission (UPSC), Staff Selection Commission (SSC), National Testing Agency (NTA), Central Board of Secondary Education (CBSE), or any statutory examination authority. All trademarks, official names, and logos remain the intellectual property of their respective statutory bodies and are referenced under fair nominative use.
                </p>

                <h2>Contact the Editorial Desk</h2>
                <p>
                    For news tips, factual corrections, institutional press releases, or grievance submissions, visit our <a href="<?= url('contact/') ?>">Contact &amp; Grievance Redressal Page</a> or write directly to <!--email_off--><a href="mailto:official.sarkarionline@gmail.com"><code>official.sarkarionline@gmail.com</code></a><!--/email_off-->.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
