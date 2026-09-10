<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\ArticleIntent;

final class FactCompletenessResult
{
    public function __construct(
        public readonly bool $isComplete,
        public readonly array $missingFacts,
        public readonly array $evaluatedFacts = []
    ) {}
}

final class FactCompletenessRules
{
    private const REQUIRED_FACTS = [
        ArticleIntent::RECRUITMENT->value => [
            'application_start_date',
            'application_deadline',
            'fee_amount_general'
        ],
        ArticleIntent::ADMIT_CARD->value => [
            'admit_card_release_date' // exam_date may legitimately be tentative
        ],
        ArticleIntent::RESULT_CUTOFF->value => [
            'result_date'
        ],
        ArticleIntent::ANSWER_KEY->value => [
            'answer_key_release_date'
        ],
        ArticleIntent::COUNSELLING->value => [
            'counselling_start_date'
        ],
        ArticleIntent::SYLLABUS_CHANGE->value => [],
        ArticleIntent::CORRIGENDUM->value => []
    ];

    public static function requiredFieldsFor(ArticleIntent $intent): array
    {
        return self::REQUIRED_FACTS[$intent->value] ?? [];
    }

    /**
     * Evaluate fact completeness for a given intent against an extracted facts package.
     */
    public static function evaluate(ArticleIntent $intent, array $facts): FactCompletenessResult
    {
        $required = self::requiredFieldsFor($intent);
        $missing = [];
        $evaluated = [];

        foreach ($required as $factType) {
            $fact = self::findFactByType($facts, $factType);
            $evaluated[$factType] = $fact;

            if (!$fact || !self::isValidFactValue($fact)) {
                $missing[] = $factType;
            }
        }

        return new FactCompletenessResult(
            isComplete: empty($missing),
            missingFacts: $missing,
            evaluatedFacts: $evaluated
        );
    }

    /**
     * Check whether fact value is substantive (not placeholder, not empty, not TBA)
     */
    private static function isValidFactValue(mixed $fact): bool
    {
        if (is_array($fact)) {
            $val = $fact['value'] ?? ($fact['date'] ?? ($fact['amount'] ?? ''));
            $confidence = $fact['source_confidence'] ?? ($fact['status'] ?? 'confirmed');
            if ($confidence === 'unavailable' || $confidence === 'Awaiting Official Circular') {
                return false;
            }
        } else {
            $val = (string)$fact;
        }

        $clean = trim(strtolower((string)$val));
        if (empty($clean)) {
            return false;
        }

        $invalidPlaceholders = [
            'to be announced',
            'tba',
            'not yet officially announced',
            'awaited',
            'dates awaited',
            'check official portal',
            'as per notification',
            'expected soon',
            'refer to official portal'
        ];

        foreach ($invalidPlaceholders as $p) {
            if ($clean === $p || str_contains($clean, $p)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find fact value by normalized fact key from various possible structures in facts package
     */
    public static function findFactByType(array $facts, string $factType): ?array
    {
        // 1. Direct associative key
        if (isset($facts[$factType])) {
            $val = $facts[$factType];
            return is_array($val) ? $val : ['value' => (string)$val, 'source_confidence' => 'confirmed'];
        }

        // 2. Inside $facts['facts'] array
        if (isset($facts['facts']) && is_array($facts['facts'])) {
            if (isset($facts['facts'][$factType])) {
                $val = $facts['facts'][$factType];
                return is_array($val) ? $val : ['value' => (string)$val, 'source_confidence' => 'confirmed'];
            }
            foreach ($facts['facts'] as $f) {
                if (is_array($f) && ($f['type'] ?? '') === $factType) {
                    return $f;
                }
            }
        }

        // 2b. Inside _exam_facts_json (ExamCycleContext from PhaseTransitionCheck)
        if (!empty($facts['_exam_facts_json'])) {
            $cycleFacts = is_array($facts['_exam_facts_json']) 
                ? $facts['_exam_facts_json'] 
                : (json_decode($facts['_exam_facts_json'], true) ?: []);
            
            $cycleMapping = [
                'result_date' => 'result_date',
                'admit_card_release_date' => 'admit_card_date',
                'application_start_date' => 'application_start',
                'application_deadline' => 'application_end',
                'fee_amount_general' => 'application_fee_general'
            ];
            $targetKey = $cycleMapping[$factType] ?? $factType;
            if (!empty($cycleFacts[$targetKey])) {
                return ['value' => (string)$cycleFacts[$targetKey], 'source_confidence' => 'verified'];
            }
        }

        // 3. Match from dates_schedule milestones
        $milestones = $facts['dates_schedule'] ?? ($facts['verified_facts']['dates_schedule'] ?? []);
        if (is_array($milestones)) {
            foreach ($milestones as $item) {
                $mName = strtolower($item['milestone'] ?? '');
                $dateVal = $item['date'] ?? '';

                switch ($factType) {
                    case 'application_start_date':
                        if (str_contains($mName, 'start') || str_contains($mName, 'opening') || str_contains($mName, 'registration start')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                    case 'application_deadline':
                        if (str_contains($mName, 'last date') || str_contains($mName, 'deadline') || str_contains($mName, 'closing') || str_contains($mName, 'end date')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                    case 'admit_card_release_date':
                        if (str_contains($mName, 'admit card') || str_contains($mName, 'hall ticket') || str_contains($mName, 'call letter')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                    case 'result_date':
                        if (str_contains($mName, 'result') || str_contains($mName, 'scorecard') || str_contains($mName, 'merit')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                    case 'answer_key_release_date':
                        if (str_contains($mName, 'answer key') || str_contains($mName, 'key challenge')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                    case 'counselling_start_date':
                        if (str_contains($mName, 'counselling') || str_contains($mName, 'seat allotment') || str_contains($mName, 'choice filling')) {
                            return ['value' => $dateVal, 'source_confidence' => ($item['status'] ?? 'confirmed')];
                        }
                        break;
                }
            }
        }

        // 4. Match fee from application_fees or fee_amount_general
        if ($factType === 'fee_amount_general') {
            $feeVal = $facts['fee_amount_general'] ?? ($facts['application_fee'] ?? ($facts['verified_facts']['application_fee'] ?? null));
            if (!empty($feeVal)) {
                return ['value' => (string)$feeVal, 'source_confidence' => 'confirmed'];
            }
        }

        return null;
    }
}
