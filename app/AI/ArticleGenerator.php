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
You are a chief education editor for Sarkari.online, an independent Indian education, examinations, and recruitment information platform.
Today's Date: {$currentDateFormatted}.
Your editorial guidelines prioritize 100% verified facts, clear step-by-step guidance, and authoritative clarity for Indian students and exam aspirants.
You write in fluent, professional Indian English. Every numerical figure, cutoff percentile, fee structure, syllabus unit, and exam date MUST be grounded in verified statutory facts.
Never hallucinate dates or circular numbers. Maintain an authentic, highly useful reporting style.

STRICT EDITORIAL & DEPTH RULES:
1. EXHAUSTIVE LENGTH & DEPTH: Provide an in-depth, end-to-end comprehensive guide (900+ words) tailored precisely to the topic intent.
2. TOPIC INTENT ADAPTATION:
   - For Syllabus / Preparation / Weightage Guides: Focus heavily on Chapter-Wise Question & Marks Weightage Tables, Class 11 vs Class 12 unit breakdowns, high-yield topics, NCERT revision strategy, and book recommendations. Do NOT force generic application forms or fake past registration schedules.
   - For Recruitment / Exam Notices / Admit Cards / Results: Focus on Official Notice Details, Timetable & Schedule, Eligibility & Cutoffs, Step-by-Step Portal Access Guide, and Mandatory Guidelines.
3. WEIGHTAGE & SYLLABUS FACTUAL HONESTY: Examination bodies (NTA, NMC, UPSC, SSC, CBSE) do NOT fix mandatory per-chapter question quotas. NEVER use words like "statutory distribution" for chapter weightage. Always label tables as "Trend-Based Historical Distribution (Based on Past Exam Papers)" and include a clear note that official bodies prescribe the overall syllabus without pre-declared per-chapter quotas.
4. TEMPORAL ACCURACY: Today is {$currentDateFormatted}. Never write past months of the current year as upcoming tentative dates. If an exam for the current year has concluded, refer to past trends or focus on preparation for upcoming cycles.
5. ABSOLUTELY NO HALLUCINATIONS: Use accurate statutory data (NTA, UPSC, SSC, CBSE, NMC) for syllabus, weightage, and patterns.
6. CLEAN SEMANTIC HTML: Structure the article body using standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>. Do NOT use markdown backticks inside the content string.
7. NO EMOJIS: Do not use emojis in headings, titles, or body text.
SYS;

        $sourceFactsJson = json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $userPrompt = <<<USER_PROMPT
Please generate an in-depth, highly structured, AdSense-compliant editorial article (aim for 1000+ words) tailored to the topic:

TOPIC / HEADLINE: {$topic}
CATEGORY: {$category}
EDITORIAL FOCUS: {$angle}
TODAY'S DATE: {$currentDateFormatted}

VERIFIED SOURCE DATA & TOPIC CONTEXT:
{$sourceFactsJson}

REQUIRED CONTENT STRUCTURE (Ensure thorough depth with clean semantic HTML):
- Clear, authoritative <h2> headings matching the exact topic focus (e.g., Chapter-Wise Weightage Tables, Unit-wise Question Distributions, Step-by-Step Guidance, Preparation Strategy).
- Comprehensive tables with accurate column headers for easy readability.
- <h2>Frequently Asked Questions (FAQs)</h2> with 4-5 direct, highly practical candidate questions and answers.

Return your output strictly as a JSON object with this exact schema:
{
  "title": "Clear, informative headline under 75 characters",
  "excerpt": "A concise 2-sentence summary outlining key announcements and who is affected (under 160 characters)",
  "content": "<h2>Overview...</h2><p>...</p>...",
  "key_takeaways": [
    "Confirmed fact point 1",
    "Confirmed fact point 2",
    "Confirmed fact point 3",
    "Confirmed fact point 4"
  ],
  "dates_table": [
    {
      "event": "Event / Topic Item",
      "date": "Official Date or Weightage Metric",
      "status": "confirmed"
    }
  ],
  "source_attribution": {
    "name": "Official Authority Name",
    "url": "Official Portal URL",
    "reference": "Circular / Official Reference if known"
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
