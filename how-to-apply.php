<?php
declare(strict_types=1);

/**
 * Sarkari.online - Keyword-Accurate Statutory Application Guides
 * URL Pattern: /how-to-apply/{exam-slug}-{year}/
 * 
 * Programmatic content module strictly governed by the ABSOLUTE DATA-CONFIDENCE GATE.
 */

require_once __DIR__ . '/config.php';

use App\Database\Database;
use App\Services\ApplicationGuideRenderer;
use App\Helpers\Logger;

$slug = $_GET['slug'] ?? '';
$slug = strtolower(trim((string)$slug, '/'));

if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// 1. Resolve Exam Cycle from slug (e.g. "ctet-2026")
$cycle = null;
if (preg_match('/^([a-z0-9-]+)-(20[2-4]\d)$/', $slug, $matches)) {
    $authOrExamPart = strtoupper(str_replace('-', ' ', $matches[1]));
    $year = (int)$matches[2];

    // Query by authority_code and cycle_year
    $cycle = Database::fetchOne(
        "SELECT * FROM exam_cycles 
          WHERE (authority_code = :auth OR UPPER(exam_name) LIKE :name_like)
            AND cycle_year = :year
          ORDER BY id DESC LIMIT 1",
        [
            'auth'      => strtoupper($matches[1]),
            'name_like' => '%' . $authOrExamPart . '%',
            'year'      => $year,
        ]
    );
}

// Fallback search by general slug fragment
if (!$cycle) {
    $cycle = Database::fetchOne(
        "SELECT * FROM exam_cycles 
          WHERE (LOWER(exam_name) LIKE :slug_like OR LOWER(authority_code) = :auth_slug)
          ORDER BY cycle_year DESC, id DESC LIMIT 1",
        [
            'slug_like' => '%' . str_replace('-', '%', $slug) . '%',
            'auth_slug' => $slug,
        ]
    );
}

if (!$cycle) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// 2. Render content using ApplicationGuideRenderer
$renderer = new ApplicationGuideRenderer();
$guideData = $renderer->render($cycle, false);

if (!$guideData) {
    // If blocked by gate or phase
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pageTitle    = $guideData['meta_title'];
$pageDesc     = $guideData['meta_description'];
$canonicalUrl = url("how-to-apply/{$slug}/");
$ogType       = 'article';

$examName = htmlspecialchars($cycle['exam_name'], ENT_QUOTES, 'UTF-8');
$authCode = htmlspecialchars($cycle['authority_code'], ENT_QUOTES, 'UTF-8');
$year     = htmlspecialchars((string)$cycle['cycle_year'], ENT_QUOTES, 'UTF-8');

// 3. Structured Data: HowTo & Breadcrumbs
$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Application Guides', 'url' => url('how-to-apply/')],
    ['label' => "{$authCode} {$year}", 'url' => null]
];

$schemaJson = json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Home',
                    'item'     => url(),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => "{$authCode} {$year} Application Guide",
                    'item'     => $canonicalUrl,
                ]
            ]
        ],
        [
            '@type' => 'HowTo',
            'name'  => $guideData['keywords']['primary_h1'] ?? "How to Apply for {$examName} {$year}",
            'description' => $pageDesc,
            'step' => [
                [
                    '@type' => 'HowToStep',
                    'position' => 1,
                    'name' => 'Official Portal Registration',
                    'text' => "Navigate to official {$authCode} portal, click on New Candidate Registration, and verify via OTP."
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 2,
                    'name' => 'Candidate Details & Qualifications',
                    'text' => 'Enter personal details exactly matching Class 10 certificate and select test centre preferences.'
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 3,
                    'name' => 'Upload Scanned Photo & Signature',
                    'text' => 'Upload passport photograph and signature in prescribed format.'
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 4,
                    'name' => 'Application Fee Payment',
                    'text' => 'Pay statutory application fee through Net Banking, UPI, or Debit/Credit card.'
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 5,
                    'name' => 'Confirmation Page Download',
                    'text' => 'Review final form submission and save/print Confirmation Page receipt.'
                ]
            ]
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$customHeadHtml = '<script type="application/ld+json">' . $schemaJson . '</script>';

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding: 2rem 0 5rem 0; background: #f8fafc; min-height: 80vh;">
    <div class="container" style="max-width: 960px; margin: 0 auto; padding: 0 1rem;">
        
        <!-- Breadcrumbs -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
            <ol style="display: flex; flex-wrap: wrap; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li><a href="<?= url('category/application-form/') ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Application Guides</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li style="color: #0f172a; font-weight: 600;"><?= $authCode ?> <?= $year ?></li>
            </ol>
        </nav>

        <!-- Main Guide Card -->
        <article style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); margin-bottom: 2rem;">
            
            <!-- Authority Verified Badge & Strip -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 0.72rem; font-weight: 700; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= $authCode ?> OFFICIAL APPLICATION GUIDE
                </span>
                <div style="font-size: 0.75rem; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    <span>Verified Authority &middot; Last Checked: <?= date('M d, Y') ?></span>
                </div>
            </div>

            <!-- Rendered Guide Content -->
            <?= $guideData['html'] ?>

        </article>

    </div>
</main>

<?php
include __DIR__ . '/components/footer.php';
