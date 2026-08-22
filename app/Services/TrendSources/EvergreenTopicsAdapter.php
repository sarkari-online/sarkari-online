<?php
/**
 * EduPulse - High-Search Evergreen & Authority Topics Catalog
 * Provides verified, fact-grounded syllabus, cutoff, eligibility, and scholarship topics
 * covering all major national exams and recruitment cycles.
 */

namespace App\Services\TrendSources;

use App\Services\TrendService;
use App\Helpers\Logger;
use Throwable;

class EvergreenTopicsAdapter implements TrendSourceInterface {

    public function getSourceId(): string {
        return 'evergreen_guides';
    }

    public function getSourceName(): string {
        return 'High-Search Evergreen Guides';
    }

    /**
     * Comprehensive catalog of top-searched Indian education, exam, and career topics
     */
    private function getCatalog(): array {
        return [
            // --- SSC Exams ---
            [
                'keyword' => 'SSC CHSL 2026 Tier 1 Exam Pattern, Syllabus Breakdown & Subject-Wise Marks',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 95,
                'snippet' => 'Official examination syllabus, 100-question tier-1 structure, and topic weightage for SSC CHSL 2026.'
            ],
            [
                'keyword' => 'SSC MTS 2026 Havaldar Notification: Eligibility, Age Limits & Selection Process',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 94,
                'snippet' => 'Multi-Tasking Staff and Havaldar recruitment guidelines, computer-based exam format, and PET/PST criteria.'
            ],
            [
                'keyword' => 'SSC GD Constable 2026: State-Wise Vacancies, Physical Standards & Exam Blueprint',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 96,
                'snippet' => 'CAPF Constable recruitment eligibility, height-chest physical standards, and CBT syllabus for SSC GD 2026.'
            ],

            // --- Railway Exams ---
            [
                'keyword' => 'RRB Assistant Loco Pilot (ALP) 2026: CBT 1 & CBT 2 Exam Pattern and Psycho Test Guide',
                'source' => 'Railway Recruitment Control Board (RRB)',
                'url' => 'https://rrbcdg.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 95,
                'snippet' => 'Railway ALP selection stages, technical trade syllabus for CBT 2 Part B, and qualifying marks breakdown.'
            ],
            [
                'keyword' => 'RRB Group D 2026 Level 1 Posts: Physical Efficiency Test (PET) & Selection Rules',
                'source' => 'Railway Recruitment Control Board (RRB)',
                'url' => 'https://rrbcdg.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 96,
                'snippet' => 'RRC Level-1 recruitment guidelines, 100-mark CBT syllabus, and gender-specific PET run-weight standards.'
            ],
            [
                'keyword' => 'RRB Junior Engineer (JE) 2026: Technical Syllabus & Stage-Wise Selection Process',
                'source' => 'Railway Recruitment Control Board (RRB)',
                'url' => 'https://rrbcdg.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 94,
                'snippet' => 'Railway engineering recruitment guidelines for Civil, Electrical, and Mechanical diplomas and degrees.'
            ],

            // --- Banking Exams ---
            [
                'keyword' => 'SBI PO 2026 Prelims & Mains Syllabus, Sectional Cutoff Rules and Group Exercise Pattern',
                'source' => 'State Bank of India (SBI)',
                'url' => 'https://sbi.co.in/web/careers',
                'category_hint' => 'government-jobs',
                'trend_score' => 96,
                'snippet' => 'State Bank of India probationary officer selection blueprint, descriptive test format, and interview scoring.'
            ],
            [
                'keyword' => 'IBPS Clerk 2026 State-Wise Vacancies, Prelims & Mains Pattern, and Local Language Test',
                'source' => 'Institute of Banking Personnel Selection (IBPS)',
                'url' => 'https://ibps.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 95,
                'snippet' => 'Common recruitment process for customer service associates across participating public sector banks.'
            ],
            [
                'keyword' => 'RBI Grade B 2026 Officer Notification: Phase 1 & Phase 2 Syllabus and Eligibility Criteria',
                'source' => 'Reserve Bank of India (RBI)',
                'url' => 'https://opportunities.rbi.org.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 95,
                'snippet' => 'Central bank officer recruitment pattern, economic and social issues (ESI) syllabus, and finance management.'
            ],

            // --- Defense & Police Exams ---
            [
                'keyword' => 'UPSC NDA 2026: Mathematics & GAT Paper Pattern, Negative Marking, and SSB Interview Stages',
                'source' => 'Union Public Service Commission (UPSC)',
                'url' => 'https://upsc.gov.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 96,
                'snippet' => 'National Defence Academy entrance guidelines, 900-mark written test format, and 5-day SSB interview protocol.'
            ],
            [
                'keyword' => 'UPSC CDS 2026: IMA, INA, AFA & OTA Syllabus, Eligibility and Subject Weightage',
                'source' => 'Union Public Service Commission (UPSC)',
                'url' => 'https://upsc.gov.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 95,
                'snippet' => 'Combined Defence Services examination guidelines, English and General Knowledge curriculum, and math criteria.'
            ],
            [
                'keyword' => 'AFCAT 2026 Air Force Common Admission Test: Flying & Ground Duty Branch Selection Process',
                'source' => 'Indian Air Force (IAF)',
                'url' => 'https://afcat.cdac.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 94,
                'snippet' => 'Air Force officer entrance guidelines, online exam syllabus, and AFSB testing schedule.'
            ],

            // --- National Entrance Exams ---
            [
                'keyword' => 'JEE Main 2027: Physics, Chemistry & Mathematics Chapter-Wise Weightage & NTA Syllabus',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://jeemain.nta.ac.in',
                'category_hint' => 'career-guides',
                'trend_score' => 97,
                'snippet' => 'Engineering entrance blueprint, high-yield NCERT topics, and numerical question distribution for JEE Main.'
            ],
            [
                'keyword' => 'CUET UG 2026: Section 1 Language, Domain Subjects & General Test Structure Explained',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://exams.nta.ac.in/CUET-UG/',
                'category_hint' => 'entrance-exams',
                'trend_score' => 97,
                'snippet' => 'Common University Entrance Test guidelines, domain subject mapping, and central university admission criteria.'
            ],
            [
                'keyword' => 'GATE 2027: Engineering Discipline-Wise Syllabus, General Aptitude and Virtual Calculator Rules',
                'source' => 'Graduate Aptitude Test in Engineering (GATE)',
                'url' => 'https://gate2026.iitkgp.ac.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 95,
                'snippet' => 'Postgraduate engineering entrance guidelines, PSU recruitment cutoff benchmarks, and marking scheme.'
            ],

            // --- Teaching & Eligibility ---
            [
                'keyword' => 'UGC NET 2026: Paper 1 Teaching Aptitude Syllabus, JRF Cutoff Percentiles & Validity',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://ugcnet.nta.ac.in',
                'category_hint' => 'exam-results',
                'trend_score' => 95,
                'snippet' => 'National Eligibility Test framework for Assistant Professorship and Junior Research Fellowship across 83 subjects.'
            ],

            // --- Scholarships & Welfare ---
            [
                'keyword' => 'PMSS Prime Minister Special Scholarship Scheme 2026: Eligibility & Financial Benefits',
                'source' => 'AICTE / Prime Minister Scholarship Board',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 94,
                'snippet' => 'Technical and professional degree financial grants for wards of armed forces and paramilitary personnel.'
            ],
            [
                'keyword' => 'Post Matric Scholarship for SC/ST/OBC Students 2026: Income Limits & State Portal Links',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 95,
                'snippet' => 'Government fee reimbursement guidelines, maintenance allowance slabs, and direct benefit transfer rules.'
            ],

            // --- School Boards ---
            [
                'keyword' => 'CBSE Class 10 & 12 Sample Question Papers 2027: Marking Schemes Released at cbseacademic.nic.in',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://cbseacademic.nic.in',
                'category_hint' => 'school-boards',
                'trend_score' => 96,
                'snippet' => 'Official model question papers, case-study questions, and step-wise marking schemes for board exam preparation.'
            ]
        ];
    }

    public function fetch(int $limit = 5): array {
        $results = [];

        try {
            $catalog = $this->getCatalog();

            foreach ($catalog as $item) {
                if (count($results) >= $limit) break;

                // Check if this evergreen topic already exists in trends or articles
                if (TrendService::existsAsTrend($item['keyword']) || TrendService::existsAsArticle($item['keyword'])) {
                    continue;
                }

                $results[] = [
                    'keyword' => $item['keyword'],
                    'source' => $item['source'],
                    'url' => $item['url'],
                    'trend_score' => $item['trend_score'],
                    'category_hint' => $item['category_hint'],
                    'snippet' => $item['snippet'],
                    'detected_at' => date('Y-m-d H:i:s'),
                    'raw_payload' => [
                        'source_type' => 'evergreen_official',
                        'authority' => $item['source'],
                        'url' => $item['url']
                    ]
                ];
            }
        } catch (Throwable $e) {
            Logger::warning('EvergreenTopicsAdapter fetch error: ' . $e->getMessage());
        }

        return $results;
    }
}
