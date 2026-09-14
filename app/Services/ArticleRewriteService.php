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
 * for long-term Google AdSense compliance and 100% human-grade editorial prose (0% AI on QuillBot).
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
        'substantiate why a specific question',
        'The All India Management Association has released',
        'The National Board of Examinations in Medical Sciences has released'
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
                // Clean any stray markdown headings
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

        // Generate clean, humanized excerpt using conditional hook
        $excerptPrompt = <<<EXCERPT
You are a senior Indian education mentor. Write a punchy 2-sentence journalistic summary of this update for candidates:
Topic: "{$title}"
Issuing Authority: "{$sourceName}"

STRICT RULES:
- Start with a direct conditional hook (e.g. "If you registered for...", "Looking for your scorecard...", "Planning to apply...").
- NEVER start with "The [Authority] has released..." or "Following the...".
- Use natural contractions: you'll, don't, can't, it's, here's.
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

        $isOpening = in_array($sectionKey, ['urgency_brief', 'role_overview', 'result_overview', 'objection_window_brief', 'whats_changed'], true);
        $isSteps = in_array($sectionKey, ['download_steps', 'how_to_check', 'how_to_challenge'], true);
        $isChecklist = in_array($sectionKey, ['exam_day_logistics', 'document_checklist', 'document_verification_checklist'], true);
        $isTroubleshoot = in_array($sectionKey, ['common_errors_and_correction', 'consequences_of_missing_window', 'whats_next'], true);

        $specificGuidance = '';
        if ($isOpening) {
            $specificGuidance = <<<GUIDE
SPECIFIC SECTION GOAL (CONVERSATIONAL OPENING HOOK - PROVEN 0% AI FORMULA):
- Start directly with a conversational hook addressing the aspirant.
  Examples of required opening style:
  * "If you registered for {$examTitle}, head over to the official portal right now—your update is officially live."
  * "Got doubts about a question in your {$examTitle} paper? The official challenge link is set to go live."
  * "The wait for {$examTitle} is finally over. Check your scorecard and rank right away."
- NEVER start with: "The {$authority} has released...", "The {$authority} is set to...", "Following the...", "As per...".
- Warn about ground realities: server traffic on the final day, session timeouts, keeping application number & DOB ready.
- Use natural contractions: don't, you'll, it's, won't.
GUIDE;
        } elseif ($isSteps) {
            $specificGuidance = <<<GUIDE
SPECIFIC SECTION GOAL (ACTIONABLE STEP-BY-STEP LIST):
- Provide a clear, actionable numbered list (<ol><li>...</li></ol>) of 4 to 5 concise steps.
- Write each step as an active command (e.g. "Head over to the official portal at...", "Click on the candidate login / download link", "Enter your registration number and date of birth", "Save the PDF and print at least two copies").
- Keep steps brief and practical. Avoid long explanatory essays.
GUIDE;
        } elseif ($isChecklist) {
            $specificGuidance = <<<GUIDE
SPECIFIC SECTION GOAL (EXAM DAY LOGISTICS & VERIFICATION RULES):
- Provide an essential bullet list (<ul><li>...</li></ul>) of ground realities:
  * Hard copy requirement: Digital copies or screenshots on smartphones are strictly rejected at the exam center gate.
  * Original government photo ID: Aadhaar card, Voter ID, PAN card, or Passport.
  * Passport-size photographs matching the online registration form.
  * Prohibited items: Calculators, smartwatches, Bluetooth devices, and bags (most centers offer no cloakrooms).
GUIDE;
        } elseif ($isTroubleshoot) {
            $specificGuidance = <<<GUIDE
SPECIFIC SECTION GOAL (COMMON ERRORS & HELPDESK ADVICE):
- Address real aspirant friction: gateway timeout errors, session expires (advise switching to incognito or private browsing).
- Discrepancy warning: if photo is blurred or name is misspelled, contact the official helpdesk immediately before exam morning.
GUIDE;
        }

        return <<<PROMPT
You are a veteran Indian education journalist and competitive exam mentor with 20+ years of ground-level experience guiding aspirants for UPSC, SSC, NEET, and state exams.

YOUR MISSION:
Write the content for the section: "{$sectionKey}"
Examination: "{$examTitle}"
Issuing Authority: {$authority}
Article Intent: {$intent}

{$specificGuidance}

BACKGROUND CONTEXT:
{$contextSnippet}

STRICT LINGUISTIC RULES (0% AI / 100% HUMAN ON QUILLBOT & GPTZERO):
1. HIGH BURSTINESS (EXTREME ASYMMETRIC SENTENCE LENGTHS):
   - Mix ultra-short punchy sentences (3 to 6 words like "Don't wait.", "Do it right away.", "The cutoff margin is steep.", "Keep these handy.", "Server down? Try incognito.") with natural medium (10-15 words) and explanatory sentences (20-25 words).
   - Never write consecutive sentences of uniform length.

2. MANDATORY NATURAL HUMAN CONTRACTIONS:
   - You MUST use natural contractions throughout: you'll, don't, can't, it's, here's, won't, there's, you've, didn't, aren't.
   - NEVER write "you will", "do not", "cannot", "it is", "there is", "are not" when a contraction is natural.

3. DIRECT HUMAN MENTOR VOICE (BAN HYPER-FORMAL ACADEMIC PHRASING):
   - Speak directly to the aspirant as a coach sitting right in front of them ("you", "your admit card", "your scorecard").
   - Ban formal robotic phrases like "financial commitment required", "intermittent connectivity errors", "substantiate why".

4. COMPLETE BLACKLIST (ZERO TOLERANCE):
   - NEVER use these phrases: ["{$forbiddenStr}"].
   - Never start with: "Following the...", "In the wake of...", "As per the latest announcement...", "With the examination scheduled for...".

5. STRICT FACTUAL GROUNDING:
   - Use only the real facts, dates, and official URLs from the background context. Do NOT invent dates or numbers.

6. OUTPUT FORMAT:
   - Return clean HTML paragraphs (<p>...</p>) and lists (<ol><li>, <ul><li>) where requested.
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
        if (str_contains($text, '<p>') || str_contains($text, '<ul>') || str_contains($text, '<ol>')) {
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
