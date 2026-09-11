<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;

/**
 * PhaseGapCalculator — Task 3 Infra
 * 
 * Computes statistical delta ranges (min/max days) between exam phases
 * strictly from historical closed cycles in the exam_cycles database.
 * 
 * ZERO GEMINI CALLS — pure SQL & mathematical date difference calculation.
 * Fail-closed: returns NULL if sample_size < 2.
 */
class PhaseGapCalculator
{
    private const MIN_SAMPLE_SIZE = 2;

    /**
     * Calculate historical gap between two phases for a given authority.
     * 
     * @param string $authorityCode e.g. 'UPSC', 'SSC', 'BSEB'
     * @param string $fromPhase     e.g. 'EXAM_CONDUCTED', 'APPLICATION_CLOSED'
     * @param string $toPhase       e.g. 'ANSWER_KEY_OBJECTION', 'RESULT_DECLARED'
     * @return array|null Returns ['min_days' => int, 'max_days' => int, 'avg_days' => int, 'sample_size' => int, 'evidence_cycles' => array] or null
     */
    public function calculate(string $authorityCode, string $fromPhase, string $toPhase): ?array
    {
        $authorityCode = strtoupper(trim($authorityCode));
        if ($authorityCode === '') {
            return null;
        }

        // 1. Check existing cached stat in authority_phase_gap_stats if recent (within 7 days)
        try {
            $cached = Database::fetchOne(
                "SELECT * FROM authority_phase_gap_stats 
                  WHERE authority_code = :auth AND from_phase = :from_p AND to_phase = :to_p 
                    AND last_calculated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  LIMIT 1",
                ['auth' => $authorityCode, 'from_p' => $fromPhase, 'to_p' => $toPhase]
            );

            if ($cached && (int)$cached['sample_size'] >= self::MIN_SAMPLE_SIZE) {
                return [
                    'min_days'        => (int)$cached['min_days_observed'],
                    'max_days'        => (int)$cached['max_days_observed'],
                    'avg_days'        => (int)round(((int)$cached['min_days_observed'] + (int)$cached['max_days_observed']) / 2),
                    'sample_size'     => (int)$cached['sample_size'],
                    'evidence_cycles' => array_filter(explode(',', (string)$cached['evidence_cycles'])),
                ];
            }
        } catch (\Throwable $e) {
            // Table might not exist yet or empty; fallback to calculating
        }

        // 2. Query all concluded/closed cycles for this authority
        $cycles = Database::fetchAll(
            "SELECT id, authority_code, exam_name, cycle_year, cycle_identifier, current_phase, facts_json, created_at, last_verified_at
               FROM exam_cycles
              WHERE authority_code = :auth
              ORDER BY cycle_year DESC, id DESC",
            ['auth' => $authorityCode]
        );

        if (count($cycles) < self::MIN_SAMPLE_SIZE) {
            return null;
        }

        $observedDeltas = [];
        $evidenceList = [];

        foreach ($cycles as $cycle) {
            $facts = [];
            if (!empty($cycle['facts_json'])) {
                $decoded = json_decode($cycle['facts_json'], true);
                if (is_array($decoded)) {
                    $facts = $decoded;
                }
            }

            $fromDate = $this->resolvePhaseDate($fromPhase, $facts);
            $toDate   = $this->resolvePhaseDate($toPhase, $facts);

            if ($fromDate !== null && $toDate !== null) {
                $fromTs = strtotime($fromDate);
                $toTs   = strtotime($toDate);

                if ($fromTs !== false && $toTs !== false && $toTs >= $fromTs) {
                    $diffDays = (int)round(($toTs - $fromTs) / 86400);
                    $observedDeltas[] = $diffDays;
                    $evidenceList[] = $cycle['cycle_identifier'] ?: ($cycle['exam_name'] . ' ' . $cycle['cycle_year']);
                }
            }
        }

        // Strict Gate: Must have at least 2 historical cycles observed
        if (count($observedDeltas) < self::MIN_SAMPLE_SIZE) {
            return null;
        }

        $minDays = min($observedDeltas);
        $maxDays = max($observedDeltas);
        $sampleSize = count($observedDeltas);
        $avgDays = (int)round(array_sum($observedDeltas) / $sampleSize);
        $evidenceStr = implode(', ', array_unique($evidenceList));

        // Persist to authority_phase_gap_stats if table exists
        try {
            Database::execute(
                "INSERT INTO authority_phase_gap_stats 
                    (authority_code, from_phase, to_phase, min_days_observed, max_days_observed, sample_size, evidence_cycles, last_calculated_at)
                 VALUES 
                    (:auth, :from_p, :to_p, :min_d, :max_d, :sample_s, :evidence, NOW())
                 ON DUPLICATE KEY UPDATE 
                    min_days_observed = VALUES(min_days_observed),
                    max_days_observed = VALUES(max_days_observed),
                    sample_size = VALUES(sample_size),
                    evidence_cycles = VALUES(evidence_cycles),
                    last_calculated_at = NOW()",
                [
                    'auth'      => $authorityCode,
                    'from_p'    => $fromPhase,
                    'to_p'      => $toPhase,
                    'min_d'     => $minDays,
                    'max_d'     => $maxDays,
                    'sample_s'  => $sampleSize,
                    'evidence'  => $evidenceStr,
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning("PhaseGapCalculator: could not cache stats: " . $e->getMessage());
        }

        return [
            'min_days'        => $minDays,
            'max_days'        => $maxDays,
            'avg_days'        => $avgDays,
            'sample_size'     => $sampleSize,
            'evidence_cycles' => array_unique($evidenceList),
        ];
    }

    /**
     * Map lifecycle phase to concrete date in facts_json
     */
    private function resolvePhaseDate(string $phase, array $facts): ?string
    {
        return match ($phase) {
            'NOTIFICATION_RELEASED'  => $facts['notification_date'] ?? null,
            'APPLICATION_OPEN'       => $facts['application_start'] ?? null,
            'APPLICATION_CLOSED'     => $facts['application_end'] ?? null,
            'ADMIT_CARD_RELEASED'    => $facts['admit_card_date'] ?? null,
            'EXAM_CONDUCTED',
            'EXAM_SCHEDULED'         => is_array($facts['exam_dates'] ?? null) ? ($facts['exam_dates'][0] ?? null) : ($facts['exam_date'] ?? null),
            'ANSWER_KEY_OBJECTION'   => $facts['answer_key_date'] ?? null,
            'FINAL_KEY_RELEASED'     => $facts['final_key_date'] ?? null,
            'RESULT_DECLARED',
            'CYCLE_CLOSED'           => $facts['result_date'] ?? null,
            default                  => null,
        };
    }
}
