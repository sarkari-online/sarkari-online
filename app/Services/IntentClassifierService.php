<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use App\Services\ArticleIntent;
use Throwable;

final class IntentClassifierService
{
    private Gemini $gemini;

    // Weighted keyword map based on Indian exam search demand and user intent
    private const KEYWORD_MAP = [
        ArticleIntent::ADMIT_CARD->value => [
            'admit card' => 12, 'hall ticket' => 12, 'city slip' => 12, 'city intimation' => 12,
            'e-call letter' => 11, 'call letter' => 10, 'exam date' => 6, 'shift timing' => 8,
            'download link' => 4, 'exam city' => 9
        ],
        ArticleIntent::RESULT_CUTOFF->value => [
            'result' => 10, 'cut off' => 12, 'cutoff' => 12, 'merit list' => 11,
            'scorecard' => 10, 'rank list' => 9, 'marksheet' => 8, 'qualifying marks' => 8
        ],
        ArticleIntent::RECRUITMENT->value => [
            'recruitment' => 10, 'notification' => 8, 'apply online' => 12, 'vacancy' => 10,
            'vacancies' => 10, 'bharti' => 9, 'online form' => 11, 'registration open' => 10,
            'eligibility criteria' => 7, 'age limit' => 7
        ],
        ArticleIntent::ANSWER_KEY->value => [
            'answer key' => 12, 'response sheet' => 11, 'objection window' => 11,
            'question paper' => 8, 'challenge' => 7, 'omr sheet' => 9, 'provisional key' => 10
        ],
        ArticleIntent::COUNSELLING->value => [
            'counselling' => 12, 'counseling' => 12, 'seat allotment' => 12, 'choice filling' => 11,
            'option entry' => 11, 'spot round' => 10, 'cap round' => 11, 'csas' => 10, 'josaa' => 11, 'mcc' => 10
        ],
        ArticleIntent::SYLLABUS_CHANGE->value => [
            'revised syllabus' => 12, 'exam pattern' => 11, 'scheme changed' => 11,
            'new syllabus' => 10, 'marking scheme' => 9, 'negative marking' => 8
        ],
        ArticleIntent::CORRIGENDUM->value => [
            'corrigendum' => 12, 'date extended' => 11, 'extended' => 8, 'postponed' => 10,
            'postponement' => 10, 'revised schedule' => 10, 'rescheduled' => 10, 'cancellation' => 9
        ],
    ];

    private const AMBIGUITY_MARGIN = 4; // If top-2 scores differ by less than this, escalate to Gemini tie-breaker

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Classify reader intent with deterministic Tier 1 and fallback to LLM Tier 2
     */
    public function classify(string $headline, string $rawDispatchExcerpt = ''): ArticleIntent
    {
        $text = mb_strtolower($headline . ' ' . mb_substr($rawDispatchExcerpt, 0, 400));
        $scores = [];

        foreach (self::KEYWORD_MAP as $intent => $keywords) {
            $scores[$intent] = 0;
            foreach ($keywords as $kw => $weight) {
                if (str_contains($text, $kw)) {
                    $scores[$intent] += $weight;
                }
            }
        }

        arsort($scores);
        $top = array_key_first($scores);
        $topScore = reset($scores);
        next($scores);
        $secondScore = current($scores) ?: 0;

        // Override Rule: Corrigendum Co-occurrence Guard
        // A corrigendum is definitionally about another intent's subject matter (e.g. "Admit card date extended"),
        // so it will almost always co-occur with that intent's keywords. Never let raw arithmetic out-score it!
        $corrigendumScore = $scores[ArticleIntent::CORRIGENDUM->value] ?? 0;
        if ($corrigendumScore >= 8 && $top !== ArticleIntent::CORRIGENDUM->value) {
            Logger::info("IntentClassifier: Corrigendum co-occurrence detected (Score: {$corrigendumScore}). Forcing Tier-2 LLM evaluation for '{$headline}'");
            return $this->llmTieBreak($headline, $rawDispatchExcerpt);
        }

        // Decisive match
        if ($topScore > 0 && ($topScore - $secondScore) >= self::AMBIGUITY_MARGIN) {
            Logger::info("IntentClassifier: Deterministic match '{$top}' (Score: {$topScore} vs {$secondScore}) for '{$headline}'");
            return ArticleIntent::from($top);
        }

        // Ambiguous or zero-score -> LLM Tie-Breaker
        Logger::info("IntentClassifier: Escalating to Tier 2 LLM Tie-Break for '{$headline}' (Top: {$top}={$topScore}, 2nd={$secondScore})");
        return $this->llmTieBreak($headline, $rawDispatchExcerpt);
    }

    private function llmTieBreak(string $headline, string $excerpt): ArticleIntent
    {
        $prompt = <<<PROMPT
Classify the PRIMARY reader intent of this Indian government exam/recruitment dispatch.
HEADLINE: {$headline}
SNIPPET: {$excerpt}

CRITICAL RULES:
- If a dispatch announces a date extension, postponement, or corrigendum that ALSO mentions an admit card, result, or application form, classify as "corrigendum" — the revision is the primary news.
- Never invent an intent outside the allowed list.

ALLOWED INTENT VALUES:
- admit_card (Exam date, City intimation slip, Hall ticket, e-call letter, Shift timings)
- result_cutoff (Result declaration, Scorecard download, Cutoff marks, Merit list)
- recruitment (New job notification, Vacancies announced, Apply online, Eligibility, Registration)
- answer_key (Provisional/Final answer keys, Response sheets, Objection filing)
- counselling (College seat allotment, CAP rounds, Choice filling, Spot admissions)
- syllabus_change (Revised exam pattern, New syllabus, Scheme update)
- corrigendum (Date extension, Postponement notice, Cancellation, Official error correction)

Return strictly JSON:
{
  "intent": "admit_card | result_cutoff | recruitment | answer_key | counselling | syllabus_change | corrigendum",
  "confidence": "high | medium | low"
}
PROMPT;

        try {
            $response = $this->gemini->generateJson($prompt, [
                'stage' => 'intent_classification',
                'temperature' => 0.0
            ]);

            $data = $response['data'];
            $detected = $data['intent'] ?? null;

            if (!empty($detected)) {
                $intent = ArticleIntent::tryFrom($detected);
                if ($intent) {
                    return $intent;
                }
            }
        } catch (Throwable $e) {
            Logger::warning("IntentClassifier LLM tie-breaker failed: " . $e->getMessage());
        }

        // Safety Architecture: NEVER guess or silently fall back to RECRUITMENT!
        // Throw an UnresolvedIntentException so PipelineService routes to the human review queue.
        Logger::error("IntentClassifier: Could not resolve intent for '{$headline}' — routing to editorial review");
        throw new UnresolvedIntentException("Unresolved intent for headline: '{$headline}'");
    }
}

class UnresolvedIntentException extends \Exception {}

