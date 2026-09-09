<?php
/**
 * Test Suite: Hallucination Prevention & Fact Completeness Engine
 */

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\AI\Gemini;
use App\AI\ArticleGenerator;
use App\Services\ArticleIntent;
use App\Services\FactCompletenessRules;
use App\Services\HallucinationGuard;
use App\Services\AuthorityPortalResolverService;

echo "====================================================================\n";
echo "🧪 RUNNING HALLUCINATION PREVENTION & AUTHORITY RESOLVER TEST SUITE\n";
echo "====================================================================\n\n";

$allPassed = true;

// ---------------------------------------------------------------------
// TEST 1: FactCompletenessRules Unit Test
// ---------------------------------------------------------------------
echo ">>> TEST 1: FactCompletenessRules Evaluation...\n";

// Case A: Missing required facts for RECRUITMENT
$emptyFacts = [
    'application_start_date' => 'September 09, 2026',
    'dates_schedule' => [
        ['milestone' => 'Last Date to Apply', 'date' => 'To Be Announced', 'status' => 'Awaiting Official Circular']
    ]
];
$resA = FactCompletenessRules::evaluate(ArticleIntent::RECRUITMENT, $emptyFacts);
if (!$resA->isComplete && in_array('application_deadline', $resA->missingFacts) && in_array('fee_amount_general', $resA->missingFacts)) {
    echo "  ✅ Case A PASS: Incomplete facts correctly blocked. Missing: " . implode(', ', $resA->missingFacts) . "\n";
} else {
    echo "  ❌ Case A FAIL: Should have blocked incomplete recruitment facts.\n";
    $allPassed = false;
}

// Case B: Complete facts for RECRUITMENT
$completeFacts = [
    'application_start_date' => 'September 08, 2026',
    'application_deadline' => 'October 07, 2026',
    'fee_amount_general' => '₹2,000'
];
$resB = FactCompletenessRules::evaluate(ArticleIntent::RECRUITMENT, $completeFacts);
if ($resB->isComplete && empty($resB->missingFacts)) {
    echo "  ✅ Case B PASS: Complete facts correctly approved.\n";
} else {
    echo "  ❌ Case B FAIL: Complete facts were rejected: " . implode(', ', $resB->missingFacts) . "\n";
    $allPassed = false;
}

// ---------------------------------------------------------------------
// TEST 2: HallucinationGuard Unit Test
// ---------------------------------------------------------------------
echo "\n>>> TEST 2: HallucinationGuard Suspicious Today-Date Detection...\n";

$todayFormatted = date('F d, Y');
$suspiciousTable = [
    'application_start_date' => $todayFormatted,
    'application_deadline' => 'October 07, 2026'
];
// Source does NOT contain today's date
$sourceWithoutToday = "Notification released for Assistant Professor posts. Exam in November.";
$issues = HallucinationGuard::checkForSuspiciousTodayDate($suspiciousTable, $sourceWithoutToday);

if (!empty($issues) && str_contains($issues[0], 'BLOCKING: Suspicious')) {
    echo "  ✅ PASS: Suspicious today-date hallucination correctly flagged: {$issues[0]}\n";
} else {
    echo "  ❌ FAIL: HallucinationGuard failed to detect fabricated today date.\n";
    $allPassed = false;
}

// Case where today's date IS in source text (genuine circular release date)
$sourceWithToday = "Official circular issued on {$todayFormatted} by the Commission.";
$issuesValid = HallucinationGuard::checkForSuspiciousTodayDate($suspiciousTable, $sourceWithToday);
if (empty($issuesValid)) {
    echo "  ✅ PASS: Legitimate date present in source is correctly permitted.\n";
} else {
    echo "  ❌ FAIL: Legitimate date was falsely blocked.\n";
    $allPassed = false;
}

// ---------------------------------------------------------------------
// TEST 3: End-to-End Regression Test Replicating Today's Incident
// ---------------------------------------------------------------------
echo "\n>>> TEST 3: E2E Regression Test Replicating Today's Empty-Portal Incident...\n";

$generator = new ArticleGenerator();
// Simulate empty facts as happened with UPESSC today
$emptySourceData = [
    'keyword' => 'UPESSC Assistant Professor Recruitment 2026',
    'source_name' => 'UPESSC (Statutory Examination Body)',
    'source_url' => '',
    'reference' => '',
    'notes' => 'UPESSC Assistant Professor Recruitment 2026 notification released for 1,936 posts',
    'verified_facts' => [
        'authority_name' => 'UPESSC (Statutory Examination Body)',
        'official_portal' => '',
        'dates_schedule' => []
    ]
];

// PHP-constructed dates table from empty facts
$datesTable = $generator->buildDatesTableFromFacts($emptySourceData['verified_facts'], ArticleIntent::RECRUITMENT);

echo "  Checking PHP-constructed dates table for empty facts:\n";
print_r($datesTable);

$todayVariant = date('F d, Y');
$hasHallucinatedDate = false;
foreach ($datesTable as $field => $val) {
    if (stripos($val, $todayVariant) !== false) {
        $hasHallucinatedDate = true;
    }
}

$allTba = (
    $datesTable['application_start_date'] === ArticleGenerator::NOT_YET_ANNOUNCED_LABEL &&
    $datesTable['application_deadline'] === ArticleGenerator::NOT_YET_ANNOUNCED_LABEL &&
    $datesTable['fee_amount_general'] === ArticleGenerator::NOT_YET_ANNOUNCED_LABEL
);

if ($allTba && !$hasHallucinatedDate) {
    echo "  ✅ PASS: Structural Decoupling Success! Empty facts strictly produce '" . ArticleGenerator::NOT_YET_ANNOUNCED_LABEL . "' with ZERO dates matching today.\n";
} else {
    echo "  ❌ FAIL: Dates table contained unexpected values.\n";
    $allPassed = false;
}

// Assert FactCompletenessRules blocks this
$completenessCheck = FactCompletenessRules::evaluate(ArticleIntent::RECRUITMENT, $emptySourceData['verified_facts']);
if (!$completenessCheck->isComplete) {
    echo "  ✅ PASS: FactCompletenessRules strictly blocks empty-portal topic before generation.\n";
} else {
    echo "  ❌ FAIL: FactCompletenessRules failed to block empty topic.\n";
    $allPassed = false;
}

// ---------------------------------------------------------------------
// TEST 4: AuthorityPortalResolverService Dynamic Discovery Test (Point #1 & #4)
// ---------------------------------------------------------------------
echo "\n>>> TEST 4: Dynamic Authority Discovery & Fail-Closed Verification (Unseeded Authority: OPSC)...\n";

// Remove OPSC from DB if present to force the discovery path
Database::execute("DELETE FROM authority_portals WHERE acronym = 'OPSC'");

// Use mock handler to simulate Gemini returning Grounding Metadata with real opsc.gov.in
Gemini::setMockHandler(function(string $prompt, array $options) {
    return [
        'text' => 'The official portal for Odisha Public Service Commission (OPSC) is https://opsc.gov.in.',
        'status' => 'success',
        'tokens_used' => 50,
        'grounding_metadata' => [
            'groundingChunks' => [
                [
                    'web' => [
                        'uri' => 'https://opsc.gov.in',
                        'title' => 'Odisha Public Service Commission'
                    ]
                ]
            ]
        ]
    ];
});

$resolver = new AuthorityPortalResolverService();
$resolved = $resolver->resolve('OPSC', 'Odisha Public Service Commission');

// Reset mock handler
Gemini::setMockHandler(null);

if ($resolved && !empty($resolved['portal']) && str_contains($resolved['portal'], 'opsc.gov.in')) {
    echo "  ✅ PASS: Dynamic Grounded Discovery extracted: {$resolved['portal']}\n";
    echo "  ✅ Verification Status: {$resolved['verification_status']} (Discovery Method: {$resolved['discovery_method']})\n";
    
    // Verify DB entry was written
    $dbRecord = Database::fetchOne("SELECT * FROM authority_portals WHERE acronym = 'OPSC'");
    if ($dbRecord && $dbRecord['verification_status'] === 'pending_review') {
        echo "  ✅ PASS: Verified in DB with status 'pending_review' (Point #2: never auto-published without human verification)!\n";
    } else {
        echo "  ❌ FAIL: DB record status not 'pending_review'.\n";
        $allPassed = false;
    }
} else {
    echo "  ❌ FAIL: Dynamic discovery failed to resolve opsc.gov.in.\n";
    $allPassed = false;
}

// Sub-Test 4B: Fail-Closed cURL Verification Test (Point #4)
echo "\n>>> TEST 4B: Fail-Closed cURL Verification on Dead/Untrusted Domain...\n";
$deadResult = $resolver->verifyPageMentionsAuthority('https://this-domain-does-not-exist-at-all-12345.gov.in', 'XYZ', 'Unknown');
if ($deadResult === false) {
    echo "  ✅ PASS: Dead/unreachable portal failed closed (returned false) as required.\n";
} else {
    echo "  ❌ FAIL: Dead portal should have returned false.\n";
    $allPassed = false;
}

echo "\n====================================================================\n";
if ($allPassed) {
    echo "🎉 ALL 4 TESTS PASSED! Hallucination Prevention Engine is 100% Validated.\n";
} else {
    echo "⚠️ SOME TESTS FAILED. Review output above.\n";
}
echo "====================================================================\n";
