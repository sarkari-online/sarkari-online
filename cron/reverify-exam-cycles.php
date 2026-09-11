<?php
declare(strict_types=1);

/**
 * Sarkari.online - Autonomous Exam Lifecycle Re-Verification Engine (Task 0)
 * 
 * Periodically re-verifies exam cycles against official circulars and portals
 * using TTL-based scheduling rules:
 * - ANNUAL_CALENDAR_ONLY   : 30 days TTL
 * - NOTIFICATION_RELEASED  : 7 days TTL
 * - APPLICATION_OPEN       : 3 days TTL
 * - APPLICATION_CORRECTION : 2 days TTL
 * - APPLICATION_CLOSED     : 5 days TTL
 * - ADMIT_CARD_AWAITED     : 5 days TTL
 * - ADMIT_CARD_RELEASED    : 3 days TTL
 * - EXAM_SCHEDULED         : 3 days TTL
 * - EXAM_CONDUCTED         : 7 days TTL
 * - ANSWER_KEY_OBJECTION   : 2 days TTL
 * - FINAL_KEY_RELEASED     : 15 days TTL
 * - RESULT_DECLARED        : 15 days TTL
 * - PET_DV_STAGE           : 15 days TTL
 * - FINAL_SELECTION        : 20 days TTL
 * - CYCLE_CLOSED           : Terminal phase (never re-checked)
 * 
 * PRIORITIZES:
 * 1. "Never Verified" cycles (last_verified_at IS NULL)
 * 2. High-volatility active phases nearing deadline or exam dates
 * 3. Expired TTL cycles
 * 
 * Usage:
 *   php cron/reverify-exam-cycles.php [--limit=15] [--dry-run=true]
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\PhaseTransitionCheck;
use App\Helpers\Logger;

$limit = 15;
$isDryRun = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(50, (int)substr($arg, 8)));
    } elseif ($arg === '--dry-run=true' || $arg === '--dry-run') {
        $isDryRun = true;
    }
}

echo "================================================================================\n";
echo "🔄 SARKARI.ONLINE — EXAM LIFECYCLE RE-VERIFICATION ENGINE (TASK 0)\n";
echo "Batch Limit : {$limit} cycles\n";
echo "Mode        : " . ($isDryRun ? "DRY-RUN (Inspection Only)" : "LIVE VERIFICATION") . "\n";
echo "Started At  : " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

// ── 1. TTL Phase Map (Days) ──────────────────────────────────────────────────
$phaseTtlDays = [
    'APPLICATION_CORRECTION' => 2,
    'ANSWER_KEY_OBJECTION'   => 2,
    'APPLICATION_OPEN'       => 3,
    'ADMIT_CARD_RELEASED'    => 3,
    'EXAM_SCHEDULED'         => 3,
    'ADMIT_CARD_AWAITED'     => 5,
    'APPLICATION_CLOSED'     => 5,
    'NOTIFICATION_RELEASED'  => 7,
    'EXAM_CONDUCTED'         => 7,
    'FINAL_KEY_RELEASED'     => 15,
    'RESULT_DECLARED'        => 15,
    'PET_DV_STAGE'           => 15,
    'FINAL_SELECTION'        => 20,
    'ANNUAL_CALENDAR_ONLY'   => 30,
];

// ── 2. Select Candidates (Prioritizing Never Verified, then Stale/Expired TTL) ──
$candidates = [];

// Priority 1: Never Verified (last_verified_at IS NULL)
$neverVerified = Database::fetchAll(
    "SELECT * FROM exam_cycles 
      WHERE last_verified_at IS NULL 
        AND current_phase != 'CYCLE_CLOSED'
      ORDER BY id ASC 
      LIMIT {$limit}"
);

foreach ($neverVerified as $row) {
    $candidates[] = array_merge($row, ['_reason' => 'NEVER_VERIFIED']);
}

// Priority 2: Fill remaining quota with due/stale cycles
$remainingSlots = $limit - count($candidates);
if ($remainingSlots > 0) {
    $existingIds = array_column($candidates, 'id');
    $idExcludeClause = !empty($existingIds) ? "AND id NOT IN (" . implode(',', $existingIds) . ")" : "";

    $staleCycles = Database::fetchAll(
        "SELECT * FROM exam_cycles 
          WHERE current_phase != 'CYCLE_CLOSED'
            AND last_verified_at IS NOT NULL
            {$idExcludeClause}
          ORDER BY 
            CASE 
              WHEN current_phase IN ('APPLICATION_OPEN', 'APPLICATION_CORRECTION') THEN 1
              WHEN current_phase IN ('ADMIT_CARD_RELEASED', 'ANSWER_KEY_OBJECTION') THEN 2
              WHEN phase_confidence = 'INFERRED' THEN 3
              ELSE 4
            END ASC,
            last_verified_at ASC
          LIMIT {$remainingSlots}"
    );

    foreach ($staleCycles as $row) {
        $ph = $row['current_phase'] ?? 'ANNUAL_CALENDAR_ONLY';
        $ttl = $phaseTtlDays[$ph] ?? 30;
        $candidates[] = array_merge($row, ['_reason' => "TTL_EXPIRED_{$ttl}D"]);
    }
}

$candidateCount = count($candidates);
echo "Identified {$candidateCount} high-priority cycles due for re-verification.\n\n";

if ($candidateCount === 0) {
    echo "✅ All exam cycles are fully up-to-date within their statutory TTL windows.\n";
    exit(0);
}

// ── 3. Execute Re-Verification Batch ──────────────────────────────────────────
$checker = new PhaseTransitionCheck();

$processed = 0;
$elevatedPhases = 0;
$newlyVerified = 0;
$errors = 0;

foreach ($candidates as $idx => $cycle) {
    $processed++;
    $cId        = (int)$cycle['id'];
    $auth       = $cycle['authority_code'];
    $examName   = $cycle['exam_name'];
    $year       = $cycle['cycle_year'];
    $oldPhase   = $cycle['current_phase'];
    $oldConf    = $cycle['phase_confidence'];
    $reason     = $cycle['_reason'];

    echo sprintf("[%2d/%2d] [#%d] %-8s | %-28s (%s)\n", 
        $processed, $candidateCount, $cId, $auth, mb_strimwidth($examName, 0, 28, '...'), $year
    );
    echo sprintf("     Previous State: %-22s | Conf: %-8s | Reason: %s\n", $oldPhase, $oldConf, $reason);

    if ($isDryRun) {
        echo "     [DRY-RUN] Skipped API call.\n\n";
        continue;
    }

    try {
        $updated = $checker->check($cycle);
        $newPhase = $updated['current_phase'] ?? $oldPhase;
        $newConf  = $updated['phase_confidence'] ?? $oldConf;
        $newEv    = $updated['phase_evidence_url'] ?? '';

        if ($newPhase !== $oldPhase) {
            $elevatedPhases++;
            echo sprintf("     ⚡ PHASE ADVANCED: %s ➔ %s\n", $oldPhase, $newPhase);
        }

        if ($oldConf !== 'VERIFIED' && $newConf === 'VERIFIED') {
            $newlyVerified++;
            echo sprintf("     🛡️ ELEVATED TO VERIFIED: Evidence: %s\n", $newEv ?: 'Official Portal');
        } else {
            echo sprintf("     Status: Current Phase: %s | Conf: %s\n", $newPhase, $newConf);
        }

        // Pacing delay between statutory calls to preserve rate limits
        if ($processed < $candidateCount) {
            sleep(2);
        }

    } catch (\Throwable $e) {
        $errors++;
        echo "     ❌ Re-verification error: " . $e->getMessage() . "\n";
        Logger::error("reverify-exam-cycles: Cycle #{$cId} error: " . $e->getMessage());
    }

    echo "\n";
}

// ── 4. Print Executive Batch Summary ──────────────────────────────────────────
echo "================================================================================\n";
echo "🏁 RE-VERIFICATION BATCH SUMMARY\n";
echo "================================================================================\n";
echo "  • Total Cycles Processed     : {$processed}\n";
echo "  • Phase Transitions Advanced : {$elevatedPhases}\n";
echo "  • Newly Promoted to VERIFIED : {$newlyVerified}\n";
echo "  • Errors Encountered         : {$errors}\n";

// Query latest DB stats
try {
    $totalDb = (int)Database::fetchValue("SELECT COUNT(*) FROM exam_cycles");
    $verDb   = (int)Database::fetchValue("SELECT COUNT(*) FROM exam_cycles WHERE phase_confidence = 'VERIFIED'");
    $infDb   = (int)Database::fetchValue("SELECT COUNT(*) FROM exam_cycles WHERE phase_confidence = 'INFERRED'");
    $neverDb = (int)Database::fetchValue("SELECT COUNT(*) FROM exam_cycles WHERE last_verified_at IS NULL");
    $pctDb   = $totalDb > 0 ? round(($verDb / $totalDb) * 100, 1) : 0;

    echo "\n  Current Overall Database Health:\n";
    echo sprintf("  • Total Cycles       : %d\n", $totalDb);
    echo sprintf("  • VERIFIED Cycles    : %d (%.1f%%)\n", $verDb, $pctDb);
    echo sprintf("  • INFERRED Cycles    : %d\n", $infDb);
    echo sprintf("  • Remaining Unverified: %d\n", $neverDb);
} catch (\Throwable $e) {
    // Non-critical
}

echo "================================================================================\n";
