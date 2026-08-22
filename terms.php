<?php
/**
 * Sarkari.online - Terms of Service Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Terms of Service';
$pageDesc = 'Sarkari.online Terms of Service governing platform usage, intellectual property, user conduct, and jurisdictional law.';
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
                <h1 class="static-page-title">Terms of Service</h1>
                <p class="static-page-subtitle">Last updated: August 18, 2026 &bull; Read before accessing or utilizing our digital services.</p>
            </header>

            <div class="static-page-content">
                <h2>1. Acceptance of Terms</h2>
                <p>
                    By accessing and browsing <strong><?= e(SITE_NAME) ?></strong> (<code><?= e(SITE_URL) ?></code>), you agree to comply with and be bound by these Terms of Service, all applicable Indian laws, and regulations. If you disagree with any portion of these terms, you are prohibited from using this website.
                </p>

                <h2>2. Permitted Use &amp; Intellectual Property</h2>
                <p>
                    All editorial guides, original analysis, structured datasets, graphics, and layout on this site are protected by applicable copyright and trademark law. You may:
                </p>
                <ul>
                    <li>Read, bookmark, and share links to our articles for personal, non-commercial education purposes.</li>
                    <li>Quote brief excerpts (up to 75 words) with explicit attribution and a direct hyperlink back to the original article on <?= e(SITE_NAME) ?>.</li>
                </ul>
                <p>
                    <strong>Prohibited actions:</strong> Scraping, mass automated reproduction, re-publishing entire articles, or framing our content on third-party domains without written prior consent.
                </p>

                <h2>3. User Conduct</h2>
                <p>
                    When using interactive sections, newsletters, or contacting our desk, users agree not to transmit any unlawful, threatening, defamatory, or abusive material, or disrupt platform infrastructure.
                </p>

                <h2>4. Governing Law &amp; Jurisdiction</h2>
                <p>
                    These Terms shall be governed and construed in accordance with the laws of the Republic of India. Any disputes arising in connection with these terms shall be subject to the exclusive jurisdiction of the competent courts in India.
                </p>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
