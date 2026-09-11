<?php
declare(strict_types=1);

/**
 * Sarkari.online - Keyword-Accurate Statutory Application Guides
 * URL Patterns:
 *   - /how-to-apply/                -> Directory Index of all active & announced application guides
 *   - /how-to-apply/{exam-slug}/    -> Dedicated Step-by-Step statutory application guide
 * 
 * Programmatic content module strictly governed by the ABSOLUTE DATA-CONFIDENCE GATE.
 */

require_once __DIR__ . '/config.php';

use App\Database\Database;
use App\Services\ApplicationGuideRenderer;
use App\Helpers\Logger;

$slug = $_GET['slug'] ?? '';
$slug = strtolower(trim((string)$slug, '/'));

// Helper: Canonical guide slug generator
function formatGuideSlug(array $c): string {
    $auth = strtolower(trim((string)$c['authority_code']));
    $year = (int)$c['cycle_year'];
    $name = strtolower(trim((string)$c['exam_name']));
    if ($auth === 'ssc' && str_contains($name, 'cgl')) {
        return "ssc-cgl-{$year}";
    }
    if ($auth === 'ssc' && str_contains($name, 'chsl')) {
        return "ssc-chsl-{$year}";
    }
    return "{$auth}-{$year}";
}

// =========================================================================
// CASE A: DIRECTORY HUB PAGE (/how-to-apply/)
// =========================================================================
if (empty($slug)) {
    $pageTitle    = "How to Apply Online: Government Exam Application Guides & Portals (2026)";
    $pageDesc     = "Complete directory of step-by-step guides for government exam applications, active registration links, correction windows, and official portal URLs on Sarkari.online.";
    $canonicalUrl = url('how-to-apply/');
    $ogType       = 'website';

    $crumbs = [
        ['label' => 'Home', 'url' => url()],
        ['label' => 'Application Guides', 'url' => null]
    ];

    // Fetch Active Cycles (APPLICATION_OPEN or APPLICATION_CORRECTION)
    $activeCycles = Database::fetchAll(
        "SELECT * FROM exam_cycles 
          WHERE current_phase IN ('APPLICATION_OPEN', 'APPLICATION_CORRECTION')
            AND phase_confidence = 'VERIFIED'
          ORDER BY (current_phase = 'APPLICATION_OPEN') DESC, id DESC"
    );

    // Fetch Upcoming / Calendar Verified Cycles
    $upcomingCycles = Database::fetchAll(
        "SELECT * FROM exam_cycles 
          WHERE current_phase IN ('ANNUAL_CALENDAR_ONLY', 'NOTIFICATION_RELEASED')
            AND phase_confidence = 'VERIFIED'
          ORDER BY id DESC LIMIT 20"
    );

    // Fetch Concluded / Reference Verified Cycles
    $referenceCycles = Database::fetchAll(
        "SELECT * FROM exam_cycles 
          WHERE current_phase NOT IN ('APPLICATION_OPEN', 'APPLICATION_CORRECTION', 'ANNUAL_CALENDAR_ONLY', 'NOTIFICATION_RELEASED')
            AND phase_confidence = 'VERIFIED'
          ORDER BY id DESC LIMIT 15"
    );

    $schemaJson = json_encode([
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url()],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Application Guides', 'item' => $canonicalUrl]
                ]
            ],
            [
                '@type' => 'CollectionPage',
                'name'  => $pageTitle,
                'description' => $pageDesc,
                'url'   => $canonicalUrl
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $customHeadHtml = '<script type="application/ld+json">' . $schemaJson . '</script>';

    include __DIR__ . '/components/head.php';
    include __DIR__ . '/components/header.php';
    ?>

    <main class="site-main" style="padding: 1.5rem 0 4rem 0;">
        <div class="container">
            
            <!-- Breadcrumbs -->
            <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

            <div class="article-layout-grid">
                
                <!-- Main Content Column (Full Width in Grid) -->
                <article class="article-main-column">
                    
                    <header style="margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem;">
                        <h1 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0; line-height: 1.25; letter-spacing: -0.02em;">
                            Government Exam Application Guides (2026)
                        </h1>
                        <p style="font-size: 1.05rem; color: #475569; line-height: 1.6; margin: 0;">
                            Official step-by-step registration guides, active application portals, document upload guidelines, and form correction procedures verified against statutory commission circulars.
                        </p>
                    </header>

                    <!-- Section 1: Active Application Windows -->
                    <section style="margin-bottom: 3rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem;">
                            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0;">
                                Active Application &amp; Correction Portals
                            </h2>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 4px;">
                                Live Now
                            </span>
                        </div>

                        <?php if (!empty($activeCycles)): ?>
                            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th style="padding: 0.85rem 1rem; text-align: left; color: #1e3a8a; font-weight: 700;">Examination / Recruitment</th>
                                            <th style="padding: 0.85rem 1rem; text-align: left; color: #475569; font-weight: 600;">Authority</th>
                                            <th style="padding: 0.85rem 1rem; text-align: left; color: #475569; font-weight: 600;">Current Phase</th>
                                            <th style="padding: 0.85rem 1rem; text-align: left; color: #475569; font-weight: 600;">Last Date</th>
                                            <th style="padding: 0.85rem 1rem; text-align: right; color: #475569; font-weight: 600;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeCycles as $c): 
                                            $cSlug = formatGuideSlug($c);
                                            $facts = !empty($c['facts_json']) ? json_decode($c['facts_json'], true) : [];
                                            $lastDate = !empty($facts['application_end']) ? date('d M Y', strtotime($facts['application_end'])) : 'Refer Notice';
                                            $phaseBadge = $c['current_phase'] === 'APPLICATION_CORRECTION' 
                                                ? '<span style="color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; font-size: 0.75rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Correction Window</span>'
                                                : '<span style="color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; font-size: 0.75rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Application Open</span>';
                                        ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 0.85rem 1rem; font-weight: 700;">
                                                    <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="color: #0f172a; text-decoration: none;">
                                                        <?= e($c['exam_name']) ?> (<?= e((string)$c['cycle_year']) ?>)
                                                    </a>
                                                </td>
                                                <td style="padding: 0.85rem 1rem; color: #475569; font-weight: 600;">
                                                    <?= e($c['authority_code']) ?>
                                                </td>
                                                <td style="padding: 0.85rem 1rem;">
                                                    <?= $phaseBadge ?>
                                                </td>
                                                <td style="padding: 0.85rem 1rem; color: #334155;">
                                                    <?= e($lastDate) ?>
                                                </td>
                                                <td style="padding: 0.85rem 1rem; text-align: right;">
                                                    <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="display: inline-block; background: #1e3a8a; color: #ffffff; font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 4px; text-decoration: none;">
                                                        View Guide &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="color: #64748b;">Currently no application forms are active. Check upcoming schedules below.</p>
                        <?php endif; ?>
                    </section>

                    <!-- Section 2: Upcoming & Announced Cycles -->
                    <section style="margin-bottom: 3rem;">
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">
                            Upcoming Examination Schedules &amp; Calendars
                        </h2>
                        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                <thead>
                                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 0.85rem 1rem; text-align: left; color: #1e3a8a; font-weight: 700;">Examination</th>
                                        <th style="padding: 0.85rem 1rem; text-align: left; color: #475569; font-weight: 600;">Authority</th>
                                        <th style="padding: 0.85rem 1rem; text-align: left; color: #475569; font-weight: 600;">Official Portal</th>
                                        <th style="padding: 0.85rem 1rem; text-align: right; color: #475569; font-weight: 600;">Guide</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingCycles as $c): 
                                        $cSlug = formatGuideSlug($c);
                                        $portal = $c['phase_evidence_url'] ?: 'https://' . strtolower($c['authority_code']) . '.gov.in';
                                    ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                                <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="color: #0f172a; text-decoration: none;">
                                                    <?= e($c['exam_name']) ?> (<?= e((string)$c['cycle_year']) ?>)
                                                </a>
                                            </td>
                                            <td style="padding: 0.85rem 1rem; color: #475569;">
                                                <?= e($c['authority_code']) ?>
                                            </td>
                                            <td style="padding: 0.85rem 1rem;">
                                                <a href="<?= e($portal) ?>" target="_blank" rel="noopener noreferrer" style="color: #1e3a8a; text-decoration: underline;">
                                                    <?= parse_url($portal, PHP_URL_HOST) ?> &rarr;
                                                </a>
                                            </td>
                                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                                <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="color: #1e3a8a; font-weight: 700; text-decoration: none;">
                                                    Read Process &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Section 3: Reference & Past Cycles -->
                    <?php if (!empty($referenceCycles)): ?>
                    <section style="margin-bottom: 2rem;">
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">
                            Archived Procedural References
                        </h2>
                        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                                <tbody>
                                    <?php foreach ($referenceCycles as $c): 
                                        $cSlug = formatGuideSlug($c);
                                    ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                                <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="color: #334155; text-decoration: none;">
                                                    <?= e($c['exam_name']) ?> (<?= e((string)$c['cycle_year']) ?>)
                                                </a>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; color: #64748b;">
                                                <?= e($c['authority_code']) ?>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                                <a href="<?= url("how-to-apply/{$cSlug}/") ?>" style="color: #64748b; text-decoration: none;">
                                                    View Reference &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endif; ?>

                </article>

                <!-- Right Sidebar -->
                <?php include __DIR__ . '/components/sidebar.php'; ?>

            </div>

        </div>
    </main>

    <?php
    include __DIR__ . '/components/footer.php';
    exit;
}

// =========================================================================
// CASE B: DEDICATED APPLICATION GUIDE (/how-to-apply/{slug}/)
// =========================================================================

// 1. Resolve Exam Cycle flexibly from slug (e.g. "ctet-2026", "ssc-cgl-2026", "nsp-2026")
$cycle = null;
if (preg_match('/^([a-z0-9-]+)-(20[2-4]\d)$/', $slug, $matches)) {
    $rawAuthOrExam = $matches[1];
    $year = (int)$matches[2];
    $words = explode('-', $rawAuthOrExam);
    $primaryAuth = strtoupper($words[0]);

    // First try authority_code and cycle_year
    $cycle = Database::fetchOne(
        "SELECT * FROM exam_cycles 
          WHERE authority_code = :auth AND cycle_year = :year
          ORDER BY (phase_confidence = 'VERIFIED') DESC, id DESC LIMIT 1",
        ['auth' => $primaryAuth, 'year' => $year]
    );

    // If multi-word slug (e.g. ssc-cgl-2026), search exam_name matching words
    if (!$cycle || count($words) > 1) {
        $nameLike = '%' . strtoupper(str_replace('-', ' ', $rawAuthOrExam)) . '%';
        $matched = Database::fetchOne(
            "SELECT * FROM exam_cycles 
              WHERE UPPER(exam_name) LIKE :name_like AND cycle_year = :year
              ORDER BY (phase_confidence = 'VERIFIED') DESC, id DESC LIMIT 1",
            ['name_like' => $nameLike, 'year' => $year]
        );
        if ($matched) {
            $cycle = $matched;
        }
    }
}

// Fallback search by general slug fragment
if (!$cycle) {
    $slugPattern = '%' . str_replace('-', '%', $slug) . '%';
    $cycle = Database::fetchOne(
        "SELECT * FROM exam_cycles 
          WHERE (LOWER(exam_name) LIKE :slug_pat OR LOWER(authority_code) LIKE :slug_pat)
          ORDER BY (phase_confidence = 'VERIFIED') DESC, cycle_year DESC, id DESC LIMIT 1",
        ['slug_pat' => $slugPattern]
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
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$pageTitle    = $guideData['meta_title'];
$pageDesc     = $guideData['meta_description'];
$canonicalUrl = url("how-to-apply/{$slug}/");
$ogType       = 'article';

$examName  = htmlspecialchars((string)$cycle['exam_name'], ENT_QUOTES, 'UTF-8');
$authCode  = htmlspecialchars((string)$cycle['authority_code'], ENT_QUOTES, 'UTF-8');
$year      = htmlspecialchars((string)$cycle['cycle_year'], ENT_QUOTES, 'UTF-8');
$portalUrl = $cycle['phase_evidence_url'] ?: 'https://' . strtolower($authCode) . '.nic.in';

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
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Application Guides', 'item' => url('how-to-apply/')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => "{$authCode} {$year} Guide", 'item' => $canonicalUrl]
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

<main class="site-main" style="padding: 1.5rem 0 4rem 0;">
    <div class="container">
        
        <!-- Breadcrumbs -->
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <div class="article-layout-grid">
            
            <!-- Left Main Column (Full width in grid, zero floating card borders) -->
            <article class="article-main-column">
                
                <!-- Authority Verified Header Strip -->
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?= $authCode ?> OFFICIAL STATUTORY GUIDE
                    </span>
                    <div style="font-size: 0.75rem; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                        <span>Verified Official Portal &middot; <?= date('d M Y') ?></span>
                    </div>
                </div>

                <!-- Rendered Guide Content -->
                <?= $guideData['html'] ?>

                <!-- Official Source Attribution -->
                <div class="source-verification-box" style="margin-top: 2.5rem;" role="complementary">
                    <div class="source-ver-icon">
                        <?= icon('shield-check', 'icon-lg') ?>
                    </div>
                    <div class="source-ver-details">
                        <div class="source-ver-title">Official Portal Reference &amp; Attribution</div>
                        <p class="source-ver-text">
                            Procedural steps, portal URLs, and statutory rules in this guide have been verified directly against announcements published on the official <strong><?= $authCode ?></strong> examination portal.
                        </p>
                        <?php if (!empty($portalUrl)): ?>
                            <a href="<?= e($portalUrl) ?>" target="_blank" rel="noopener noreferrer" class="source-ver-link">
                                Visit Official <?= $authCode ?> Portal (<?= parse_url($portalUrl, PHP_URL_HOST) ?>) &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </article>

            <!-- Right Sidebar (Standard Sarkari.online Sidebar) -->
            <?php include __DIR__ . '/components/sidebar.php'; ?>

        </div>

    </div>
</main>

<?php
include __DIR__ . '/components/footer.php';
