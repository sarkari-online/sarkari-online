<?php
/**
 * Govt Job Age Calculator & Eligibility Checker (2026 Edition)
 * Statutory age computation engine for UPSC, SSC, IBPS, Railways, Defence & State PSCs.
 * Simple, high-trust institutional UI with SVG Iconography & Rich Schemas for Google Search Rankings.
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\SEOHelper;

$pageTitle = 'Govt Job Age Calculator 2026: UPSC, SSC, Banking & Category Relaxation Checker';
$pageDesc = 'Calculate exact age in years, months, and days as on notification cutoff date. Check age eligibility and category relaxation for UPSC, SSC CGL, CHSL, IBPS, Railways and State PSC forms.';
$pageKeywords = 'age calculator for govt jobs, ssc cgl age calculator, upsc age limit calculator, sarkari age calculator, age as on cutoff date calculator, obc sc st age relaxation calculator';
$canonicalUrl = url('tools/age-calculator/');

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Student Tools', 'url' => url('tools/')],
    ['label' => 'Govt Job Age Calculator', 'url' => $canonicalUrl]
];

// Rich JSON-LD Schemas for Google Rich Results
$schemas = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Govt Job Age Calculator & Eligibility Checker',
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
                'name' => 'How is age calculated as on cutoff date in government job forms?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'In Indian government exams (SSC, UPSC, Banking, Railways), age is calculated strictly as on the specified cutoff date (e.g., 1st August or 1st January of the exam year). Your age is computed by subtracting your Date of Birth from the Cutoff Date in completed years, months, and days.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the upper age limit relaxation for OBC category in central govt jobs?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'According to DoPT (Department of Personnel and Training) guidelines, OBC (Non-Creamy Layer) candidates are entitled to a 3-year relaxation over and above the upper age limit prescribed for General (UR) category candidates.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the age relaxation for SC/ST and PwBD candidates?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'SC and ST candidates receive 5 years of upper age limit relaxation. PwBD (General) candidates get 10 years, PwBD (OBC-NCL) candidates get 13 years, and PwBD (SC/ST) candidates receive up to 15 years of age relaxation in central government recruitments.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What is the age limit for UPSC Civil Services Exam 2026?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'For the UPSC Civil Services Examination (CSE), candidates must be at least 21 years old and must not have attained the age of 32 years as on 1st August of the exam year for General/EWS category. The upper age limit is 35 years for OBC (with 9 attempts) and 37 years for SC/ST (with unlimited attempts).'
                ]
            ]
        ]
    ]
];

include dirname(__DIR__) . '/components/head.php';
include dirname(__DIR__) . '/components/header.php';
?>

<main class="site-main" style="padding: 2rem 0 4.5rem 0; background: #f8fafc;">
    <div class="container">

        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
            <ol class="breadcrumb-list" style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li><a href="<?= url('tools/') ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Student Tools</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li style="color: #0f172a; font-weight: 600;">Govt Job Age Calculator</li>
            </ol>
        </nav>

        <!-- Header Title Banner -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="max-width: 820px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; margin-bottom: 0.85rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>DoPT Statutory Rules Compliant</span>
                </div>
                <h1 style="font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; line-height: 1.3; margin: 0 0 0.6rem 0; color: #0f172a; letter-spacing: -0.02em;">
                    Govt Job Age Calculator &amp; Eligibility Checker 2026
                </h1>
                <p style="font-size: 0.9375rem; color: #64748b; line-height: 1.6; margin: 0;">
                    Calculate your exact age in years, months, and days as on the official recruitment cutoff date. Check age eligibility and category-wise age relaxation (UR, OBC, SC, ST, PwBD) for UPSC, SSC, IBPS, Railways, and State PSC application forms.
                </p>
            </div>
        </div>

        <!-- Calculator Main Interactive Grid -->
        <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 1.75rem; margin-bottom: 2.5rem;" class="calc-grid-layout">
            
            <!-- Input Form Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: #0f172a;">Candidate Details &amp; Cutoff Date</h2>
                </div>

                <form id="ageCalcForm" onsubmit="event.preventDefault(); calculateAge();">
                    
                    <!-- Date of Birth (DOB) -->
                    <div style="margin-bottom: 1.35rem;">
                        <label for="dobInput" style="display: block; font-size: 0.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.45rem;">
                            Date of Birth (DOB) <span style="color: #b91c1c;">*</span>
                        </label>
                        <input type="date" id="dobInput" name="dob" required style="width: 100%; padding: 0.75rem 0.95rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; color: #0f172a; background: #ffffff; outline: none; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#1e3a8a';" onblur="this.style.borderColor='#cbd5e1';" onchange="calculateAge();">
                        <span style="font-size: 0.775rem; color: #64748b; margin-top: 0.25rem; display: block;">As recorded in your Class 10th / Matriculation Certificate.</span>
                    </div>

                    <!-- Cutoff Date (Age As On) -->
                    <div style="margin-bottom: 1.35rem;">
                        <label for="asOnInput" style="display: block; font-size: 0.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.45rem;">
                            Calculate Age As On (Notification Cutoff Date) <span style="color: #b91c1c;">*</span>
                        </label>
                        <input type="date" id="asOnInput" name="as_on" required style="width: 100%; padding: 0.75rem 0.95rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; color: #0f172a; background: #ffffff; outline: none; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#1e3a8a';" onblur="this.style.borderColor='#cbd5e1';" onchange="calculateAge();">
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.45rem; flex-wrap: wrap;">
                            <button type="button" onclick="setAsOnToday()" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">Today (<?= date('d M Y') ?>)</button>
                            <button type="button" onclick="setAsOnDate('2026-08-01')" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">01 Aug 2026 (UPSC / SSC)</button>
                            <button type="button" onclick="setAsOnDate('2026-01-01')" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">01 Jan 2026</button>
                        </div>
                    </div>

                    <!-- Target Exam Preset (Optional) -->
                    <div style="margin-bottom: 1.35rem;">
                        <label for="examSelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.45rem;">
                            Target Exam / Commission (Check Eligibility)
                        </label>
                        <select id="examSelect" onchange="calculateAge();" style="width: 100%; padding: 0.75rem 0.95rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.925rem; color: #0f172a; background: #ffffff; outline: none;">
                            <option value="custom">-- Custom Age Limit (Enter Below) --</option>
                            <option value="ssc_cgl_27" selected>SSC CGL (Auditor, Accountant, Tax Assistant — 18 to 27 Years)</option>
                            <option value="ssc_cgl_30">SSC CGL (Inspector, ASO, Income Tax — 18 to 30 Years)</option>
                            <option value="ssc_cgl_32">SSC CGL (Junior Statistical Officer JSO — 18 to 32 Years)</option>
                            <option value="ssc_chsl">SSC CHSL (LDC, DEO, JSA — 18 to 27 Years)</option>
                            <option value="upsc_cse">UPSC Civil Services (IAS / IPS / IFS — 21 to 32 Years)</option>
                            <option value="ibps_po">IBPS PO / Management Trainee (20 to 30 Years)</option>
                            <option value="ibps_clerk">IBPS Clerk / Office Assistant (20 to 28 Years)</option>
                            <option value="sbi_po">SBI PO (21 to 30 Years)</option>
                            <option value="rrb_ntpc">Railway RRB NTPC (Graduate — 18 to 33 Years)</option>
                            <option value="nda">NDA &amp; NA (Navy, Army, Air Force — 16.5 to 19.5 Years)</option>
                            <option value="cds">CDS (IMA / OTA / Naval Academy — 19 to 25 Years)</option>
                        </select>
                    </div>

                    <!-- Reservation Category -->
                    <div style="margin-bottom: 1.5rem;">
                        <label for="categorySelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.45rem;">
                            Reservation Category (Age Relaxation)
                        </label>
                        <select id="categorySelect" onchange="calculateAge();" style="width: 100%; padding: 0.75rem 0.95rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.925rem; color: #0f172a; background: #ffffff; outline: none;">
                            <option value="0" selected>General (UR) / EWS — No Relaxation (0 Years)</option>
                            <option value="3">OBC (Non-Creamy Layer) — 3 Years Relaxation</option>
                            <option value="5">SC / ST — 5 Years Relaxation</option>
                            <option value="10">PwBD (General / EWS) — 10 Years Relaxation</option>
                            <option value="13">PwBD (OBC-NCL) — 13 Years Relaxation</option>
                            <option value="15">PwBD (SC / ST) — 15 Years Relaxation</option>
                            <option value="3_esm">Ex-Servicemen (ESM) — 3 Years (after military service)</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.8rem 1.25rem; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                            <span>Calculate Exact Age</span>
                        </button>
                        <button type="button" onclick="resetForm();" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; padding: 0.8rem 1.15rem; font-weight: 600; border-radius: 6px; cursor: pointer;">
                            Reset
                        </button>
                    </div>

                </form>
            </div>

            <!-- Calculation Results Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between;">
                
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: #0f172a;">Computed Age &amp; Eligibility</h3>
                        </div>
                        <span id="asOnDisplayBadge" style="font-size: 0.75rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 4px;">
                            As on Cutoff Date
                        </span>
                    </div>

                    <!-- Primary Big Age Display -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; text-align: center;">
                        <div style="font-size: 0.8125rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                            Exact Calculated Age
                        </div>
                        <div id="primaryAgeText" style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: #0f172a; line-height: 1.25;">
                            Enter Dates to Calculate
                        </div>
                        <div id="primaryAgeSub" style="font-size: 0.845rem; color: #475569; margin-top: 0.35rem;">
                            Years &middot; Months &middot; Days
                        </div>
                    </div>

                    <!-- Exam Eligibility Status Card -->
                    <div id="eligibilityCard" style="display: none; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; line-height: 1.5;">
                        <!-- Injected via JavaScript -->
                    </div>

                    <!-- Secondary Breakdown Grid -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.25rem;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; text-align: center;">
                            <div style="font-size: 0.725rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Months</div>
                            <div id="totalMonthsText" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 2px;">—</div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; text-align: center;">
                            <div style="font-size: 0.725rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Weeks</div>
                            <div id="totalWeeksText" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 2px;">—</div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; text-align: center;">
                            <div style="font-size: 0.725rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Days</div>
                            <div id="totalDaysText" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-top: 2px;">—</div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; text-align: center;">
                            <div style="font-size: 0.725rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Next Birthday In</div>
                            <div id="nextBdayText" style="font-size: 1.1rem; font-weight: 700; color: #1e3a8a; margin-top: 2px;">—</div>
                        </div>
                    </div>
                </div>

                <!-- Action Button: Copy Summary -->
                <button type="button" id="copySummaryBtn" onclick="copyAgeSummary();" style="width: 100%; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.65rem 1rem; font-size: 0.845rem; font-weight: 600; color: #334155; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background 0.15s ease;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span id="copyBtnText">Copy Age Summary for Application Form</span>
                </button>

            </div>

        </div>

        <!-- Reference Guide 1: Category-Wise Age Relaxation Table -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0;">
                    Central Government Upper Age Limit Relaxation Rules (DoPT Guidelines)
                </h2>
            </div>
            <p style="font-size: 0.9rem; color: #64748b; line-height: 1.6; margin-bottom: 1.25rem;">
                Standard upper age limit concessions granted by the Government of India for competitive examinations and direct recruitment:
            </p>

            <div class="table-responsive" style="margin: 0; border-radius: 8px;">
                <table style="width: 100%; margin: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Candidate Category</th>
                            <th style="padding: 0.75rem 1rem;">Permissible Age Relaxation</th>
                            <th style="padding: 0.75rem 1rem;">Statutory Authority / Conditions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>General (UR) / EWS</strong></td>
                            <td>No relaxation (0 Years)</td>
                            <td>Standard notification upper age limit applies strictly.</td>
                        </tr>
                        <tr>
                            <td><strong>OBC (Non-Creamy Layer)</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+3 Years</span></td>
                            <td>Valid Central OBC-NCL certificate issued in financial year.</td>
                        </tr>
                        <tr>
                            <td><strong>SC / ST</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+5 Years</span></td>
                            <td>Castes and tribes notified in the Presidential Order.</td>
                        </tr>
                        <tr>
                            <td><strong>PwBD (General / EWS)</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+10 Years</span></td>
                            <td>Minimum 40% benchmark disability certificate.</td>
                        </tr>
                        <tr>
                            <td><strong>PwBD + OBC (NCL)</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+13 Years</span></td>
                            <td>Cumulative concession (10 years PwBD + 3 years OBC).</td>
                        </tr>
                        <tr>
                            <td><strong>PwBD + SC / ST</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+15 Years</span></td>
                            <td>Cumulative concession (10 years PwBD + 5 years SC/ST).</td>
                        </tr>
                        <tr>
                            <td><strong>Ex-Servicemen (ESM)</strong></td>
                            <td><span style="color: #15803d; font-weight: 700;">+3 Years</span></td>
                            <td>After deduction of military service rendered from actual age.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reference Guide 2: Top Exams Age Limit Matrix 2026 -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem 2.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0;">
                    Major Competitive Exams Age Limit Matrix (2026 Edition)
                </h2>
            </div>
            <p style="font-size: 0.9rem; color: #64748b; line-height: 1.6; margin-bottom: 1.25rem;">
                Prescribed age brackets (General category) and standard cutoff reference dates across top recruitment commissions:
            </p>

            <div class="table-responsive" style="margin: 0; border-radius: 8px;">
                <table style="width: 100%; margin: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Examination Name</th>
                            <th style="padding: 0.75rem 1rem;">Conducting Body</th>
                            <th style="padding: 0.75rem 1rem;">Min–Max Age (UR)</th>
                            <th style="padding: 0.75rem 1rem;">Standard Cutoff Reference Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>UPSC Civil Services (CSE)</strong></td>
                            <td>UPSC</td>
                            <td>21 – 32 Years</td>
                            <td>1st August of Examination Year</td>
                        </tr>
                        <tr>
                            <td><strong>SSC CGL (Group B / C)</strong></td>
                            <td>Staff Selection Commission</td>
                            <td>18 – 27 / 30 / 32 Years</td>
                            <td>1st August of Examination Year</td>
                        </tr>
                        <tr>
                            <td><strong>SSC CHSL (10+2)</strong></td>
                            <td>Staff Selection Commission</td>
                            <td>18 – 27 Years</td>
                            <td>1st August of Examination Year</td>
                        </tr>
                        <tr>
                            <td><strong>IBPS Probationary Officer (PO)</strong></td>
                            <td>IBPS</td>
                            <td>20 – 30 Years</td>
                            <td>1st day of the month of notification</td>
                        </tr>
                        <tr>
                            <td><strong>SBI PO</strong></td>
                            <td>State Bank of India</td>
                            <td>21 – 30 Years</td>
                            <td>1st April of Recruitment Year</td>
                        </tr>
                        <tr>
                            <td><strong>RRB NTPC (Non-Technical)</strong></td>
                            <td>Railway Recruitment Boards</td>
                            <td>18 – 33 / 36 Years</td>
                            <td>1st July of Recruitment Year</td>
                        </tr>
                        <tr>
                            <td><strong>NDA &amp; Naval Academy</strong></td>
                            <td>UPSC / Defence</td>
                            <td>16.5 – 19.5 Years</td>
                            <td>Exact birth date window specified in notice</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQ Section -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem 2.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0;">Frequently Asked Questions (FAQs)</h2>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                        How is age calculated as on cutoff date in government job forms?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        In Indian government exams (SSC, UPSC, Banking, Railways), age is calculated strictly as on the specified cutoff date (e.g., 1st August or 1st January of the exam year). Your age is computed by subtracting your Date of Birth from the Cutoff Date in completed years, months, and days.
                    </p>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                        What is the upper age limit relaxation for OBC category in central govt jobs?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        According to DoPT guidelines, OBC (Non-Creamy Layer) candidates are entitled to a 3-year relaxation over and above the upper age limit prescribed for General (UR) category candidates.
                    </p>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                        What is the age relaxation for SC/ST and PwBD candidates?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        SC and ST candidates receive 5 years of upper age limit relaxation. PwBD (General) candidates get 10 years, PwBD (OBC-NCL) candidates get 13 years, and PwBD (SC/ST) candidates receive up to 15 years of age relaxation in central government recruitments.
                    </p>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem 1.25rem;">
                    <h3 style="font-size: 0.975rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                        What is the age limit for UPSC Civil Services Exam 2026?
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.6; margin: 0;">
                        For the UPSC Civil Services Examination (CSE), candidates must be at least 21 years old and must not have attained the age of 32 years as on 1st August of the exam year for General/EWS category. The upper age limit is 35 years for OBC (with 9 attempts) and 37 years for SC/ST (with unlimited attempts).
                    </p>
                </div>

            </div>
        </div>

    </div>
</main>

<style>
@media (max-width: 860px) {
    .calc-grid-layout {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Pure Client-Side Accurate Age Engine -->
<script>
const EXAM_LIMITS = {
    'custom': { min: 18, max: 30, name: 'Custom Target' },
    'ssc_cgl_27': { min: 18, max: 27, name: 'SSC CGL (18–27 Years Posts)' },
    'ssc_cgl_30': { min: 18, max: 30, name: 'SSC CGL (18–30 Years Posts)' },
    'ssc_cgl_32': { min: 18, max: 32, name: 'SSC CGL (JSO 18–32 Years)' },
    'ssc_chsl': { min: 18, max: 27, name: 'SSC CHSL (10+2)' },
    'upsc_cse': { min: 21, max: 32, name: 'UPSC Civil Services (CSE)' },
    'ibps_po': { min: 20, max: 30, name: 'IBPS PO / MT' },
    'ibps_clerk': { min: 20, max: 28, name: 'IBPS Clerk' },
    'sbi_po': { min: 21, max: 30, name: 'SBI PO' },
    'rrb_ntpc': { min: 18, max: 33, name: 'RRB NTPC (Graduate)' },
    'nda': { min: 16.5, max: 19.5, name: 'NDA & NA' },
    'cds': { min: 19, max: 24, name: 'CDS Examination' }
};

// Set default dates
document.addEventListener('DOMContentLoaded', function() {
    const dobEl = document.getElementById('dobInput');
    const asOnEl = document.getElementById('asOnInput');
    
    // Default DOB: 15 July 2000
    if (!dobEl.value) {
        dobEl.value = '2000-07-15';
    }
    // Default As-On: 01 August 2026 (Standard SSC/UPSC Cutoff)
    if (!asOnEl.value) {
        asOnEl.value = '2026-08-01';
    }
    
    calculateAge();
});

function setAsOnToday() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    document.getElementById('asOnInput').value = `${yyyy}-${mm}-${dd}`;
    calculateAge();
}

function setAsOnDate(dateStr) {
    document.getElementById('asOnInput').value = dateStr;
    calculateAge();
}

function calculateAge() {
    const dobVal = document.getElementById('dobInput').value;
    const asOnVal = document.getElementById('asOnInput').value;
    const examVal = document.getElementById('examSelect').value;
    const catVal = document.getElementById('categorySelect').value;

    if (!dobVal || !asOnVal) return;

    const dob = new Date(dobVal);
    const asOn = new Date(asOnVal);

    if (dob > asOn) {
        document.getElementById('primaryAgeText').innerHTML = '<span style="color: #b91c1c; font-size: 1.1rem;">DOB cannot be after Cutoff Date</span>';
        document.getElementById('primaryAgeSub').textContent = 'Please verify your entered dates.';
        document.getElementById('eligibilityCard').style.display = 'none';
        document.getElementById('totalMonthsText').textContent = '—';
        document.getElementById('totalWeeksText').textContent = '—';
        document.getElementById('totalDaysText').textContent = '—';
        document.getElementById('nextBdayText').textContent = '—';
        return;
    }

    // Exact calendar calculation
    let years = asOn.getFullYear() - dob.getFullYear();
    let months = asOn.getMonth() - dob.getMonth();
    let days = asOn.getDate() - dob.getDate();

    if (days < 0) {
        months -= 1;
        const prevMonthDate = new Date(asOn.getFullYear(), asOn.getMonth(), 0);
        days += prevMonthDate.getDate();
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    // Display primary text
    document.getElementById('primaryAgeText').textContent = `${years} Years, ${months} Months, ${days} Days`;
    document.getElementById('primaryAgeSub').textContent = `Accurate as on ${formatDateDisplay(asOn)}`;

    // Total differences
    const diffMs = asOn - dob;
    const totalDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const totalMonths = (years * 12) + months;
    const totalWeeks = Math.floor(totalDays / 7);

    document.getElementById('totalMonthsText').textContent = totalMonths.toLocaleString();
    document.getElementById('totalWeeksText').textContent = totalWeeks.toLocaleString();
    document.getElementById('totalDaysText').textContent = totalDays.toLocaleString();

    // Next Birthday countdown
    const today = new Date();
    let nextBday = new Date(today.getFullYear(), dob.getMonth(), dob.getDate());
    if (nextBday < today) {
        nextBday.setFullYear(today.getFullYear() + 1);
    }
    const daysToBday = Math.ceil((nextBday - today) / (1000 * 60 * 60 * 24));
    document.getElementById('nextBdayText').textContent = daysToBday === 0 ? 'Today!' : `${daysToBday} Days`;

    // Eligibility check
    const exam = EXAM_LIMITS[examVal] || EXAM_LIMITS['custom'];
    let relaxation = 0;
    if (catVal === '3' || catVal === '3_esm') relaxation = 3;
    else if (catVal === '5') relaxation = 5;
    else if (catVal === '10') relaxation = 10;
    else if (catVal === '13') relaxation = 13;
    else if (catVal === '15') relaxation = 15;

    const allowedMax = exam.max + relaxation;
    const allowedMin = exam.min;
    const ageDecimal = years + (months / 12) + (days / 365.25);

    const elCard = document.getElementById('eligibilityCard');
    elCard.style.display = 'block';

    if (ageDecimal >= allowedMin && ageDecimal <= allowedMax) {
        elCard.style.background = '#f0fdf4';
        elCard.style.border = '1px solid #bbf7d0';
        elCard.style.color = '#15803d';
        elCard.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; margin-bottom: 4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>ELIGIBLE for ${exam.name}</span>
            </div>
            <div style="font-size: 0.825rem; color: #166534;">
                Permissible Age: <strong>${allowedMin} to ${allowedMax} Years</strong> (including ${relaxation} yrs category relaxation). Your age of ${years}y ${months}m ${days}d is within the statutory limit.
            </div>
        `;
    } else if (ageDecimal > allowedMax) {
        const overYears = Math.floor(ageDecimal - allowedMax);
        elCard.style.background = '#fef2f2';
        elCard.style.border = '1px solid #fecaca';
        elCard.style.color = '#b91c1c';
        elCard.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; margin-bottom: 4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>OVERAGE for ${exam.name}</span>
            </div>
            <div style="font-size: 0.825rem; color: #991b1b;">
                Maximum Allowed Age: <strong>${allowedMax} Years</strong> (with relaxation). Your calculated age exceeds the upper limit.
            </div>
        `;
    } else {
        elCard.style.background = '#fffbeb';
        elCard.style.border = '1px solid #fde68a';
        elCard.style.color = '#92400e';
        elCard.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; margin-bottom: 4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>UNDERAGE for ${exam.name}</span>
            </div>
            <div style="font-size: 0.825rem; color: #78350f;">
                Minimum Age Required: <strong>${allowedMin} Years</strong> as on cutoff date.
            </div>
        `;
    }
}

function formatDateDisplay(dateObj) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${String(dateObj.getDate()).padStart(2, '0')} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;
}

function resetForm() {
    document.getElementById('dobInput').value = '2000-07-15';
    document.getElementById('asOnInput').value = '2026-08-01';
    document.getElementById('examSelect').value = 'ssc_cgl_27';
    document.getElementById('categorySelect').value = '0';
    calculateAge();
}

function copyAgeSummary() {
    const ageText = document.getElementById('primaryAgeText').textContent;
    const asOnText = document.getElementById('primaryAgeSub').textContent;
    const summary = `Age as calculated on Sarkari.online: ${ageText} (${asOnText})`;
    
    navigator.clipboard.writeText(summary).then(function() {
        const btnText = document.getElementById('copyBtnText');
        btnText.textContent = 'Copied to Clipboard!';
        setTimeout(function() {
            btnText.textContent = 'Copy Age Summary for Application Form';
        }, 2500);
    });
}
</script>

<!-- Rich JSON-LD Schemas -->
<script type="application/ld+json">
<?= json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
