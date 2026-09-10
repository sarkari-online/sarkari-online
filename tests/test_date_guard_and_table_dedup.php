<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

use App\Services\NewsDispatchSanitizer;
use App\Services\LegacyTableDetector;
use App\Services\ContentIntegrityGuard;
use App\Services\ContentLossException;

echo "================================================================================\n";
echo "🧪 RUNNING ADVERSARIAL TESTS (Anti-Date-Confusion & Legacy Table Dedup)\n";
echo "================================================================================\n\n";

$passed = 0;

// -----------------------------------------------------------------------------
// Test 1: NewsDispatchSanitizer completely strips published_at and filters age
// -----------------------------------------------------------------------------
$rawDispatches = [
    [
        'headline' => 'NEET PG 2026: Answer Key Expected Soon',
        'body_snippet' => 'Candidates awaiting the answer key can check on natboard.edu.in once released.',
        'published_at' => 'Thu, 10 Sep 2026 08:30:00 GMT',
        'source_url' => 'https://timesofindia.indiatimes.com/example'
    ],
    [
        'headline' => 'Old Article from 2025',
        'body_snippet' => 'Expired information.',
        'published_at' => 'Wed, 01 Jan 2025 00:00:00 GMT',
        'source_url' => 'https://example.com/old'
    ]
];

$sanitized = NewsDispatchSanitizer::filterAndSanitize($rawDispatches, 14);

assert(count($sanitized) === 1, "Expected 1 recent dispatch, got " . count($sanitized));
assert(!isset($sanitized[0]['published_at']), "published_at MUST be completely stripped!");
assert($sanitized[0]['headline'] === 'NEET PG 2026: Answer Key Expected Soon');
echo "✅ Test 1 Passed: NewsDispatchSanitizer strictly strips published_at and filters stale news.\n";
$passed++;

// -----------------------------------------------------------------------------
// Test 2: LegacyTableDetector detects corrupted placeholder table
// -----------------------------------------------------------------------------
$corruptedHtml = <<<HTML
<h2>NEET PG 2026 Result: Cutoff and Qualifying Percentiles</h2>
<p>Some preliminary analysis...</p>
<div class="table-responsive">
  <table class="data-table">
    <thead>
      <tr><th>Statutory Milestone</th><th>Official Date / Status</th></tr>
    </thead>
    <tbody>
      <tr><td>Result Date</td><td>Not yet announced</td></tr>
      <tr><td>Scorecard Link</td><td>Not yet announced</td></tr>
    </tbody>
  </table>
</div>
<p>Next steps...</p>
HTML;

$legacyFound = LegacyTableDetector::findLegacyTables($corruptedHtml);
assert(count($legacyFound) === 1, "Expected 1 legacy table, found " . count($legacyFound));
assert(str_contains($legacyFound[0]['html'], 'Result Date'));
echo "✅ Test 2 Passed: LegacyTableDetector correctly identified corrupted 2-row milestone table.\n";
$passed++;

// -----------------------------------------------------------------------------
// Test 3: LegacyTableDetector DOES NOT flag legitimate domain tables (Cutoffs/Pattern)
// -----------------------------------------------------------------------------
$legitCutoffHtml = <<<HTML
<h2>Cutoff Percentile Requirements</h2>
<div class="table-responsive">
  <table class="data-table">
    <thead>
      <tr><th>Category</th><th>Qualifying Percentile</th><th>Cutoff Score</th></tr>
    </thead>
    <tbody>
      <tr><td>General / EWS</td><td>50th Percentile</td><td>291</td></tr>
      <tr><td>SC / ST / OBC</td><td>40th Percentile</td><td>257</td></tr>
      <tr><td>UR-PwBD</td><td>45th Percentile</td><td>Not yet announced</td></tr>
    </tbody>
  </table>
</div>
HTML;

$legitFound = LegacyTableDetector::findLegacyTables($legitCutoffHtml);
assert(count($legitFound) === 0, "Legitimate cutoff table MUST NOT be flagged as legacy!");
echo "✅ Test 3 Passed: Legitimate Cutoff table with partial placeholder cell was preserved.\n";
$passed++;

// -----------------------------------------------------------------------------
// Test 4: ContentIntegrityGuard assertIntentionalReplacement enforces exact counts
// -----------------------------------------------------------------------------
$before = "<p>Text</p><table><tr><td>Old 1</td></tr></table><p>More</p><table><tr><td>Old 2</td></tr></table>";
$after  = "<p>Text</p><p>More</p><table><tr><td>Old 2</td></tr></table>"; // 1 table removed

ContentIntegrityGuard::assertIntentionalReplacement($before, $after, 1);

$threw = false;
try {
    ContentIntegrityGuard::assertIntentionalReplacement($before, $after, 2); // expected 2, but only 1 removed
} catch (ContentLossException $e) {
    $threw = true;
}
assert($threw, "ContentIntegrityGuard must throw when expected removed count doesn't match!");
echo "✅ Test 4 Passed: ContentIntegrityGuard::assertIntentionalReplacement enforces exact removal counts.\n";
$passed++;

echo "\n🎉 ALL {$passed} ADVERSARIAL TESTS PASSED!\n";
