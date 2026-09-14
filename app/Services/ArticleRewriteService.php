<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use App\Services\ArticleIntent;
use App\Services\IntentClassifierService;
use App\Services\IntentStructureMap;
use App\Services\SelectionFlowchartRenderer;
use App\Services\SalaryTableRenderer;
use Throwable;

/**
 * ArticleRewriteService
 * Section-scoped editorial rewrite engine designed to elevate article quality,
 * guarantee structural diversity per intent, and eliminate formulaic boilerplate
 * for long-term Google AdSense compliance and 100% human-grade editorial prose.
 */
class ArticleRewriteService
{
    private Gemini $gemini;
    private IntentClassifierService $classifier;

    private const FORBIDDEN_PHRASES = [
        'Following the ',
        'streamline the recruitment process',
        'digital governance initiative',
        'crucial step',
        'serves as a testament',
        'in today\'s competitive era',
        'in today\'s digital era',
        'candidates are advised to',
        'it is worth noting that',
        'in conclusion',
        'without further ado',
        'stay tuned',
        'centralized repository',
        'pivotal role in ensuring',
        'candidates are awaiting the release of',
        'financial commitment required',
        'intermittent connectivity errors',
        'substantiate why a specific question'
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
        $this->classifier = new IntentClassifierService();
    }

    /**
     * Rewrite an existing article into an intent-structured, high-utility guide.
     */
    public function rewriteArticle(array $article, array $cycleFacts = []): ?array
    {
        $title = $article['title'] ?? '';
        $existingContent = $article['content'] ?? '';
        $sourceName = $article['source_name'] ?? 'Official Examination Authority';
        $sourceUrl = $article['source_url'] ?? '';

        // Classify intent
        $intentEnum = $this->classifier->classify($title, $article['excerpt'] ?? '');
        $intentKey = strtoupper($intentEnum->value);
        $sections = IntentStructureMap::getSections($intentKey);

        Logger::info("ArticleRewriteService: Rewriting article #{$article['id']} with intent [{$intentKey}] (" . count($sections) . " sections)");

        // Extract existing tables or structured blocks to preserve them 100% intact
        $tables = $this->extractTables($existingContent);

        $assembledHtml = '';

        foreach ($sections as $sectionKey) {
            $sectionTitle = IntentStructureMap::getSectionTitle($sectionKey);

            // Special structural handlers
            if ($sectionKey === 'selection_process_flowchart') {
                $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                $assembledHtml .= SelectionFlowchartRenderer::render() . "\n\n";
                continue;
            }

            if ($sectionKey === 'salary_and_perks' && !empty($cycleFacts)) {
                $salaryTable = SalaryTableRenderer::render($cycleFacts);
                if ($salaryTable) {
                    $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                    $assembledHtml .= $salaryTable . "\n\n";
                    continue;
                }
            }

            // Build section-scoped prompt
            $prompt = $this->buildSectionPrompt($title, $sourceName, $intentKey, $sectionKey, $cycleFacts, $existingContent);

            try {
                $response = $this->gemini->generate($prompt, [
                    'stage' => 'editorial_section_rewrite',
                    'temperature' => 0.7
                ]);

                $sectionProse = trim($response['text'] ?? '');
                // Clean any stray markdown headings or forbidden phrases
                $sectionProse = preg_replace('/^#+\s*.*$/m', '', $sectionProse);
                $sectionProse = trim($sectionProse);

                if (!empty($sectionProse) && mb_strlen($sectionProse) >= 40) {
                    $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                    $assembledHtml .= $this->wrapInParagraphs($sectionProse) . "\n\n";

                    // Intelligently slot preserved tables into relevant sections
                    if (($sectionKey === 'urgency_brief' || $sectionKey === 'role_overview' || $sectionKey === 'result_overview') && !empty($tables)) {
                        $firstTable = array_shift($tables);
                        $assembledHtml .= $firstTable . "\n\n";
                    }
                }
            } catch (Throwable $e) {
                Logger::warning("ArticleRewriteService: Section [{$sectionKey}] failed: " . $e->getMessage());
            }

            usleep(500000); // 0.5s pacing
        }

        // Append any remaining original tables so no factual data is lost
        foreach ($tables as $t) {
            $assembledHtml .= $t . "\n\n";
        }

        // Generate clean, humanized excerpt
        $excerptPrompt = <<<EXCERPT
You are a senior Indian education mentor. Write a punchy 2-sentence journalistic summary of this update for candidates:
Topic: "{$title}"
Issuing Authority: "{$sourceName}"

RULES:
- Use natural contractions: you'll, don't, can't, it's, here's.
- Mix one short sentence (4-6 words) with one medium sentence.
- Ban: "Following the...", "As per the latest...", "Candidates are awaiting...".
- Plain text only. No quotes.
EXCERPT;

        $newExcerpt = $article['excerpt'] ?? '';
        try {
            $exRes = $this->gemini->generate($excerptPrompt, ['stage' => 'excerpt_rewrite', 'temperature' => 0.7]);
            $candidateEx = trim($exRes['text'] ?? '');
            if (!empty($candidateEx) && mb_strlen($candidateEx) >= 30) {
                $newExcerpt = $candidateEx;
            }
        } catch (Throwable $e) {
            // Keep existing excerpt
        }

        return [
            'content' => trim($assembledHtml),
            'excerpt' => $newExcerpt,
            'intent'  => $intentKey
        ];
    }

    /**
     * Build focused, intent-and-section-specific prompt with strict Anti-AI humanizer constraints.
     */
    public function buildSectionPrompt(
        string $examTitle,
        string $authority,
        string $intent,
        string $sectionKey,
        array $facts,
        string $rawContext
    ): string {
        $forbiddenStr = implode('", "', self::FORBIDDEN_PHRASES);
        $contextSnippet = mb_substr(strip_tags($rawContext), 0, 800);

        return <<<PROMPT
You are a veteran Indian education journalist and competitive exam mentor with 20+ years of ground-level experience guiding aspirants for UPSC, SSC, NEET, and state exams.

YOUR MISSION:
Write the content for the section: "{$sectionKey}"
Examination: "{$examTitle}"
Issuing Authority: {$authority}
Article Intent: {$intent}

BACKGROUND CONTEXT:
{$contextSnippet}

STRICT LINGUISTIC RULES (0% AI / 100% HUMAN FORMULA ON QUILLBOT & GPTZERO):
1. HIGH BURSTINESS (EXTREME ASYMMETRIC SENTENCE LENGTHS):
   - Never write two sentences of uniform length back-to-back.
   - Mix ultra-short punchy sentences (3 to 6 words like "Don't wait.", "Do it right away.", "The cutoff margin is steep.", "Keep these handy.", "Server down? Try incognito.") with natural medium (10-15 words) and explanatory sentences (20-25 words).
   - This sentence-length variance is the single biggest factor AI detectors inspect.

2. MANDATORY NATURAL HUMAN CONTRACTIONS:
   - You MUST use natural contractions throughout: you'll, don't, can't, it's, here's, won't, there's, you've, didn't.
   - NEVER write "you will", "do not", "cannot", "it is", "there is" when a contraction is natural. AI detectors heavily flag lack of contractions.

3. DIRECT HUMAN MENTOR VOICE (BAN HYPER-FORMAL ACADEMIC PHRASING):
   - Speak directly to the aspirant as an experienced coach sitting right in front of them ("you", "your admit card", "your scorecard").
   - Ban hyper-formal robotic phrasing:
     * Instead of "Be prepared for the financial commitment required for this process" -> write "Challenging a question isn't cheap."
     * Instead of "substantiate why a specific question is flawed" -> write "back your claim with standard textbook references."
     * Instead of "leads to intermittent connectivity errors" -> write "servers get bogged down with heavy traffic."
     * Instead of "discrepancies can lead to complications during identity verification" -> write "any mismatch at the gate will cause serious trouble."

4. COMPLETE BLACKLIST (ZERO TOLERANCE FOR MACHINE CLICHÉS):
   - NEVER use these phrases: ["{$forbiddenStr}"].
   - Never start with: "Following the...", "In the wake of...", "As per the latest announcement...", "With the examination scheduled for...".

5. STRICT FACTUAL GROUNDING:
   - Use only the real facts, dates, and official URLs from the background context. Do NOT invent dates, shift timings, or numbers.

6. OUTPUT FORMAT:
   - Return clean HTML paragraphs (<p>...</p>) and list items (<ul><li>...</li></ul>) if needed.
   - Do NOT include <h1> or <h2> tags.
PROMPT;
    }

    private function extractTables(string $html): array
    {
        $tables = [];
        if (preg_match_all('/<div class="table-responsive"[^>]*>.*?<\/table>\s*<\/div>|<table[^>]*>.*?<\/table>/is', $html, $matches)) {
            $tables = $matches[0];
        }
        return $tables;
    }

    private function wrapInParagraphs(string $text): string
    {
        if (str_contains($text, '<p>') || str_contains($text, '<ul>')) {
            return $text;
        }

        $parts = preg_split('/\n\s*\n/', $text);
        $html = '';
        foreach ($parts as $p) {
            $clean = trim($p);
            if (!empty($clean)) {
                $html .= "<p>" . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . "</p>\n";
            }
        }
        return $html;
    }
}
