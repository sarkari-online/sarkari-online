<?php
/**
 * Sarkari.online - 404 Not Found Page
 * Executive Design matching brand system (#1a237e Navy, #f57c00 Orange)
 */
require_once __DIR__ . '/config.php';

http_response_code(404);
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow', true);
}

$pageTitle = 'Page Not Found (404) | ' . SITE_NAME;
$pageDesc = 'The page or notification you are looking for has been relocated or updated. Search our verified database of government job alerts, admit cards, and results.';
$canonicalUrl = '';
$metaRobots = 'noindex, nofollow';
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => '404 Not Found', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding: 2.5rem 0 4rem; background: var(--bg-page);">
    <div class="container container-narrow">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <div style="background: #ffffff; border: 1px solid #e0e0e0; border-top: 4px solid #f57c00; border-radius: 16px; padding: 3rem 2rem; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 680px; margin: 1.5rem auto;">
            
            <div style="display: inline-block; background: #e8eaf6; color: #1a237e; font-size: 0.8rem; font-weight: 800; padding: 4px 14px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">
                HTTP 404 &middot; Notice Relocated
            </div>

            <div style="font-size: clamp(3.5rem, 8vw, 5.5rem); font-weight: 900; line-height: 1; color: #1a237e; letter-spacing: -0.04em; margin-bottom: 0.75rem;">
                404
            </div>

            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                Notice Not Found or Cycle Relocated
            </h1>

            <p style="color: #546e7a; font-size: 0.95rem; line-height: 1.6; max-width: 500px; margin: 0 auto 2rem auto;">
                The recruitment notice, examination scorecard, or syllabus update you requested may have been updated with a newer cycle or relocated.
            </p>

            <!-- Search Box with Navy & Orange Action -->
            <form action="<?= url('search/') ?>" method="GET" style="max-width: 480px; margin: 0 auto 2rem auto; display: flex; gap: 8px;">
                <input type="search" name="q" placeholder="Search exams, results, admit cards, full forms..." required style="flex: 1; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.15s;" onfocus="this.style.borderColor='#1a237e'" onblur="this.style.borderColor='#cbd5e1'">
                <button type="submit" style="background: #1a237e; color: #ffffff; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: background 0.15s; white-space: nowrap;" onmouseover="this.style.background='#f57c00'" onmouseout="this.style.background='#1a237e'">
                    Search
                </button>
            </form>

            <!-- Quick Access Portals -->
            <div style="margin-bottom: 2.25rem; padding-top: 1.5rem; border-top: 1px solid #f0f0f0;">
                <div style="font-size: 0.75rem; font-weight: 800; color: #78909c; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1rem;">
                    Quick Portal Shortcuts
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                    <a href="<?= url('category/exam-results/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">Exam Results</a>
                    <a href="<?= url('latest-jobs/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">Latest Govt Jobs</a>
                    <a href="<?= url('category/admit-cards/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">Admit Cards</a>
                    <a href="<?= url('category/answer-keys/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">Answer Keys</a>
                    <a href="<?= url('full-forms/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">Full Forms (A-Z)</a>
                    <a href="<?= url('state-jobs/') ?>" style="padding: 6px 14px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.825rem; font-weight: 600; color: #1a237e; text-decoration: none; transition: all 0.15s;" onmouseover="this.style.background='#1a237e'; this.style.color='#fff';" onmouseout="this.style.background='#fafafa'; this.style.color='#1a237e';">State Jobs (28 States)</a>
                </div>
            </div>

            <a href="<?= url() ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #1a237e; font-weight: 700; font-size: 0.9rem; text-decoration: none;" onmouseover="this.style.color='#f57c00'" onmouseout="this.style.color='#1a237e'">
                &larr; Return to Homepage
            </a>
        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
