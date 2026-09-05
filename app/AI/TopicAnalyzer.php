<?php
/**
 * Sarkari.online - Topic Analyzer AI Service (100-Point Weighted Intent Engine)
 * Analyzes emerging trends against Indian education taxonomy, verifies tiered authority,
 * evaluates 100-point priority scores, enforces hard quality gates, and tags content types.
 */

namespace App\AI;

use App\Services\TopicDiscoveryEngine;
use App\Services\AuthorityVerificationService;
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
        // Step 1: Pre-AI Hard Quality Gate Evaluation (Saves API Quota)
        $gateResult = TopicDiscoveryEngine::evaluateHardQualityGates($trendKeyword, $sourceInfo);
        if (!$gateResult['pass']) {
            return [
                'publish_recommendation' => false,
                'publishing_tier' => 'rejected',
                'category' => 'career-guides',
                'priority_score' => 20,
                'content_type' => 'explainer',
                'search_intent' => 'informational',
                'suggested_original_angle' => 'Rejected by Hard Quality Gate',
                'duplicate_risk' => 'high',
                'reasoning' => 'Hard Quality Gate rejection: ' . $gateResult['reason']
            ];
        }

        $systemInstruction = <<<PROMPT
You are a senior editorial director and search strategist for Sarkari.online (India's authoritative Government Jobs, Exams & Career intelligence portal).
Your task is to analyze emerging keywords/trends and score them using our 100-Point Multi-Factor Priority Scoring Model.

TAXONOMY CATEGORIES AVAILABLE:
- exam-results (Scorecards, rank lists, merit lists, marksheets)
- admit-cards (Hall tickets, exam city slips, release notices)
- exam-dates (Schedules, timetables, postponement/revision notices)
- entrance-exams (NEET UG/PG, JEE Main/Adv, CUET, GATE, CTET, AIBE, CAT, CLAT registration & blueprints)
- government-jobs (UPSC, SSC, State PSC, Bank, Defence, Railway recruitment)
- college-updates (JoSAA, CSAB, DU CSAS, UGC fee refund & norms, university admissions, college cutoffs)
- school-boards (CBSE, ICSE, State Boards 10th/12th updates)
- scholarships (NSP, PMSSS, PM YASASVI, institutional financial aids, student fee waivers)
- career-guides (Preparation strategies, subject roadmaps, syllabus breakdowns, 7th CPC salaries)
- student-technology (STUDENT_DIGITAL_SERVICES: DigiLocker, ABC ID, APAAR ID, SSC OTR, UPSC OTR)

17 DISTINCT CONTENT TYPES:
1. news_update, 2. recruitment_page, 3. exam_guide, 4. result_page, 5. admit_card,
6. answer_key, 7. cutoff_analysis, 8. scholarship_page, 9. admission_page, 10. career_guide,
11. salary_guide, 12. eligibility_guide, 13. syllabus_guide, 14. comparison_page,
15. explainer, 16. resource_pdf, 17. official_hub.

100-POINT SCORING BREAKDOWN:
- Search Demand (30 pts): Mega National (>500k = 28-30), High State/Sectoral (>100k = 22-27), Medium (>20k = 15-21), Low (<10).
- Trend & Seasonality (15 pts): Active live milestone / notification release right now (13-15), evergreen steady (9-12), off-season (<8).
- Relevance to Audience (15 pts): Direct student/aspirant utility (14-15), general policy/interest (8-12), irrelevant (<5).
- Search Intent Strength (15 pts): Direct action (Apply Online, Admit Card, Result, Cut Off = 14-15), informational (10-13), vague (<7).
- SERP Opportunity (10 pts): Better tabular data, PDF direct links, or speed advantage over competitors (8-10).
- Freshness Potential (5 pts): Multi-stage lifecycle updates (Answer key → Result → Cutoff → DV = 5).
- Monetization Value (5 pts): High-intent preparation & education CPC value (4-5).
- Internal Ecosystem (5 pts): Natural interlinking with parent commission hub and sibling guides (4-5).

TIERED PUBLISHING GATES:
- 90+ Priority: Mega National Milestone (RRB, SSC, UPSC, Bank, NEET, JEE, DigiLocker/APAAR). Auto-Approved for fast-track publishing.
- 80-89 Approved: Solid Sectoral/State recruitment or comprehensive career blueprint. Standard publishing queue.
- 65-79 Review/Wait: Needs active notice confirmation or higher demand signal.
- <65 Reject: Low intent, micro-vacancies (<50), administrative noise.

HARD QUALITY GATES (ALWAYS OVERRIDE SCORE TO REJECT IF TRIGGERED):
- Concluded transactional application forms for expired years (e.g. "Apply online 2023"). NOTE: Do NOT reject historical results, previous cutoffs, answer keys, question papers, salary, or syllabus, which remain permanently valuable for student preparation.
- Administrative noise, grievance desks, PILs, legal hearing gossip ("SC told", "plea filed", "protest").
- Duplicate search intent (>80% overlap with existing articles).
- Speculative rumors lacking an official statutory circular (.gov.in, .nic.in, ibps.in, nta.ac.in, cbse.gov.in).
PROMPT;

        $existingList = empty($existingArticles) ? "None" : implode("\n- ", array_map(function($a) {
            return is_array($a) ? ($a['title'] ?? '') : (string)$a;
        }, $existingArticles));

        $sourceContext = "Source Name: " . ($sourceInfo['source_name'] ?? 'Not specified') . "\n";
        $sourceContext .= "Source URL: " . ($sourceInfo['source_url'] ?? ($sourceInfo['url'] ?? 'Not specified')) . "\n";
        $sourceContext .= "Source Snippet: " . ($sourceInfo['snippet'] ?? 'Not specified') . "\n";

        $userPrompt = <<<USER_PROMPT
Please evaluate the following trend against our 100-Point Scoring Model:

TREND KEYWORD: {$trendKeyword}

SOURCE INFORMATION:
{$sourceContext}

EXISTING RELATED ARTICLES IN OUR DATABASE:
- {$existingList}

Return your response strictly as a JSON object with this exact schema:
{
  "publish_recommendation": true/false,
  "priority_score": 92,
  "publishing_tier": "priority | approved | review_wait | rejected",
  "category": "category-slug-from-allowed-list",
  "content_type": "one_of_the_17_content_types",
  "search_intent": "informational | transactional | time_sensitive | navigational | comparison",
  "scoring_breakdown": {
    "demand": 28,
    "trend": 14,
    "relevance": 15,
    "intent": 14,
    "serp": 8,
    "freshness": 5,
    "monetization": 4,
    "ecosystem": 4
  },
  "canonical_topic": "Consolidated clean canonical headline for this search intent",
  "suggested_original_angle": "Actionable explanation of the unique value angle for Indian aspirants",
  "duplicate_risk": "low | medium | high",
  "reasoning": "Brief justification of scores and tier"
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'topic_analysis',
            'system_instruction' => $systemInstruction,
            'temperature' => 0.1
        ]);

        $data = $response['data'];

        // Validate required keys
        $requiredKeys = ['publish_recommendation', 'category', 'priority_score', 'content_type'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new Exception("TopicAnalyzer response missing required field: {$key}");
            }
        }

        // Apply strict Tiered Publishing Gate override
        $tierInfo = TopicDiscoveryEngine::classifyPublishingTier((int)$data['priority_score']);
        $data['publishing_tier'] = $tierInfo['tier'];
        $data['publish_recommendation'] = in_array($tierInfo['tier'], ['priority', 'approved'], true);

        return $data;
    }
}
