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
// HELPER: Build Gemini prompt to rewrite one glossary field
// ─────────────────────────────────────────────────────────────
function buildGlossaryFieldPrompt(string $acronym, string $fieldName, string $currentText): string
{
    $fieldLabel = match ($fieldName) {
        'overview'            => 'Overview (what the exam/organization is)',
        'eligibility_criteria'=> 'Eligibility Criteria (age, qualification, marks)',
        'selection_process'   => 'Selection Process (stages: exam, interview, doc verification)',
        'syllabus_snapshot'   => 'Syllabus Snapshot (key topics covered)',
        default               => $fieldName,
    };

    return <<<PROMPT
Convert the following "{$fieldLabel}" info about {$acronym} into a clean, scannable DATA FORMAT — no prose sentences, only facts.

OUTPUT FORMAT RULES (mandatory):
- For eligibility: use this EXACT format:
  Degree: [branch names]
  Marks: [percentage]
  Age: [numbers with category breakdown]
  College: [recognition requirement]

- For selection process: use this EXACT format:
  Step 1 → [stage name]: [one short fact, max 6 words]
  Step 2 → [stage name]: [one short fact, max 6 words]
  (etc.)

- For syllabus: use this EXACT format:
  Core: [topic1], [topic2], [topic3]
  Non-tech: [topic1], [topic2]
  Tip: [one factual observation, no motivational filler]

- For overview: 2 sentences MAX. First: what the org does (one line). Second: why aspirants apply (one line with a number or fact).

ABSOLUTE RULES:
1. NO informal phrases: no "Quick heads-up", "Here's the deal", "I've seen", "Basically", "Look,", "Honestly,", "You've got this", "Good luck", "don't overthink", "stay calm", "balancing act".
2. NO sentences with subject-verb-object structure unless it's the overview field.
3. Preserve ALL numbers exactly: percentages, years, stage counts.
4. Return ONLY the formatted data. No preamble. No headers. No markdown symbols except dashes and arrows.

SOURCE DATA TO CONVERT:
{$currentText}

CLEAN DATA OUTPUT:
PROMPT;
}

// ─────────────────────────────────────────────────────────────
// HELPER: Build Gemini prompt to rewrite one article paragraph
// ─────────────────────────────────────────────────────────────
function buildArticleParagraphPrompt(string $articleTitle, string $paragraphText): string
{
    return <<<PROMPT
You are a real human writer — a senior coaching mentor in India who has helped hundreds of students clear government exams. You're writing this paragraph for your coaching blog, not for an AI portal.

Rewrite this paragraph from "{$articleTitle}" the way you'd naturally type it — like you're explaining it to a batch of students in a coaching session. Your goal: make it impossible for QuillBot AI Detector to flag this as AI.

MANDATORY RULES (break any = failure):
1. START the rewrite with either a very short punchy sentence (3–6 words) OR a direct question.
2. VARY sentence length aggressively — short (3–7 words), medium (10–14 words), long (16–22 words) — mix them, never two of same length back-to-back.
3. Use contractions: "you'll", "it's", "don't", "there's", "can't", "won't", "they're", "I've seen".
4. Use at least 2 informal connectors from: "Look,", "Honestly,", "Basically,", "Here's the thing —", "No surprises here —", "Quick note:", "Oh, and", "Worth knowing:".
5. NEVER use: "furthermore", "moreover", "it is worth noting", "candidates are advised", "it is important to note", "plays a crucial role", "it is imperative", "with respect to", "as per the official", "in conclusion", "to summarize", "in today's competitive", "needless to say", "at the end of the day".
6. DO NOT literally use these AI-detectable phrases: "You've got this", "Good luck", "Hit me up", "Focus your energy on", "no surprises here", "this is where candidates slip up", "keep your basics rock solid", "stay consistent with your prep", "You're going to nail this", "master the basics", "rock solid".
7. Preserve EVERY fact exactly: all numbers, dates, percentages, exam names, URLs, seat counts — word-for-word unchanged.
8. Do NOT add facts not in the original.
9. Keep length within 25% of original.
10. Return ONLY the rewritten paragraph. No preamble, no label, no explanation.

ORIGINAL PARAGRAPH:
{$paragraphText}

YOUR REWRITE (natural coaching mentor voice):
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
