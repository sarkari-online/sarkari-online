<?php
declare(strict_types=1);

namespace App\Services;

/**
 * IntentStructureMap
 * Defines the fixed editorial section skeleton for each ArticleIntent.
 * Guarantees structural diversity at the PHP layer rather than leaving it to LLM memory.
 */
final class IntentStructureMap
{
    public const SECTIONS = [
        'RECRUITMENT' => [
            'role_overview', 'vacancy_eligibility_snapshot', 'salary_and_perks',
            'selection_process_flowchart', 'document_checklist', 'editorial_verdict',
        ],
        'ADMIT_CARD' => [
            'urgency_brief', 'download_steps', 'exam_day_logistics',
            'common_errors_and_correction', 'whats_next',
        ],
        'RESULT_CUTOFF' => [
            'result_overview', 'how_to_check', 'cutoff_analysis',
            'next_stage_explainer', 'faq',
        ],
        'ANSWER_KEY' => [
            'objection_window_brief', 'how_to_challenge', 'normalization_note',
            'consequences_of_missing_window', 'whats_next',
        ],
        'SYLLABUS_CHANGE' => [
            'whats_changed', 'topic_breakdown', 'prep_strategy_impact', 'faq',
        ],
        'COUNSELLING' => [
            'preference_locking', 'document_verification_checklist',
            'seat_allotment_rounds', 'withdrawal_process',
        ],
        'CORRIGENDUM' => [
            'whats_changed_diff', 'why_it_matters', 'revised_values_table', 'action_needed',
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
            'role_overview'                   => 'Recruitment Overview & Role Mandate',
            'vacancy_eligibility_snapshot'    => 'Vacancy Details & Eligibility Snapshot',
            'salary_and_perks'                => '7th Pay Commission Salary & Pay Level',
            'selection_process_flowchart'     => 'Selection Process & Examination Stages',
            'document_checklist'              => 'Mandatory Documents Checklist',
            'editorial_verdict'               => 'Editorial Analysis & Preparation Advice',

            // ADMIT_CARD
            'urgency_brief'                   => 'Hall Ticket Release Overview',
            'download_steps'                  => 'Step-by-Step Guide to Download Admit Card',
            'exam_day_logistics'              => 'Exam Day Instructions & Entry Timings',
            'common_errors_and_correction'    => 'Discrepancy Correction & Helpline Support',
            'whats_next'                      => 'Upcoming Timeline & Post-Admit Card Milestones',

            // RESULT_CUTOFF
            'result_overview'                 => 'Examination Result & Merit List Release',
            'how_to_check'                    => 'How to Check Scorecard & Download Merit PDF',
            'cutoff_analysis'                 => 'Qualifying Cutoff Marks & Score Trends',
            'next_stage_explainer'            => 'Next Stage: Document Verification & Selection',
            'faq'                             => 'Frequently Asked Questions',

            // ANSWER_KEY
            'objection_window_brief'          => 'Provisional Answer Key Release & Overview',
            'how_to_challenge'                => 'How to Submit Challenges & Pay Objection Fees',
            'normalization_note'              => 'Score Calculation & Marking Scheme Rules',
            'consequences_of_missing_window'  => 'Critical Objection Deadlines & Ground Rules',

            // SYLLABUS_CHANGE
            'whats_changed'                   => 'Summary of Revised Examination Pattern',
            'topic_breakdown'                 => 'Subject-Wise Detailed Syllabus Breakdown',
            'prep_strategy_impact'            => 'Impact on Preparation Strategy & Time Allocation',

            // COUNSELLING
            'preference_locking'              => 'Choice Filling & Option Locking Procedures',
            'document_verification_checklist' => 'Document Verification (DV) Protocols & Certificates',
            'seat_allotment_rounds'           => 'Seat Allotment Rounds & Reporting Schedule',
            'withdrawal_process'              => 'Seat Acceptance, Float/Freeze & Exit Rules',

            // CORRIGENDUM
            'whats_changed_diff'              => 'Official Corrigendum & Key Modifications',
            'why_it_matters'                  => 'Impact of Amendments on Applicants',
            'revised_values_table'            => 'Comparison of Old vs Revised Terms',
            'action_needed'                   => 'Immediate Action Required from Candidates',
        ];

        return $titles[$sectionKey] ?? ucwords(str_replace('_', ' ', $sectionKey));
    }
}
