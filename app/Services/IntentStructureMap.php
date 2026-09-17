<?php
declare(strict_types=1);

namespace App\Services;

/**
 * IntentStructureMap
 * Defines the editorial section skeleton for each ArticleIntent.
 * Eliminates generic mandatory boilerplate sections.
 */
final class IntentStructureMap
{
    public const SECTIONS = [
        'RECRUITMENT' => [
            'role_overview', 'vacancy_eligibility_snapshot', 'salary_and_perks',
            'selection_process_flowchart', 'application_procedure', 'faq',
        ],
        'ADMIT_CARD' => [
            'admit_release_overview', 'city_intimation_distinction', 'exam_schedule_shifts',
            'regional_portals', 'faq',
        ],
        'RESULT_CUTOFF' => [
            'result_overview', 'scorecard_links', 'cutoff_analysis',
            'tie_breaking_criteria', 'next_stage_explainer', 'faq',
        ],
        'ANSWER_KEY' => [
            'objection_window_brief', 'response_sheet_links', 'challenge_fee_structure',
            'objection_procedure', 'estimated_score_formula', 'faq',
        ],
        'SYLLABUS_CHANGE' => [
            'whats_changed', 'pattern_comparison_table', 'topic_breakdown', 'faq',
        ],
        'COUNSELLING' => [
            'preference_locking', 'seat_allotment_rounds', 'seat_acceptance_rules',
            'document_verification_checklist', 'faq',
        ],
        'CORRIGENDUM' => [
            'corrigendum_overview', 'revised_schedule_matrix', 'revision_reason', 'action_needed', 'faq',
        ],
    ];

    public static function getSections(string $intent): array
    {
        $key = strtoupper(trim($intent));
        return self::SECTIONS[$key] ?? self::SECTIONS['RECRUITMENT'];
    }

    public static function getSectionTitle(string $sectionKey): string
    {
        $titles = [
            // RECRUITMENT
            'role_overview'                   => 'Recruitment Overview & Notification Details',
            'vacancy_eligibility_snapshot'    => 'Vacancy Details & Eligibility Snapshot',
            'salary_and_perks'                => '7th Pay Commission Pay Matrix & Salary',
            'selection_process_flowchart'     => 'Selection Process & Examination Stages',
            'application_procedure'           => 'Online Application & Registration Procedure',

            // ADMIT_CARD
            'admit_release_overview'          => 'Admit Card Availability & Status',
            'city_intimation_distinction'     => 'City Intimation Slip vs e-Call Letter',
            'exam_schedule_shifts'            => 'Exam Schedule & Shift Timings',
            'regional_portals'                => 'Regional Official Portals',

            // RESULT_CUTOFF
            'result_overview'                 => 'Result Declaration & Scorecard Availability',
            'scorecard_links'                 => 'Direct Scorecard & Merit List Links',
            'cutoff_analysis'                 => 'Category-Wise Qualifying Cutoff Marks',
            'tie_breaking_criteria'           => 'Tie-Breaking & Normalization Criteria',
            'next_stage_explainer'            => 'Next Stage Roadmap for Qualified Candidates',

            // ANSWER_KEY
            'objection_window_brief'          => 'Provisional Answer Key Release & Challenge Window',
            'response_sheet_links'            => 'Direct Response Sheet & Question Paper Links',
            'challenge_fee_structure'         => 'Challenge Fee Structure & Representation Guidelines',
            'objection_procedure'             => 'Procedure to Submit Objections Online',
            'estimated_score_formula'         => 'Score Calculation & Marking Scheme Rules',

            // SYLLABUS_CHANGE
            'whats_changed'                   => 'Summary of Revised Examination Pattern',
            'pattern_comparison_table'        => 'Old vs New Examination Pattern Comparison',
            'topic_breakdown'                 => 'Subject-Wise Detailed Syllabus Breakdown',

            // COUNSELLING
            'preference_locking'              => 'Counselling Schedule & Choice Filling',
            'seat_allotment_rounds'           => 'Seat Allotment Rounds & Cutoff Ranks',
            'seat_acceptance_rules'           => 'Seat Acceptance: Freeze, Float & Slide Options',
            'document_verification_checklist' => 'Mandatory Verification Documents Checklist',

            // CORRIGENDUM
            'corrigendum_overview'            => 'Official Corrigendum & Key Amendments',
            'revised_schedule_matrix'         => 'Original vs Revised Schedule Comparison',
            'revision_reason'                 => 'Reason for Revision & Scope of Affected Candidates',
            'action_needed'                   => 'Immediate Action Required from Candidates',

            // COMMON
            'faq'                             => 'Frequently Asked Questions',
        ];

        return $titles[$sectionKey] ?? ucwords(str_replace('_', ' ', $sectionKey));
    }
}
