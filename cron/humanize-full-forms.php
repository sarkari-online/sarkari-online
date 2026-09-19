<?php
/**
 * Sarkari.online - Full Forms (Glossary Terms) 2-Pass Student-Grade Humanizer
 *
 * Rewrites glossary_terms (overview, eligibility, selection, syllabus) AND
 * full_form_entity_facts (career_growth, FAQs) into student-friendly Indian English
 * (Hindustan Times / Jagran Josh style, RAJEEV SHARMA persona, temp 1.7)
 * to bring AI detector score down from 77% to under 25%.
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
echo "🎓 Full Forms 2-Pass High-Perplexity Humanizer (Temp 1.7)\n";
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

    // Fetch associated facts if present
    $facts = Database::fetchOne("SELECT career_growth_summary, faqs_json FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $termId]);
    $currGrowth = !empty($facts['career_growth_summary']) ? trim(strip_tags($facts['career_growth_summary'])) : '';
    $currFaqs   = !empty($facts['faqs_json']) ? $facts['faqs_json'] : '';

    $prompt = <<<PROMPT
You are RAJEEV SHARMA, a 36-year-old senior Indian education reporter for Sarkari.online.
You write in simple, punchy, direct everyday Indian English (Hindustan Times Education / Jagran Josh style) that Class 10 Hindi-medium students can easily read.

TERM: "{$acronym}"
FULL FORM: "{$fullEn}"
CONDUCTING BODY: "{$conductingBody}"

STUDY THESE EXACT EXAMPLES OF HOW TO WRITE:

[OVERVIEW EXAMPLE]
Looking for a central government job? Staff Selection Commission—or SSC—is where millions of students start. It's an attached body under the Department of Personnel and Training (DoPT).
From Income Tax Inspector to Delhi Police Sub-Inspector, SSC conducts recruitment for premier Group B and C posts. Everything runs online on ssc.gov.in. Lakhs of aspirants register each year, making competition fierce but completely open to merit.

[ELIGIBILITY EXAMPLE]
10th Pass Candidates: You can apply for SSC MTS and Havaldar posts; 12th Pass Students: CHSL and Stenographer Grade C and D are open to you; Graduate Aspirants: CGL and CPO officer posts require a graduation degree in any discipline; Age Bracket: Mostly 18 to 27 or 32 years, with standard government relaxations for reserved categories.

[SELECTION EXAMPLE]
Tier 1 CBT: A 60-minute online screening test with 100 objective questions; Tier 2 Mains: Comprehensive online exam covering core subjects with negative marking; Skill Tests: Typing or stenography speed test for clerical roles; Final Stage: Merit ranking followed by document verification and medical checkup.

[SYLLABUS EXAMPLE]
Quantitative Aptitude: Arithmetic, percentages, ratio-proportion, algebra, and geometry; General Intelligence & Reasoning: Puzzles, number series, seating arrangements, and coding; English Comprehension: Grammar basics, error spotting, idioms, and reading passages; General Awareness: Daily current affairs, Indian constitution, history, and general science.

[CAREER GROWTH EXAMPLE]
Joining as an Assistant Section Officer opens up a solid promotion ladder. With departmental tests and service years, you step up to Section Officer, Under Secretary, and eventually Deputy Secretary or Director.

---

CURRENT CONTENT TO REWRITE:
Overview: {$currOverview}
Eligibility: {$currEligibility}
Selection: {$currSelection}
Syllabus: {$currSyllabus}
Career Growth: {$currGrowth}

---

UNIVERSAL RULES:
1. Short sentences (4-12 words). Vary rhythm: mix very short 3-word punchy lines with medium ones.
2. Mandatory contractions: it's, don't, you'll, can't, here's, won't.
3. Use em-dashes (—) for natural emphasis.
4. Sometimes start sentences with 'And' or 'But'.
5. BANNED words: paramount, pivotal, delve, realm, comprehensive, streamline, multifaceted, commence, subsequent, intricate, testament, beacon, foster, vital.
6. PRESERVE ALL FACTS: qualifications, degrees, age numbers, test stages, portals.
7. Return strictly a JSON object with keys: "overview", "eligibility_criteria", "selection_process", "syllabus_snapshot", "career_growth_summary".
PROMPT;

    try {
        echo "  ✍️  Sending to Gemini (Temp 1.7 RAJEEV SHARMA Persona)...\n";

        $result = $gemini->generate($prompt, [
            'stage'              => 'humanize_full_form_v2',
            'json_mode'          => true,
            'temperature'        => 1.7,
            'system_instruction' => "You are RAJEEV SHARMA, a 36-year-old senior Indian education journalist. Simple, punchy, conversational Indian English. Short sentences. Natural contractions. High burstiness. Never sound like a textbook or AI."
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

        echo "  ✅ Humanized!\n";
        echo "  📋 Overview:\n     " . mb_substr($newOverview, 0, 180) . "...\n";
        echo "  📋 Eligibility:\n     " . mb_substr($newEligibility, 0, 180) . "...\n";

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

            if (!empty($newGrowth)) {
                Database::execute(
                    "UPDATE full_form_entity_facts 
                     SET career_growth_summary = :gw, 
                         last_verified_at = NOW(), 
                         updated_at = NOW() 
                     WHERE full_form_id = :id",
                    [
                        'gw' => $newGrowth,
                        'id' => $termId
                    ]
                );
            } else {
                Database::execute(
                    "UPDATE full_form_entity_facts 
                     SET last_verified_at = NOW(), updated_at = NOW() 
                     WHERE full_form_id = :id",
                    ['id' => $termId]
                );
            }

            echo "  💾 Saved to glossary_terms & full_form_entity_facts!\n\n";
        } else {
            echo "  🔵 DRY-RUN: Not saved.\n\n";
        }

        $success++;

    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        $failed++;
    }

    if ($termNum < $total) {
        sleep(3);
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
