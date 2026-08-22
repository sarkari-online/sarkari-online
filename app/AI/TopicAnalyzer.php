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
- government-jobs (UPSC, SSC, State PSC, Bank, Defence, Railway recruitment)
- higher-education (Admissions, counselling, college cutoffs, university updates)
- school-boards (CBSE, ICSE, State Boards 10th/12th updates)
- scholarships (Government & institutional financial aids)
- career-guides (Preparation strategies, subject roadmaps, career paths)
- student-technology (EdTech tools, AI for learning, educational apps)

CRITICAL RULES:
1. Prioritize actionable student information (official dates, direct portal instructions, criteria).
2. Avoid low-value clickbait rumors (e.g., unofficial "likely today" speculation without authority notice).
3. If similar articles already exist, assess duplicate risk. Only recommend publishing if there is a genuine new update.
4. TEMPORAL FRESHNESS & RELEVANCE: Current operating year is 2026. Strictly REJECT (publish_recommendation: false) any old, expired historical exam/recruitment cycles (e.g., 2024, 2023, 2022, 2021). Only approve active 2026/2027 ongoing cycles.
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
