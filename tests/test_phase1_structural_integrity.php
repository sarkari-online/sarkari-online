<?php
declare(strict_types=1);

/**
 * Phase 1 Test Suite — Structural Integrity & Non-Destructive Table Injection
 * 
 * Verifies:
 * 1. ContentIntegrityGuard catches structural loss (table/h2 count decrease).
 * 2. LOAD-BEARING ADVERSARIAL CASE: Exam Pattern <table> placed BEFORE
 *    the DATES_MILESTONE_TABLE placeholder in document order.
 *    (Recreates the exact failure mode of the SBI Clerk 2026 article where
 *    the legacy regex destroyed the Exam Pattern table).
 * 3. Safe fallback insertion when placeholder is omitted by LLM.
 */

require_once dirname(__DIR__) . '/config.php';

use App\Services\ContentIntegrityGuard;
use App\Services\ContentLossException;
use App\AI\ArticleGenerator;

echo "================================================================================\n";
echo "🧪 PHASE 1 TEST SUITE: STRUCTURAL INTEGRITY & NON-DESTRUCTIVE INJECTION\n";
echo "================================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: {$name}\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: {$name}\n";
        if ($details) echo "     Details: {$details}\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// TEST 1: ContentIntegrityGuard detects table count loss
// -----------------------------------------------------------------------------
echo "1. Testing ContentIntegrityGuard with reduced <table> count...\n";
try {
    $before = "<h2>Heading</h2><table><tr><td>Original Table 1</td></tr></table><table><tr><td>Original Table 2</td></tr></table>";
    $after  = "<h2>Heading</h2><table><tr><td>Only One Table Survived</td></tr></table>";
    ContentIntegrityGuard::assertNoStructuralLoss($before, $after);
    assertTest("ContentIntegrityGuard should throw when table count decreases", false);
} catch (ContentLossException $e) {
    assertTest("ContentIntegrityGuard caught table reduction", true, $e->getMessage());
}

// -----------------------------------------------------------------------------
// TEST 2: ContentIntegrityGuard detects <h2> heading count loss
// -----------------------------------------------------------------------------
echo "\n2. Testing ContentIntegrityGuard with reduced <h2> count...\n";
try {
    $before = "<h2>Section 1</h2><p>Text</p><h2>Section 2</h2><p>Text</p>";
    $after  = "<h2>Section 1 Only</h2><p>Text</p>";
    ContentIntegrityGuard::assertNoStructuralLoss($before, $after);
    assertTest("ContentIntegrityGuard should throw when h2 count decreases", false);
} catch (ContentLossException $e) {
    assertTest("ContentIntegrityGuard caught h2 reduction", true, $e->getMessage());
}

// -----------------------------------------------------------------------------
// TEST 3: ContentIntegrityGuard allows equal or increased structural elements
// -----------------------------------------------------------------------------
echo "\n3. Testing ContentIntegrityGuard on valid insertion...\n";
try {
    $before = "<h2>Overview</h2><p>Text</p>";
    $after  = "<h2>Overview</h2><div class=\"table-responsive\"><table><tr><td>Dates</td></tr></table></div><p>Text</p>";
    ContentIntegrityGuard::assertNoStructuralLoss($before, $after);
    assertTest("ContentIntegrityGuard passed valid table addition", true);
} catch (Exception $e) {
    assertTest("ContentIntegrityGuard should not throw on valid addition", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// TEST 4: LOAD-BEARING ADVERSARIAL REGRESSION TEST
// -----------------------------------------------------------------------------
// HISTORICAL BUG REPRODUCTION NOTE:
// In the SBI Clerk 2026 incident, the LLM generated:
//   <h2>SBI Clerk Junior Associate 2026: Old vs New Exam Pattern Comparison</h2>
//   followed by the Prelims/Mains Exam Pattern <table>.
// Later in document order, the dates table was supposed to be placed.
// The legacy regex `preg_replace('/<div class="table-responsive">.*?<\/table><\/div>/s', ...)`
// matched the FIRST <table> in the document and clobbered the Exam Pattern table!
//
// This test fixture enforces that Exam Pattern <table> appears BEFORE the
// <!--DATES_MILESTONE_TABLE--> placeholder in document order.
// -----------------------------------------------------------------------------
echo "\n4. Testing Load-Bearing Adversarial Case: Exam Pattern Table BEFORE Dates Placeholder...\n";

$generator = new ArticleGenerator();

$patternTableHtml = <<<HTML
<div class="table-responsive">
  <table class="exam-pattern-table">
    <thead>
      <tr><th>Subject</th><th>Questions</th><th>Marks</th><th>Duration</th></tr>
    </thead>
    <tbody>
      <tr><td>English Language</td><td>30</td><td>30</td><td>20 Minutes</td></tr>
      <tr><td>Quantitative Aptitude</td><td>35</td><td>35</td><td>20 Minutes</td></tr>
      <tr><td>Reasoning Ability</td><td>35</td><td>35</td><td>20 Minutes</td></tr>
    </tbody>
  </table>
</div>
HTML;

$datesTableHtml = <<<HTML
<div class="table-responsive">
  <table class="dates-milestone-table">
    <thead>
      <tr><th>Statutory Milestone</th><th>Official Date / Status</th></tr>
    </thead>
    <tbody>
      <tr><td>Notification Release</td><td>August 11, 2026</td></tr>
      <tr><td>Application Last Date</td><td>August 31, 2026</td></tr>
      <tr><td>Preliminary Exam</td><td>Late September 2026 (Tentative)</td></tr>
    </tbody>
  </table>
</div>
HTML;

$adversarialArticleContent = <<<HTML
<h2>SBI Clerk Junior Associate 2026: Overview & Latest Notification</h2>
<p>State Bank of India Junior Associate recruitment details and timeline.</p>
<!--DATES_MILESTONE_TABLE-->

<h2>SBI Clerk Junior Associate 2026: Old vs New Exam Pattern Comparison</h2>
<p>Below is the verified examination pattern for the upcoming preliminary test:</p>
{$patternTableHtml}

<h2>Detailed Subject-Wise Syllabus Breakdown</h2>
<p>Comprehensive subject analysis for banking aspirants.</p>
HTML;

$injectedContent = $generator->injectPhpDatesTable($adversarialArticleContent, $datesTableHtml);

// Assert 1: The placeholder was replaced
assertTest(
    "DATES_MILESTONE_TABLE placeholder was replaced",
    !str_contains($injectedContent, ArticleGenerator::DATES_TABLE_PLACEHOLDER)
);

// Assert 2: The Exam Pattern table is 100% intact
assertTest(
    "Exam Pattern table was preserved byte-for-byte without clobbering",
    str_contains($injectedContent, $patternTableHtml),
    "Pattern table markup was modified or destroyed"
);

// Assert 3: The Dates Milestone table is present
assertTest(
    "Dates Milestone table was successfully injected",
    str_contains($injectedContent, $datesTableHtml)
);

// Assert 4: Total table count in injected content is exactly 2
$tableCount = substr_count($injectedContent, '<table');
assertTest(
    "Final HTML contains exactly 2 tables (Exam Pattern + Dates)",
    $tableCount === 2,
    "Expected 2 tables, found {$tableCount}"
);

// -----------------------------------------------------------------------------
// TEST 5: Fallback Insertion when placeholder is omitted by LLM
// -----------------------------------------------------------------------------
echo "\n5. Testing Safe Fallback Insertion (when placeholder is missing)...\n";

$contentWithoutPlaceholder = <<<HTML
<h2>SBI Clerk Junior Associate 2026: Overview & Notification</h2>
<p>First paragraph overview text.</p>

<h2>SBI Clerk Junior Associate 2026: Exam Pattern</h2>
{$patternTableHtml}
HTML;

$fallbackInjected = $generator->injectPhpDatesTable($contentWithoutPlaceholder, $datesTableHtml);

assertTest(
    "Fallback preserves Exam Pattern table without overwriting it",
    str_contains($fallbackInjected, $patternTableHtml)
);

assertTest(
    "Fallback successfully injected Dates Milestone table",
    str_contains($fallbackInjected, $datesTableHtml)
);

$fallbackTableCount = substr_count($fallbackInjected, '<table');
assertTest(
    "Fallback final HTML has both tables intact (count = 2)",
    $fallbackTableCount === 2,
    "Expected 2 tables, found {$fallbackTableCount}"
);

// -----------------------------------------------------------------------------
// SUMMARY & DIFF DISPLAY
// -----------------------------------------------------------------------------
echo "\n================================================================================\n";
echo "📊 TEST RESULTS: Passed: {$passed} | Failed: {$failed}\n";
echo "================================================================================\n\n";

echo "--- INJECTED CONTENT STRUCTURE DIFF INSPECTION ---\n";
echo "Length Before: " . strlen($adversarialArticleContent) . " bytes\n";
echo "Length After : " . strlen($injectedContent) . " bytes\n";
echo "Placeholder Present Before: " . (str_contains($adversarialArticleContent, ArticleGenerator::DATES_TABLE_PLACEHOLDER) ? 'YES' : 'NO') . "\n";
echo "Placeholder Present After : " . (str_contains($injectedContent, ArticleGenerator::DATES_TABLE_PLACEHOLDER) ? 'YES' : 'NO') . "\n";
echo "Exam Pattern Table Present: " . (str_contains($injectedContent, 'class="exam-pattern-table"') ? 'YES' : 'NO') . "\n";
echo "Dates Table Present       : " . (str_contains($injectedContent, 'class="dates-milestone-table"') ? 'YES' : 'NO') . "\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
