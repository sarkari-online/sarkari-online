<?php
declare(strict_types=1);

/**
 * Sarkari.online - Full-Forms Directory SEO & Content Depth Auditor (Phase A)
 *
 * Scans all full-form detail pages (/full-forms/{slug}/) across 5 critical dimensions:
 * 1. Meta-Content Mismatch (Salary, Pay, Selection, Eligibility, Syllabus promised vs present)
 * 2. Meta-Title Answer-Leak Check (Zero-click spoiler risk in SERP snippet)
 * 3. Content Depth Check (Body word count; < 500 = THIN_CONTENT)
 * 4. Schema Verification (DefinedTerm / FAQPage JSON-LD)
 * 5. Internal Linking Check (Links to live articles/categories outside related acronyms)
 *
 * Outputs CSV and JSON reports in storage/reports/ and prints executive summary.
 *
 * Usage:
 *   php cron/audit-full-forms-seo.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GlossaryService;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "🔍 SARKARI.ONLINE — FULL-FORMS SEO & CONTENT DEPTH AUDITOR (PHASE A)\n";
echo "================================================================================\n\n";

// Ensure reports directory exists
$reportsDir = dirname(__DIR__) . '/storage/reports';
if (!is_dir($reportsDir)) {
    @mkdir($reportsDir, 0755, true);
}

// -----------------------------------------------------------------------------
// 1. FETCH ALL FULL-FORM TERMS
// -----------------------------------------------------------------------------
try {
    $terms = Database::fetchAll("SELECT * FROM glossary_terms ORDER BY acronym ASC");
} catch (\Throwable $e) {
    echo "❌ Failed to query glossary_terms: " . $e->getMessage() . "\n";
    exit(1);
}

$totalTerms = count($terms);
if ($totalTerms === 0) {
    echo "⚠️ No glossary terms found in database.\n";
    exit(0);
}

echo "Found {$totalTerms} full-form entries in database. Beginning deep forensic audit...\n\n";

// -----------------------------------------------------------------------------
// 2. AUDIT EXECUTION
// -----------------------------------------------------------------------------
$auditResults = [];
$stats = [
    'total' => $totalTerms,
    'salary_promised' => 0,
    'salary_present' => 0,
    'salary_mismatch' => 0,
    'any_mismatch' => 0,
    'leak_risk' => 0,
    'thin_content' => 0, // < 500 words
    'depth_500_plus' => 0, // >= 500 words
    'total_word_count' => 0,
    'has_defined_term_schema' => 0,
    'has_faq_schema' => 0,
    'has_any_schema' => 0,
    'zero_internal_links' => 0,
    'has_internal_links' => 0,
];

foreach ($terms as $term) {
    $slug = (string)$term['slug'];
    $acronym = (string)$term['acronym'];
    $fullEn = (string)$term['full_form_en'];

    // A. Generate Meta Title & Meta Description as output by GlossaryService
    $metaTitle = GlossaryService::generateMetaTitle($term);
    $metaDesc  = GlossaryService::generateMetaDescription($term);
    $canonicalUrl = url("full-forms/{$slug}/");
    $hubUrl       = url('full-forms/');

    // B. Assemble rendered page body (strictly inside <article>, excluding nav/header/footer/related acronyms)
    $directAnswerHtml = GlossaryService::renderDirectAnswerBlock($term);
    $factsTableHtml   = GlossaryService::renderFactsTable($term);

    $sectionsHtml = '';
    $sectionsHtml .= "<h2>1. Official Mandate, Scope & Background</h2>\n<p>" . nl2br(htmlspecialchars($term['overview'] ?? '')) . "</p>\n";
    if (!empty($term['eligibility_criteria'])) {
        $sectionsHtml .= "<h2>2. Eligibility Criteria, Qualifications & Age Limits</h2>\n<p>" . nl2br(htmlspecialchars($term['eligibility_criteria'])) . "</p>\n";
    }
    if (!empty($term['selection_process'])) {
        $sectionsHtml .= "<h2>3. Examination Scheme & Selection Procedure</h2>\n<p>" . nl2br(htmlspecialchars($term['selection_process'])) . "</p>\n";
    }
    if (!empty($term['syllabus_snapshot'])) {
        $sectionsHtml .= "<h2>4. Core Syllabus & Key Subjects</h2>\n<p>" . nl2br(htmlspecialchars($term['syllabus_snapshot'])) . "</p>\n";
    }

    $relatedArticleHtml = '';
    if (!empty($term['related_article_slug'])) {
        $relatedArticleHtml = '<a href="' . url('article/' . $term['related_article_slug'] . '/') . '">Check Verified 2026 Examination Schedule & Application Guide</a>';
    }

    $bodyHtml = "<h1>Full Form of {$acronym}</h1>\n"
              . $directAnswerHtml . "\n"
              . $factsTableHtml . "\n"
              . $sectionsHtml . "\n"
              . $relatedArticleHtml;

    // C. Word Count Calculation (Plain body text only)
    $plainBodyText = strip_tags($bodyHtml);
    // Replace non-word whitespace characters
    $cleanWordsText = preg_replace('/\s+/u', ' ', trim($plainBodyText));
    $wordCount = count(preg_split('/\s+/u', $cleanWordsText, -1, PREG_SPLIT_NO_EMPTY));
    $stats['total_word_count'] += $wordCount;

    $isThinContent = ($wordCount < 500);
    if ($isThinContent) {
        $stats['thin_content']++;
    } else {
        $stats['depth_500_plus']++;
    }

    // D. Check 1: Meta-Content Mismatch
    // Check Salary / Pay promise
    $salaryPromised = (bool)preg_match('/\b(salary|pay scale|pay level|7th cpc|remuneration|stipend)\b/i', $metaTitle . ' ' . $metaDesc);
    $salaryContentPresent = (bool)preg_match('/\b(salary|pay scale|pay level|in-hand pay|basic pay|7th cpc|remuneration|stipend)\b/i', $bodyHtml);
    $salaryMismatch = ($salaryPromised && !$salaryContentPresent);

    if ($salaryPromised) $stats['salary_promised']++;
    if ($salaryContentPresent) $stats['salary_present']++;
    if ($salaryMismatch) $stats['salary_mismatch']++;

    // Check Eligibility promise
    $eligibilityPromised = (bool)preg_match('/\b(eligibility|age limit|qualification)\b/i', $metaTitle . ' ' . $metaDesc);
    $eligibilityContentPresent = !empty($term['eligibility_criteria']);
    $eligibilityMismatch = ($eligibilityPromised && !$eligibilityContentPresent);

    // Check Selection promise
    $selectionPromised = (bool)preg_match('/\b(selection|exam scheme|procedure)\b/i', $metaTitle . ' ' . $metaDesc);
    $selectionContentPresent = !empty($term['selection_process']);
    $selectionMismatch = ($selectionPromised && !$selectionContentPresent);

    // Check Syllabus promise
    $syllabusPromised = (bool)preg_match('/\b(syllabus|subjects)\b/i', $metaTitle . ' ' . $metaDesc);
    $syllabusContentPresent = !empty($term['syllabus_snapshot']);
    $syllabusMismatch = ($syllabusPromised && !$syllabusContentPresent);

    $hasAnyMismatch = ($salaryMismatch || $eligibilityMismatch || $selectionMismatch || $syllabusMismatch);
    if ($hasAnyMismatch) {
        $stats['any_mismatch']++;
    }

    $mismatchFlags = [];
    if ($salaryMismatch)      $mismatchFlags[] = 'NO_SALARY_IN_BODY';
    if ($eligibilityMismatch) $mismatchFlags[] = 'NO_ELIGIBILITY_IN_BODY';
    if ($selectionMismatch)   $mismatchFlags[] = 'NO_SELECTION_IN_BODY';
    if ($syllabusMismatch)    $mismatchFlags[] = 'NO_SYLLABUS_IN_BODY';

    // E. Check 2: Meta-Title Answer-Leak Check
    // A leak occurs if the meta-title directly provides the full expansion in the SERP
    $leakRisk = false;
    $leakReason = '';

    if (stripos($metaTitle, $fullEn) !== false) {
        $leakRisk = true;
        $leakReason = "Meta-title directly leaks full form: '{$fullEn}'";
    } elseif (preg_match('/\b(?:full form is|stands for)\s+' . preg_quote($fullEn, '/') . '/i', $metaTitle . ' ' . $metaDesc)) {
        $leakRisk = true;
        $leakReason = "Snippet directly answers full form: '{$fullEn}'";
    }

    if ($leakRisk) {
        $stats['leak_risk']++;
    }

    // F. Check 3: Schema Verification
    $schemaJson = GlossaryService::generateDefinedTermSchema($term, $canonicalUrl, $hubUrl);
    $hasDefinedTerm = str_contains($schemaJson, '"@type": "DefinedTerm"') || str_contains($schemaJson, '"@type":"DefinedTerm"');
    $hasFaqPage     = str_contains($schemaJson, '"@type": "FAQPage"')     || str_contains($schemaJson, '"@type":"FAQPage"');
    $hasSchema      = ($hasDefinedTerm || $hasFaqPage);

    if ($hasDefinedTerm) $stats['has_defined_term_schema']++;
    if ($hasFaqPage)     $stats['has_faq_schema']++;
    if ($hasSchema)      $stats['has_any_schema']++;

    // G. Check 4: Internal Linking Check
    // Count internal links to live articles/categories outside of related acronyms
    $internalLinksCount = 0;
    if (!empty($term['related_article_slug'])) {
        $internalLinksCount++;
    }
    // Also scan body text for any embedded <a href="..."> pointing to articles/categories
    preg_match_all('/href=["\'](?:https?:\/\/[^\/]+)?\/(article|category|exam-dates|results)\/([^"\']+)["\']/i', $bodyHtml, $linkMatches);
    $internalLinksCount += count($linkMatches[0] ?? []);

    if ($internalLinksCount > 0) {
        $stats['has_internal_links']++;
    } else {
        $stats['zero_internal_links']++;
    }

    $auditResults[] = [
        'slug'                   => $slug,
        'acronym'                => $acronym,
        'full_form_en'           => $fullEn,
        'category'               => $term['category'] ?? '',
        'meta_title'             => $metaTitle,
        'meta_desc'              => $metaDesc,
        'salary_promised'        => $salaryPromised,
        'salary_content_present' => $salaryContentPresent,
        'salary_mismatch'        => $salaryMismatch,
        'eligibility_mismatch'   => $eligibilityMismatch,
        'selection_mismatch'     => $selectionMismatch,
        'syllabus_mismatch'      => $syllabusMismatch,
        'has_any_mismatch'       => $hasAnyMismatch,
        'mismatch_flags'         => implode('|', $mismatchFlags),
        'word_count'             => $wordCount,
        'is_thin_content'        => $isThinContent,
        'has_schema'             => $hasSchema,
        'has_defined_term'       => $hasDefinedTerm,
        'has_faq_schema'         => $hasFaqPage,
        'leak_risk'              => $leakRisk,
        'leak_reason'            => $leakReason,
        'internal_links_count'   => $internalLinksCount,
        'related_article_slug'   => $term['related_article_slug'] ?? '',
    ];
}

$avgWordCount = $totalTerms > 0 ? (int)round($stats['total_word_count'] / $totalTerms) : 0;

// -----------------------------------------------------------------------------
// 3. WRITE CSV & JSON REPORTS
// -----------------------------------------------------------------------------
$csvPath  = $reportsDir . '/full_forms_seo_audit.csv';
$jsonPath = $reportsDir . '/full_forms_seo_audit.json';

// Write CSV
$fp = fopen($csvPath, 'w');
fputcsv($fp, [
    'slug',
    'acronym',
    'meta_title',
    'salary_promised',
    'salary_content_present',
    'salary_mismatch',
    'word_count',
    'has_schema',
    'leak_risk',
    'internal_links_count',
    'mismatch_flags',
    'leak_reason'
]);

foreach ($auditResults as $r) {
    fputcsv($fp, [
        $r['slug'],
        $r['acronym'],
        $r['meta_title'],
        $r['salary_promised'] ? '1' : '0',
        $r['salary_content_present'] ? '1' : '0',
        $r['salary_mismatch'] ? '1' : '0',
        $r['word_count'],
        $r['has_schema'] ? '1' : '0',
        $r['leak_risk'] ? '1' : '0',
        $r['internal_links_count'],
        $r['mismatch_flags'],
        $r['leak_reason']
    ]);
}
fclose($fp);

// Write JSON
file_put_contents($jsonPath, json_encode([
    'audit_timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total_pages_scanned'     => $totalTerms,
        'meta_content_mismatches' => $stats['any_mismatch'],
        'salary_mismatches'       => $stats['salary_mismatch'],
        'answer_leak_pages'       => $stats['leak_risk'],
        'average_word_count'      => $avgWordCount,
        'pages_500_plus_words'    => $stats['depth_500_plus'],
        'thin_content_pages'      => $stats['thin_content'],
        'pages_with_schema'       => $stats['has_any_schema'],
        'pages_zero_links'        => $stats['zero_internal_links'],
    ],
    'pages' => $auditResults
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "📄 CSV Report saved to: {$csvPath}\n";
echo "📄 JSON Report saved to: {$jsonPath}\n\n";

// -----------------------------------------------------------------------------
// 4. PRINT EXECUTIVE REPORT
// -----------------------------------------------------------------------------
echo "================================================================================\n";
echo "📊 EXECUTIVE SEO AUDIT REPORT — /full-forms/ ({$totalTerms} Pages)\n";
echo "================================================================================\n";
printf("%-45s : %d / %d (%.1f%%)\n", "1. Meta-Content Mismatches (Any Promised Gap)", $stats['any_mismatch'], $totalTerms, ($stats['any_mismatch'] / $totalTerms) * 100);
printf("   └─ Promised 'Salary'/'Pay' but missing in body : %d / %d (%.1f%%)\n", $stats['salary_mismatch'], $totalTerms, ($stats['salary_mismatch'] / $totalTerms) * 100);
printf("%-45s : %d / %d (%.1f%%)\n", "2. Meta-Title Answer-Leaks (Zero-Click Spoiler)", $stats['leak_risk'], $totalTerms, ($stats['leak_risk'] / $totalTerms) * 100);
printf("%-45s : %d words\n", "3. Average Body Word Count", $avgWordCount);
printf("%-45s : %d / %d (%.1f%%)\n", "4. Baseline In-Depth Pages (500+ Words)", $stats['depth_500_plus'], $totalTerms, ($stats['depth_500_plus'] / $totalTerms) * 100);
printf("   └─ Thin Content Pages (< 500 Words)           : %d / %d (%.1f%%)\n", $stats['thin_content'], $totalTerms, ($stats['thin_content'] / $totalTerms) * 100);
printf("%-45s : %d / %d (%.1f%%)\n", "5. Schema Present (DefinedTerm JSON-LD)", $stats['has_defined_term_schema'], $totalTerms, ($stats['has_defined_term_schema'] / $totalTerms) * 100);
printf("   └─ FAQPage Schema Present                      : %d / %d (%.1f%%)\n", $stats['has_faq_schema'], $totalTerms, ($stats['has_faq_schema'] / $totalTerms) * 100);
printf("%-45s : %d / %d (%.1f%%)\n", "6. Live Article / Category Internal Links", $stats['has_internal_links'], $totalTerms, ($stats['has_internal_links'] / $totalTerms) * 100);
printf("   └─ Zero Article Links (Orphaned from updates) : %d / %d (%.1f%%)\n", $stats['zero_internal_links'], $totalTerms, ($stats['zero_internal_links'] / $totalTerms) * 100);
echo "================================================================================\n\n";

// Top 10 sample mismatch/leak pages
echo "--- SAMPLE 1: PAGES LEAKING FULL FORM IN META TITLE (Zero-Click Spoilers) ---\n";
$leaks = array_filter($auditResults, fn($r) => $r['leak_risk']);
$leakSample = array_slice($leaks, 0, 8);
foreach ($leakSample as $l) {
    echo "  • [{$l['acronym']}] {$l['meta_title']}\n";
}

echo "\n--- SAMPLE 2: PAGES PROMISING SALARY WITH ZERO SALARY CONTENT IN BODY ---\n";
$salaries = array_filter($auditResults, fn($r) => $r['salary_mismatch']);
$salSample = array_slice($salaries, 0, 8);
foreach ($salSample as $s) {
    echo "  • [{$s['acronym']}] Words: {$s['word_count']} | Title: {$s['meta_title']}\n";
}

echo "\n✅ AUDIT COMPLETE. Zero changes made to content, schema, or templates.\n";
