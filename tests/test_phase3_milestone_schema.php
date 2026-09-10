<?php
declare(strict_types=1);

/**
 * Phase 3 Test Suite — Milestone Schema Expansion, Badging & Gate Validation
 * 
 * Verifies:
 * 1. MilestoneStatusRenderer formatting: Confirmed, Tentative (with basis), and Upcoming.
 * 2. MilestoneTableRenderer: Renders full lifecycle (10+ rows for recruitment, 5+ for syllabus).
 * 3. TableIntegrityGate:
 *    - Allows grounded tentative badges with basis note.
 *    - Rejects ungrounded TBA / Awaited strings.
 *    - Preserves tentative badges during repair().
 */

require_once dirname(__DIR__) . '/config.php';

use App\Services\MilestoneStatusRenderer;
use App\Services\MilestoneTableRenderer;
use App\Services\TableIntegrityGate;

echo "================================================================================\n";
echo "🧪 PHASE 3 TEST SUITE: MILESTONE SCHEMA EXPANSION & GATE INTEGRITY\n";
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
// TEST 1: MilestoneStatusRenderer Badge Formatting
// -----------------------------------------------------------------------------
echo "1. Testing MilestoneStatusRenderer Badge Generation...\n";

// Confirmed
$confirmedBadge = MilestoneStatusRenderer::renderBadge([
    'value' => 'August 11, 2026',
    'source_confidence' => 'confirmed_primary_source'
]);
assertTest(
    "Confirmed date renders with text-success strong markup",
    str_contains($confirmedBadge, 'class="text-success"') && str_contains($confirmedBadge, 'August 11, 2026')
);

// Tentative with Basis
$tentativeBadge = MilestoneStatusRenderer::renderBadge([
    'value' => 'Late September 2026',
    'source_confidence' => 'tentative_estimate',
    'tentative_basis' => 'Per SBI FY26-27 annual calendar'
]);
assertTest(
    "Tentative date renders with status-pill-tentative and basis note",
    str_contains($tentativeBadge, 'status-pill-tentative') &&
    str_contains($tentativeBadge, 'Late September 2026 (Expected)') &&
    str_contains($tentativeBadge, 'class="basis-note"') &&
    str_contains($tentativeBadge, 'Per SBI FY26-27 annual calendar')
);

// Unannounced
$upcomingBadge = MilestoneStatusRenderer::renderBadge('Not yet announced');
assertTest(
    "Unannounced date renders with status-pill-upcoming",
    str_contains($upcomingBadge, 'status-pill-upcoming') && str_contains($upcomingBadge, 'Not Yet Officially Announced')
);

// -----------------------------------------------------------------------------
// TEST 2: MilestoneTableRenderer Expanded Schemas
// -----------------------------------------------------------------------------
echo "\n2. Testing MilestoneTableRenderer Lifecycle Expansion...\n";

$renderer = new MilestoneTableRenderer();

$sbiCycle = [
    'facts_json' => json_encode([
        'notification_date' => '2026-08-11',
        'application_start' => '2026-08-11',
        'application_end'   => '2026-08-31',
        'prelims_exam_date' => [
            'value' => 'Late September 2026',
            'source_confidence' => 'tentative_estimate',
            'tentative_basis' => 'Per SBI FY26-27 recruitment calendar and 2025 cycle precedent'
        ],
        'mains_exam_date' => [
            'value' => 'November 2026',
            'source_confidence' => 'tentative_estimate',
            'tentative_basis' => 'Precedent: SBI Clerk Mains conducted 6-8 weeks post-prelims per 2024/2025 archives'
        ],
        'vacancies' => 9124,
        'application_fee_general' => 750
    ])
];

// Recruitment Intent Table Check
$recruitmentHtml = $renderer->render($sbiCycle, 'recruitment');
assertTest("Recruitment table renders non-null HTML", !empty($recruitmentHtml));
assertTest(
    "Recruitment table contains Notification Release date",
    str_contains((string)$recruitmentHtml, '11 August 2026')
);
assertTest(
    "Recruitment table contains Application Last Date",
    str_contains((string)$recruitmentHtml, '31 August 2026')
);
assertTest(
    "Recruitment table contains Prelims Exam Date with Tentative Badge & Basis",
    str_contains((string)$recruitmentHtml, 'status-pill-tentative') &&
    str_contains((string)$recruitmentHtml, 'Per SBI FY26-27 recruitment calendar')
);
assertTest(
    "Recruitment table contains Vacancies formatted",
    str_contains((string)$recruitmentHtml, '9,124 Posts')
);

// Syllabus Intent Table Check
$syllabusHtml = $renderer->render($sbiCycle, 'syllabus_change');
assertTest("Syllabus change table renders non-null HTML", !empty($syllabusHtml));
assertTest(
    "Syllabus table contains Prelims and Mains schedule rows",
    str_contains((string)$syllabusHtml, 'Preliminary Examination Date') &&
    str_contains((string)$syllabusHtml, 'Mains Examination Date')
);

// -----------------------------------------------------------------------------
// TEST 3: TableIntegrityGate Validation with Grounded Badges
// -----------------------------------------------------------------------------
echo "\n3. Testing TableIntegrityGate Compliance...\n";

$gate = new TableIntegrityGate();

// Clean table containing tentative badges
$cleanTable = <<<HTML
<div class="table-responsive">
  <table class="data-table">
    <tr>
      <td>Preliminary Exam Date</td>
      <td>
        <span class="status-pill status-pill-tentative">Late September 2026 (Expected)</span>
        <div class="basis-note">Basis: Per SBI FY26-27 recruitment calendar</div>
      </td>
    </tr>
    <tr>
      <td>Notification Date</td>
      <td><strong class="text-success">11 August 2026</strong></td>
    </tr>
  </table>
</div>
HTML;

assertTest(
    "TableIntegrityGate permits grounded tentative badge with basis",
    $gate->isClean($cleanTable) === true,
    implode(', ', $gate->scan($cleanTable))
);

// Corrupted table containing forbidden TBA string
$corruptedTable = <<<HTML
<div class="table-responsive">
  <table class="data-table">
    <tr>
      <td>Admit Card</td>
      <td>TBA</td>
    </tr>
  </table>
</div>
HTML;

assertTest(
    "TableIntegrityGate catches ungrounded TBA placeholder",
    $gate->isClean($corruptedTable) === false
);

// Repair test: Tentative badge preserved, TBA repaired
$mixedTable = <<<HTML
<table>
  <tr>
    <td>Prelims Date</td>
    <td>
      <span class="status-pill status-pill-tentative">Late September 2026 (Expected)</span>
      <div class="basis-note">Basis: Per SBI FY26-27 recruitment calendar</div>
    </td>
  </tr>
  <tr>
    <td>Admit Card</td>
    <td>TBA</td>
  </tr>
</table>
HTML;

$repaired = $gate->repair($mixedTable);
assertTest(
    "TableIntegrityGate repair() preserves tentative badge untouched",
    str_contains($repaired, 'status-pill-tentative') && str_contains($repaired, 'Per SBI FY26-27 recruitment calendar')
);
assertTest(
    "TableIntegrityGate repair() replaces raw TBA with 'Not yet announced'",
    str_contains($repaired, 'Not yet announced') && !str_contains($repaired, '<td>TBA</td>')
);

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n================================================================================\n";
echo "📊 PHASE 3 TEST RESULTS: Passed: {$passed} | Failed: {$failed}\n";
echo "================================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
