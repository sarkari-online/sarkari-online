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

// Google reCAPTCHA v3 Keys
$recaptchaSiteKey   = \App\Helpers\Env::get('RECAPTCHA_SITE_KEY', '');
$recaptchaSecretKey = \App\Helpers\Env::get('RECAPTCHA_SECRET_KEY', '');
$recaptchaMinScore  = (float)\App\Helpers\Env::get('RECAPTCHA_MIN_SCORE', '0.5');

// Time-based form token (changes every 10 minutes)
$formToken = hash('sha256', date('Y-m-d H:i', time() - (time() % 600)) . 'sarkari_contact_secret_2026');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    // LAYER 1: Honeypot — invisible field bots fill, humans don't
    if (!empty($_POST['website'] ?? '')) {
        $messageSent = true; // Silently discard
        goto render_page;
    }

    // LAYER 2: Time-based CSRF Token check
    $submittedToken = trim($_POST['_token'] ?? '');
    $validToken     = hash('sha256', date('Y-m-d H:i', time() - (time() % 600)) . 'sarkari_contact_secret_2026');
    $prevToken      = hash('sha256', date('Y-m-d H:i', (time() - 600) - ((time() - 600) % 600)) . 'sarkari_contact_secret_2026');
    if ($submittedToken !== $validToken && $submittedToken !== $prevToken) {
        $errorMessage = 'Invalid session. Please refresh the page and try again.';
        goto render_page;
    }

    // LAYER 3: Google reCAPTCHA v3 Server-Side Verification via cURL
    if (!empty($recaptchaSecretKey)) {
        $recaptchaToken = trim($_POST['g-recaptcha-response'] ?? '');
        if (!empty($recaptchaToken)) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'secret'   => $recaptchaSecretKey,
                    'response' => $recaptchaToken,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]),
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $verifyResult = curl_exec($ch);
            curl_close($ch);

            $verifyJson   = $verifyResult ? json_decode($verifyResult, true) : [];
            $captchaOk    = ($verifyJson['success'] ?? false) === true;
            $captchaScore = (float)($verifyJson['score'] ?? 0);

            // Reject only if Google explicitly marks it as a failed bot (score < minScore)
            if ($verifyResult && (!$captchaOk || $captchaScore < $recaptchaMinScore)) {
                $errorMessage = 'Security verification flagged this submission as automated. Please try again.';
                goto render_page;
            }
        }
    }

    // LAYER 4: Rate Limiting — max 5 per IP per hour
    $clientIp = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $cacheDir = dirname(__FILE__) . '/storage/cache';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
    $rateLimitFile = $cacheDir . '/contact_rate_' . md5($clientIp) . '.json';
    $rateData = file_exists($rateLimitFile) ? (json_decode(@file_get_contents($rateLimitFile), true) ?: []) : [];
    $rateData = array_values(array_filter($rateData, fn($ts) => $ts > (time() - 3600)));
    if (count($rateData) >= 5) {
        $errorMessage = 'Too many submissions from this IP. Please try again after an hour.';
        goto render_page;
    }
    $rateData[] = time();
    @file_put_contents($rateLimitFile, json_encode($rateData));

    // LAYER 5: Content Spam Validation
    $senderName    = trim($_POST['name'] ?? '');
    $senderEmail   = trim($_POST['email'] ?? '');
    $inquiryType   = trim($_POST['subject'] ?? 'General Query');
    $articleUrl    = trim($_POST['article_url'] ?? '');
    $senderMessage = trim($_POST['message'] ?? '');

    // Reject random alphanumeric bot names (no spaces, 8+ chars of gibberish)
    if (preg_match('/^[a-zA-Z0-9]{8,}$/', $senderName) && !str_contains($senderName, ' ')) {
        $messageSent = true; // Silently discard
        goto render_page;
    }
    // Reject gibberish messages (no spaces = random payload)
    if (!str_contains($senderMessage, ' ') && strlen($senderMessage) > 5) {
        $messageSent = true; // Silently discard
        goto render_page;
    }
    // Reject spam link injection (more than 3 URLs)
    if (substr_count($senderMessage, 'http') > 3) {
        $errorMessage = 'Your message contains too many external links. Please simplify.';
        goto render_page;
    }

    if (!empty($senderName) && !empty($senderEmail) && !empty($senderMessage) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        // 1. Save to Database for Admin Inquiries & Leads Panel
        try {
            \App\Database\Database::insert('contact_messages', [
                'name' => $senderName,
                'email' => $senderEmail,
                'subject' => $inquiryType,
                'article_url' => $articleUrl ?: null,
                'message' => $senderMessage,
                'ip_address' => $clientIp,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::warning('Contact message DB insert error: ' . $e->getMessage());
        }

        // 2. Email Notification
        $to = \App\Services\SettingsService::get('EDITORIAL_CONTACT_EMAIL', 'official.sarkarionline@gmail.com');
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

render_page:
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

            <?php if (!empty($errorMessage)): ?>
                <div class="info-callout" style="background-color: #fef2f2; border-left-color: #ef4444; color: #b91c1c; margin-bottom: 2rem;">
                    <div><strong>Error:</strong> <?= e($errorMessage) ?></div>
                </div>
            <?php endif; ?>

            <div class="static-page-content">
                <form action="<?= url('contact/') ?>" method="POST">
                    <!-- CSRF Token (time-based, rotates every 10 min) -->
                    <input type="hidden" name="_token" value="<?= e($formToken) ?>">
                    <!-- Honeypot: hidden from humans, bots fill this — auto-rejected -->
                    <div style="position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                        <label for="hp_website">Leave this empty</label>
                        <input type="text" id="hp_website" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>
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

                    <!-- reCAPTCHA v3 hidden token field -->
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                    <button type="submit" id="contact-submit-btn" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">
                        Send Message <?= icon('chevron-right', 'icon-sm') ?>
                    </button>

                    <?php if (!empty($recaptchaSiteKey)): ?>
                    <p style="font-size:0.78rem; color: var(--text-muted); margin-top: 0.75rem;">
                        This site is protected by Google reCAPTCHA.
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" style="color: var(--color-primary);">Privacy Policy</a> &amp;
                        <a href="https://policies.google.com/terms" target="_blank" rel="noopener" style="color: var(--color-primary);">Terms of Service</a> apply.
                    </p>
                    <?php endif; ?>
                </form>

                <?php if (!empty($recaptchaSiteKey)): ?>
                <!-- Load reCAPTCHA v3 JS -->
                <script src="https://www.google.com/recaptcha/api.js?render=<?= e($recaptchaSiteKey) ?>"></script>
                <script>
                document.getElementById('contact-submit-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    var form = this.closest('form');
                    var btn  = this;
                    btn.disabled = true;
                    btn.textContent = 'Verifying...';
                    grecaptcha.ready(function() {
                        grecaptcha.execute('<?= e($recaptchaSiteKey) ?>', {action: 'contact_submit'}).then(function(token) {
                            document.getElementById('g-recaptcha-response').value = token;
                            form.submit();
                        });
                    });
                });
                </script>
                <?php endif; ?>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 1rem;">
                    <h3 style="margin-bottom: 0.25rem;">Official Editorial Bureau &amp; Grievance Office</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-top: 0.5rem;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                            <strong style="color: #0f172a; display: block; margin-bottom: 0.35rem;">Registered Bureau Office:</strong>
                            <p style="font-size: 0.875rem; color: #475569; line-height: 1.5;">
                                <?= e(SITE_NAME) ?> Media Bureau<br>
                                Barakhamba Road, Connaught Place<br>
                                New Delhi, Delhi 110001, India
                            </p>
                            <p style="font-size: 0.775rem; color: #64748b; margin-top: 0.5rem;">
                                Operating Hours: Mon &ndash; Fri, 9:30 AM &ndash; 6:30 PM IST
                            </p>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                            <strong style="color: #0f172a; display: block; margin-bottom: 0.35rem;">Direct Digital Desks:</strong>
                            <p style="font-size: 0.875rem; color: #475569; line-height: 1.6;">
                                <strong>Editorial Desk:</strong> <!--email_off--><a href="mailto:official.sarkarionline@gmail.com" style="color: var(--color-primary);">official.sarkarionline@gmail.com</a><!--/email_off--><br>
                                <strong>Grievance Officer:</strong> <!--email_off--><a href="mailto:official.sarkarionline@gmail.com" style="color: var(--color-primary);">official.sarkarionline@gmail.com</a><!--/email_off--><br>
                                <strong>Response Commitment:</strong> Formally acknowledged within 24 hours.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </article>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
