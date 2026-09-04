<?php
/**
 * Professional Authority-Grade Footer Component
 * Sarkari.online - Indian Education & Recruitment News Network
 * Designed with Logo-Matched Color Palette (Deep Navy, Saffron/Amber, Crisp White)
 * Strictly zero emojis, 100% SVG & institutional typography.
 */
?>
<footer class="site-footer">
    <!-- Main Footer Columns -->
    <div class="footer-main-section">
        <div class="container">
            <div class="footer-grid">
                
                <!-- Column 1: Brand & Purpose -->
                <div class="footer-col footer-col-brand">
                    <a href="<?= url() ?>" class="footer-brand-logo" aria-label="<?= e(SITE_NAME) ?>">
                        <img src="<?= asset('sarkari-logo-white.png') ?>" alt="<?= e(SITE_NAME) ?>" style="height: 38px; width: auto; max-width: 175px; object-fit: contain; display: block;">
                    </a>
                    <p class="footer-brand-desc">
                        An independent digital news observatory providing verified schedules, recruitment circulars, and scholarship gazettes for students and competitive exam aspirants across India.
                    </p>
                    <div class="footer-live-status-pill">
                        <span class="live-pulse-dot"></span>
                        <span class="live-status-text">Statutory Portal Feed: Active</span>
                    </div>
                </div>

                <!-- Column 2: National Examination Hub -->
                <div class="footer-col">
                    <h3 class="footer-heading">National Exams</h3>
                    <ul class="footer-links-list">
                        <li><a href="<?= url('category/career-guides/') ?>" class="footer-link">NTA Entrance Tests</a></li>
                        <li><a href="<?= url('category/entrance-exams/') ?>" class="footer-link">UPSC Civil Services</a></li>
                        <li><a href="<?= url('category/school-boards/') ?>" class="footer-link">CBSE &amp; State Boards</a></li>
                        <li><a href="<?= url('category/exam-results/') ?>" class="footer-link">Teacher Eligibility (CTET)</a></li>
                        <li><a href="<?= url('category/entrance-exams/') ?>" class="footer-link">Central University Admissions</a></li>
                        <li><a href="<?= url('category/answer-keys/') ?>" class="footer-link">Official Answer Keys</a></li>
                    </ul>
                </div>

                <!-- Column 3: Recruitment & Aid -->
                <div class="footer-col">
                    <h3 class="footer-heading">Recruitment &amp; Aid</h3>
                    <ul class="footer-links-list">
                        <li><a href="<?= url('state-jobs/') ?>" class="footer-link">State Govt Jobs 2026</a></li>
                        <li><a href="<?= url('category/government-jobs/') ?>" class="footer-link">Staff Selection (SSC)</a></li>
                        <li><a href="<?= url('category/government-jobs/') ?>" class="footer-link">Railway Recruitment (RRB)</a></li>
                        <li><a href="<?= url('tools/age-calculator/') ?>" class="footer-link">Govt Job Age Calculator</a></li>
                        <li><a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" class="footer-link">7th Pay Salary Calculator</a></li>
                        <li><a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" class="footer-link">CGPA to % Converter</a></li>
                        <li><a href="<?= url('category/scholarships/') ?>" class="footer-link">National Scholarships (NSP)</a></li>
                        <li><a href="<?= url('category/admit-cards/') ?>" class="footer-link">Admit Cards &amp; Hall Tickets</a></li>
                        <li><a href="<?= url('category/exam-dates/') ?>" class="footer-link">Exam Calendars 2026–27</a></li>
                    </ul>
                </div>

                <!-- Column 4: Trust & Standards -->
                <div class="footer-col">
                    <h3 class="footer-heading">Editorial &amp; Legal</h3>
                    <ul class="footer-links-list">
                        <li><a href="<?= url('about/') ?>" class="footer-link">About Editorial Desk</a></li>
                        <li><a href="<?= url('editorial-policy/') ?>" class="footer-link">Fact-Checking Charter</a></li>
                        <li><a href="<?= url('ai-policy/') ?>" class="footer-link">AI Transparency Code</a></li>
                        <li><a href="<?= url('why-choose-us/') ?>" class="footer-link">Why Choose Us</a></li>
                        <li><a href="<?= url('contact/') ?>" class="footer-link">Grievance Redressal</a></li>
                        <li><a href="<?= url('disclaimer/') ?>" class="footer-link">Statutory Disclaimer</a></li>
                    </ul>
                </div>

            </div>

            <!-- Simple Minimalist Disclaimer (No bulky card/borders) -->
            <div class="footer-disclaimer-simple">
                <p>
                    <strong>Disclaimer:</strong> Sarkari.online is an independent educational news portal and is not affiliated, associated, or endorsed by any Government ministry, commission, or statutory agency. All recruitment notifications, admission timelines, and exam updates are curated from publicly available government gazettes and official board portals for candidate assistance. Aspirants must verify all details on the respective official statutory websites.
                </p>
            </div>
        </div>
    </div>

    <!-- Bottom Copyright Strip -->
    <?php
    $siteLastUpdated = \App\Database\Database::fetchValue("SELECT published_at FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 1");
    $siteLastUpdatedStr = !empty($siteLastUpdated) ? date('d M Y, h:i A', strtotime($siteLastUpdated)) . ' IST' : date('d M Y, h:i A') . ' IST';
    ?>
    <div class="footer-bottom-bar">
        <div class="container">
            <div class="footer-bottom-flex">
                <div class="footer-copy-left">
                    <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &middot; Independent Educational Information Network.</div>
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.35rem; display: flex; align-items: center; gap: 5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Portal Last Updated: <strong style="color: #cbd5e1;"><?= e($siteLastUpdatedStr) ?></strong></span>
                    </div>
                </div>
                <div class="footer-legal-inline-links">
                    <a href="<?= url('privacy-policy/') ?>">Privacy Policy</a>
                    <span class="footer-sep">&middot;</span>
                    <a href="<?= url('terms/') ?>">Terms of Service</a>
                    <span class="footer-sep">&middot;</span>
                    <a href="<?= url('disclaimer/') ?>">Disclaimer</a>
                    <span class="footer-sep">&middot;</span>
                    <a href="<?= url('sitemap.xml') ?>" target="_blank">Sitemap</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Smart Language Choice Banner (Safe Non-Intrusive Floating Pill) -->
<div id="mobileLangBanner" class="mobile-lang-banner" style="display: none;">
    <div class="mobile-lang-banner-inner">
        <div class="mobile-lang-banner-left">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <div class="mobile-lang-banner-text">
                <span class="lang-txt-hi">हिंदी में पढ़ें?</span>
                <span class="lang-txt-en">Choose language</span>
            </div>
        </div>
        <div class="mobile-lang-banner-actions">
            <button type="button" class="btn-lang-pill btn-lang-hi" id="btnChooseHindi">हिंदी</button>
            <button type="button" class="btn-lang-pill btn-lang-en" id="btnChooseEnglish">English</button>
            <button type="button" class="btn-lang-dismiss" id="btnCloseLangBanner" aria-label="Dismiss">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</div>

<style>
.mobile-lang-banner {
    position: fixed;
    bottom: 68px;
    left: 12px;
    right: 12px;
    z-index: 1050;
    animation: slideUpLangBanner 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@media (min-width: 769px) {
    .mobile-lang-banner {
        display: none !important;
    }
}
.mobile-lang-banner-inner {
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45);
    gap: 0.5rem;
}
.mobile-lang-banner-left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.mobile-lang-banner-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}
.mobile-lang-banner-text .lang-txt-hi {
    font-size: 0.85rem;
    font-weight: 800;
    color: #ffffff;
}
.mobile-lang-banner-text .lang-txt-en {
    font-size: 0.68rem;
    color: #94a3b8;
}
.mobile-lang-banner-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.btn-lang-pill {
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-lang-hi {
    background: #0284c7;
    color: #ffffff;
}
.btn-lang-hi:active {
    background: #0369a1;
}
.btn-lang-en {
    background: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.btn-lang-dismiss {
    background: transparent;
    border: none;
    color: #64748b;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}
.btn-lang-dismiss:active {
    color: #ffffff;
}
@keyframes slideUpLangBanner {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth >= 769) return;

    var isDismissed = localStorage.getItem('sarkari_lang_banner_closed');
    var hasCookie = document.cookie.indexOf('googtrans=') !== -1 && document.cookie.indexOf('googtrans=/en/en') === -1;
    if (isDismissed || hasCookie) return;

    var banner = document.getElementById('mobileLangBanner');
    if (!banner) return;

    setTimeout(function() {
        banner.style.display = 'block';
    }, 1500);

    var btnHi = document.getElementById('btnChooseHindi');
    if (btnHi) {
        btnHi.addEventListener('click', function() {
            localStorage.setItem('sarkari_lang_banner_closed', 'true');
            banner.style.display = 'none';
            if (typeof loadGoogleTranslate === 'function') loadGoogleTranslate();
            if (typeof setSiteLanguage === 'function') {
                setSiteLanguage('hi');
            } else {
                var d = new Date();
                d.setTime(d.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = "googtrans=/en/hi;path=/;expires=" + d.toUTCString();
                window.location.reload();
            }
        });
    }

    var btnEn = document.getElementById('btnChooseEnglish');
    if (btnEn) {
        btnEn.addEventListener('click', function() {
            localStorage.setItem('sarkari_lang_banner_closed', 'true');
            banner.style.display = 'none';
        });
    }

    var btnClose = document.getElementById('btnCloseLangBanner');
    if (btnClose) {
        btnClose.addEventListener('click', function() {
            localStorage.setItem('sarkari_lang_banner_closed', 'true');
            banner.style.display = 'none';
        });
    }
});
</script>

<?php include __DIR__ . '/mobile-nav.php'; ?>

<!-- Google Neural Multi-Language Translation Engine (Lazy-Loaded On Demand) -->
<div id="google_translate_element" style="display:none;" aria-hidden="true"></div>
<script>
window.googleTranslateElementInit = function() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,hi,ta,te,ur,bn,mr,gu,pa,kn,ml,or',
        autoDisplay: false,
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
};

function loadGoogleTranslate() {
    if (window._gtLoaded) return;
    window._gtLoaded = true;
    const s = document.createElement('script');
    s.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    s.async = true;
    document.body.appendChild(s);
}

// Load on user interaction or idle callback (0ms initial blocking)
document.addEventListener('DOMContentLoaded', function() {
    const triggers = document.querySelectorAll('.lang-toggle-btn, .lang-option-btn, #mobileLangSelect, .mobile-lang-box');
    triggers.forEach(function(t) {
        t.addEventListener('click', loadGoogleTranslate, { once: true });
        t.addEventListener('mouseenter', loadGoogleTranslate, { once: true });
        t.addEventListener('focus', loadGoogleTranslate, { once: true });
    });
    if (document.cookie.indexOf('googtrans=') !== -1 && document.cookie.indexOf('googtrans=/en/en') === -1) {
        loadGoogleTranslate();
    } else if ('requestIdleCallback' in window) {
        requestIdleCallback(function() { setTimeout(loadGoogleTranslate, 3500); });
    }
});
</script>

<!-- Google AdSense & Privacy Compliant Cookie Consent Banner -->
<div id="cookieConsentModal" class="cookie-consent-modal" style="display: none;">
    <div class="cookie-consent-card">
        <div class="cookie-consent-body">
            <div class="cookie-icon-wrap">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 13v.01"/></svg>
            </div>
            <div class="cookie-text-wrap">
                <div class="cookie-title">Cookie &amp; Advertising Preferences</div>
                <p class="cookie-desc">
                    <?= e(SITE_NAME) ?> and authorized advertising partners (including <strong>Google AdSense</strong>) use cookies to personalize content, deliver relevant advertisements, and analyze website traffic. By clicking <strong>"Accept All"</strong>, you consent to our use of cookies in accordance with our <a href="<?= url('privacy-policy/') ?>" target="_blank" rel="noopener">Privacy Policy</a>.
                </p>
            </div>
        </div>
        <div class="cookie-consent-actions">
            <button type="button" class="cookie-btn cookie-btn-accept" id="btnAcceptCookies">Accept All</button>
            <button type="button" class="cookie-btn cookie-btn-necessary" id="btnRejectCookies">Necessary Only</button>
        </div>
    </div>
</div>

<style>
.cookie-consent-modal {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    max-width: 580px;
    z-index: 99999;
    animation: cookieSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes cookieSlideUp {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.cookie-consent-card {
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(16px);
}
.cookie-consent-body {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.cookie-icon-wrap {
    background: rgba(56, 189, 248, 0.15);
    padding: 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
.cookie-title {
    font-size: 0.925rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 0.35rem;
}
.cookie-desc {
    font-size: 0.785rem;
    color: #cbd5e1;
    line-height: 1.5;
}
.cookie-desc a {
    color: #38bdf8;
    text-decoration: underline;
    font-weight: 600;
}
.cookie-consent-actions {
    display: flex;
    gap: 0.65rem;
    justify-content: flex-end;
}
.cookie-btn {
    padding: 0.5rem 1.1rem;
    border-radius: 8px;
    font-size: 0.825rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    font-family: inherit;
}
.cookie-btn-accept {
    background: #2563eb;
    color: #ffffff;
}
.cookie-btn-accept:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}
.cookie-btn-necessary {
    background: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.cookie-btn-necessary:hover {
    background: rgba(255, 255, 255, 0.18);
}
@media (max-width: 640px) {
    .cookie-consent-modal {
        left: 12px;
        right: 12px;
        bottom: 12px;
    }
    .cookie-consent-actions {
        flex-direction: column;
    }
    .cookie-btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
(function() {
    try {
        var consent = localStorage.getItem('sarkari_cookie_consent');
        if (!consent) {
            setTimeout(function() {
                var modal = document.getElementById('cookieConsentModal');
                if (modal) modal.style.display = 'block';
            }, 1200);
        }
        var acceptBtn = document.getElementById('btnAcceptCookies');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('sarkari_cookie_consent', 'accepted');
                var modal = document.getElementById('cookieConsentModal');
                if (modal) modal.style.display = 'none';
            });
        }
        var rejectBtn = document.getElementById('btnRejectCookies');
        if (rejectBtn) {
            rejectBtn.addEventListener('click', function() {
                localStorage.setItem('sarkari_cookie_consent', 'necessary');
                var modal = document.getElementById('cookieConsentModal');
                if (modal) modal.style.display = 'none';
            });
        }
    } catch(e) {}
})();
</script>

<!-- Vanilla JS Application Script -->
<script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
