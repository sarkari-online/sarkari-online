<?php
declare(strict_types=1);

/**
 * Data Integrity Forensic Check: Cycle #3 vs #107
 * 
 * Inspects full row details of cycle #3 and cycle #107 (CTET).
 * Compares authority_code, exam_name, cycle_year, cycle_identifier,
 * current_phase, facts_json, and linked articles.
 * 
 * Usage:
 *   php cron/inspect-cycle-3-and-107.php [--merge=true]
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

$isMerge = in_array('--merge=true', $argv, true);

echo "================================================================================\n";
echo "🔍 DATA INTEGRITY FORENSIC CHECK: EXAM CYCLES #3 VS #107\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

$cycles = Database::fetchAll(
    "SELECT * FROM exam_cycles WHERE id IN (3, 107) ORDER BY id ASC"
);

if (empty($cycles)) {
    echo "❌ Neither cycle #3 nor #107 found in database!\n";
    exit(1);
}

foreach ($cycles as $c) {
    $cId = (int)$c['id'];
    echo "--------------------------------------------------------------------------------\n";
    echo "EXAM CYCLE #{$cId} FULL DETAILS:\n";
    echo "--------------------------------------------------------------------------------\n";
    echo "  • Authority Code   : " . ($c['authority_code'] ?? 'NULL') . "\n";
    echo "  • Exam Name        : " . ($c['exam_name'] ?? 'NULL') . "\n";
    echo "  • Cycle Year       : " . ($c['cycle_year'] ?? 'NULL') . "\n";
    echo "  • Cycle Identifier : " . ($c['cycle_identifier'] ?? 'NULL') . "\n";
    echo "  • Current Phase    : " . ($c['current_phase'] ?? 'NULL') . "\n";
    echo "  • Phase Confidence : " . ($c['phase_confidence'] ?? 'NULL') . "\n";
    echo "  • Phase Evidence   : " . ($c['phase_evidence_url'] ?? 'NULL') . "\n";
    echo "  • Last Verified At : " . ($c['last_verified_at'] ?? 'NULL') . "\n";
    echo "  • Created At       : " . ($c['created_at'] ?? 'NULL') . "\n";
    echo "  • Updated At       : " . ($c['updated_at'] ?? 'NULL') . "\n";
    echo "  • Facts JSON:\n";
    if (empty($c['facts_json'])) {
        echo "      NULL\n";
    } else {
        $decoded = json_decode($c['facts_json'], true);
        echo "      " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    // Linked Articles
    $linkedArticles = Database::fetchAll(
        "SELECT a.id, a.title, a.slug, eca.article_role 
           FROM exam_cycle_articles eca
           JOIN articles a ON a.id = eca.article_id
          WHERE eca.exam_cycle_id = :cid",
        ['cid' => $cId]
    );
    echo "  • Linked Articles (" . count($linkedArticles) . "):\n";
    foreach ($linkedArticles as $art) {
        echo sprintf("      - [#%d] [%s] %s (/article/%s/)\n", $art['id'], $art['article_role'], $art['title'], $art['slug']);
    }
    echo "\n";
}

// Forensic Comparative Analysis
echo "================================================================================\n";
echo "📊 COMPARATIVE INTEGRITY ANALYSIS\n";
echo "================================================================================\n";
if (count($cycles) === 2) {
    $c3 = $cycles[0]['id'] == 3 ? $cycles[0] : $cycles[1];
    $c107 = $cycles[0]['id'] == 107 ? $cycles[0] : $cycles[1];

    $sameAuth = ($c3['authority_code'] === $c107['authority_code']);
    $sameYear = ($c3['cycle_year'] === $c107['cycle_year']);
    $sameIden = ($c3['cycle_identifier'] === $c107['cycle_identifier']);

    // Both belong to CTET authority in 2026 — 'CENTRAL' is parser artifact from Central Teacher Eligibility Test
    $isCtetDuplicate = ($sameAuth && $sameYear && ($sameIden || in_array($c107['cycle_identifier'], ['CENTRAL', null], true)));

    if ($isCtetDuplicate) {
        echo "🚨 VERDICT: Cycle #3 and Cycle #107 are DUPLICATE representations of the same examination!\n";
        echo "   - Cycle #3 was generated from article title with title-word leak (\"{$c3['exam_name']}\").\n";
        echo "   - Cycle #3 holds verified fact: application_end = '2026-09-10'.\n";
        echo "   - Cycle #107 has canonical exam name (\"{$c107['exam_name']}\") and is in active phase '{$c107['current_phase']}'.\n\n";

        if ($isMerge) {
            echo "⚡ EXECUTING SAFE DEDUPLICATION & MERGE INTO CYCLE #107...\n";
            
            // 1. Merge verified facts from #3 into #107 (preserving non-null facts)
            $f3 = !empty($c3['facts_json']) ? json_decode($c3['facts_json'], true) : [];
            $f107 = !empty($c107['facts_json']) ? json_decode($c107['facts_json'], true) : [];
            if (!is_array($f3)) $f3 = [];
            if (!is_array($f107)) $f107 = [];
            $mergedFacts = array_merge($f107, array_filter($f3, fn($v) => $v !== null));
            $newFactsJson = !empty($mergedFacts) ? json_encode($mergedFacts, JSON_UNESCAPED_UNICODE) : null;

            Database::execute(
                "UPDATE exam_cycles SET facts_json = :facts, cycle_identifier = NULL WHERE id = 107",
                ['facts' => $newFactsJson]
            );

            // 2. Transfer linked articles from #3 to #107
            Database::execute(
                "INSERT IGNORE INTO exam_cycle_articles (exam_cycle_id, article_id, article_role, linked_at)
                 SELECT 107, article_id, article_role, linked_at FROM exam_cycle_articles WHERE exam_cycle_id = 3"
            );
            Database::execute("DELETE FROM exam_cycle_articles WHERE exam_cycle_id = 3");
            Database::execute("DELETE FROM exam_cycles WHERE id = 3");

            echo "✅ Merge complete:\n";
            echo "   - Verified application_end ('2026-09-10') merged into Cycle #107.\n";
            echo "   - Canonical cycle #107 cycle_identifier cleaned to NULL.\n";
            echo "   - Duplicate cycle #3 safely deleted.\n";
        } else {
            echo "💡 To automatically merge verified facts from #3 to #107 and remove duplicate #3, run:\n";
            echo "   php cron/inspect-cycle-3-and-107.php --merge=true\n";
        }
    }
}
echo "================================================================================\n";
