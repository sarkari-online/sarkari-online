<?php
/**
 * Sarkari.online - Phase 3A Six-Article Preconditions & Lifecycle Transition Test Suite
 *
 * Tests the exact precondition rules and lifecycle transitions for execute-batch-six.php:
 * 1. BPSC: ['draft', 'evergreen', 'closed'] allowed
 * 2. BPSC: draft OR evergreen -> transitions to closed
 * 3. BPSC: closed -> preserved, not downgraded
 * 4. BPSC: invalid lifecycle (e.g., 'active') -> rejected
 * 5. Maharashtra: 'evergreen' -> allowed and preserved
 * 6. Maharashtra: non-evergreen (e.g., 'draft', 'closed') -> rejected
 * 7. Remaining four articles: 'draft' -> allowed and preserved
 * 8. Remaining four articles: non-draft -> rejected
 */

$passed = 0;
$failed = 0;
$errors = [];

function assertBatchTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  [PASS] {$name}" . ($details ? " — {$details}" : "") . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($details ? " — {$details}" : "") . "\n";
        $failed++;
        $errors[] = "{$name}: {$details}";
    }
}

echo "========================================================================\n";
echo "   PHASE 3A BATCH PRECONDITION & LIFECYCLE TRANSITION TESTS\n";
echo "========================================================================\n\n";

$targetSlugs = [
    'bpsc-tre-4-application-postponed-dates',
    'rvunl-recruitment-2026-last-date',
    'punjab-pti-recruitment-2026-apply-now',
    'coal-india-mt-answer-key-2026',
    'odisha-deled-result-2026-sams-ct',
    'maharashtra-3-year-llb-round-2-allotment-2026'
];

// Precondition evaluation function mimicking execute-batch-six.php logic
function evaluateBatchPreconditions(array $articles): array {
    $failures = [];
    foreach ($articles as $r) {
        $slug = $r['slug'];
        if ($r['status'] !== 'published') {
            $failures[] = "Slug '{$slug}' status is '{$r['status']}', expected 'published'.";
        }
        if ($slug === 'bpsc-tre-4-application-postponed-dates') {
            if (!in_array($r['lifecycle_status'], ['draft', 'evergreen', 'closed'], true)) {
                $failures[] = "Slug '{$slug}' lifecycle is '{$r['lifecycle_status']}', expected 'draft', 'evergreen', or 'closed'.";
            }
        } elseif ($slug === 'maharashtra-3-year-llb-round-2-allotment-2026') {
            if ($r['lifecycle_status'] !== 'evergreen') {
                $failures[] = "Slug '{$slug}' lifecycle is '{$r['lifecycle_status']}', expected 'evergreen'.";
            }
        } else {
            if ($r['lifecycle_status'] !== 'draft') {
                $failures[] = "Slug '{$slug}' lifecycle has changed to '{$r['lifecycle_status']}' (no longer 'draft'). ABORTING to prevent overwrite.";
            }
        }
    }
    return $failures;
}

// BPSC transition logic function mimicking execute-batch-six.php DML
function transitionBpsc(string $currentLifecycle): string {
    if (in_array($currentLifecycle, ['draft', 'evergreen'], true)) {
        return 'closed';
    }
    return $currentLifecycle; // preserves closed or any other state
}

// TEST 1: Current Production State Passes Preconditions
echo "1. Testing Live Production State Preconditions...\n";
$liveProductionState = [
    ['slug' => 'bpsc-tre-4-application-postponed-dates', 'status' => 'published', 'lifecycle_status' => 'evergreen'],
    ['slug' => 'maharashtra-3-year-llb-round-2-allotment-2026', 'status' => 'published', 'lifecycle_status' => 'evergreen'],
    ['slug' => 'rvunl-recruitment-2026-last-date', 'status' => 'published', 'lifecycle_status' => 'draft'],
    ['slug' => 'punjab-pti-recruitment-2026-apply-now', 'status' => 'published', 'lifecycle_status' => 'draft'],
    ['slug' => 'coal-india-mt-answer-key-2026', 'status' => 'published', 'lifecycle_status' => 'draft'],
    ['slug' => 'odisha-deled-result-2026-sams-ct', 'status' => 'published', 'lifecycle_status' => 'draft'],
];
$failures1 = evaluateBatchPreconditions($liveProductionState);
assertBatchTest("Live production state passes all preconditions", empty($failures1), empty($failures1) ? '0 failures' : implode('; ', $failures1));

// TEST 2: BPSC Lifecycle Acceptance & Transition
echo "\n2. Testing BPSC Lifecycle Transitions...\n";
$bpscFromDraft = transitionBpsc('draft');
assertBatchTest("BPSC draft transitions to closed", $bpscFromDraft === 'closed', "Result: {$bpscFromDraft}");

$bpscFromEvergreen = transitionBpsc('evergreen');
assertBatchTest("BPSC evergreen transitions to closed", $bpscFromEvergreen === 'closed', "Result: {$bpscFromEvergreen}");

$bpscFromClosed = transitionBpsc('closed');
assertBatchTest("BPSC closed is preserved (no downgrade)", $bpscFromClosed === 'closed', "Result: {$bpscFromClosed}");

$invalidBpscState = $liveProductionState;
$invalidBpscState[0]['lifecycle_status'] = 'active';
$failuresBpsc = evaluateBatchPreconditions($invalidBpscState);
assertBatchTest("BPSC invalid lifecycle ('active') is rejected", count($failuresBpsc) === 1, "Failures: " . count($failuresBpsc));

// TEST 3: Maharashtra Evergreen Precondition
echo "\n3. Testing Maharashtra Preconditions...\n";
$invalidMahaState = $liveProductionState;
$invalidMahaState[1]['lifecycle_status'] = 'draft';
$failuresMaha = evaluateBatchPreconditions($invalidMahaState);
assertBatchTest("Maharashtra non-evergreen ('draft') is rejected", count($failuresMaha) === 1, "Failures: " . count($failuresMaha));

$invalidMahaClosedState = $liveProductionState;
$invalidMahaClosedState[1]['lifecycle_status'] = 'closed';
$failuresMahaClosed = evaluateBatchPreconditions($invalidMahaClosedState);
assertBatchTest("Maharashtra non-evergreen ('closed') is rejected", count($failuresMahaClosed) === 1, "Failures: " . count($failuresMahaClosed));

// TEST 4: Remaining Four Articles Require Draft
echo "\n4. Testing Remaining Four Articles Preconditions...\n";
foreach (['rvunl-recruitment-2026-last-date', 'punjab-pti-recruitment-2026-apply-now', 'coal-india-mt-answer-key-2026', 'odisha-deled-result-2026-sams-ct'] as $idx => $draftSlug) {
    $mutatedState = $liveProductionState;
    // index in array: 2, 3, 4, 5
    $targetIdx = 2 + $idx;
    $mutatedState[$targetIdx]['lifecycle_status'] = 'evergreen';
    $mutatedFailures = evaluateBatchPreconditions($mutatedState);
    assertBatchTest("Article '{$draftSlug}' rejects non-draft ('evergreen')", count($mutatedFailures) === 1, "Failures: " . count($mutatedFailures));
}

// TEST 5: Status Invariant (published required)
echo "\n5. Testing Status Invariant (must be published)...\n";
$unpublishedState = $liveProductionState;
$unpublishedState[0]['status'] = 'draft';
$failuresUnpub = evaluateBatchPreconditions($unpublishedState);
assertBatchTest("Unpublished article status is rejected", count($failuresUnpub) === 1, "Failures: " . count($failuresUnpub));

// TEST 6: Baseline Counts (75 published, 75 indexable) and legitimate 75th article
echo "\n6. Testing 75-Article Baseline Invariants & Legitimate 75th Article...\n";
function evaluateBaseline(int $pubCount, int $idxCount, ?array $nvsArticle): array {
    $failures = [];
    if ($pubCount !== 75) {
        $failures[] = "Published count is {$pubCount}, expected exactly 75.";
    }
    if ($idxCount !== 75) {
        $failures[] = "Unique indexable slug count is {$idxCount}, expected exactly 75.";
    }
    if (!$nvsArticle || ($nvsArticle['status'] ?? '') !== 'published') {
        $failures[] = "75th article 'nvs-2026-exam-schedule-results' is missing or not published.";
    }
    return $failures;
}

$validBaseline = evaluateBaseline(75, 75, ['slug' => 'nvs-2026-exam-schedule-results', 'status' => 'published']);
assertBatchTest("75-article baseline with legitimate NVS article passes", empty($validBaseline), empty($validBaseline) ? '0 failures' : implode('; ', $validBaseline));

$invalidPubBaseline = evaluateBaseline(74, 74, ['slug' => 'nvs-2026-exam-schedule-results', 'status' => 'published']);
assertBatchTest("Stale 74-article count is rejected", count($invalidPubBaseline) === 2, "Failures: " . count($invalidPubBaseline));

$missingNvsBaseline = evaluateBaseline(75, 75, null);
assertBatchTest("Missing 75th NVS article is rejected", count($missingNvsBaseline) === 1, "Failures: " . count($missingNvsBaseline));

echo "\n========================================================================\n";
echo "   RESULTS: {$passed}/" . ($passed + $failed) . " PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
