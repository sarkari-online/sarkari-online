<?php
/**
 * EduPulse - Topic Analyzer AI Service
 * Analyzes emerging trends against Indian education taxonomy, verifies authenticity requirements,
 * checks duplicate risks against existing library, and computes priority scores.
 */

namespace App\AI;

use Exception;

class TopicAnalyzer {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Analyze a topic/trend
     * 
     * @param string $trendKeyword Emerging keyword or notice headline
     * @param array $sourceInfo Array containing 'source_name', 'source_url', 'snippet'
     * @param array $existingArticles Array of existing related article titles/slugs
     * @return array Structured analysis output
     */
    public function analyze(string $trendKeyword, array $sourceInfo = [], array $existingArticles = []): array {
        $systemInstruction = <<<PROMPT
You are a senior editorial director for an Indian Education, Exams & Career intelligence portal.
Your task is to analyze emerging keywords/trends and evaluate whether our portal should publish an original report.

TAXONOMY CATEGORIES AVAILABLE:
- exam-results (Scorecards, rank lists, merit lists, marksheets)
- admit-cards (Hall tickets, exam city slips, release notices)
- exam-dates (Schedules, timetables, postponement/revision notices)
- entrance-exams (NEET UG/PG, JEE Main/Adv, CUET, GATE, CTET, AIBE, CAT, CLAT registration & blueprints)
- government-jobs (UPSC, SSC, State PSC, Bank, Defence, Railway recruitment)
- college-updates (JoSAA, CSAB, DU CSAS, UGC fee refund & norms, university admissions, college cutoffs)
- school-boards (CBSE, ICSE, State Boards 10th/12th updates)
- scholarships (NSP, PMSSS, PM YASASVI, institutional financial aids, student fee waivers)
- career-guides (Preparation strategies, subject roadmaps, syllabus breakdowns)
- student-technology (DigiLocker, ABC ID, APAAR ID, SSC OTR, EdTech & Free Skilling)

CRITICAL RULES:
1. OFFICIAL SOURCES VS DEMAND SIGNALS:
   - Official statutory portals (.gov.in, .nic.in, .ac.in) are the primary factual authority.
   - Google Trends RSS represents user search demand signal only — ground truth must be verified against official authority.
2. GENUINE STUDENT VALUE OVER QUOTA:
   - Prioritize actionable student information (official dates, direct portal instructions, eligibility criteria, registration steps).
   - Never recommend publishing merely to meet daily quotas. Only approve if genuine, useful, non-trivial information exists for students.
3. Avoid low-value clickbait rumors (e.g., unofficial "likely today" speculation without authority notice).
4. If similar articles already exist, assess duplicate risk. Only recommend publishing if there is a genuine new update.
5. TEMPORAL FRESHNESS & PROACTIVE ADVANCE NOTICE:
   - Operating Year: 2026/2027. Strictly REJECT (publish_recommendation: false) any old, expired historical exam/recruitment cycles (2025, 2024, 2023).
   - PROACTIVE TIMING: Prioritize upcoming events detected in ADVANCE (registration opening, admit card release, exam schedules, counselling rounds, answer key objections).
   - If an exam date or registration deadline has ALREADY expired/concluded in the past, REJECT it (publish_recommendation: false) because same-day/past notices are useless for students.
6. NATIONAL SKILLING & TECH EMPOWERMENT: Proactively APPROVE (publish_recommendation: true) under 'student-technology' or 'scholarships' any national skill development initiatives (NSDC, Skill India, PMKVY), Free Cloud/AI certifications (AWS, Microsoft, Google, NASSCOM), and government-backed digital learning programs offering concrete career value to Indian students.
PROMPT;

        $existingList = empty($existingArticles) ? "None" : implode("\n- ", array_map(function($a) {
            return is_array($a) ? ($a['title'] ?? '') : (string)$a;
        }, $existingArticles));

        $sourceContext = "Source Name: " . ($sourceInfo['source_name'] ?? 'Not specified') . "\n";
        $sourceContext .= "Source URL: " . ($sourceInfo['source_url'] ?? 'Not specified') . "\n";
        $sourceContext .= "Source Snippet: " . ($sourceInfo['snippet'] ?? 'Not specified') . "\n";

        $userPrompt = <<<USER_PROMPT
Please analyze the following trend:

TREND KEYWORD: {$trendKeyword}

SOURCE INFORMATION:
{$sourceContext}

EXISTING RELATED ARTICLES IN OUR DATABASE:
- {$existingList}

Return your response strictly as a JSON object with this exact schema:
{
  "publish_recommendation": true/false,
  "category": "category-slug-from-allowed-list",
  "search_intent": "informational | direct_action | deadline_tracking | comparison",
  "priority_score": 85,
  "article_type": "breaking_notice | comprehensive_guide | step_by_step_process | analysis",
  "required_source_type": "statutory_authority | government_portal | university_bulletin",
  "suggested_original_angle": "Actionable explanation of the unique value angle for Indian aspirants",
  "duplicate_risk": "low | medium | high",
  "reasoning": "Brief explanation of why this topic should or should not be published"
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'topic_analysis',
            'system_instruction' => $systemInstruction,
            'temperature' => 0.1
        ]);

        $data = $response['data'];

        // Validate required keys
        $requiredKeys = ['publish_recommendation', 'category', 'priority_score', 'suggested_original_angle', 'duplicate_risk'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new Exception("TopicAnalyzer response missing required field: {$key}");
            }
        }

        return $data;
    }
}
