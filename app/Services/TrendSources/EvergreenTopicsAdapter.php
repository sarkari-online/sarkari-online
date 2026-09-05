<?php
/**
 * Sarkari.online - High-Search Evergreen & Authority Topics Catalog
 * Provides verified, factual high-demand student search topics:
 * Default Mix: ~70% Student Technology & AI, ~30% Scholarships & Financial Aid,
 * with flexibility for other high-demand educational queries.
 */

namespace App\Services\TrendSources;

use App\Services\TrendService;
use App\Helpers\Logger;
use App\AI\Gemini;
use Throwable;

class EvergreenTopicsAdapter implements TrendSourceInterface {

    public function getSourceId(): string {
        return 'evergreen_guides';
    }

    public function getSourceName(): string {
        return 'High-Search Intent Student Guides';
    }

    /**
     * Authoritative catalog of high-demand Indian student topics
     * Default mix: ~70% Student Technology & AI | ~30% Scholarships & Financial Aid | Verified Educational How-To
     */
    private function getCatalog(): array {
        return [
            // =========================================================================
            // CATEGORY 0: TIER 1 MEGA NATIONAL EXAMINATIONS & RECRUITMENTS (Score 98-99)
            // =========================================================================
            [
                'keyword' => 'RRB NTPC 2026: CBT 1 Exam Schedule, Shift Timings and Hall Ticket Direct Link',
                'source' => 'Railway Recruitment Boards (RRB)',
                'url' => 'https://indianrailways.gov.in',
                'category_hint' => 'admit-cards',
                'trend_score' => 99,
                'snippet' => 'Official Railway Recruitment Board CEN 05/2024 and CEN 06/2024 computer based test dates, shift schedules, city slip download, and e-call letter guidelines.'
            ],
            [
                'keyword' => 'SSC GD Constable 2026: Application Status, Exam Dates and Physical Test (PST/PET) Guidelines',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 99,
                'snippet' => 'Staff Selection Commission Constable GD recruitment in CAPFs, SSF, and Rifleman in Assam Rifles exam dates, application status check, and physical standards.'
            ],
            [
                'keyword' => 'SSC CGL 2026: Tier 1 & Tier 2 Exam Pattern, Syllabus Breakdown and Cut-Off Analysis',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'career-guides',
                'trend_score' => 98,
                'snippet' => 'Comprehensive Combined Graduate Level examination scheme, module-wise negative marking, computer knowledge test qualifying marks, and category cutoffs.'
            ],
            [
                'keyword' => 'NEET UG 2026: Eligibility Criteria, Information Bulletin and Step-by-Step Registration Guide',
                'source' => 'National Testing Agency (NTA)',
                'url' => 'https://nta.ac.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 98,
                'snippet' => 'NTA National Eligibility cum Entrance Test undergraduate bulletin, mandatory age limits, qualifying subjects code, application fee, and document upload specs.'
            ],
            [
                'keyword' => 'BPSC Teacher (TRE 4.0) Recruitment 2026: Vacancy Distribution, Eligibility and Online Application',
                'source' => 'Bihar Public Service Commission (BPSC)',
                'url' => 'https://www.bpsc.bih.nic.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 98,
                'snippet' => 'Bihar Public Service Commission School Teacher Phase 4 recruitment, primary, middle, secondary, and higher secondary vacancy details, CTET/STET qualification.'
            ],
            [
                'keyword' => 'UP Police Constable 2026: Selection Stages, Written Cut-Off Marks and Physical Standards Guide',
                'source' => 'Uttar Pradesh Police Recruitment & Promotion Board (UPPRPB)',
                'url' => 'https://uppbpb.gov.in',
                'category_hint' => 'government-jobs',
                'trend_score' => 98,
                'snippet' => 'UPPRPB 60,244 police constable selection process, document verification (DV), physical standard test (PST), physical efficiency test (PET), and cutoff marks.'
            ],
            [
                'keyword' => 'CTET 2026: Paper 1 & Paper 2 Syllabus, Eligibility and DigiLocker Marksheet Download',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://ctet.nic.in',
                'category_hint' => 'entrance-exams',
                'trend_score' => 97,
                'snippet' => 'Central Teacher Eligibility Test qualifying marks for General/OBC/SC/ST, lifetime certificate validity, Paper 1 and 2 syllabus, and DigiLocker security PIN.'
            ],
            [
                'keyword' => 'SBI Clerk 2026: Junior Associates Notification, State-wise Vacancies and Prelims Preparation Blueprint',
                'source' => 'State Bank of India (SBI)',
                'url' => 'https://sbi.co.in/web/careers',
                'category_hint' => 'government-jobs',
                'trend_score' => 97,
                'snippet' => 'State Bank of India Junior Associates customer support and sales recruitment, state-wise language proficiency test (LPT), exam pattern, and cutoffs.'
            ],
            // =========================================================================
            // CATEGORY 1: STUDENT TECHNOLOGY & DIGITAL IDENTITY (~70% Default Focus)
            // =========================================================================
            [
                'keyword' => 'DigiLocker ABC ID Creation: Academic Bank of Credits Registration and University Mapping',
                'source' => 'Ministry of Electronics and Information Technology (MeitY)',
                'url' => 'https://www.abc.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 97,
                'snippet' => 'Official protocol for creating Academic Bank of Credits digital identity, credit accumulation under NEP 2020, and linking with DigiLocker.'
            ],
            [
                'keyword' => 'APAAR ID Card Download: One Nation One Student ID Registration and School Consent Guide',
                'source' => 'Ministry of Education',
                'url' => 'https://apaar.education.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 97,
                'snippet' => 'Automated Permanent Academic Account Registry 12-digit student ID creation, parent consent guidelines, and DigiLocker card download.'
            ],
            [
                'keyword' => 'DigiLocker CBSE Certificate Download: Class 10 and 12 Digital Marksheet and Passing Certificate',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://digilocker.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'Step-by-step procedure to pull legally valid digitally signed CBSE marksheet, migration certificate, and passing document from DigiLocker repository.'
            ],
            [
                'keyword' => 'DigiLocker CTET Qualifying Certificate: Mark Statement Download and Security PIN Guide',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://ctet.nic.in',
                'category_hint' => 'student-technology',
                'trend_score' => 95,
                'snippet' => 'How candidates can access encrypted digital CTET eligibility certificate and marks statement via DigiLocker credentials and Aadhaar mapping.'
            ],
            [
                'keyword' => 'Academic Bank of Credits (ABC): Multiple Entry and Exit Policy Credit Transfer Rules',
                'source' => 'University Grants Commission (UGC)',
                'url' => 'https://www.abc.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 95,
                'snippet' => 'UGC guidelines for credit redemption, inter-university transfer, validity period of academic credits, and digital transcript verification.'
            ],
            [
                'keyword' => 'SSC One-Time Registration (OTR): Mandatory Live Photo Capture and Document Specification Guide',
                'source' => 'Staff Selection Commission (SSC)',
                'url' => 'https://ssc.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'Staff Selection Commission official portal one-time registration protocol, webcam live photograph capture rules, and signature specifications.'
            ],
            [
                'keyword' => 'UPSC One-Time Registration (OTR): Profile Creation, Lifetime Registration and Modification Rules',
                'source' => 'Union Public Service Commission (UPSC)',
                'url' => 'https://upsconline.nic.in',
                'category_hint' => 'student-technology',
                'trend_score' => 96,
                'snippet' => 'UPSC OTR portal registration guide, mandatory documents, one-time profile modification window, and examination application linking.'
            ],
            [
                'keyword' => 'SWAYAM NPTEL Course Registration: Credit Transfer, Online Exam Enrollment and Certification',
                'source' => 'Ministry of Education',
                'url' => 'https://swayam.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 94,
                'snippet' => 'Guidelines for enrolling in free MOOCs courses on SWAYAM, credit transfer to college degrees under UGC regulations, and proctored exam fees.'
            ],
            [
                'keyword' => 'National Digital Library of India (NDLI): Free Student Membership, Academic Books and Research Access',
                'source' => 'IIT Kharagpur / Ministry of Education',
                'url' => 'https://ndl.iitkgp.ac.in',
                'category_hint' => 'student-technology',
                'trend_score' => 93,
                'snippet' => 'Accessing millions of digitized textbooks, university question papers, video lectures, and research publications for free across India.'
            ],
            [
                'keyword' => 'Cyber Security Essentials for Students: Identifying Fake Job Portals, Exam Phishing and Scam Notices',
                'source' => 'Indian Computer Emergency Response Team (CERT-In)',
                'url' => 'https://www.cert-in.org.in',
                'category_hint' => 'student-technology',
                'trend_score' => 94,
                'snippet' => 'Recognizing unauthorized clone portals of SSC/UPSC, checking official gov.in SSL certificates, and reporting cyber recruitment fraud on national portal.'
            ],
            [
                'keyword' => 'National Apprenticeship Training Scheme (NATS 2.0): Student Registration, Industry Placement and Stipend Rules',
                'source' => 'Ministry of Education',
                'url' => 'https://nats.education.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 95,
                'snippet' => 'Portal registration procedure for engineering, diploma, and general stream graduates, direct benefit transfer (DBT) stipend rules, and employer contract.'
            ],
            [
                'keyword' => 'National Apprenticeship Promotion Scheme (NAPS): Candidate Registration and Trade Apprenticeship Guide',
                'source' => 'Ministry of Skill Development and Entrepreneurship',
                'url' => 'https://www.apprenticeshipindia.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 94,
                'snippet' => 'Step-by-step portal onboarding for ITI and 10th pass candidates, industry apprenticeship vacancies, and monthly government co-funding stipend.'
            ],
            [
                'keyword' => 'Digital India Internship Scheme: Eligibility, Application Process and Monthly Stipend Guidelines',
                'source' => 'Ministry of Electronics and Information Technology (MeitY)',
                'url' => 'https://www.meity.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 94,
                'snippet' => 'MeitY official summer and winter internship guidelines, selection criteria for B.Tech and MCA students, and certificate of completion.'
            ],
            [
                'keyword' => 'AI Tools in Academic Learning: Ethical Use, Prompt Engineering for Exam Revision and Research Verification',
                'source' => 'National Institute of Open Schooling (NIOS)',
                'url' => 'https://www.nios.ac.in',
                'category_hint' => 'student-technology',
                'trend_score' => 93,
                'snippet' => 'Constructive and responsible utilization of AI study assistants for competitive exam question analysis, study scheduling, and citation verification.'
            ],
            [
                'keyword' => 'National Career Service (NCS) Portal: Jobseeker Registration, Skill Assessment and Govt Jobs Mapping',
                'source' => 'Ministry of Labour and Employment',
                'url' => 'https://www.ncs.gov.in',
                'category_hint' => 'student-technology',
                'trend_score' => 94,
                'snippet' => 'Free government employment exchange registration, career counseling access, digital employment fairs, and state job vacancy alerts.'
            ],

            // =========================================================================
            // CATEGORY 2: SCHOLARSHIPS & FINANCIAL AID (~30% Default Focus)
            // =========================================================================
            [
                'keyword' => 'National Scholarship Portal (NSP): One-Time Registration (OTR), Face Auth and Aadhaar Biometric Guide',
                'source' => 'Ministry of Electronics and Information Technology (MeitY)',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 98,
                'snippet' => 'Mandatory OTR app face authentication protocol, biometric verification at CSC centers, and direct benefit transfer guidelines for NSP schemes.'
            ],
            [
                'keyword' => 'Post Matric Scholarship for SC and ST Students: Income Slabs, State Portal Links and DBT Verification',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 97,
                'snippet' => 'Annual family income ceiling of Rs 2.5 lakh, tuition fee reimbursement rules, maintenance allowance schedule, and state portal linking.'
            ],
            [
                'keyword' => 'Post Matric Scholarship for OBC, EBC and DNT Students: Scheme Guidelines and Document Checklist',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://socialjustice.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 96,
                'snippet' => 'Central assistance for other backward classes in higher education, income criteria of Rs 2.5 lakh, required certificates, and institution verification.'
            ],
            [
                'keyword' => 'Central Sector Scheme of Scholarship for College and University Students (CSSS): Cutoffs and Renewal Rules',
                'source' => 'Department of Higher Education',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 96,
                'snippet' => 'Top 20th percentile board cutoff criteria, Rs 12,000 to Rs 20,000 annual disbursement for graduation/post-graduation, and 50% marks renewal rule.'
            ],
            [
                'keyword' => 'PM Yasasvi Scholarship Scheme: Top-Class Education Grants for OBC, EBC and DNT Students',
                'source' => 'Ministry of Social Justice and Empowerment',
                'url' => 'https://yet.nta.ac.in',
                'category_hint' => 'scholarships',
                'trend_score' => 96,
                'snippet' => 'Financial grants covering full college tuition and living allowances for students admitted to notified top-class institutions across India.'
            ],
            [
                'keyword' => 'PMSSS Prime Minister Special Scholarship Scheme: AICTE J&K and Ladakh Engineering and Degree Grants',
                'source' => 'All India Council for Technical Education (AICTE)',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 95,
                'snippet' => 'Academic fee up to Rs 1.25 lakh and annual maintenance grant of Rs 1 lakh for students from Jammu, Kashmir, and Ladakh studying in AICTE colleges.'
            ],
            [
                'keyword' => 'AICTE Pragati Scholarship for Girls: Degree and Diploma Eligibility, Rs 50,000 Annual Financial Grant',
                'source' => 'All India Council for Technical Education (AICTE)',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 96,
                'snippet' => 'Scholarship for female students entering technical degree or diploma programs, family income under Rs 8 lakh, and direct bank account disbursement.'
            ],
            [
                'keyword' => 'AICTE Saksham Scholarship: Financial Aid and Allowance for Differently-Abled Technical Degree Students',
                'source' => 'All India Council for Technical Education (AICTE)',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 94,
                'snippet' => 'Rs 50,000 annual scholarship for students with 40% or more disability entering AICTE approved technical institutes.'
            ],
            [
                'keyword' => 'AICTE Swanath Scholarship: Support for Orphans, Wards of Armed Forces and COVID-19 Affected Youth',
                'source' => 'All India Council for Technical Education (AICTE)',
                'url' => 'https://www.aicte-india.org',
                'category_hint' => 'scholarships',
                'trend_score' => 94,
                'snippet' => 'Financial grants of Rs 50,000 per annum for degree and diploma students pursuing technical courses under the Swanath rehabilitation scheme.'
            ],
            [
                'keyword' => 'Ishan Uday Special Scholarship for North Eastern Region (NER): UGC Grants and Selection Guidelines',
                'source' => 'University Grants Commission (UGC)',
                'url' => 'https://www.ugc.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 94,
                'snippet' => '10,000 annual scholarships for students with domicile of NE region admitted to first year undergraduate programs, Rs 5,400 to Rs 7,800 monthly.'
            ],
            [
                'keyword' => 'DST INSPIRE Fellowship: Eligibility Criteria, Selection and Financial Support for Research Scholars',
                'source' => 'Department of Science and Technology (DST)',
                'url' => 'https://online-inspire.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 94,
                'snippet' => 'Fellowship for university 1st rankers and top 1% basic science students pursuing doctoral studies, monthly stipend and research contingency grants.'
            ],
            [
                'keyword' => 'Begum Hazrat Mahal National Scholarship: Financial Aid for Meritorious Minority Girls',
                'source' => 'Maulana Azad Education Foundation (MAEF)',
                'url' => 'https://scholarships.gov.in',
                'category_hint' => 'scholarships',
                'trend_score' => 93,
                'snippet' => 'Financial assistance for female students from minority communities in classes 9 to 12 with family income under Rs 2 lakh per annum.'
            ],

            // =========================================================================
            // CATEGORY 3: HIGH-DEMAND EDUCATIONAL INTENT (Override Category)
            // =========================================================================
            [
                'keyword' => 'UGC College Fee Refund Policy: Deadlines, Zero Deduction Rules and Student Grievance Redressal',
                'source' => 'University Grants Commission (UGC)',
                'url' => 'https://ugc.ac.in',
                'category_hint' => 'college-updates',
                'trend_score' => 96,
                'snippet' => 'Mandatory statutory refund timelines, full fee refund with zero deduction on timely seat cancellation, and penalties for certificate withholding.'
            ],
            [
                'keyword' => 'College Admission Gap Certificate: Standard Format, Stamp Paper Rules and Notary Affidavit Guidelines',
                'source' => 'Ministry of Education',
                'url' => 'https://education.gov.in',
                'category_hint' => 'college-updates',
                'trend_score' => 95,
                'snippet' => 'Official gap year explanation format on non-judicial stamp paper, essential declarations for college admissions, and notary verification norms.'
            ],
            [
                'keyword' => 'Anti-Ragging Undertaking: Mandatory Online Affidavit Registration and Reference Number on antiragging.in',
                'source' => 'University Grants Commission (UGC)',
                'url' => 'https://www.antiragging.in',
                'category_hint' => 'college-updates',
                'trend_score' => 95,
                'snippet' => 'Mandatory online anti-ragging declaration for university enrollment, step-by-step form submission, and student acknowledgment slip generation.'
            ],
            [
                'keyword' => 'Migration Certificate vs Transfer Certificate: Key Differences, Issuance Process and Validity Rules',
                'source' => 'Central Board of Secondary Education (CBSE)',
                'url' => 'https://cbse.gov.in',
                'category_hint' => 'college-updates',
                'trend_score' => 94,
                'snippet' => 'Clear distinction between board migration certificates and school/college transfer certificates, document requirements, and university submission rules.'
            ]
        ];
    }

    /**
     * Fetch unaddressed high-intent topics.
     * Evaluates static catalog first; if exhausted, dynamically consults AI for genuine student search topics.
     * If no qualified candidates pass verification, safely returns an empty array (0 items).
     */
    public function fetch(int $limit = 5): array {
        $results = [];

        try {
            $catalog = $this->getCatalog();

            // 1. Evaluate Pre-Curated Catalog First
            foreach ($catalog as $item) {
                if (count($results) >= $limit) break;

                // Deduplication: Skip if already exists as trend or article
                if (TrendService::existsAsTrend($item['keyword']) || TrendService::existsAsArticle($item['keyword'])) {
                    continue;
                }

                $results[] = [
                    'keyword'       => $item['keyword'],
                    'source'        => $item['source'],
                    'url'           => $item['url'],
                    'trend_score'   => $item['trend_score'],
                    'category_hint' => $item['category_hint'],
                    'snippet'       => $item['snippet'],
                    'detected_at'   => date('Y-m-d H:i:s'),
                    'raw_payload'   => [
                        'source_type' => 'evergreen_official',
                        'authority'   => $item['source'],
                        'url'         => $item['url']
                    ]
                ];
            }

            // 2. If catalog slots remain unfilled, attempt dynamic search-intent discovery via AI
            if (count($results) < $limit) {
                $needed = $limit - count($results);
                $dynamicItems = $this->discoverDynamicDemandTopics($needed);

                foreach ($dynamicItems as $dItem) {
                    if (count($results) >= $limit) break;

                    // Must pass qualification (English only, education relevant, deduplication, valid category)
                    $qualification = TrendService::isQualified($dItem);
                    if (!$qualification['qualified']) {
                        continue;
                    }

                    $results[] = $dItem;
                }
            }

        } catch (Throwable $e) {
            Logger::warning('EvergreenTopicsAdapter fetch error: ' . $e->getMessage());
        }

        // Return qualified items. If none qualify, returns empty array (0 items) without forcing artificial entries.
        return $results;
    }

    /**
     * AI-Assisted Dynamic Demand Topic Discovery
     * Queries Gemini to identify authentic, unaddressed student search queries.
     * Enforces ~70% student-technology / ~30% scholarships default mix, allowing genuine demand overrides.
     */
    private function discoverDynamicDemandTopics(int $count = 3): array {
        try {
            $gemini = new Gemini();
            $prompt = <<<PROMPT
You are a Search Intent Specialist for Indian education.
Generate {$count} real, highly practical search queries that Indian students are actively looking up on Google right now.

FOCUS BALANCE (Default Mix):
- ~70% on Student Technology, Digital Identity & Tools (e.g. DigiLocker, APAAR ID, Academic Bank of Credits, OTR portals, official government skill schemes like NATS, PMKVY, SWAYAM, or practical student tech).
- ~30% on National / State Scholarships & Financial Aid (e.g. NSP Portal, Post-Matric, AICTE schemes, Merit grants).
- If there is stronger genuine search demand in other existing categories (e.g. university admission documents, anti-ragging, gap certificate), you may include it.

STRICT CRITERIA:
1. Must be in 100% English.
2. Must address a concrete, real problem or procedural guide (How-to, documents required, error solving).
3. Must link to an authentic government statutory authority or official portal (e.g., MeitY, UGC, AICTE, MeitY DigiLocker, Ministry of Education, scholarships.gov.in).
4. No clickbait, no fictitious schemes, no invented dates.

OUTPUT FORMAT (Valid JSON Array of objects only):
[
  {
    "keyword": "Precise, descriptive topic headline under 75 characters",
    "source": "Official authority name (e.g. Ministry of Education, MeitY, UGC)",
    "url": "https://official.gov.in/url",
    "category_hint": "student-technology or scholarships or college-updates",
    "trend_score": 95,
    "snippet": "Brief factual summary of what the guide covers."
  }
]
PROMPT;

            $response = $gemini->generateJson($prompt, ['stage' => 'evergreen_discovery', 'temperature' => 0.3]);
            $items = $response['data'] ?? [];

            if (!is_array($items)) {
                return [];
            }

            $valid = [];
            foreach ($items as $item) {
                if (empty($item['keyword']) || empty($item['source']) || empty($item['category_hint'])) {
                    continue;
                }

                $valid[] = [
                    'keyword'       => trim((string)$item['keyword']),
                    'source'        => trim((string)$item['source']),
                    'url'           => filter_var($item['url'] ?? '', FILTER_VALIDATE_URL) ?: 'https://india.gov.in',
                    'trend_score'   => (int)($item['trend_score'] ?? 92),
                    'category_hint' => trim((string)$item['category_hint']),
                    'snippet'       => trim((string)($item['snippet'] ?? '')),
                    'detected_at'   => date('Y-m-d H:i:s'),
                    'raw_payload'   => [
                        'source_type' => 'evergreen_ai_discovered',
                        'authority'   => trim((string)$item['source']),
                        'url'         => $item['url'] ?? 'https://india.gov.in'
                    ]
                ];
            }

            return $valid;

        } catch (Throwable $e) {
            Logger::warning('Evergreen dynamic discovery failed: ' . $e->getMessage());
            return [];
        }
    }
}
