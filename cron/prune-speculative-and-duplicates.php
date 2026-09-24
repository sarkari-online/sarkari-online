<?php
/**
 * Sarkari.online - SEO Content Pruning Engine
 * Safely converts low-quality, speculative (2027), and cannibalizing duplicate articles
 * from 'published' to 'draft' status to restore Google domain trust and clear GSC hold.
 * 
 * Usage (CLI only):
 * php cron/prune-speculative-and-duplicates.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access Denied: CLI execution only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Content Pruning Engine...\n";

$pruneList = [
    // 1. 2027 Speculative & Premature Posts (Direct GSC Validation Failures)
    'cbse-datesheet-2027-class-10-12' => 'Speculative 2027 post (GSC validation failed)',
    'cbse-board-exam-2027-date-sheet' => 'Speculative 2027 post (Unverified)',
    'mbose-sslc-2027-exam-dates' => 'Speculative 2027 post (Unverified)',
    'wbjee-2027-exam-application-guide' => 'Speculative 2027 post (Unverified)',
    'gate-2027-syllabus-exam-pattern-eligibility' => 'Speculative 2027 post (2026 ongoing)',
    'jee-main-2027-syllabus-weightage' => 'Speculative 2027 post (2026 ongoing)',

    // 2. Speculative / Unofficial Vacancy Notices
    'up-super-tet-2026-application' => 'No official notification released (GSC validation failed)',
    'rrb-group-d-2026-answer-key' => 'Premature answer key for unheld exam',
    'upsc-ese-2026-interview-schedule-scorecard' => 'Premature interview post (prelims pending)',
    'punjab-pti-recruitment-2026-cancelled-fee-refund' => 'Obsolete cancelled recruitment',
    'bpsc-tre-4-application-postponed-dates' => 'Thin postponed notice',
    'aws-nsdc-ai-skill-initiative-2026' => 'Non-government/private skill initiative',

    // 3. Heavy Topic Cannibalization: NEET PG (Retain: neet-pg-2026-result-scorecard)
    'neet-pg-2026-answer-key-response-sheet' => 'NEET PG duplicate (cannibalizing scorecard)',
    'neet-pg-2026-answer-key-objection' => 'NEET PG duplicate (cannibalizing scorecard)',
    'neet-pg-2026-answer-key' => 'NEET PG duplicate (cannibalizing scorecard)',
    'neet-pg-2026-exam-time-shift-timings' => 'NEET PG duplicate (cannibalizing scorecard)',

    // 4. Duplicate ID / Portal Registration Guides (Retain: how-to-create-apaar-id-digilocker)
    'digilocker-abc-id-creation-2026' => 'Duplicate ABC/APAAR ID guide',
    'link-aadhaar-abc-digilocker-2026' => 'Duplicate Aadhaar/ABC ID guide',

    // 5. Duplicate Exams & Registration Guides
    'ssc-cgl-2026-tier-1-exam-guide' => 'SSC CGL duplicate (cannibalizing ssc-cgl-2026-notification-apply)',
    'ibps-po-2026-prelims-exam-concluded' => 'Thin concluded exam event',
    'ibps-rrb-clerk-2026-registration' => 'IBPS RRB duplicate (cannibalizing ibps-rrb-2026-registration-apply-online)',
    'nsp-otr-2026-27-registration-guide' => 'NSP duplicate (cannibalizing nsp-scholarship-2026-27-registration-guide)',
    'neet-ug-2026-counselling-schedule-released-mcc-nic-in' => 'NEET UG counselling duplicate',
    'maharashtra-neet-ug-2026-round-1-allotment' => 'Thin state round allotment notice',
    'rajasthan-neet-ug-2026-round-1-seat-allotment' => 'Thin state round allotment notice',
    'mht-cet-2026-cap-round-4-options' => 'Thin counselling round options notice',
];

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage() . "\n");
}

$prunedCount = 0;
$alreadyDraftCount = 0;
$notFoundCount = 0;

$stmtCheck = $db->prepare("SELECT id, title, status FROM articles WHERE slug = :slug LIMIT 1");
$stmtUpdate = $db->prepare("UPDATE articles SET status = 'draft', updated_at = NOW() WHERE id = :id");

foreach ($pruneList as $slug => $reason) {
    $stmtCheck->execute(['slug' => $slug]);
    $art = $stmtCheck->fetch();

    if (!$art) {
        echo "  - [NOT FOUND] '{$slug}'\n";
        $notFoundCount++;
        continue;
    }

    if ($art['status'] === 'draft') {
        echo "  - [ALREADY DRAFT] #{$art['id']} '{$slug}'\n";
        $alreadyDraftCount++;
        continue;
    }

    $stmtUpdate->execute(['id' => $art['id']]);
    echo "  -> [PRUNED TO DRAFT] #{$art['id']}: '{$slug}' | Reason: {$reason}\n";
    $prunedCount++;
    Logger::info("Content Pruning: Article #{$art['id']} ({$slug}) moved to draft. Reason: {$reason}");
}

// Get updated count of published articles
$stmtCount = $db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'");
$publishedRemaining = $stmtCount->fetchColumn();

echo "\n======================================================\n";
echo "Content Pruning Summary:\n";
echo "  - Total target candidates: " . count($pruneList) . "\n";
echo "  - Successfully moved to draft: {$prunedCount}\n";
echo "  - Already in draft: {$alreadyDraftCount}\n";
echo "  - Not found: {$notFoundCount}\n";
echo "  - Remaining ACTIVE published articles: {$publishedRemaining}\n";
echo "======================================================\n";
echo "[" . date('Y-m-d H:i:s') . "] Content Pruning Finished Successfully.\n";
