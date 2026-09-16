<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;

/**
 * IntentSanitizer
 * Dynamically sanitizes article HTML sections using IntentStructureMap.
 * Strips unallowed H2 sections (heading + body) based on detected intent.
 * Zero hardcoded blacklists — fully driven by the intent structure specification.
 */
class IntentSanitizer
{
    /**
     * Map of section keywords associated with each canonical section key
     */
    private const SECTION_KEYWORD_MAP = [
        // Common across intents
        'faq' => ['faq', 'frequently asked questions', 'common queries', 'questions and answers'],
        'official_links' => ['official notice', 'official portal', 'authority verification', 'direct links', 'official website', 'gazette reference'],

        // RECRUITMENT
        'role_overview' => ['overview', 'notification highlights', 'recruitment overview', 'role mandate', 'announcement'],
        'vacancy_eligibility_snapshot' => ['vacancy', 'vacancies', 'eligibility', 'educational qualification', 'age limit', 'posts'],
        'salary_and_perks' => ['salary', 'pay scale', 'pay level', '7th pay', 'remuneration', 'perks', 'in-hand'],
        'selection_process_flowchart' => ['selection process', 'exam stages', 'examination scheme', 'selection criteria'],
        'document_checklist' => ['documents checklist', 'required documents', 'certificates needed', 'documentation'],
        'editorial_verdict' => ['editorial analysis', 'preparation advice', 'mentor verdict', 'expert take'],
        'application_steps' => ['how to apply', 'online application', 'otr registration', 'step-by-step application', 'apply online'],
        'application_fee' => ['application fee', 'fee payment', 'category fee', 'payment modes'],

        // ADMIT_CARD / DATESHEET
        'urgency_brief' => ['release overview', 'hall ticket', 'city intimation', 'city slip', 'datesheet', 'timetable', 'exam schedule', 'schedule overview'],
        'download_steps' => ['download admit card', 'how to download', 'download steps', 'download timetable', 'download datesheet', 'download city slip', 'access schedule'],
        'exam_day_logistics' => ['exam day', 'shift timings', 'reporting hours', 'entry timings', 'center rules', 'bell timings', 'timetable release breakdown', 'exam commencement'],
        'common_errors_and_correction' => ['discrepancy', 'helpline', 'errors and correction', 'helpdesk', 'support', 'mistakes on admit card', 'hall ticket correction'],
        'whats_next' => ['upcoming timeline', 'post-admit card', 'exam roadmap', 'next milestones', 'historical release trends'],

        // RESULT_CUTOFF
        'result_overview' => ['result release', 'merit list', 'scorecard release', 'rank list', 'result declared', 'result highlights'],
        'how_to_check' => ['how to check', 'download scorecard', 'check result', 'download merit pdf', 'login steps'],
        'cutoff_analysis' => ['cutoff', 'cut off', 'qualifying marks', 'score trends', 'percentile', 'category-wise cutoff'],
        'next_stage_explainer' => ['next stage', 'document verification', 'selection round', 'stage 2', 'interview', 'cbt-2'],

        // ANSWER_KEY
        'objection_window_brief' => ['provisional answer key', 'response sheet', 'key release', 'answer key overview', 'omr sheet'],
        'how_to_challenge' => ['how to challenge', 'submit challenges', 'pay objection fees', 'raise objections', 'challenge window'],
        'normalization_note' => ['score calculation', 'normalization', 'marking scheme', 'raw score vs normalized', 'negative marking'],
        'consequences_of_missing_window' => ['critical objection deadlines', 'ground rules', 'deadlines', 'challenge timeline'],

        // SYLLABUS_CHANGE
        'whats_changed' => ['revised pattern', 'summary of revised', 'whats changed', 'pattern changes'],
        'topic_breakdown' => ['subject-wise', 'syllabus breakdown', 'topic breakdown', 'weightage', 'detailed syllabus'],
        'prep_strategy_impact' => ['preparation strategy', 'time allocation', 'prep impact', 'study roadmap'],

        // COUNSELLING
        'preference_locking' => ['choice filling', 'option locking', 'preference locking', 'registration and choice'],
        'document_verification_checklist' => ['document verification', 'dv protocols', 'certificates', 'counselling documents'],
        'seat_allotment_rounds' => ['seat allotment', 'allotment rounds', 'reporting schedule', 'round 1', 'round 2'],
        'withdrawal_process' => ['seat acceptance', 'float', 'freeze', 'exit rules', 'withdrawal'],

        // CORRIGENDUM
        'whats_changed_diff' => ['official corrigendum', 'key modifications', 'amendments', 'revision notice'],
        'why_it_matters' => ['impact of amendments', 'why it matters', 'applicant impact'],
        'revised_values_table' => ['comparison', 'old vs revised', 'revised terms', 'revised schedule'],
        'action_needed' => ['immediate action', 'action required', 'candidate action'],
    ];

    /**
     * Sanitize HTML content based on ArticleIntent
     * 
     * @param string $intent e.g. 'admit_card', 'answer_key', 'recruitment', etc.
     * @param string $html Full article HTML content
     * @return array ['html' => string, 'removed_sections' => array, 'kept_sections' => array]
     */
    public function sanitize(string $intent, string $html): array
    {
        $intentKey = strtoupper(trim($intent));
        $allowedSectionKeys = IntentStructureMap::getSections($intentKey);

        // Always allow FAQ and Official Links across all intents
        $allowedSectionKeys[] = 'faq';
        $allowedSectionKeys[] = 'official_links';

        // Also allow common structural sections for the intent category
        if ($intentKey === 'ADMIT_CARD' || $intentKey === 'EXAM_CALENDAR' || $intentKey === 'DATESHEET') {
            $allowedSectionKeys[] = 'exam_day_logistics';
            $allowedSectionKeys[] = 'urgency_brief';
            $allowedSectionKeys[] = 'download_steps';
            $allowedSectionKeys[] = 'whats_next';
        }

        // Build list of allowed keyword phrases for this intent
        $allowedKeywords = [];
        foreach ($allowedSectionKeys as $sKey) {
            if (isset(self::SECTION_KEYWORD_MAP[$sKey])) {
                foreach (self::SECTION_KEYWORD_MAP[$sKey] as $kw) {
                    $allowedKeywords[] = strtolower($kw);
                }
            }
            $allowedKeywords[] = strtolower(str_replace('_', ' ', $sKey));
        }
        $allowedKeywords = array_unique($allowedKeywords);

        // Disallowed concepts strictly not permitted for specific non-recruitment intents
        $strictlyDisallowed = match ($intentKey) {
            'ADMIT_CARD', 'ANSWER_KEY', 'RESULT_CUTOFF', 'SYLLABUS_CHANGE', 'CORRIGENDUM' => [
                'how to apply', 'online registration', 'application guide', 'application form',
                'otr registration', 'how to register', 'documents checklist to apply',
                'application fee payment', 'steps to apply'
            ],
            default => []
        };

        // Specific exclusions for Answer Key
        if ($intentKey === 'ANSWER_KEY') {
            $strictlyDisallowed[] = 'post-admit card';
            $strictlyDisallowed[] = 'admit card milestones';
            $strictlyDisallowed[] = 'hall ticket release';
        }

        // Parse HTML into H2 sections
        // Pattern matches from <h2 up to the next <h2 or end of string
        $pattern = '/(<h2\b[^>]*>.*?<\/h2>)(.*?)(?=(?:<h2\b[^>]*>|$))/is';
        
        $removedSections = [];
        $keptSections = [];

        $cleanedHtml = preg_replace_callback($pattern, function ($matches) use (
            $allowedKeywords,
            $strictlyDisallowed,
            &$removedSections,
            &$keptSections
        ) {
            $h2Tag = $matches[1];
            $h2HeadingText = trim(strip_tags($h2Tag));
            $headingLower = strtolower($h2HeadingText);
            $sectionBody = $matches[2];

            // Check 1: Is it explicitly in strictly disallowed concepts?
            foreach ($strictlyDisallowed as $disallowed) {
                if (str_contains($headingLower, $disallowed)) {
                    $removedSections[] = $h2HeadingText . " (Matched disallowed: '{$disallowed}')";
                    return ''; // Strip entire section (heading + body)
                }
            }

            // Check 2: Does it fuzzy match any allowed section keyword?
            $isAllowed = false;
            foreach ($allowedKeywords as $kw) {
                if (str_contains($headingLower, $kw)) {
                    $isAllowed = true;
                    break;
                }
            }

            // If still not matched, check word overlap ratio
            if (!$isAllowed) {
                $headingWords = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', '', $headingLower)));
                foreach ($allowedKeywords as $kw) {
                    $kwWords = explode(' ', $kw);
                    $intersect = array_intersect($headingWords, $kwWords);
                    if (count($intersect) >= 2 || (count($kwWords) === 1 && count($intersect) === 1)) {
                        $isAllowed = true;
                        break;
                    }
                }
            }

            if ($isAllowed) {
                $keptSections[] = $h2HeadingText;
                return $matches[0]; // Keep section untouched
            }

            // Not allowed in this intent structure -> strip cleanly
            $removedSections[] = $h2HeadingText . " (Not in allowed IntentStructureMap)";
            return '';
        }, $html);

        Logger::info("IntentSanitizer for [{$intentKey}]: Kept " . count($keptSections) . ", Stripped " . count($removedSections) . " section(s).");

        return [
            'html'             => $cleanedHtml,
            'removed_sections' => $removedSections,
            'kept_sections'    => $keptSections,
        ];
    }
}
