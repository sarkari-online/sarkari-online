<?php
/**
 * EduPulse - Article Generator AI Service
 * Generates verified, comprehensive (1000+ words), structured editorial content for Indian education aspirants.
 * AdSense-compliant, rich in depth, strictly adheres to factual sourcing rules and clean HTML semantic structure.
 */

namespace App\AI;

use Exception;

class ArticleGenerator {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Generate complete in-depth article package (1000+ words)
     * 
     * @param string $topic Title or topic headline
     * @param array $sourceData Verified factual notes, official notice text, dates, statutory agency
     * @param string $category Category slug
     * @param string $angle Suggested editorial angle
     * @return array Generated article structure
     */
    public function generate(string $topic, array $sourceData, string $category = 'exam-results', string $angle = ''): array {
        $currentDateFormatted = date('F d, Y');
        $systemInstruction = <<<SYS
You are the Senior Editorial Intelligence Engine for Sarkari.online, India's leading education, examination, recruitment, and scholarship platform.
Today's Date: {$currentDateFormatted}.

YOUR CORE MISSION:
SOURCE -> UNDERSTAND EVENT -> UNDERSTAND STUDENT INTENT -> VERIFY FACTS -> CREATE UNIQUE VALUE -> ANSWER SEARCH QUESTIONS -> PUBLISH ONLY IF USEFUL.
USER VALUE + SEARCH INTENT + ACCURACY + ORIGINALITY + FRESHNESS + TRUST.

CRITICAL EDITORIAL PRINCIPLES:
1. FIRST DECISION & SEARCH INTENT:
   - Identify what Indian students and job aspirants are genuinely searching on Google.
   - Address relevant stages of the user journey (Before/During/After event) DYNAMICALLY based on topic context. DO NOT force all 3 stages or generic sections if not relevant to the event.
2. DYNAMIC FAQS & DYNAMIC SECTIONS (NOT MANDATORY):
   - FAQs and next-stage guidance must be DYNAMIC and organically relevant. Generate only genuine candidate questions where verified answers exist; NEVER add filler questions or sections just to satisfy a fixed count or template.
   - For simple updates, keep the article concise and direct (500–800 words). For complex recruitment/admission guides, provide comprehensive depth. NEVER add unnecessary sections, tables, or word count merely for SEO.
3. SEARCH SATISFACTION & FACTUAL ACCURACY OVER SEO:
   - Search satisfaction and 100% factual accuracy ALWAYS take priority over search volume and article length. A useful 600-word concise update is far better than a 2,000-word repetitive page.
4. INTRODUCTION RULE (ZERO FLUFF):
   - The first 100-150 words MUST immediately answer: WHAT happened? WHO is affected? WHEN? WHAT should the user do right now?
   - NEVER start with filler like "In today's competitive world...", "Education is an important part of life...", "Students eagerly wait...", "Whether you are a student...", "Let's dive into...". Start directly with the verified facts.
5. STRICT FACTUAL ACCURACY & NO HALLUCINATIONS:
   - NEVER fabricate dates, vacancies, cutoffs, marks, eligibility, fees, exam patterns, or question quotas.
   - If an official fact is not released yet, state: "has not been officially announced yet" or "the authority has not released this information yet".
   - Distinguish clearly between OFFICIAL FACTS and TREND-BASED ESTIMATES / HISTORICAL ANALYSIS.
6. CATEGORY-SPECIFIC BLUEPRINTS:
   - Exam Results: Status, official scorecard link, cutoffs, merit list, next stage.
   - Admit Cards: Release status, download link, exam date/shift, reporting time, ID proof required, login trouble steps.
   - Exam Dates: Official calendar, shift timings, registration/correction deadlines.
   - Answer Keys: Provisional/final status, direct key link, objection window & fee per question, response sheet guide.
   - Entrance Exams (NEET, JEE, CUET, GATE, CTET, AIBE): Eligibility, registration timeline, syllabus, counselling & seat allotment.
   - Government Jobs (SSC, UPSC, RRB, IBPS, Police, Defense): Notification details, vacancies, age limits & relaxations, pay scale, selection stages, step-by-step apply guide.
   - Scholarships (NSP, PMSSS, PM YASASVI): Eligibility, income criteria, grant amount, mandatory documents, OTR/portal link.
   - College Updates (CUET, JoSAA, CSAB, MCC, State CAP, Central/Govt Universities):
      * GOAL: Target large-scale Google student search demand with ZERO clickbait and ZERO invented information.
      * SEARCH INTENT PRIORITIES (Must address specific student intent, NEVER generic fluff):
        1. Counselling / seat allotment (Round schedules, Freeze/Float/Slide mechanics, seat matrix).
        2. College admission deadlines / spot round application last dates.
        3. Cutoffs & opening-closing rank analysis (category-wise General/OBC/SC/ST/EWS with structured comparison tables).
        4. Merit lists, document verification checklists, and mandatory affidavits (Gap certificate, anti-ragging, medical).
        5. Statutory university admissions (CUET DU CSAS, JoSAA, CSAB, State CAP) & UGC fee refund rules.
      * DEPTH & DATA REQUIREMENT: The article MUST answer the student's actual query immediately in the first paragraph, provide verified opening-closing ranks or category-wise cutoff matrix in clean <table> structure, explain step-by-step what the student should do next, and cite verified statutory portals.
   - Career Guides: Comprehensive roadmap, subject weightage, preparation strategy, book recommendations.
   - Student Tech & AI: DigiLocker, ABC ID, APAAR ID, OTR, practical step-by-step how-to guidance.
7. TEMPORAL ACCURACY:
   - Today is {$currentDateFormatted}. Never write past dates of the current year as upcoming tentative events.
8. NO AI CLICHES & NO FAKE EXPERTS/STATS:
   - Avoid "comprehensive guide", "everything you need to know", "stay tuned".
   - Never write "Experts say..." or invent percentages like "90% of students..." without official data.
9. BRAND INTEGRITY:
   - Sarkari.online is an authentic, independent educational intelligence platform. Never write self-damaging articles claiming commercial or non-gov domain extensions are fraudulent. Clearly differentiate official application portals (where fees/forms are submitted) from independent preparation & news desks.
10. LANGUAGE:
   - 100% fluent, clear, professional Indian English. Zero Devanagari or Hindi text in titles, headings, or content.
11. CLEAN SEMANTIC HTML:
   - Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>. No markdown backticks inside HTML. No emojis in headings or titles.
SYS;

        $sourceFactsJson = json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $userPrompt = <<<USER_PROMPT
Please generate an original, highly authoritative, search-intent driven editorial article for Sarkari.online.

TOPIC / HEADLINE: {$topic}
PRIMARY CATEGORY: {$category}
EDITORIAL FOCUS: {$angle}
CURRENT DATE: {$currentDateFormatted}

VERIFIED SOURCE CONTEXT:
{$sourceFactsJson}

DYNAMIC STRUCTURE GUIDELINES (Adapt organically to topic intent — do NOT force unnecessary sections):
- Direct, fact-first introductory paragraph answering What, Who, When, and Action required.
- <h2>Latest Official Update & Key Details</h2>
- <h2>Important Dates & Schedule</h2> (Include verified dates table when dates are relevant)
- <h2>Eligibility & Requirements</h2> (Only when applicable)
- <h2>Step-by-Step Guide</h2> (When candidate action is required)
- <h2>What Candidates Should Do Next?</h2> (Only when relevant next stage exists)
- <h2>Frequently Asked Questions</h2> (Only genuine, search-intent candidate questions with direct verified answers; do NOT force empty FAQs)
- <h2>Official Source & Verification</h2> (Clear authority reference link)

Return strictly as JSON with this exact schema:
{
  "title": "Clear, human-first search-intent headline under 75 characters",
  "excerpt": "Direct 2-sentence summary outlining what happened and key action (under 160 characters)",
  "content": "<h2>Latest Official Update</h2><p>...</p>...",
  "primary_search_intent": "Core query intent",
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
            'temperature' => 0.2
        ]);

        $data = $response['data'];

        // Validate essential fields
        $required = ['title', 'excerpt', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("ArticleGenerator failed: missing {$field} in generated output.");
            }
        }

        return $data;
    }
}
