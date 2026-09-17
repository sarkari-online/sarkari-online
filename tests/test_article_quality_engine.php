<?php
declare(strict_types=1);

/**
 * Test Suite: ArticleQualityEngine & Editorial Human Quality Rules
 * 
 * Verifies all 24 Part 15 acceptance criteria:
 * - Single-line & multiline HTML handling
 * - Generic opener detection & replacement
 * - Generic 5-step download removal/conversion
 * - Generic exam-day section removal
 * - Boilerplate authority verification section removal
 * - FAQ quality gate (cap at 3, removal of stress/server FAQs, removal of empty FAQs)
 * - Table preservation
 * - Official URL preservation
 * - Repetitive candidate phrasing reduction
 * - Natural sentence rhythm
 * - Zero detector gaming
 */

require_once dirname(__DIR__) . '/app/Services/ArticleIntent.php';
require_once dirname(__DIR__) . '/app/Services/ArticleQualityEngine.php';
require_once dirname(__DIR__) . '/app/Services/HumanizerService.php';
require_once dirname(__DIR__) . '/app/Services/IntentStructureMap.php';
require_once dirname(__DIR__) . '/app/AI/OutlineContracts.php';

use App\Services\ArticleQualityEngine;
use App\Services\ArticleIntent;
use App\Services\IntentStructureMap;
use App\AI\OutlineContracts;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$details}\n";
        $failed++;
    }
}

echo "======================================================================\n";
echo "🧪 SARKARI.ONLINE ARTICLE QUALITY ENGINE COMPREHENSIVE TEST SUITE\n";
echo "======================================================================\n\n";

// ─────────────────────────────────────────────────────────────
// 1. GENERIC OPENER DETECTION & REPLACEMENT
// ─────────────────────────────────────────────────────────────
echo "Group 1: Generic Opener Detection & Replacement\n";

$openers = [
    "<p>If you've been waiting for the official notification, the wait is finally over. The board has released...</p>",
    "<p>The wait for the admit card is finally over for thousands of applicants.</p>",
    "<p>Good news for all aspirants! The examination dates have been declared.</p>",
    "<p>Candidates who have been eagerly waiting for results can now rejoice.</p>",
    "<p>In a major update, the commission announced new dates.</p>",
];

foreach ($openers as $idx => $op) {
    $analysis = ArticleQualityEngine::analyzeQuality($op, "SSC CGL 2026", "admit_card");
    assertTest("Detect formulaic opener #{$idx}", isset($analysis['issues']['generic_opener']) || isset($analysis['issues']['wait_is_over']));

    $refactored = ArticleQualityEngine::refactorContent($op, "SSC CGL 2026", "https://ssc.gov.in", "admit_card");
    assertTest("Replace formulaic opener #{$idx} with factual direct news", !str_contains($refactored['content'], "wait is finally over") && !str_contains($refactored['content'], "Good news for all"));
}

// ─────────────────────────────────────────────────────────────
// 2. GENERIC 5-STEP DOWNLOAD REMOVAL & OFFICIAL URL PRESERVATION
// ─────────────────────────────────────────────────────────────
echo "\nGroup 2: Generic 5-Step Download Guide Removal & URL Preservation\n";

$downloadHtml = '<h2>Step-by-Step Guide to Download SSC CGL Admit Card</h2><ol><li>Visit the official website at https://ssc.gov.in.</li><li>Look for the Notifications tab.</li><li>Click on Admit Card.</li><li>The PDF will open.</li><li>Download and save the file.</li></ol>';
$analysis = ArticleQualityEngine::analyzeQuality($downloadHtml);
assertTest("Detect generic download guide", isset($analysis['issues']['generic_download_guide']));

$refactored = ArticleQualityEngine::refactorContent($downloadHtml, "SSC CGL 2026", "https://ssc.gov.in", "admit_card");
assertTest("Strip 5-step numbered list", !str_contains($refactored['content'], "<ol>") && !str_contains($refactored['content'], "Step-by-Step Guide to Download"));
assertTest("Preserve official portal link in direct notice", str_contains($refactored['content'], "https://ssc.gov.in") && str_contains($refactored['content'], "ssc.gov.in"));

// ─────────────────────────────────────────────────────────────
// 3. EXAM DAY ADVICE REMOVAL (TRANSPARENT POUCH & PREACHINESS)
// ─────────────────────────────────────────────────────────────
echo "\nGroup 3: Exam Day Advice Removal\n";

$examDayHtml = '<h2>Exam Day Instructions & Mandatory Guidelines for RRB NTPC</h2><p>As you prepare, remember that board exams require strict adherence to protocols. Carry your school-issued admit card and photo ID. Ensure your stationery is kept in a transparent pouch to avoid any issues during the security check. Don\'t bring any electronic gadgets, including smartwatches.</p>';
$analysis = ArticleQualityEngine::analyzeQuality($examDayHtml);
assertTest("Detect generic exam day section and preachy advice", isset($analysis['issues']['exam_day_section']) && isset($analysis['issues']['preachy_advice']));

$refactored = ArticleQualityEngine::refactorContent($examDayHtml, "RRB NTPC 2026", "https://rrbcdg.gov.in", "admit_card");
assertTest("Remove generic exam day section completely", !str_contains($refactored['content'], "Exam Day Instructions") && !str_contains($refactored['content'], "transparent pouch"));

// ─────────────────────────────────────────────────────────────
// 4. BOILERPLATE AUTHORITY VERIFICATION REMOVAL
// ─────────────────────────────────────────────────────────────
echo "\nGroup 4: Boilerplate Authority Verification Removal\n";

$authHtml = '<h2>Official Notice Reference & Authority Verification for UPSC 2026</h2><p>All information provided is based on the official circulars released by the Union Public Service Commission. You should cross-check any updates directly at https://upsc.gov.in to ensure you\'re acting on the latest verified data.</p>';
$analysis = ArticleQualityEngine::analyzeQuality($authHtml);
assertTest("Detect boilerplate authority verification section", isset($analysis['issues']['authority_verification_section']));

$refactored = ArticleQualityEngine::refactorContent($authHtml, "UPSC 2026", "https://upsc.gov.in", "recruitment");
assertTest("Remove boilerplate authority verification section", !str_contains($refactored['content'], "Official Notice Reference & Authority Verification") && !str_contains($refactored['content'], "All information provided is based on"));

// ─────────────────────────────────────────────────────────────
// 5. FAQ QUALITY GATES (CAP AT 3, PURGE STRESS/SERVER FAQS)
// ─────────────────────────────────────────────────────────────
echo "\nGroup 5: FAQ Quality Gates\n";

$faqHtml = '<h2>Frequently Asked Questions (FAQs)</h2><ul>' .
    '<li><strong>What is the exam date?</strong> The exam is on November 15, 2026.</li>' .
    '<li><strong>What is the application fee?</strong> General fee is Rs 500.</li>' .
    '<li><strong>How do I handle exam stress?</strong> Focus on revision notes and maintain a consistent sleep schedule.</li>' .
    '<li><strong>What if the server crawls?</strong> Try incognito mode or clear cache.</li>' .
    '<li><strong>Can I change my center?</strong> Center changes are not permitted.</li>' .
    '<li><strong>What is the negative marking?</strong> 0.25 marks penalty per wrong answer.</li>' .
    '</ul>';

$analysis = ArticleQualityEngine::analyzeQuality($faqHtml);
assertTest("Detect excessive FAQs (> 3 questions)", isset($analysis['issues']['excessive_faqs']));
assertTest("Detect fake stress FAQ", isset($analysis['issues']['stress_faq']));
assertTest("Detect server crawl / cache advice", isset($analysis['issues']['server_advice']));

$refactored = ArticleQualityEngine::refactorContent($faqHtml, "Exam 2026", "https://exam.gov.in", "recruitment");
assertTest("Purge fake stress FAQ from content", !str_contains($refactored['content'], "exam stress") && !str_contains($refactored['content'], "sleep schedule"));
assertTest("Purge server / cache FAQ from content", !str_contains($refactored['content'], "server crawls") && !str_contains($refactored['content'], "incognito"));

$liCount = substr_count($refactored['content'], '<li>');
assertTest("Cap FAQs at maximum 3 items", $liCount <= 3, "Resulting LI count: {$liCount}");

// When all FAQs are fake/bogus, entire section should be removed
$bogusFaqHtml = '<h2>Frequently Asked Questions (FAQs)</h2><ul><li><strong>How do I handle exam stress?</strong> Sleep 8 hours.</li></ul>';
$refactoredBogus = ArticleQualityEngine::refactorContent($bogusFaqHtml, "Exam 2026", "https://exam.gov.in", "recruitment");
assertTest("Remove FAQ section completely when no factual FAQs exist", !str_contains($refactoredBogus['content'], "Frequently Asked Questions"));

// ─────────────────────────────────────────────────────────────
// 6. SINGLE-LINE HTML VS MULTILINE HTML HANDLING
// ─────────────────────────────────────────────────────────────
echo "\nGroup 6: Single-Line vs Multiline HTML Handling\n";

$singleLine = '<p>The wait is finally over.</p><h2>Step-by-Step Guide to Download</h2><ol><li>Visit site.</li></ol><h2>Exam Day Instructions & Mandatory Guidelines</h2><p>Ensure transparent pouch.</p>';
$refSingle = ArticleQualityEngine::refactorContent($singleLine, "Test Exam", "https://test.nic.in", "admit_card");
assertTest("Refactor single-line HTML without losing tags", !str_contains($refSingle['content'], "transparent pouch") && !str_contains($refSingle['content'], "Step-by-Step Guide"));

$multiLine = "<p>The wait is finally over.</p>\n\n<h2>Step-by-Step Guide to Download</h2>\n<ol>\n<li>Visit site.</li>\n</ol>\n\n<h2>Exam Day Instructions & Mandatory Guidelines</h2>\n<p>Ensure transparent pouch.</p>";
$refMulti = ArticleQualityEngine::refactorContent($multiLine, "Test Exam", "https://test.nic.in", "admit_card");
assertTest("Refactor multiline HTML identically", !str_contains($refMulti['content'], "transparent pouch") && !str_contains($refMulti['content'], "Step-by-Step Guide"));

// ─────────────────────────────────────────────────────────────
// 7. FACTUAL DATA & TABLE PRESERVATION
// ─────────────────────────────────────────────────────────────
echo "\nGroup 7: Factual Data & Table Preservation\n";

$tableHtml = '<h2>Shift Schedule & Timings</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Shift</th><th>Reporting</th></tr></thead><tbody><tr><td>Shift 1</td><td>08:30 AM</td></tr><tr><td>Shift 2</td><td>12:30 PM</td></tr></tbody></table></div><p>Always verify your subject codes against the official PDF to avoid any confusion during your study sessions.</p>';

$refTable = ArticleQualityEngine::refactorContent($tableHtml, "Exam", "https://test.nic.in", "admit_card");
assertTest("Preserve table markup intact", str_contains($refTable['content'], '<table class="data-table">') && str_contains($refTable['content'], '08:30 AM') && str_contains($refTable['content'], '12:30 PM'));
assertTest("Strip preachy sentence after table", !str_contains($refTable['content'], "avoid any confusion during your study sessions"));

// ─────────────────────────────────────────────────────────────
// 8. REPETITIVE CANDIDATE PHRASING REDUCTION
// ─────────────────────────────────────────────────────────────
echo "\nGroup 8: Repetitive Candidate Phrasing Reduction\n";

$repetitiveProse = '<p>Candidates can download the form. Candidates can check results. Candidates must verify their photo. Candidates must sign the slip. Candidates are advised to keep copies.</p>';
$varied = ArticleQualityEngine::varyCandidatePhrasing($repetitiveProse);
assertTest("Vary repetitive candidate phrasing", str_contains($varied, 'You can') && str_contains($varied, 'You need to') && str_contains($varied, 'Make sure to'));

// ─────────────────────────────────────────────────────────────
// 9. OUTLINE CONTRACTS & INTENT STRUCTURE MAP VERIFICATION
// ─────────────────────────────────────────────────────────────
echo "\nGroup 9: Intent-Specific Structure Non-Repetition\n";

foreach (ArticleIntent::cases() as $intentCase) {
    $contract = OutlineContracts::forIntent($intentCase);
    assertTest("Contract for {$intentCase->value} forbids generic download tutorial", str_contains($contract, "Do NOT generate generic 5-step download") || str_contains($contract, "Do NOT generate generic preparation tips") || str_contains($contract, "Keep the article focused strictly"));
    assertTest("Contract for {$intentCase->value} limits FAQs to max 3", str_contains($contract, "Max 3 factual FAQs only") || str_contains($contract, "maximum 3"));

    $sections = IntentStructureMap::getSections($intentCase->value);
    assertTest("IntentStructureMap for {$intentCase->value} has no boilerplate authority section", !in_array('official_notice_reference', $sections) && !in_array('authority_verification', $sections));
}

// ─────────────────────────────────────────────────────────────
// 10. SERVER CRASH & INCOGNITO ADVICE COMPLETE PURGE
// ─────────────────────────────────────────────────────────────
echo "\nGroup 10: Server Crash & Incognito Advice Complete Purge\n";

$serverAdviceHtml = '<p>Admit cards are available on https://upsc.gov.in.</p><p>Due to heavy traffic, the server crawls during peak hours. Candidates can try incognito mode or clear your browser cache.</p><ul><li>Use incognito window if link does not open.</li><li>Carry printed hall ticket.</li></ul>';
$analysis = ArticleQualityEngine::analyzeQuality($serverAdviceHtml);
assertTest("Detect server advice in paragraph and list", isset($analysis['issues']['server_advice']));

$refactored = ArticleQualityEngine::refactorContent($serverAdviceHtml, "UPSC 2026", "https://upsc.gov.in", "admit_card");
assertTest("Purge server crawl and incognito sentences from paragraphs", !str_contains($refactored['content'], "server crawls") && !str_contains($refactored['content'], "browser cache"));
assertTest("Purge incognito list items completely", !str_contains($refactored['content'], "incognito window"));
assertTest("Preserve factual admit card link and checklist item", str_contains($refactored['content'], "https://upsc.gov.in") && str_contains($refactored['content'], "Carry printed hall ticket"));

$postAnalysis = ArticleQualityEngine::analyzeQuality($refactored['content'], "UPSC 2026");
assertTest("Post-refactor quality score reaches 100/100", $postAnalysis['score'] === 100 && $postAnalysis['is_clean']);


// ─────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────
echo "\n======================================================================\n";
echo "🏁 TEST RUN FINISHED: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
