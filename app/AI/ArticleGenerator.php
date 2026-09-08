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
    public function generate(string $topic, array $sourceData, string $category = 'exam-results', string $angle = '', string $lifecycleStatus = 'active'): array {
        $currentDateFormatted = date('F d, Y');
        
        // Stage 1: Fast Deterministic Intent Classification
        $rawSnippet = $sourceData['notes'] ?? ($sourceData['snippet'] ?? '');
        $intent = $this->classifier->classify($topic, $rawSnippet);
        $outlineContract = OutlineContracts::forIntent($intent);

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
   - Explain the "Why": Why is this notification, admit card, or answer key a critical turning point? (e.g. revised vacancy numbers, city slip vs call letter schedule, biometric screening).
   - Clear up rumors: Address misleading claims circulating on social media and replace them with calm, authoritative official facts.

2. MENTORSHIP & EMPATHETIC GUIDANCE:
   - Talk directly to the student as an experienced mentor sitting across the table:
     * "If you are attempting this CBT exam for the first time, keep in mind that the countdown timer on the test screen runs continuously..."
     * "Candidates frequently face server timeouts during peak hours. Download and print multiple copies of your e-call letter immediately..."

3. RIGOROUS STATUTORY CROSS-VERIFICATION & ZERO HEDGING:
   - Every single fact, date, code, and quota MUST be grounded in VERIFIED SOURCE CONTEXT.
   - If a fact is marked as unavailable or pending, state plainly: "Awaiting Official Circular / To Be Announced (TBA)".
   - NEVER invent speculative shift timings, dummy gate-closure minutes, or arbitrary shoe/clothing bans.
   - BANNED CLICHÉS: Never use "In today's digital world", "Without further ado", "Stay tuned", "Let's dive in", "It is important to note that".

4. DYNAMIC INTENT STRUCTURAL CONTRACT:
{$outlineContract}

5. CLEAN SEMANTIC HTML:
   - Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>.
   - Every <h2> heading MUST contain the specific Examination/Recruitment entity name.
   - Format steps as clean numbered lists (<ol><li>) and comparisons/dates as clean HTML tables.
SYS;

        $sourceFactsJson = json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $userPrompt = <<<USER_PROMPT
Please generate an original, highly authoritative, search-intent driven editorial article for Sarkari.online.

TOPIC / HEADLINE: {$topic}
PRIMARY CATEGORY: {$category}
DETECTED INTENT: {$intent->value}
EDITORIAL FOCUS: {$angle}
CURRENT DATE: {$currentDateFormatted}
CURRENT LIFECYCLE STATE: {$lifecycleStatus}

VERIFIED SOURCE CONTEXT:
{$sourceFactsJson}

MANDATORY STRUCTURAL GUIDELINE:
Follow the OUTLINE CONTRACT for {$intent->value} in the system prompt exactly.
- Direct Answer Box first (40-60 words), answering the single question that brought the reader to the page.
- Every <h2> heading MUST contain the specific Examination/Recruitment entity name.
- CRITICAL: Do NOT generate sections forbidden by this intent contract (e.g. No 'How to Apply' or 'Eligibility' in Admit Card or Result articles!).

Return strictly as JSON with this exact schema:
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
  "dates_table": [
    {
      "event": "Event name",
      "date": "Official date or timeline",
      "status": "confirmed"
    }
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

        $data['_intent'] = $intent->value;
        return $data;
    }
}
