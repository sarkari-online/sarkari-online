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
function humanizeSection(
    Gemini $gemini,
    string $sectionTitle,
    string $proseText,
    string $fewShotExamples,
    string $examTitle,
    int $articleId
): string {
    if (mb_strlen($proseText) < 30) {
        return ''; // Too short to humanize — skip
    }

    $proseText = mb_substr($proseText, 0, 1200); // Max per section

    $prompt = <<<PROMPT
You are rewriting content for Sarkari.online — India's #1 government job info portal.

TARGET READERS: Indian students preparing for government exams. Many are from Hindi-medium backgrounds. They need fast, simple, clear information.

{$fewShotExamples}

---

NOW REWRITE THE FOLLOWING SECTION in that exact style:

SECTION: "{$sectionTitle}"
EXAM: "{$examTitle}"

STRICT RULES:
1. Maximum 2-3 sentences per <p> paragraph. Never write long blocks.
2. Mix very short sentences (3-6 words) with medium ones (12-15 words). Never same length twice in a row.
3. Use natural contractions: don't, it's, you'll, can't, here's, won't, aren't.
4. Simple Indian English — Class 10 vocabulary level only. No GRE words.
5. NEVER use: furthermore, moreover, paramount, pivotal, multifaceted, comprehensive, streamline, delve, testament, commence, subsequent, intricate.
6. Preserve ALL key facts: dates, fees, marks, eligibility rules, website names.
7. Start with something direct — NOT "The [Authority] has..." or "In a major development..."
8. Output ONLY clean HTML <p>...</p> paragraphs. No headings. No bullet lists.

TEXT TO REWRITE:
{$proseText}
PROMPT;

    try {
        $result = $gemini->generate($prompt, [
            'stage'              => 'full_humanizer_section',
            'article_id'        => $articleId,
            'temperature'       => 1.3,
            'system_instruction' => "You are a senior Indian education journalist at a major Hindi-English portal. Write in simple, punchy, direct everyday Indian English. Short sentences. Short paragraphs. Never sound like a robot or an academic textbook."
        ]);
        return trim($result['text'] ?? '');
    } catch (\Throwable $e) {
        echo "  ⚠️  Gemini error: " . $e->getMessage() . "\n";
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
    $proseText   = extractProseOnly($bodyHtml);

    $sectionNum = $i + 1;
    echo "[{$sectionNum}/{$totalSections}] Section: {$headingText}\n";

    if (empty($proseText)) {
        echo "  ⏩ No prose to humanize (tables/lists only). Keeping as-is.\n\n";
        $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
        continue;
    }

    echo "  ✍️  Prose chars: " . mb_strlen($proseText) . " → Sending to Gemini...\n";

    $humanizedProse = humanizeSection(
        $gemini,
        $headingText,
        $proseText,
        $fewShotExamples,
        $title,
        $targetId
    );

    if (!empty($humanizedProse)) {
        // Keep tables + lists from original body; replace only prose paragraphs
        // Strategy: Remove original <p> blocks, replace with humanized, keep rest
        $bodyWithoutProse = preg_replace('/<p\b[^>]*>.*?<\/p>/is', '', $bodyHtml);
        $newBody = $humanizedProse . "\n" . $bodyWithoutProse;

        echo "  ✅ Humanized! Words: " . str_word_count(strip_tags($humanizedProse)) . "\n\n";
        $assembledContent .= $headingHtml . "\n" . $newBody . "\n\n";
    } else {
        echo "  ⚠️  Empty result — keeping original.\n\n";
        $assembledContent .= $headingHtml . "\n" . $bodyHtml . "\n\n";
    }

    // Small delay to avoid Gemini rate limit
    if ($sectionNum < $totalSections) {
        sleep(2);
    }
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
