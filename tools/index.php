<?php
/**
 * Sarkari.online - Student Utilities & Examination Calculators Hub
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

<main class="site-main" style="padding: 2rem 0 4rem 0; background: #f8fafc;">
    <div class="container">
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.25rem;">
            <ol class="breadcrumb-list" style="display: flex; gap: 0.5rem; list-style: none; padding: 0; margin: 0; font-size: 0.8125rem; color: #64748b;">
                <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none;">Home</a> <span style="margin: 0 0.35rem; color: #cbd5e1;">/</span></li>
                <li style="color: #1e293b; font-weight: 600;">Student Tools</li>
            </ol>
        </nav>

        <!-- Hub Header Banner -->
        <div style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); color: #ffffff; border-radius: 16px; padding: 2.25rem 2.5rem; margin-bottom: 2.5rem; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);">
            <div style="max-width: 800px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; background: rgba(56, 189, 248, 0.2); border: 1px solid rgba(56, 189, 248, 0.4); color: #7dd3fc; margin-bottom: 0.85rem;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #38bdf8;"></span>
                    Free Interactive Student Toolkits
                </div>
                <h1 style="font-size: 2rem; font-weight: 800; line-height: 1.3; margin: 0 0 0.85rem 0; color: #ffffff;">
                    Interactive Examination &amp; Career Utility Tools
                </h1>
                <p style="font-size: 1rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    Accurate, statutory-compliant calculation engines designed for competitive exam aspirants, college students, and government job applicants across India.
                </p>
            </div>
        </div>

        <!-- Tool Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.75rem; margin-bottom: 3rem;">
            
            <!-- Card 1: 7th Pay Commission Calculator -->
            <div style="background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.06)';">
                <div>
                    <div style="width: 52px; height: 52px; border-radius: 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; font-size: 1.5rem;">
                        💰
                    </div>
                    <span style="display: inline-block; font-size: 0.72rem; font-weight: 800; color: #059669; background: #dcfce7; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; margin-bottom: 0.5rem;">
                        7th CPC &middot; 50% DA Revised
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.6rem 0;">
                        7th Pay Commission Salary &amp; In-Hand Pay Calculator
                    </h2>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem 0;">
                        Calculate post-wise monthly in-hand net salary, gross package, 50% DA, HRA (X, Y, Z cities), TA, and NPS deductions for SSC CGL, RRB, UPSC, and Banking exams.
                    </p>
                </div>
                <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>Open Salary Calculator</span>
                    <?= icon('chevron-right', 'icon-xs') ?>
                </a>
            </div>

            <!-- Card 2: CGPA to Percentage Converter -->
            <div style="background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.06)';">
                <div>
                    <div style="width: 52px; height: 52px; border-radius: 12px; background: #e0f2fe; border: 1px solid #bae6fd; color: #0284c7; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; font-size: 1.5rem;">
                        🎓
                    </div>
                    <span style="display: inline-block; font-size: 0.72rem; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; margin-bottom: 0.5rem;">
                        CBSE, AICTE, AKTU &amp; VTU Formulas
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.6rem 0;">
                        CGPA to Percentage &amp; Marks Converter
                    </h2>
                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem 0;">
                        Convert Cumulative Grade Point Average (CGPA) to percentage and calculate marks obtained for CBSE 10th/12th, B.Tech Engineering, AKTU, and Mumbai University.
                    </p>
                </div>
                <a href="<?= url('tools/cgpa-to-percentage-calculator/') ?>" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>Open CGPA Converter</span>
                    <?= icon('chevron-right', 'icon-xs') ?>
                </a>
            </div>

        </div>

    </div>
</main>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
