<?php
/**
 * Sarkari.online - Batch Humanizer for ALL Published Articles
 *
 * Runs humanize-full-article logic on every published article.
 * Processes safely in batches with rate-limit delays.
 *
 * Usage:
 *   php cron/batch-humanize-all.php --dry-run          # Preview only, no save
 *   php cron/batch-humanize-all.php                    # Live run all articles
 *   php cron/batch-humanize-all.php --limit=10         # First 10 articles only
 *   php cron/batch-humanize-all.php --offset=20        # Skip first 20, do rest
 *   php cron/batch-humanize-all.php --limit=10 --offset=20  # Articles 21-30
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;

$options  = getopt('', ['dry-run::', 'limit::', 'offset::']);
$isDryRun = isset($options['dry-run']);
$limit    = isset($options['limit']) ? max(1, (int)$options['limit']) : 999;
$offset   = isset($options['offset']) ? max(0, (int)$options['offset']) : 0;

echo "==========================================================\n";
echo "🚀 Batch Humanizer — All Published Articles\n";
echo "Mode:   " . ($isDryRun ? "DRY-RUN (no DB save)" : "LIVE SAVE") . "\n";
echo "Limit:  {$limit} articles\n";
echo "Offset: {$offset}\n";
echo "==========================================================\n\n";

// ─── Fetch published articles ─────────────────────────────────────────────────
$articles = Database::fetchAll(
    "SELECT id, title FROM articles
     WHERE status = 'published'
     ORDER BY id ASC
     LIMIT {$limit} OFFSET {$offset}"
);

$total = count($articles);
if ($total === 0) {
    die("⚠️  No published articles found for this range.\n");
}

echo "📋 Found {$total} articles to process.\n\n";

// ─── Include shared humanizer functions ──────────────────────────────────────
// We reuse all the same functions from humanize-full-article.php

function extractTableBlocks(string $html): array {
    $tables = [];
    preg_match_all('/<div class="table-responsive"[^>]*>.*?<\/div>\s*(?:<\/div>)?|<table\b[^>]*>.*?<\/table>/is', $html, $m);
    foreach ($m[0] as $i => $t) {
        $placeholder = "%%TABLE_{$i}%%";
        $tables[$placeholder] = $t;
    }
    return $tables;
}

function replaceTables(string $html, array $tables): string {
    foreach ($tables as $placeholder => $tableHtml) {
        $html = str_replace($tableHtml, $placeholder, $html);
    }
    return $html;
}

function restoreTables(string $html, array $tables): string {
    foreach ($tables as $placeholder => $tableHtml) {
        $html = str_replace($placeholder, $tableHtml, $html);
    }
    return $html;
}

function splitSections(string $html): array {
    $sections = [];
    $parts = preg_split('/(<h2\b[^>]*>.*?<\/h2>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $currentHeading = '';
    $currentBody    = '';
    foreach ($parts as $part) {
        if (preg_match('/^<h2\b[^>]*>(.*?)<\/h2>$/is', $part)) {
            if ($currentBody !== '') {
                $sections[] = ['heading' => $currentHeading, 'body' => $currentBody];
            }
            $currentHeading = $part;
            $currentBody    = '';
        } else {
            $currentBody .= $part;
        }
    }
    if ($currentBody !== '') {
        $sections[] = ['heading' => $currentHeading, 'body' => $currentBody];
    }
    return $sections;
}

function extractProseOnly(string $html): string {
    $prose = preg_replace('/<div class="table-responsive"[^>]*>.*?<\/div>/is', '', $html);
    $prose = preg_replace('/<table\b[^>]*>.*?<\/table>/is', '', $prose);
    $text  = strip_tags($prose);
    $text  = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function getSectionType(string $heading): string {
    $lower = mb_strtolower($heading);
    if (str_contains($lower, 'faq') || str_contains($lower, 'frequently')) return 'faq';
    if (str_contains($lower, 'how to apply') || str_contains($lower, 'step')) return 'steps';
    if (str_contains($lower, 'selection') || str_contains($lower, 'exam pattern')) return 'selection';
    return 'general';
}

$fewShotExamples = <<<EXAMPLES
STUDY THESE EXAMPLES and match their style exactly:

EXAMPLE 1 (Jagran Josh style):
"UP Teacher Recruitment 2026 is finally here. UPESSC has notified 12,405 posts. If you have a B.Ed or D.El.Ed and a valid UPTET or CTET score, you're eligible. Registration opens soon on upessc.up.gov.in."

EXAMPLE 2 (Hindustan Times Education style):
"12,405 teacher jobs. That's what UPESSC is offering. Primary and upper primary school posts are open across all UP districts. Your UPTET or CTET certificate is your key."

EXAMPLE 3 (conversational, student-friendly):
"Getting a government teacher job in UP isn't as hard as it sounds. You need graduation with 50% marks, a teacher training course (D.El.Ed or B.Ed), and a TET score. That's it."

EXAMPLE 4 (punchy, direct):
"Don't miss this. UPESSC just released the official notification. 12,405 seats. Apply online. Deadline in October."
EXAMPLES;

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

    if ($sectionType === 'steps') {
        $sectionRule = "THIS IS A HOW-TO SECTION. Write as flowing prose with inline step numbers — not a rigid list. Use casual language: 'First thing — go to...', 'Then just click...'. Add one practical tip like 'keep your Aadhaar handy'.";
    } elseif ($sectionType === 'selection') {
        $sectionRule = "THIS IS A SELECTION/EXAM SECTION. Use em-dashes for impact. Start some sentences with 'And' or 'But'. Use 'your': 'Your academic marks will count.' Break into punchy insights.";
    } else {
        $sectionRule = "GENERAL SECTION: Add one practical detail. Start at least one sentence with 'And' or 'But'. Use an em-dash somewhere. One sentence should be 4 words or less.";
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
8. Do NOT add random or made-up information. Only use facts from the original text.

TEXT TO REWRITE:
{$proseText}
PROMPT;

    try {
        $result = $gemini->generate($prompt, [
            'stage'              => 'batch_humanizer_' . $sectionType,
            'article_id'        => $articleId,
            'temperature'       => 1.7,
            'system_instruction' => "You are RAJEEV SHARMA, a 36-year-old senior education reporter at a UP-based Hindi-English news portal. Simple, punchy, direct Indian English. Short sentences. Natural imperfections. Sometimes start with 'And' or 'But'. Use em-dashes. Feel like a knowledgeable friend, not a textbook."
        ]);
        return trim($result['text'] ?? '');
    } catch (\Throwable $e) {
        return '';
    }
}

function humanizeFAQSection(
    Gemini $gemini,
    string $faqBodyHtml,
    string $examTitle,
    int $articleId
): string {
    $cleanHtml = preg_replace('/<div class="table-responsive"[^>]*>.*?<\/div>/is', '', $faqBodyHtml);
    $cleanHtml = preg_replace('/<table\b[^>]*>.*?<\/table>/is', '', $cleanHtml);
    $cleanHtml = mb_substr(trim($cleanHtml), 0, 2000);
    if (mb_strlen(strip_tags($cleanHtml)) < 20) return '';

    $prompt = <<<PROMPT
You are rewriting FAQ content for Sarkari.online — India's top government exam portal.
Exam: "{$examTitle}"

READERS: Indian students. Simple, friendly, direct English.

THE FAQ HTML has <h3> question headings and <p> answer paragraphs.

YOUR JOB: Rewrite ONLY the <p> answer text in a conversational student-friendly tone.
Keep EVERY <h3> question heading EXACTLY as-is — word for word. Do NOT change questions.
Keep the same number of Q+A pairs.

RULES FOR EACH ANSWER:
- Max 2-3 sentences. Short and direct.
- Start with "Yes," or "No," or "Actually," or directly answer — never "The [Authority]..."
- Simple Class 10 English. Contractions: don't, it's, you'll, can't.
- Add ONE practical tip per answer where relevant.
- No fancy words. Preserve all facts: dates, marks, fees, names.
- Do NOT add random or made-up information.

OUTPUT FORMAT — ONLY this structure for each Q+A pair:
<h3>[exact original question]</h3>
<p>[conversational answer]</p>

No extra headings, intros, or wrappers. Just Q+A pairs.

FAQ HTML TO REWRITE:
{$cleanHtml}
PROMPT;

    try {
        $result = $gemini->generate($prompt, [
            'stage'              => 'batch_humanizer_faq',
            'article_id'        => $articleId,
            'temperature'       => 1.5,
            'system_instruction' => "You are RAJEEV SHARMA, a senior Indian education journalist. Rewrite FAQ answers in simple, friendly, conversational Indian English. Keep question headings exactly unchanged. Short answers. Real tips. Never sound like AI."
        ]);
        return trim($result['text'] ?? '');
    } catch (\Throwable $e) {
        return '';
    }
}

// ─── Process each article ─────────────────────────────────────────────────────
$gemini   = new Gemini();
$success  = 0;
$failed   = 0;
$skipped  = 0;

foreach ($articles as $idx => $articleRow) {
    $articleId    = (int)$articleRow['id'];
    $articleTitle = $articleRow['title'];
    $articleNum   = $idx + 1;

    echo "──────────────────────────────────────────────────────────\n";
    echo "[{$articleNum}/{$total}] Article #{$articleId}: {$articleTitle}\n";
    echo "──────────────────────────────────────────────────────────\n";

    // Re-fetch full content
    $full = Database::fetchOne(
        "SELECT content FROM articles WHERE id = :id LIMIT 1",
        ['id' => $articleId]
    );
    if (!$full || empty($full['content'])) {
        echo "  ⚠️  No content. Skipping.\n\n";
        $skipped++;
        continue;
    }

    $content = $full['content'];
    $originalWordCount = str_word_count(strip_tags($content));

    // Extract tables
    $tables   = extractTableBlocks($content);
    $withPlaceholders = replaceTables($content, $tables);

    // Split sections
    $sections = splitSections($withPlaceholders);
    echo "  📂 Sections: " . count($sections) . " | Tables: " . count($tables) . "\n";

    $assembledContent = '';
    $sectionErrors    = 0;

    foreach ($sections as $si => $section) {
        $headingHtml = $section['heading'];
        $bodyHtml    = $section['body'];
        $headingText = strip_tags($headingHtml);
        $sectionType = getSectionType($headingText);
        $proseText   = extractProseOnly($bodyHtml);

        // FAQ: structure-preserving handler
        if ($sectionType === 'faq') {
            $humanized = humanizeFAQSection($gemini, $bodyHtml, $articleTitle, $articleId);
            if (!empty($humanized)) {
                $assembledContent .= $headingHtml . "\n" . $humanized . "\n\n";
            } else {
                $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
                $sectionErrors++;
            }
            sleep(2);
            continue;
        }

        // No prose to humanize
        if (empty($proseText)) {
            $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
            continue;
        }

        // Regular section
        $humanized = humanizeSection($gemini, $headingText, $proseText, $fewShotExamples, $articleTitle, $articleId);
        if (!empty($humanized)) {
            $bodyWithoutProse = preg_replace('/<p\b[^>]*>.*?<\/p>/is', '', $bodyHtml);
            $assembledContent .= $headingHtml . "\n" . $humanized . "\n" . $bodyWithoutProse . "\n\n";
        } else {
            $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
            $sectionErrors++;
        }

        sleep(2); // Rate limit protection
    }

    // Restore tables
    $finalContent = restoreTables($assembledContent, $tables);
    $finalWordCount = str_word_count(strip_tags($finalContent));

    echo "  📊 Words: {$originalWordCount} → {$finalWordCount}";
    if ($sectionErrors > 0) echo " | ⚠️ {$sectionErrors} section(s) kept original";
    echo "\n";

    if (!$isDryRun) {
        try {
            Database::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                ['content' => $finalContent, 'id' => $articleId]
            );
            echo "  💾 Saved!\n\n";
            $success++;
        } catch (\Throwable $e) {
            echo "  ❌ DB save failed: " . $e->getMessage() . "\n\n";
            $failed++;
        }
    } else {
        echo "  🔵 DRY-RUN: Not saved.\n\n";
        $success++;
    }

    // Delay between articles to avoid Gemini rate limit
    if ($articleNum < $total) {
        echo "  ⏳ Waiting 5s before next article...\n\n";
        sleep(5);
    }
}

// ─── Final Summary ───────────────────────────────────────────────────────────
echo "==========================================================\n";
echo "✅ BATCH COMPLETE\n";
echo "==========================================================\n";
echo "Processed: {$total}\n";
echo "Success:   {$success}\n";
echo "Skipped:   {$skipped}\n";
echo "Failed:    {$failed}\n";
echo "Mode:      " . ($isDryRun ? "DRY-RUN" : "LIVE SAVED") . "\n";
echo "==========================================================\n";
