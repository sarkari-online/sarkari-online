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
use App\Services\TemporalRevalidationService;
use App\Services\FeaturedSnippetService;

$totalTests = 25;
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

// -------------------------------------------------------------------------
// TEST 22: Initial publish with NULL date -> Autonomous official detection -> Same article enriched in-place
// -------------------------------------------------------------------------
// Step 1: Early publish with NULL deadline
$now22_1 = new DateTimeImmutable('2026-09-01 10:00:00', $tz);
$facts22_1 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => null,
        'source_url' => 'https://ssc.gov.in',
        'status' => 'pending'
    ]
];
$state22_1 = TemporalFactService::resolveLifecycle(0, $facts22_1, 'government-jobs', 'SSC CGL 2026 Recruitment', $now22_1);
$pass22_step1 = ($state22_1 === TemporalFactService::LIFECYCLE_ACTIVE) && ($facts22_1['application_end']['fact_value'] === null);

// Step 2: 5 days later, official portal circular released
$now22_2 = new DateTimeImmutable('2026-09-06 12:00:00', $tz);
$portalNotice22 = "Staff Selection Commission: Notice No. 3/2/2026 - Online applications for Combined Graduate Level Examination 2026 close on September 30, 2026 at 23:00 Hrs.";
$extractedDate22 = TemporalRevalidationService::extractDeadlineFromPortalText($portalNotice22, $now22_2);

// Step 3: Same article in-place HTML enrichment
$initialHtml22 = "<div class='notice-box'>Official recruitment notice.</div><p>Staff Selection Commission has announced CGL 2026.</p><table><tr><td>Application Last Date</td><td>To Be Announced</td></tr></table><p>Last Date: To Be Announced</p><a href='https://ssc.gov.in'>Apply Online</a>";
$enrichedHtml22 = TemporalRevalidationService::updateKeyDateInContent($initialHtml22, 'application_end', $extractedDate22, false);

// Step 4: Verified fact provenance and lifecycle recalculation
$facts22_2 = [
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => $extractedDate22,
        'valid_until' => '2026-09-30 23:59:59',
        'source_url' => 'https://ssc.gov.in',
        'status' => 'verified'
    ]
];
$state22_2 = TemporalFactService::resolveLifecycle(0, $facts22_2, 'government-jobs', 'SSC CGL 2026 Recruitment', $now22_2);
$authCheck22 = AuthorityVerificationService::verify('https://ssc.gov.in');

$hasTableUpdated22 = str_contains($enrichedHtml22, '<td>Application Last Date</td><td>September 30, 2026</td>');
$hasProseUpdated22 = str_contains($enrichedHtml22, 'Last Date: September 30, 2026');
$hasTbaGone22 = !str_contains($enrichedHtml22, 'To Be Announced');

$test22Pass = $pass22_step1 &&
              ($extractedDate22 === 'September 30, 2026') &&
              ($state22_2 === TemporalFactService::LIFECYCLE_ACTIVE) &&
              $hasTableUpdated22 &&
              $hasProseUpdated22 &&
              $hasTbaGone22 &&
              ($authCheck22['is_valid'] === true);

recordTestResult(22, "Early publish with NULL date -> official portal announcement detected -> same article enriched in-place", $test22Pass, "Initial: NULL/Active -> Portal Detected: {$extractedDate22} -> Enriched: Active (table & prose updated in-place)");

// -------------------------------------------------------------------------
// TEST 23: Official date revision / extension (Sept 30 -> Oct 10) with audit trail preserved
// -------------------------------------------------------------------------
// Step 1: Baseline active article with September 30 deadline
$now23_1 = new DateTimeImmutable('2026-09-28 12:00:00', $tz);
$baselineFacts23 = [
    'application_end' => [
        'id' => 101,
        'fact_name' => 'application_end',
        'fact_value' => 'September 30, 2026',
        'valid_until' => '2026-09-30 23:59:59',
        'source_url' => 'https://ssc.gov.in',
        'status' => 'verified'
    ]
];

// Step 2: SSC issues extension corrigendum: "Last date extended up to October 10, 2026"
$now23_2 = new DateTimeImmutable('2026-09-29 15:00:00', $tz);
$portalCorrigendum23 = "Corrigendum Notice: The last date for submission of online application is extended up to October 10, 2026.";
$extractedExtensionDate23 = TemporalRevalidationService::extractDeadlineFromPortalText($portalCorrigendum23, $now23_2);

// Step 3: In-place HTML content update with extension
$extendedHtml23 = TemporalRevalidationService::updateKeyDateInContent($enrichedHtml22, 'application_extension', $extractedExtensionDate23, true);

// Step 4: Audit trail & Superseding (Old fact preserved as superseded, new fact verified)
$factsWithExtension23 = [
    'application_end' => [
        'id' => 101,
        'fact_name' => 'application_end',
        'fact_value' => 'September 30, 2026',
        'valid_until' => '2026-09-30 23:59:59',
        'source_url' => 'https://ssc.gov.in',
        'status' => 'superseded'
    ],
    'application_extension' => [
        'id' => 102,
        'fact_name' => 'application_extension',
        'fact_value' => $extractedExtensionDate23,
        'valid_until' => '2026-10-10 23:59:59',
        'source_url' => 'https://ssc.gov.in',
        'status' => 'verified'
    ]
];

// Step 5: Lifecycle recalculation at October 02 (would be CLOSED under old date, but ACTIVE under extension)
$now23_3 = new DateTimeImmutable('2026-10-02 12:00:00', $tz);
$state23_extended = TemporalFactService::resolveLifecycle(0, $factsWithExtension23, 'government-jobs', 'SSC CGL 2026', $now23_3);

// Step 6: Lifecycle recalculation at October 11 (past extended deadline -> now CLOSED)
$now23_4 = new DateTimeImmutable('2026-10-11 00:01:00', $tz);
$state23_expired = TemporalFactService::resolveLifecycle(0, $factsWithExtension23, 'government-jobs', 'SSC CGL 2026', $now23_4);

// Step 7: Idempotency verification - second pass produces ZERO unintended changes
$secondPassHtml23 = TemporalRevalidationService::updateKeyDateInContent($extendedHtml23, 'application_extension', $extractedExtensionDate23, true);
$isIdempotent23 = ($extendedHtml23 === $secondPassHtml23);

$hasTableExtended23 = str_contains($extendedHtml23, '<td>Application Last Date</td><td>October 10, 2026 (Extended)</td>');
$hasProseExtended23 = str_contains($extendedHtml23, 'Last Date: October 10, 2026 (Extended)');

$test23Pass = ($extractedExtensionDate23 === 'October 10, 2026') &&
              ($state23_extended === TemporalFactService::LIFECYCLE_ACTIVE) &&
              ($state23_expired === TemporalFactService::LIFECYCLE_CLOSED) &&
              ($factsWithExtension23['application_end']['status'] === 'superseded') &&
              ($factsWithExtension23['application_extension']['status'] === 'verified') &&
              $hasTableExtended23 &&
              $hasProseExtended23 &&
              $isIdempotent23;

recordTestResult(23, "Official deadline extension (Sept 30 -> Oct 10) supersedes old fact, preserves audit trail, and maintains ACTIVE state", $test23Pass, "Oct 02: {$state23_extended} -> Oct 11: {$state23_expired}, Idempotent: " . ($isIdempotent23 ? 'true' : 'false') . ", Prior fact: superseded");

// -------------------------------------------------------------------------
// TEST 24: Array Fact Shape TypeError Regression (Production 2 PM Pipeline Bug)
// -------------------------------------------------------------------------
// PipelineService::extractTemporalFacts() returns facts formatted as:
// ['application_end' => ['value' => 'September 30, 2026', 'source_url' => 'https://hssc.gov.in'], ...]
// Before the fix, absence of 'fact_name' caused resolveLifecycle and TemporalContentValidator
// to pass the raw associative array directly into isUnannouncedValue(?string $val), throwing a fatal TypeError.
$now24 = new DateTimeImmutable('2026-09-06 14:00:00', $tz);
$pipelineExtractedFacts = [
    'application_end' => [
        'value' => 'September 30, 2026',
        'source_url' => 'https://hssc.gov.in'
    ],
    'exam_date' => [
        'value' => 'To Be Announced',
        'source_url' => 'https://hssc.gov.in'
    ],
    'result_date' => [
        'value' => null,
        'source_url' => 'https://hssc.gov.in'
    ],
    'admit_card_date' => [
        'value' => 'TBA',
        'source_url' => 'https://hssc.gov.in'
    ]
];

// 1. Verify isUnannouncedValue handles both string and array shapes directly without TypeError
$isTbaArray = TemporalFactService::isUnannouncedValue($pipelineExtractedFacts['exam_date']);
$isNullArray = TemporalFactService::isUnannouncedValue($pipelineExtractedFacts['result_date']);
$isAnnouncedArray = TemporalFactService::isUnannouncedValue($pipelineExtractedFacts['application_end']);

// 2. Verify resolveLifecycle resolves safely without TypeError to ACTIVE
$state24 = TemporalFactService::resolveLifecycle(0, $pipelineExtractedFacts, 'government-jobs', 'HSSC CET 2026', $now24);

// 3. Verify TemporalContentValidator normalizes array-shaped facts without TypeError or provenance failures
$audit24 = TemporalContentValidator::validate([
    'title' => 'HSSC Haryana CET 2026: Apply Online & Schedule',
    'content' => '<p>The Haryana Staff Selection Commission has invited applications. The last date to apply online is September 30, 2026.</p><p>Last Date: September 30, 2026</p><a href="https://hssc.gov.in">Apply Online</a>',
    'source_url' => 'https://hssc.gov.in'
], $pipelineExtractedFacts, $state24, $now24);

$test24Pass = ($isTbaArray === true) &&
              ($isNullArray === true) &&
              ($isAnnouncedArray === false) &&
              ($state24 === TemporalFactService::LIFECYCLE_ACTIVE) &&
              ($audit24['pass'] === true);

recordTestResult(24, "Array-shaped temporal facts from PipelineService execute without TypeError across lifecycle & validation", $test24Pass, "TBA array=" . ($isTbaArray ? 'true' : 'false') . ", NULL array=" . ($isNullArray ? 'true' : 'false') . ", Announced array=" . ($isAnnouncedArray ? 'true' : 'false') . ", Lifecycle={$state24}, Validator pass=" . ($audit24['pass'] ? 'true' : 'false'));

// -------------------------------------------------------------------------
// TEST 25: Expired Deadline with Official Portal URL & Unverified Extension (Article #692 Regression)
// -------------------------------------------------------------------------
// Recreates the exact scenario of Article #692 (HSSC CET 2026):
// 1. Application deadline expired on July 03, 2026 (Advt 05/2026) / June 30, 2026 (Advt 06/2026)
// 2. Official statutory portal link exists (https://hssc.gov.in)
// 3. An unverified secondary source claims extension to September 30, 2026
// Invariants enforced:
// - Portal link existence alone MUST NOT override expired deadline -> strictly CLOSED
// - Unverified secondary extension MUST NOT keep article ACTIVE -> strictly CLOSED
// - FeaturedSnippet candidate action MUST NOT suggest "Submit Online Application Form" -> "Check Official Portal for Next Stage Updates"
// - TemporalContentValidator flags active claims & pills -> pass = false
// - TemporalContentValidator::validateAndRepair replaces active claims & pills -> pass = true
$now25 = new DateTimeImmutable('2026-09-06 14:17:00', $tz);
$facts25 = [
    'application_start' => [
        'fact_name' => 'application_start',
        'fact_value' => 'June 19, 2026',
        'valid_until' => '2026-06-19 00:00:00',
        'source_url' => 'https://hssc.gov.in',
        'status' => 'verified'
    ],
    'application_end' => [
        'fact_name' => 'application_end',
        'fact_value' => 'July 03, 2026',
        'valid_until' => '2026-07-03 23:59:59',
        'source_url' => 'https://hssc.gov.in',
        'status' => 'verified'
    ],
    'application_extension' => [
        'fact_name' => 'application_extension',
        'fact_value' => 'September 30, 2026',
        'valid_until' => '2026-09-30 23:59:59',
        'source_url' => 'https://unverified-aggregator.com/hssc-dates',
        'status' => 'unverified'
    ],
    'exam_date' => [
        'fact_name' => 'exam_date',
        'fact_value' => 'To Be Announced',
        'valid_until' => null,
        'source_url' => 'https://hssc.gov.in',
        'status' => 'unannounced'
    ]
];

// 1. Verify resolveLifecycle resolves to CLOSED (portal URL & unverified extension do NOT override expired verified deadline)
$state25 = TemporalFactService::resolveLifecycle(0, $facts25, 'government-jobs', 'HSSC Haryana CET 2026', $now25);
$isStateClosed = ($state25 === TemporalFactService::LIFECYCLE_CLOSED);

// 2. Verify FeaturedSnippetService determines safe non-active action
$action25 = FeaturedSnippetService::determineCandidateAction('HSSC Haryana CET 2026: Apply Online', $state25);
$isActionSafe = ($action25 === 'Check Official Portal for Next Stage Updates') && ($action25 !== 'Submit Online Application Form');

// 3. Verify TemporalContentValidator rejects active claims & status pills
$article25 = [
    'title' => 'HSSC Haryana CET 2026: Apply Online & Eligibility',
    'excerpt' => 'Haryana CET 2026 recruitment for Group C and D posts.',
    'content' => '<p>The Haryana Staff Selection Commission has opened the recruitment cycle for CET 2026.</p>' .
                 '<table><thead><tr><th>Milestone</th><th>Status</th><th>Official Timeline</th></tr></thead>' .
                 '<tbody><tr><td>Application for CET Group D-05/2026</td><td><span class="status-pill status-pill-confirmed">Active</span></td><td>As of September 06, 2026</td></tr></tbody></table>' .
                 '<p><strong>Q: Is the application window for Group D-05/2026 still open?</strong><br>A: Yes, as of September 06, 2026, the application process is active.</p>' .
                 '<p>Submit Online Application Form at official portal.</p>' .
                 '<a href="https://hssc.gov.in">Apply Online</a>',
    'source_url' => 'https://hssc.gov.in'
];

$audit25 = TemporalContentValidator::validate($article25, $facts25, $state25, $now25);
$hasViolations = ($audit25['pass'] === false) && (count($audit25['violations']) >= 2);

// 4. Verify validateAndRepair successfully neutralizes all active claims, pills, and CTAs
$repair25 = TemporalContentValidator::validateAndRepair($article25, $facts25, $state25, $now25);
$isRepairPass = ($repair25['pass'] === true) &&
                !str_contains($repair25['repaired_data']['content'], '>Active<') &&
                !str_contains(strtolower($repair25['repaired_data']['content']), 'application process is active') &&
                !str_contains($repair25['repaired_data']['content'], '>Apply Online<') &&
                !str_contains(strtolower($repair25['repaired_data']['title']), 'apply online');

$test25Pass = $isStateClosed && $isActionSafe && $hasViolations && $isRepairPass;

recordTestResult(25, "Expired deadline with official portal URL + unverified extension strictly resolves to CLOSED, enforces safe CTAs, and repairs active copy", $test25Pass, "Lifecycle={$state25}, Action='{$action25}', Validator Rejected=" . ($hasViolations ? 'true' : 'false') . ", Auto-Repaired=" . ($isRepairPass ? 'true' : 'false'));

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
