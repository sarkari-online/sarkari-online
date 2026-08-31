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
                        <li><a href="<?= url('category/government-jobs/') ?>" class="footer-link">Staff Selection (SSC)</a></li>
                        <li><a href="<?= url('category/government-jobs/') ?>" class="footer-link">Railway Recruitment (RRB)</a></li>
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
    <div class="footer-bottom-bar">
        <div class="container">
            <div class="footer-bottom-flex">
                <div class="footer-copy-left">
                    &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &middot; Independent Educational Information Network.
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

<!-- Vanilla JS Application Script -->
<script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
