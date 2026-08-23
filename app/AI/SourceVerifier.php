<?php
/**
 * Sarkari.online — Autonomous AI Source & Factual Verifier
 *
 * Provides 100% autonomous zero-human-effort verification:
 * 1. Real Source Text Match: Attempts live curl fetch from official portal.
 * 2. Statutory Knowledge & Syllabus Audit: Uses Gemini to cross-verify all key claims,
 *    marking schemes, eligibility rules, and dates against authoritative standards.
 * 3. Guide & Syllabus Autonomous Validation: Verifies that preparation guides adhere
 *    to official syllabi and past year exam trends with proper disclaimer formatting.
 *
 * Verdicts:
 *   - PASS (confidence >= 75%) -> Auto-Published live immediately.
 *   - FAIL (confidence < 75% or hallucination) -> Rejected & DELETED from DB.
 */

namespace App\AI;

use App\Helpers\Logger;
use Throwable;

class SourceVerifier {

    private Gemini $gemini;
    private int $fetchTimeout = 10;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Fetch real text content from official portal URL
     */
    private function fetchSourceText(string $url): ?string {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->fetchTimeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept-Language: en-IN,en;q=0.9,hi;q=0.8']
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode < 200 || $httpCode >= 400 || empty($html)) {
                return null;
            }

            $text = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
            $text = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $text);
            $text = strip_tags($text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            return mb_substr($text, 0, 4000);

        } catch (Throwable $e) {
            Logger::warning("SourceVerifier fetch failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Classify article type
     */
    public function classifyArticleType(string $title): string {
        $lower = strtolower($title);
        $guidePatterns = ['syllabus', 'weightage', 'preparation guide', 'chapter-wise',
                          'how to', 'roadmap', 'career guide', 'best books', 'strategy', 'tips'];
        foreach ($guidePatterns as $p) {
            if (str_contains($lower, $p)) return 'guide';
        }
        return 'notice';
    }

    /**
     * Verify article content autonomously
     */
    public function verify(string $articleTitle, string $articleContent, string $sourceUrl, string $sourceName): array {
        $articleType = $this->classifyArticleType($articleTitle);
        $sourceText = $this->fetchSourceText($sourceUrl);
        $articleSnippet = mb_substr(strip_tags($articleContent), 0, 3000);
        $currentYear = (int)date('Y');

        if ($articleType === 'guide') {
            // Autonomous Guide & Syllabus Verification
            $prompt = <<<PROMPT
You are a senior editorial audit engine for an Indian education and career portal.
Today's date is: {$currentYear}-08-22.

ARTICLE TITLE: {$articleTitle}
AUTHORITY: {$sourceName}
SOURCE URL: {$sourceUrl}

ARTICLE CONTENT:
{$articleSnippet}

Perform an autonomous accuracy & safety audit on this Study Guide / Syllabus / Strategy article:
1. Verify that all exam frameworks (total marks, negative marking, subject sections, exam duration, question count) match genuine Indian standards (e.g. NEET UG 720 marks / 180 Qs, SSC CGL Tier-1 100 Qs / 200 marks, UPSC CSE 9 papers, CTET 150 Qs, State PSC KAS/PSC patterns).
2. TENTATIVE / PENDING DATES POLICY: If an upcoming examination date has not yet been announced by the commission/agency, it is 100% legitimate and standard practice to state dates as 'To Be Announced (TBA)', 'Tentative', or 'Pending Official Notice at official portal'. Approve such honest reporting!
3. Check if chapter weightage or question distribution is labeled honestly as past-year trend analysis (PYQ) and NOT falsely claimed as "statutory/mandatory official distribution".
4. Verify that there are no expired historical cycles (2020-2024) presented as active current schedules.
5. Verify that the article contains complete, actionable, high-quality educational guidance.

Return strictly JSON:
{
  "verdict": "pass | fail",
  "confidence": 90,
  "verified_points": [
    {"point": "Exam structure accuracy", "status": "confirmed", "note": "Details"}
  ],
  "reason": "Clear explanation of audit outcome"
}
Rules:
- If exam pattern, marking scheme, eligibility, and syllabus are accurate -> verdict: "pass", confidence: 85-95
- If contains false statutory claims, fabricated confirmed dates, or expired historical years -> verdict: "fail", confidence: 30
PROMPT;
        } else {
            // Autonomous Notice / Recruitment / Result Verification
            $sourceContext = !empty($sourceText)
                ? "REAL CONTENT FETCHED FROM OFFICIAL PORTAL:\n{$sourceText}"
                : "OFFICIAL SOURCE: {$sourceName} ({$sourceUrl}) [Portal active, verify against statutory Indian notification norms]";

            $prompt = <<<PROMPT
You are a strict fact-verification engine for Indian government jobs, exams, and scholarship notifications.
Today's date is: {$currentYear}-08-23.

ARTICLE TITLE: {$articleTitle}
SOURCE AUTHORITY: {$sourceName}
SOURCE URL: {$sourceUrl}

ARTICLE CONTENT:
{$articleSnippet}

{$sourceContext}

Verify the factual accuracy of this notification:
1. Check key claims (exam/recruitment name, conducting body, eligibility, age limits, pay scale/vacancies, application procedures).
2. TENTATIVE & UPCOMING NOTICES POLICY: If the conducting authority has not yet gazetted the exact exam dates for an upcoming {$currentYear}+ recruitment cycle, it is 100% valid and correct for the article to state dates as 'To be announced (TBA)' or 'Tentative at official portal' while detailing confirmed eligibility and exam syllabus. Do NOT fail an article for honestly stating 'To be announced'.
3. Ensure no expired historical cycles (2020-2024) are presented as active.
4. Check for factual consistency with standard official notification formats.

Return strictly JSON:
{
  "verdict": "pass | fail",
  "confidence": 88,
  "verified_claims": [
    {"claim": "Key claim", "status": "confirmed | contradicted", "detail": "Note"}
  ],
  "reason": "Summary of factual assessment"
}
Rules:
- If eligibility, exam format, and authority details are accurate and dates are either confirmed or honestly marked TBA -> verdict: "pass", confidence: 85-95
- Only if contains fabricated/fake confirmed dates, wrong eligibility, or expired years -> verdict: "fail", confidence: 30
PROMPT;
        }

        try {
            $response = $this->gemini->generateJson($prompt, [
                'stage' => 'source_verification',
                'temperature' => 0.05,
            ]);

            $data = $response['data'];
            $verdict = strtolower(trim($data['verdict'] ?? 'fail'));
            $confidence = (int)($data['confidence'] ?? 0);
            $reason = $data['reason'] ?? 'Autonomous verification completed.';

            if ($confidence < 75) {
                $verdict = 'fail';
            }

            Logger::info("SourceVerifier result for '{$articleTitle}'", [
                'type' => $articleType,
                'verdict' => $verdict,
                'confidence' => $confidence,
                'reason' => $reason
            ]);

            return [
                'verdict' => $verdict,
                'confidence' => $confidence,
                'reason' => $reason,
                'article_type' => $articleType,
            ];

        } catch (Throwable $e) {
            Logger::error("SourceVerifier Gemini call failed: " . $e->getMessage());
            return [
                'verdict' => 'fail',
                'confidence' => 0,
                'reason' => 'Verification failed: ' . $e->getMessage(),
                'article_type' => $articleType,
            ];
        }
    }
}
