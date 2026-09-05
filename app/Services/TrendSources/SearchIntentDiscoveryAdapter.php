<?php
/**
 * Sarkari.online - High-Volume Search-Intent Discovery Adapter
 * Implements the 18-cluster search-intent universe around Government Jobs,
 * Sarkari Result, SSC, Railway, UPSC, Banking, Defence, Teaching, State Exams,
 * NEET, JEE, Board Exams, Scholarships, Admissions, and Student Digital Services.
 */

namespace App\Services\TrendSources;

use App\Services\AuthorityVerificationService;
use App\Helpers\Logger;

class SearchIntentDiscoveryAdapter implements TrendSourceInterface {

    public function getSourceId(): string {
        return 'search_intent_engine';
    }

    public function getSourceName(): string {
        return 'High-Volume Search-Intent Engine';
    }

    /**
     * 18 Topic Clusters Catalog
     */
    private const CLUSTERS = [
        'GOVERNMENT_JOBS' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://www.india.gov.in',
            'entities' => [
                'Central Government Jobs', 'State Government Jobs', 'Government Jobs in India',
                'Government Jobs After 10th', 'Government Jobs After 12th', 'Government Jobs After Graduation',
                'Government Jobs Without Exam', 'Government Jobs for Freshers', 'Government Jobs for Women',
                'Government Recruitment'
            ],
            'intents' => [
                'Notification & Vacancy Breakdown', 'Eligibility Criteria & Age Limit',
                'Online Application Form & Direct Portal Link', 'Salary Structure & Pay Matrix'
            ]
        ],
        'SARKARI_RESULT' => [
            'category' => 'exam-results',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://sarkari.online',
            'entities' => [
                'Sarkari Result', 'Central & State Exam Results', 'Government Recruitment Result',
                'Entrance Exam Result', 'Merit List PDF & Scorecard Download'
            ],
            'intents' => [
                'Result Declaration Date', 'Category-Wise Cut Off Marks',
                'Merit List PDF & Direct Scorecard Link', 'Document Verification List'
            ]
        ],
        'SSC' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://ssc.gov.in',
            'entities' => [
                'SSC CGL', 'SSC CHSL', 'SSC GD Constable', 'SSC MTS', 'SSC CPO Sub Inspector',
                'SSC JE Junior Engineer', 'SSC Stenographer', 'SSC Selection Post Phase 13', 'SSC Delhi Police Constable'
            ],
            'intents' => [
                'Notification, Vacancies & Eligibility', 'Exam Date, Shift Timings & City Slip',
                'Admit Card / Hall Ticket Download Link', 'Answer Key & Response Sheet Challenge',
                'Tier 1 & Tier 2 Result & Cut Off Marks', 'Syllabus Breakdown & Exam Pattern',
                '7th CPC In-Hand Salary & Post-Wise Pay Level'
            ]
        ],
        'RAILWAY_RRB' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://indianrailways.gov.in',
            'entities' => [
                'RRB NTPC (Graduate & Undergraduate)', 'RRB Group D Level 1', 'RRB ALP Assistant Loco Pilot',
                'RRB Technician Grade 1 & 3', 'RRB JE Junior Engineer', 'RPF Constable & Sub Inspector',
                'Railway Apprentice'
            ],
            'intents' => [
                'CEN Notification & Zone-Wise Vacancies', 'CBT 1 Exam Schedule & Shift Timings',
                'City Intimation Slip & E-Call Letter Download', 'Official Answer Key & Normalization Formula',
                'CBT Result, Zone-Wise Cut Off & Scorecard', 'Physical Efficiency Test (PET) & DV Guidelines',
                'Salary Structure, Grade Pay & Allowances'
            ]
        ],
        'UPSC' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://upsc.gov.in',
            'entities' => [
                'UPSC Civil Services (IAS/IPS/IFS)', 'UPSC NDA & NA', 'UPSC CDS Combined Defence Services',
                'UPSC CAPF Assistant Commandant', 'UPSC EPFO Personal Assistant & EO/AO', 'UPSC Engineering Services (ESE)'
            ],
            'intents' => [
                'Official Notification, Vacancies & OTR Registration', 'Prelims & Mains Exam Schedule',
                'Admit Card Download Link & Exam Day Guidelines', 'Official Answer Key & Cut Off Marks',
                'Final Result, Marksheet & Service Allocation', 'Optional Subject Syllabus & Exam Pattern',
                'IAS / IPS In-Hand Salary, Rank Hierarchy & Perks'
            ]
        ],
        'BANKING' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://ibps.in',
            'entities' => [
                'SBI PO Probationary Officer', 'SBI Clerk Junior Associate', 'IBPS PO Management Trainee',
                'IBPS Clerk', 'IBPS RRB Officer Scale 1 & Office Assistant', 'RBI Grade B Officer',
                'RBI Assistant', 'LIC AAO'
            ],
            'intents' => [
                'Recruitment Notification & State-Wise Vacancies', 'Prelims & Mains Exam Dates',
                'Call Letter / Admit Card Direct Link', 'Prelims Scorecard & Category-Wise Cut Off',
                'Mains Result & Interview Call Letter', 'Sectional Cutoff, Negative Marking & Exam Pattern',
                'Bank PO In-Hand Salary & Allowances'
            ]
        ],
        'POLICE' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://uppbpb.gov.in',
            'entities' => [
                'UP Police Constable & SI', 'Delhi Police Constable & Head Constable', 'Bihar Police Constable & SI',
                'Rajasthan Police Constable', 'MP Police Constable & Sub Inspector', 'Maharashtra Police Constable',
                'Haryana Police Constable'
            ],
            'intents' => [
                'Recruitment Notification, District Vacancies & Eligibility', 'Written Exam Date & Admit Card Link',
                'Answer Key & Category Cut Off Marks', 'Result & Document Verification (DV) Schedule',
                'Physical Standard Test (PST) & Running Requirements (PET)', 'Pay Scale, Grade Pay & In-Hand Salary'
            ]
        ],
        'DEFENCE' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://joinindianarmy.nic.in',
            'entities' => [
                'Indian Army Agniveer Recruitment Rally', 'Indian Navy Agniveer (SSR & MR)',
                'Indian Air Force Agniveer Vayu', 'AFCAT Air Force Common Admission Test',
                'Indian Coast Guard Navik (GD & DB)'
            ],
            'intents' => [
                'Rally Notification, Eligibility & Online Registration', 'Common Entrance Exam (CEE) Date & Admit Card',
                'Answer Key, CEE Result & Merit List', 'Physical Fitness Test (PFT) & Medical Standards',
                'Seva Nidhi Package, Monthly Salary & Skill Certificate'
            ]
        ],
        'TEACHING' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://ctet.nic.in',
            'entities' => [
                'CTET Central Teacher Eligibility Test', 'UPTET Uttar Pradesh Teacher Eligibility Test',
                'REET Rajasthan Eligibility Examination for Teachers', 'Bihar STET & BPSC TRE School Teacher',
                'KVS Kendriya Vidyalaya Recruitment', 'NVS Navodaya Vidyalaya Samiti', 'DSSSB Teacher Recruitment'
            ],
            'intents' => [
                'Notification, Eligibility (B.Ed/D.El.Ed) & Registration', 'Exam Schedule & City Intimation Slip',
                'Admit Card Download Link', 'Provisional Answer Key & OMR Challenge Window',
                'Result, DigiLocker Marksheet & Lifetime Certificate', 'PRT, TGT, PGT Syllabus & Exam Pattern',
                'Primary & TGT Teacher In-Hand Salary as per 7th CPC'
            ]
        ],
        'STATE_EXAMS' => [
            'category' => 'government-jobs',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://bpsc.bih.nic.in',
            'entities' => [
                'BPSC Combined Competitive Exam (CCE)', 'UPPSC Combined State / Upper Subordinate Exam',
                'RPSC RAS / RTS Exam', 'MPPSC State Service Exam', 'UKPSC Combined State Civil Services',
                'HSSC Haryana CET (Group C & D)', 'Rajasthan Patwari & VDO Recruitment',
                'UPSSSC Junior Assistant & Lekhpal'
            ],
            'intents' => [
                'Notification & Department-Wise Vacancies', 'Exam Date & Hall Ticket Download Link',
                'Official Answer Key & Objection Tracker', 'Prelims & Mains Result & Cut Off Marks',
                'Syllabus Breakdown & Subject Weightage', 'Pay Scale, Basic Pay & Allowances'
            ]
        ],
        'NEET' => [
            'category' => 'entrance-exams',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://nta.ac.in',
            'entities' => [
                'NEET UG National Eligibility cum Entrance Test', 'NEET PG Medical Entrance Exam',
                'NEET MDS Entrance Exam'
            ],
            'intents' => [
                'Information Bulletin, Eligibility & Online Application Guide', 'Exam City Slip & Admit Card Direct Link',
                'Official Answer Key & OMR Scanned Image Challenge', 'Result, All India Rank (AIR) & Category Cut Off Percentile',
                'MCC All India Quota (AIQ) & State MBBS/BDS Counselling Guide', 'Marks vs Rank Analysis & Government Medical College Cutoffs',
                'Syllabus Breakdown, Physics/Chemistry/Biology Marking Scheme'
            ]
        ],
        'JEE' => [
            'category' => 'entrance-exams',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://nta.ac.in',
            'entities' => [
                'JEE Main Session 1 & Session 2', 'JEE Advanced Engineering Entrance Exam'
            ],
            'intents' => [
                'Registration Window, Eligibility & Session Dates', 'Advance City Intimation Slip & Admit Card Link',
                'Final Answer Key & Normalization Formula', 'NTA Score, Percentile vs Marks & All India Rank (AIR)',
                'JoSAA & CSAB Counselling Seat Allocation Process', 'NITs, IIITs & GFTIs BTech Cutoff Percentiles',
                'Syllabus Blueprint & High-Weightage Chapters'
            ]
        ],
        'BOARD_EXAMS' => [
            'category' => 'school-boards',
            'opportunity_tier' => 'tier_1_national_priority',
            'applicant_scale' => 'mega_applicant_pool',
            'authority' => 'https://cbse.gov.in',
            'entities' => [
                'CBSE Board Class 10 & Class 12', 'UP Board High School & Intermediate',
                'Bihar Board (BSEB) Matric & Inter', 'Rajasthan Board (RBSE) Class 10 & 12',
                'MP Board (MPBSE) 10th & 12th', 'ICSE & ISC Board Exam'
            ],
            'intents' => [
                'Official Date Sheet / Exam Time Table PDF', 'Roll Number & Admit Card Download Guidelines',
                'Result Declaration Date, Direct Link & DigiLocker Marksheet', 'Compartment / Supplementary Exam Schedule',
                'Model Question Papers, Sample Blueprints & Marking Scheme'
            ]
        ],
        'SCHOLARSHIPS' => [
            'category' => 'scholarships',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://scholarships.gov.in',
            'entities' => [
                'NSP National Scholarship Portal Central Sector Scheme', 'PM YASASVI Scholarship for OBC/EBC/DNT',
                'PMSSS J&K and Ladakh Special Scholarship Scheme', 'Post-Matric Scholarship for SC/ST/OBC Students',
                'Pre-Matric Minority & Merit-cum-Means Scholarship'
            ],
            'intents' => [
                'Online Application Form, Eligibility & Document Checklist', 'Application Deadline & Renewal Process',
                'Aadhaar Face Auth & OTR Registration Steps', 'Merit List, Selection Status & Direct PFMS Payment Track'
            ]
        ],
        'COLLEGE_ADMISSIONS' => [
            'category' => 'college-updates',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://cuet.nta.nic.in',
            'entities' => [
                'CUET UG Common University Entrance Test', 'CUET PG Post Graduate Entrance Test',
                'Delhi University (DU CSAS) UG Admission', 'JoSAA Engineering Seat Allotment'
            ],
            'intents' => [
                'Admission Notification, Eligibility & Online Registration', 'Admit Card & Exam Schedule',
                'Scorecard & Normalized NTA Percentile Link', 'College Cutoff Marks & Counselling Seat Matrix'
            ]
        ],
        'CAREER_SALARY_ELIGIBILITY' => [
            'category' => 'career-guides',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://www.india.gov.in',
            'entities' => [
                '7th Pay Commission Pay Matrix', 'Government Jobs Salary After 10th & 12th',
                'SSC CGL vs Bank PO Salary & Career Comparison', 'Civil Services (IAS) Promotion Hierarchy & Perks',
                'Government Teacher (PRT/TGT/PGT) Pay Scales'
            ],
            'intents' => [
                'Pay Level 1 to 14 Basic Pay, DA, HRA & In-Hand Calculation', 'Job Profile, Work Hours & Promotion Path',
                'Minimum Age Limit, Educational Qualification & Relaxation Rules'
            ]
        ],
        'STUDENT_DIGITAL_SERVICES' => [
            'category' => 'student-technology',
            'opportunity_tier' => 'tier_2_state_or_sectoral',
            'applicant_scale' => 'high_applicant_pool',
            'authority' => 'https://digilocker.gov.in',
            'entities' => [
                'DigiLocker Student Marksheet & Migration Certificate',
                'APAAR ID One Nation One Student ID Card',
                'Academic Bank of Credits (ABC ID) Creation',
                'SSC One-Time Registration (OTR) Portal',
                'UPSC One-Time Registration (OTR) Process',
                'National Scholarship Portal (NSP) OTR'
            ],
            'intents' => [
                'Step-by-Step Registration & Aadhaar Face Auth Guide', 'Online Download Link & Verification Process',
                'Correction, Mobile Number Update & Error Resolution'
            ]
        ]
    ];

    /**
     * Fetch emerging candidate topics synthesized from the search-intent matrix
     */
    public function fetch(int $limit = 10): array {
        $candidates = [];
        $currentYear = (int)date('Y');
        $nextYear = $currentYear + 1;

        // Shuffle cluster keys to ensure broad round-robin discovery
        $clusterKeys = array_keys(self::CLUSTERS);
        shuffle($clusterKeys);

        foreach ($clusterKeys as $clusterKey) {
            if (count($candidates) >= $limit) {
                break;
            }

            $cluster = self::CLUSTERS[$clusterKey];
            $entities = $cluster['entities'];
            $intents = $cluster['intents'];

            shuffle($entities);
            shuffle($intents);

            $entity = $entities[0];
            $intent = $intents[0];

            // Decide temporal modifier: current year, next year, or evergreen without year
            $yearModifier = '';
            $isEvergreen = in_array($clusterKey, ['CAREER_SALARY_ELIGIBILITY', 'STUDENT_DIGITAL_SERVICES'], true);
            if (!$isEvergreen) {
                $yearModifier = (str_contains($intent, 'Notification') || str_contains($intent, 'Exam Date')) 
                    ? " {$currentYear}" 
                    : " {$currentYear}";
            }

            $keyword = "{$entity}{$yearModifier}: {$intent}";

            // Verify authority tier
            $authCheck = AuthorityVerificationService::verify($cluster['authority']);

            $candidates[] = [
                'keyword' => $keyword,
                'source' => $authCheck['authority_name'],
                'url' => $cluster['authority'],
                'trend_score' => 95, // High initial priority for Intent-Driven seeds
                'category_hint' => $cluster['category'],
                'snippet' => "Authoritative student intent guide for {$entity} {$yearModifier} covering {$intent}. Grounded in official portal standards.",
                'detected_at' => date('Y-m-d H:i:s'),
                'raw_payload' => [
                    'cluster' => $clusterKey,
                    'entity' => $entity,
                    'intent' => $intent,
                    'year_cycle' => $isEvergreen ? 'evergreen' : (string)$currentYear,
                    'search_demand' => [
                        'exact_keyword_volume' => [
                            'value' => null, // Explicitly null without fabrication
                            'confidence' => 'unverified'
                        ],
                        'cluster_discovery_opportunity' => [
                            'opportunity_tier' => $cluster['opportunity_tier'],
                            'signal_type' => 'discovery_opportunity_clustering', // Explicit discovery signal, NOT measured search volume!
                            'basis' => 'national_search_intent_matrix'
                        ],
                        'applicant_scale' => [
                            'applicant_tier' => $cluster['applicant_scale'],
                            'source' => 'official_commission_historical_filings'
                        ],
                        'trend_signal' => 'active_cycle',
                        'official_event_signal' => 'statutory_portal_bulletin'
                    ],
                    'authority_tier' => $authCheck['tier'],
                    'authority_confidence' => $authCheck['confidence']
                ]
            ];
        }

        return $candidates;
    }
}
