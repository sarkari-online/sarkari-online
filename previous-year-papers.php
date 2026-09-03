<?php
/**
 * Sarkari.online - Previous Year Question Papers & Official Answer Key Hub
 * High-authority, verified academic repository for UPSC, SSC, Railways, Banking & Defence papers.
 */

require_once __DIR__ . '/config.php';

use App\Services\QuestionPaperService;
use App\Helpers\Sanitizer;

$examSlug = Sanitizer::string($_GET['exam'] ?? '');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
$filterType = Sanitizer::string($_GET['type'] ?? '');
$searchQuery = Sanitizer::string($_GET['q'] ?? '');

$allExams = QuestionPaperService::getAllExams();
$examMap = [];
foreach ($allExams as $e) {
    $examMap[$e['exam_slug']] = $e;
}

$currentExam = null;
if (!empty($examSlug)) {
    if (isset($examMap[$examSlug])) {
        $currentExam = $examMap[$examSlug];
    } else {
        // Unknown exam slug -> redirect or 404
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
}

// Fetch papers
if ($currentExam) {
    $papers = QuestionPaperService::getPapersByExam($examSlug, $filterYear, $filterType);
    $pageTitle = "{$currentExam['exam_name']} Previous Year Question Papers & Official Answer Keys PDF";
    $metaDesc = "Download official {$currentExam['exam_name']} master question papers and final answer keys PDF free. Verified academic archive compiled from {$currentExam['conducting_body']} public records.";
} else {
    $papers = QuestionPaperService::getAllPapers($examSlug ?: null, $filterYear, $filterType ?: null, $searchQuery ?: null);
    $pageTitle = "Previous Year Question Papers & Official Answer Keys Archive 2026 | Sarkari.online";
    $metaDesc = "Free download verified official previous year question papers (PYQs) and master answer keys for UPSC, SSC CGL, RRB NTPC, IBPS PO, and NDA. 100% authentic statutory data.";
}

$canonicalUrl = url($currentExam ? 'previous-year-papers/' . $examSlug . '/' : 'previous-year-papers/');
$years = QuestionPaperService::getDistinctYears();
$schemaJson = json_encode(QuestionPaperService::generateSchema($papers, $examSlug, $currentExam), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main">
    <div class="container" style="max-width: 1200px; margin: 1.5rem auto 3rem auto; padding: 0 1rem;">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.25rem; font-size: 0.875rem; color: var(--text-muted);">
        <ol style="display: flex; flex-wrap: wrap; list-style: none; padding: 0; margin: 0; gap: 0.5rem; align-items: center;">
            <li><a href="<?= url() ?>" style="color: var(--color-primary); text-decoration: none;">Home</a></li>
            <li><span style="color: var(--border-color);">&gt;</span></li>
            <?php if ($currentExam): ?>
                <li><a href="<?= url('previous-year-papers/') ?>" style="color: var(--color-primary); text-decoration: none;">Question Papers</a></li>
                <li><span style="color: var(--border-color);">&gt;</span></li>
                <li style="color: var(--text-main); font-weight: 600;"><?= e($currentExam['exam_name']) ?></li>
            <?php else: ?>
                <li style="color: var(--text-main); font-weight: 600;">Previous Year Question Papers</li>
            <?php endif; ?>
        </ol>
    </nav>

    <!-- Header Banner -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1.25rem;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.65rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>100% Authentic Statutory Archive</span>
                </div>
                <h1 style="font-size: 1.625rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; line-height: 1.3;">
                    <?= $currentExam ? e($currentExam['exam_name']) . ' Question Papers &amp; Answer Keys' : 'Previous Year Question Papers &amp; Official Answer Keys' ?>
                </h1>
                <p style="font-size: 0.9375rem; color: var(--text-muted); margin: 0; max-width: 800px; line-height: 1.5;">
                    <?= $currentExam 
                        ? 'Download official master question papers and final verified answer keys released by ' . e($currentExam['conducting_body']) . ' for candidate practice and self-evaluation.'
                        : 'Access genuine, verified master question papers and official answer keys for UPSC, SSC, Railways, Banking, and Defence examinations without third-party redirects.' 
                    ?>
                </p>
            </div>

            <?php if (!$currentExam): ?>
                <div style="display: flex; gap: 1.5rem; border-left: 1px solid var(--border-color); padding-left: 1.5rem;">
                    <div style="text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: var(--color-primary); display: block; line-height: 1;">12+</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Verified Papers</span>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: #16a34a; display: block; line-height: 1;">100%</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Direct PDF</span>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); display: block; line-height: 1;">Free</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Zero Redirection</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$currentExam): ?>
        <!-- Examination Hub Category Grid -->
        <div style="margin-bottom: 2.25rem;">
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Browse by Examination Commission</span>
            </h2>

            <div class="grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <?php foreach ($allExams as $exam): ?>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; transition: transform 0.15s ease, box-shadow 0.15s ease; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                <?= e($exam['conducting_body']) ?>
                            </span>
                            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700;">
                                <?= (int)$exam['latest_year'] ?> Edition
                            </span>
                        </div>

                        <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0 0 0.5rem 0; line-height: 1.35;">
                            <a href="<?= url('previous-year-papers/' . $exam['exam_slug'] . '/') ?>" style="color: var(--text-main); text-decoration: none;">
                                <?= e($exam['exam_name']) ?>
                            </a>
                        </h3>

                        <div style="display: flex; gap: 1rem; font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1rem;">
                            <span>📄 <?= (int)$exam['question_paper_count'] ?> Question Papers</span>
                            <span>🔑 <?= (int)$exam['answer_key_count'] ?> Answer Keys</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; pt: 0.75rem; border-top: 1px solid var(--border-color);">
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                <?= number_format((int)$exam['total_downloads']) ?> downloads
                            </span>
                            <a href="<?= url('previous-year-papers/' . $exam['exam_slug'] . '/') ?>" style="font-size: 0.8125rem; font-weight: 700; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <span>View Archive</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Bar & Search Box -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
        
        <!-- Filter Form -->
        <form method="GET" action="<?= url($currentExam ? 'previous-year-papers/' . $examSlug . '/' : 'previous-year-papers/') ?>" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; flex: 1;">
            
            <?php if (!$currentExam): ?>
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search papers e.g. SSC CGL, GS Paper 1..." style="padding: 0.5rem 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.875rem; min-width: 260px; flex: 1;">
            <?php endif; ?>

            <select name="type" style="padding: 0.5rem 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.875rem; background: #ffffff;">
                <option value="">All Document Types</option>
                <option value="question_paper" <?= $filterType === 'question_paper' ? 'selected' : '' ?>>Question Papers Only</option>
                <option value="answer_key" <?= $filterType === 'answer_key' ? 'selected' : '' ?>>Official Answer Keys Only</option>
            </select>

            <select name="year" style="padding: 0.5rem 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.875rem; background: #ffffff;">
                <option value="">All Years</option>
                <?php foreach ($years as $yr): ?>
                    <option value="<?= (int)$yr ?>" <?= $filterYear === (int)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" style="padding: 0.5rem 1rem; background: var(--color-primary); color: #ffffff; border: none; border-radius: var(--radius-sm); font-size: 0.875rem; font-weight: 700; cursor: pointer;">
                Filter
            </button>

            <?php if ($filterYear || $filterType || $searchQuery): ?>
                <a href="<?= url($currentExam ? 'previous-year-papers/' . $examSlug . '/' : 'previous-year-papers/') ?>" style="font-size: 0.8125rem; color: #dc2626; text-decoration: none; font-weight: 600; padding: 0.5rem;">
                    Clear Filters
                </a>
            <?php endif; ?>
        </form>

        <span style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600;">
            Showing <?= count($papers) ?> Verified Papers
        </span>
    </div>

    <!-- Question Papers Table / Cards -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
        <?php if (empty($papers)): ?>
            <div style="padding: 3rem 1.5rem; text-align: center;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 0.75rem;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">No Papers Found</h3>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">Try clearing your filters or selecting a different year.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); color: #475569; font-weight: 700; font-size: 0.8125rem;">
                            <th style="padding: 0.85rem 1.25rem;">YEAR &amp; STAGE</th>
                            <th style="padding: 0.85rem 1.25rem;">DOCUMENT TITLE</th>
                            <th style="padding: 0.85rem 1.25rem;">TYPE</th>
                            <th style="padding: 0.85rem 1.25rem;">DETAILS</th>
                            <th style="padding: 0.85rem 1.25rem; text-align: right;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($papers as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.1s ease;">
                                
                                <!-- Year & Stage -->
                                <td style="padding: 1rem 1.25rem; vertical-align: middle; white-space: nowrap;">
                                    <span style="font-weight: 800; font-size: 0.9375rem; color: var(--text-main); display: block;">
                                        <?= (int)$p['year'] ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                                        <?= e($p['tier_stage']) ?>
                                    </span>
                                </td>

                                <!-- Title -->
                                <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                    <h4 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 0.25rem 0; color: var(--text-main); line-height: 1.35;">
                                        <?= e($p['paper_title']) ?>
                                    </h4>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 0.75rem; align-items: center;">
                                        <span>Authority: <?= e($p['conducting_body']) ?></span>
                                        <?php if (!empty($p['shift_session'])): ?>
                                            <span>• <?= e($p['shift_session']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Type Badge -->
                                <td style="padding: 1rem 1.25rem; vertical-align: middle; white-space: nowrap;">
                                    <?php if ($p['file_type'] === 'answer_key'): ?>
                                        <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 0.725rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span>Official Answer Key</span>
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 0.725rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <span>Question Paper</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Details -->
                                <td style="padding: 1rem 1.25rem; vertical-align: middle; white-space: nowrap; font-size: 0.8125rem; color: var(--text-muted);">
                                    <div><strong><?= (int)$p['total_questions'] ?></strong> MCQs</div>
                                    <div style="font-size: 0.75rem;"><?= e($p['file_size']) ?> • <?= number_format((int)$p['download_count']) ?> hits</div>
                                </td>

                                <!-- Download Action -->
                                <td style="padding: 1rem 1.25rem; vertical-align: middle; text-align: right; white-space: nowrap;">
                                    <a href="<?= url('download/paper/' . $p['id'] . '/') ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 0.55rem 1.15rem; background: var(--color-primary); color: #ffffff; border-radius: var(--radius-sm); font-size: 0.8125rem; font-weight: 700; text-decoration: none; transition: opacity 0.15s ease;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        <span>Download PDF</span>
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statutory Notice & Legal Disclaimer Box -->
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: var(--radius-lg); padding: 1.5rem 1.75rem; margin-bottom: 2.5rem;">
        <div style="display: flex; gap: 1rem; align-items: flex-start;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #475569; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div>
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
                    Official Statutory Disclaimer &amp; Fair-Use Educational Notice
                </h3>
                <p style="font-size: 0.8125rem; color: #64748b; line-height: 1.5; margin: 0;">
                    All question papers and official answer keys indexed on Sarkari.online are compiled strictly from publicly published master documents released by statutory commissions (UPSC, SSC, NTA, RRB, IBPS). These documents are provided solely for non-commercial educational reference, self-evaluation, and candidate syllabus preparation under fair-use principles. All logos, trademarks, and formulations remain the exclusive property of their respective statutory bodies. Sarkari.online is an independent candidate information portal.
                </p>
            </div>
        </div>
    </div>

    <!-- Frequently Asked Questions (FAQ) Section -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
        <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>Frequently Asked Questions</span>
        </h2>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 1rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.35rem 0;">Are these question papers and answer keys official?</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.45;">Yes. Every paper and answer key is curated directly from the master response sheets and final evaluation keys released officially by the respective commissions (UPSC, SSC, RRB, IBPS).</p>
            </div>

            <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 1rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.35rem 0;">Do I need to register or pay to download the PDFs?</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.45;">No. All educational documents are 100% free with 1-click direct download. No login, mobile number, or registration is required.</p>
            </div>

            <div>
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.35rem 0;">How can solving previous year question papers help in exam preparation?</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.45;">Solving actual past papers helps candidates understand question framing trends, sectional weightage, negative marking accuracy, and exact time management needed for CBT and written examinations.</p>
            </div>
        </div>
    </div>

    </div>
</main>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
