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
$errorMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $senderName    = trim($_POST['name'] ?? '');
    $senderEmail   = trim($_POST['email'] ?? '');
    $inquiryType   = trim($_POST['subject'] ?? 'General Query');
    $articleUrl    = trim($_POST['article_url'] ?? '');
    $senderMessage = trim($_POST['message'] ?? '');

    if (!empty($senderName) && !empty($senderEmail) && !empty($senderMessage) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        // 1. Save to Database for Admin Inquiries & Leads Panel
        try {
            \App\Database\Database::insert('contact_messages', [
                'name' => $senderName,
                'email' => $senderEmail,
                'subject' => $inquiryType,
                'article_url' => $articleUrl ?: null,
                'message' => $senderMessage,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::warning('Contact message DB insert error: ' . $e->getMessage());
        }

        // 2. Email Notification
        $to = 'official.sarkarionline@gmail.com';
        $emailSubject = "[Sarkari.online Inquiry] {$inquiryType} from " . strip_tags($senderName);

        $emailBody = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>";
        $emailBody .= "<h2 style='color: #1e3a8a; border-bottom: 2px solid #f97316; padding-bottom: 8px; margin-top: 0;'>New Communication Received on Sarkari.online</h2>";
        $emailBody .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>";
        $emailBody .= "<tr><td style='padding: 8px; font-weight: bold; width: 140px; color: #64748b;'>Sender Name:</td><td style='padding: 8px;'>" . htmlspecialchars($senderName) . "</td></tr>";
        $emailBody .= "<tr style='background: #f8fafc;'><td style='padding: 8px; font-weight: bold; color: #64748b;'>Sender Email:</td><td style='padding: 8px;'><a href='mailto:" . htmlspecialchars($senderEmail) . "'>" . htmlspecialchars($senderEmail) . "</a></td></tr>";
        $emailBody .= "<tr><td style='padding: 8px; font-weight: bold; color: #64748b;'>Inquiry Type:</td><td style='padding: 8px; font-weight: 600; color: #f97316;'>" . htmlspecialchars($inquiryType) . "</td></tr>";
        if (!empty($articleUrl)) {
            $emailBody .= "<tr style='background: #f8fafc;'><td style='padding: 8px; font-weight: bold; color: #64748b;'>Article URL:</td><td style='padding: 8px;'><a href='" . htmlspecialchars($articleUrl) . "' target='_blank'>" . htmlspecialchars($articleUrl) . "</a></td></tr>";
        }
        $emailBody .= "<tr><td style='padding: 8px; font-weight: bold; color: #64748b;'>Received At:</td><td style='padding: 8px;'>" . date('d M Y, h:i A (T)') . "</td></tr>";
        $emailBody .= "<tr style='background: #f8fafc;'><td style='padding: 8px; font-weight: bold; color: #64748b;'>Sender IP:</td><td style='padding: 8px;'>" . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</td></tr>";
        $emailBody .= "</table>";
        $emailBody .= "<h3 style='color: #0f172a; margin-bottom: 8px;'>Message Content:</h3>";
        $emailBody .= "<div style='background: #f1f5f9; padding: 15px; border-radius: 6px; border-left: 4px solid #1e3a8a; white-space: pre-wrap; font-size: 14px;'>" . htmlspecialchars($senderMessage) . "</div>";
        $emailBody .= "<p style='margin-top: 25px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px;'>This automated alert was sent from the Sarkari.online Contact &amp; Grievance portal.</p>";
        $emailBody .= "</body></html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Sarkari.online Notification <no-reply@sarkari.online>\r\n";
        $headers .= "Reply-To: " . addslashes($senderName) . " <{$senderEmail}>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        @mail($to, $emailSubject, $emailBody, $headers);
        $messageSent = true;
    } else {
        $errorMessage = 'Please provide a valid name, email address, and message details.';
    }
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
