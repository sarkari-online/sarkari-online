<?php
declare(strict_types=1);

/**
 * Sarkari.online - Full-Forms Content & Facts Healer (Phase E)
 *
 * Scans glossary_terms requiring fact enrichment (missing full_form_entity_facts
 * or last_verified_at > 90 days), queries Grounded Gemini with Google Search,
 * and upserts verified salary, career growth, and FAQs.
 *
 * Usage:
 *   php cron/heal-full-forms-content.php --dry-run=true --batch=5 --acronyms=PO,IAS,RRB,SSC,SBI
 *   php cron/heal-full-forms-content.php --dry-run=false --batch=5 --acronyms=PO,IAS,RRB,SSC,SBI
 *   php cron/heal-full-forms-content.php --dry-run=false --batch=15
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\FullFormFactFetcherService;
use App\Helpers\Logger;

// -----------------------------------------------------------------------------
// 1. CLI ARGUMENT PARSING
// -----------------------------------------------------------------------------
$batchSize   = 10;
$dryRun      = true; // Safe default
$filterAcronyms = [];
$targetSlug  = null;
$resetCursor = false;
$startId     = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batchSize = max(1, (int)substr($arg, 8));
    } elseif ($arg === '--dry-run' || $arg === '--dry-run=true') {
        $dryRun = true;
    } elseif ($arg === '--dry-run=false' || $arg === '--execute' || $arg === '--live') {
        $dryRun = false;
    } elseif (str_starts_with($arg, '--acronyms=')) {
        $raw = substr($arg, 11);
        $filterAcronyms = array_map('trim', explode(',', strtoupper($raw)));
    } elseif (str_starts_with($arg, '--slug=')) {
        $targetSlug = trim(substr($arg, 7));
    } elseif (str_starts_with($arg, '--start-id=')) {
        $startId = (int)substr($arg, 11);
    } elseif ($arg === '--reset-cursor') {
        $resetCursor = true;
    }
}

$cacheDir = dirname(__DIR__) . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cursorFile = $cacheDir . '/full_forms_heal_cursor.json';

if ($resetCursor && file_exists($cursorFile)) {
    @unlink($cursorFile);
    echo "🔄 Cursor state reset.\n";
}

$lastProcessedId = 0;
if ($startId !== null) {
    $lastProcessedId = $startId - 1;
} elseif (empty($filterAcronyms) && empty($targetSlug) && file_exists($cursorFile)) {
    $cursorData = json_decode((string)file_get_contents($cursorFile), true);
    $lastProcessedId = (int)($cursorData['last_id'] ?? 0);
}

echo "================================================================================\n";
echo "🏥 SARKARI.ONLINE — FULL-FORMS CONTENT & FACT ENRICHMENT HEALER\n";
echo "================================================================================\n";
echo "Mode         : " . ($dryRun ? "\033[33mDRY-RUN (No database writes)\033[0m" : "\033[32mLIVE EXECUTION (Updating DB)\033[0m") . "\n";
echo "Batch Size   : {$batchSize}\n";
echo "Cursor Pos   : Last Processed ID #{$lastProcessedId}\n";
if (!empty($filterAcronyms)) {
    echo "Filter Acronyms: " . implode(', ', $filterAcronyms) . "\n";
}
if (!empty($targetSlug)) {
    echo "Filter Slug  : {$targetSlug}\n";
}
echo "--------------------------------------------------------------------------------\n\n";

// -----------------------------------------------------------------------------
// 2. QUERY CANDIDATE TERMS
// -----------------------------------------------------------------------------
$params = [];
$sql = "SELECT g.*, f.id as facts_id, f.confidence as current_confidence, f.last_verified_at 
        FROM glossary_terms g
        LEFT JOIN full_form_entity_facts f ON g.id = f.full_form_id
        WHERE 1=1 ";

if (!empty($targetSlug)) {
    $sql .= " AND g.slug = :slug";
    $params['slug'] = $targetSlug;
} elseif (!empty($filterAcronyms)) {
    $inClause = implode(',', array_map(fn($k) => ":acr_{$k}", array_keys($filterAcronyms)));
    $sql .= " AND UPPER(g.acronym) IN ({$inClause})";
    foreach ($filterAcronyms as $k => $acr) {
        $params["acr_{$k}"] = $acr;
    }
} else {
    $sql .= " AND g.id > :last_id 
              AND (f.id IS NULL OR f.last_verified_at < DATE_SUB(NOW(), INTERVAL 90 DAY))";
    $params['last_id'] = $lastProcessedId;
}

$sql .= " ORDER BY g.id ASC LIMIT " . (int)$batchSize;

try {
    $candidates = Database::fetchAll($sql, $params);
} catch (Throwable $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "\n";
    exit(1);
}

$count = count($candidates);
if ($count === 0) {
    echo "✅ No pending glossary terms found in this cycle. Full rollout complete or cursor reached end.\n";
    if (file_exists($cursorFile) && empty($filterAcronyms) && empty($targetSlug)) {
        echo "💡 To re-scan from beginning, run with: --reset-cursor\n";
    }
    exit(0);
}

echo "Found {$count} candidate term(s) to process in this batch.\n\n";

// -----------------------------------------------------------------------------
// 3. EXECUTE BATCH WITH GROUNDED FETCHER
// -----------------------------------------------------------------------------
$fetcher = new FullFormFactFetcherService();

$stats = [
    'processed'   => 0,
    'verified'    => 0,
    'inferred'    => 0,
    'unavailable' => 0,
    'errors'      => 0,
];

$startTime = microtime(true);

foreach ($candidates as $index => $term) {
    $termId  = (int)$term['id'];
    $acronym = (string)$term['acronym'];
    $fullEn  = (string)$term['full_form_en'];
    $slug    = (string)$term['slug'];

    $stepNum = $index + 1;
    echo "▶ [{$stepNum}/{$count}] Term [#{$termId}] {$acronym} ({$fullEn})\n";
    echo "  Slug: {$slug}\n";

    try {
        $facts = $fetcher->fetch($term);
        $confidence = $facts['confidence'] ?? 'UNAVAILABLE';

        echo "  Confidence: {$confidence}\n";
        if (!empty($facts['reason'])) {
            echo "  Reason    : \033[33m{$facts['reason']}\033[0m\n";
        }
        if (!empty($facts['pay_level_7cpc'])) {
            echo "  Pay Level : {$facts['pay_level_7cpc']}\n";
        }
        if (!empty($facts['basic_pay_min'])) {
            $basicStr = '₹' . number_format((int)$facts['basic_pay_min']);
            if (!empty($facts['basic_pay_max']) && $facts['basic_pay_max'] !== $facts['basic_pay_min']) {
                $basicStr .= ' – ₹' . number_format((int)$facts['basic_pay_max']);
            }
            echo "  Basic Pay : {$basicStr}\n";
        }
        if (!empty($facts['gross_salary_min'])) {
            $grossStr = '₹' . number_format((int)$facts['gross_salary_min']);
            if (!empty($facts['gross_salary_max']) && $facts['gross_salary_max'] !== $facts['gross_salary_min']) {
                $grossStr .= ' – ₹' . number_format((int)$facts['gross_salary_max']);
            }
            echo "  Gross/Mo  : {$grossStr}\n";
        }
        if (!empty($facts['evidence_url'])) {
            echo "  Evidence  : {$facts['evidence_url']}\n";
        }
        $faqCount = !empty($facts['faqs_json']) ? count($facts['faqs_json']) : 0;
        echo "  FAQs Gen  : {$faqCount} Q&A items\n";

        // If dry run, print raw JSON snippet for verification
        if ($dryRun) {
            echo "  \033[36m[RAW FACTS JSON PREVIEW]\033[0m\n";
            $previewJson = json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            foreach (explode("\n", (string)$previewJson) as $jsonLine) {
                echo "    " . $jsonLine . "\n";
            }
        }

        // Live DB Upsert
        if (!$dryRun) {
            $faqsJsonEncoded = !empty($facts['faqs_json']) ? json_encode($facts['faqs_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            $upsertSql = "INSERT INTO full_form_entity_facts 
                (full_form_id, pay_level_7cpc, basic_pay_min, basic_pay_max, gross_salary_min, gross_salary_max, allowances_summary, career_growth_summary, faqs_json, evidence_url, confidence, last_verified_at)
                VALUES 
                (:fid, :pay_lvl, :b_min, :b_max, :g_min, :g_max, :allow, :growth, :faqs, :evidence, :conf, NOW())
                ON DUPLICATE KEY UPDATE 
                pay_level_7cpc = VALUES(pay_level_7cpc),
                basic_pay_min = VALUES(basic_pay_min),
                basic_pay_max = VALUES(basic_pay_max),
                gross_salary_min = VALUES(gross_salary_min),
                gross_salary_max = VALUES(gross_salary_max),
                allowances_summary = VALUES(allowances_summary),
                career_growth_summary = VALUES(career_growth_summary),
                faqs_json = VALUES(faqs_json),
                evidence_url = VALUES(evidence_url),
                confidence = VALUES(confidence),
                last_verified_at = NOW(),
                updated_at = NOW()";

            Database::execute($upsertSql, [
                'fid'      => $termId,
                'pay_lvl'  => $facts['pay_level_7cpc'],
                'b_min'    => $facts['basic_pay_min'],
                'b_max'    => $facts['basic_pay_max'],
                'g_min'    => $facts['gross_salary_min'],
                'g_max'    => $facts['gross_salary_max'],
                'allow'    => $facts['allowances_summary'],
                'growth'   => $facts['career_growth_summary'],
                'faqs'     => $faqsJsonEncoded,
                'evidence' => $facts['evidence_url'],
                'conf'     => $confidence,
            ]);

            echo "  Status    : \033[32m✅ LIVE PERSISTED\033[0m\n";
        } else {
            echo "  Status    : \033[33m⚡ DRY-RUN SIMULATED (No DB write)\033[0m\n";
        }

        // Track stats
        $stats['processed']++;
        if ($confidence === 'VERIFIED') {
            $stats['verified']++;
        } elseif ($confidence === 'INFERRED') {
            $stats['inferred']++;
        } else {
            $stats['unavailable']++;
        }

        // Update cursor position only if not dry run and processing sequential batches
        if (!$dryRun && empty($filterAcronyms) && empty($targetSlug)) {
            @file_put_contents($cursorFile, json_encode([
                'last_id'   => $termId,
                'timestamp' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT));
        }

    } catch (Throwable $e) {
        $stats['errors']++;
        echo "  Status    : \033[31m❌ ERROR: " . $e->getMessage() . "\033[0m\n";
        Logger::error("HealFullFormsContent error on term #{$termId} ({$acronym}): " . $e->getMessage());
    }

    echo "\n";

    // Rate limiting pacing: sleep 5 seconds between consecutive Gemini search calls
    if ($stepNum < $count) {
        echo "⏳ Pacing rate limiter (sleeping 5s)...\n";
        sleep(5);
    }
}

$elapsed = round(microtime(true) - $startTime, 2);

// -----------------------------------------------------------------------------
// 4. PRINT BATCH SUMMARY
// -----------------------------------------------------------------------------
echo "================================================================================\n";
echo "📊 BATCH ENRICHMENT RUN COMPLETED\n";
echo "================================================================================\n";
echo "Batch Size Target : {$batchSize}\n";
echo "Total Processed   : {$stats['processed']}\n";
echo "  - Verified      : {$stats['verified']}\n";
echo "  - Inferred      : {$stats['inferred']}\n";
echo "  - Unavailable   : {$stats['unavailable']}\n";
echo "Errors Encountered: {$stats['errors']}\n";
echo "Execution Time    : {$elapsed} seconds\n";
echo "Mode              : " . ($dryRun ? "DRY-RUN (Simulated)" : "LIVE EXECUTION (DB Updated)") . "\n";
echo "================================================================================\n";
