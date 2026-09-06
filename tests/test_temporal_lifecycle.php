<?php
/**
 * Sarkari.online - Deterministic Temporal Lifecycle & Provenance Unit Test Suite
 *
 * Verifies all 18 test cases specified in the temporal lifecycle blueprint:
 * 1. Deadline tomorrow → ACTIVE
 * 2. Deadline today before exact cutoff → ACTIVE
 * 3. Deadline passed → CLOSED
 * 4. Expired + official extension → ACTIVE with new date
 * 5. Expired + no extension → CLOSED
 * 6. Exam date unknown → NULL + no speculation
 * 7. Exam date officially announced but future → CLOSED, NOT EXAM_COMPLETED
 * 8. Exam actually completed → EXAM_COMPLETED
 * 9. Result date announced but result not released → NOT RESULT_RELEASED
 * 10. Result actually released → RESULT_RELEASED
 * 11. Historical cutoff/PYQ preserved
 * 12. Relative static urgency → FAIL
 * 13. "Last Date: September 2, 2026" → PASS (Rule 8)
 * 14. CLOSED + "Apply Now" → FAIL
 * 15. Asia/Kolkata timezone correctness
 * 16. RVUNL regression fixture
 * 17. Secondary source cannot override conflicting official source
 * 18. Missing official date remains NULL
 */

require_once dirname(__DIR__) . '/config.php';

use App\Services\TemporalFactService;
use App\Services\TemporalContentValidator;
use App\Services\AuthorityVerificationService;

$totalTests = 21;
$passedTests = 0;
$failedTests = 0;
$errors = [];

function recordTestResult(int $num, string $title, bool $passed, string $detail = ''): void {
    global $passedTests, $failedTests, $errors;
    if ($passed) {
        $passedTests++;
        echo "  \033[32m[PASS]\033[0m TEST {$num}: {$title}" . ($detail ? " — {$detail}" : "") . "\n";
    } else {
        $failedTests++;
        $errors[] = "TEST {$num}: {$title} failed" . ($detail ? " ({$detail})" : "");
        echo "  \033[31m[FAIL]\033[0m TEST {$num}: {$title}" . ($detail ? " — {$detail}" : "") . "\n";
    }
}

echo "========================================================================\n";
echo "   SARKARI.ONLINE - DETERMINISTIC TEMPORAL LIFECYCLE TEST SUITE\n";
echo "========================================================================\n\n";

$tz = new DateTimeZone('Asia/Kolkata');

// -------------------------------------------------------------------------
// TEST 1: Deadline tomorrow → ACTIVE
// -------------------------------------------------------------------------
$now1 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$facts1 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 07, 2026',
        'valid_until' => '2026-09-07 23:59:59'
    ]
];
$state1 = TemporalFactService::resolveLifecycle(0, $facts1, null, null, $now1);
recordTestResult(1, "Deadline tomorrow resolves to ACTIVE", $state1 === TemporalFactService::LIFECYCLE_ACTIVE, "Resolved: {$state1}");

// -------------------------------------------------------------------------
// TEST 2: Deadline today before exact cutoff → ACTIVE
// -------------------------------------------------------------------------
$now2 = new DateTimeImmutable('2026-09-06 14:00:00', $tz);
$facts2 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 06, 2026',
        'valid_until' => '2026-09-06 23:59:59'
    ]
];
$state2 = TemporalFactService::resolveLifecycle(0, $facts2, null, null, $now2);
recordTestResult(2, "Deadline today before exact cutoff (14:00 vs 23:59:59) resolves to ACTIVE", $state2 === TemporalFactService::LIFECYCLE_ACTIVE, "Resolved: {$state2}");

// -------------------------------------------------------------------------
// TEST 3: Deadline passed → CLOSED
// -------------------------------------------------------------------------
$now3 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$facts3 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ]
];
$state3 = TemporalFactService::resolveLifecycle(0, $facts3, null, null, $now3);
recordTestResult(3, "Deadline passed resolves to CLOSED", $state3 === TemporalFactService::LIFECYCLE_CLOSED, "Resolved: {$state3}");

// -------------------------------------------------------------------------
// TEST 4: Expired + official extension → ACTIVE with new date
// -------------------------------------------------------------------------
$now4 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$facts4 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'application_extension' => [
        'fact_name' => 'application_extension',
        'fact_value' => 'September 15, 2026',
        'valid_until' => '2026-09-15 23:59:59'
    ]
];
$state4 = TemporalFactService::resolveLifecycle(0, $facts4, null, null, $now4);
recordTestResult(4, "Expired deadline + official extension resolves to ACTIVE", $state4 === TemporalFactService::LIFECYCLE_ACTIVE, "Resolved: {$state4}");

// -------------------------------------------------------------------------
// TEST 5: Expired + no extension → CLOSED
// -------------------------------------------------------------------------
$now5 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$facts5 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ]
];
$state5 = TemporalFactService::resolveLifecycle(0, $facts5, null, null, $now5);
recordTestResult(5, "Expired deadline + no extension resolves to CLOSED", $state5 === TemporalFactService::LIFECYCLE_CLOSED, "Resolved: {$state5}");

// -------------------------------------------------------------------------
// TEST 6: Exam date unknown → NULL + no speculation
// -------------------------------------------------------------------------
$isUnannounced = TemporalFactService::isUnannouncedValue('To Be Announced (TBA)');
$specContent = '<p>Candidates are now waiting for the CBT exam date.</p>';
$audit6 = TemporalContentValidator::validate([
    'title' => 'Sample Exam 2026 Schedule',
    'content' => $specContent
], ['exam_date' => null], TemporalFactService::LIFECYCLE_CLOSED, $now1);
$test6Pass = ($isUnannounced === true) && ($audit6['pass'] === false);
recordTestResult(6, "Exam date unknown → fact is NULL and speculation is rejected", $test6Pass, "isUnannounced=" . ($isUnannounced ? 'true' : 'false') . ", validator rejected speculation=" . (!$audit6['pass'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 7: Exam date officially announced but future → CLOSED, NOT EXAM_COMPLETED
// -------------------------------------------------------------------------
$now7 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$facts7 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'valid_until' => '2026-10-10 23:59:59'
    ]
];
$state7 = TemporalFactService::resolveLifecycle(0, $facts7, null, null, $now7);
$test7Pass = ($state7 === TemporalFactService::LIFECYCLE_CLOSED) && ($state7 !== TemporalFactService::LIFECYCLE_EXAM_COMPLETED);
recordTestResult(7, "Future exam date does NOT mean EXAM_COMPLETED (remains CLOSED)", $test7Pass, "Resolved: {$state7}");

// -------------------------------------------------------------------------
// TEST 8: Exam actually completed → EXAM_COMPLETED
// -------------------------------------------------------------------------
$now8 = new DateTimeImmutable('2026-10-15 12:00:00', $tz);
$facts8 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'status' => 'completed',
        'valid_until' => '2026-10-10 23:59:59'
    ]
];
$state8 = TemporalFactService::resolveLifecycle(0, $facts8, null, null, $now8);
recordTestResult(8, "Exam actually completed resolves to EXAM_COMPLETED", $state8 === TemporalFactService::LIFECYCLE_EXAM_COMPLETED, "Resolved: {$state8}");

// -------------------------------------------------------------------------
// TEST 9: Result date announced but result not released → NOT RESULT_RELEASED
// -------------------------------------------------------------------------
$now9 = new DateTimeImmutable('2026-10-15 12:00:00', $tz);
$facts9 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'valid_until' => '2026-10-10 23:59:59'
    ],
    'result_date' => [
        'fact_name' => 'result_date',
        'fact_value' => 'November 15, 2026',
        'status' => 'pending'
    ]
];
$state9 = TemporalFactService::resolveLifecycle(0, $facts9, null, null, $now9);
$test9Pass = ($state9 !== TemporalFactService::LIFECYCLE_RESULT_RELEASED) && ($state9 === TemporalFactService::LIFECYCLE_EXAM_COMPLETED);
recordTestResult(9, "Future announced result date does NOT equate to RESULT_RELEASED", $test9Pass, "Resolved: {$state9}");

// -------------------------------------------------------------------------
// TEST 10: Result actually released → RESULT_RELEASED
// -------------------------------------------------------------------------
$now10 = new DateTimeImmutable('2026-11-20 12:00:00', $tz);
$facts10 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'valid_until' => '2026-10-10 23:59:59'
    ],
    'result_date' => [
        'fact_name' => 'result_date',
        'fact_value' => 'November 15, 2026',
        'status' => 'released'
    ]
];
$state10 = TemporalFactService::resolveLifecycle(0, $facts10, null, null, $now10);
recordTestResult(10, "Result actually released event resolves to RESULT_RELEASED", $state10 === TemporalFactService::LIFECYCLE_RESULT_RELEASED, "Resolved: {$state10}");

// -------------------------------------------------------------------------
// TEST 11: Historical cutoff / PYQ preserved
// -------------------------------------------------------------------------
$pyqState = TemporalFactService::resolveLifecycle(0, [], 'career-guides', 'UPSC Civil Services 2023: General Studies Previous Year Question Paper PDF');
$cutoffState = TemporalFactService::resolveLifecycle(0, [], 'exam-results', 'SSC CGL 2024: Tier 1 Category-Wise Cutoff Marks');
$test11Pass = ($pyqState === TemporalFactService::LIFECYCLE_EVERGREEN) && ($cutoffState === TemporalFactService::LIFECYCLE_HISTORICAL);
recordTestResult(11, "Historical cutoff/PYQ papers preserved under historical/evergreen", $test11Pass, "PYQ: {$pyqState}, Cutoff: {$cutoffState}");

// -------------------------------------------------------------------------
// TEST 12: Relative static urgency → FAIL
// -------------------------------------------------------------------------
$audit12 = TemporalContentValidator::validate([
    'title' => 'RVUNL Recruitment 2026: Last Date Today',
    'content' => '<p>The final deadline is today. Apply today to avoid cancellation.</p>'
], [], TemporalFactService::LIFECYCLE_ACTIVE, $now1);
recordTestResult(12, "Relative static urgency ('Last Date Today') triggers FAIL", $audit12['pass'] === false, "Validator passed=" . ($audit12['pass'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 13: "Last Date: September 2, 2026" → PASS (Rule 8)
// -------------------------------------------------------------------------
$audit13 = TemporalContentValidator::validate([
    'title' => 'RVUNL Recruitment 2026: Key Dates & Schedule',
    'content' => '<p>The application window closed on September 2, 2026. The exam date has not yet been officially announced by the commission.</p><p>Last Date: September 2, 2026</p>'
], [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 2, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ]
], TemporalFactService::LIFECYCLE_CLOSED, $now1);
recordTestResult(13, "'Last Date: September 2, 2026' paired with absolute date PASSES", $audit13['pass'] === true, "Validator passed=" . ($audit13['pass'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 14: CLOSED + "Apply Now" → FAIL
// -------------------------------------------------------------------------
$audit14 = TemporalContentValidator::validate([
    'title' => 'RVUNL Recruitment 2026: Apply Now for 2005 Posts',
    'content' => '<p><a href="#">Apply Online</a></p>'
], [], TemporalFactService::LIFECYCLE_CLOSED, $now1);
recordTestResult(14, "CLOSED lifecycle with active 'Apply Now' CTA triggers FAIL", $audit14['pass'] === false, "Violations detected: " . count($audit14['violations']));

// -------------------------------------------------------------------------
// TEST 15: Asia/Kolkata timezone correctness
// -------------------------------------------------------------------------
$nowIST = TemporalFactService::nowIST();
$test15Pass = ($nowIST->getTimezone()->getName() === 'Asia/Kolkata') && ($nowIST->getOffset() === 19800);
recordTestResult(15, "Timezone calculations strictly use Asia/Kolkata (+05:30)", $test15Pass, "Timezone: {$nowIST->getTimezone()->getName()}, Offset: {$nowIST->getOffset()}s");

// -------------------------------------------------------------------------
// TEST 16: RVUNL Regression Fixture
// -------------------------------------------------------------------------
$rvunlFixture = [
    'title' => 'RVUNL Recruitment 2026: Application Closed — 2,005 Posts',
    'excerpt' => 'RVUNL Recruitment 2026 application window closed on September 2, 2026. Candidates are awaiting examination dates.',
    'content' => "<div class='notice-box'><strong>Application Status: CLOSED</strong> — The registration window officially concluded on September 2, 2026.</div><p>The Rajasthan Rajya Vidyut Utpadan Nigam Limited (RVUNL) has concluded the online application process for 2,005 posts on September 2, 2026. The exam date has not yet been officially announced by the commission.</p><table><tr><td>Application Last Date</td><td>September 2, 2026</td></tr></table><a href='https://energy.rajasthan.gov.in'>Application Closed (Portal Archive)</a>",
    'meta_title' => 'RVUNL Recruitment 2026: Application Closed',
    'meta_description' => 'RVUNL recruitment for 2,005 posts closed on September 2, 2026. Check latest exam schedule updates.'
];
$rvunlFacts = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 2, 2026',
        'valid_until' => '2026-09-02 23:59:59',
        'source_url' => 'https://energy.rajasthan.gov.in'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => null,
        'source_url' => 'https://energy.rajasthan.gov.in'
    ]
];
$rvunlAudit = TemporalContentValidator::validate($rvunlFixture, $rvunlFacts, TemporalFactService::LIFECYCLE_CLOSED, $now1);
$rvunlText = $rvunlFixture['title'] . ' ' . $rvunlFixture['content'];
$hasForbiddenWords = preg_match('/\b(last date today|apply today|closing today|exam tomorrow)\b/i', $rvunlText);
$test16Pass = $rvunlAudit['pass'] && !$hasForbiddenWords;
recordTestResult(16, "RVUNL Regression Fixture (/article/rvunl-recruitment-2026-last-date/) passes 100%", $test16Pass, "Audit Pass: " . ($rvunlAudit['pass'] ? 'true' : 'false') . ", Forbidden words: " . ($hasForbiddenWords ? 'found' : 'none'));

// -------------------------------------------------------------------------
// TEST 17: Secondary source cannot override conflicting official source
// -------------------------------------------------------------------------
$govAuth = AuthorityVerificationService::verify('https://energy.rajasthan.gov.in/rvunl');
$mediaAuth = AuthorityVerificationService::verify('https://timesofindia.indiatimes.com/education');
$test17Pass = ($govAuth['is_valid'] === true && $govAuth['tier'] === 'tier_1a_government') &&
              ($mediaAuth['is_valid'] === false);
recordTestResult(17, "Secondary source cannot override conflicting official source", $test17Pass, "Gov.in valid: " . ($govAuth['is_valid'] ? 'true' : 'false') . ", Media unverified: " . (!$mediaAuth['is_valid'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 18: Missing official date remains NULL
// -------------------------------------------------------------------------
$parsedDateNull = TemporalFactService::parseDateIST('To Be Announced (TBA)');
$parsedDateNull2 = TemporalFactService::parseDateIST('Awaiting Official Circular');
$test18Pass = ($parsedDateNull === null) && ($parsedDateNull2 === null);
recordTestResult(18, "Missing official date remains strictly NULL (zero date hallucination)", $test18Pass, "TBA parsed=" . var_export($parsedDateNull, true));

// -------------------------------------------------------------------------
// TEST 19: Exam date passed + official postponement MUST NOT become EXAM_COMPLETED
// -------------------------------------------------------------------------
$now19 = new DateTimeImmutable('2026-10-15 19:00:00', $tz);
$facts19 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'admit_card_date' => [
        'fact_name' => 'admit_card_date',
        'fact_value' => 'October 01, 2026'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'valid_until' => '2026-10-10 23:59:59'
    ],
    'exam_postponement' => [
        'fact_name' => 'exam_postponement',
        'fact_value' => 'Examination postponed by official notification dated Oct 08',
        'status' => 'verified'
    ]
];
$state19 = TemporalFactService::resolveLifecycle(0, $facts19, null, null, $now19);
$test19Pass = ($state19 !== TemporalFactService::LIFECYCLE_EXAM_COMPLETED) && 
              ($state19 === TemporalFactService::LIFECYCLE_ADMIT_CARD_RELEASED || $state19 === TemporalFactService::LIFECYCLE_CLOSED);
recordTestResult(19, "Exam date passed + official postponement MUST NOT become EXAM_COMPLETED", $test19Pass, "Resolved: {$state19} (prevented false EXAM_COMPLETED)");

// -------------------------------------------------------------------------
// TEST 20: Post-exam event classification distinguishes answer_key, provisional_merit, scorecard vs final result
// -------------------------------------------------------------------------
$akEvent = TemporalFactService::classifyPostExamEvent("Notice: Provisional Answer Key and Candidate Response Sheet Released. Submit Objections up to Oct 20.");
$pmlEvent = TemporalFactService::classifyPostExamEvent("Notification: Provisional Merit List for Document Verification Published on Portal.");
$scEvent = TemporalFactService::classifyPostExamEvent("Candidate Scorecard and Individual Marks Link Now Active on Official Login Portal.");
$finalMeritEvent = TemporalFactService::classifyPostExamEvent("Final Selection: Final Merit List and Recommendation List of Selected Candidates Declared.");
$finalResEvent = TemporalFactService::classifyPostExamEvent("Examination Final Result Declared. Download Selection List and Category-Wise Cut Off.");

$test20Pass = ($akEvent['type'] === 'answer_key' && $akEvent['is_final_outcome'] === false) &&
              ($pmlEvent['type'] === 'provisional_merit_list' && $pmlEvent['is_final_outcome'] === false) &&
              ($scEvent['type'] === 'scorecard' && $scEvent['is_final_outcome'] === false) &&
              ($finalMeritEvent['type'] === 'final_merit_list' && $finalMeritEvent['is_final_outcome'] === true) &&
              ($finalResEvent['type'] === 'result' && $finalResEvent['is_final_outcome'] === true);

recordTestResult(20, "Post-exam event taxonomy distinguishes answer_key, provisional_merit, scorecard vs final outcome", $test20Pass, "AK outcome=" . ($akEvent['is_final_outcome'] ? 'true' : 'false') . ", PML outcome=" . ($pmlEvent['is_final_outcome'] ? 'true' : 'false') . ", Final outcome=" . ($finalResEvent['is_final_outcome'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 21: exam_date passed + zero postponement + NO evidence of conduct MUST NOT become EXAM_COMPLETED
// -------------------------------------------------------------------------
$now21 = new DateTimeImmutable('2026-10-15 19:00:00', $tz);
$facts21 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'September 02, 2026',
        'valid_until' => '2026-09-02 23:59:59'
    ],
    'admit_card_date' => [
        'fact_name' => 'admit_card_date',
        'fact_value' => 'October 01, 2026'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'October 10, 2026',
        'valid_until' => '2026-10-10 23:59:59',
        'status' => 'verified' // Date announced, but NO authoritative evidence that exam was actually conducted
    ]
];
$state21 = TemporalFactService::resolveLifecycle(0, $facts21, null, null, $now21);
// MUST NOT become EXAM_COMPLETED without authoritative evidence of conduct! Preserves safest lifecycle: ADMIT_CARD_RELEASED
$test21Pass = ($state21 !== TemporalFactService::LIFECYCLE_EXAM_COMPLETED) && 
              ($state21 === TemporalFactService::LIFECYCLE_ADMIT_CARD_RELEASED || $state21 === TemporalFactService::LIFECYCLE_CLOSED);
recordTestResult(21, "Exam date passed + zero postponement + NO evidence of conduct MUST NOT become EXAM_COMPLETED", $test21Pass, "Resolved: {$state21} (safest lifecycle preserved, completion uncertainty prevented)");

echo "\n========================================================================\n";
echo "   RESULTS: {$passedTests}/{$totalTests} PASSED, {$failedTests} FAILED\n";
echo "========================================================================\n";

if ($failedTests > 0) {
    echo "Failures summary:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
} else {
    echo "🎉 ALL {$totalTests} DETERMINISTIC TESTS PASSED WITH 100% SUCCESS!\n";
    exit(0);
}
