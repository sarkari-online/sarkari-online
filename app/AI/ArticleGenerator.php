<?php
/**
 * EduPulse - Article Generator AI Service
 * Generates verified, comprehensive (1000+ words), structured editorial content for Indian education aspirants.
 * AdSense-compliant, rich in depth, strictly adheres to factual sourcing rules and clean HTML semantic structure.
 */

namespace App\AI;

use App\Services\IntentClassifierService;
use App\Services\ArticleIntent;
use App\Services\ContentIntegrityGuard;
use App\Services\HumanizerService;
use App\Services\ArticleQualityEngine;
use App\Helpers\Logger;
use App\AI\OutlineContracts;
use Exception;

class ArticleGenerator {

    private Gemini $gemini;
    private IntentClassifierService $classifier;

    public const DATES_TABLE_PLACEHOLDER = '<!--DATES_MILESTONE_TABLE-->';
    public const NOT_YET_ANNOUNCED_LABEL = 'Not Yet Officially Announced';

    public function __construct(?Gemini $gemini = null, ?IntentClassifierService $classifier = null) {
        $this->gemini = $gemini ?: new Gemini();
        $this->classifier = $classifier ?: new IntentClassifierService($this->gemini);
    }

    /**
     * Generate complete in-depth article package (1000+ words) with Intent-Driven Dynamic Outlines
     * Decoupled Architecture: Dates and Fee tables are constructed strictly by PHP from verified facts;
     * LLM only authors prose and is never asked to invent or fill dates as free text.
     * 
     * @param string $topic Title or topic headline
     * @param array $sourceData Verified factual notes, official notice text, dates, statutory agency
     * @param string $category Category slug
     * @param string $angle Suggested editorial angle
     * @param string $lifecycleStatus Resolved lifecycle state (active, closed, etc.)
     * @return array Generated article structure
     */
    public function generate(string $topic, array $sourceData, string $category = 'exam-results', string $angle = '', string $lifecycleStatus = 'active'): array {
        $currentDateFormatted = date('F d, Y');
        
        // Stage 1: Fast Deterministic Intent Classification
        $rawSnippet = $sourceData['notes'] ?? ($sourceData['snippet'] ?? '');
        $intent = $this->classifier->classify($topic, $rawSnippet);
        $outlineContract = OutlineContracts::forIntent($intent);

        // Stage 2: Construct Structured Tables directly in PHP (Zero LLM Fabrication)
        $verifiedFacts = $sourceData['verified_facts'] ?? $sourceData;
        $datesTable = $this->buildDatesTableFromFacts($verifiedFacts, $intent);
        $feeTable = $this->buildFeeTableFromFacts($verifiedFacts, $intent);
        $datesTableHtml = $this->renderDatesTableHtml($datesTable);

        $systemInstruction = <<<SYS
You are the Senior Investigative Education Journalist and Editorial Director for Sarkari.online, India's premier student intelligence and examination guidance portal.
Today's Date: {$currentDateFormatted}.
Current Lifecycle State: {$lifecycleStatus}.
DETECTED ARTICLE INTENT: {$intent->value}.

YOUR CORE PERSONA & EDITORIAL PHILOSOPHY:
You write with the authentic voice of a seasoned, empathetic Indian education editor. Every article blends authentic human clarity with 100% rigorous factual cross-verification against official statutory government websites (.gov.in, .nic.in, .ac.in).

IMMUTABLE LAWS OF EDITORIAL EXCELLENCE:

LAW 1: THE DIRECT NEWS-FIRST OPENING (NO ROBOTIC FORMULAS)
- NEVER open with inverted pyramid definitions: "[Entity] is a [adjective] [category]..."
- NEVER open with formulaic hooks: "The [Authority] has released...", "The wait is finally over...", "In a major update...", "If you've been waiting for...", "Good news for aspirants...", "Candidates who have been eagerly waiting...".
- Open immediately with the core factual event: What happened, Who is affected, What date or status matters, and What action is required next.

LAW 2: NATURAL ASYMMETRIC SENTENCE RHYTHM (3-30 RULE)
- Mix punchy short sentences (3 to 6 words) with natural medium (12-16 words) and longer explanatory sentences (22-28 words).
- Never write three sentences of uniform length in a row. Avoid repetitive sentence openings.

LAW 3: NATURAL HUMAN CONTRACTIONS
- Use natural human contractions throughout: you'll, don't, can't, it's, here's, won't, there's, you've, didn't, aren't.

LAW 4: FORBIDDEN SEMICOLON ANTITHESIS CLICHES
- ABSOLUTELY FORBIDDEN: "[X] isn't just about A; it's about B" or "It's not just a [noun]; it's a [noun]".
- State the concrete fact behind it instead — what specifically makes it hard or different.

LAW 5: BANNED WORDS & VAGUE FILLER
- ABSOLUTELY BANNED: delve, testament, crucial, pivotal, multifaceted, foster, beacon, paramount, landscape, embark, streamline, digital era, competitive era, without further ado, stay tuned, furthermore, moreover, in conclusion, utilize, tapestry, plethora, comprehensive guide, centralized repository.
- Every sentence must carry a real, checkable fact — a date, document name, fee amount, venue rule, or specific rejection reason. If a sentence has no fact in it, delete it.

LAW 6: PROCEDURAL SPECIFICITY OVER VAGUE ADVICE
- Instead of "don't ignore the fine print", name the actual rule.
- State official portal domains, exact fees, photograph specs, and official reporting times.

LAW 7: ZERO BLOGGER TECH-ADVICE & TICKING-CLOCK TROPES
- NEVER write server crash / incognito / browser cache boilerplate ("servers will crawl", "try incognito mode or clear your browser cache; it often does the trick", "save password in a notepad file").
- NEVER write ticking clock urgency clichés ("clock starts ticking immediately", "make your move, don't waste time", "portal won't reopen", "clock hits the final hour").
- NEVER write fear-mongering warnings ("it's a red flag", "errors haunting your results later").
- NEVER write pep-talk endings ("You've worked too hard for this", "Stay sharp, move fast").

LAW 8: ZERO GENERIC EXAM-DAY FLUFF OR PREACHINESS
- NEVER invent unverified advice: "transparent pouch", "reach early", "strict adherence to protocols", "smartwatches banned", "stay calm", "sleep well". Include exam instructions ONLY if they appear explicitly in the official circular.

LAW 9: ZERO FAKE PSYCHOLOGICAL FAQS (MAXIMUM 3 FACTUAL FAQS)
- FAQs are OPTIONAL. Maximum 3 FAQs per article.
- NEVER create FAQs about exam stress, sleep schedules, motivation, or server slowdowns.
- Every FAQ must have a concrete factual answer grounded in the verified source. If no genuine FAQs exist, omit the FAQ section entirely.

LAW 10: NO BOILERPLATE DISCLAIMER / AUTHORITY VERIFICATION SECTION
- Do NOT generate a large concluding section titled "Official Notice Reference & Authority Verification" or boilerplate text ("All information provided is based on official circulars...").

DYNAMIC INTENT STRUCTURAL CONTRACT:
{$outlineContract}

CLEAN SEMANTIC HTML:
- Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>.
- Every <h2> heading MUST contain the specific Examination/Recruitment entity name.
SYS;

        $sourceFactsJson = json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $datesTableJson = json_encode($datesTable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $userPrompt = <<<USER_PROMPT
Please generate an original, highly authoritative, search-intent driven editorial article for Sarkari.online.

TOPIC / HEADLINE: {$topic}
PRIMARY CATEGORY: {$category}
DETECTED INTENT: {$intent->value}
EDITORIAL FOCUS: {$angle}
CURRENT DATE: {$currentDateFormatted}
CURRENT LIFECYCLE STATE: {$lifecycleStatus}

CONFIRMED STATUTORY DATES (READ-ONLY GROUND TRUTH):
{$datesTableJson}

VERIFIED SOURCE CONTEXT:
{$sourceFactsJson}

MANDATORY STRUCTURAL GUIDELINE:
Follow the OUTLINE CONTRACT for {$intent->value} in the system prompt exactly.
- Direct Answer Box first (40-60 words), answering the single question that brought the reader to the page.
- Every <h2> heading MUST contain the specific Examination/Recruitment entity name.
- Immediately after the first <h2> heading and its opening paragraph, you MUST insert the literal token: <!--DATES_MILESTONE_TABLE-->
- CRITICAL: Do NOT generate sections forbidden by this intent contract.
- CRITICAL: You do NOT generate date tables or statutory milestone tables yourself — the system automatically inserts the verified dates table at that exact marker.
- Domain tables (Vacancy Distribution, Exam Pattern, Subject Weightage, Shift Schedule, Challenge Fees, Cutoffs) MUST contain strictly domain data and NEVER duplicate statutory milestone dates.

Return strictly as JSON with this exact schema (NO dates_table field):
{
  "title": "100% Unique search-intent headline under 80 chars (NEVER copied verbatim from source)",
  "excerpt": "Direct 2-sentence summary outlining what happened and key action (under 160 characters)",
  "direct_answer": "Crisp 35-50 word direct factual answer in 100% human mentor voice. MUST use contractions (you'll, it's, don't). MUST NOT start with 'Following the...' or use robotic clichés.",
  "content": "<h2>[Entity/Exam Name]: Latest Official Circular & Update</h2><p>...</p>...",
  "primary_search_intent": "{$intent->value}",
  "search_queries": [
    "search query 1",
    "search query 2",
    "search query 3",
    "search query 4",
    "search query 5"
  ],
  "key_takeaways": [
    "Key fact 1",
    "Key fact 2",
    "Key fact 3",
    "Key fact 4"
  ],
  "source_attribution": {
    "name": "Official Authority Name",
    "url": "Official Portal URL",
    "reference": "Official Notification Reference"
  }
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'article_generation',
            'system_instruction' => $systemInstruction,
            'temperature' => 0.15
        ]);

        $data = $response['data'];

        // Validate essential fields
        $required = ['title', 'excerpt', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("ArticleGenerator failed: missing {$field} in generated output.");
            }
        }

        // Attach PHP-constructed tables (Guaranteed 100% immune to LLM hallucination)
        $data['dates_table'] = $datesTable;
        $data['fee_table'] = $feeTable;
        $data['_intent'] = $intent->value;

        // Ensure the content contains the authentic PHP-constructed dates table HTML
        $data['content'] = $this->injectPhpDatesTable($data['content'], $datesTableHtml);

        // Deterministic Anti-AI Humanization Post-Processing:
        // Enforce contractions, scrub banned AI words, sanitize opening hooks across all text fields
        $sourceUrl = $sourceData['source_url'] ?? '';
        $data['content'] = HumanizerService::humanize($data['content'], $topic, $sourceUrl, $intent->value);
        if (!empty($data['direct_answer'])) {
            $data['direct_answer'] = HumanizerService::humanize($data['direct_answer'], $topic, $sourceUrl, $intent->value);
        }
        if (!empty($data['excerpt'])) {
            $data['excerpt'] = HumanizerService::enforceContractions(HumanizerService::scrubClichés($data['excerpt']));
        }

        return $data;
    }

    public function getNotYetAnnouncedLabel(): string {
        return self::NOT_YET_ANNOUNCED_LABEL;
    }

    /**
     * Construct structured dates table in PHP from verified facts
     */
    public function buildDatesTableFromFacts(array $facts, ArticleIntent $intent): array
    {
        $requiredFields = \App\Services\FactCompletenessRules::requiredFieldsFor($intent);
        $table = [];

        // 1. Required fields for the intent
        foreach ($requiredFields as $field) {
            $fact = \App\Services\FactCompletenessRules::findFactByType($facts, $field);
            if ($fact && ($fact['source_confidence'] ?? '') !== 'unavailable' && !empty($fact['value'])) {
                $table[$field] = $fact;
            } else {
                $table[$field] = self::NOT_YET_ANNOUNCED_LABEL;
            }
        }

        // 2. Additional standard milestones from dates_schedule if available
        $existingKeysNormalized = array_map(
            fn($k) => strtolower(preg_replace('/[\s\-]+/', '_', (string)$k)),
            array_keys($table)
        );

        $schedule = $facts['dates_schedule'] ?? ($facts['verified_facts']['dates_schedule'] ?? []);
        if (is_array($schedule)) {
            foreach ($schedule as $item) {
                $m = $item['milestone'] ?? '';
                $d = $item['date'] ?? '';
                if (empty($m) || empty($d)) continue;

                $mNormalized = strtolower(preg_replace('/[\s\-]+/', '_', $m));
                if (in_array($mNormalized, $existingKeysNormalized, true)) continue;

                $status = $item['status'] ?? 'Confirmed';
                $confidence = $item['source_confidence'] ?? ($status === 'Confirmed' ? 'confirmed_primary_source' : 'unavailable');
                $basis = $item['tentative_basis'] ?? null;

                if ($status === 'Awaiting Official Circular' || stripos($d, 'to be announced') !== false || $confidence === 'unavailable') {
                    $table[$m] = self::NOT_YET_ANNOUNCED_LABEL;
                } else {
                    $table[$m] = [
                        'value' => $d,
                        'source_confidence' => $confidence,
                        'tentative_basis' => $basis
                    ];
                }
                $existingKeysNormalized[] = $mNormalized;
            }
        }

        return $table;
    }

    /**
     * Construct fee table in PHP from verified facts
     */
    public function buildFeeTableFromFacts(array $facts, ArticleIntent $intent): array
    {
        if ($intent !== ArticleIntent::RECRUITMENT) {
            return [];
        }

        $feeFact = \App\Services\FactCompletenessRules::findFactByType($facts, 'fee_amount_general');
        if ($feeFact && !empty($feeFact['value'])) {
            return [
                'general_obc_ews' => $feeFact['value'],
                'sc_st' => $facts['fee_amount_sc_st'] ?? 'As per official notification',
                'ph' => $facts['fee_amount_ph'] ?? 'As per official notification'
            ];
        }

        return [];
    }

    /**
     * Render clean semantic HTML table from dates array using MilestoneStatusRenderer
     */
    public function renderDatesTableHtml(array $datesTable): string
    {
        if (empty($datesTable)) {
            return '';
        }

        $html = '<div class="table-responsive"><table class="data-table"><thead><tr><th>Statutory Milestone</th><th>Official Date / Status</th></tr></thead><tbody>';
        foreach ($datesTable as $event => $dateFact) {
            $cleanEvent = ucwords(str_replace('_', ' ', (string)$event));
            $valDisplay = \App\Services\MilestoneStatusRenderer::renderBadge($dateFact);
            $html .= "<tr><td>{$cleanEvent}</td><td>{$valDisplay}</td></tr>";
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Safely inject the verified PHP-constructed dates table into the article content.
     */
    public function injectPhpDatesTable(string $content, string $datesTableHtml): string
    {
        if (empty($datesTableHtml)) {
            Logger::warning('ArticleGenerator: injectPhpDatesTable called with empty datesTableHtml — stripped placeholder without table injection');
            return str_replace(self::DATES_TABLE_PLACEHOLDER, '', $content);
        }

        $before = $content;
        $after = '';

        if (str_contains($content, self::DATES_TABLE_PLACEHOLDER)) {
            $after = str_replace(self::DATES_TABLE_PLACEHOLDER, $datesTableHtml, $content);
        } elseif (preg_match('/(?:<div[^>]*class=["\'][^"\']*table-responsive[^"\']*["\'][^>]*>\s*)?<table\b[^>]*>.*?(?:Statutory Milestone|Official Date|Important Dates|Key Dates).*?<\/table>(?:\s*<\/div>)?/is', $content, $existingMilestoneMatch)) {
            $after = str_replace($existingMilestoneMatch[0], $datesTableHtml, $content);
        } else {
            Logger::warning('ArticleGenerator: DATES_TABLE_PLACEHOLDER missing from generated content — using safe fallback insertion');
            if (preg_match('/(<\/h2>)/i', $content)) {
                $after = preg_replace('/(<\/h2>)/i', "$1\n" . $datesTableHtml, $content, 1);
            } else {
                $after = $datesTableHtml . "\n" . $content;
            }
        }

        ContentIntegrityGuard::assertNoStructuralLoss($before, $after);

        return $after;
    }
}
