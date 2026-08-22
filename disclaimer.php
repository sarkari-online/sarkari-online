<?php
/**
 * Sarkari.online - Legal Disclaimer Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Disclaimer & Official Non-Affiliation Notice';
$pageDesc = 'Important legal disclaimer and non-affiliation notice regarding exam dates, results, government notifications, and third-party content.';
$canonicalUrl = url('disclaimer/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Disclaimer', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">Disclaimer &amp; Non-Affiliation Notice</h1>
                <p class="static-page-subtitle">Please read this advisory carefully before relying on any exam dates, results, or recruitment details.</p>
            </header>

            <div class="static-page-content">
                <div class="info-callout" style="background-color: var(--color-warning-light); border-left-color: var(--color-warning); color: #78350f;">
                    <div>
                        <strong>Mandatory Candidate Advisory:</strong> Always cross-verify dates, cutoff marks, and admit card download links on the official statutory websites of respective exam authorities (e.g., NTA, CBSE, UPSC, SSC, or respective State/Central Ministries) before making travel or fee payments.
                    </div>
                </div>

                <h2>1. General Educational Information</h2>
                <p>
                    The information provided by <strong><?= e(SITE_NAME) ?></strong> on this website is for general informational and educational guidance purposes only. While our editorial team makes every reasonable effort to keep the information accurate, complete, and updated with reference to official gazettes and press bulletins, we make no representations or warranties of any kind, express or implied, regarding the completeness or accuracy of any information.
                </p>

                <h2>2. No Government or Authority Affiliation</h2>
                <p>
                    <strong><?= e(SITE_NAME) ?> is NOT associated, affiliated, endorsed, or connected in any capacity</strong> with the Government of India, any State Government, Union Public Service Commission (UPSC), Staff Selection Commission (SSC), National Testing Agency (NTA), Central Board of Secondary Education (CBSE), Medical Counselling Committee (MCC), or any other regulatory/statutory authority.
                </p>
                <p>
                    All registered names, trademarks, exam logos, and emblems referenced on this platform belong strictly to their respective official owners and are used purely for nominative descriptive reference.
                </p>

                <h2>3. External Links &amp; Third-Party Portals</h2>
                <p>
                    Our articles contain direct links to official government and university websites (e.g., <code>.gov.in</code>, <code>.nic.in</code>, <code>.ac.in</code>). We have no control over the availability, server uptime, or content changes of external portals. Inclusion of any link does not imply endorsement of external services.
                </p>

                <h2>4. Limitation of Liability</h2>
                <p>
                    Under no circumstances shall <?= e(SITE_NAME) ?> or its authors be liable for any direct, indirect, incidental, or consequential loss or damage arising out of the use of, or inability to use, the materials on this site, including but not limited to missed application deadlines or fee disputes.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
