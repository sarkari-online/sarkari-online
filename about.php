<?php
/**
 * Sarkari.online - About Us Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'About Us — Mission & Editorial Values';
$pageDesc = 'Learn about Sarkari.online, our editorial standards, research methodology, and dedication to authentic Indian education and career intelligence.';
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
                <p class="static-page-subtitle">Democratizing access to verified Indian examination, admission, and career intelligence.</p>
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
                    <li><strong>Career Roadmaps &amp; Student Tech:</strong> Pragmatic roadmaps in emerging engineering, healthcare, commerce, and responsible AI productivity tools for academic success.</li>
                </ul>

                <h2>Our 3-Pillar Verification Standard</h2>
                <div class="info-callout">
                    <div>
                        <p><strong>1. Primary Source Attribution:</strong> We never publish breaking claims without citing official gazettes, press releases from bodies like NTA, CBSE, UPSC, or UGC, or direct links to authority domains (.gov.in / .nic.in / .ac.in).</p>
                        <p><strong>2. Editorial Fact-Checking:</strong> Every numerical claim, date, or cutoff table undergoes internal data cross-verification before publication.</p>
                        <p><strong>3. Transparent Corrections:</strong> When official authorities update shift timings or syllabus amendments, our reports are updated with visible revision timestamps.</p>
                    </div>
                </div>

                <h2>Non-Affiliation Disclosure</h2>
                <p>
                    <?= e(SITE_NAME) ?> is an independent, privately run educational intelligence platform. We are <strong>not affiliated with, endorsed by, or representing</strong> any government ministry, National Testing Agency (NTA), Central Board of Secondary Education (CBSE), Union Public Service Commission (UPSC), or any examination authority. All trademarks, official names, and logos remain the intellectual property of their respective statutory bodies.
                </p>

                <h2>Contact the Editorial Desk</h2>
                <p>
                    <?php $contactEmailVal = \App\Services\SettingsService::get('EDITORIAL_CONTACT_EMAIL', 'official.sarkarionline@gmail.com'); ?>
                    For news tips, factual corrections, or institutional press releases, visit our <a href="<?= url('contact/') ?>">Contact &amp; Grievance Page</a> or write to our editorial desk at <a href="mailto:<?= e($contactEmailVal) ?>"><code><?= e($contactEmailVal) ?></code></a>.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
