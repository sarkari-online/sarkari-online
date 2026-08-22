<?php
/**
 * EduPulse - Fact Checker & Quality Audit AI Service
 * Cross-examines drafted articles against verified authority records, detecting hallucinations,
 * discrepancies in dates or quotas, and copied phrasing risks.
 */

namespace App\AI;

use App\Database\Database;
use App\Helpers\Logger;
use Exception;

class FactChecker {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Audit an article against verified source facts
     * 
     * @param array $article Array containing 'title', 'excerpt', 'content', 'id' (optional)
     * @param array $sourceFacts Ground truth data / official bulletin notes
     * @return array Verification audit scorecard
     */
    public function check(array $article, array $sourceFacts = []): array {
        $systemInstruction = <<<PROMPT
You are a meticulous Chief Fact-Checking Officer for an Indian education and career news portal.
Your job is to audit draft articles against original ground-truth source material.

YOU MUST CHECK FOR:
1. DATE ACCURACY: Are application deadlines, exam dates, or result declarations identical to the source?
2. AUTHORITY CONSISTENCY: Are testing bodies (NTA, UPSC, SSC, MCC, CBSE, etc.) correctly identified without confusion?
3. NUMERICAL INTEGRITY: Are vacancy numbers, fee amounts, eligibility percentages, or age criteria completely accurate?
4. UNSUPPORTED CLAIMS: Does the draft state unverified rumours as confirmed facts?
5. HALLUCINATION RISK: Did the generator invent circular numbers or procedures not found in the ground truth?
6. PLAGIARISM / DIRECT PHRASING: Did the generator copy entire sentences from third-party websites?

SCORING CRITERIA:
- 90–100: Flawless alignment with ground truth, clear distinction of expectations. Pass.
- 75–89: Minor ambiguities or missing caveats. Needs human review.
- < 75: Contains factual errors, distorted dates, or fabricated claims. Reject.
PROMPT;

        $sourceJson = json_encode($sourceFacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $articleTitle = $article['title'] ?? '';
        $articleContent = strip_tags($article['content'] ?? '');

        $userPrompt = <<<USER_PROMPT
Please perform a rigorous factual audit on the following draft article:

DRAFT ARTICLE TITLE:
{$articleTitle}

DRAFT ARTICLE CONTENT:
{$articleContent}

GROUND TRUTH SOURCE DATA:
{$sourceJson}

Return your audit strictly as a JSON object with this exact schema:
{
  "overall_score": 95,
  "factual_accuracy_score": 96,
  "dates_accuracy_score": 98,
  "source_consistency_score": 95,
  "hallucination_risk": "low | medium | high",
  "copied_risk": "low | medium | high",
  "recommendation": "pass | needs_review | reject",
  "flagged_issues": [
    {
      "claim": "The exact sentence or claim from the article",
      "issue_type": "date_discrepancy | unsupported_claim | authority_confusion | phrasing_risk",
      "severity": "minor | critical",
      "correction_advice": "Specific correction required"
    }
  ],
  "summary": "Concise summary of the audit evaluation"
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'fact_checking',
            'article_id' => $article['id'] ?? null,
            'system_instruction' => $systemInstruction,
            'temperature' => 0.1
        ]);

        $data = $response['data'];

        // Save check result into article_checks database table if article ID is provided
        if (!empty($article['id'])) {
            try {
                Database::insert('article_checks', [
                    'article_id' => (int)$article['id'],
                    'check_type' => 'factual_verification',
                    'score' => (int)($data['overall_score'] ?? 0),
                    'notes' => json_encode([
                        'recommendation' => $data['recommendation'] ?? 'review',
                        'hallucination_risk' => $data['hallucination_risk'] ?? 'low',
                        'flagged_count' => count($data['flagged_issues'] ?? []),
                        'summary' => $data['summary'] ?? ''
                    ], JSON_UNESCAPED_UNICODE),
                    'checker' => 'ai',
                    'checked_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Throwable $e) {
                Logger::error('Failed to log article_check record: ' . $e->getMessage());
            }
        }

        return $data;
    }
}
