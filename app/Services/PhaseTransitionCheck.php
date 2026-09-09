<?php
declare(strict_types=1);
/**
 * PhaseTransitionCheck — Phase B
 * Uses Grounded Gemini to detect real exam lifecycle phase and update exam_cycles row.
 * Rules: monotonic forward-only phase, TBA/Awaited FORBIDDEN in LLM output, 429 retry.
 */

namespace App\Services;

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class PhaseTransitionCheck
{
    private Gemini $gemini;

    private const PHASE_ORDER = [
        'ANNUAL_CALENDAR_ONLY'   => 0,
        'NOTIFICATION_RELEASED'  => 1,
        'APPLICATION_OPEN'       => 2,
        'APPLICATION_CORRECTION' => 3,
        'APPLICATION_CLOSED'     => 4,
        'ADMIT_CARD_AWAITED'     => 5,
        'ADMIT_CARD_RELEASED'    => 6,
        'EXAM_SCHEDULED'         => 7,
        'EXAM_CONDUCTED'         => 8,
        'ANSWER_KEY_OBJECTION'   => 9,
        'FINAL_KEY_RELEASED'     => 10,
        'RESULT_DECLARED'        => 11,
        'PET_DV_STAGE'           => 12,
        'FINAL_SELECTION'        => 13,
        'CYCLE_CLOSED'           => 14,
    ];

    private const PHASE_DESCRIPTIONS = [
        'ANNUAL_CALENDAR_ONLY'   => 'Only annual exam calendar announced; no official notification PDF yet',
        'NOTIFICATION_RELEASED'  => 'Official recruitment notification PDF published on authority portal',
        'APPLICATION_OPEN'       => 'Online application form currently accepting submissions',
        'APPLICATION_CORRECTION' => 'Application correction window is open',
        'APPLICATION_CLOSED'     => 'Application form submission window has closed',
        'ADMIT_CARD_AWAITED'     => 'Exam date confirmed but admit card not yet released',
        'ADMIT_CARD_RELEASED'    => 'Admit card / hall ticket / city intimation slip available for download',
        'EXAM_SCHEDULED'         => 'Exam date announced and approaching (exam not yet conducted)',
        'EXAM_CONDUCTED'         => 'Exam conducted; result not yet declared',
        'ANSWER_KEY_OBJECTION'   => 'Provisional answer key released; objection window is open',
        'FINAL_KEY_RELEASED'     => 'Final answer key released after objection processing',
        'RESULT_DECLARED'        => 'Final result / merit list published',
        'PET_DV_STAGE'           => 'Physical Efficiency Test / Document Verification stage ongoing',
        'FINAL_SELECTION'        => 'Final selection list / appointment letters being issued',
        'CYCLE_CLOSED'           => 'This recruitment cycle is fully closed',
    ];

    private const FORBIDDEN_PATTERNS = [
        '/\bTBA\b/i',
        '/\bTo Be Announced\b/i',
        '/\bAwaited\b/i',
        '/\bComing Soon\b/i',
        '/\bNot Announced\b/i',
        '/\bWill be announced\b/i',
        '/\bYet to be released\b/i',
        '/\bExpected Soon\b/i',
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Detect real current phase via Grounded Gemini and update exam_cycles row.
     * @param  array $cycle  One exam_cycles DB row
     * @return array         Updated row (or original if detection failed)
     */
    public function check(array $cycle): array
    {
        $cycleId      = (int)$cycle['id'];
        $currentPhase = $cycle['current_phase'] ?? 'ANNUAL_CALENDAR_ONLY';
        $currentIdx   = self::PHASE_ORDER[$currentPhase] ?? 0;

        $forwardPhases = array_filter(
            self::PHASE_ORDER,
            fn(int $idx) => $idx >= $currentIdx,
            ARRAY_FILTER_USE_BOTH
        );

        $phaseListText = '';
        foreach ($forwardPhases as $phaseName => $idx) {
            $desc = self::PHASE_DESCRIPTIONS[$phaseName] ?? '';
            $phaseListText .= "  - {$phaseName}: {$desc}\n";
        }

        $examLabel  = trim(($cycle['authority_code'] ?? '') . ' ' . ($cycle['exam_name'] ?? '') . ' ' . ($cycle['cycle_year'] ?? ''));
        $cycleIdStr = !empty($cycle['cycle_identifier']) ? " ({$cycle['cycle_identifier']})" : '';
        $today      = date('d F Y');
        $curDesc    = self::PHASE_DESCRIPTIONS[$currentPhase] ?? '';

        $prompt = "You are a fact-verifier for Sarkari.online. Today: {$today}.\n\n"
            . "EXAM: {$examLabel}{$cycleIdStr}\n"
            . "CURRENT PHASE: {$currentPhase} ({$curDesc})\n\n"
            . "Using Google Search, find the ACTUAL CURRENT phase of this exam today.\n\n"
            . "ALLOWED PHASES (forward-only from {$currentPhase}):\n{$phaseListText}\n"
            . "STRICT RULES:\n"
            . "1. Only return facts verifiable from official .gov.in/.nic.in sources.\n"
            . "2. For any unknown fact return null. NEVER write TBA, Awaited, Coming Soon, "
            . "To Be Announced, Not Announced, Expected Soon, Will be announced. "
            . "These placeholder strings are ABSOLUTELY FORBIDDEN.\n"
            . "3. detected_phase MUST be one of the allowed phase names above.\n"
            . "4. If unsure, keep phase as {$currentPhase}.\n"
            . "5. evidence_url must be a real .gov.in/.nic.in URL if confidence=VERIFIED.\n\n"
            . 'Return ONLY JSON: {"detected_phase":"PHASE_NAME","confidence":"VERIFIED or INFERRED",'
            . '"evidence_url":"url or null","next_expected_transition":"YYYY-MM-DD or null",'
            . '"facts":{"notification_date":"YYYY-MM-DD or null","application_start":"YYYY-MM-DD or null",'
            . '"application_end":"YYYY-MM-DD or null","correction_window_start":"YYYY-MM-DD or null",'
            . '"correction_window_end":"YYYY-MM-DD or null","admit_card_date":"YYYY-MM-DD or null",'
            . '"exam_dates":["YYYY-MM-DD"] or null,"answer_key_date":"YYYY-MM-DD or null",'
            . '"objection_end":"YYYY-MM-DD or null","result_date":"YYYY-MM-DD or null",'
            . '"vacancies":integer or null,"application_fee_general":integer or null,'
            . '"application_fee_sc_st":integer or null,"age_min":integer or null,"age_max":integer or null}}';

        $response = $this->callGeminiWithRetry($prompt);
        if ($response === null) {
            Logger::warning("PhaseTransitionCheck: Gemini failed for cycle #{$cycleId} — phase unchanged");
            return $cycle;
        }

        $validated = $this->validateResponse($response, $currentIdx);
        if ($validated === null) {
            Logger::warning("PhaseTransitionCheck: Invalid response for cycle #{$cycleId} — phase unchanged");
            return $cycle;
        }

        $this->updateCycle($cycleId, $validated);
        $updated = Database::fetchOne("SELECT * FROM exam_cycles WHERE id = :id LIMIT 1", ['id' => $cycleId]);
        Logger::info("PhaseTransitionCheck: cycle #{$cycleId} {$currentPhase} -> {$validated['detected_phase']} ({$validated['confidence']})");
        return $updated ?: $cycle;
    }

    private function callGeminiWithRetry(string $prompt): ?array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $this->gemini->generateJson($prompt, [
                    'stage'       => 'phase_transition_check',
                    'temperature' => 0.0,
                    'tools'       => ['googleSearch' => []],
                ]);
                return $response['data'] ?? null;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if ((str_contains($msg, '429') || str_contains($msg, 'quota')) && $attempt < 2) {
                    Logger::warning('PhaseTransitionCheck: Rate limit — sleeping 60s');
                    sleep(60);
                    continue;
                }
                Logger::error("PhaseTransitionCheck Gemini error attempt {$attempt}: {$msg}");
                return null;
            }
        }
        return null;
    }

    private function validateResponse(array $data, int $currentIdx): ?array
    {
        $detectedPhase = $data['detected_phase'] ?? null;
        if (!isset(self::PHASE_ORDER[$detectedPhase])) {
            Logger::warning("PhaseTransitionCheck: Unknown phase '{$detectedPhase}'");
            return null;
        }
        if (self::PHASE_ORDER[$detectedPhase] < $currentIdx) {
            Logger::warning("PhaseTransitionCheck: Backward phase '{$detectedPhase}' rejected");
            return null;
        }
        $confidence  = in_array($data['confidence'] ?? '', ['VERIFIED', 'INFERRED'], true) ? $data['confidence'] : 'INFERRED';
        $evidenceUrl = $data['evidence_url'] ?? null;
        if ($confidence === 'VERIFIED' && empty($evidenceUrl)) $confidence = 'INFERRED';
        return [
            'detected_phase'           => $detectedPhase,
            'confidence'               => $confidence,
            'evidence_url'             => $evidenceUrl,
            'next_expected_transition' => $this->parseDate($data['next_expected_transition'] ?? null),
            'facts'                    => $this->sanitizeFacts($data['facts'] ?? []),
        ];
    }

    private function sanitizeFacts(array $facts): array
    {
        foreach ($facts as $key => $value) {
            if (is_string($value)) {
                foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                    if (preg_match($pattern, $value)) { $facts[$key] = null; break; }
                }
            } elseif (is_array($value)) {
                $cleaned = array_values(array_filter($value, function($item) {
                    if (!is_string($item)) return true;
                    foreach (self::FORBIDDEN_PATTERNS as $p) { if (preg_match($p, $item)) return false; }
                    return true;
                }));
                $facts[$key] = empty($cleaned) ? null : $cleaned;
            }
        }
        return $facts;
    }

    private function parseDate(?string $raw): ?string
    {
        if (empty($raw)) return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null;
    }

    private function updateCycle(int $cycleId, array $v): void
    {
        $factsJson = !empty($v['facts']) ? json_encode($v['facts'], JSON_UNESCAPED_UNICODE) : null;
        Database::execute(
            "UPDATE exam_cycles SET current_phase=:phase, phase_confidence=:confidence,
             phase_evidence_url=:evidence_url, phase_detected_at=NOW(),
             next_expected_transition=:next_transition, facts_json=:facts_json,
             last_verified_at=NOW(), updated_at=NOW() WHERE id=:id",
            ['phase'=>$v['detected_phase'],'confidence'=>$v['confidence'],
             'evidence_url'=>$v['evidence_url'],'next_transition'=>$v['next_expected_transition'],
             'facts_json'=>$factsJson,'id'=>$cycleId]
        );
    }
}
