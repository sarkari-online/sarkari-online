<?php
declare(strict_types=1);

/**
 * Sarkari.online - Programmatic Content Modules Data-Readiness Audit
 * 
 * Verifies readiness of exam_cycles data for:
 * 1. Document Checklist Pages (/documents-required/{exam-slug}-{year}/)
 * 2. Keyword-Accurate Application Guides (/how-to-apply/{exam-slug}-{year}/)
 * 3. "Exam Ke Baad Kya" Post-Exam Timeline (/after-exam/{exam-slug}-{year}/)
 * 
 * Under the ABSOLUTE DATA-CONFIDENCE GATE:
 * - Content must NEVER publish if phase_confidence = 'INFERRED' & required fact is missing
 * - Content must NEVER publish if last_verified_at > 30 days old
 * - Placeholders ("TBA", "Awaited", etc.) are strictly forbidden
 * 
 * Usage:
 *   php cron/audit-data-readiness-for-content.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "🔍 SARKARI.ONLINE — DATA-READINESS FORENSIC AUDIT (EXAM CYCLES)\n";
echo "================================================================================\n";
echo "Audit Execution Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "Enforcing: ABSOLUTE DATA-CONFIDENCE GATE (Strict Zero-Speculation Mode)\n";
echo "--------------------------------------------------------------------------------\n\n";

// -----------------------------------------------------------------------------
// 0. VERIFY SCHEMA INTEGRITY
// -----------------------------------------------------------------------------
try {
    $tables = Database::fetchAll("SHOW TABLES LIKE 'exam_cycles'");
    if (empty($tables)) {
        echo "❌ CRITICAL ERROR: 'exam_cycles' table does not exist in the database!\n";
        echo "Please run database/migrations/create_exam_lifecycle_tables.php first.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "\n";
    exit(1);
}

// -----------------------------------------------------------------------------
// 1. SCAN EXAM CYCLES
// -----------------------------------------------------------------------------
try {
    $cycles = Database::fetchAll("SELECT * FROM exam_cycles ORDER BY authority_code ASC, exam_name ASC, cycle_year DESC");
} catch (\Throwable $e) {
    echo "❌ Failed to query exam_cycles: " . $e->getMessage() . "\n";
    exit(1);
}

$totalCycles = count($cycles);
echo "Total Exam Cycles in Database: {$totalCycles}\n\n";

if ($totalCycles === 0) {
    echo "⚠️ Warning: Zero exam cycles found. Run cron/backfill-exam-cycles.php first.\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// 2. METRIC 1: CONFIDENCE BREAKDOWN & 30-DAY STALENESS GATE
// -----------------------------------------------------------------------------
$confidenceCounts = [
    'VERIFIED' => 0,
    'INFERRED' => 0,
    'STALE'    => 0,
    'OTHER'    => 0,
];

$freshnessCounts = [
    'FRESH_LE_30D' => 0, // <= 30 days old
    'STALE_GT_30D' => 0, // > 30 days old
    'NEVER_VERIFIED' => 0, // null last_verified_at
];

$now = time();
$thirtyDaysSeconds = 30 * 86400;

foreach ($cycles as $c) {
    $conf = strtoupper((string)($c['phase_confidence'] ?? 'INFERRED'));
    if (isset($confidenceCounts[$conf])) {
        $confidenceCounts[$conf]++;
    } else {
        $confidenceCounts['OTHER']++;
    }

    $lastVerified = $c['last_verified_at'] ?? null;
    if (empty($lastVerified)) {
        $freshnessCounts['NEVER_VERIFIED']++;
    } else {
        $verifiedTs = strtotime($lastVerified);
        if ($verifiedTs !== false && ($now - $verifiedTs) <= $thirtyDaysSeconds) {
            $freshnessCounts['FRESH_LE_30D']++;
        } else {
            $freshnessCounts['STALE_GT_30D']++;
        }
    }
}

$verifiedPct = round(($confidenceCounts['VERIFIED'] / $totalCycles) * 100, 1);
$inferredPct = round(($confidenceCounts['INFERRED'] / $totalCycles) * 100, 1);
$staleConfPct = round(($confidenceCounts['STALE'] / $totalCycles) * 100, 1);

echo "📊 [DIMENSION 1] PHASE CONFIDENCE & FRESHNESS AUDIT\n";
echo "--------------------------------------------------------------------------------\n";
echo sprintf("  • VERIFIED Confidence : %4d / %-4d (%5.1f%%)  <-- Eligible for Task 1 & 2\n", $confidenceCounts['VERIFIED'], $totalCycles, $verifiedPct);
echo sprintf("  • INFERRED Confidence : %4d / %-4d (%5.1f%%)  <-- BLOCKED by Gate until verified\n", $confidenceCounts['INFERRED'], $totalCycles, $inferredPct);
echo sprintf("  • STALE Confidence    : %4d / %-4d (%5.1f%%)\n", $confidenceCounts['STALE'], $totalCycles, $staleConfPct);
echo "\n  Freshness (< 30 days gate):\n";
echo sprintf("  • Fresh (<= 30 days)  : %4d / %-4d\n", $freshnessCounts['FRESH_LE_30D'], $totalCycles);
echo sprintf("  • Stale (> 30 days)   : %4d / %-4d\n", $freshnessCounts['STALE_GT_30D'], $totalCycles);
echo sprintf("  • Never Verified      : %4d / %-4d\n\n", $freshnessCounts['NEVER_VERIFIED'], $totalCycles);

// -----------------------------------------------------------------------------
// 3. METRIC 2: REQUIRED DOCUMENTS & FACTS_JSON AUDIT (TASK 1 READINESS)
// -----------------------------------------------------------------------------
$factsJsonCount = 0;
$hasDocsCount = 0;
$docsGte3Count = 0;
$docsLt3Count = 0;
$factKeyFrequencies = [];

foreach ($cycles as $c) {
    $factsRaw = $c['facts_json'] ?? null;
    if (empty($factsRaw)) {
        continue;
    }
    $factsJsonCount++;

    $facts = is_array($factsRaw) ? $factsRaw : json_decode($factsRaw, true);
    if (!is_array($facts)) {
        continue;
    }

    foreach (array_keys($facts) as $key) {
        $factKeyFrequencies[$key] = ($factKeyFrequencies[$key] ?? 0) + 1;
    }

    if (!empty($facts['required_documents']) && is_array($facts['required_documents'])) {
        $hasDocsCount++;
        $docCount = count($facts['required_documents']);
        if ($docCount >= 3) {
            $docsGte3Count++;
        } else {
            $docsLt3Count++;
        }
    }
}

echo "📋 [DIMENSION 2] TASK 1 READINESS — DOCUMENT CHECKLISTS\n";
echo "--------------------------------------------------------------------------------\n";
echo sprintf("  • Cycles with facts_json populated : %4d / %-4d (%5.1f%%)\n", $factsJsonCount, $totalCycles, round(($factsJsonCount / $totalCycles) * 100, 1));
echo sprintf("  • Cycles with 'required_documents' : %4d / %-4d (%5.1f%%)\n", $hasDocsCount, $totalCycles, round(($hasDocsCount / $totalCycles) * 100, 1));
echo sprintf("    - Meeting Threshold (>= 3 docs)  : %4d (Publishable)\n", $docsGte3Count);
echo sprintf("    - Below Threshold   (< 3 docs)   : %4d (Thin content - would fail-close)\n", $docsLt3Count);
echo "\n  Extracted Fact Keys Frequency across cycles:\n";
if (empty($factKeyFrequencies)) {
    echo "  (No fact keys found in any cycle)\n";
} else {
    arsort($factKeyFrequencies);
    foreach ($factKeyFrequencies as $key => $freq) {
        echo sprintf("    - %-26s: %3d cycles\n", $key, $freq);
    }
}
echo "\n";

// -----------------------------------------------------------------------------
// 4. METRIC 3: ACTIVE APPLICATION PHASES (TASK 2 READINESS)
// -----------------------------------------------------------------------------
$applicationOpenCycles = [];
$applicationCorrectionCycles = [];

foreach ($cycles as $c) {
    $phase = $c['current_phase'] ?? '';
    if ($phase === 'APPLICATION_OPEN') {
        $applicationOpenCycles[] = $c;
    } elseif ($phase === 'APPLICATION_CORRECTION') {
        $applicationCorrectionCycles[] = $c;
    }
}

$totalAppPhase = count($applicationOpenCycles) + count($applicationCorrectionCycles);

echo "📝 [DIMENSION 3] TASK 2 READINESS — APPLICATION GUIDES (HOW-TO-APPLY)\n";
echo "--------------------------------------------------------------------------------\n";
echo sprintf("  • Cycles in APPLICATION_OPEN       : %d\n", count($applicationOpenCycles));
echo sprintf("  • Cycles in APPLICATION_CORRECTION : %d\n", count($applicationCorrectionCycles));
echo sprintf("  • Total Active Application Cycles  : %d\n", $totalAppPhase);

if ($totalAppPhase > 0) {
    echo "\n  Active Application Cycle Details:\n";
    $allAppCycles = array_merge($applicationOpenCycles, $applicationCorrectionCycles);
    foreach ($allAppCycles as $ac) {
        $cId = $ac['id'];
        $auth = $ac['authority_code'];
        $name = $ac['exam_name'];
        $yr = $ac['cycle_year'];
        $ph = $ac['current_phase'];
        $cf = $ac['phase_confidence'];
        $ev = !empty($ac['phase_evidence_url']) ? $ac['phase_evidence_url'] : 'None';
        echo sprintf("    [#%d] %-8s | %-32s (%s) | Phase: %-22s | Conf: %-8s | Evidence: %s\n", 
            $cId, $auth, mb_strimwidth($name, 0, 32, '...'), $yr, $ph, $cf, mb_strimwidth($ev, 0, 45, '...')
        );
    }
} else {
    echo "  (No cycles currently in APPLICATION_OPEN or APPLICATION_CORRECTION phase)\n";
}
echo "\n";

// -----------------------------------------------------------------------------
// 5. METRIC 4: HISTORICAL PAST CYCLES PER AUTHORITY (TASK 3 READINESS)
// -----------------------------------------------------------------------------
// Completed/past phases include: CYCLE_CLOSED, FINAL_SELECTION, PET_DV_STAGE, RESULT_DECLARED, FINAL_KEY_RELEASED, EXAM_CONDUCTED
$pastPhases = [
    'CYCLE_CLOSED',
    'FINAL_SELECTION',
    'PET_DV_STAGE',
    'RESULT_DECLARED',
    'FINAL_KEY_RELEASED',
    'EXAM_CONDUCTED'
];

$authorityCycles = [];
$authorityClosedCount = [];
$authorityPastCount = [];

foreach ($cycles as $c) {
    $auth = strtoupper(trim((string)$c['authority_code']));
    if ($auth === '') $auth = 'UNKNOWN';

    if (!isset($authorityCycles[$auth])) {
        $authorityCycles[$auth] = [];
        $authorityClosedCount[$auth] = 0;
        $authorityPastCount[$auth] = 0;
    }
    $authorityCycles[$auth][] = $c;

    $phase = $c['current_phase'] ?? '';
    if ($phase === 'CYCLE_CLOSED') {
        $authorityClosedCount[$auth]++;
    }
    if (in_array($phase, $pastPhases, true)) {
        $authorityPastCount[$auth]++;
    }
}

$eligibleStrictClosed = [];
$eligibleBroadPast = [];

foreach ($authorityCycles as $auth => $cycleList) {
    if ($authorityClosedCount[$auth] >= 2) {
        $eligibleStrictClosed[$auth] = $authorityClosedCount[$auth];
    }
    if ($authorityPastCount[$auth] >= 2) {
        $eligibleBroadPast[$auth] = $authorityPastCount[$auth];
    }
}

echo "⏳ [DIMENSION 4] TASK 3 READINESS — POST-EXAM TIMELINE & HISTORICAL GAPS\n";
echo "--------------------------------------------------------------------------------\n";
echo sprintf("  • Total Unique Authorities tracked : %d\n", count($authorityCycles));
echo sprintf("  • Authorities with >= 2 strictly CLOSED cycles (sample_size >= 2)   : %d\n", count($eligibleStrictClosed));
echo sprintf("  • Authorities with >= 2 past/concluded cycles (exam conducted+)    : %d\n", count($eligibleBroadPast));

echo "\n  Authority Breakdown (Total Cycles | Strictly Closed | Concluded/Past):\n";
ksort($authorityCycles);
foreach ($authorityCycles as $auth => $cycleList) {
    $totalAuthCycles = count($cycleList);
    $closedAuth = $authorityClosedCount[$auth];
    $pastAuth = $authorityPastCount[$auth];
    $status = ($closedAuth >= 2) ? '✅ ELIGIBLE (Strict)' : (($pastAuth >= 2) ? '⚠️ ELIGIBLE (Concluded)' : '❌ INSUFFICIENT (< 2)');
    echo sprintf("    - %-12s: %2d total | %2d closed | %2d past/concluded | %s\n", 
        $auth, $totalAuthCycles, $closedAuth, $pastAuth, $status
    );
}
echo "\n";

// -----------------------------------------------------------------------------
// 6. FULL PHASE DISTRIBUTION ACROSS ALL CYCLES
// -----------------------------------------------------------------------------
$allPhases = [
    'ANNUAL_CALENDAR_ONLY',
    'NOTIFICATION_RELEASED',
    'APPLICATION_OPEN',
    'APPLICATION_CORRECTION',
    'APPLICATION_CLOSED',
    'ADMIT_CARD_AWAITED',
    'ADMIT_CARD_RELEASED',
    'EXAM_SCHEDULED',
    'EXAM_CONDUCTED',
    'ANSWER_KEY_OBJECTION',
    'FINAL_KEY_RELEASED',
    'RESULT_DECLARED',
    'PET_DV_STAGE',
    'FINAL_SELECTION',
    'CYCLE_CLOSED'
];

$phaseCounts = array_fill_keys($allPhases, 0);
foreach ($cycles as $c) {
    $ph = $c['current_phase'] ?? 'ANNUAL_CALENDAR_ONLY';
    if (isset($phaseCounts[$ph])) {
        $phaseCounts[$ph]++;
    } else {
        $phaseCounts[$ph] = ($phaseCounts[$ph] ?? 0) + 1;
    }
}

echo "📈 [DIMENSION 5] COMPLETE LIFECYCLE PHASE DISTRIBUTION\n";
echo "--------------------------------------------------------------------------------\n";
foreach ($phaseCounts as $phaseName => $count) {
    $pct = $totalCycles > 0 ? ($count / $totalCycles) * 100 : 0;
    $bar = str_repeat('■', (int)round($pct / 4));
    echo sprintf("  %-25s : %3d (%5.1f%%)  %s\n", $phaseName, $count, $pct, $bar);
}
echo "\n";

// -----------------------------------------------------------------------------
// 7. CANDIDATE VERIFIED CYCLES READY FOR PILOT / DRY-RUN
// -----------------------------------------------------------------------------
echo "🎯 [DIMENSION 6] CANDIDATE VERIFIED CYCLES (GATE PASS)\n";
echo "--------------------------------------------------------------------------------\n";
$verifiedCycles = array_values(array_filter($cycles, fn($c) => ($c['phase_confidence'] ?? '') === 'VERIFIED'));

if (empty($verifiedCycles)) {
    echo "  ⚠️ No cycles currently have phase_confidence = 'VERIFIED'.\n";
    echo "  Re-verification via PhaseTransitionCheck or AuthorityFactFetcherService is required!\n";
} else {
    echo sprintf("  Found %d VERIFIED cycles. Top candidates for Dry-Runs:\n", count($verifiedCycles));
    $candidatesToShow = array_slice($verifiedCycles, 0, 10);
    foreach ($candidatesToShow as $vc) {
        $cId = $vc['id'];
        $auth = $vc['authority_code'];
        $name = $vc['exam_name'];
        $yr = $vc['cycle_year'];
        $ph = $vc['current_phase'];
        $lv = $vc['last_verified_at'] ?? 'Never';
        $ev = !empty($vc['phase_evidence_url']) ? $vc['phase_evidence_url'] : 'None';
        echo sprintf("  [#%d] %-8s | %-28s | %-4s | %-20s | Verified: %-10s | %s\n",
            $cId, $auth, mb_strimwidth($name, 0, 28, '...'), $yr, $ph, substr($lv, 0, 10), mb_strimwidth($ev, 0, 40, '...')
        );
    }
}
echo "\n";

// -----------------------------------------------------------------------------
// 8. EXECUTIVE SUMMARY & GATE VERDICT
// -----------------------------------------------------------------------------
echo "================================================================================\n";
echo "🏁 DATA-CONFIDENCE GATE READINESS VERDICT\n";
echo "================================================================================\n";

$task1Ready = ($confidenceCounts['VERIFIED'] >= 5) && ($docsGte3Count >= 5);
$task2Ready = ($totalAppPhase >= 1);
$task3Ready = count($eligibleStrictClosed) >= 1 || count($eligibleBroadPast) >= 1;

echo sprintf("1. Task 1 (Document Checklists)   : %s\n", 
    $task1Ready ? "✅ READY FOR DRY-RUN" : "⚠️ BLOCKED (Need to extend facts_json with 'required_documents' array & verify)"
);
echo sprintf("2. Task 2 (Application Guides)    : %s (Active cycles: %d)\n", 
    $task2Ready ? "✅ CANDIDATES EXIST" : "⚠️ BLOCKED (No active APPLICATION_OPEN / APPLICATION_CORRECTION cycles)",
    $totalAppPhase
);
echo sprintf("3. Task 3 (Post-Exam Gap Stats)   : %s (Authorities with >=2 past cycles: %d)\n", 
    $task3Ready ? "✅ READY FOR MIGRATION & CALCULATION" : "⚠️ INSUFFICIENT SAMPLES (Need >=2 historical cycles per authority)",
    count($eligibleBroadPast)
);

echo "--------------------------------------------------------------------------------\n";
echo "Recommended Next Actions:\n";
echo "1. Review above audit metrics on production.\n";
echo "2. If VERIFIED count or required_documents is low:\n";
echo "   - Extend AuthorityFactFetcherService to extract 'required_documents' into facts_json.\n";
echo "   - Run re-verification on candidate cycles before generating checklist pages.\n";
echo "3. For Task 3: Proceed with authority_phase_gap_stats migration & PhaseGapCalculator.\n";
echo "================================================================================\n";
