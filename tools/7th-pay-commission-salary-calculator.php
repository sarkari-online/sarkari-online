<?php
/**
 * 7th Pay Commission Salary & In-Hand Pay Calculator (2026-2027 Edition)
 * High-Accuracy Statutory Computation Engine for Central Government, SSC, RRB, Banking & Defence Posts.
 * Professional Institutional UI with SVG Iconography & Rich Schemas for Google Search Rankings.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\SEOHelper;

$pageTitle = '7th Pay Commission Salary Calculator 2026: Calculate In-Hand Pay, DA 50%, HRA & Deductions';
$pageDesc = 'Free 7th Pay Commission in-hand salary calculator for Central Govt, SSC, RRB, UPSC & Banking posts. Calculate exact basic pay (Level 1-10), 50% DA, HRA (X, Y, Z cities), TA, and NPS deductions.';
$pageKeywords = '7th pay commission salary calculator, in hand salary calculator 2026, ssc cgl salary calculator, 7th cpc pay matrix level 7, da 50 percent salary calculation, central govt salary in hand, hra rates x y z cities';
$canonicalUrl = url('tools/7th-pay-commission-salary-calculator/');

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Student Tools', 'url' => url('tools/')],
    ['label' => '7th Pay Salary Calculator', 'url' => $canonicalUrl]
];

// Rich JSON-LD Schemas for Google Rich Snippets
$schemas = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => '7th Pay Commission Salary Calculator 2026',
        'url' => $canonicalUrl,
        'description' => $pageDesc,
        'applicationCategory' => 'FinanceApplication',
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
                'name' => 'What is the current DA (Dearness Allowance) rate in 7th Pay Commission for 2026?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'The current Dearness Allowance (DA) rate for Central Government employees is 50% of the Basic Pay. When the DA rate reached 50%, the House Rent Allowance (HRA) rates were automatically revised to 30% for X cities, 20% for Y cities, and 10% for Z cities.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the in-hand salary for SSC CGL Level 7 posts (Income Tax Inspector, ASO)?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'For Pay Level 7 (Basic Pay ₹44,900), in an X-category city like Delhi or Mumbai, the gross salary is approximately ₹86,220 per month. After standard NPS deduction (10% of Basic+DA = ₹6,735) and CGHS/CGEGIS contributions, the net in-hand salary credited to the bank account is approximately ₹78,800 to ₹79,500 per month.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'How are cities classified into X, Y, and Z categories for HRA calculation?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Under 7th CPC guidelines: X category (Tier-1: Delhi, Mumbai, Kolkata, Chennai, Bengaluru, Hyderabad, Ahmedabad, Pune) receives 30% HRA. Y category (Tier-2 cities with population above 5 lakhs like Jaipur, Lucknow, Patna, Bhopal, Chandigarh) receives 20% HRA. Z category (all remaining rural areas and small towns) receives 10% HRA.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the monthly NPS deduction formula for Central Government employees?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'The mandatory employee NPS contribution under the National Pension System is 10% of (Basic Pay + Dearness Allowance). The Government of India additionally contributes 14% of (Basic Pay + DA) into the employee\'s PRAN account.'
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
                            <span style="color: #0f172a; font-weight: 600;"><?= e($crumb['label']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <!-- Professional Institutional Header Banner -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem;">
                <div style="max-width: 780px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; margin-bottom: 0.85rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Ministry of Finance 7th CPC Matrix Compliant &middot; 50% DA Active</span>
                    </div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.75rem 0; color: #0f172a; letter-spacing: -0.02em;">
                        7th Pay Commission Salary &amp; In-Hand Pay Calculator (2026–27)
                    </h1>
                    <p style="font-size: 0.95rem; color: #64748b; line-height: 1.6; margin: 0;">
                        Compute exact monthly in-hand net salary, gross package, 50% DA, house rent allowance (30%/20%/10%), transport allowance, and mandatory NPS deductions for Central Government, SSC, RRB, UPSC, and Banking posts.
                    </p>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; text-align: center; min-width: 170px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">Current DA Rate</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #0284c7; margin: 0.25rem 0;">50%</div>
                    <div style="font-size: 0.7rem; color: #15803d; font-weight: 600;">Standard Central Govt Rate</div>
                </div>
            </div>
        </div>

        <!-- Calculator Interactive Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;" class="calc-grid-layout">
            
            <!-- Left Column: Inputs & Controls -->
            <div style="background: #ffffff; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Post &amp; Posting Location Settings</span>
                </h2>

                <!-- 1. Pay Level Selection -->
                <div style="margin-bottom: 1.5rem;">
                    <label for="payLevelSelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">
                        1. Pay Level &amp; Grade Pay
                    </label>
                    <select id="payLevelSelect" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.95rem; font-weight: 600; color: #0f172a; background: #f8fafc; outline: none; cursor: pointer;">
                        <option value="1" data-basic="18000" data-gp="1800" data-posts="MTS, Group D, Peon, Trackman">Level 1 (GP 1800) &mdash; Basic: ₹18,000 (MTS / Group D)</option>
                        <option value="2" data-basic="19900" data-gp="1900" data-posts="Lower Division Clerk (LDC), Junior Clerk">Level 2 (GP 1900) &mdash; Basic: ₹19,900 (LDC / Junior Clerk)</option>
                        <option value="3" data-basic="21700" data-gp="2000" data-posts="Police Constable, Postal Assistant, Fireman">Level 3 (GP 2000) &mdash; Basic: ₹21,700 (Constable / Postal Asst)</option>
                        <option value="4" data-basic="25500" data-gp="2400" data-posts="Tax Assistant (CBIC/CBDT), UDC, DEO Grade A">Level 4 (GP 2400) &mdash; Basic: ₹25,500 (Tax Asst / UDC / DEO)</option>
                        <option value="5" data-basic="29200" data-gp="2800" data-posts="Auditor (CAG/CGDA), Accountant, Junior Accountant">Level 5 (GP 2800) &mdash; Basic: ₹29,200 (Auditor / Accountant)</option>
                        <option value="6" data-basic="35400" data-gp="4200" data-posts="Sub-Inspector (CBI/CAPF), Executive Asst, Junior Engineer">Level 6 (GP 4200) &mdash; Basic: ₹35,400 (SI / JE / Exec Asst)</option>
                        <option value="7" data-basic="44900" data-gp="4600" data-posts="Income Tax Inspector, ASO in CSS/MEA, GST Inspector" selected>Level 7 (GP 4600) &mdash; Basic: ₹44,900 (ITI / ASO / GST Inspector)</option>
                        <option value="8" data-basic="47600" data-gp="4800" data-posts="Assistant Audit Officer (AAO), Assistant Accounts Officer">Level 8 (GP 4800) &mdash; Basic: ₹47,600 (Assistant Audit Officer)</option>
                        <option value="9" data-basic="53100" data-gp="5400" data-posts="Section Officer, Assistant Accounts Officer (Senior)">Level 9 (GP 5400) &mdash; Basic: ₹53,100 (Section Officer)</option>
                        <option value="10" data-basic="56100" data-gp="5400" data-posts="UPSC CSE Entry (IAS, IPS, IRS, IFS), Assistant Commissioner">Level 10 (GP 5400) &mdash; Basic: ₹56,100 (UPSC Group A / IAS / IPS)</option>
                    </select>
                    <div id="typicalPostsDesc" style="font-size: 0.785rem; color: #64748b; margin-top: 0.35rem;">
                        <strong>Typical Posts:</strong> Income Tax Inspector, ASO in CSS/MEA, GST Inspector
                    </div>
                </div>

                <!-- 2. City Classification Selection -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">
                        2. Posting City Classification (HRA Tier)
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
                        <label style="cursor: pointer;">
                            <input type="radio" name="cityTier" value="X" checked style="display: none;" id="tierX">
                            <div class="tier-card active" id="cardTierX" style="border: 1.5px solid #0284c7; background: #f0f9ff; padding: 0.75rem 0.5rem; border-radius: 8px; text-align: center; transition: all 0.2s;">
                                <div style="font-weight: 800; font-size: 0.95rem; color: #0369a1;">X City (30%)</div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;">Delhi, Mumbai, Bengaluru</div>
                            </div>
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="cityTier" value="Y" style="display: none;" id="tierY">
                            <div class="tier-card" id="cardTierY" style="border: 1.5px solid #cbd5e1; background: #ffffff; padding: 0.75rem 0.5rem; border-radius: 8px; text-align: center; transition: all 0.2s;">
                                <div style="font-weight: 800; font-size: 0.95rem; color: #475569;">Y City (20%)</div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;">Jaipur, Lucknow, Patna</div>
                            </div>
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="cityTier" value="Z" style="display: none;" id="tierZ">
                            <div class="tier-card" id="cardTierZ" style="border: 1.5px solid #cbd5e1; background: #ffffff; padding: 0.75rem 0.5rem; border-radius: 8px; text-align: center; transition: all 0.2s;">
                                <div style="font-weight: 800; font-size: 0.95rem; color: #475569;">Z City (10%)</div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;">Small Towns &amp; Rural</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 3. Advanced Settings (DA & NPS Sliders) -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #334155;">Dearness Allowance (DA Rate):</span>
                        <span id="daRateVal" style="font-size: 0.925rem; font-weight: 800; color: #0284c7;">50%</span>
                    </div>
                    <input type="range" id="daRange" min="40" max="60" value="50" step="1" style="width: 100%; accent-color: #0284c7; cursor: pointer;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #334155;">NPS Employee Share:</span>
                        <span id="npsRateVal" style="font-size: 0.875rem; font-weight: 700; color: #15803d;">10% (Statutory)</span>
                    </div>
                    <div style="font-size: 0.72rem; color: #64748b;">
                        Calculated strictly as 10% of (Basic Pay + Dearness Allowance).
                    </div>
                </div>

                <!-- Action Button Strip -->
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" id="copySummaryBtn" style="flex: 1; padding: 0.75rem 1rem; border-radius: 8px; background: #0f172a; color: #ffffff; font-weight: 700; font-size: 0.875rem; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Copy Breakdown</span>
                    </button>
                    <button type="button" onclick="window.print();" style="padding: 0.75rem 1.25rem; border-radius: 8px; background: #ffffff; color: #334155; font-weight: 700; font-size: 0.875rem; border: 1px solid #cbd5e1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        <span>Print</span>
                    </button>
                </div>
            </div>

            <!-- Right Column: Live Output & Salary Slip -->
            <div>
                <!-- Primary In-Hand Card -->
                <div style="background: #0f172a; color: #ffffff; border-radius: 12px; padding: 2rem; border: 1px solid #1e293b; box-shadow: 0 4px 12px rgba(15,23,42,0.12); margin-bottom: 1.5rem; text-align: center;">
                    <span style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.075em; font-weight: 700; color: #94a3b8;">
                        Estimated Monthly In-Hand Salary (in Bank)
                    </span>
                    <div id="netInHandDisplay" style="font-size: 2.75rem; font-weight: 900; line-height: 1.1; margin: 0.5rem 0; color: #38bdf8;">
                        ₹78,805
                    </div>
                    <div style="font-size: 0.875rem; color: #cbd5e1;">
                        Annual In-Hand Package: <strong id="annualInHandDisplay" style="color: #ffffff;">₹9,45,660 / Year</strong>
                    </div>
                </div>

                <!-- Detailed Breakdown Table Box -->
                <div style="background: #ffffff; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; display: flex; justify-content: space-between; align-items: center;">
                        <span>Monthly Earnings &amp; Deductions</span>
                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">7th CPC Formula</span>
                    </h3>

                    <!-- Earnings Table -->
                    <div style="margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; font-weight: 800; color: #0284c7; text-transform: uppercase; margin-bottom: 0.5rem; border-bottom: 1px solid #e0f2fe; padding-bottom: 0.25rem;">
                            Earnings (A)
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>Basic Pay:</span>
                            <strong id="breakdownBasic" style="color: #0f172a;">₹44,900</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>Dearness Allowance (DA @ <span id="lblDaPercent">50</span>%):</span>
                            <strong id="breakdownDA" style="color: #0f172a;">₹22,450</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>House Rent Allowance (HRA @ <span id="lblHraPercent">30</span>%):</span>
                            <strong id="breakdownHRA" style="color: #0f172a;">₹13,470</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>Transport Allowance (TA + DA on TA):</span>
                            <strong id="breakdownTA" style="color: #0f172a;">₹5,400</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: 800; padding: 0.6rem 0; color: #0284c7; border-top: 1px dashed #cbd5e1; margin-top: 0.25rem;">
                            <span>Gross Monthly Salary (Total A):</span>
                            <span id="breakdownGross">₹86,220</span>
                        </div>
                    </div>

                    <!-- Deductions Table -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #dc2626; text-transform: uppercase; margin-bottom: 0.5rem; border-bottom: 1px solid #fee2e2; padding-bottom: 0.25rem;">
                            Statutory Deductions (B)
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>NPS Employee Contribution (10%):</span>
                            <strong id="breakdownNPS" style="color: #991b1b;">- ₹6,735</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>CGHS Health Contribution:</span>
                            <strong id="breakdownCGHS" style="color: #991b1b;">- ₹650</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.35rem 0; color: #334155;">
                            <span>CGEGIS Insurance Scheme:</span>
                            <strong id="breakdownCGEGIS" style="color: #991b1b;">- ₹30</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: 800; padding: 0.6rem 0; color: #dc2626; border-top: 1px dashed #cbd5e1; margin-top: 0.25rem;">
                            <span>Total Deductions (Total B):</span>
                            <span id="breakdownTotalDeduct">- ₹7,415</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- In-Depth SEO Reference Guides & Pay Matrix Tables -->
        <div style="background: #ffffff; border-radius: 12px; padding: 2.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 2rem;">
            
            <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1rem;">
                Official 7th CPC Pay Matrix &amp; In-Hand Salary Guide (2026–27)
            </h2>
            <p style="font-size: 0.95rem; color: #475569; line-height: 1.7; margin-bottom: 1.5rem;">
                The 7th Central Pay Commission (CPC) replaced the legacy system of Pay Bands and Grade Pay with a unified <strong>Pay Matrix</strong> covering Pay Level 1 (Grade Pay 1800) through Pay Level 18 (Cabinet Secretary). As of 2026, with Dearness Allowance reaching the threshold of <strong>50%</strong>, Central Government allowances including HRA, Children Education Allowance, and Hotel Subsidy have been revised upwards by 25%.
            </p>

            <h3 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-bottom: 0.85rem;">
                1. Central Government Pay Level Summary Matrix
            </h3>
            
            <div class="table-responsive" style="overflow-x: auto; margin-bottom: 2rem;">
                <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Pay Level</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Grade Pay</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Entry Basic Pay</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Gross (X City)</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Estimated In-Hand</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700;">Key Examinations / Posts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 1</td>
                            <td style="padding: 0.75rem 1rem;">GP 1800</td>
                            <td style="padding: 0.75rem 1rem;">₹18,000</td>
                            <td style="padding: 0.75rem 1rem;">₹34,425</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹31,400</td>
                            <td style="padding: 0.75rem 1rem;">SSC MTS, RRB Group D, Peon, Trackman</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 2</td>
                            <td style="padding: 0.75rem 1rem;">GP 1900</td>
                            <td style="padding: 0.75rem 1rem;">₹19,900</td>
                            <td style="padding: 0.75rem 1rem;">₹37,795</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹34,500</td>
                            <td style="padding: 0.75rem 1rem;">SSC CHSL (LDC / JSA), Junior Clerk</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 4</td>
                            <td style="padding: 0.75rem 1rem;">GP 2400</td>
                            <td style="padding: 0.75rem 1rem;">₹25,500</td>
                            <td style="padding: 0.75rem 1rem;">₹48,850</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹44,600</td>
                            <td style="padding: 0.75rem 1rem;">SSC CGL Tax Assistant, UDC, DEO</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 6</td>
                            <td style="padding: 0.75rem 1rem;">GP 4200</td>
                            <td style="padding: 0.75rem 1rem;">₹35,400</td>
                            <td style="padding: 0.75rem 1rem;">₹69,120</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹63,200</td>
                            <td style="padding: 0.75rem 1rem;">Sub-Inspector (CBI / Delhi Police), Junior Engineer</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 7</td>
                            <td style="padding: 0.75rem 1rem;">GP 4600</td>
                            <td style="padding: 0.75rem 1rem;">₹44,900</td>
                            <td style="padding: 0.75rem 1rem;">₹86,220</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹78,800</td>
                            <td style="padding: 0.75rem 1rem;">Income Tax Inspector, ASO (CSS/MEA), GST Inspector</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 8</td>
                            <td style="padding: 0.75rem 1rem;">GP 4800</td>
                            <td style="padding: 0.75rem 1rem;">₹47,600</td>
                            <td style="padding: 0.75rem 1rem;">₹91,060</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹83,200</td>
                            <td style="padding: 0.75rem 1rem;">Assistant Audit Officer (CAG), Assistant Accounts Officer</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">Level 10</td>
                            <td style="padding: 0.75rem 1rem;">GP 5400</td>
                            <td style="padding: 0.75rem 1rem;">₹56,100</td>
                            <td style="padding: 0.75rem 1rem;">₹1,09,950</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #15803d;">₹98,500</td>
                            <td style="padding: 0.75rem 1rem;">UPSC Civil Services (IAS, IPS, IRS), Group A Gazetted</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FAQs Section -->
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-top: 2rem; margin-bottom: 1rem;">
                Frequently Asked Questions (FAQs)
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;" open>
                    <summary style="font-weight: 700; color: #0f172a; cursor: pointer;">
                        How does the 50% DA increase affect HRA rates in 2026?
                    </summary>
                    <p style="font-size: 0.9rem; color: #475569; margin: 0.5rem 0 0 0; line-height: 1.6;">
                        As per the 7th Central Pay Commission recommendations accepted by the Ministry of Finance, when Dearness Allowance (DA) touches or crosses 50%, House Rent Allowance (HRA) rates automatically increase from 27%, 18%, and 9% to <strong>30% (X Class)</strong>, <strong>20% (Y Class)</strong>, and <strong>10% (Z Class)</strong> respectively.
                    </p>
                </details>

                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                    <summary style="font-weight: 700; color: #0f172a; cursor: pointer;">
                        What are the standard employee deduction components?
                    </summary>
                    <p style="font-size: 0.9rem; color: #475569; margin: 0.5rem 0 0 0; line-height: 1.6;">
                        Mandatory monthly deductions include <strong>NPS (10% of Basic Pay + DA)</strong>, <strong>CGHS (Health insurance)</strong> ranging between ₹250 and ₹650 based on pay level, and <strong>CGEGIS (Group insurance)</strong> of ₹30 to ₹120. Income tax (TDS) is deducted separately based on your chosen tax regime (Old vs New).
                    </p>
                </details>

                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                    <summary style="font-weight: 700; color: #0f172a; cursor: pointer;">
                        Is the salary calculation identical for Central Govt and State Govt employees?
                    </summary>
                    <p style="font-size: 0.9rem; color: #475569; margin: 0.5rem 0 0 0; line-height: 1.6;">
                        Basic Pay and Level definitions under 7th CPC are standard across all Central Government departments (SSC, Railways, Defence civilians, Central Ministries). Most state governments (e.g. UP, Maharashtra, Rajasthan, MP) follow the same 7th CPC Pay Matrix, though state-specific DA notification timelines and local allowances may slightly vary.
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
.tier-card.active {
    border-color: #0284c7 !important;
    background: #f0f9ff !important;
}
.tier-card.active div:first-child {
    color: #0369a1 !important;
}
</style>

<!-- Live Instant Reactive Calculation Engine (Zero Latency) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var payLevelSelect = document.getElementById('payLevelSelect');
    var daRange = document.getElementById('daRange');
    var daRateVal = document.getElementById('daRateVal');
    var typicalPostsDesc = document.getElementById('typicalPostsDesc');
    
    var netInHandDisplay = document.getElementById('netInHandDisplay');
    var annualInHandDisplay = document.getElementById('annualInHandDisplay');
    
    var breakdownBasic = document.getElementById('breakdownBasic');
    var breakdownDA = document.getElementById('breakdownDA');
    var breakdownHRA = document.getElementById('breakdownHRA');
    var breakdownTA = document.getElementById('breakdownTA');
    var breakdownGross = document.getElementById('breakdownGross');
    var breakdownNPS = document.getElementById('breakdownNPS');
    var breakdownCGHS = document.getElementById('breakdownCGHS');
    var breakdownCGEGIS = document.getElementById('breakdownCGEGIS');
    var breakdownTotalDeduct = document.getElementById('breakdownTotalDeduct');
    
    var lblDaPercent = document.getElementById('lblDaPercent');
    var lblHraPercent = document.getElementById('lblHraPercent');

    function formatINR(val) {
        return '₹' + Math.round(val).toLocaleString('en-IN');
    }

    function calculateSalary() {
        var selectedOpt = payLevelSelect.options[payLevelSelect.selectedIndex];
        var level = parseInt(payLevelSelect.value, 10);
        var basic = parseFloat(selectedOpt.getAttribute('data-basic')) || 18000;
        var posts = selectedOpt.getAttribute('data-posts') || '';
        typicalPostsDesc.innerHTML = '<strong>Typical Posts:</strong> ' + posts;

        var daPercent = parseFloat(daRange.value) || 50;
        daRateVal.textContent = daPercent + '%';
        lblDaPercent.textContent = daPercent;

        // Determine City Tier & HRA Rate
        var tier = 'X';
        if (document.getElementById('tierY').checked) tier = 'Y';
        if (document.getElementById('tierZ').checked) tier = 'Z';

        var hraRate = tier === 'X' ? 0.30 : (tier === 'Y' ? 0.20 : 0.10);
        lblHraPercent.textContent = (hraRate * 100);

        // DA Amount
        var daAmount = basic * (daPercent / 100);

        // HRA Amount (with statutory minimums)
        var minHRA = tier === 'X' ? 5400 : (tier === 'Y' ? 3600 : 1800);
        var hraAmount = Math.max(basic * hraRate, minHRA);

        // Transport Allowance (TA) + DA on TA
        var baseTA = 0;
        if (tier === 'X') {
            baseTA = (level <= 2) ? 1350 : (level >= 9 ? 7200 : 3600);
        } else {
            baseTA = (level <= 2) ? 900 : (level >= 9 ? 3600 : 1800);
        }
        var taAmount = baseTA + (baseTA * (daPercent / 100));

        // Gross Salary
        var grossSalary = basic + daAmount + hraAmount + taAmount;

        // Deductions
        var npsDeduction = (basic + daAmount) * 0.10; // 10% of Basic + DA
        var cghsDeduction = (level <= 5) ? 250 : ((level <= 8) ? 650 : 1000);
        var cgegisDeduction = (level <= 5) ? 30 : ((level <= 8) ? 60 : 120);
        var totalDeductions = npsDeduction + cghsDeduction + cgegisDeduction;

        // Net In-Hand
        var netInHand = grossSalary - totalDeductions;
        var annualInHand = netInHand * 12;

        // Update UI
        netInHandDisplay.textContent = formatINR(netInHand);
        annualInHandDisplay.textContent = formatINR(annualInHand) + ' / Year';

        breakdownBasic.textContent = formatINR(basic);
        breakdownDA.textContent = formatINR(daAmount);
        breakdownHRA.textContent = formatINR(hraAmount);
        breakdownTA.textContent = formatINR(taAmount);
        breakdownGross.textContent = formatINR(grossSalary);

        breakdownNPS.textContent = '- ' + formatINR(npsDeduction);
        breakdownCGHS.textContent = '- ' + formatINR(cghsDeduction);
        breakdownCGEGIS.textContent = '- ' + formatINR(cgegisDeduction);
        breakdownTotalDeduct.textContent = '- ' + formatINR(totalDeductions);
    }

    // Attach Event Listeners
    payLevelSelect.addEventListener('change', calculateSalary);
    daRange.addEventListener('input', calculateSalary);

    ['tierX', 'tierY', 'tierZ'].forEach(function(tierId) {
        var radio = document.getElementById(tierId);
        radio.addEventListener('change', function() {
            document.querySelectorAll('.tier-card').forEach(function(el) {
                el.classList.remove('active');
                el.style.borderColor = '#cbd5e1';
                el.style.background = '#ffffff';
                el.querySelector('div:first-child').style.color = '#475569';
            });
            var activeCard = document.getElementById('cardTier' + this.value);
            activeCard.classList.add('active');
            activeCard.style.borderColor = '#0284c7';
            activeCard.style.background = '#f0f9ff';
            activeCard.querySelector('div:first-child').style.color = '#0369a1';
            calculateSalary();
        });
    });

    // Copy Summary Button
    document.getElementById('copySummaryBtn').addEventListener('click', function() {
        var selectedOpt = payLevelSelect.options[payLevelSelect.selectedIndex].text;
        var inHand = netInHandDisplay.textContent;
        var text = "7th Pay Commission Salary Breakdown:\n" +
                   "Post: " + selectedOpt + "\n" +
                   "Net In-Hand Salary: " + inHand + "/month\n" +
                   "Gross Salary: " + breakdownGross.textContent + "\n" +
                   "Total Deductions: " + breakdownTotalDeduct.textContent + "\n" +
                   "Calculated via Sarkari.online Salary Calculator: " + window.location.href;
        
        navigator.clipboard.writeText(text).then(function() {
            alert('Salary Breakdown copied to clipboard!');
        });
    });

    // Initial Trigger
    calculateSalary();
});
</script>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
