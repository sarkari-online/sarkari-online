<?php
declare(strict_types=1);

/**
 * Sarkari.online — Scoped Table Corruption Healer
 * 
 * Heals published articles affected by the legacy destructive table replacement bug.
 * Reconstructs grounded dates milestone tables (with tentative basis) and full
 * Prelims & Mains exam pattern tables without clobbering existing content.
 * 
 * Usage:
 *   php cron/heal-table-corruption.php --scan-only
 *   php cron/heal-table-corruption.php --article-id=709 --dry-run
 *   php cron/heal-table-corruption.php --article-id=709 --live
 *   php cron/heal-table-corruption.php --batch=5 --dry-run
 *   php cron/heal-table-corruption.php --batch=5 --live
 *   php cron/heal-table-corruption.php --all --live
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\AuthorityFactFetcherService;
use App\Services\MilestoneTableRenderer;
use App\Services\MilestoneStatusRenderer;
use App\Services\ContentIntegrityGuard;
use App\Services\TableIntegrityGate;
use App\Services\IntentClassifierService;
use App\Services\LegacyTableDetector;
use App\AI\ArticleGenerator;

echo "================================================================================\n";
echo "🏥 SARKARI.ONLINE — SCOPED TABLE CORRUPTION HEALER\n";
echo "================================================================================\n\n";

$options = getopt('', ['scan-only', 'dry-run', 'live', 'article-id:', 'slug:', 'batch:', 'all']);
$isScanOnly = isset($options['scan-only']);
$isDryRun   = isset($options['dry-run']) || !isset($options['live']);
$targetId   = isset($options['article-id']) ? (int)$options['article-id'] : null;
$targetSlug = isset($options['slug']) ? (string)$options['slug'] : null;
$batchLimit = isset($options['batch']) ? (int)$options['batch'] : (isset($options['all']) ? 9999 : null);

// Ensure snapshots directory exists
$snapshotDir = dirname(__DIR__) . '/storage/snapshots';
if (!is_dir($snapshotDir)) {
    @mkdir($snapshotDir, 0755, true);
}

// -----------------------------------------------------------------------------
// 1. SCAN MODE / CANDIDATE SELECTION
// -----------------------------------------------------------------------------
if ($isScanOnly) {
    echo "🔍 Scanning published catalog for suspected table corruption...\n";
    try {
        $articles = Database::fetchAll(
            "SELECT id, title, slug, content FROM articles WHERE status = 'published' ORDER BY id ASC"
        );
        $suspects = [];
        foreach ($articles as $a) {
            $tableCount = substr_count($a['content'], '<table');
            if ($tableCount < 2) {
                $suspects[] = [
                    'id' => $a['id'],
                    'title' => $a['title'],
                    'slug' => $a['slug'],
                    'tables' => $tableCount
                ];
            }
        }
        echo "Found " . count($suspects) . " articles with < 2 tables (potential table corruption):\n\n";
        foreach ($suspects as $s) {
            echo "  [#{$s['id']}] {$s['title']} ({$s['slug']}) — Tables: {$s['tables']}\n";
        }
    } catch (\Throwable $e) {
        echo "⚠️ Database query failed: " . $e->getMessage() . "\n";
    }
    exit(0);
}

// -----------------------------------------------------------------------------
// 2. TARGET DETERMINATION (SINGLE OR BATCH)
// -----------------------------------------------------------------------------
$targets = [];

if ($targetId || $targetSlug) {
    try {
        if ($targetId) {
            $art = Database::fetchOne("SELECT * FROM articles WHERE id = :id LIMIT 1", ['id' => $targetId]);
        } else {
            $art = Database::fetchOne("SELECT * FROM articles WHERE slug = :slug LIMIT 1", ['slug' => $targetSlug]);
        }
        if ($art) {
            $targets[] = $art;
        }
    } catch (\Throwable $e) {
        echo "⚠️ Database query failed: " . $e->getMessage() . "\n";
    }
} elseif ($batchLimit !== null && $batchLimit > 0) {
    echo "🔍 Finding articles needing table reconstruction (Batch limit: {$batchLimit})...\n";
    try {
        $all = Database::fetchAll(
            "SELECT * FROM articles WHERE status = 'published' ORDER BY id DESC"
        );
        foreach ($all as $a) {
            if (substr_count($a['content'], '<table') < 2) {
                $targets[] = $a;
                if (count($targets) >= $batchLimit) {
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
        echo "⚠️ Database query failed: " . $e->getMessage() . "\n";
    }
}

if (empty($targets)) {
    echo "❌ No matching articles found. Specify --article-id=ID, --slug=SLUG, or --batch=N.\n";
    exit(1);
}

echo "Found " . count($targets) . " article(s) to process.\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Inspection Only — No DB writes)" : "LIVE EXECUTION (Updating DB)") . "\n";
echo "--------------------------------------------------------------------------------\n\n";

// -----------------------------------------------------------------------------
// 3. PROCESS EACH TARGET
// -----------------------------------------------------------------------------
$successCount = 0;
$skippedCount = 0;

foreach ($targets as $idx => $article) {
    $articleId = (int)$article['id'];
    $title     = $article['title'];
    $slug      = $article['slug'];
    $before    = $article['content'];
    $stepNum   = $idx + 1;
    $totalArts = count($targets);

    echo "▶ [{$stepNum}/{$totalArts}] Article [#{$articleId}] {$title}\n";
    echo "  Slug: {$slug}\n";

    // Snapshot before write
    $snapshotFile = "{$snapshotDir}/article_{$articleId}_pre_table_heal_" . date('Ymd_His') . ".json";
    file_put_contents($snapshotFile, json_encode([
        'article_id' => $articleId,
        'slug'       => $slug,
        'title'      => $title,
        'content'    => $before,
        'saved_at'   => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    try {
        // Resolve category
        $category = 'recruitment';
        if (!empty($article['category_id'])) {
            try {
                $cat = Database::fetchOne("SELECT slug FROM categories WHERE id = :id LIMIT 1", ['id' => $article['category_id']]);
                if ($cat && !empty($cat['slug'])) {
                    $category = $cat['slug'];
                }
            } catch (\Throwable $e) {}
        }

        // 1. Dynamic verified statutory facts extraction
        $fetcher = new AuthorityFactFetcherService();
        $verifiedFacts = $fetcher->fetchFactsForTopic($title, $category, $article['source_portal'] ?? '');

        $classifier = new IntentClassifierService();
        $intent = $classifier->classify($title, $category);
        $generator = new ArticleGenerator();

        // 2. Build dates milestone table HTML
        $datesTable = $generator->buildDatesTableFromFacts($verifiedFacts, $intent);
        $datesTableHtml = $generator->renderDatesTableHtml($datesTable);

        // 3. Build exam pattern tables (if applicable)
        $patternSectionHtml = '';
        $prelims = $verifiedFacts['prelims_pattern'] ?? null;
        $mains   = $verifiedFacts['mains_pattern'] ?? null;

        $hasPrelims = !empty($prelims['sections']) && is_array($prelims['sections']);
        $hasMains   = !empty($mains['sections']) && is_array($mains['sections']);

        if ($hasPrelims || $hasMains) {
            $patternSectionHtml .= "<div class=\"exam-pattern-section\" style=\"margin: 1.5rem 0;\">\n";
            if ($hasPrelims) {
                $patternSectionHtml .= "  <h3>Preliminary Examination Pattern (Objective Type)</h3>\n";
                $patternSectionHtml .= "  <div class=\"table-responsive\">\n";
                $patternSectionHtml .= "    <table class=\"table table-bordered table-striped data-table\">\n";
                $patternSectionHtml .= "      <thead class=\"table-dark\">\n";
                $patternSectionHtml .= "        <tr><th>Section / Subject</th><th>No. of Questions</th><th>Max Marks</th><th>Duration</th></tr>\n";
                $patternSectionHtml .= "      </thead>\n      <tbody>\n";
                foreach ($prelims['sections'] as $sec) {
                    $patternSectionHtml .= "        <tr><td><strong>" . htmlspecialchars((string)($sec['subject'] ?? ''), ENT_QUOTES, 'UTF-8') . "</strong></td><td>" . ($sec['questions'] ?? '-') . "</td><td>" . ($sec['marks'] ?? '-') . "</td><td>" . ($sec['duration_minutes'] ?? '-') . " Minutes</td></tr>\n";
                }
                $totQ = $prelims['total_questions'] ?? '-';
                $totM = $prelims['total_marks'] ?? '-';
                $totD = $prelims['duration_minutes'] ?? '-';
                $patternSectionHtml .= "        <tr class=\"table-info\"><td><strong>Total</strong></td><td><strong>{$totQ}</strong></td><td><strong>{$totM}</strong></td><td><strong>{$totD} Minutes</strong></td></tr>\n";
                $patternSectionHtml .= "      </tbody>\n    </table>\n  </div>\n";
                if (!empty($prelims['negative_marking'])) {
                    $patternSectionHtml .= "  <p><small><em>Negative Marking: " . htmlspecialchars((string)$prelims['negative_marking'], ENT_QUOTES, 'UTF-8') . "</em></small></p>\n";
                }
            }

            if ($hasMains) {
                $patternSectionHtml .= "  <h3 style=\"margin-top: 1.5rem;\">Main Examination Pattern (Objective Type)</h3>\n";
                $patternSectionHtml .= "  <div class=\"table-responsive\">\n";
                $patternSectionHtml .= "    <table class=\"table table-bordered table-striped data-table\">\n";
                $patternSectionHtml .= "      <thead class=\"table-dark\">\n";
                $patternSectionHtml .= "        <tr><th>Section / Subject</th><th>No. of Questions</th><th>Max Marks</th><th>Duration</th></tr>\n";
                $patternSectionHtml .= "      </thead>\n      <tbody>\n";
                foreach ($mains['sections'] as $sec) {
                    $patternSectionHtml .= "        <tr><td><strong>" . htmlspecialchars((string)($sec['subject'] ?? ''), ENT_QUOTES, 'UTF-8') . "</strong></td><td>" . ($sec['questions'] ?? '-') . "</td><td>" . ($sec['marks'] ?? '-') . "</td><td>" . ($sec['duration_minutes'] ?? '-') . " Minutes</td></tr>\n";
                }
                $totQ = $mains['total_questions'] ?? '-';
                $totM = $mains['total_marks'] ?? '-';
                $totD = $mains['duration_minutes'] ?? '-';
                $patternSectionHtml .= "        <tr class=\"table-info\"><td><strong>Total</strong></td><td><strong>{$totQ}</strong></td><td><strong>{$totM}</strong></td><td><strong>{$totD} Minutes</strong></td></tr>\n";
                $patternSectionHtml .= "      </tbody>\n    </table>\n  </div>\n";
                if (!empty($mains['negative_marking'])) {
                    $patternSectionHtml .= "  <p><small><em>Negative Marking: " . htmlspecialchars((string)$mains['negative_marking'], ENT_QUOTES, 'UTF-8') . "</em></small></p>\n";
                }
            }
            $patternSectionHtml .= "</div>\n";
        }

        // ---------------------------------------------------------------------
        // 4. TWO-PHASE ASSEMBLY & INTENTIONAL DEDUPLICATION
        // ---------------------------------------------------------------------
        // Phase A: Identify and remove legacy corrupted placeholder tables (Counted Removal)
        $legacyTables = LegacyTableDetector::findLegacyTables($before);
        $afterRemoval = $before;
        foreach ($legacyTables as $legacy) {
            $targetToRemove = !empty($legacy['wrapper_html']) ? $legacy['wrapper_html'] : $legacy['html'];
            $afterRemoval = str_replace($targetToRemove, '', $afterRemoval);
        }
        ContentIntegrityGuard::assertIntentionalReplacement($before, $afterRemoval, count($legacyTables));

        if (!empty($legacyTables)) {
            echo "  Legacy corrupted tables cleanly deduplicated & removed: " . count($legacyTables) . "\n";
        }

        // Phase B: Inject fresh, verified tables into cleaned content
        $healed = $afterRemoval;

        if (!empty($patternSectionHtml)) {
            $healed = preg_replace(
                '/(<h[23]>[^<]*(?:Exam Pattern|Syllabus|Selection Process|Scheme of Exam)[^<]*<\/h[23]>\s*(?:<p>.*?<\/p>\s*)?)<div class="table-responsive">\s*<table class="data-table">.*?<\/table>\s*<\/div>/si',
                "$1\n" . $patternSectionHtml,
                $healed
            );
        }

        if (!empty($datesTableHtml)) {
            $healed = $generator->injectPhpDatesTable($healed, $datesTableHtml);
        }

        // Must not lose any structure compared to afterRemoval!
        ContentIntegrityGuard::assertNoStructuralLoss($afterRemoval, $healed);

        $gate = new TableIntegrityGate();
        $violations = $gate->scan($healed);
        if (!empty($violations)) {
            $healed = $gate->repair($healed);
        }

        $tablesBefore = substr_count($before, '<table');
        $tablesAfter  = substr_count($healed, '<table');

        echo "  Tables: {$tablesBefore} → {$tablesAfter} | Bytes: " . strlen($before) . " → " . strlen($healed) . "\n";

        if ($isDryRun) {
            echo "  Status: DRY-RUN OK (Verification passed)\n\n";
            $successCount++;
        } else {
            Database::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                ['content' => $healed, 'id' => $articleId]
            );
            echo "  Status: ✅ LIVE HEALED\n\n";
            $successCount++;
        }

    } catch (\Throwable $e) {
        echo "  Status: ⚠️ SKIPPED (Integrity protection: " . $e->getMessage() . ")\n\n";
        $skippedCount++;
    }

    // Pacing between batch items to protect API rate limits
    if ($stepNum < $totalArts) {
        sleep(2);
    }
}

echo "================================================================================\n";
echo "📊 HEALING RUN COMPLETED\n";
echo "Processed: " . count($targets) . " | Succeeded: {$successCount} | Skipped: {$skippedCount}\n";
echo "================================================================================\n";
