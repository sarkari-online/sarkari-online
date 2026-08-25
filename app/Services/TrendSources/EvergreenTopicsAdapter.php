<?php
/**
 * EduPulse - High-Search Evergreen & Authority Topics Catalog
 * Provides verified, fact-grounded high-search topics ordered strictly by student search intent:
 * 1. Entrance Exams -> 2. Scholarships -> 3. College Updates -> 4. Career Guides -> 5. Student Technology
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
        return 'High-Search Intent Student Guides';
    }

    /**
     * Comprehensive catalog of top-searched Indian education, exam, and career topics
     * Ordered strictly by student intent priority:
     * 1. Entrance Exams -> 2. Scholarships -> 3. College Updates -> 4. Career Guides -> 5. Student Technology
     */
    private function getCatalog(): array {
        return [
            // =========================================================================
            // PRIORITY 1: ENTRANCE EXAMS (NEET UG/PG, JEE Main/Adv, CUET, GATE, CTET, AIBE)
            // =========================================================================
            [
                'keyword' => 'NEET UG 2026 Registration, Eligibility Criteria, Marking Scheme and Exam Roadmap',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://exams.nta.ac.in/NEET/',
                'category_hint' => 'entrance-exams',
                'trend_score' => 98,
                'snippet' => 'National Eligibility cum Entrance Test for undergraduate medical admissions across India.'
            ],
            [
                'keyword' => 'JEE Main 2026 Session 1 & 2: Eligibility, Exam Pattern and Official Roadmap',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://jeemain.nta.ac.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 98,
                'snippet' => 'Joint Entrance Examination for undergraduate engineering admissions at NITs, IIITs, and CFTIs.'
            ],
            [
                'keyword' => 'CUET UG 2026: Section 1 Language, Domain Subjects & Central University Admission Process',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://exams.nta.ac.in/CUET-UG/',
                'category_hint' => 'entrance-exams',
                'trend_score' => 97,
                'snippet' => 'Common University Entrance Test guidelines, domain subject selection, and central university admissions.'
            ],
            [
                'keyword' => 'GATE 2027: Engineering Discipline-Wise Syllabus, General Aptitude and Virtual Calculator Rules',
                'source' => 'Graduate Aptitude Test in Engineering (GATE)',
                'url' => 'https://gate2026.iitkgp.ac.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 96,
                'snippet' => 'Postgraduate engineering entrance guidelines, PSU recruitment cutoff benchmarks, and marking scheme.'
            ],
            [
                'keyword' => 'AIBE 2026: Complete Guide to Bar Council of India Enrollment and Examination Process',
                'source' => 'Bar Council of India (BCI)',
                'url' => 'https://allindiabarexamination.com',
                'category_hint' => 'entrance-exams',
                'trend_score' => 95,
                'snippet' => 'All India Bar Examination guidelines, certificate of practice rules, and qualifying percentiles.'
            ],
            [
                'keyword' => 'CTET 2026: Paper 1 & Paper 2 Eligibility, Qualifying Marks and Central School Criteria',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://ctet.nic.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 96,
                'snippet' => 'Central Teacher Eligibility Test certification guidelines, validity period, and minimum pass criteria.'
            ],

            // =========================================================================
            // PRIORITY 2: SCHOLARSHIPS & FINANCIAL AID (NSP, PMSSS, PM YASASVI, Post-Matric)
            // =========================================================================
            [
                'keyword' => 'National Scholarship Portal (NSP 2026-27): Fresh Registration, OTR Guide and Biometric Rules',
                'source' => 'Ministry of Electronics and Information Technology (MeitY)',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 98,
                'snippet' => 'Central sector scholarship schemes, one-time registration protocol, and direct benefit transfer guidelines.'
            ],
            [
                'keyword' => 'PM Yasasvi Scholarship Scheme 2026: Eligibility, Income Slabs and Top-Class Education Grants',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://yet.nta.ac.in',
                'category_hint' => 'scholarships',
                'trend_score' => 96,
                'snippet' => 'Scholarship scheme for OBC, EBC, and DNT students studying in designated top-class schools and colleges.'
            ],
            [
                'keyword' => 'PMSSS 2026 Prime Minister Special Scholarship Scheme: AICTE Eligibility & Direct College Allotment',
                'source' => 'All India Council for Technical Education (AICTE)',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 95,
                'snippet' => 'Engineering, medical, and general degree scholarship grants for Jammu, Kashmir, and Ladakh youth.'
            ],
            [
                'keyword' => 'Post Matric Scholarship for SC/ST/OBC Students 2026: Income Limits & State Portal Links',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 95,
                'snippet' => 'Government fee reimbursement guidelines, maintenance allowance slabs, and direct benefit transfer rules.'
            ],

            // =========================================================================
            // PRIORITY 3: COLLEGE UPDATES & ADMISSIONS (JoSAA, DU CSAS, MCC, UGC Norms)
            // =========================================================================
            [
                'keyword' => 'JoSAA 2026 Seat Allotment: Opening vs Closing Ranks, Freeze, Float, Slide Rules & Seat Matrix',
                'source' => 'Joint Seat Allocation Authority (JoSAA)',
                'url' => 'https://josaa.nic.in',
                'category_hint' => 'college-updates',
                'trend_score' => 98,
                'snippet' => 'Centralised engineering seat allocation for 23 IITs, 31 NITs, 26 IIITs, opening-closing rank analysis, and seat acceptance guidelines.'
            ],
            [
                'keyword' => 'DU CSAS 2026: Delhi University UG Seat Allocation Phases, Spot Round & Document Verification Checklist',
                'source' => 'University of Delhi',
                'url' => 'https://admission.uod.ac.in',
                'category_hint' => 'college-updates',
                'trend_score' => 97,
                'snippet' => 'Common Seat Allocation System guidelines, preference simulated ranks, acceptance deadlines, and physical document verification.'
            ],
            [
                'keyword' => 'UGC College Fee Refund Policy 2026: 100% Refund Guidelines, Seat Cancellation Deadline and AICTE Norms',
                'source' => 'University Grants Commission (UGC)',
                'url' => 'https://ugc.ac.in',
                'category_hint' => 'college-updates',
                'trend_score' => 96,
                'snippet' => 'Statutory fee refund timelines, zero deduction slabs, certificate withholding penalties, and student grievance portal.'
            ],
            [
                'keyword' => 'MCC NEET UG 2026 Counselling: Round 1 & Round 2 All India Quota Seat Matrix and Security Deposit Refund Rules',
                'source' => 'Medical Counselling Committee (MCC)',
                'url' => 'https://mcc.nic.in',
                'category_hint' => 'college-updates',
                'trend_score' => 97,
                'snippet' => 'AIQ 15% government medical seats, deemed universities, free exit rules, and security deposit forfeiture norms.'
            ],
            [
                'keyword' => 'CSAB Special Round 2026: Vacant Seats in NITs, IIITs, Eligibility and Security Deposit Refund Process',
                'source' => 'Central Seat Allocation Board (CSAB)',
                'url' => 'https://csab.nic.in',
                'category_hint' => 'college-updates',
                'trend_score' => 96,
                'snippet' => 'Special round vacant seat matrix, fresh registration guidelines, and seat acceptance fee refund structure.'
            ],
            [
                'keyword' => 'College Admission Gap Certificate 2026: Format, Stamp Paper Rules, Affidavit and Notary Guidelines',
                'source' => 'Ministry of Education',
                'url' => 'https://education.gov.in',
                'category_hint' => 'college-updates',
                'trend_score' => 95,
                'snippet' => 'Standard gap year affidavit format, non-judicial stamp paper value, notary attestation, and university reporting rules.'
            ],

            // =========================================================================
            // PRIORITY 4: CAREER GUIDES & PREPARATION BLUEPRINTS (Syllabus & Weightage)
            // =========================================================================
            [
                'keyword' => 'JEE Main 2027: Physics, Chemistry & Mathematics Chapter-Wise Weightage Analysis',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://jeemain.nta.ac.in',
                'category_hint' => 'career-guides',
                'trend_score' => 97,
                'snippet' => 'Engineering entrance blueprint, high-yield NCERT topics, and numerical question distribution for JEE Main.'
            ],
            [
                'keyword' => 'NEET UG 2026 Biology: Chapter-Wise Weightage, Botany vs Zoology & High-Yield NCERT Units',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://exams.nta.ac.in/NEET/',
                'category_hint' => 'career-guides',
                'trend_score' => 98,
                'snippet' => 'Historical 360-mark biology analysis, genetics & ecology question breakdown, and NCERT revision plan.'
            ],
            [
                'keyword' => 'SSC CGL Tier 1 Syllabus Breakdown, Subject-Wise Marks & Exam Strategy Guide',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'career-guides',
                'trend_score' => 96,
                'snippet' => 'Combined Graduate Level examination syllabus, section-wise marks distribution, and time management.'
            ],

            // =========================================================================
            // PRIORITY 5: STUDENT TECH & DIGITAL SERVICES (DigiLocker, ABC, APAAR, OTR)
            // =========================================================================
            [
                'keyword' => 'DigiLocker ABC ID Creation: Step-by-Step Guide for University & College Students',
                'source' => 'Ministry of Education / Digital India',
                'url' => 'https://www.abc.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'Academic Bank of Credits digital identity creation, credit transfer rules, and DigiLocker integration.'
            ],
            [
                'keyword' => 'APAAR ID Card: Mandatory One Nation One Student ID Registration & DigiLocker Linking',
                'source' => 'Ministry of Education',
                'url' => 'https://apaar.education.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'Automated Permanent Academic Account Registry creation steps, school consent forms, and student benefits.'
            ],
            [
                'keyword' => 'SSC One-Time Registration (OTR): Mandatory Live Photo, Signature & Document Upload Guide',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'Staff Selection Commission new portal one-time registration protocol, application app, and live capture rules.'
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
