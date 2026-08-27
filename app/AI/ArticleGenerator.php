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
3. COMPREHENSIVE DEPTH & ZERO FILLER (1,000–1,500+ WORDS):
   - For all Comprehensive Guides, Exam Roadmaps, Admission Blueprints, and Recruitment Portals: The article MUST be thorough and comprehensive, with a MINIMUM length of 1,000 to 1,400+ words.
   - Include detailed structured <table> comparisons (e.g. Exam Pattern & Marks Breakdown, Category Cutoffs, Eligibility & Age Criteria, Important Cycle Dates).
   - Provide multi-step actionable walkthroughs with subheadings, mandatory document checklists, mistakes candidates must avoid, and comprehensive FAQs.
   - NEVER add repetitive filler or artificial fluff sentences. Depth must come from rich, verified domain information, step-by-step clarity, and thorough explanations.
4. INTRODUCTION RULE (ZERO FLUFF):
   - The first 100-150 words MUST immediately answer: WHAT happened? WHO is affected? WHEN? WHAT should the user do right now?
   - NEVER start with filler like "In today's competitive world...", "Education is an important part of life...", "Students eagerly wait...", "Whether you are a student...", "Let's dive into...". Start directly with the verified facts.
5. STRICT FACTUAL ACCURACY & NO HALLUCINATIONS:
   - NEVER fabricate dates, vacancies, cutoffs, marks, eligibility, fees, exam patterns, or question quotas.
   - If an official fact or date is not released yet, state explicitly: "has not been officially announced yet by the authority" — NEVER guess or invent placeholder dates.
   - Clearly distinguish between:
     * [OFFICIAL LIVE UPDATE] — Confirmed by statutory gazette/portal.
     * [COMPREHENSIVE GUIDE] — Verified step-by-step process & rules.
     * [HISTORICAL BENCHMARK] — Past years' verified cutoffs & opening-closing ranks.
     * [EXPECTED TIMELINE] — Clearly labeled as estimated based on official cycle history.
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
7. TEMPORAL ACCURACY & ACADEMIC YEAR LOGIC:
   - Today is {$currentDateFormatted}.
   - In the second half of the year (July to December 2026): Spring entrance exams (WBJEE, JEE Main, NEET, GATE, CUET) for 2026 have already concluded earlier this year.
   - For Upcoming Application & Exam Guides written in late 2026: Target the UPCOMING academic cycle (e.g. 2027 Session: Notification in late 2026, Exam in early/mid 2027).
   - If writing about the current 2026 cycle in late 2026: Focus exclusively on Centralised Counselling, Seat Allotment, Rank Cutoffs, and Decentralised Spot Admissions. NEVER present past exam dates as upcoming events!
8. NO AI CLICHES & NO FAKE EXPERTS/STATS:
   - Avoid "comprehensive guide", "everything you need to know", "stay tuned".
   - Never write "Experts say..." or invent percentages like "90% of students..." without official data.
9. BRAND INTEGRITY:
   - Sarkari.online is an authentic, independent educational intelligence platform. Never write self-damaging articles claiming commercial or non-gov domain extensions are fraudulent. Clearly differentiate official application portals (where fees/forms are submitted) from independent preparation & news desks.
10. LANGUAGE:
   - 100% fluent, clear, professional Indian English. Zero Devanagari or Hindi text in titles, headings, or content.
11. CLEAN SEMANTIC HTML:
   - Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>. No markdown backticks inside HTML. No emojis in headings or titles.
12. CURRENCY & NUMERIC INTEGRITY:
   - Always use strictly the Indian Rupee symbol "₹" for all Indian exam fees, scholarships, and family income limits. NEVER use "$" or "USD" in Indian context.
   - Never write conflicting figures for the same statutory threshold (e.g. state single verified income limit like "₹2,50,000 per annum" rather than conflicting multiple numbers).
13. STRICT INTERNAL CONSISTENCY:
   - Table data, paragraph text, H1 heading, meta title, and descriptions MUST 100% match. Never state "fourth Sunday" in text and "Last Sunday" in tables; maintain absolute uniform terminology throughout the article.
14. FACTUAL SOURCING & TIMELINE LABELS:
   - If an upcoming future notification is not yet officially released by the authority, explicitly label timelines as "Expected timeline based on established statutory pattern, subject to official notification release".
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

DYNAMIC STRUCTURE GUIDELINES (Provide rich, comprehensive depth of 1,000 to 1,400+ words with HTML tables):
- Direct, fact-first introductory paragraph answering What, Who, When, and Action required.
- <h2>Overview & Official Notification Highlights</h2>
- <h2>Important Dates & Exam Schedule</h2> (Structured HTML <table>)
- <h2>Detailed Eligibility Criteria & Educational Qualifications</h2>
- <h2>Exam Pattern, Marking Scheme & Marks Breakdown</h2> (Structured HTML <table>)
- <h2>Step-by-Step Application & Selection Process</h2>
- <h2>Category-Wise Cutoff Benchmarks & Relaxation Rules</h2> (When applicable)
- <h2>Common Mistakes Candidates Must Avoid</h2>
- <h2>Frequently Asked Questions (FAQs)</h2> (5-6 genuine search questions with thorough, direct verified answers)
- <h2>Official Authority Verification & Direct Portal Links</h2>

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
