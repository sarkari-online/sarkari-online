<?php
/**
 * Sarkari.online - Dynamic State Government Jobs Landing Page
 * Automated, real-time database-backed state employment portal with official commission portals and verified articles.
 */

require_once __DIR__ . '/config.php';

use App\Services\StateJobService;
use App\Helpers\Sanitizer;

$stateSlug = Sanitizer::string($_GET['state'] ?? '');
$state = StateJobService::getStateBySlug($stateSlug);

if (!$state) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$articlesData = StateJobService::getArticlesByState($state, 12, $currentPage);
$articles = $articlesData['items'];
$totalPages = $articlesData['pages'];
$isFallback = $articlesData['is_fallback'];

$pageTitle = $state['meta_title'] . ' | ' . SITE_NAME;
$pageDesc = $state['meta_desc'];
$pageKeywords = implode(', ', $state['top_keywords']);
$canonicalUrl = url('jobs/' . $state['slug'] . '/');
$ogType = 'website';

$schemaData = StateJobService::generateStateSchema($state, $articles);
$schemaJson = json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main state-detail-main">

    <!-- State Hero Banner -->
    <section class="state-banner" style="--state-brand-color: <?= e($state['color']) ?>; --state-brand-bg: <?= e($state['bg']) ?>;">
        <div class="container">
            
            <!-- Breadcrumbs -->
            <nav class="state-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= url() ?>">Home</a>
                <span class="sep" aria-hidden="true">›</span>
                <a href="<?= url('state-jobs/') ?>">State Govt Jobs</a>
                <span class="sep" aria-hidden="true">›</span>
                <span class="curr"><?= e($state['name']) ?></span>
            </nav>

            <div class="state-banner-inner">
                <div class="state-banner-main">
                    <div class="state-banner-tags">
                        <span class="state-banner-badge"><?= e($state['region']) ?> Zone</span>
                        <span class="state-meta-dot"></span>
                        <span class="state-banner-capital">Capital: <?= e($state['capital']) ?></span>
                        <span class="state-meta-dot"></span>
                        <span class="state-banner-auth">100% Statutory Verified</span>
                    </div>

                    <h1 class="state-banner-title">
                        <span class="state-title-en"><?= e($state['name']) ?> Government Jobs 2026</span>
                        <span class="state-title-hi"><?= e($state['name_hi']) ?> सरकारी नौकरी</span>
                    </h1>

                    <p class="state-banner-desc"><?= e($state['tagline']) ?>. Direct access to official recruitment alerts, online application portals, admit cards, and examination results.</p>

                    <!-- Popular Keyword Tags for SEO Discovery -->
                    <div class="state-keywords-pills">
                        <?php foreach ($state['top_keywords'] as $kw): ?>
                        <span class="keyword-pill"><?= e($kw) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Fast Official Portal Action Box -->
                <div class="state-banner-sidebar">
                    <div class="state-quick-card">
                        <div class="quick-card-header">
                            <span class="state-emblem-badge" style="background-color: <?= e($state['bg']) ?>; color: <?= e($state['color']) ?>;">
                                <?= e($state['code']) ?>
                            </span>
                            <div>
                                <h2 class="quick-card-title"><?= e($state['name']) ?> Portals</h2>
                                <span class="quick-card-sub"><?= count($state['conducting_bodies']) ?> Verified Commissions</span>
                            </div>
                        </div>

                        <div class="quick-portals-list">
                            <?php foreach ($state['conducting_bodies'] as $body): ?>
                            <a href="<?= e($body['url']) ?>" target="_blank" rel="noopener noreferrer" class="quick-portal-link">
                                <div>
                                    <span class="portal-abbr"><?= e($body['abbr']) ?></span>
                                    <span class="portal-name"><?= e(mb_substr($body['name'], 0, 28)) ?>…</span>
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Main Content Area -->
    <div class="container state-content-layout">
        <div class="state-main-col">

            <!-- Active Job Notifications Section -->
            <section class="state-jobs-section">
                <div class="section-header-row">
                    <div>
                        <h2 class="section-title">
                            <?= $isFallback ? "Active Government Jobs & " . e($state['name']) . " Opportunities 2026" : "Verified " . e($state['name']) . " Recruitment Notifications" ?>
                        </h2>
                        <p class="section-desc">
                            <?= $isFallback ? "All-India and state-eligible vacancies open for " . e($state['name']) . " candidates (State gazette notices auto-synced upon release):" : "Live database-synchronized jobs matching " . e($state['name']) . " recruiting agencies:" ?>
                        </p>
                    </div>
                    <span class="job-count-badge"><?= count($articles) ?> Active Updates</span>
                </div>

                <?php if ($isFallback && !empty($articles)): ?>
                <div class="state-fallback-notice" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div style="font-size: 0.85rem; color: #1e3a8a; line-height: 1.5;">
                        <strong>Upcoming <?= e($state['name']) ?> Vacancies:</strong> State selection boards (<?= e(implode(', ', array_column($state['conducting_bodies'], 'abbr'))) ?>) release official notification gazettes periodically. Below are the verified active public sector vacancies open for candidates from <?= e($state['name']) ?>.
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($articles)): ?>
                <div class="state-articles-grid">
                    <?php foreach ($articles as $art): ?>
                    <article class="state-article-card">
                        <div class="article-meta-bar" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                            <span class="article-cat-tag" style="background-color: <?= e($art['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($art['category_color'] ?? '#1e3a8a') ?>; font-weight:700;">
                                <?= e($art['category_name'] ?? 'Govt Jobs') ?>
                            </span>
                            <span class="article-date" style="display:inline-flex; align-items:center; gap:5px; font-size:0.75rem; font-weight:700; color:#1e293b; background:#f8fafc; border:1px solid #e2e8f0; padding:3px 8px; border-radius:4px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?= !empty($art['published_at']) ? date('M d, Y', strtotime($art['published_at'])) : date('M d, Y') ?>
                            </span>
                            <?php if ($isFallback): ?>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 7px; border-radius: 4px;">
                                Open for <?= e($state['code']) ?> Candidates
                            </span>
                            <?php else: ?>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 7px; border-radius: 4px;">
                                <?= e($state['code']) ?> State Vacancy
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($art['reading_time'])): ?>
                            <span class="article-read-time"><?= e((string)$art['reading_time']) ?> min read</span>
                            <?php endif; ?>
                        </div>

                        <h3 class="article-title">
                            <a href="<?= url('article/' . $art['slug'] . '/') ?>">
                                <?= e($art['title']) ?>
                            </a>
                        </h3>

                        <?php if (!empty($art['summary'])): ?>
                        <p class="article-excerpt"><?= e(mb_substr($art['summary'], 0, 140)) ?>…</p>
                        <?php endif; ?>

                        <div class="article-footer-row">
                            <a href="<?= url('article/' . $art['slug'] . '/') ?>" class="article-apply-btn">
                                <span>Read Notification &amp; Apply</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination if required -->
                <?php if ($totalPages > 1): ?>
                <div class="state-pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>" class="page-link <?= $p === $currentPage ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="state-no-jobs">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <h3>Upcoming <?= e($state['name']) ?> State Notifications</h3>
                    <p>New vacancies for <?= e($state['name']) ?> are updated as soon as gazette releases are published. Check the official commission portals below for active advertisements.</p>
                </div>
                <?php endif; ?>
            </section>

            <!-- Official State Conducting Bodies Directory -->
            <section class="state-bodies-section">
                <div class="section-header-row">
                    <div>
                        <h2 class="section-title">Official <?= e($state['name']) ?> Recruitment Bodies &amp; Commissions</h2>
                        <p class="section-desc">Direct links to verified statutory government selection portals:</p>
                    </div>
                </div>

                <div class="bodies-grid">
                    <?php foreach ($state['conducting_bodies'] as $body): ?>
                    <div class="body-card">
                        <div class="body-card-top">
                            <span class="body-abbr-badge"><?= e($body['abbr']) ?></span>
                            <a href="<?= e($body['url']) ?>" target="_blank" rel="noopener noreferrer" class="body-ext-link">
                                Official Portal
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                        <h3 class="body-full-name"><?= e($body['name']) ?></h3>
                        <p class="body-desc"><?= e($body['desc']) ?></p>
                        <div class="body-url-domain"><code><?= parse_url($body['url'], PHP_URL_HOST) ?></code></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- State-Specific SEO FAQs (Google Rich Snippets) -->
            <section class="state-faq-section">
                <div class="section-header-row">
                    <div>
                        <h2 class="section-title">Frequently Asked Questions — <?= e($state['name']) ?> Govt Jobs</h2>
                        <p class="section-desc">Key details regarding state eligibility, application procedures, and recruitment cycles:</p>
                    </div>
                </div>

                <div class="state-faq-accordion">
                    <details class="state-faq-item" open>
                        <summary class="faq-question">How can I check the latest <?= e($state['name']) ?> Government Jobs in 2026?</summary>
                        <div class="faq-answer">
                            <p>You can check verified <?= e($state['name']) ?> employment alerts directly on this official hub of Sarkari.online. We update notifications daily from official gazettes and statutory commissions including <?= e(implode(', ', array_column($state['conducting_bodies'], 'abbr'))) ?>.</p>
                        </div>
                    </details>

                    <details class="state-faq-item">
                        <summary class="faq-question">What are the main public recruitment agencies in <?= e($state['name']) ?>?</summary>
                        <div class="faq-answer">
                            <p>The principal recruitment agencies in <?= e($state['name']) ?> are <?= e(implode(', ', array_map(function($b) { return "{$b['name']} ({$b['abbr']})"; }, $state['conducting_bodies']))) ?>.</p>
                        </div>
                    </details>

                    <details class="state-faq-item">
                        <summary class="faq-question">Are candidates from other states eligible for <?= e($state['name']) ?> state jobs?</summary>
                        <div class="faq-answer">
                            <p>Yes, in most general category recruitment, citizens of India from any state can apply, subject to regional domicile reservation policies. However, reserved category benefits (SC/ST/OBC/EWS) are typically applicable only to bona fide permanent residents of <?= e($state['name']) ?>.</p>
                        </div>
                    </details>
                </div>
            </section>

        </div>

        <!-- Sidebar Navigation -->
        <aside class="state-sidebar-col">
            <div class="sidebar-box">
                <h3 class="sidebar-box-title">Explore Other States</h3>
                <div class="sidebar-states-menu">
                    <?php 
                    $otherStates = StateJobService::getAllStates();
                    foreach ($otherStates as $oSlug => $oState): 
                        if ($oSlug === $state['slug']) continue;
                    ?>
                    <a href="<?= url('jobs/' . $oState['slug'] . '/') ?>" class="sidebar-state-item">
                        <span class="sb-code"><?= e($oState['code']) ?></span>
                        <span class="sb-name"><?= e($oState['name']) ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Free Student Tools Widget -->
            <div class="sidebar-box tools-widget">
                <h3 class="sidebar-box-title">Student Calculators</h3>
                <p class="sidebar-box-sub">Free utilities for state recruitment eligibility:</p>
                <div class="sidebar-tools-list">
                    <a href="<?= url('tools/age-calculator/') ?>" class="sb-tool-link">
                        <strong>Govt Job Age Calculator</strong>
                        <span>Calculate exact cutoff age &amp; category relaxations</span>
                    </a>
                    <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" class="sb-tool-link">
                        <strong>7th Pay Salary Calculator</strong>
                        <span>In-hand pay, DA &amp; HRA across pay levels</span>
                    </a>
                    <a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" class="sb-tool-link">
                        <strong>CGPA to % Converter</strong>
                        <span>University grade conversion for job forms</span>
                    </a>
                </div>
            </div>
        </aside>
    </div>

</main>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
