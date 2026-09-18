<?php
/**
 * Sarkari.online - Full Article 2-Pass Humanizer
 *
 * STRATEGY: Section-by-Section Humanization
 * 1. Article content ko H2 sections mein split karo
 * 2. Har section ki ONLY PROSE (not tables) ko Gemini se humanize karwao
 * 3. Tables ko bilkul touch mat karo — waise hi rakhne hain
 * 4. Humanized prose + original tables = Full humanized article
 * 5. Poora article DB mein save karo
 *
 * Usage:
 *   php cron/humanize-full-article.php --id=728 --dry-run
 *   php cron/humanize-full-article.php --id=728
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;

$options  = getopt('', ['id::', 'dry-run::']);
$targetId = (int)($options['id'] ?? 728);
$isDryRun = isset($options['dry-run']);

echo "==========================================================\n";
echo "🔬 Full Article Section-by-Section 2-Pass Humanizer\n";
echo "Article: #{$targetId}\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (no DB save)" : "LIVE SAVE") . "\n";
echo "==========================================================\n\n";

// ─── Fetch article ──────────────────────────────────────────────────────────
$article = Database::fetchOne(
    "SELECT id, title, content, excerpt FROM articles WHERE id = :id LIMIT 1",
    ['id' => $targetId]
);

if (!$article) {
    die("❌ Article #{$targetId} not found.\n");
}

$title = $article['title'];
echo "📄 Title: {$title}\n";
echo "📊 Original word count: " . str_word_count(strip_tags($article['content'])) . " words\n\n";

// ─── Real Indian journalism few-shot examples (used for every section) ───────
$fewShotExamples = <<<EXAMPLES
STUDY THESE EXAMPLES and match their style exactly:

EXAMPLE 1 (Jagran Josh style):
"UP Teacher Recruitment 2026 is finally here. UPESSC has notified 12,405 posts. If you have a B.Ed or D.El.Ed and a valid UPTET or CTET score, you're eligible. Registration opens soon on upessc.up.gov.in."

EXAMPLE 2 (Hindustan Times Education style):
"12,405 teacher jobs. That's what UPESSC is offering. Primary and upper primary school posts are open across all UP districts. Your UPTET or CTET certificate is your key."

EXAMPLE 3 (conversational, student-friendly):
"Getting a government teacher job in UP isn't as hard as it sounds. You need graduation with 50% marks, a teacher training course (D.El.Ed or B.Ed), and a TET score. That's it."

EXAMPLE 4 (punchy news-ticker style):
"Don't miss this. UPESSC just released the official notification for UP Super TET 2026. 12,405 seats. Apply online. Deadline in October."
EXAMPLES;

// ─── Helper: Extract tables from HTML (preserve as-is) ──────────────────────
function extractTableBlocks(string $html): array {
    $tables = [];
    preg_match_all('/<div class="table-responsive"[^>]*>.*?<\/div>\s*(?:<\/div>)?|<table\b[^>]*>.*?<\/table>/is', $html, $m);
    foreach ($m[0] as $i => $t) {
        $placeholder = "%%TABLE_{$i}%%";
        $tables[$placeholder] = $t;
    }
    return $tables;
}

// ─── Helper: Replace tables with placeholders ────────────────────────────────
function replaceTables(string $html, array $tables): string {
    foreach ($tables as $placeholder => $tableHtml) {
        $html = str_replace($tableHtml, $placeholder, $html);
    }
    return $html;
}

// ─── Helper: Restore table placeholders ─────────────────────────────────────
function restoreTables(string $html, array $tables): string {
    foreach ($tables as $placeholder => $tableHtml) {
        $html = str_replace($placeholder, $tableHtml, $html);
    }
    return $html;
}

// ─── Split content into H2 sections ─────────────────────────────────────────
function splitSections(string $html): array {
    $sections = [];
    $parts = preg_split('/(<h2\b[^>]*>.*?<\/h2>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $currentHeading = '';
    $currentBody = '';

    foreach ($parts as $part) {
        if (preg_match('/^<h2\b[^>]*>(.*?)<\/h2>$/is', $part)) {
            if ($currentBody !== '') {
                $sections[] = ['heading' => $currentHeading, 'body' => $currentBody];
            }
            $currentHeading = $part;
            $currentBody = '';
        } else {
            $currentBody .= $part;
        }
    }
    if ($currentBody !== '') {
        $sections[] = ['heading' => $currentHeading, 'body' => $currentBody];
    }
    return $sections;
}

// ─── Helper: Extract only prose text (no tables, no lists) from a section ───
function extractProseOnly(string $html): string {
    // Remove table blocks
    $prose = preg_replace('/<div class="table-responsive"[^>]*>.*?<\/div>/is', '', $html);
    $prose = preg_replace('/<table\b[^>]*>.*?<\/table>/is', '', $prose);
    // Remove ordered/unordered lists (keep as-is in assembly)
    // Keep <p> tags, strip the HTML
    $text = strip_tags($prose);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// ─── Main Gemini Humanizer call ──────────────────────────────────────────────
// Section type detector
function getSectionType(string $heading): string {
    $lower = mb_strtolower($heading);
    if (str_contains($lower, 'faq') || str_contains($lower, 'frequently')) return 'faq';
    if (str_contains($lower, 'how to apply') || str_contains($lower, 'step')) return 'steps';
    if (str_contains($lower, 'selection') || str_contains($lower, 'exam pattern')) return 'selection';
    return 'general';
}

function humanizeSection(
    Gemini $gemini,
    string $sectionTitle,
    string $proseText,
    string $fewShotExamples,
    string $examTitle,
    int $articleId
): string {
    if (mb_strlen($proseText) < 30) return '';

    $proseText   = mb_substr($proseText, 0, 1200);
    $sectionType = getSectionType($sectionTitle);

    if ($sectionType === 'faq') {
        $sectionRule = "THIS IS A FAQ SECTION. Make answers feel like a knowledgeable friend explaining — not a textbook.\n- Start some answers with 'Yes,' or 'No,' or 'Actually,' or 'Good question —'\n- Use 'you' directly: 'You need...', 'You'll have to...'\n- Keep each answer under 3 sentences. Very conversational.\n- Add one small real-world tip per answer.";
    } elseif ($sectionType === 'steps') {
        $sectionRule = "THIS IS A HOW-TO SECTION. Make it feel like a helpful senior student explaining.\n- Write as flowing prose with inline step numbers — not a rigid list.\n- Use casual language: 'First thing — go to...', 'Then just click...', 'Last step:'\n- Add one practical tip: e.g. 'keep your Aadhaar handy before you start'\n- Mix sentence lengths drastically.";
    } elseif ($sectionType === 'selection') {
        $sectionRule = "THIS IS A SELECTION/EXAM SECTION.\n- Use em-dashes for impact: 'The written exam — 150 MCQs — is the main filter.'\n- Start some sentences with 'And' or 'But' naturally.\n- Use 'your': 'Your academic marks will count for 40%.'\n- Break it into punchy insights, NOT formal process flow.";
    } else {
        $sectionRule = "GENERAL SECTION:\n- Add one unexpected or practical detail.\n- Start at least one sentence with 'And' or 'But'.\n- Use an em-dash somewhere for impact.\n- One sentence should be 4 words or less.";
    }

    $prompt = <<<PROMPT
You are rewriting content for Sarkari.online — India's top government exam portal.

READERS: Indian aspirants, many from Hindi-medium schools. Think Jagran Josh or Hindustan Times education section.

{$fewShotExamples}

---

SECTION: "{$sectionTitle}" | EXAM: "{$examTitle}"

{$sectionRule}

UNIVERSAL RULES:
1. Max 2-3 sentences per <p> block. Never one big paragraph.
2. Sentence rhythm MUST vary: mix 4-word punchy lines with 14-word factual ones.
3. Contractions: don't, it's, you'll, can't, here's, won't, aren't, didn't.
4. Class 10 vocabulary only. BANNED: paramount, pivotal, commence, subsequent, intricate, comprehensive, streamline, multifaceted.
5. Keep ALL facts: dates, fees, percentages, website names, vacancy numbers.
6. NEVER start with "The [Authority] has released..." — completely banned.
7. Return ONLY clean HTML <p>...</p>. No <h2>, <h3>, <ul>, <ol>.

TEXT TO REWRITE:
{$proseText}
PROMPT;

    try {
        $result = $gemini->generate($prompt, [
            'stage'              => 'full_humanizer_v2_' . $sectionType,
            'article_id'        => $articleId,
            'temperature'       => 1.7,
            'system_instruction' => "You are RAJEEV SHARMA, a 36-year-old senior education reporter at a popular UP-based Hindi-English news portal. You write in simple, punchy, direct everyday Indian English that even a Class 10 student can read. Short sentences. Natural imperfections. You sometimes start sentences with 'And' or 'But'. You use em-dashes for impact. You make it feel like a knowledgeable friend explaining — not a textbook or AI."
        ]);
        return trim($result['text'] ?? '');
    } catch (\Throwable $e) {
        echo "  ⚠️  Gemini error: " . $e->getMessage() . "\n";
        return '';
    }
}

// ─── Special FAQ humanizer — keeps Q+A structure intact ─────────────────────
function humanizeFAQSection(
    Gemini $gemini,
    string $faqBodyHtml,
    string $examTitle,
    int $articleId
): string {
    // Strip tables (preserve them separately for FAQ — unlikely but safe)
    $cleanHtml = preg_replace('/<div class="table-responsive"[^>]*>.*?<\/div>/is', '', $faqBodyHtml);
    $cleanHtml = preg_replace('/<table\b[^>]*>.*?<\/table>/is', '', $cleanHtml);
    $cleanHtml = mb_substr(trim($cleanHtml), 0, 2000);

    if (mb_strlen(strip_tags($cleanHtml)) < 20) return '';

    $prompt = <<<PROMPT
You are rewriting FAQ content for Sarkari.online — India's top government exam portal.
Exam: "{$examTitle}"

READERS: Indian students preparing for government exams. Simple, friendly, direct English.

THE FAQ HTML BELOW has <h3> question headings and <p> answer paragraphs.

YOUR JOB: Rewrite ONLY the <p> answer text in a conversational student-friendly tone.
Keep EVERY <h3> question heading exactly as-is — word for word. Do NOT change questions.
Keep the same number of Q+A pairs.

RULES FOR EACH ANSWER:
- Max 2-3 sentences. Short and direct.
- Start with "Yes," or "No," or "Actually," or directly answer — never with "The [Authority]..."
- Use simple Class 10 English. Contractions: don't, it's, you'll, can't.
- Add ONE practical tip per answer where relevant (e.g. "Keep your original marksheet ready")
- No fancy words. No jargon. Preserve all facts: dates, marks, fees, names.

OUTPUT FORMAT — return ONLY this exact structure for each Q+A pair:
<h3>[exact original question]</h3>
<p>[conversational answer]</p>

Do NOT add any extra headings, intros, or wrappers. Just the Q+A pairs.

FAQ HTML TO REWRITE:
{$cleanHtml}
PROMPT;

    try {
        $result = $gemini->generate($prompt, [
            'stage'              => 'full_humanizer_v2_faq',
            'article_id'        => $articleId,
            'temperature'       => 1.5,
            'system_instruction' => "You are RAJEEV SHARMA, a senior Indian education journalist. Rewrite FAQ answers in simple, friendly, conversational Indian English. Keep question headings exactly unchanged. Short answers. Real tips. Never sound like an AI or textbook."
        ]);
        return trim($result['text'] ?? '');
    } catch (\Throwable $e) {
        echo "  ⚠️  FAQ Gemini error: " . $e->getMessage() . "\n";
        return '';
    }
}


// ─── MAIN PROCESSING ─────────────────────────────────────────────────────────
$gemini  = new Gemini();
$content = $article['content'];

// Step 1: Extract all tables with placeholders
$tables  = extractTableBlocks($content);
$contentWithPlaceholders = replaceTables($content, $tables);

echo "📦 Tables found & preserved: " . count($tables) . "\n\n";

// Step 2: Split into H2 sections
$sections = splitSections($contentWithPlaceholders);
echo "📂 Sections found: " . count($sections) . "\n\n";

// Step 3: Humanize each section's prose
$assembledContent = '';
$totalSections    = count($sections);

foreach ($sections as $i => $section) {
    $headingHtml = $section['heading'];
    $bodyHtml    = $section['body'];

    $headingText = strip_tags($headingHtml);
    $sectionType = getSectionType($headingText);
    $proseText   = extractProseOnly($bodyHtml);

    $sectionNum = $i + 1;
    echo "[{$sectionNum}/{$totalSections}] Section: {$headingText}\n";

    // ── FAQ: special handler that keeps Q+A structure intact ──────────────
    if ($sectionType === 'faq') {
        echo "  🗂️  FAQ section — using structure-preserving handler...\n";
        $humanizedFAQ = humanizeFAQSection($gemini, $bodyHtml, $title, $targetId);
        if (!empty($humanizedFAQ)) {
            echo "  ✅ FAQ Humanized! Words: " . str_word_count(strip_tags($humanizedFAQ)) . "\n\n";
            $assembledContent .= $headingHtml . "\n" . $humanizedFAQ . "\n\n";
        } else {
            echo "  ⚠️  FAQ empty result — keeping original.\n\n";
            $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
        }
        if ($sectionNum < $totalSections) sleep(2);
        continue;
    }

    // ── Non-FAQ: no prose to humanize (tables/lists only) ─────────────────
    if (empty($proseText)) {
        echo "  ⏩ No prose to humanize. Keeping as-is.\n\n";
        $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
        continue;
    }

    // ── Non-FAQ: humanize prose, keep tables + lists unchanged ────────────
    echo "  ✍️  Prose chars: " . mb_strlen($proseText) . " → Sending to Gemini...\n";

    $humanizedProse = humanizeSection(
        $gemini, $headingText, $proseText, $fewShotExamples, $title, $targetId
    );

    if (!empty($humanizedProse)) {
        // Remove original <p> blocks only — tables, <ul>, <ol>, <h3> etc stay intact
        $bodyWithoutProse = preg_replace('/<p\b[^>]*>.*?<\/p>/is', '', $bodyHtml);
        $newBody = $humanizedProse . "\n" . $bodyWithoutProse;

        echo "  ✅ Humanized! Words: " . str_word_count(strip_tags($humanizedProse)) . "\n\n";
        $assembledContent .= $headingHtml . "\n" . $newBody . "\n\n";
    } else {
        echo "  ⚠️  Empty result — keeping original.\n\n";
        $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
    }

    if ($sectionNum < $totalSections) sleep(2);
}


// Step 4: Restore table placeholders back
$finalContent = restoreTables($assembledContent, $tables);

// ─── Stats ────────────────────────────────────────────────────────────────────
$finalWordCount = str_word_count(strip_tags($finalContent));
echo "==========================================================\n";
echo "📊 FINAL STATS\n";
echo "==========================================================\n";
echo "Original word count: " . str_word_count(strip_tags($content)) . "\n";
echo "Final word count:    {$finalWordCount}\n";
echo "Tables preserved:    " . count($tables) . "\n\n";

// ─── Preview first 600 chars of plain text ────────────────────────────────────
echo "📋 PREVIEW (first 600 chars of plain text):\n";
echo "---\n";
echo mb_substr(strip_tags($finalContent), 0, 600) . "\n";
echo "---\n\n";

// ─── Save to DB ───────────────────────────────────────────────────────────────
if ($isDryRun) {
    echo "🔵 DRY-RUN: Not saved to database.\n";
    echo "To save, run WITHOUT --dry-run flag.\n\n";
} else {
    Database::execute(
        "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
        ['content' => $finalContent, 'id' => $targetId]
    );
    echo "💾 Saved! Article #{$targetId} updated in database.\n";
    echo "🔗 Check live: https://sarkari.online/article/up-super-tet-2026-application/\n\n";
}

echo "==========================================================\n";
echo "✅ Full Humanizer Complete! Copy the preview text above\n";
echo "   into https://writer.com/ai-content-detector/ to check score.\n";
echo "==========================================================\n";
