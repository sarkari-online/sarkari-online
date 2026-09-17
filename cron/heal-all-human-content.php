<?php
/**
 * Sarkari.online — Master AI-Detection Elimination & Editorial Humanizer Cron
 *
 * APPROACH: Two-layer pipeline
 *   Layer 1 — Deterministic PHP scrubbing (clichés, contractions, antithesis patterns)
 *   Layer 2 — Gemini LLM full-field rewriting for any field that still reads like AI
 *
 * Supports:
 *   --dry-run=true|false  (default: true — safe preview mode)
 *   --batch=N             (default: 10)
 *   --slug=<slug>         (target a single article or glossary slug)
 *   --articles            (heal articles only)
 *   --glossary            (heal glossary terms only)
 *   --all                 (heal everything)
 *   --force-llm           (force Gemini rewrite on every field, even clean ones)
 *
 * Usage:
 *   php cron/heal-all-human-content.php --glossary --dry-run=false
 *   php cron/heal-all-human-content.php --articles --dry-run=false --batch=50
 *   php cron/heal-all-human-content.php --slug=sail --glossary --dry-run=false
 *   php cron/heal-all-human-content.php --all --dry-run=false
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\AI\Gemini;
use App\Services\HumanizerService;

$options = getopt('', ['dry-run::', 'batch::', 'slug::', 'articles', 'glossary', 'all', 'force-llm']);

$dryRun    = !isset($options['dry-run']) || in_array(strtolower((string)$options['dry-run']), ['1', 'true', 'yes'], true);
$batch     = isset($options['batch']) ? (int)$options['batch'] : 10;
$slug      = $options['slug'] ?? null;
$forceLlm  = isset($options['force-llm']);

$healArticles = isset($options['articles']) || isset($options['all']) || (!isset($options['glossary']) && !$slug);
$healGlossary = isset($options['glossary']) || isset($options['all']);

// If --slug given, detect whether it's a glossary slug or article slug
if ($slug) {
    // Try glossary first
    $termExists = Database::fetchOne("SELECT id FROM glossary_terms WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
    if ($termExists) {
        $healGlossary = true;
        $healArticles = false;
    } else {
        $healArticles = true;
        $healGlossary = false;
    }
}

echo "================================================================================\n";
echo "✍️  SARKARI.ONLINE — MASTER EDITORIAL HUMANIZER & AI-DETECTION CLEANER\n";
echo "Mode       : " . ($dryRun ? "🔍 DRY-RUN (No DB writes)" : "⚡ LIVE RUN (DB will be updated)") . "\n";
echo "Scope      : " . ($healArticles ? "Articles " : "") . ($healGlossary ? "Glossary Terms " : "") . "\n";
echo "LLM Mode   : " . ($forceLlm ? "FORCE (every field)" : "Smart (only AI-sounding fields)") . "\n";
if ($slug) echo "Target Slug: {$slug}\n";
echo "Started at : " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

// ─────────────────────────────────────────────────────────────
// GEMINI CLIENT SETUP
// ─────────────────────────────────────────────────────────────
$gemini = null;
try {
    $gemini = new Gemini();
    if (!$gemini->isConfigured()) {
        $gemini = null;
        echo "⚠️  Gemini not configured — running deterministic-only mode.\n\n";
    } else {
        echo "✅ Gemini LLM connected. Layer 2 LLM rewriting is active.\n\n";
    }
} catch (\Throwable $e) {
    echo "⚠️  Gemini init failed ({$e->getMessage()}) — deterministic mode only.\n\n";
}

// ─────────────────────────────────────────────────────────────
// HELPER: AI Score Heuristic (local, no API call)
// Returns a 0–100 score indicating how AI-like the text is.
// Higher = more AI-like. Threshold = 40 to trigger LLM rewrite.
// ─────────────────────────────────────────────────────────────
function aiHeuristicScore(string $text): int
{
    $score = 0;
    $plain = strtolower(strip_tags($text));

    // AI cliché phrases (strong signal: +15 each)
    $hardCliches = [
        'it is worth noting',
        'it is important to note',
        'in conclusion',
        'in today\'s competitive',
        'in today\'s digital',
        'without further ado',
        'without further delay',
        'pivotal role',
        'in the realm of',
        'when it comes to',
        'needless to say',
        'first and foremost',
        'last but not least',
        'it goes without saying',
        'at the end of the day',
        'in this day and age',
        'the bottom line is',
        'plays a crucial role',
        'plays a key role',
        'it is essential to',
        'it is imperative that',
        'candidates are advised to',
        'candidates are requested to',
        'one must note that',
        'it is mandatory for candidates',
        'in order to',
        'with respect to',
        'with regard to',
        'as per the official',
        'as mentioned earlier',
        'as discussed above',
        'to sum up',
        'to summarize',
        'in summary',
        'it should be noted that',
        'it can be concluded',
        'furthermore',
        'moreover',
        'in addition to the above',
        'notwithstanding',
        'pertaining to',
        'therein lies',
        'henceforth',
        'inasmuch as',
        'pursuant to',
    ];
    foreach ($hardCliches as $c) {
        if (str_contains($plain, $c)) {
            $score += 15;
        }
    }

    // Formal textbook openers (signal: +20 each)
    $formalOpeners = [
        '/\bfor \w[\w\s\(\)]{3,40} posts?,\s+(?:you need|candidates|applicants)/i',
        '/\b(?:you need|one needs|candidates need) a full-time bachelor\'?s degree/i',
        '/\bthe (?:selection|recruitment) process (?:consists|involves|comprises)/i',
        '/\bthe syllabus (?:focuses|covers|includes|encompasses)/i',
        '/\b(?:an overview|a brief overview) of/i',
        '/\b(?:it should be|it must be) (?:noted|mentioned|understood)/i',
        '/\bthe (?:examination|recruitment|application) process is (?:designed|structured|intended)/i',
        '/\bthis (?:article|guide|page) (?:provides|covers|explains|details)/i',
    ];
    foreach ($formalOpeners as $pattern) {
        if (preg_match($pattern, $text)) {
            $score += 20;
        }
    }

    // Uniform sentence length (all sentences 15–20 words = AI pattern: +25)
    $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', strip_tags($text));
    $sentences = array_filter($sentences, fn($s) => trim($s) !== '' && str_word_count($s) > 3);
    if (count($sentences) >= 4) {
        $lengths = array_map('str_word_count', $sentences);
        $mean = array_sum($lengths) / count($lengths);
        $variance = array_sum(array_map(fn($l) => ($l - $mean) ** 2, $lengths)) / count($lengths);
        $stdDev = sqrt($variance);
        if ($stdDev < 2.5) {
            $score += 25;
        } elseif ($stdDev < 4.0) {
            $score += 10;
        }
    }

    // Passive voice overuse (signal: +8 each instance)
    $passiveMatches = preg_match_all('/\b(?:is|are|was|were|be|been|being)\s+\w+ed\b/i', $text, $dummy);
    if ($passiveMatches > 3) {
        $score += min(24, ($passiveMatches - 3) * 8);
    }

    return min(100, $score);
}

// ─────────────────────────────────────────────────────────────
// HELPER: Build Gemini prompt to rewrite ONE specific glossary field
// Strictly field-isolated: only rewrites the requested field.
// Eliminates both robotic academic jargon AND fake conversational AI clichés.
// ─────────────────────────────────────────────────────────────
function buildGlossaryFieldPrompt(string $acronym, string $fieldName, string $currentText): string
{
    $fieldRules = match ($fieldName) {
        'overview' => <<<EOR
Write exactly 2 clear, factual sentences about {$acronym}.
Sentence 1: What {$acronym} is, its statutory/administrative status, and which ministry or state department governs it.
Sentence 2: What major recruitments or examinations it conducts and for what roles.
Do NOT include eligibility, selection stages, or syllabus here.
EOR,
        'eligibility_criteria' => <<<EOR
State the essential eligibility requirements for {$acronym} recruitment in 2 or 3 clean sentences:
- Academic qualification required (degrees, acceptable disciplines, and minimum percentage).
- Age limits for general category along with statutory relaxations (OBC, SC/ST).
Do NOT include overview, selection stages, or syllabus here.
EOR,
        'selection_process' => <<<EOR
State the official selection stages for {$acronym} in chronological order using this format:
Stage 1: [Name of first stage, e.g. Computer Based Test] evaluating [subject scope].
Stage 2: [Name of second stage, e.g. Interview or Skill Test] for candidates qualifying Stage 1 merit.
Stage 3: Document verification and medical examination at designated centers.
Do NOT include overview, eligibility qualifications, or syllabus here.
EOR,
        'syllabus_snapshot' => <<<EOR
State the examination syllabus breakdown for {$acronym} in 2 clear sentences:
Sentence 1 (Technical/Domain): Core subjects covered from relevant engineering or professional streams.
Sentence 2 (General Aptitude): Non-technical topics (Reasoning, Quantitative Aptitude, English, and General Awareness).
Do NOT include overview, eligibility qualifications, or selection stages here.
EOR,
        default => "Rewrite this {$fieldName} for {$acronym} clearly and concisely.",
    };

    return <<<PROMPT
You are a senior education editor for an Indian government jobs portal (like The Hindu Education or ClearIAS).

Task: Rewrite the "{$fieldName}" section for "{$acronym}" based on the source text below.

FIELD REQUIREMENTS:
{$fieldRules}

EDITORIAL RULES (STRICT):
1. Write in objective, authoritative, candidate-friendly Indian English — NOT robotic, NOT casual blog banter.
2. ABSOLUTELY FORBIDDEN CONVERSATIONAL FILLER (these trigger AI detectors instantly):
   - "Honestly", "Look,", "Basically,", "Here's the deal", "Quick heads-up", "Quick note", "Also worth knowing"
   - "Don't overthink it", "You've got this", "Good luck", "Hit me up", "trip up", "grill you", "balancing act"
   - "No surprises here", "They're strict about this", "I've seen", "stress way too much", "folks", "gotta"
3. ABSOLUTELY FORBIDDEN ACADEMIC AI CLICHÉS:
   - "pivotal role", "crucial step", "serves as a testament", "beacon of hope", "in today's competitive era"
   - "without further ado", "delve into", "it is worth noting", "it is important to note", "furthermore", "moreover"
   - "it is imperative that", "candidates are advised to", "in conclusion", "to summarize"
4. Keep all factual details exact: degree names, percentage cutoffs, age figures, category relaxations.
5. Return ONLY the rewritten text for this single field. No section headers, no preamble, no markdown asterisks.

SOURCE TEXT:
{$currentText}

REWRITTEN {$fieldName}:
PROMPT;
}

// ─────────────────────────────────────────────────────────────
// HELPER: Build Gemini prompt to rewrite one article paragraph
// ─────────────────────────────────────────────────────────────
function buildArticleParagraphPrompt(string $articleTitle, string $paragraphText): string
{
    return <<<PROMPT
You are a senior journalist writing for an Indian government employment publication.

Rewrite the following paragraph from the article "{$articleTitle}" into clean, direct, informative Indian English.

STRICT EDITORIAL RULES:
1. Write objectively and clearly. State the facts directly.
2. Vary sentence length naturally: combine a short direct statement (5-8 words) with a detailed explanatory sentence (15-20 words).
3. ABSOLUTELY BANNED CONVERSATIONAL PHRASES (zero tolerance):
   - "Honestly", "Look,", "Basically,", "Here's the thing", "Here's the deal", "Quick heads-up", "Quick note"
   - "Don't overthink", "You've got this", "Good luck", "Hit me up", "balancing act", "trip up", "folks", "gotta"
4. ABSOLUTELY BANNED AI CLICHÉS (zero tolerance):
   - "pivotal role", "crucial step", "serves as a testament", "in today's competitive era", "without further ado"
   - "delve into", "it is worth noting", "furthermore", "moreover", "it is imperative that", "candidates are advised"
5. Retain every single fact: dates, numbers, exam names, URLs, application fees, cutoffs.
6. Return ONLY the rewritten paragraph text. No preamble, no quotes, no labels.

ORIGINAL:
{$paragraphText}

REWRITTEN:
PROMPT;
}

// ─────────────────────────────────────────────────────────────
// 1. HEAL GLOSSARY TERMS
// ─────────────────────────────────────────────────────────────
if ($healGlossary) {
    echo "--- SCANNING A-Z GLOSSARY TERMS ---\n";

    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'glossary_terms'");
    if (!$tableExists) {
        echo "⚠️  Table 'glossary_terms' does not exist. Run database/seed_glossary.php first.\n";
    } else {
        $sql = "SELECT id, acronym, slug, overview, eligibility_criteria, selection_process, syllabus_snapshot FROM glossary_terms";
        $params = [];
        if ($slug) {
            $sql .= " WHERE slug = :slug";
            $params['slug'] = $slug;
        } else {
            $sql .= " ORDER BY id ASC";
            if ($batch > 0 && !isset($options['all'])) {
                $sql .= " LIMIT {$batch}";
            }
        }

        $terms = Database::fetchAll($sql, $params);
        echo "Found " . count($terms) . " glossary term(s) to inspect.\n\n";

        $healedCount  = 0;
        $llmRewrites  = 0;
        $detCount     = 0;

        foreach ($terms as $term) {
            $tId     = (int)$term['id'];
            $acronym = $term['acronym'];
            $tSlug   = $term['slug'];

            echo "📚 [#{$tId}] {$acronym} (/{$tSlug})\n";

            $fieldsToClean = ['overview', 'eligibility_criteria', 'selection_process', 'syllabus_snapshot'];
            $updates  = [];
            $hasChange = false;

            foreach ($fieldsToClean as $field) {
                $original = $term[$field] ?? '';
                if (empty(trim($original))) {
                    continue;
                }

                // ── Layer 1: Deterministic scrubbing ──
                $cleaned = $original;
                $scrubbed = HumanizerService::scrubClichePatterns("<p>" . $cleaned . "</p>");
                $cleaned  = strip_tags($scrubbed['html']);
                $cleaned  = HumanizerService::scrubClichés($cleaned);
                $cleaned  = HumanizerService::enforceContractions($cleaned);

                // ── Heuristic score ──
                $score = aiHeuristicScore($cleaned);
                $needsLlm = $forceLlm || $score >= 15;

                echo "   [{$field}] AI Score: {$score}/100" . ($needsLlm ? " → LLM rewrite triggered" : " → clean") . "\n";

                // ── Layer 2: Gemini LLM rewrite ──
                if ($needsLlm && $gemini !== null) {
                    $prompt = buildGlossaryFieldPrompt($acronym, $field, $cleaned);
                    try {
                        $res = $gemini->generate($prompt, [
                            'temperature'       => 0.75,
                            'max_output_tokens' => 800,
                        ]);
                        $rewritten = trim($res['text'] ?? '');
                        if (!empty($rewritten) && mb_strlen($rewritten) > 30) {
                            // ── Post-LLM scrubbing: strip AI filler Gemini added ──
                            $postScrub = HumanizerService::scrubClichePatterns("<p>" . $rewritten . "</p>");
                            $rewritten = strip_tags($postScrub['html']);
                            $rewritten = HumanizerService::scrubClichés($rewritten);
                            if (!empty($postScrub['removed'])) {
                                echo "      🧹 Post-LLM scrubber removed " . count($postScrub['removed']) . " AI filler phrase(s) Gemini added.\n";
                            }
                            $cleaned = $rewritten;
                            $llmRewrites++;
                            echo "      ✨ Gemini rewrote {$field} successfully.\n";
                        }
                    } catch (\Throwable $e) {
                        echo "      ⚠️  Gemini failed for {$field}: " . $e->getMessage() . "\n";
                    }
                }

                if ($cleaned !== $original) {
                    $updates[$field] = $cleaned;
                    $hasChange = true;
                }
            }

            if ($hasChange) {
                $healedCount++;
                if (!$dryRun) {
                    $setClauses = [];
                    $params2    = ['id' => $tId];
                    foreach ($updates as $k => $v) {
                        $setClauses[] = "`{$k}` = :{$k}";
                        $params2[$k]  = $v;
                    }
                    $setSql = implode(', ', $setClauses);
                    Database::execute(
                        "UPDATE glossary_terms SET {$setSql}, updated_at = NOW() WHERE id = :id",
                        $params2
                    );
                    echo "   💾 SAVED: {$acronym} updated in DB.\n";
                } else {
                    echo "   🔍 DRY-RUN: Would update {$acronym} ({" . implode(', ', array_keys($updates)) . "}).\n";
                }
            } else {
                echo "   ✅ Already human-grade. No changes needed.\n";
            }

            echo "\n";
            // Small sleep to avoid Gemini rate limits
            if ($gemini !== null && !$dryRun) {
                usleep(300000); // 300ms
            }
        }

        echo "Glossary Summary: {$healedCount} terms healed, {$llmRewrites} fields rewritten by Gemini.\n\n";
    }
}

// ─────────────────────────────────────────────────────────────
// 2. HEAL ARTICLES
// ─────────────────────────────────────────────────────────────
if ($healArticles) {
    echo "--- SCANNING PUBLISHED ARTICLES ---\n";

    $sql    = "SELECT id, title, slug, content, source_url FROM articles WHERE status = 'published'";
    $params = [];
    if ($slug) {
        $sql   .= " AND slug = :slug";
        $params['slug'] = $slug;
    } else {
        $sql .= " ORDER BY id DESC";
        if ($batch > 0 && !isset($options['all'])) {
            $sql .= " LIMIT {$batch}";
        }
    }

    $articles = Database::fetchAll($sql, $params);
    echo "Found " . count($articles) . " article(s) to analyze.\n\n";

    $healedArticlesCount = 0;
    $totalPatternsScrubbed = 0;
    $llmParagraphRewrites  = 0;

    foreach ($articles as $art) {
        $id              = (int)$art['id'];
        $title           = $art['title'];
        $artSlug         = $art['slug'];
        $originalContent = $art['content'];
        $currentContent  = $originalContent;

        echo "📄 [Article #{$id}] {$title}\n";
        echo "   URL: " . SITE_URL . "/article/{$artSlug}/\n";

        // ── Layer 1A: Cliché Scrubbing ──
        $scrubResult = HumanizerService::scrubClichePatterns($currentContent);
        $currentContent = $scrubResult['html'];
        $removedItems   = $scrubResult['removed'];

        if (!empty($removedItems)) {
            echo "   ✂️  Scrubbed " . count($removedItems) . " robotic pattern(s).\n";
            $totalPatternsScrubbed += count($removedItems);
        } else {
            echo "   ✅ No antithesis/encyclopedic patterns found.\n";
        }

        // ── Layer 1B: Contractions + Clichés ──
        $currentContent = HumanizerService::scrubClichés($currentContent);
        $currentContent = HumanizerService::enforceContractions($currentContent);

        // ── Layer 1C: Structure dense prose lists ──
        $currentContent = HumanizerService::structureDenseProseLists($currentContent);

        // ── Layer 2: Gemini — rewrite uniform-cadence paragraphs ──
        if ($gemini !== null) {
            $flaggedParagraphs = HumanizerService::detectUniformCadenceParagraphs($currentContent);
            if (!empty($flaggedParagraphs)) {
                echo "   ⚠️  Detected " . count($flaggedParagraphs) . " robotic uniform-cadence paragraph(s) (StdDev < 3.0).\n";

                foreach (array_slice($flaggedParagraphs, 0, 3) as $fp) {
                    // Also run heuristic — only rewrite if both cadence AND AI score agree
                    $paraScore = aiHeuristicScore($fp['text']);
                    if (!$forceLlm && $paraScore < 15) {
                        echo "      ℹ️  Para #{$fp['paragraph_index']} cadence flagged but AI score low ({$paraScore}), skipping LLM.\n";
                        continue;
                    }

                    $prompt = buildArticleParagraphPrompt($title, $fp['text']);
                    try {
                        $res = $gemini->generate($prompt, [
                            'temperature'       => 0.75,
                            'max_output_tokens' => 600,
                        ]);
                        $rewritten = trim($res['text'] ?? '');

                        if (!empty($rewritten) && mb_strlen($rewritten) > 30 && !str_contains($rewritten, '<html')) {
                            if (str_contains($currentContent, $fp['text'])) {
                                $currentContent = str_replace($fp['text'], $rewritten, $currentContent);
                                $llmParagraphRewrites++;
                                echo "      ✨ Gemini varied cadence of paragraph #{$fp['paragraph_index']} (AI score was {$paraScore}).\n";
                            }
                        }
                    } catch (\Throwable $e) {
                        echo "      ⚠️  Gemini skipped para #{$fp['paragraph_index']}: " . $e->getMessage() . "\n";
                    }

                    usleep(250000); // 250ms between paragraphs
                }
            } else {
                echo "   ✅ No uniform-cadence paragraphs detected.\n";
            }
        }

        // ── Layer 1D: Sanitize opening hook ──
        $currentContent = HumanizerService::sanitizeOpeningHook(
            $currentContent,
            $title,
            $art['source_url'] ?? ''
        );

        // ── Check for overall article AI score and LLM-rewrite if needed ──
        $articleScore = aiHeuristicScore(strip_tags($currentContent));
        echo "   📊 Overall article AI heuristic: {$articleScore}/100\n";

        if ($currentContent !== $originalContent) {
            $healedArticlesCount++;
            $diff = mb_strlen($currentContent) - mb_strlen($originalContent);
            echo "   📊 Size delta: {$diff} chars.\n";

            if (!$dryRun) {
                Database::execute(
                    "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                    ['content' => $currentContent, 'id' => $id]
                );
                echo "   💾 SAVED: Article #{$id} updated.\n";
            } else {
                echo "   🔍 DRY-RUN: Changes detected but not saved.\n";
            }
        } else {
            echo "   ✅ Already human-grade. No changes needed.\n";
        }

        echo "\n";
    }

    echo "Article Summary: {$healedArticlesCount} articles healed, {$totalPatternsScrubbed} clichés scrubbed, {$llmParagraphRewrites} paragraphs rewritten by Gemini.\n\n";
}

echo "================================================================================\n";
echo "✅ HUMAN CONTENT HEALING COMPLETED AT " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n";
