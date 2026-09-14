<?php
/**
 * Sarkari.online - Content Humanizer & Anti-AI Detector Healing Script
 * Rewrites AI-detected passages into authentic, conversational human journalism.
 * Uses Claude-Engineered Anti-AI rules (High burstiness, contractions, direct mentor tone).
 * 
 * Usage:
 *   php cron/humanize-existing-content.php --glossary
 *   php cron/humanize-existing-content.php --articles --limit=10
 *   php cron/humanize-existing-content.php --slug=neet-pg-2026-answer-key-response-sheet
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only access permitted.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;

$options = getopt('', ['glossary', 'articles', 'limit::', 'slug::', 'all']);
$isGlossary = isset($options['glossary']);
$isArticles = isset($options['articles']);
$targetSlug = $options['slug'] ?? null;
$limit = (int)($options['limit'] ?? 20);

if (!$isGlossary && !$isArticles && !$targetSlug) {
    echo "Usage:\n";
    echo "  php cron/humanize-existing-content.php --glossary\n";
    echo "  php cron/humanize-existing-content.php --articles --limit=10\n";
    echo "  php cron/humanize-existing-content.php --slug=neet-pg-2026-answer-key-response-sheet\n";
    exit(0);
}

$gemini = new Gemini();

$humanizerPrompt = <<<PROMPT
You are a veteran Indian education journalist and competitive exam mentor with 20+ years of experience guiding aspirants for UPSC, SSC, NEET, and State PSCs.

YOUR MISSION:
Rewrite the provided text so that it reads 100% like authentic human journalism. It must pass all AI detectors (QuillBot, GPTZero, Turnitin, CopyLeaks) with a score of 0% AI / 100% Human.

STRICT LINGUISTIC RULES (CLAUDE-ENGINEERED ANTI-AI PROTOCOL):
1. HIGH BURSTINESS (ASYMMETRIC SENTENCE LENGTHS):
   - Never write three sentences of uniform length in a row.
   - Mix ultra-short punchy sentences (3 to 6 words like "Do not wait until the last day.", "Results will follow soon.", "The cutoff margin is steep.") with natural medium (12-16 words) and longer explanatory sentences (22-28 words).
2. MANDATORY HUMAN CONTRACTIONS:
   - Use natural contractions throughout: you'll, don't, can't, it's, here's, won't, there's. (AI detectors heavily penalize lack of contractions).
3. ACTIVE VOICE & DIRECT MENTOR TONE:
   - Speak directly to the aspirant as a coach sitting across the table. Use second person ("you", "your scorecard", "candidates").
   - Acknowledge real ground-level friction: server lag on the final day, OTP verification delays, ₹1,000 non-refundable objection fees, live webcam photo rejections, normalization shifts.
4. COMPLETE BLACKLIST (ZERO TOLERANCE FOR AI CLICHÉS):
   Never use these machine phrases:
   - "Following the [exam] held on..."
   - "Candidates are awaiting the release of..."
   - "These documents allow aspirants to verify..."
   - "Streamline the recruitment process"
   - "Digital governance initiative"
   - "Serves as a testament to / centralized repository"
   - "Crucial step / pivotal role / delve into"
   - "It is important to note that / in today's digital era"
   - "Without further ado / stay tuned"
5. REAL INDIAN EXAMINATION VERNACULAR:
   Naturally use genuine Indian exam terms: "cutoff margin", "raw score vs normalized marks", "disputed question stem", "provisional answer key", "48-hour challenge window", "counselling round", "hall ticket".

TEXT TO REWRITE:
{{TEXT}}

Return ONLY the rewritten text as plain text. Do not wrap in quotes. Do not add introductory or concluding remarks.
PROMPT;

// ─────────────────────────────────────────────────────────────────────────────
// 1. HEAL GLOSSARY TERMS
// ─────────────────────────────────────────────────────────────────────────────
if ($isGlossary || $targetSlug) {
    echo "\n📚 Scanning Glossary Terms for AI Phrasing...\n";
    $sql = "SELECT id, acronym, slug, overview, full_form_en FROM glossary_terms WHERE 1=1";
    $params = [];
    if ($targetSlug) {
        $sql .= " AND slug = :slug";
        $params['slug'] = $targetSlug;
    } else {
        $sql .= " AND (overview LIKE '%digital governance initiative%' 
                    OR overview LIKE '%streamline the recruitment process%' 
                    OR overview LIKE '%centralized repository%' 
                    OR overview LIKE '%eliminate the redundancy%'
                    OR overview LIKE '%fosters transparency%') LIMIT " . $limit;
    }

    $terms = Database::fetchAll($sql, $params);
    echo "Found " . count($terms) . " glossary term(s) needing humanization.\n";

    foreach ($terms as $t) {
        echo "▶ Humanizing Glossary Term: {$t['acronym']} ({$t['slug']})...\n";
        $currentOverview = $t['overview'];

        $prompt = str_replace('{{TEXT}}', $currentOverview, $humanizerPrompt);
        try {
            $response = $gemini->generate($prompt, ['stage' => 'humanize_glossary', 'temperature' => 0.7]);
            $rewritten = trim($response['text'] ?? '');

            if (!empty($rewritten) && mb_strlen($rewritten) >= 60) {
                Database::execute(
                    "UPDATE glossary_terms SET overview = :ov, updated_at = NOW() WHERE id = :id",
                    ['ov' => $rewritten, 'id' => (int)$t['id']]
                );
                echo "  ✅ Updated successfully! Preview:\n";
                echo "  " . mb_substr($rewritten, 0, 120) . "...\n\n";
            }
        } catch (\Throwable $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
        sleep(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. HEAL ARTICLES
// ─────────────────────────────────────────────────────────────────────────────
if ($isArticles || $targetSlug) {
    echo "\n📰 Scanning Articles for Formulaic AI Phrasing...\n";
    $sql = "SELECT id, title, slug, direct_answer, content FROM articles WHERE status = 'published'";
    $params = [];
    if ($targetSlug) {
        $sql .= " AND slug = :slug";
        $params['slug'] = $targetSlug;
    } else {
        $sql .= " AND (content LIKE '%Following the %' 
                    OR direct_answer LIKE '%Following the %'
                    OR content LIKE '%candidates are awaiting the release%'
                    OR direct_answer LIKE '%candidates are awaiting the release%'
                    OR content LIKE '%These documents allow aspirants to verify%') LIMIT " . $limit;
    }

    $articles = Database::fetchAll($sql, $params);
    echo "Found " . count($articles) . " article(s) matching AI formulaic patterns.\n";

    foreach ($articles as $art) {
        echo "▶ Humanizing Article: {$art['title']} ({$art['slug']})...\n";
        
        $content = $art['content'];
        $directAnswer = $art['direct_answer'] ?? '';

        // Extract first paragraph under first h2 if it has "Following the "
        $updatedContent = $content;
        if (preg_match('/<p>(Following the [^<]+)<\/p>/i', $content, $matches)) {
            $originalP = $matches[1];
            echo "  Found opening paragraph to humanize...\n";

            $prompt = str_replace('{{TEXT}}', $originalP, $humanizerPrompt);
            try {
                $response = $gemini->generate($prompt, ['stage' => 'humanize_article', 'temperature' => 0.7]);
                $rewrittenP = trim($response['text'] ?? '');

                if (!empty($rewrittenP) && mb_strlen($rewrittenP) >= 40) {
                    $updatedContent = str_replace("<p>{$originalP}</p>", "<p>{$rewrittenP}</p>", $updatedContent);
                    echo "  ✅ Replaced opening paragraph with humanized version.\n";
                }
            } catch (\Throwable $e) {
                echo "  ❌ Content Error: " . $e->getMessage() . "\n";
            }
        }

        // Also humanize direct_answer if present
        $updatedDirectAnswer = $directAnswer;
        if (!empty($directAnswer) && (str_contains($directAnswer, 'Following the ') || str_contains($directAnswer, 'candidates are awaiting'))) {
            $prompt = str_replace('{{TEXT}}', $directAnswer, $humanizerPrompt);
            try {
                $response = $gemini->generate($prompt, ['stage' => 'humanize_direct_answer', 'temperature' => 0.7]);
                $rewrittenDA = trim($response['text'] ?? '');
                if (!empty($rewrittenDA) && mb_strlen($rewrittenDA) >= 25) {
                    $updatedDirectAnswer = $rewrittenDA;
                    echo "  ✅ Humanized direct_answer field.\n";
                }
            } catch (\Throwable $e) {
                echo "  ❌ Direct Answer Error: " . $e->getMessage() . "\n";
            }
        }

        // Update database if changed
        if ($updatedContent !== $content || $updatedDirectAnswer !== $directAnswer) {
            Database::execute(
                "UPDATE articles SET content = :content, direct_answer = :da, updated_at = NOW() WHERE id = :id",
                [
                    'content' => $updatedContent,
                    'da' => $updatedDirectAnswer,
                    'id' => (int)$art['id']
                ]
            );
            echo "  🎉 Article #{$art['id']} successfully updated in database!\n\n";
        } else {
            echo "  ℹ️ No changes needed or pattern not matched.\n\n";
        }

        sleep(1);
    }
}

echo "✨ Humanization batch complete!\n";
