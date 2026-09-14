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

// If neither flag is passed, default to checking both if slug is provided
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
if ($isGlossary || (!$isArticles && $targetSlug)) {
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
if ($isArticles || (!$isGlossary && $targetSlug)) {
    echo "\n📰 Scanning Articles for Formulaic AI Phrasing...\n";
    $sql = "SELECT id, title, slug, excerpt, content FROM articles WHERE status = 'published'";
    $params = [];
    if ($targetSlug) {
        $sql .= " AND slug = :slug";
        $params['slug'] = $targetSlug;
    } else {
        $sql .= " AND (content LIKE '%Following the %' 
                    OR excerpt LIKE '%Following the %'
                    OR content LIKE '%candidates are awaiting the release%'
                    OR excerpt LIKE '%candidates are awaiting the release%'
                    OR content LIKE '%These documents allow aspirants to verify%'
                    OR content LIKE '%streamline the recruitment%'
                    OR content LIKE '%digital governance%') LIMIT " . $limit;
    }

    $articles = Database::fetchAll($sql, $params);
    echo "Found " . count($articles) . " article(s) matching AI formulaic patterns.\n";

    $aiTriggers = [
        'Following the ',
        'candidates are awaiting the release',
        'These documents allow aspirants to verify',
        'streamline the recruitment process',
        'digital governance initiative',
        'serves as a testament',
        'centralized repository',
        'crucial step for candidates',
        'in today\'s digital era',
        'fosters transparency'
    ];

    foreach ($articles as $art) {
        echo "▶ Humanizing Article: {$art['title']} ({$art['slug']})...\n";
        
        $content = $art['content'];
        $excerpt = $art['excerpt'] ?? '';
        $updatedContent = $content;
        $updatedExcerpt = $excerpt;
        $madeChanges = false;

        // Extract paragraphs
        if (preg_match_all('/<p(?:\s+[^>]*)?>(.*?)<\/p>/is', $content, $pMatches, PREG_SET_ORDER)) {
            $pReplaced = 0;
            foreach ($pMatches as $idx => $pMatch) {
                $fullTag = $pMatch[0];
                $innerHtml = $pMatch[1];
                $cleanText = trim(strip_tags($innerHtml));

                $shouldRewrite = false;
                foreach ($aiTriggers as $trigger) {
                    if (stripos($cleanText, $trigger) !== false) {
                        $shouldRewrite = true;
                        break;
                    }
                }

                // If target slug is explicitly given and first paragraph, rewrite it even if not explicitly triggered
                if (!$shouldRewrite && $targetSlug && $idx === 0 && mb_strlen($cleanText) > 50) {
                    $shouldRewrite = true;
                }

                if ($shouldRewrite && mb_strlen($cleanText) >= 30) {
                    echo "  Found AI paragraph #{$idx} (" . mb_substr($cleanText, 0, 60) . "...)\n";
                    $prompt = str_replace('{{TEXT}}', $cleanText, $humanizerPrompt);
                    try {
                        $response = $gemini->generate($prompt, ['stage' => 'humanize_article', 'temperature' => 0.7]);
                        $rewrittenP = trim($response['text'] ?? '');

                        if (!empty($rewrittenP) && mb_strlen($rewrittenP) >= 30) {
                            $updatedContent = str_replace($fullTag, "<p>{$rewrittenP}</p>", $updatedContent);
                            echo "  ✅ Replaced paragraph #{$idx} with humanized version.\n";
                            $madeChanges = true;
                            $pReplaced++;
                        }
                    } catch (\Throwable $e) {
                        echo "  ❌ Paragraph Error: " . $e->getMessage() . "\n";
                    }
                    sleep(1);
                    // Rewrite up to 2 major paragraphs per article to avoid over-altering factual tables/data
                    if ($pReplaced >= 2) {
                        break;
                    }
                }
            }
        }

        // Also humanize excerpt if it contains AI phrases
        if (!empty($excerpt)) {
            $excerptTriggered = false;
            foreach ($aiTriggers as $trigger) {
                if (stripos($excerpt, $trigger) !== false) {
                    $excerptTriggered = true;
                    break;
                }
            }
            if ($excerptTriggered) {
                $prompt = str_replace('{{TEXT}}', $excerpt, $humanizerPrompt);
                try {
                    $response = $gemini->generate($prompt, ['stage' => 'humanize_excerpt', 'temperature' => 0.7]);
                    $rewrittenEx = trim($response['text'] ?? '');
                    if (!empty($rewrittenEx) && mb_strlen($rewrittenEx) >= 20) {
                        $updatedExcerpt = $rewrittenEx;
                        echo "  ✅ Humanized excerpt field.\n";
                        $madeChanges = true;
                    }
                } catch (\Throwable $e) {
                    echo "  ❌ Excerpt Error: " . $e->getMessage() . "\n";
                }
                sleep(1);
            }
        }

        // Update database if changed
        if ($madeChanges && ($updatedContent !== $content || $updatedExcerpt !== $excerpt)) {
            Database::execute(
                "UPDATE articles SET content = :content, excerpt = :excerpt, updated_at = NOW() WHERE id = :id",
                [
                    'content' => $updatedContent,
                    'excerpt' => $updatedExcerpt,
                    'id'      => (int)$art['id']
                ]
            );
            echo "  🎉 Article #{$art['id']} successfully updated in database!\n\n";
        } else {
            echo "  ℹ️ No changes needed or pattern not matched.\n\n";
        }
    }
}

echo "✨ Humanization batch complete!\n";
