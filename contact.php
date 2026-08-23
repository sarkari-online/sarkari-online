<?php
/**
 * Sarkari.online - Contact & Grievance Page (Phase 0)
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Contact & Grievance Redressal';
$pageDesc = 'Get in touch with the Sarkari.online editorial team for inquiries, press releases, factual corrections, or grievance submissions.';
$canonicalUrl = url('contact/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Contact Us', 'url' => null]
];

$messageSent = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Phase 0 UI feedback
    $messageSent = true;
}

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <article class="static-page-card">
            <header class="static-page-header">
                <h1 class="static-page-title">Contact &amp; Grievance Redressal</h1>
                <p class="static-page-subtitle">Have a question, feedback, or noticed an error? Our editorial team responds within 24–48 hours.</p>
            </header>

            <?php if ($messageSent): ?>
                <div class="info-callout" style="background-color: var(--color-success-light); border-left-color: var(--color-success); color: var(--color-success); margin-bottom: 2rem;">
                    <div>
                        <strong>Message Received:</strong> Thank you for contacting <?= e(SITE_NAME) ?>. Our editorial desk has logged your communication and will review it shortly.
                    </div>
                </div>
            <?php endif; ?>

            <div class="static-page-content">
                <form action="<?= url('contact/') ?>" method="POST">
                    <div class="contact-form-grid">
                        <div class="form-group">
                            <label for="contact-name" class="form-label">Your Full Name *</label>
                            <input type="text" id="contact-name" name="name" class="form-input" placeholder="e.g. Ananya Sharma" required>
                        </div>
                        <div class="form-group">
                            <label for="contact-email" class="form-label">Email Address *</label>
                            <input type="email" id="contact-email" name="email" class="form-input" placeholder="e.g. ananya@example.com" required>
                        </div>
                    </div>

                    <div class="contact-form-grid">
                        <div class="form-group">
                            <label for="contact-subject" class="form-label">Subject / Inquiry Type *</label>
                            <select id="contact-subject" name="subject" class="form-select" required>
                                <option value="Editorial Correction">Report a Factual Correction</option>
                                <option value="General Query">General Inquiry</option>
                                <option value="Press Release">Submit Official Press Release</option>
                                <option value="Grievance">Grievance / Copyright Concern</option>
                                <option value="Partnership">Educational Partnership</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contact-url" class="form-label">Relevant Article URL (Optional)</label>
                            <input type="url" id="contact-url" name="article_url" class="form-input" placeholder="https://...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="contact-message" class="form-label">Your Message / Correction Details *</label>
                        <textarea id="contact-message" name="message" class="form-textarea" placeholder="Please describe your query or provide verified official source links for corrections..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                        Send Message <?= icon('chevron-right', 'icon-sm') ?>
                    </button>
                </form>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.5rem;">
                    <h4 style="margin-bottom: 0.25rem;">Official Editorial &amp; Grievance Communication</h4>
                    <p style="font-size: 0.935rem; color: var(--text-muted); line-height: 1.6;">
                        For news tips, factual corrections, official press releases, and statutory grievance redressal:<br>
                        <a href="mailto:official.sarkarionline@gmail.com" style="color: var(--color-primary); font-weight: 600; font-size: 1.05rem; text-decoration: none;">official.sarkarionline@gmail.com</a>
                    </p>
                </div>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
