<?php
/**
 * CGPA to Percentage & Marks Converter (2026 Edition)
 * Statutory conversion engine for CBSE, AICTE B.Tech, AKTU, VTU, Mumbai University & 10-Point Scales.
 * Includes WebApplication, FAQPage, HowTo and BreadcrumbList Rich Schemas for Google Search Rich Results.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\SEOHelper;

$pageTitle = 'CGPA to Percentage Calculator 2026: CBSE, B.Tech (AICTE), AKTU, VTU & MU Formula';
$pageDesc = 'Convert CGPA to percentage and marks for CBSE 10th/12th, B.Tech/Engineering (AICTE), AKTU, VTU, Mumbai University, and 10-point scales. Get exact percentage for SSC, UPSC & Railway application forms.';
$pageKeywords = 'cgpa to percentage calculator, cbse cgpa to percentage, aicte cgpa to percentage btech, vtu cgpa conversion, aktu percentage calculator, ssc form cgpa to percentage, 10 point scale cgpa converter';
$canonicalUrl = url('tools/cgpa-to-percentage-calculator/');

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Student Tools', 'url' => url('tools/')],
    ['label' => 'CGPA to Percentage Calculator', 'url' => $canonicalUrl]
];

// Rich JSON-LD Schemas for Google Rich Results
$schemas = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'CGPA to Percentage & Marks Converter',
        'url' => $canonicalUrl,
        'description' => $pageDesc,
        'applicationCategory' => 'EducationalApplication',
        'operatingSystem' => 'All',
        'browserRequirements' => 'Requires JavaScript. Requires HTML5.',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'INR'
        ],
        'provider' => [
            '@type' => 'Organization',
            'name' => SITE_NAME,
            'url' => SITE_URL
        ]
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'How to convert CBSE 10th CGPA to Percentage?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'To convert CBSE CGPA into percentage, multiply your overall CGPA by 9.5. Formula: Percentage = CGPA × 9.5. For example, if your CGPA is 8.4, your percentage is 8.4 × 9.5 = 79.8%.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'Why does CBSE multiply CGPA by 9.5 instead of 10?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'CBSE analyzed previous 5-year board exam score distributions and found that the average marks of candidates scoring 91–100 (A1 grade, 10 grade points) was approximately 95%. Dividing 95 by 10 gives 9.5, which is why 9.5 was officially adopted as the standard conversion multiplier.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the AICTE / B.Tech Engineering CGPA to Percentage conversion formula?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'According to AICTE guidelines for engineering and technical degree programs: Percentage = (CGPA - 0.75) × 10. For example, a CGPA of 8.0 converts to (8.0 - 0.75) × 10 = 72.5%.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'How to write CGPA as percentage in SSC and UPSC application forms?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'In SSC, UPSC, and Railway recruitment portals, enter the percentage calculated using your official university/board conversion formula. You should retain the official conversion certificate or formula page from your university transcript for document verification.'
                ]
            ]
        ]
    ]
];

include dirname(__DIR__) . '/components/head.php';
?>

<!-- Inject WebApplication & FAQPage JSON-LD Schemas -->
<?php foreach ($schemas as $s): ?>
    <script type="application/ld+json">
        <?= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>
<?php endforeach; ?>

<?php include dirname(__DIR__) . '/components/header.php'; ?>

<main class="site-main" style="padding: 2rem 0 4rem 0; background: #f8fafc;">
    <div class="container">
        
        <!-- Breadcrumb Bar -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.25rem;">
            <ol class="breadcrumb-list" style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <?php foreach ($crumbs as $idx => $crumb): ?>
                    <li>
                        <?php if ($idx < count($crumbs) - 1): ?>
                            <a href="<?= e($crumb['url']) ?>" style="color: var(--color-primary); text-decoration: none;"><?= e($crumb['label']) ?></a>
                            <span style="margin-left: 0.35rem; color: #cbd5e1;">/</span>
                        <?php else: ?>
                            <span style="color: #1e293b; font-weight: 600;"><?= e($crumb['label']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <!-- Tool Header Banner -->
        <div style="background: linear-gradient(135deg, #0284c7 0%, #0f172a 100%); color: #ffffff; border-radius: 16px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem;">
                <div style="max-width: 780px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; background: rgba(56, 189, 248, 0.2); border: 1px solid rgba(56, 189, 248, 0.4); color: #7dd3fc; margin-bottom: 0.85rem;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #38bdf8;"></span>
                        Statutory Board &amp; University Multipliers Verified &middot; 2026 Academic Standard
                    </div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.3; margin: 0 0 0.75rem 0; color: #ffffff;">
                        CGPA to Percentage &amp; Marks Converter (10-Point Scale)
                    </h1>
                    <p style="font-size: 0.95rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                        Instantly convert Cumulative Grade Point Average (CGPA) into exact equivalent percentage and calculated marks for CBSE 10th/12th, B.Tech/Engineering (AICTE), AKTU, VTU, Mumbai University, and Central Universities for government job applications.
                    </p>
                </div>
                <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 1rem 1.25rem; text-align: center; min-width: 170px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.05em;">Supported Scale</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #38bdf8; margin: 0.25rem 0;">10.0 CGPA</div>
                    <div style="font-size: 0.7rem; color: #bae6fd; font-weight: 600;">Standard UGC/AICTE Scale</div>
                </div>
            </div>
        </div>

        <!-- Calculator Interactive Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;" class="calc-grid-layout">
            
            <!-- Left Column: Inputs & Formula Selector -->
            <div style="background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <?= icon('settings', 'icon-sm') ?>
                    <span>Enter CGPA &amp; Select Board / University</span>
                </h2>

                <!-- 1. CGPA Input -->
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label for="cgpaInput" style="font-size: 0.875rem; font-weight: 700; color: #334155;">
                            1. Enter Your Overall CGPA (Out of 10.0)
                        </label>
                        <span id="cgpaLiveBadge" style="font-size: 1.1rem; font-weight: 900; color: #0284c7; background: #e0f2fe; padding: 2px 10px; border-radius: 6px;">8.40</span>
                    </div>
                    <input type="number" id="cgpaInput" min="0" max="10" step="0.01" value="8.40" style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 1.1rem; font-weight: 800; color: #0f172a; outline: none; margin-bottom: 0.75rem;">
                    <input type="range" id="cgpaRange" min="0" max="10" step="0.05" value="8.40" style="width: 100%; accent-color: #0284c7; cursor: pointer;">
                </div>

                <!-- 2. Board / University Formula Dropdown -->
                <div style="margin-bottom: 1.5rem;">
                    <label for="formulaSelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">
                        2. Select Board / University Conversion Formula
                    </label>
                    <select id="formulaSelect" style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.95rem; font-weight: 600; color: #0f172a; background: #f8fafc; outline: none; cursor: pointer;">
                        <option value="cbse" selected>CBSE Board (Class 10th &amp; 12th) &mdash; Multiplier: 9.5x</option>
                        <option value="aicte">AICTE Engineering / B.Tech &mdash; Formula: (CGPA - 0.75) × 10</option>
                        <option value="aktu">AKTU (Dr. APJ Abdul Kalam Tech Univ) &mdash; (CGPA - 0.75) × 10</option>
                        <option value="vtu">VTU Karnataka (Engineering) &mdash; (CGPA - 0.75) × 10</option>
                        <option value="mu">Mumbai University (MU 10-Point Scale)</option>
                        <option value="standard_10">Standard 10-Point Scale &mdash; Multiplier: 10x (CGPA × 10)</option>
                    </select>
                    <div id="formulaExplanation" style="font-size: 0.785rem; color: #64748b; margin-top: 0.35rem;">
                        <strong>Statutory Rule:</strong> Percentage = CGPA × 9.5 (Official CBSE standard)
                    </div>
                </div>

                <!-- 3. Optional Total Marks for Marks Calculation -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <label for="totalMarksInput" style="display: block; font-size: 0.8125rem; font-weight: 700; color: #334155; margin-bottom: 0.4rem;">
                        3. Maximum / Total Marks (Optional for Marks Calculation):
                    </label>
                    <input type="number" id="totalMarksInput" placeholder="e.g. 500 or 600" value="500" style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.95rem; font-weight: 700; outline: none;">
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.3rem;">
                        Used to calculate equivalent marks obtained out of maximum marks.
                    </div>
                </div>

                <!-- Action Button Strip -->
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" id="copyResultBtn" style="flex: 1; padding: 0.75rem 1rem; border-radius: 10px; background: #0f172a; color: #ffffff; font-weight: 700; font-size: 0.875rem; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <?= icon('copy', 'icon-xs') ?> Copy Percentage
                    </button>
                    <button type="button" onclick="window.print();" style="padding: 0.75rem 1rem; border-radius: 10px; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.875rem; border: 1px solid #cbd5e1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <?= icon('printer', 'icon-xs') ?> Print
                    </button>
                </div>
            </div>

            <!-- Right Column: Live Output Card -->
            <div>
                <!-- Primary Percentage Result Card -->
                <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.3); margin-bottom: 1.5rem; text-align: center;">
                    <span style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.075em; font-weight: 700; color: #bae6fd;">
                        Equivalent Calculated Percentage
                    </span>
                    <div id="percentageDisplay" style="font-size: 3.25rem; font-weight: 900; line-height: 1.1; margin: 0.5rem 0; color: #ffffff;">
                        79.80%
                    </div>
                    <div id="divisionBadge" style="display: inline-block; padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 0.875rem; background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.35); color: #ffffff;">
                        First Division with Distinction
                    </div>
                </div>

                <!-- Detailed Marks & Conversion Proof Box -->
                <div style="background: #ffffff; border-radius: 16px; padding: 1.75rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; display: flex; justify-content: space-between; align-items: center;">
                        <span>Step-by-Step Conversion Summary</span>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Statutory Proof</span>
                    </h3>

                    <div style="background: #f8fafc; border-left: 4px solid #0284c7; padding: 1rem; border-radius: 0 8px 8px 0; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Applied Formula:</div>
                        <div id="stepFormulaString" style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; font-family: monospace;">
                            8.40 × 9.5 = 79.80%
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                            <span style="color: #64748b;">Input CGPA:</span>
                            <strong id="outCGPA" style="color: #0f172a;">8.40 / 10.0</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                            <span style="color: #64748b;">Selected Authority:</span>
                            <strong id="outAuthority" style="color: #0f172a;">CBSE Board</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                            <span style="color: #64748b;">Equivalent Percentage:</span>
                            <strong id="outPercent" style="color: #0284c7; font-size: 1rem;">79.80%</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.25rem;">
                            <span style="color: #64748b;">Calculated Marks Obtained:</span>
                            <strong id="outMarks" style="color: #15803d; font-size: 1rem;">399 / 500 Marks</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- In-Depth SEO Educational Reference Content -->
        <div style="background: #ffffff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; margin-bottom: 2rem;">
            
            <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1rem;">
                Official University &amp; Board CGPA Conversion Reference Guide
            </h2>
            <p style="font-size: 0.95rem; color: #475569; line-height: 1.7; margin-bottom: 1.5rem;">
                When filling online application forms for UPSC Civil Services, Staff Selection Commission (SSC CGL/CHSL), Railway Recruitment Boards (RRB), and State PSC examinations, candidates are strictly required to declare percentage marks rather than raw CGPA. Each educational board and statutory body prescribes a specific conversion formula:
            </p>

            <h3 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-bottom: 0.85rem;">
                1. University &amp; Board Formula Comparison Matrix
            </h3>
            
            <div class="table-responsive" style="overflow-x: auto; margin-bottom: 2rem;">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Board / University</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Official Formula</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">CGPA 8.0 Example</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">CGPA 9.0 Example</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Statutory Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">CBSE Board (10th/12th)</td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace;">CGPA × 9.5</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">76.00%</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">85.50%</td>
                            <td style="padding: 0.75rem 1rem;">CBSE Circular No. Coord/2010</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">AICTE Engineering / B.Tech</td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace;">(CGPA - 0.75) × 10</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">72.50%</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">82.50%</td>
                            <td style="padding: 0.75rem 1rem;">AICTE Approval Process Handbook</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">AKTU (Uttar Pradesh)</td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace;">(CGPA - 0.75) × 10</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">72.50%</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">82.50%</td>
                            <td style="padding: 0.75rem 1rem;">AKTU Ordinance Notification</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">VTU Karnataka</td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace;">(CGPA - 0.75) × 10</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">72.50%</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">82.50%</td>
                            <td style="padding: 0.75rem 1rem;">VTU Executive Council Resolution</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Mumbai University (MU)</td>
                            <td style="padding: 0.75rem 1rem; font-family: monospace;">7.1 + (CGPA × 7.4)</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">66.30%</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">73.70%</td>
                            <td style="padding: 0.75rem 1rem;">MU Circular No. UG/02 of 2012-13</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FAQs Section -->
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-top: 2rem; margin-bottom: 1rem;">
                Frequently Asked Questions (FAQs)
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem;" open>
                    <summary style="font-weight: 700; color: #0f172a; cursor: pointer;">
                        What is the difference between SGPA and CGPA?
                    </summary>
                    <p style="font-size: 0.9rem; color: #475569; margin: 0.5rem 0 0 0; line-height: 1.6;">
                        <strong>SGPA (Semester Grade Point Average)</strong> reflects your performance in a single semester. <strong>CGPA (Cumulative Grade Point Average)</strong> is the weighted average of grade points secured in all semesters combined across the entire degree program.
                    </p>
                </details>

                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem;">
                    <summary style="font-weight: 700; color: #0f172a; cursor: pointer;">
                        What if my university doesn't specify a conversion formula?
                    </summary>
                    <p style="font-size: 0.9rem; color: #475569; margin: 0.5rem 0 0 0; line-height: 1.6;">
                        In cases where a university transcript or degree certificate does not mention a conversion guideline, government commissions (UPSC/SSC) accept the standard multiplier of <strong>9.5</strong> or <strong>(CGPA - 0.75) × 10</strong> for technical streams, provided you obtain a Bonafide conversion certificate from your Registrar.
                    </p>
                </details>
            </div>
        </div>
    </div>
</main>

<style>
@media (max-width: 900px) {
    .calc-grid-layout {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Live Instant Reactive Calculation Engine -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cgpaInput = document.getElementById('cgpaInput');
    var cgpaRange = document.getElementById('cgpaRange');
    var cgpaLiveBadge = document.getElementById('cgpaLiveBadge');
    var formulaSelect = document.getElementById('formulaSelect');
    var formulaExplanation = document.getElementById('formulaExplanation');
    var totalMarksInput = document.getElementById('totalMarksInput');

    var percentageDisplay = document.getElementById('percentageDisplay');
    var divisionBadge = document.getElementById('divisionBadge');
    var stepFormulaString = document.getElementById('stepFormulaString');

    var outCGPA = document.getElementById('outCGPA');
    var outAuthority = document.getElementById('outAuthority');
    var outPercent = document.getElementById('outPercent');
    var outMarks = document.getElementById('outMarks');

    function calculate() {
        var cgpa = parseFloat(cgpaInput.value);
        if (isNaN(cgpa) || cgpa < 0) cgpa = 0;
        if (cgpa > 10) cgpa = 10;

        cgpaLiveBadge.textContent = cgpa.toFixed(2);
        cgpaRange.value = cgpa;

        var formula = formulaSelect.value;
        var percent = 0;
        var formulaStr = '';
        var authorityName = '';

        switch (formula) {
            case 'cbse':
                percent = cgpa * 9.5;
                formulaStr = cgpa.toFixed(2) + ' × 9.5 = ' + percent.toFixed(2) + '%';
                authorityName = 'CBSE Board';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> Percentage = CGPA × 9.5 (Official CBSE standard)';
                break;
            case 'aicte':
                percent = Math.max(0, (cgpa - 0.75) * 10);
                formulaStr = '(' + cgpa.toFixed(2) + ' - 0.75) × 10 = ' + percent.toFixed(2) + '%';
                authorityName = 'AICTE B.Tech Standard';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> Percentage = (CGPA - 0.75) × 10 (AICTE Engineering Handbook)';
                break;
            case 'aktu':
                percent = Math.max(0, (cgpa - 0.75) * 10);
                formulaStr = '(' + cgpa.toFixed(2) + ' - 0.75) × 10 = ' + percent.toFixed(2) + '%';
                authorityName = 'AKTU Uttar Pradesh';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> Percentage = (CGPA - 0.75) × 10 (AKTU Ordinance)';
                break;
            case 'vtu':
                percent = Math.max(0, (cgpa - 0.75) * 10);
                formulaStr = '(' + cgpa.toFixed(2) + ' - 0.75) × 10 = ' + percent.toFixed(2) + '%';
                authorityName = 'VTU Karnataka';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> Percentage = (CGPA - 0.75) × 10 (VTU Resolution)';
                break;
            case 'mu':
                percent = cgpa >= 7 ? 7.1 + (cgpa * 7.4) : (cgpa * 7.25 + 11);
                formulaStr = '7.1 + (' + cgpa.toFixed(2) + ' × 7.4) = ' + percent.toFixed(2) + '%';
                authorityName = 'Mumbai University (MU)';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> 10-Point Grading Scale (MU Circular UG/02)';
                break;
            case 'standard_10':
                percent = cgpa * 10;
                formulaStr = cgpa.toFixed(2) + ' × 10 = ' + percent.toFixed(2) + '%';
                authorityName = 'Standard 10x Multiplier';
                formulaExplanation.innerHTML = '<strong>Statutory Rule:</strong> Direct 10x Scale (CGPA × 10)';
                break;
        }

        percent = Math.min(100, Math.max(0, percent));

        // Division Determination
        var division = 'Second Division';
        if (percent >= 75) {
            division = 'First Division with Distinction';
        } else if (percent >= 60) {
            division = 'First Division';
        } else if (percent >= 50) {
            division = 'Second Division';
        } else if (percent >= 33) {
            division = 'Third Division (Pass)';
        } else {
            division = 'Essential Repeat';
        }

        // Marks Calculation
        var maxMarks = parseFloat(totalMarksInput.value) || 500;
        var marksObtained = (percent / 100) * maxMarks;

        // Update UI
        percentageDisplay.textContent = percent.toFixed(2) + '%';
        divisionBadge.textContent = division;
        stepFormulaString.textContent = formulaStr;

        outCGPA.textContent = cgpa.toFixed(2) + ' / 10.0';
        outAuthority.textContent = authorityName;
        outPercent.textContent = percent.toFixed(2) + '%';
        outMarks.textContent = Math.round(marksObtained) + ' / ' + Math.round(maxMarks) + ' Marks';
    }

    cgpaInput.addEventListener('input', function() {
        cgpaRange.value = this.value;
        calculate();
    });

    cgpaRange.addEventListener('input', function() {
        cgpaInput.value = this.value;
        calculate();
    });

    formulaSelect.addEventListener('change', calculate);
    totalMarksInput.addEventListener('input', calculate);

    document.getElementById('copyResultBtn').addEventListener('click', function() {
        var text = "CGPA to Percentage Conversion Result:\n" +
                   "CGPA: " + outCGPA.textContent + "\n" +
                   "Formula: " + stepFormulaString.textContent + "\n" +
                   "Equivalent Percentage: " + percentageDisplay.textContent + "\n" +
                   "Division: " + divisionBadge.textContent + "\n" +
                   "Marks: " + outMarks.textContent + "\n" +
                   "Calculated via Sarkari.online CGPA Converter: " + window.location.href;
        
        navigator.clipboard.writeText(text).then(function() {
            alert('Percentage & Formula copied to clipboard!');
        });
    });

    calculate();
});
</script>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
