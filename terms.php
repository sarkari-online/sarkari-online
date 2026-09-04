<?php
/**
 * Sarkari.online - Terms of Service Page (AdSense & Indian IT Act Compliant)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Terms of Service — User Agreement & Statutory Notice';
$pageDesc = 'Sarkari.online Terms of Service governing platform usage, intellectual property, fair reporting, third-party links, and Indian jurisdiction.';
$canonicalUrl = url('terms/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Terms of Service', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">Terms of Service &amp; User Agreement</h1>
                <p class="static-page-subtitle">Last updated: September 4, 2026 &bull; Read carefully before accessing or utilizing our digital news and educational services.</p>
            </header>

            <div class="static-page-content">
                <h2>1. Acceptance of Terms</h2>
                <p>
                    By accessing, browsing, or utilizing <strong><?= e(SITE_NAME) ?></strong> (accessible at <code><?= e(SITE_URL) ?></code>), you acknowledge that you have read, understood, and agree to be bound by these Terms of Service, all applicable laws and regulations of the Republic of India, and agree that you are responsible for compliance with any applicable local laws. If you do not agree with any of these terms, you are prohibited from using or accessing this site.
                </p>

                <h2>2. Nature of Service &amp; Statutory Non-Affiliation</h2>
                <p>
                    <?= e(SITE_NAME) ?> is an independent, privately operated digital news and educational information platform. <strong>We are NOT affiliated with, sponsored by, authorized by, or in any way officially connected to the Government of India, any State Government, Union Public Service Commission (UPSC), Staff Selection Commission (SSC), National Testing Agency (NTA), Central Board of Secondary Education (CBSE), or any statutory board.</strong>
                </p>
                <p>
                    All notifications, exam dates, cutoffs, syllabus breakdowns, and career updates provided on this portal are compiled from publicly accessible official gazettes, press releases, and statutory portals for candidate guidance. Aspirants must cross-verify all details on official government portals before acting upon them.
                </p>

                <h2>3. Permitted Use &amp; Intellectual Property Rights</h2>
                <p>
                    All content, design, structure, layout, calculators, and original analysis published on <?= e(SITE_NAME) ?> are the intellectual property of <?= e(SITE_NAME) ?> Media Bureau and protected under Indian Copyright and Trademark laws:
                </p>
                <ul>
                    <li>You are permitted to access, view, and print single copies of articles strictly for personal, educational, and non-commercial informational purposes.</li>
                    <li>Educational institutions and bloggers may cite brief factual excerpts (maximum 75 words) provided direct hyperlinked attribution is made to the original article on <?= e(SITE_NAME) ?>.</li>
                    <li>Mass automated scraping, framing, re-hosting entire articles, reverse-engineering calculations, or unauthorized commercial syndication is strictly prohibited without prior written license.</li>
                </ul>

                <h2>4. Third-Party Advertisements &amp; External Links</h2>
                <p>
                    Our platform displays third-party advertisements, primarily served through <strong>Google AdSense</strong>. We also provide outbound links to official statutory authority portals (.gov.in, .nic.in, .ac.in) for direct verification:
                </p>
                <ul>
                    <li><?= e(SITE_NAME) ?> does not control the content, privacy practices, or availability of third-party portals or advertisers.</li>
                    <li>Inclusion of an external link does not imply endorsement or warranty of the third-party services.</li>
                    <li>Interactions, transactions, or fee payments made on third-party portals are solely between you and the respective entity.</li>
                </ul>

                <h2>5. Disclaimer of Warranties &amp; Limitation of Liability</h2>
                <p>
                    The materials on <?= e(SITE_NAME) ?> are provided on an 'as is' and 'as available' basis. <?= e(SITE_NAME) ?> makes no warranties, expressed or implied, regarding accuracy, completeness, or reliability. Under no circumstances shall <?= e(SITE_NAME) ?>, its editors, or authors be liable for any direct, indirect, incidental, or consequential damages resulting from the use or inability to use the information, including but not limited to missed application deadlines, examination schedule revisions, or travel cancellations.
                </p>

                <h2>6. User Conduct &amp; Prohibited Activities</h2>
                <p>Users agree not to:</p>
                <ul>
                    <li>Attempt to gain unauthorized access to any portion of the platform or servers.</li>
                    <li>Inject malicious code, spam forms, or launch denial-of-service attacks.</li>
                    <li>Post defamatory, abusive, infringing, or unlawful communications to our contact desks.</li>
                </ul>

                <h2>7. Governing Law &amp; Dispute Resolution</h2>
                <p>
                    These Terms of Service shall be governed by and interpreted in accordance with the substantive laws of the Republic of India. Any disputes arising out of or related to these terms shall be subject to the exclusive jurisdiction of the competent courts located in New Delhi, India.
                </p>

                <h2>8. Changes to These Terms</h2>
                <p>
                    We reserve the right to revise or modify these Terms of Service at any time without prior notice. By continuing to use <?= e(SITE_NAME) ?> after any revisions are posted, you agree to be bound by the updated terms.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
