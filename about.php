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

                <h2>Editorial Leadership &amp; Fact-Checking Bureau</h2>
                <p>Our reporting, gazette analysis, and data verification are directed by verified editorial leadership dedicated to factual precision and zero speculative reporting:</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin: 1.5rem 0;">
                    <!-- Founder & Managing Editor: Ajay Mathur -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: #1e3a8a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; overflow: hidden; border: 2px solid #e2e8f0; flex-shrink: 0;">
                                <img src="<?= asset('assets/images/ajay-mathur.jpg') ?>" alt="Ajay Mathur" width="48" height="48" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div>
                                <h3 style="font-size: 1.05rem; margin: 0; color: #0f172a;">
                                    <a href="<?= url('author/ajay-mathur/') ?>" style="color: inherit; text-decoration: none;">Ajay Mathur</a>
                                </h3>
                                <p style="font-size: 0.785rem; color: #1e3a8a; font-weight: 700; margin: 0;">Founder &amp; Lead Web Architect</p>
                            </div>
                        </div>
                        <p style="font-size: 0.8125rem; color: #475569; line-height: 1.5; margin-bottom: 0.75rem;">Web &amp; Cloud Infrastructure Engineer (Ex-Collegedunia, 2022–2025). 4+ years managing high-traffic educational web architecture, AWS cloud deployment, and statutory notification systems.</p>
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <a href="<?= url('author/ajay-mathur/') ?>" style="font-size: 0.785rem; font-weight: 700; color: #1e3a8a; text-decoration: none;">View Profile &amp; Articles &rarr;</a>
                            <span style="color: #cbd5e1;">&bull;</span>
                            <a href="https://www.linkedin.com/in/ajay-mathur-03a626254/" target="_blank" rel="noopener noreferrer" style="font-size: 0.785rem; font-weight: 700; color: #0a66c2; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.25V10.9H6.46M7.86 6.55a1.64 1.64 0 0 0-1.63 1.64c0 .9.73 1.63 1.63 1.63a1.64 1.64 0 0 0 1.64-1.63c0-.91-.74-1.64-1.64-1.64Z"/></svg>
                                Official LinkedIn
                            </a>
                        </div>
                    </div>

                    <!-- Editorial Verification Desk -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: #047857; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">
                                S
                            </div>
                            <div>
                                <h3 style="font-size: 1.05rem; margin: 0; color: #0f172a;">
                                    <a href="<?= url('author/editorial-desk/') ?>" style="color: inherit; text-decoration: none;">Editorial &amp; Verification Desk</a>
                                </h3>
                                <p style="font-size: 0.785rem; color: #047857; font-weight: 700; margin: 0;">Statutory Compliance Unit</p>
                            </div>
                        </div>
                        <p style="font-size: 0.8125rem; color: #475569; line-height: 1.5; margin-bottom: 0.75rem;">Collaborative research unit responsible for tracking real-time commission bulletins, answer key release windows, and official press releases.</p>
                        <a href="<?= url('author/editorial-desk/') ?>" style="font-size: 0.785rem; font-weight: 700; color: #047857; text-decoration: none;">View Desk Coverage &rarr;</a>
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

                <h2>Editorial &amp; Operations Desk</h2>
                <div class="info-callout" style="background-color: #f8fafc; border-left-color: #1e3a8a; color: #0f172a;">
                    <p><strong>Editorial Bureau:</strong> Sarkari.online Independent Educational News Network</p>
                    <p><strong>Location &amp; Jurisdiction:</strong> New Delhi, India</p>
                    <p><strong>Editorial Inquiries:</strong> <!--email_off--><a href="mailto:official.sarkarionline@gmail.com"><code>official.sarkarionline@gmail.com</code></a><!--/email_off--></p>
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
