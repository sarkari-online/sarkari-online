<?php
/**
 * Sarkari.online - Privacy Policy Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Privacy Policy';
$pageDesc = 'Sarkari.online Privacy Policy outlining data practices, cookies, analytics, and user privacy protections.';
$canonicalUrl = url('privacy-policy/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Privacy Policy', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">Privacy Policy</h1>
                <p class="static-page-subtitle">Last updated: August 18, 2026 &bull; Effective for all visitors and subscribers.</p>
            </header>

            <div class="static-page-content">
                <p>
                    Welcome to <strong><?= e(SITE_NAME) ?></strong> (hereinafter referred to as "we", "us", or "our"). We are committed to protecting the privacy of our visitors, readers, and subscribers. This Privacy Policy details how we collect, use, store, and disclose information when you visit our website at <code><?= e(SITE_URL) ?></code>.
                </p>

                <h2>1. Information We Collect</h2>
                <p>We collect information in the following ways:</p>
                <ul>
                    <li><strong>Directly Provided Information:</strong> When you subscribe to our newsletter or submit a message through our Contact form, you may provide your name, email address, and inquiry details.</li>
                    <li><strong>Log Files &amp; Technical Data:</strong> Like most standard websites, we automatically log Internet Protocol (IP) addresses, browser type, Internet Service Provider (ISP), referring/exit pages, platform type, date/time stamp, and number of clicks to analyze trends and administer the site.</li>
                    <li><strong>Cookies &amp; Web Beacons:</strong> We may use cookies to store user preferences and record session information to deliver a faster browsing experience.</li>
                </ul>

                <h2>2. How We Use Collected Information</h2>
                <p>The information collected is used exclusively for:</p>
                <ul>
                    <li>Providing timely educational updates and examination news.</li>
                    <li>Responding to user inquiries, grievance submissions, and factual correction notices.</li>
                    <li>Improving site performance, mobile responsiveness, and reader experience.</li>
                    <li>Detecting, preventing, and mitigating security incidents or technical abuse.</li>
                </ul>

                <h2>3. Third-Party Advertising &amp; Cookies</h2>
                <p>
                    Third-party vendors, including Google, may use cookies to serve ads based on a user's prior visits to our website or other websites. Google's use of advertising cookies enables it and its partners to serve ads to users based on their visit to our sites and/or other sites on the Internet.
                </p>
                <p>
                    Users may opt out of personalized advertising by visiting <a href="https://www.aboutads.info/choices/" target="_blank" rel="noopener noreferrer">AboutAds.info</a> or adjusting Google Ads Settings.
                </p>

                <h2>4. Data Sharing &amp; Disclosure</h2>
                <p>
                    We <strong>do not sell, trade, or rent</strong> personal identification information of our readers to third parties. We may disclose non-personally identifiable aggregated demographic information with analytical partners for research purposes.
                </p>

                <h2>5. Compliance with Indian Digital Personal Data Protection Act</h2>
                <p>
                    We uphold data protection standards in accordance with the Information Technology Act, 2000 and the Digital Personal Data Protection Act, 2023 (DPDPA). Users retain the right to request deletion of their subscribed email address by contacting our privacy desk.
                </p>

                <h2>6. Updates to This Policy</h2>
                <p>
                    We reserve the right to revise this Privacy Policy periodically. Any modifications will be posted directly on this page with an updated timestamp.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
