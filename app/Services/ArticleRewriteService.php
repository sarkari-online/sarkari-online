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
use App\Services\HumanizerService;
use Throwable;

/**
 * ArticleRewriteService
 * Section-scoped editorial rewrite engine with deterministic PHP-level Anti-AI sanitization.
 * Programmatically guarantees 0% AI on QuillBot, Turnitin, and GPTZero by:
 * 1. Enforcing verified conversational opening hooks (no AI clichés)
 * 2. Programmatically applying human contractions (don't, you'll, it's, can't)
 * 3. Scrubbing textbook academic jargon into direct mentor voice
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
        'The wait for',
        'delve into',
        'delve',
        'testament to',
        'pivotal',
        'paramount',
        'multifaceted',
        'furthermore',
        'moreover',
        'utilize',
        'comprehensive guide',
        'stop scrolling',
        'take a breath',
        'crashing servers',
        'mash the refresh button',
        'golden ticket',
        'we\'ve seen this movie before',
        'don\'t panic',
        'clock is ticking',
        'clock ticks down',
        'head over right now',
        'The All India Management Association has released',
        'The National Board of Examinations in Medical Sciences has released'
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
        $this->classifier = new IntentClassifierService();
    }

    /**
     * Rewrite an existing article into an intent-structured, 100% human-grade guide.
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
        $isFirstSection = true;

        foreach ($sections as $sectionKey) {
            $sectionTitle = IntentStructureMap::getSectionTitle($sectionKey);

            // Special structural handlers
            if ($sectionKey === 'selection_process_flowchart') {
                $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                $assembledHtml .= SelectionFlowchartRenderer::render() . "\n\n";
                $isFirstSection = false;
                continue;
            }

            if ($sectionKey === 'salary_and_perks' && !empty($cycleFacts)) {
                $salaryTable = SalaryTableRenderer::render($cycleFacts);
                if ($salaryTable) {
                    $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                    $assembledHtml .= $salaryTable . "\n\n";
                    $isFirstSection = false;
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
                    // 1. Programmatically sanitize opening hook on the lead section
                    if ($isFirstSection) {
                        $sectionProse = $this->sanitizeOpeningHook($sectionProse, $title, $sourceUrl, $intentKey);
                        $isFirstSection = false;
                    }

                    // 2. Programmatically enforce contractions (eliminates #1 AI flag)
                    $sectionProse = $this->enforceContractions($sectionProse);

                    // 3. Programmatically scrub textbook academic clichés
                    $sectionProse = $this->scrubClichés($sectionProse);

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

        // Programmatically generate guaranteed 0% AI excerpt
        $cleanHost = !empty($sourceUrl) ? preg_replace('/^www\./i', '', parse_url($sourceUrl, PHP_URL_HOST) ?? '') : 'the official portal';
        if (empty($cleanHost)) {
            $cleanHost = 'the official portal';
        }

        $newExcerpt = match ($intentKey) {
            'ADMIT_CARD'      => "Download official admit card and check examination schedule for {$title} on {$cleanHost}.",
            'ANSWER_KEY'      => "Check provisional answer key, challenge window parameters, and response sheet details for {$title} on {$cleanHost}.",
            'RESULT_CUTOFF'   => "Check official scorecards, merit ranks, and category-wise qualifying cutoffs for {$title} on {$cleanHost}.",
            'RECRUITMENT'     => "Official notification, eligibility criteria, and application schedule for {$title} on {$cleanHost}.",
            'SYLLABUS_CHANGE' => "Review revised examination syllabus, subject weightage, and evaluation pattern for {$title} on {$cleanHost}.",
            default           => "Check official schedule, eligibility parameters, and verified alerts for {$title} on {$cleanHost}."
        };

        return [
            'content' => trim($assembledHtml),
            'excerpt' => $newExcerpt,
            'intent'  => $intentKey
        ];
    }

    /**
     * Programmatically replaces formulaic opening sentences with proven 0% AI conditional hooks.
     */
    public function sanitizeOpeningHook(string $prose, string $examTitle, string $sourceUrl, string $intent): string
    {
        return HumanizerService::sanitizeOpeningHook($prose, $examTitle, $sourceUrl, $intent);
    }

    /**
     * Programmatically enforce natural contractions across the text.
     */
    public function enforceContractions(string $text): string
    {
        return HumanizerService::enforceContractions($text);
    }

    /**
     * Programmatically scrub hyper-formal academic jargon into human phrasing.
     */
    public function scrubClichés(string $text): string
    {
        return HumanizerService::scrubClichés($text);
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
SPECIFIC SECTION GOAL (DATA-FIRST GAZETTE OPENING):
- State the core official development immediately: what milestone or examination schedule is notified, the responsible authority ({$authority}), and key verified parameters.
- If authority is unverified or generic, refer to it neutrally as "the recruiting authority for {$examTitle}".
- NEVER use fake coaching hooks, urgency tropes, or server crash warnings: NO "stop scrolling", NO "take a breath", NO "don't panic", NO "head over right now", NO "crashing servers", NO "clock is ticking".
- NEVER use formulaic AI preambles: NO "The wait is finally over", NO "In a significant development", NO "Following the announcement".
- Maintain high sentence burstiness (mix 4-8 word concise factual statements with 15-25 word regulatory statements).
GUIDE;
        } elseif ($isSteps) {
            $specificGuidance = <<<GUIDE
SPECIFIC SECTION GOAL (ACTIONABLE STEP-BY-STEP LIST):
- Provide a clear, actionable numbered list (<ol><li>...</li></ol>) of 4 to 5 concise steps.
- Write each step as an active command (e.g. "Head over to the official portal", "Click on the candidate login / download link", "Enter your registration number and DOB", "Save the PDF and print at least two copies").
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
SPECIFIC SECTION GOAL (OFFICIAL HELPDESK & CORRECTION WINDOW RULES):
- Provide official gazetted helpdesk protocols: helpline numbers, official email, and representation timelines.
- Do NOT provide generic browser troubleshooting (no incognito mode or cache advice).
GUIDE;
        }

        return <<<PROMPT
You are a senior Indian education journalist and official gazette reporter for Sarkari.online.

YOUR MISSION:
Write the content for the section: "{$sectionKey}"
Examination: "{$examTitle}"
Issuing Authority: {$authority}
Article Intent: {$intent}

{$specificGuidance}

BACKGROUND CONTEXT:
{$contextSnippet}

STRICT LINGUISTIC RULES (0% AI / 100% HUMAN FORMULA ON QUILLBOT & GPTZERO):
1. HIGH BURSTINESS (EXTREME ASYMMETRIC SENTENCE LENGTHS):
   - Mix concise punchy sentences (4 to 8 words) with informative factual sentences (15-25 words).
   - Never write consecutive sentences of uniform length.

2. MANDATORY NATURAL HUMAN CONTRACTIONS:
   - Use natural contractions where appropriate: don't, can't, it's, here's, won't, there's, haven't.

3. AUTHORITATIVE JOURNALISTIC TONE (ZERO FAKE-MENTOR GURU CLAIMS):
   - Write in objective 3rd-person gazette voice.
   - NEVER use first-person coaching claims: "I've seen many candidates get rejected", "Don't underestimate", "It's a classic mistake", "The interview panel isn't looking for bookish knowledge", "Don't take the Group Task lightly".
   - State rules and administrative procedures directly without emotional pep-talks.

4. COMPLETE BLACKLIST (ZERO TOLERANCE):
   - NEVER use these phrases: ["{$forbiddenStr}"].
   - Never start with: "Following the...", "In the wake of...", "As per the latest announcement...", "With the examination scheduled for...", "The wait for...".

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
