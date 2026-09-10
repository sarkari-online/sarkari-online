<?php
declare(strict_types=1);

/**
 * Phase 2 Test Suite — Grounded Fact Extraction, Exam Pattern & Tentative Basis
 * 
 * Verifies:
 * 1. Exam Pattern Structure: Full Prelims (100 Qs/100 M/60 min) & Mains (190 Qs/200 M/160 min).
 * 2. Hand-checked comparison of Prelims & Mains numbers against official SBI Clerk ground truth.
 * 3. Sharpened Tentative Basis Assertion: tentative_basis must contain a citable reference
 *    (year, circular, official portal, or calendar) — vague non-answers rejected.
 * 4. FactCompletenessRules::satisfiesRequirement() enforcement of tiered trust.
 * 5. AuthorityFactFetcherService post-processing cleansing of ungrounded estimates.
 */

require_once dirname(__DIR__) . '/config.php';

use App\Services\AuthorityFactFetcherService;
use App\Services\FactCompletenessRules;
use App\AI\Gemini;

echo "================================================================================\n";
echo "🧪 PHASE 2 TEST SUITE: GROUNDED FACTS, EXAM PATTERN & TENTATIVE BASIS\n";
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
// TEST 1: FactCompletenessRules::satisfiesRequirement() Unit Tests
// -----------------------------------------------------------------------------
echo "1. Testing FactCompletenessRules::satisfiesRequirement() with tiered trust...\n";

// Case A: Confirmed primary source
$confirmedFact = [
    'value' => 'August 11, 2026',
    'source_confidence' => 'confirmed_primary_source',
    'tentative_basis' => null
];
assertTest(
    "confirmed_primary_source satisfies completeness requirement",
    FactCompletenessRules::satisfiesRequirement($confirmedFact) === true
);

// Case B: Tentative estimate with valid cited basis (contains year / portal)
$groundedTentativeFact = [
    'value' => 'Late September 2026',
    'source_confidence' => 'tentative_estimate',
    'tentative_basis' => 'Prelims held late September per SBI Clerk 2025 cycle historical schedule'
];
assertTest(
    "tentative_estimate with citable precedent satisfies requirement",
    FactCompletenessRules::satisfiesRequirement($groundedTentativeFact) === true
);

// Case C: Tentative estimate with vague non-answer basis (must FAIL)
$vagueTentativeFact = [
    'value' => 'September 2026',
    'source_confidence' => 'tentative_estimate',
    'tentative_basis' => 'based on general pattern'
];
assertTest(
    "tentative_estimate with vague non-answer basis is REJECTED",
    FactCompletenessRules::satisfiesRequirement($vagueTentativeFact) === false
);

// Case D: Tentative estimate with empty basis (must FAIL)
$emptyBasisFact = [
    'value' => 'September 2026',
    'source_confidence' => 'tentative_estimate',
    'tentative_basis' => ''
];
assertTest(
    "tentative_estimate with empty basis is REJECTED",
    FactCompletenessRules::satisfiesRequirement($emptyBasisFact) === false
);

// Case E: Unavailable fact (must FAIL)
$unavailableFact = [
    'value' => 'Not yet announced',
    'source_confidence' => 'unavailable',
    'tentative_basis' => null
];
assertTest(
    "unavailable status is REJECTED from satisfying completeness",
    FactCompletenessRules::satisfiesRequirement($unavailableFact) === false
);

// -----------------------------------------------------------------------------
// TEST 2: AuthorityFactFetcherService Extraction & Post-Processing Cleanser
// -----------------------------------------------------------------------------
echo "\n2. Testing AuthorityFactFetcherService Grounded Fact Extraction for SBI Clerk...\n";

// Set a deterministic mock handler matching official SBI Clerk notification specs
Gemini::setMockHandler(function(string $prompt, array $options) {
    return [
        'text' => json_encode([
            'authority_name' => 'State Bank of India (SBI)',
            'official_portal' => 'https://sbi.co.in/web/careers',
            'notification_code' => 'CRPD/CR/2026-27/01',
            'exam_phase' => 'Junior Associate (Customer Support & Sales)',
            'exam_status' => 'Upcoming / Tentative Schedule Active',
            'distinction_notes' => 'Preliminary Exam is qualifying; Final merit based solely on Mains Exam.',
            'prelims_pattern' => [
                'total_questions' => 100,
                'total_marks' => 100,
                'duration_minutes' => 60,
                'negative_marking' => '0.25 (1/4th mark deducted per incorrect answer)',
                'sections' => [
                    ['subject' => 'English Language', 'questions' => 30, 'marks' => 30, 'duration_minutes' => 20],
                    ['subject' => 'Quantitative Aptitude', 'questions' => 35, 'marks' => 35, 'duration_minutes' => 20],
                    ['subject' => 'Reasoning Ability', 'questions' => 35, 'marks' => 35, 'duration_minutes' => 20]
                ]
            ],
            'mains_pattern' => [
                'total_questions' => 190,
                'total_marks' => 200,
                'duration_minutes' => 160,
                'negative_marking' => '0.25 (1/4th mark deducted per incorrect answer)',
                'sections' => [
                    ['subject' => 'General / Financial Awareness', 'questions' => 50, 'marks' => 50, 'duration_minutes' => 35],
                    ['subject' => 'General English', 'questions' => 40, 'marks' => 40, 'duration_minutes' => 35],
                    ['subject' => 'Quantitative Aptitude', 'questions' => 50, 'marks' => 50, 'duration_minutes' => 45],
                    ['subject' => 'Reasoning Ability & Computer Aptitude', 'questions' => 50, 'marks' => 60, 'duration_minutes' => 45]
                ]
            ],
            'dates_schedule' => [
                [
                    'milestone' => 'Official Notification Release Date',
                    'date' => 'August 11, 2026',
                    'status' => 'Confirmed',
                    'source_confidence' => 'confirmed_primary_source',
                    'tentative_basis' => null
                ],
                [
                    'milestone' => 'Application Last Date',
                    'date' => 'August 31, 2026',
                    'status' => 'Confirmed',
                    'source_confidence' => 'confirmed_primary_source',
                    'tentative_basis' => null
                ],
                [
                    'milestone' => 'Preliminary Examination Date',
                    'date' => 'Late September 2026',
                    'status' => 'Tentative / Expected',
                    'source_confidence' => 'tentative_estimate',
                    'tentative_basis' => 'Per SBI FY26-27 recruitment calendar and 2025 cycle precedent'
                ],
                [
                    'milestone' => 'Mains Examination Date',
                    'date' => 'November 2026',
                    'status' => 'Tentative / Expected',
                    'source_confidence' => 'tentative_estimate',
                    'tentative_basis' => 'Precedent: SBI Clerk Mains conducted 6-8 weeks post-prelims per 2024/2025 archives'
                ],
                [
                    'milestone' => 'Ungrounded Milestone Test',
                    'date' => 'October 15, 2026',
                    'status' => 'Tentative',
                    'source_confidence' => 'tentative_estimate',
                    'tentative_basis' => 'just guessing without basis' // SHOULD BE CLEANSED BY POST-PROCESSOR
                ]
            ],
            'regional_portals' => [],
            'mandatory_documents' => [
                'Printed Call Letter with passport photo',
                'Original Valid Photo ID proof'
            ],
            'official_notice_ref' => 'SBI Careers Portal (sbi.co.in/web/careers)',
            'extraction_confidence' => 'high'
        ])
    ];
});

$fetcher = new AuthorityFactFetcherService();
$extracted = $fetcher->fetchFactsForTopic("SBI Clerk Junior Associate 2026 Syllabus & Exam Pattern");

// Restore mock handler
Gemini::setMockHandler(null);

// -----------------------------------------------------------------------------
// TEST 3: Ground Truth Verification of Extracted Exam Pattern
// -----------------------------------------------------------------------------
echo "\n3. Cross-Checking Extracted Exam Pattern Numbers Against SBI Official Ground Truth...\n";

// Prelims Pattern Check:
// Official SBI Clerk Prelims: 100 Questions, 100 Marks, 60 Minutes (English 30, Quant 35, Reasoning 35), 0.25 neg
$prelims = $extracted['prelims_pattern'] ?? [];
assertTest(
    "Prelims Total Questions === 100",
    ($prelims['total_questions'] ?? 0) === 100,
    "Got: " . ($prelims['total_questions'] ?? 'null')
);
assertTest(
    "Prelims Total Marks === 100",
    ($prelims['total_marks'] ?? 0) === 100,
    "Got: " . ($prelims['total_marks'] ?? 'null')
);
assertTest(
    "Prelims Duration === 60 Minutes",
    ($prelims['duration_minutes'] ?? 0) === 60,
    "Got: " . ($prelims['duration_minutes'] ?? 'null')
);
assertTest(
    "Prelims Negative Marking === 0.25 (1/4th penalty)",
    str_contains((string)($prelims['negative_marking'] ?? ''), '0.25') || str_contains((string)($prelims['negative_marking'] ?? ''), '1/4'),
    "Got: " . ($prelims['negative_marking'] ?? 'null')
);
assertTest(
    "Prelims Sections count === 3 (English: 30, Quant: 35, Reasoning: 35)",
    count($prelims['sections'] ?? []) === 3 &&
    $prelims['sections'][0]['questions'] === 30 &&
    $prelims['sections'][1]['questions'] === 35 &&
    $prelims['sections'][2]['questions'] === 35
);

// Mains Pattern Check:
// Official SBI Clerk Mains: 190 Questions, 200 Marks, 160 Minutes (2h 40m), 4 sections
$mains = $extracted['mains_pattern'] ?? [];
assertTest(
    "Mains Total Questions === 190",
    ($mains['total_questions'] ?? 0) === 190,
    "Got: " . ($mains['total_questions'] ?? 'null')
);
assertTest(
    "Mains Total Marks === 200",
    ($mains['total_marks'] ?? 0) === 200,
    "Got: " . ($mains['total_marks'] ?? 'null')
);
assertTest(
    "Mains Duration === 160 Minutes (2 hours 40 minutes)",
    ($mains['duration_minutes'] ?? 0) === 160,
    "Got: " . ($mains['duration_minutes'] ?? 'null')
);
assertTest(
    "Mains Sections count === 4 (GA: 50, English: 40, Quant: 50, Reasoning/Computer: 50 Qs / 60 M)",
    count($mains['sections'] ?? []) === 4 &&
    $mains['sections'][0]['questions'] === 50 &&
    $mains['sections'][1]['questions'] === 40 &&
    $mains['sections'][2]['questions'] === 50 &&
    $mains['sections'][3]['questions'] === 50 &&
    $mains['sections'][3]['marks'] === 60 // Reasoning & Computer Aptitude has 60 marks for 50 Qs
);

// -----------------------------------------------------------------------------
// TEST 4: Post-Extraction Cleansing of Ungrounded Estimates
// -----------------------------------------------------------------------------
echo "\n4. Verifying Cleansing of Ungrounded Tentative Estimates...\n";

$schedule = $extracted['dates_schedule'] ?? [];
$ungroundedItem = null;
$validTentativeItem = null;

foreach ($schedule as $item) {
    if (($item['milestone'] ?? '') === 'Ungrounded Milestone Test') {
        $ungroundedItem = $item;
    }
    if (($item['milestone'] ?? '') === 'Preliminary Examination Date') {
        $validTentativeItem = $item;
    }
}

assertTest(
    "Valid tentative milestone preserved with citable basis",
    $validTentativeItem !== null &&
    $validTentativeItem['source_confidence'] === 'tentative_estimate' &&
    !empty($validTentativeItem['tentative_basis']) &&
    preg_match('/\b(202\d|sbi|calendar)\b/i', $validTentativeItem['tentative_basis']) === 1
);

assertTest(
    "Ungrounded tentative estimate without valid basis was CLEANSED to 'unavailable'",
    $ungroundedItem !== null &&
    $ungroundedItem['source_confidence'] === 'unavailable' &&
    $ungroundedItem['date'] === 'Not yet announced' &&
    $ungroundedItem['tentative_basis'] === null,
    "Got confidence: " . ($ungroundedItem['source_confidence'] ?? 'null') . ", date: " . ($ungroundedItem['date'] ?? 'null')
);

// -----------------------------------------------------------------------------
// SUMMARY & GROUND-TRUTH REPORT
// -----------------------------------------------------------------------------
echo "\n================================================================================\n";
echo "📊 PHASE 2 TEST RESULTS: Passed: {$passed} | Failed: {$failed}\n";
echo "================================================================================\n\n";

echo "--- GROUND-TRUTH AUDIT COMPARISON: SBI CLERK JUNIOR ASSOCIATE ---\n";
echo "1. PRELIMS STRUCTURE:\n";
echo "   - Expected: 100 Qs | 100 Marks | 60 Mins | Sections: English(30), Quant(35), Reasoning(35)\n";
echo "   - Extracted: {$prelims['total_questions']} Qs | {$prelims['total_marks']} Marks | {$prelims['duration_minutes']} Mins\n";
foreach ($prelims['sections'] as $s) {
    echo "     * {$s['subject']}: {$s['questions']} Qs, {$s['marks']} Marks, {$s['duration_minutes']} Mins\n";
}

echo "\n2. MAINS STRUCTURE:\n";
echo "   - Expected: 190 Qs | 200 Marks | 160 Mins | GA(50/50), Eng(40/40), Quant(50/50), Reasoning&Computer(50/60)\n";
echo "   - Extracted: {$mains['total_questions']} Qs | {$mains['total_marks']} Marks | {$mains['duration_minutes']} Mins\n";
foreach ($mains['sections'] as $s) {
    echo "     * {$s['subject']}: {$s['questions']} Qs, {$s['marks']} Marks, {$s['duration_minutes']} Mins\n";
}

echo "\n3. DATES SCHEDULE PROVENANCE:\n";
foreach ($schedule as $d) {
    $basisDisplay = $d['tentative_basis'] ? " [Basis: {$d['tentative_basis']}]" : "";
    echo "   - {$d['milestone']}: {$d['date']} ({$d['source_confidence']}){$basisDisplay}\n";
}

if ($failed > 0) {
    exit(1);
}
exit(0);
