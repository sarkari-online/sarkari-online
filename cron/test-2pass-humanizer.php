<?php
/**
 * Sarkari.online - 2-Pass Humanizer Test
 * 
 * HOW 2-PASS WORKS:
 * Pass 1: Article #728 ka current content le lo (already in DB)
 * Pass 2: Us content ko Gemini ko do + real Hindustan Times style examples
 *         + temperature=1.3 (high randomness) → Gemini apni hi output rephrase karta hai
 *         → Statistical fingerprint completely change ho jaata hai → AI detector fail ho jaata hai
 * 
 * Usage: php cron/test-2pass-humanizer.php --id=728
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;

$options = getopt('', ['id::', 'dry-run::']);
$targetId = (int)($options['id'] ?? 728);
$isDryRun = isset($options['dry-run']);

echo "==========================================================\n";
echo "🔬 2-Pass Humanizer Test — Article #{$targetId}\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (no DB save)" : "LIVE") . "\n";
echo "==========================================================\n\n";

// Fetch article
$article = Database::fetchOne(
    "SELECT id, title, content, excerpt FROM articles WHERE id = :id LIMIT 1",
    ['id' => $targetId]
);

if (!$article) {
    die("❌ Article #{$targetId} not found.\n");
}

echo "📄 Title: {$article['title']}\n";
echo "📊 Current word count: " . str_word_count(strip_tags($article['content'])) . " words\n\n";

// ─── PASS 2: Rephrase with high temperature + few-shot real examples ──────────

// Extract section-by-section paragraphs (only prose, not tables)
// We pass only the text content — tables we keep as-is from PHP
$cleanText = strip_tags(preg_replace('/<(table|div class="table-responsive")[^>]*>.*?<\/(?:table|div)>/is', '', $article['content']));
$cleanText = preg_replace('/\s+/', ' ', $cleanText);
$cleanText = trim(mb_substr($cleanText, 0, 3000)); // max 3000 chars of prose

echo "✍️  Sending to Gemini (Pass 2 — High Temperature Rephrase)...\n\n";

// Real Hindustan Times / Jagran Josh style samples (few-shot examples)
$fewShotExamples = <<<EXAMPLES
EXAMPLE 1 (Jagran Josh style - easy, direct, student-friendly):
"UP Teacher Recruitment 2026 is finally here. UPESSC has notified 12,405 posts for Assistant Teachers. If you're done with your B.Ed or D.El.Ed and have a valid UPTET or CTET score, you can apply. Registration starts soon on upessc.up.gov.in."

EXAMPLE 2 (Hindustan Times Education style - punchy, simple):
"12,405 teacher jobs. That's what UPESSC is offering through UP Super TET 2026. Primary and upper primary school posts are open across all UP districts. Your UPTET or CTET certificate is your ticket in."

EXAMPLE 3 (Amar Ujala Education style - conversational Indian tone):
"Sarkari teachers ki baat karein toh UP Super TET 2026 ek bada mauka hai. UPESSC ne notification jari kar di hai. Eligibility simple hai — graduation with 50% marks, D.El.Ed ya B.Ed, aur UPTET ya CTET pass hona chahiye."

EXAMPLE 4 (Short, scannable, news-ticker style):
"UPESSC notified 12,405 Assistant Teacher vacancies. Notification: September 2026. Apply by: October 2026. Exam: December 2026. Age limit: 21-40 years. Salary: Pay Level 6 (₹35,400/month). Official site: upessc.up.gov.in."
EXAMPLES;

$gemini = new Gemini();

$pass2Prompt = <<<PROMPT
You are rewriting content for Sarkari.online — India's #1 government job portal for students.

YOUR AUDIENCE:
Indian government exam candidates, many from Hindi-medium or regional-medium backgrounds.
They want FAST, SIMPLE, SCANNABLE information — not essays.

STUDY THESE REAL EXAMPLES CAREFULLY and match their style EXACTLY:

{$fewShotExamples}

---

NOW REWRITE THE FOLLOWING CONTENT in that same style:

RULES YOU MUST FOLLOW:
1. Maximum 2-3 sentences per paragraph. Short sentences only (10-15 words max).
2. Use natural Indian English — words a Class 10 student can understand easily.
3. Mix very short sentences (3-5 words) with slightly longer ones (12-15 words). Never uniform length.
4. Use contractions naturally: don't, it's, you'll, can't, here's, won't.
5. Never start with "The [Authority] has released..." — this is banned.
6. Never use: paramount, pivotal, multifaceted, furthermore, moreover, comprehensive, streamline, delve, testament, commence, subsequent, intricate, realm, landscape.
7. Preserve all key facts: dates, fees, eligibility, vacancies, website names.
8. DO NOT rewrite tables — skip any table content you see and leave it out.
9. Return clean HTML <p> paragraphs only. No headings. No <h2> or <h1> tags.

CONTENT TO REWRITE:
{$cleanText}
PROMPT;

try {
    $result = $gemini->generate($pass2Prompt, [
        'stage'       => '2pass_humanizer_test',
        'article_id'  => $targetId,
        'temperature' => 1.3,  // HIGH — forces unpredictable word choices
        'system_instruction' => "You are a senior Indian education journalist. Write in simple, direct, student-friendly everyday Indian English. Short sentences. Short paragraphs. Real facts only. Like Jagran Josh or Hindustan Times education section."
    ]);

    $humanizedText = $result['text'] ?? '';
    $wordCount = str_word_count(strip_tags($humanizedText));

    echo "✅ Pass 2 Complete!\n";
    echo "📊 Tokens used: {$result['tokens_used']}\n";
    echo "📊 Word count: {$wordCount} words\n\n";
    echo "==========================================================\n";
    echo "📋 HUMANIZED OUTPUT PREVIEW:\n";
    echo "==========================================================\n\n";
    echo strip_tags($humanizedText) . "\n\n";

    if (!$isDryRun && !empty($humanizedText)) {
        // Save only the humanized prose back, keeping tables intact
        // We inject humanized prose into sections and leave table HTML as-is
        $currentContent = $article['content'];

        // Replace first <p> block (intro paragraphs) with humanized output
        // Strategy: Remove existing prose <p> tags between each <h2>, replace with humanized
        $humanizedContent = $humanizedText . "\n\n" . $currentContent;

        Database::execute(
            "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
            ['content' => $humanizedContent, 'id' => $targetId]
        );
        echo "💾 Saved to database! Article #{$targetId} updated.\n";
        echo "🔗 Check: https://sarkari.online/article/up-super-tet-2026-application/\n";
    }

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================================\n";
echo "✅ 2-Pass Humanizer Test Complete!\n";
echo "Now paste the output text into any AI detector to verify score.\n";
echo "==========================================================\n";
