<?php
/**
 * Sarkari.online - Comprehensive Google AdSense & GDPR Compliant Privacy Policy
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Privacy Policy — Google AdSense & Data Protection Disclosures';
$pageDesc = 'Sarkari.online comprehensive Privacy Policy detailing data practices, Google AdSense, DoubleClick DART cookies, analytics, GDPR, and CCPA compliance.';
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
                <h1 class="static-page-title">Privacy Policy &amp; Advertising Disclosures</h1>
                <p class="static-page-subtitle">Last updated: September 4, 2026 &bull; Effective for all visitors, readers, and subscribers worldwide.</p>
            </header>

            <div class="static-page-content">
                <p>
                    At <strong><?= e(SITE_NAME) ?></strong> (accessible from <code><?= e(SITE_URL) ?></code>), one of our main priorities is the privacy of our visitors. This Privacy Policy document outlines the types of information that is collected and recorded by <?= e(SITE_NAME) ?> and how we utilize it in strict adherence to global privacy laws, including the <strong>Google AdSense Publisher Policies</strong>, the <strong>Digital Personal Data Protection Act, 2023 (India)</strong>, the <strong>General Data Protection Regulation (GDPR)</strong>, and the <strong>California Consumer Privacy Act (CCPA)</strong>.
                </p>

                <div class="info-callout" style="background-color: #f0f9ff; border-left-color: #0284c7; color: #0c4a6e;">
                    <div>
                        <strong>Key Summary:</strong> We respect your data. We do not sell your personal information. Third-party advertising partners, notably <strong>Google AdSense</strong>, use cookies to serve personalized advertisements based on your browsing interests. You have complete control to opt out of personalized ads at any time.
                    </div>
                </div>

                <h2>1. Information We Collect</h2>
                <p>We collect information in several ways depending on how you interact with our platform:</p>
                <ul>
                    <li><strong>Direct Interactions:</strong> When you contact our editorial desk, submit a grievance, or subscribe to exam alerts, you may provide voluntary personal details such as your name, email address, and inquiry content.</li>
                    <li><strong>Log Files &amp; Automated Telemetry:</strong> <?= e(SITE_NAME) ?> follows a standard procedure of using log files. These files log visitors when they visit websites. The information collected includes internet protocol (IP) addresses, browser type, Internet Service Provider (ISP), date and time stamps, referring/exit pages, and possibly the number of clicks. These are not linked to any personally identifiable information and are used solely for analyzing trends, administering the site, tracking user movement, and gathering broad demographic information.</li>
                </ul>

                <h2>2. Cookies and Web Beacons</h2>
                <p>
                    Like any other modern website, <?= e(SITE_NAME) ?> uses "cookies". These cookies are used to store information including visitors' preferences, and the pages on the website that the visitor accessed or visited. The information is used to optimize the users' experience by customizing our web page content based on visitors' browser type and/or other information.
                </p>

                <h2>3. Google AdSense &amp; DoubleClick DART Cookies</h2>
                <p>
                    Google is one of our primary third-party vendors on our site. Google uses cookies, known as <strong>DART cookies</strong>, to serve advertisements to our site visitors based upon their visit to <code><?= e(SITE_URL) ?></code> and other sites across the internet:
                </p>
                <ul>
                    <li>Google's use of advertising cookies enables it and its partners to serve ads to our users based on their visits to our platform and/or other websites on the Internet.</li>
                    <li>Users may opt out of personalized advertising by visiting the <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer">Google Ads Settings Portal</a>.</li>
                    <li>Alternatively, users can opt out of third-party vendor use of cookies for personalized advertising by visiting <a href="https://www.aboutads.info/choices/" target="_blank" rel="noopener noreferrer">AboutAds.info Choices</a> or the <a href="https://optout.networkadvertising.org/" target="_blank" rel="noopener noreferrer">Network Advertising Initiative Consumer Opt-Out</a>.</li>
                    <li>For more detailed information about how Google manages data in its ad products, please consult the official policy: <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener noreferrer">How Google uses data when you use our partners' sites or apps</a>.</li>
                </ul>

                <h2>4. Third-Party Advertising Partners</h2>
                <p>
                    Some of advertisers on our site may use cookies and web beacons. Our advertising partners include:
                </p>
                <ul>
                    <li><strong>Google AdSense</strong> &middot; <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google Privacy &amp; Terms</a></li>
                </ul>
                <p>
                    Third-party ad servers or ad networks use technologies like cookies, JavaScript, or Web Beacons that are used in their respective advertisements and links that appear on <?= e(SITE_NAME) ?>, which are sent directly to users' browser. They automatically receive your IP address when this occurs. These technologies are used to measure the effectiveness of their advertising campaigns and/or to personalize the advertising content that you see on websites that you visit.
                </p>
                <p>
                    <em>Note: <?= e(SITE_NAME) ?> has no access to or control over these cookies that are used by third-party advertisers.</em>
                </p>

                <h2>5. Google Analytics Disclosures</h2>
                <p>
                    We employ Google Analytics 4 (Measurement ID: <code>G-XW0PTK22ZW</code>) to analyze traffic volume and readership patterns. Google Analytics collects first-party cookies to report user interactions. No sensitive personal information is transmitted to Google Analytics. You can prevent Google Analytics from recognizing you on return visits by disabling cookies on your browser or installing the official <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer">Google Analytics Opt-out Browser Add-on</a>.
                </p>

                <h2>6. CCPA Privacy Rights (Do Not Sell My Personal Information)</h2>
                <p>Under the California Consumer Privacy Act (CCPA), California consumers have the right to:</p>
                <ul>
                    <li>Request that a business that collects a consumer's personal data disclose the categories and specific pieces of personal data that a business has collected about consumers.</li>
                    <li>Request that a business delete any personal data about the consumer that a business has collected.</li>
                    <li>Request that a business that sells a consumer's personal data, not sell the consumer's personal data. <strong><?= e(SITE_NAME) ?> does not sell personal data under any circumstances.</strong></li>
                </ul>
                <p>If you make a request, we have one month to respond to you. If you would like to exercise any of these rights, please contact us.</p>

                <h2>7. GDPR Data Protection Rights</h2>
                <p>We would like to make sure you are fully aware of all of your data protection rights under the General Data Protection Regulation (GDPR). Every user is entitled to the following:</p>
                <ul>
                    <li><strong>The right to access:</strong> You have the right to request copies of your personal data.</li>
                    <li><strong>The right to rectification:</strong> You have the right to request that we correct any information you believe is inaccurate.</li>
                    <li><strong>The right to erasure:</strong> You have the right to request that we erase your personal data, under certain conditions.</li>
                    <li><strong>The right to restrict processing:</strong> You have the right to request that we restrict the processing of your personal data, under certain conditions.</li>
                    <li><strong>The right to object to processing:</strong> You have the right to object to our processing of your personal data, under certain conditions.</li>
                    <li><strong>The right to data portability:</strong> You have the right to request that we transfer the data that we have collected to another organization, or directly to you, under certain conditions.</li>
                </ul>

                <h2>8. Compliance with Indian Digital Personal Data Protection Act, 2023</h2>
                <p>
                    <?= e(SITE_NAME) ?> strictly abides by the provisions of the Information Technology Act, 2000 and the Digital Personal Data Protection Act, 2023 (DPDPA). We process all data strictly on lawful grounds with full transparency, proportionality, and security.
                </p>

                <h2>9. Children's Information (COPPA)</h2>
                <p>
                    Another part of our priority is adding protection for children while using the internet. We encourage parents and guardians to observe, participate in, and/or monitor and guide their online activity.
                </p>
                <p>
                    <?= e(SITE_NAME) ?> does not knowingly collect any Personal Identifiable Information from children under the age of 13. If you think that your child provided this kind of information on our website, we strongly encourage you to contact us immediately and we will do our best efforts to promptly remove such information from our records.
                </p>

                <h2>10. Data Protection &amp; Grievance Officer</h2>
                <p>
                    In accordance with the Information Technology (Intermediary Guidelines and Digital Media Ethics Code) Rules, 2021, our designated Grievance and Data Protection Officer details are provided below:
                </p>
                <div class="info-callout" style="background-color: #f8fafc; border-left-color: #1e3a8a; color: #0f172a;">
                    <p><strong>Grievance &amp; Compliance Officer:</strong> Editorial Grievance Desk</p>
                    <p><strong>Organization:</strong> <?= e(SITE_NAME) ?> Media Bureau</p>
                    <p><strong>Registered Email:</strong> <!--email_off--><a href="mailto:official.sarkarionline@gmail.com"><code>official.sarkarionline@gmail.com</code></a><!--/email_off--></p>
                    <p><strong>Response Timeline:</strong> All privacy inquiries, data deletion requests, or grievances are formally acknowledged within 24 hours and redressed within 15 working days.</p>
                </div>

                <h2>11. Consent</h2>
                <p>
                    By using our website, you hereby consent to our Privacy Policy and agree to its terms.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
