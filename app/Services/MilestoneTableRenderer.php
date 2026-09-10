<?php
declare(strict_types=1);
/**
 * MilestoneTableRenderer — Phase B
 * Generates HTML milestone/dates table from exam_cycles facts_json.
 * PHP decides ALL display strings. LLM never touches table content.
 * null facts -> "Not yet announced" (NEVER "TBA" or "Awaited").
 * Past-phase rows with no data -> omitted entirely.
 */

namespace App\Services;

use App\Helpers\Logger;

class MilestoneTableRenderer
{
    // [facts_json_key, display_label, show_if_null]
    // show_if_null=true  -> show "Not yet announced" if value is null
    // show_if_null=false -> omit row entirely if null
    private const INTENT_ROWS = [
        'recruitment' => [
            ['notification_date',       'Official Notification Released',  true],
            ['application_start',       'Online Application Start Date',   true],
            ['application_end',         'Apply Online Last Date',          true],
            ['correction_window_start', 'Application Correction Window',   false],
            ['prelims_city_slip',       'Prelims Exam City Slip Release',  false],
            ['prelims_admit_card',      'Prelims Admit Card Download',     false],
            ['prelims_exam_date',       'Preliminary Examination Date',    true],
            ['prelims_result',          'Prelims Result Declaration',      false],
            ['mains_admit_card',        'Mains Admit Card Release',        false],
            ['mains_exam_date',         'Mains Examination Date',          true],
            ['final_merit_list',        'Final Selection / Merit List',    false],
            ['vacancies',               'Total Vacancies',                 false],
            ['application_fee_general', 'Application Fee (General/OBC)',  false],
            ['application_fee_sc_st',   'Application Fee (SC/ST/PH)',     false],
            ['age_min',                 'Age Limit (Minimum)',             false],
            ['age_max',                 'Age Limit (Maximum)',             false],
            ['exam_dates',              'Exam Date (Overall Schedule)',    false],
        ],
        'admit_card' => [
            ['exam_dates',              'Exam Date',                      true],
            ['admit_card_date',         'Admit Card / City Slip Release', true],
            ['notification_date',       'Notification Date',              false],
            ['vacancies',               'Total Vacancies',                false],
        ],
        'result_cutoff' => [
            ['exam_dates',              'Exam Conducted On',              false],
            ['answer_key_date',         'Answer Key Released',            false],
            ['result_date',             'Result Declared',                true],
            ['notification_date',       'Notification Date',              false],
            ['vacancies',               'Total Vacancies',                false],
        ],
        'answer_key' => [
            ['exam_dates',              'Exam Date',                      false],
            ['answer_key_date',         'Provisional Answer Key',         true],
            ['objection_end',           'Objection Window Closes',        true],
            ['result_date',             'Result (Expected)',               false],
        ],
        'counselling' => [
            ['result_date',             'Result Declared',                false],
            ['exam_dates',              'Exam Conducted On',              false],
            ['notification_date',       'Notification Date',              false],
            ['vacancies',               'Total Seats / Vacancies',        false],
        ],
        'syllabus_change' => [
            ['notification_date',       'Official Notification Released',  true],
            ['application_start',       'Application Registration Window', false],
            ['application_end',         'Application Last Date',           false],
            ['prelims_exam_date',       'Preliminary Examination Date',    true],
            ['mains_exam_date',         'Mains Examination Date',          true],
            ['exam_dates',              'Exam Date (Overall Schedule)',    false],
        ],
        'corrigendum' => [
            ['notification_date',       'Original Notification Date',     false],
            ['application_end',         'Revised Last Date',              true],
            ['exam_dates',              'Exam Date',                      true],
        ],
    ];

    /**
     * Render the milestone HTML table.
     * @param  array  $cycle   exam_cycles DB row (must have facts_json)
     * @param  string $intent  ArticleIntent value (e.g. 'recruitment', 'admit_card')
     * @return string|null     HTML string or null if < 2 rows of data
     */
    public function render(array $cycle, string $intent): ?string
    {
        $facts = [];
        if (!empty($cycle['facts_json'])) {
            $decoded = json_decode($cycle['facts_json'], true);
            if (is_array($decoded)) $facts = $decoded;
        }

        $intentKey = strtolower($intent);
        $rowDefs   = self::INTENT_ROWS[$intentKey] ?? self::INTENT_ROWS['recruitment'];

        $rows = [];
        foreach ($rowDefs as [$factKey, $label, $showIfNull]) {
            $value        = $facts[$factKey] ?? null;
            $displayValue = $this->formatValue($factKey, $value);
            if ($displayValue === null) {
                if ($showIfNull) $rows[] = ['label' => $label, 'value' => MilestoneStatusRenderer::renderBadge('Not yet announced')];
            } else {
                $rows[] = ['label' => $label, 'value' => $displayValue];
            }
        }

        if (count($rows) < 2) {
            Logger::info('MilestoneTableRenderer: < 2 rows of data — returning null (no table)');
            return null;
        }

        return $this->buildHtml($rows);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function formatValue(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        $dateKeys = ['notification_date','application_start','application_end',
                     'correction_window_start','correction_window_end','admit_card_date',
                     'answer_key_date','objection_end','result_date','prelims_exam_date',
                     'mains_exam_date','prelims_city_slip','prelims_admit_card',
                     'prelims_result','mains_admit_card','final_merit_list'];

        if (is_array($value)) {
            if (isset($value['value'])) {
                $rawVal = (string)$value['value'];
                $formatted = in_array($key, $dateKeys, true) ? ($this->formatDate($rawVal) ?: $rawVal) : $rawVal;
                $value['value'] = $formatted;
                return MilestoneStatusRenderer::renderBadge($value);
            }
            if ($key === 'exam_dates') {
                $formatted = array_filter(array_map(fn($d) => $this->formatDate((string)$d) ?: (string)$d, $value));
                if (empty($formatted)) return null;
                $range = (count($formatted) === 1) ? reset($formatted) : (reset($formatted) . ' to ' . end($formatted));
                return MilestoneStatusRenderer::renderBadge($range);
            }
        }

        if (in_array($key, $dateKeys, true)) {
            $formatted = $this->formatDate((string)$value) ?: (string)$value;
            return MilestoneStatusRenderer::renderBadge($formatted);
        }

        if (in_array($key, ['application_fee_general','application_fee_sc_st'], true)) {
            if (!is_numeric($value)) return (string)$value;
            $amt = (int)$value;
            return $amt === 0 ? 'Fee Exempt' : "\u{20B9}" . number_format($amt);
        }
        if ($key === 'vacancies') {
            if (!is_numeric($value)) return (string)$value;
            return number_format((int)$value) . ' Posts';
        }
        if (in_array($key, ['age_min','age_max'], true)) {
            if (!is_numeric($value)) return (string)$value;
            return (int)$value . ' Years';
        }
        return (string)$value;
    }

    private function formatDate(string $raw): ?string
    {
        if (empty($raw)) return null;
        $ts = strtotime($raw);
        if ($ts === false || $ts === -1) return null;
        return date('d F Y', $ts); // e.g. "30 January 2026"
    }

    private function buildHtml(array $rows): string
    {
        $tbody = '';
        foreach ($rows as $row) {
            $l = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
            $v = $row['value'];
            $tbody .= "<tr><td><strong>{$l}</strong></td><td>{$v}</td></tr>\n";
        }
        return "<div class=\"table-responsive\">\n"
            . "<table class=\"table table-bordered table-striped\">\n"
            . "<thead class=\"table-dark\"><tr><th>Event / Statutory Milestone</th><th>Official Date / Details</th></tr></thead>\n"
            . "<tbody>\n{$tbody}</tbody>\n</table>\n</div>";
    }
}
