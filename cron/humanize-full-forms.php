<?php
/**
 * Sarkari.online - Ultra-Crisp Full Forms 2-Pass Humanizer
 *
 * Rewrites ALL text on full-form pages (Overview, Eligibility, Selection, Syllabus,
 * Career Growth, Allowances, and FAQs) into ultra-short, punchy Class 10 student English.
 *
 * Eliminates long essays, DoPT textbook jargon, and robotic AI patterns.
 * Brings AI detector score strictly below 20%.
 *
 * Usage:
 *   php cron/humanize-full-forms.php --acronym=SSC --dry-run
 *   php cron/humanize-full-forms.php --acronym=SSC
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
echo "⚡ Ultra-Short & Crisp Full Forms Humanizer (Temp 1.7)\n";
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
} else {
    // Only process terms not yet humanized today
    $where .= " AND (last_reviewed_at IS NULL OR last_reviewed_at < CURDATE())";
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

    // Fetch associated facts
    $facts = Database::fetchOne("SELECT career_growth_summary, allowances_summary, faqs_json FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $termId]);
    $currGrowth     = !empty($facts['career_growth_summary']) ? trim(strip_tags($facts['career_growth_summary'])) : '';
    $currAllowances = !empty($facts['allowances_summary']) ? trim(strip_tags($facts['allowances_summary'])) : '';
    $rawFaqs        = !empty($facts['faqs_json']) ? json_decode($facts['faqs_json'], true) : [];

    $faqPromptText = "";
    if (is_array($rawFaqs) && !empty($rawFaqs)) {
        $faqPromptText = "FAQS TO REWRITE SHORT:\n";
        foreach ($rawFaqs as $fi => $f) {
            $faqPromptText .= "Q: " . ($f['q'] ?? '') . "\n";
            $faqPromptText .= "Current A: " . ($f['a'] ?? '') . "\n\n";
        }
    }

    $prompt = <<<PROMPT
You are RAJEEV SHARMA, a 36-year-old senior Indian education journalist writing for Sarkari.online.
TARGET READERS: Indian students preparing for competitive exams (mostly Class 10/12 Hindi-medium background).
MISSION: Rewrite this full-form guide to be ULTRA-SHORT, PUNCHY, SIMPLE, and 100% HUMAN. No long essays!

TERM: "{$acronym}"
FULL FORM: "{$fullEn}"
CONDUCTING BODY: "{$conductingBody}"

CURRENT DATA (this is your ONLY source of facts — see ABSOLUTE RULE below):
Overview: {$currOverview}
Eligibility: {$currEligibility}
Selection: {$currSelection}
Syllabus: {$currSyllabus}
Career Growth: {$currGrowth}
Allowances: {$currAllowances}
FAQS TO REWRITE SHORT:
{$faqPromptText}

---

WRITE ACCORDING TO THESE STRICT FORMAT RULES:

1. "overview": Max 45 words. 2 short punchy paragraphs. Start directly without robotic openings. Use only entity/body names and facts given above — nothing else.
   
   Structure example (rhythm only — do not copy any names or numbers from this, it is illustrative of SENTENCE SHAPE only):
   "Looking for a [type] government job? [Acronym]—or [full form]—is where [target group] begin. It's a [status] under [conducting body].\n\nFrom [example post] to [example post], [acronym] conducts exams for [category] posts on [official site]."

2. "eligibility_criteria": Max 4 short points separated by semicolons (each point under 14 words). Only include a point if CURRENT DATA actually contains that specific fact — if fewer than 4 genuine facts are available, write fewer points rather than padding.
   
   Structure example (rhythm only, no real numbers):
   "[Qualification level]: Apply for [post types]; [Qualification level]: [post types] are open to you; [Qualification level]: [post types] require [requirement]; Age Limit: [only if explicitly stated in input data], with relaxations for reserved categories [only if input data confirms this]."

3. "selection_process": Max 4 short sequential points separated by semicolons (each point under 12 words). Stage names and formats must come from CURRENT DATA only.
   
   Structure example (rhythm only):
   "[Stage name]: A [format] testing [what it tests]; [Stage name]: [format] covering [subjects]; [Stage name if applicable]: [skill/type] test where applicable; Final Stage: [what determines final selection]."

4. "syllabus_snapshot": Up to 4 core subjects separated by semicolons (each point under 10 words), drawn only from CURRENT DATA. If fewer than 4 subjects are given, list only what's given.
   
   Structure example (rhythm only):
   "[Subject]: [sub-topics]; [Subject]: [sub-topics]; [Subject]: [sub-topics]; [Subject]: [sub-topics]."

5. "career_growth_summary": Max 30 words. 2 short sentences. The progression path must match CURRENT DATA exactly — do not invent intermediate ranks or a 'typical' ladder if the input doesn't specify one.
   
   Structure example (rhythm only):
   "Start as [entry post]. Through [progression mechanism], you step up to [next levels] — as stated in the given data, nothing added."

6. "allowances_summary": Max 20 words. 1 short sentence. List only allowances named in CURRENT DATA.
   
   Structure example (rhythm only):
   "Includes [allowance], [allowance], and [allowance] as applicable."

7. "faqs": Exactly 3 FAQs with ULTRA-SHORT answers (1-2 sentences, max 25 words per answer). Rewrite the wording/tone of FAQS for punch and simplicity — but every fact, number, and figure inside an answer must already be present in that same FAQ's original text. Do not add a number that wasn't in the source FAQ.

---

RULES:
- Keep sentences short (4-12 words).
- Use natural contractions (it's, you'll, don't, can't, here's).
- Use em-dashes (—).
- Class 10 vocabulary only. BANNED: paramount, pivotal, delve, realm, comprehensive, streamline, multifaceted, commence, subsequent, intricate, testament, beacon, foster, vital.
- FACTUAL LOCK: Every number, date, age, percentage, fee, or post name in your output must be traceable word-for-word to the CURRENT DATA fields above. If you cannot trace it, remove it. A short, factually-bare sentence is always correct; an invented one is strictly forbidden.
- Do not generalize a specific input fact into a broader claim.

Return ONLY a valid JSON object matching this schema:
{
  "overview": "...",
  "eligibility_criteria": "...",
  "selection_process": "...",
  "syllabus_snapshot": "...",
  "career_growth_summary": "...",
  "allowances_summary": "...",
  "faqs": [
    {"q": "...", "a": "..."}
  ]
}
PROMPT;

    try {
        echo "  ✍️  Sending to Gemini (Temp 1.7 Ultra-Short Humanizer)...\n";

        $result = $gemini->generate($prompt, [
            'stage'              => 'humanize_full_form_v3',
            'json_mode'          => true,
            'temperature'        => 1.7,
            'system_instruction' => "You are RAJEEV SHARMA, a 36-year-old senior Indian education journalist. Write in simple, ultra-short, punchy everyday Indian English. Never write long essays or academic paragraphs. Every sentence is direct and conversational. ABSOLUTE RULE — NEVER BROKEN: You may only use factual data (numbers, dates, ages, percentages, fees, post names, pay figures) that appears literally in the CURRENT DATA fields given to you. If a fact isn't in the provided data, leave it out entirely — do not estimate, round, generalize, or invent. A missing fact produces a shorter sentence, never a guessed one. Return strictly valid JSON."
        ]);

        $rawText = trim($result['text'] ?? '');
        $json = json_decode($rawText, true);

        if (!$json && preg_match('/\{.*\}/s', $rawText, $m)) {
            $json = json_decode($m[0], true);
        }

        if (!$json || empty($json['overview'])) {
            throw new \Exception("Invalid JSON returned: " . substr($rawText, 0, 150));
        }

        $newOverview    = trim($json['overview']);
        $newEligibility = trim($json['eligibility_criteria'] ?? $currEligibility);
        $newSelection   = trim($json['selection_process'] ?? $currSelection);
        $newSyllabus    = trim($json['syllabus_snapshot'] ?? $currSyllabus);
        $newGrowth      = trim($json['career_growth_summary'] ?? $currGrowth);
        $newAllowances  = trim($json['allowances_summary'] ?? $currAllowances);
        $newFaqs        = !empty($json['faqs']) && is_array($json['faqs']) ? json_encode($json['faqs'], JSON_UNESCAPED_UNICODE) : null;

        $wordCount = str_word_count($newOverview . ' ' . $newEligibility . ' ' . $newSelection . ' ' . $newSyllabus);
        echo "  ✅ Humanized! Total prose words: {$wordCount} (Ultra-Short)\n";
        echo "  📋 Overview:\n     " . mb_substr($newOverview, 0, 150) . "...\n";

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

            // Update full_form_entity_facts
            $factsParams = [
                'id' => $termId,
                'gw' => $newGrowth ?: null,
                'al' => $newAllowances ?: null,
            ];

            $faqUpdateSql = "";
            if (!empty($newFaqs)) {
                $faqUpdateSql = ", faqs_json = :faqs";
                $factsParams['faqs'] = $newFaqs;
            }

            Database::execute(
                "UPDATE full_form_entity_facts 
                 SET career_growth_summary = :gw, 
                     allowances_summary = :al, 
                     last_verified_at = NOW(), 
                     updated_at = NOW() 
                     {$faqUpdateSql}
                 WHERE full_form_id = :id",
                $factsParams
            );

            echo "  💾 Saved to glossary_terms & full_form_entity_facts (including FAQs & Allowances)!\n\n";
        } else {
            echo "  🔵 DRY-RUN: Not saved.\n\n";
        }

        $success++;

    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        echo "  ❌ Error: " . $msg . "\n\n";
        $failed++;

        // If rate limit or circuit breaker, sleep and retry this term
        if (str_contains($msg, 'circuit breaker') || str_contains($msg, '429') || str_contains($msg, 'Rate limit')) {
            $waitTime = 40;
            if (preg_match('/wait ([0-9]+)s/i', $msg, $m)) {
                $waitTime = (int)$m[1] + 3;
            } elseif (preg_match('/cooldown active for ([0-9]+)s/i', $msg, $m)) {
                $waitTime = (int)$m[1] + 3;
            }
            echo "  ⏳ Rate limit hit. Sleeping {$waitTime}s before continuing...\n\n";
            sleep($waitTime);
        }
    }

    if ($termNum < $total) {
        // Sleep 6s between calls to prevent hitting 15 RPM free tier limits
        sleep(6);
    }
}

echo "==========================================================\n";
echo "✅ ULTRA-SHORT FULL FORMS HUMANIZER COMPLETE\n";
echo "==========================================================\n";
echo "Processed: {$total}\n";
echo "Success:   {$success}\n";
echo "Failed:    {$failed}\n";
echo "Mode:      " . ($isDryRun ? "DRY-RUN" : "LIVE SAVED") . "\n";
echo "==========================================================\n";
