<?php
/**
 * EduPulse - Article Generator AI Service
 * Generates verified, comprehensive (1000+ words), structured editorial content for Indian education aspirants.
 * AdSense-compliant, rich in depth, strictly adheres to factual sourcing rules and clean HTML semantic structure.
 */

namespace App\AI;

use App\Services\IntentClassifierService;
use App\Services\ArticleIntent;
use App\AI\OutlineContracts;
use Exception;

class ArticleGenerator {

    private Gemini $gemini;
    private IntentClassifierService $classifier;

    public function __construct(?Gemini $gemini = null, ?IntentClassifierService $classifier = null) {
        $this->gemini = $gemini ?: new Gemini();
        $this->classifier = $classifier ?: new IntentClassifierService($this->gemini);
    }

    /**
     * Generate complete in-depth article package (1000+ words) with Intent-Driven Dynamic Outlines
     * 
     * @param string $topic Title or topic headline
     * @param array $sourceData Verified factual notes, official notice text, dates, statutory agency
     * @param string $category Category slug
     * @param string $angle Suggested editorial angle
     * @param string $lifecycleStatus Resolved lifecycle state (active, closed, etc.)
     * @return array Generated article structure
     */
    public const NOT_YET_ANNOUNCED_LABEL = 'Not Yet Officially Announced';

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
You are the Senior Investigative Education Journalist, Master Aspirant Mentor, and Editorial Director for Sarkari.online, India's premier student intelligence and examination guidance portal.
Today's Date: {$currentDateFormatted}.
Current Lifecycle State: {$lifecycleStatus}.
DETECTED ARTICLE INTENT: {$intent->value}.

YOUR CORE PERSONA & STORYTELLING PHILOSOPHY:
You write with the voice of a seasoned, empathetic Indian education editor and career mentor who deeply understands the aspirations, sacrifices, and intense pressure experienced by Indian students and their families.
Every article you create blends authentic human mentorship with 100% rigorous factual cross-verification against official statutory government websites (.gov.in, .nic.in, .ac.in).

SENIOR WRITER STORYTELLING & EDITORIAL MANDATE:
1. THE NARRATIVE HOOK & ASPIRANT CONTEXT:
   - Begin the article by acknowledging the real-world human journey: The months of rigorous preparation, early-morning study sessions, and the clarity brought by this official release.
   - Explain the "Why": Why is this notification, admit card, or answer key a critical turning point?
   - Clear up rumors: Address misleading claims circulating on social media and replace them with calm, authoritative official facts.

2. MENTORSHIP & EMPATHETIC GUIDANCE:
   - Talk directly to the student as an experienced mentor sitting across the table.
   - Highlight critical statutory instructions without generic filler.

3. STRICT FACT GROUNDING & ZERO DATE INVENTIONS:
   - You MUST refer strictly to the CONFIRMED DATES & STATUTORY FACTS provided in the prompt.
   - NEVER invent speculative dates, dummy shift minutes, or unannounced deadlines.
   - If a date is labeled '{$this->getNotYetAnnouncedLabel()}', describe it as awaiting official release; NEVER replace it with today's date or a guessed calendar date.
   - BANNED CLICHÉS: Never use "In today's digital world", "Without further ado", "Stay tuned", "Let's dive in", "It is important to note that".

4. DYNAMIC INTENT STRUCTURAL CONTRACT:
{$outlineContract}

5. CLEAN SEMANTIC HTML:
   - Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>.
   - Every <h2> heading MUST contain the specific Examination/Recruitment entity name.
   - Format steps as clean numbered lists (<ol><li>).
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
- CRITICAL: Do NOT generate sections forbidden by this intent contract.
- CRITICAL: You do NOT generate date tables or fee tables in JSON — they are strictly constructed by the system. Refer to the confirmed dates above in your prose.

Return strictly as JSON with this exact schema (NO dates_table field):
{
  "title": "100% Unique search-intent headline under 80 chars (NEVER copied verbatim from source)",
  "excerpt": "Direct 2-sentence summary outlining what happened and key action (under 160 characters)",
  "direct_answer": "Crisp 35-45 word direct factual answer answering the core student search query (who, what, when, immediate action) specifically crafted for Google Position 0 Featured Snippet",
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
            $table[$field] = ($fact && ($fact['source_confidence'] ?? '') !== 'unavailable' && !empty($fact['value']))
                ? $fact['value']
                : self::NOT_YET_ANNOUNCED_LABEL;
        }

        // 2. Additional standard milestones from dates_schedule if available
        $schedule = $facts['dates_schedule'] ?? ($facts['verified_facts']['dates_schedule'] ?? []);
        if (is_array($schedule)) {
            foreach ($schedule as $item) {
                $m = $item['milestone'] ?? '';
                $d = $item['date'] ?? '';
                if (!empty($m) && !empty($d) && !isset($table[$m])) {
                    $status = $item['status'] ?? 'Confirmed';
                    $table[$m] = ($status === 'Awaiting Official Circular' || stripos($d, 'to be announced') !== false)
                        ? self::NOT_YET_ANNOUNCED_LABEL
                        : $d;
                }
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
     * Render clean semantic HTML table from dates array
     */
    private function renderDatesTableHtml(array $datesTable): string
    {
        if (empty($datesTable)) {
            return '';
        }

        $html = '<div class="table-responsive"><table class="data-table"><thead><tr><th>Statutory Milestone</th><th>Official Date / Status</th></tr></thead><tbody>';
        foreach ($datesTable as $event => $date) {
            $cleanEvent = ucwords(str_replace('_', ' ', (string)$event));
            $isTba = ($date === self::NOT_YET_ANNOUNCED_LABEL || stripos((string)$date, 'not yet') !== false);
            $valDisplay = $isTba
                ? '<span class="status-pill status-pill-upcoming">' . htmlspecialchars(self::NOT_YET_ANNOUNCED_LABEL) . '</span>'
                : '<strong>' . htmlspecialchars((string)$date) . '</strong>';

            $html .= "<tr><td>{$cleanEvent}</td><td>{$valDisplay}</td></tr>";
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Replace any LLM-fabricated table with the verified PHP-constructed dates table
     */
    private function injectPhpDatesTable(string $content, string $datesTableHtml): string
    {
        if (empty($datesTableHtml)) {
            return $content;
        }

        // If the LLM already generated a table right after the first H2, replace that first table
        if (preg_match('/<div class="table-responsive">.*?<\/table><\/div>/s', $content)) {
            return preg_replace('/<div class="table-responsive">.*?<\/table><\/div>/s', $datesTableHtml, $content, 1);
        }

        if (preg_match('/<table>.*?<\/table>/s', $content)) {
            return preg_replace('/<table>.*?<\/table>/s', $datesTableHtml, $content, 1);
        }

        // Otherwise inject right after the first paragraph following H2
        if (preg_match('/(<\/h2>\s*<p>.*?<\/p>)/s', $content, $m)) {
            return str_replace($m[1], $m[1] . "\n" . $datesTableHtml, $content);
        }

        return $content . "\n" . $datesTableHtml;
    }
}

