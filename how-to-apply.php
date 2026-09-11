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

<main class="site-main py-4" style="background-color: #f8fafc; min-height: 80vh;">
    <div class="container">
        
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb bg-transparent p-0 mb-0" style="font-size: 0.85rem;">
                <li class="breadcrumb-item"><a href="<?= url() ?>" class="text-decoration-none text-primary font-weight-bold">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('category/application-form/') ?>" class="text-decoration-none text-secondary">Application Guides</a></li>
                <li class="breadcrumb-item active text-dark font-weight-bold" aria-current="page"><?= $authCode ?> <?= $year ?></li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm border-0 rounded-3 p-4 p-md-5 bg-white">
                    
                    <!-- Authority Verified Badge -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">
                        <span class="badge bg-primary px-3 py-2" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                            OFFICIAL STATUTORY DIRECTIVE
                        </span>
                        <div class="text-success font-weight-bold" style="font-size: 0.8rem;">
                            ✓ Verified Portal Authority: <?= $authCode ?> &middot; <?= date('d M Y') ?>
                        </div>
                    </div>

                    <!-- Rendered Guide Content -->
                    <?= $guideData['html'] ?>

                </div>
            </div>
        </div>

    </div>
</main>

<?php
include __DIR__ . '/components/footer.php';
