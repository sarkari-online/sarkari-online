<?php
/**
 * Sarkari.online - Student Utilities & Examination Calculators Hub
 * Professional institutional UI with native SVG iconography.
 */
require_once dirname(__DIR__) . '/config.php';

$pageTitle = 'Student Utilities & Exam Calculators 2026 — 7th Pay Salary & CGPA Converter';
$pageDesc = 'Free interactive online utility calculators for Indian competitive exam aspirants and students. Calculate 7th Pay Commission in-hand salary, DA 50%, HRA, and convert CGPA to percentage.';
$pageKeywords = 'sarkari tools, exam calculators, 7th pay commission calculator, cgpa to percentage converter, cbse percentage calculator, in hand salary calculator';
$canonicalUrl = url('tools/');

$crumbs = [
    ['label' => 'Home', 'url' => url()],
    ['label' => 'Student Tools', 'url' => $canonicalUrl]
];

include dirname(__DIR__) . '/components/head.php';
include dirname(__DIR__) . '/components/header.php';
?>

<main class="site-main" style="padding: 2.5rem 0 5rem 0; background: #f8fafc;">
    <div class="container">
        
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
            <ol class="breadcrumb-list" style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li style="color: #0f172a; font-weight: 600;">Student Tools</li>
            </ol>
        </nav>

        <!-- Professional Institutional Header -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem 2.5rem; margin-bottom: 2.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="max-width: 820px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; margin-bottom: 1rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Statutory Computation Engines</span>
                </div>
                <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.75rem 0; color: #0f172a; letter-spacing: -0.02em;">
                    Student Utilities &amp; Examination Calculators
                </h1>
                <p style="font-size: 0.95rem; color: #64748b; line-height: 1.6; margin: 0;">
                    Accurate, statutory-compliant calculation tools engineered for candidates preparing for UPSC, SSC, Railways, Banking, State PSCs, and Board/University evaluations.
                </p>
            </div>
        </div>

        <!-- Tool Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.75rem; margin-bottom: 3rem;">
            
            <!-- Card 1: 7th Pay Commission Salary Calculator -->
            <div style="background: #ffffff; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease-in-out;" onmouseover="this.style.borderColor='#0284c7'; this.style.boxShadow='0 8px 20px rgba(2,132,199,0.08)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.05)';">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                        <div style="width: 48px; height: 48px; border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; color: #16a34a;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <span style="font-size: 0.72rem; font-weight: 700; color: #15803d; background: #dcfce7; padding: 3px 8px; border-radius: 4px; border: 1px solid #bbf7d0;">
                            7th CPC Matrix &middot; 50% DA
                        </span>
                    </div>

                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.6rem 0; line-height: 1.35;">
                        7th Pay Commission Salary &amp; In-Hand Pay Calculator
                    </h2>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem 0;">
                        Calculate post-wise monthly in-hand net salary, gross package, 50% DA, HRA (X, Y, Z cities), TA, and mandatory NPS deductions for SSC CGL, RRB NTPC, UPSC, and Banking exams.
                    </p>
                </div>

                <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; padding: 0.75rem 1.25rem; border-radius: 8px;">
                    <span>Open Salary Calculator</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>

            <!-- Card 2: CGPA to Percentage Converter -->
            <div style="background: #ffffff; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease-in-out;" onmouseover="this.style.borderColor='#0284c7'; this.style.boxShadow='0 8px 20px rgba(2,132,199,0.08)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.05)';">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                        <div style="width: 48px; height: 48px; border-radius: 10px; background: #f0f9ff; border: 1px solid #bae6fd; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        </div>
                        <span style="font-size: 0.72rem; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 3px 8px; border-radius: 4px; border: 1px solid #bae6fd;">
                            CBSE, AICTE &amp; VTU Formulas
                        </span>
                    </div>

                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.6rem 0; line-height: 1.35;">
                        CGPA to Percentage &amp; Marks Converter
                    </h2>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem 0;">
                        Convert Cumulative Grade Point Average (CGPA) to exact percentage and equivalent calculated marks for CBSE 10th/12th, B.Tech Engineering, AKTU, and Mumbai University.
                    </p>
                </div>

                <a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; padding: 0.75rem 1.25rem; border-radius: 8px;">
                    <span>Open CGPA Converter</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>

            <!-- Card 3: Govt Job Age Calculator & Eligibility Checker -->
            <div style="background: #ffffff; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s ease-in-out;" onmouseover="this.style.borderColor='#1e3a8a'; this.style.boxShadow='0 8px 20px rgba(30,58,138,0.08)';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.05)';">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                        <div style="width: 48px; height: 48px; border-radius: 10px; background: #eff6ff; border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #1e3a8a;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <span style="font-size: 0.72rem; font-weight: 700; color: #1e40af; background: #dbeafe; padding: 3px 8px; border-radius: 4px; border: 1px solid #bfdbfe;">
                            DoPT Rules &middot; All Categories
                        </span>
                    </div>

                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.6rem 0; line-height: 1.35;">
                        Govt Job Age Calculator &amp; Eligibility Checker
                    </h2>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem 0;">
                        Calculate exact age in years, months, and days as on notification cutoff date. Check age eligibility and category relaxation (UR, OBC, SC, ST, PwBD) for UPSC, SSC, and Banking forms.
                    </p>
                </div>

                <a href="<?= url('tools/age-calculator/') ?>" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; padding: 0.75rem 1.25rem; border-radius: 8px;">
                    <span>Open Age Calculator</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>

        </div>

        <!-- Trust and Institutional Verification Strip -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a;">Statutory Compliance Guarantee</div>
                    <div style="font-size: 0.75rem; color: #64748b;">All formulas mapped strictly to Ministry of Finance Gazette &amp; University Ordinances.</div>
                </div>
            </div>
            <div style="font-size: 0.8125rem; font-weight: 600; color: #475569;">
                Updated for Academic &amp; Fiscal Year 2026–2027
            </div>
        </div>

    </div>
</main>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
