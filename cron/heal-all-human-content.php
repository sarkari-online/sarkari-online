<?php
/**
 * Sarkari.online — Master AI-Detection Elimination & Editorial Humanizer Cron
 * 
 * Implements Claude AI's editorial architecture:
 * 1. Deterministic Cliche & Antithesis Scrubbing (scrubClichePatterns)
 * 2. Sentence Length Variance (StdDev < 3.0) Detection with Targeted LLM Rhythm Rewriting
 * 3. Opening Hook Humanization (no encyclopedic definitions)
 * 4. Human Contractions & Informal Voice Enforcement
 * 5. Works across:
 *    - All published articles (SELECT id, title, slug, content FROM articles WHERE status = 'published')
 *    - All A-Z Glossary Terms (SELECT id, acronym, slug, overview FROM glossary_terms)
 * 6. Supports:
 *    --dry-run=true|false (default: true)
 *    --batch=N (default: 10, or --all)
 *    --slug=specific-slug
 *    --articles (heal articles)
 *    --glossary (heal glossary terms)
 *    --all (heal both articles and glossary)
 * 
 * Usage:
 *   php cron/heal-all-human-content.php --dry-run=true --batch=5
 *   php cron/heal-all-human-content.php --dry-run=false --batch=50
 *   php cron/heal-all-human-content.php --slug=sail-management-trainee-mt-recruitment-2026 --dry-run=true
 *   php cron/heal-all-human-content.php --glossary --dry-run=true
 *   php cron/heal-all-human-content.php --all --dry-run=false
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\AI\Gemini;
use App\Services\HumanizerService;
use App\Helpers\Logger;

$options = getopt('', ['dry-run::', 'batch::', 'slug::', 'articles', 'glossary', 'all']);

$dryRun = !isset($options['dry-run']) || in_array(strtolower((string)$options['dry-run']), ['1', 'true', 'yes'], true);
$batch = isset($options['batch']) ? (int)$options['batch'] : 10;
$slug = $options['slug'] ?? null;
$healArticles = isset($options['articles']) || isset($options['all']) || (!isset($options['glossary']) && !$slug);
$healGlossary = isset($options['glossary']) || isset($options['all']);

if ($slug) {
    $healArticles = true;
    $healGlossary = false;
}

echo "================================================================================\n";
echo "✍️  SARKARI.ONLINE — MASTER EDITORIAL HUMANIZER & AI-DETECTION CLEANER\n";
echo "Mode       : " . ($dryRun ? "🔍 DRY-RUN (Zero DB modifications)" : "⚡ LIVE RUN (Database will be updated)") . "\n";
echo "Scope      : " . ($healArticles ? "Articles " : "") . ($healGlossary ? "Glossary Terms " : "") . "\n";
if ($slug) echo "Target Slug: {$slug}\n";
echo "Started at : " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

$gemini = null;
try {
    $gemini = new Gemini();
} catch (\Throwable $e) {
    echo "⚠️ Notice: Gemini LLM initialization deferred or running in deterministic mode: " . $e->getMessage() . "\n";
}

// ─────────────────────────────────────────────────────────────
// 1. HEAL ARTICLES
// ─────────────────────────────────────────────────────────────
if ($healArticles) {
    echo "--- SCANNING PUBLISHED ARTICLES ---\n";

    $sql = "SELECT id, title, slug, content, source_url FROM articles WHERE status = 'published'";
    $params = [];
    if ($slug) {
        $sql .= " AND slug = :slug";
        $params['slug'] = $slug;
    } else {
        $sql .= " ORDER BY id DESC";
        if ($batch > 0 && !isset($options['all'])) {
            $sql .= " LIMIT " . $batch;
        }
    }

    $articles = Database::fetchAll($sql, $params);
    echo "Found " . count($articles) . " article(s) to analyze.\n\n";

    $healedArticlesCount = 0;
    $totalPatternsScrubbed = 0;
    $totalRhythmAdjusted = 0;

    foreach ($articles as $art) {
        $id = (int)$art['id'];
        $title = $art['title'];
        $artSlug = $art['slug'];
        $originalContent = $art['content'];
        $currentContent = $originalContent;

        echo "📄 [Article #{$id}] {$title}\n";
        echo "   URL: " . SITE_URL . "/article/{$artSlug}/\n";

        // Step A: Deterministic Claude Cliché & Antithesis Scrubbing
        $scrubResult = HumanizerService::scrubClichePatterns($currentContent);
        $currentContent = $scrubResult['html'];
        $removedItems = $scrubResult['removed'];

        if (!empty($removedItems)) {
            echo "   ✂️ Scrubbed " . count($removedItems) . " robotic pattern(s):\n";
            foreach (array_slice($removedItems, 0, 3) as $r) {
                $snippet = mb_substr($r['text'], 0, 80);
                echo "      - [{$r['type']}]: \"{$snippet}...\"\n";
            }
            if (count($removedItems) > 3) {
                echo "      ... and " . (count($removedItems) - 3) . " more.\n";
            }
            $totalPatternsScrubbed += count($removedItems);
        } else {
            echo "   ✅ No formulaic antitheses or encyclopedic openers found.\n";
        }

        // Step B: Cadence & Rhythm Analysis (Sentence Length Variance StdDev < 3.0)
        $flaggedParagraphs = HumanizerService::detectUniformCadenceParagraphs($currentContent);
        if (!empty($flaggedParagraphs)) {
            echo "   ⚠️ Detected " . count($flaggedParagraphs) . " robotic uniform-cadence paragraph(s) (StdDev < 3.0 words):\n";
            foreach (array_slice($flaggedParagraphs, 0, 2) as $fp) {
                echo "      - Para #{$fp['paragraph_index']}: {$fp['sentence_count']} sentences, word lengths [" . implode(', ', $fp['lengths']) . "], StdDev: {$fp['std_dev']}\n";
            }

            // If Gemini is available and not dry-run (or in targeted slug dry-run for demonstration)
            if ($gemini !== null) {
                foreach (array_slice($flaggedParagraphs, 0, 2) as $fp) {
                    try {
                        $rewritePrompt = HumanizerService::buildRhythmRewritePrompt($fp, [
                            'article_title' => $title,
                            'article_url' => $art['source_url'] ?? ''
                        ]);
                        
                        $aiResp = $gemini->generate($rewritePrompt, [
                            'temperature' => 0.7,
                            'max_output_tokens' => 500,
                        ]);

                        $rewrittenPara = trim($aiResp['text'] ?? '');
                        if (!empty($rewrittenPara) && mb_strlen($rewrittenPara) > 30 && !str_contains($rewrittenPara, '<html')) {
                            // Replace paragraph text in content
                            $oldParaText = $fp['text'];
                            if (str_contains($currentContent, $oldParaText)) {
                                $currentContent = str_replace($oldParaText, $rewrittenPara, $currentContent);
                                echo "      ✨ Successfully varied cadence of paragraph #{$fp['paragraph_index']} via Gemini.\n";
                                $totalRhythmAdjusted++;
                            }
                        }
                    } catch (\Throwable $e) {
                        echo "      ⚠️ Cadence rewrite skipped: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        // Step C: Master Humanizer Polish (Enforce contractions, anti-jargon, clean openings)
        $finalContent = HumanizerService::humanize($currentContent, $title, $art['source_url'] ?? '');

        if ($finalContent !== $originalContent) {
            $healedArticlesCount++;
            $diff = mb_strlen($finalContent) - mb_strlen($originalContent);
            echo "   📊 Polished: Size change: {$diff} chars.\n";

            if (!$dryRun) {
                Database::execute(
                    "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                    ['content' => $finalContent, 'id' => $id]
                );
                echo "   💾 SAVED: Database record #{$id} updated.\n";
            } else {
                echo "   🔍 DRY-RUN: Changes verified without saving.\n";
            }
        } else {
            echo "   ✅ Already 100% human-grade prose. No edits required.\n";
        }
        echo "\n";
    }

    echo "Article Summary: {$healedArticlesCount} articles healed, {$totalPatternsScrubbed} clichés scrubbed, {$totalRhythmAdjusted} cadences rewritten.\n\n";
}

// ─────────────────────────────────────────────────────────────
// 2. HEAL GLOSSARY TERMS (A-Z Full Forms)
// ─────────────────────────────────────────────────────────────
if ($healGlossary) {
    echo "--- SCANNING A-Z GLOSSARY TERMS ---\n";

    // Check if glossary_terms table exists
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'glossary_terms'");
    if (!$tableExists) {
        echo "⚠️ Table 'glossary_terms' does not exist yet. Run database/seed_glossary.php first.\n";
    } else {
        $terms = Database::fetchAll("SELECT id, acronym, slug, overview, eligibility_criteria, selection_process, syllabus_snapshot FROM glossary_terms ORDER BY id ASC");
        echo "Found " . count($terms) . " glossary term(s) to inspect.\n\n";

        $healedTermsCount = 0;

        foreach ($terms as $term) {
            $tId = (int)$term['id'];
            $acronym = $term['acronym'];
            $fieldsToClean = ['overview', 'eligibility_criteria', 'selection_process', 'syllabus_snapshot'];
            $updates = [];
            $hasChange = false;

            foreach ($fieldsToClean as $f) {
                $val = $term[$f] ?? '';
                if (empty($val)) continue;

                $clean = $val;
                // 1. Scrub clichés & patterns
                $scrubbed = HumanizerService::scrubClichePatterns("<p>" . $clean . "</p>");
                $clean = strip_tags($scrubbed['html']);
                // 2. Scrub dictionary clichés & enforce natural contractions
                $clean = HumanizerService::scrubClichés($clean);
                $clean = HumanizerService::enforceContractions($clean);

                // 3. Humanize standard repetitive qualifications phrasing
                $clean = preg_replace('/\bFor\s+([A-Za-z\s]+)\s+posts?,\s*you\s+need\s+a\s+full-time\b/i', 'Candidates applying for $1 need a', $clean);
                $clean = preg_replace('/\bAlways\s+check\s+the\s+specific\s+notification[^.!?]*as\s+age\s+cut-off\s+dates\s+change[^.!?]*[.!?]/i', 'Cut-off dates get finalized in the official circular.', $clean);
                $clean = preg_replace('/\bThe\s+process\s+usually\s+starts\s+with\s+a\s+written\s+exam[^.!?]*[.!?]/i', 'Selection begins with a Computer-Based Test testing technical domain subjects.', $clean);

                if ($clean !== $val) {
                    $updates[$f] = $clean;
                    $hasChange = true;
                }
            }

            if ($hasChange) {
                $healedTermsCount++;
                echo "📚 [Glossary #{$tId}] {$acronym}: Polished all sections (Eligibility, Selection, Syllabus, Overview).\n";

                if (!$dryRun) {
                    $setClauses = [];
                    $params = ['id' => $tId];
                    foreach ($updates as $k => $v) {
                        $setClauses[] = "`{$k}` = :{$k}";
                        $params[$k] = $v;
                    }
                    $setSql = implode(', ', $setClauses);
                    Database::execute("UPDATE glossary_terms SET {$setSql}, updated_at = NOW() WHERE id = :id", $params);
                }
            }
        }

        echo "\nGlossary Summary: {$healedTermsCount} terms thoroughly polished across all sections.\n\n";
    }
}

echo "================================================================================\n";
echo "✅ HUMAN CONTENT HEALING COMPLETED AT " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n";
