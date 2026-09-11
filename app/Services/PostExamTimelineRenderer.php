<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;

/**
 * PostExamTimelineRenderer — Task 3
 * 
 * Renders statistical historical timeline pages (/after-exam/{exam-slug}-{year}/).
 * Strictly template-based (PHP decides ALL phrasing — zero LLM hallucination).
 * 
 * Enforces:
 * - Fail-closed: returns NULL if sample_size < 2.
 * - Clear visual separation: "Officially Announced" vs "Historical Statistical Estimate".
 * - Prominent statutory disclaimer box.
 */
class PostExamTimelineRenderer
{
    private PhaseGapCalculator $calculator;

    public function __construct(?PhaseGapCalculator $calculator = null)
    {
        $this->calculator = $calculator ?: new PhaseGapCalculator();
    }

    /**
     * Render the complete post-exam timeline HTML block
     * 
     * @param array $cycle Row from exam_cycles
     * @return array|null Returns ['html' => string, 'meta_title' => string, 'meta_description' => string] or null if fail-closed
     */
    public function render(array $cycle): ?array
    {
        $authCode = strtoupper(trim((string)$cycle['authority_code']));
        $examName = trim((string)$cycle['exam_name']);
        $year     = (string)$cycle['cycle_year'];
        $curPhase = (string)$cycle['current_phase'];
        $portal   = $cycle['phase_evidence_url'] ?: 'https://' . strtolower($authCode) . '.gov.in';

        // Relevant post-exam milestones to analyze
        $transitions = [
            ['from' => 'EXAM_CONDUCTED', 'to' => 'ANSWER_KEY_OBJECTION', 'label' => 'Provisional Answer Key Release'],
            ['from' => 'ANSWER_KEY_OBJECTION', 'to' => 'FINAL_KEY_RELEASED', 'label' => 'Final Answer Key Publication'],
            ['from' => 'EXAM_CONDUCTED', 'to' => 'RESULT_DECLARED', 'label' => 'Final Result & Cut-off Declaration'],
        ];

        $statCards = [];
        $hasSufficientData = false;

        foreach ($transitions as $trans) {
            $stats = $this->calculator->calculate($authCode, $trans['from'], $trans['to']);
            if ($stats !== null && $stats['sample_size'] >= 2) {
                $hasSufficientData = true;
                $statCards[] = [
                    'label'       => $trans['label'],
                    'from_phase'  => $trans['from'],
                    'to_phase'    => $trans['to'],
                    'min_days'    => $stats['min_days'],
                    'max_days'    => $stats['max_days'],
                    'avg_days'    => $stats['avg_days'],
                    'sample_size' => $stats['sample_size'],
                    'evidence'    => $stats['evidence_cycles'],
                ];
            }
        }

        // ABSOLUTE GATE: If no transition meets sample_size >= 2, fail closed
        if (!$hasSufficientData) {
            Logger::info("PostExamTimelineRenderer: < 2 historical samples for {$authCode} — failing closed");
            return null;
        }

        $metaTitle = "{$examName} {$year} Post-Exam Timeline & Expected Result Gap Analysis";
        $metaDesc  = "Historical timeline analysis for {$examName} {$year} based on past verified {$authCode} cycles. Exam to answer key & result interval patterns.";

        $html = $this->buildHtml($cycle, $statCards, $portal);

        return [
            'html'             => $html,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
        ];
    }

    private function buildHtml(array $cycle, array $statCards, string $portal): string
    {
        $authCode = htmlspecialchars((string)$cycle['authority_code'], ENT_QUOTES, 'UTF-8');
        $examName = htmlspecialchars((string)$cycle['exam_name'], ENT_QUOTES, 'UTF-8');
        $year     = htmlspecialchars((string)$cycle['cycle_year'], ENT_QUOTES, 'UTF-8');
        $portalUrl= htmlspecialchars($portal, ENT_QUOTES, 'UTF-8');

        $nextTransition = $cycle['next_expected_transition'] ?? null;
        $nextTransitionHtml = '';
        if (!empty($nextTransition)) {
            $formattedNext = date('d F Y', strtotime($nextTransition));
            $nextTransitionHtml = <<<HTML
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="border-left: 5px solid #198754; background-color: #f0fdf4;">
                <div class="me-3">
                    <span style="font-size: 1.8rem;">🏛️</span>
                </div>
                <div>
                    <h6 class="alert-heading mb-1 text-success font-weight-bold">OFFICIALLY ANNOUNCED MILESTONE</h6>
                    <p class="mb-0 text-dark">Official Authority Expected Target Date: <strong>{$formattedNext}</strong> (per official schedule/circular).</p>
                </div>
            </div>
HTML;
        }

        $cardsHtml = '';
        foreach ($statCards as $sc) {
            $label = htmlspecialchars($sc['label'], ENT_QUOTES, 'UTF-8');
            $minD  = (int)$sc['min_days'];
            $maxD  = (int)$sc['max_days'];
            $avgD  = (int)$sc['avg_days'];
            $ss    = (int)$sc['sample_size'];
            $evidenceStr = htmlspecialchars(implode(', ', $sc['evidence']), ENT_QUOTES, 'UTF-8');

            $cardsHtml .= <<<HTML
            <div class="card mb-3 shadow-sm border-0" style="background-color: #f8fafc; border-left: 4px solid #0d6efd !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title text-primary mb-0 font-weight-bold">{$label}</h5>
                        <span class="badge bg-secondary">Historical Sample: {$ss} Cycles</span>
                    </div>
                    <p class="card-text text-dark fs-6 mt-3">
                        Based on <strong>{$ss} previous {$authCode} cycles</strong>, the typical gap has ranged from 
                        <strong class="text-dark">{$minD} to {$maxD} days</strong> (average: ~{$avgD} days). 
                        This is historical statistical data only — official dates for the current {$year} cycle will be announced by {$authCode} directly.
                    </p>
                    <small class="text-muted d-block mt-2">Historical Evidence Base: {$evidenceStr}</small>
                </div>
            </div>
HTML;
        }

        return <<<HTML
<div class="post-exam-timeline-module my-4">
    <div class="header-section mb-4">
        <h2 class="h3 font-weight-bold text-dark">{$examName} {$year} — Post-Exam Timeline & Historical Analysis</h2>
        <p class="text-muted">Statutory gap analysis computed across verified historical recruitment cycles of {$authCode}.</p>
    </div>

    {$nextTransitionHtml}

    <div class="alert alert-warning mb-4" role="alert" style="border-left: 5px solid #ffc107; background-color: #fffbeb;">
        <h6 class="alert-heading font-weight-bold text-dark mb-1">⚠️ STATUTORY DISCLAIMER</h6>
        <p class="mb-0 text-dark" style="font-size: 0.95rem;">
            This is an estimate based on verified past patterns, <strong>NOT an official date</strong>. Sarkari.online never predicts future exam dates. Candidates must check the official authority portal (<a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-underline font-weight-bold">{$authCode} Official Portal</a>) for confirmed statutory announcements.
        </p>
    </div>

    <div class="historical-stats-grid mb-4">
        <h4 class="h5 font-weight-bold text-secondary mb-3">HISTORICAL GAP STATISTICS (PAST OBSERVED CYCLES)</h4>
        {$cardsHtml}
    </div>
</div>
HTML;
    }
}
