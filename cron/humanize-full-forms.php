<?php
/**
 * Sarkari.online - Full Forms (Glossary Terms) 2-Pass Humanizer
 *
 * Rewrites glossary_terms (overview, eligibility, selection, syllabus) into
 * student-friendly Indian English (Hindustan Times / Jagran Josh style)
 * to bring AI detector score from 60%+ down to under 25%.
 *
 * Preserves all facts, tables, direct answer definitions, and schemas.
 *
 * Usage:
 *   php cron/humanize-full-forms.php --acronym=SSC --dry-run
 *   php cron/humanize-full-forms.php --acronym=SSC
 *   php cron/humanize-full-forms.php --limit=5 --dry-run
 *   php cron/humanize-full-forms.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;

$options        = getopt('', ['dry-run::', 'acronym::', 'slug::', 'limit::', 'offset::']);
$isDryRun       = isset($options['dry-run']);
$targetAcronym  = isset($options['acronym']) ? strtoupper(trim($options['acronym'])) : null;
$targetSlug     = isset($options['slug']) ? strtolower(trim($options['slug'])) : null;
$limit          = isset($options['limit']) ? max(1, (int)$options['limit']) : 999;
$offset         = isset($options['offset']) ? max(0, (int)$options['offset']) : 0;

echo "==========================================================\n";
echo "🎓 Full Forms (A-Z) 2-Pass Student-Friendly Humanizer\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (no DB save)" : "LIVE SAVE") . "\n";
if ($targetAcronym) echo "Target Acronym: {$targetAcronym}\n";
if ($targetSlug)    echo "Target Slug:    {$targetSlug}\n";
echo "==========================================================\n\n";

// Fetch terms
$where = "1=1";
$params = [];

if ($targetAcronym) {
    $where .= " AND UPPER(acronym) = :acr";
    $params['acr'] = $targetAcronym;
} elseif ($targetSlug) {
    $where .= " AND slug = :slg";
    $params['slg'] = $targetSlug;
}

$sql = "SELECT id, acronym, slug, full_form_en, full_form_hi, category, conducting_body, 
               overview, eligibility_criteria, selection_process, syllabus_snapshot 
        FROM glossary_terms 
        WHERE {$where} 
        ORDER BY id ASC 
        LIMIT {$limit} OFFSET {$offset}";

$terms = Database::fetchAll($sql, $params);
$total = count($terms);

if ($total === 0) {
    die("⚠️  No glossary terms found matching criteria.\n");
}

echo "📋 Found {$total} full form term(s) to process.\n\n";

$gemini = new Gemini();
$success = 0;
$failed  = 0;

foreach ($terms as $idx => $term) {
    $termId         = (int)$term['id'];
    $acronym        = $term['acronym'];
    $slug           = $term['slug'];
    $fullEn         = $term['full_form_en'];
    $category       = $term['category'] ?? 'examination';
    $conductingBody = $term['conducting_body'] ?? 'Government Authority';
    $termNum        = $idx + 1;

    echo "──────────────────────────────────────────────────────────\n";
    echo "[{$termNum}/{$total}] {$acronym} — {$fullEn} (ID: #{$termId})\n";
    echo "──────────────────────────────────────────────────────────\n";

    $currOverview    = trim(strip_tags($term['overview'] ?? ''));
    $currEligibility = trim(strip_tags($term['eligibility_criteria'] ?? ''));
    $currSelection   = trim(strip_tags($term['selection_process'] ?? ''));
    $currSyllabus    = trim(strip_tags($term['syllabus_snapshot'] ?? ''));

    $origWords = str_word_count($currOverview . ' ' . $currEligibility . ' ' . $currSelection . ' ' . $currSyllabus);
    echo "  📊 Original words: {$origWords} across 4 sections\n";

    $prompt = <<<PROMPT
You are rewriting full form directory content for Sarkari.online — India's #1 portal for competitive exams.

AUDIENCE: Indian government exam aspirants (many from Hindi-medium backgrounds). They need fast, scannable, simple English (Class 10 level).

TERM: "{$acronym}"
FULL FORM: "{$fullEn}"
CATEGORY: "{$category}"
CONDUCTING BODY: "{$conductingBody}"

CURRENT CONTENT TO REWRITE:
[OVERVIEW]
{$currOverview}

[ELIGIBILITY]
{$currEligibility}

[SELECTION PROCESS]
{$currSelection}

[SYLLABUS]
{$currSyllabus}

---

REWRITE RULES (Strict):
1. OVERVIEW: 2-3 short, punchy paragraphs (2-3 sentences each). Explain what {$acronym} is, who conducts it, and why students take it. Use natural contractions (it's, you'll, don't). Start with an engaging direct line, NEVER "The [Authority] has...".
2. ELIGIBILITY CRITERIA: Point-by-point format separated by semicolons for clean bullets. E.g.: "Age Limit: 18 to 27 years for general category, with up to 5 years relaxation for reserved categories; Educational Qualification: Graduate degree in any discipline from a recognized university; Nationality: Citizen of India."
3. SELECTION PROCESS: Clear sequential stages separated by semicolons. E.g.: "Stage 1: Preliminary Computer Based Test (CBT) with objective MCQs; Stage 2: Mains examination testing technical domain knowledge; Stage 3: Document Verification and statutory medical checkup."
4. SYLLABUS SNAPSHOT: Clear subject breakdown separated by semicolons. E.g.: "General Intelligence & Reasoning: Analogies, series, coding-decoding, and logical puzzles; Quantitative Aptitude: Arithmetic, algebra, data interpretation, and percentages; English Language: Vocabulary, grammar, sentence correction, and comprehension; General Awareness: Current affairs, Indian polity, history, and basic science."
5. UNIVERSAL RULES:
   - Mix short 3-6 word sentences with 12-15 word sentences.
   - Use em-dashes (—) for natural emphasis.
   - Use simple words. BANNED words: paramount, pivotal, delve, realm, comprehensive, streamline, multifaceted, commence, subsequent, intricate, testament.
   - KEEP ALL FACTS EXACT: degrees, age limits, marks percentages, exam stages, official portal names.
   - DO NOT make up any new rules or dates.

Return a valid JSON object with EXACTLY these 4 keys:
{
  "overview": "...",
  "eligibility_criteria": "...",
  "selection_process": "...",
  "syllabus_snapshot": "..."
}
PROMPT;

    try {
        echo "  ✍️  Sending to Gemini (High-Temp RAJEEV SHARMA Persona)...\n";

        $result = $gemini->generate($prompt, [
            'stage'              => 'humanize_full_form',
            'json_mode'          => true,
            'temperature'        => 1.5,
            'system_instruction' => "You are RAJEEV SHARMA, a 36-year-old senior Indian education journalist. You write in simple, punchy, direct everyday Indian English that even a Class 10 student can read. Natural contractions, short sentences, realistic Indian student tone. Return strictly valid JSON."
        ]);

        $rawText = trim($result['text'] ?? '');
        $json = json_decode($rawText, true);

        // Fallback cleanup if JSON wrapped in markdown code fence
        if (!$json && preg_match('/\{.*\}/s', $rawText, $m)) {
            $json = json_decode($m[0], true);
        }

        if (!$json || empty($json['overview'])) {
            throw new \Exception("Invalid JSON returned by Gemini: " . substr($rawText, 0, 150));
        }

        $newOverview    = trim($json['overview']);
        $newEligibility = trim($json['eligibility_criteria'] ?? $currEligibility);
        $newSelection   = trim($json['selection_process'] ?? $currSelection);
        $newSyllabus    = trim($json['syllabus_snapshot'] ?? $currSyllabus);

        $newWords = str_word_count($newOverview . ' ' . $newEligibility . ' ' . $newSelection . ' ' . $newSyllabus);

        echo "  ✅ Humanized! Words: {$origWords} → {$newWords}\n";
        echo "  📋 PREVIEW (Overview):\n";
        echo "     " . mb_substr($newOverview, 0, 220) . "...\n";

        if (!$isDryRun) {
            Database::execute(
                "UPDATE glossary_terms 
                 SET overview = :ov, 
                     eligibility_criteria = :el, 
                     selection_process = :sp, 
                     syllabus_snapshot = :sy, 
                     last_reviewed_at = CURDATE(), 
                     updated_at = NOW() 
                 WHERE id = :id",
                [
                    'ov' => $newOverview,
                    'el' => $newEligibility,
                    'sp' => $newSelection,
                    'sy' => $newSyllabus,
                    'id' => $termId
                ]
            );
            echo "  💾 Saved to database!\n\n";
        } else {
            echo "  🔵 DRY-RUN: Not saved.\n\n";
        }

        $success++;

    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        $failed++;
    }

    if ($termNum < $total) {
        sleep(3); // Rate-limit buffer
    }
}

echo "==========================================================\n";
echo "✅ FULL FORMS HUMANIZER COMPLETE\n";
echo "==========================================================\n";
echo "Processed: {$total}\n";
echo "Success:   {$success}\n";
echo "Failed:    {$failed}\n";
echo "Mode:      " . ($isDryRun ? "DRY-RUN" : "LIVE SAVED") . "\n";
echo "==========================================================\n";
