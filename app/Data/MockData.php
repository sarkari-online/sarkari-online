<?php
/**
 * EduPulse - Mock Data Layer (Phase 0)
 * Realistic, verified-style Indian education and career data for Phase 0 UI preview.
 * This will be replaced by MySQL queries in Phase 1 without changing component templates.
 */

class MockData {

    public static function getBreakingUpdates(): array {
        return [
            [
                'title' => 'NTA CUET UG 2026 City Intimation Slip Released: Direct Download Link Active at exams.nta.ac.in',
                'url' => 'article/cuet-ug-2026-city-intimation-slip-download/',
                'tag' => 'Breaking',
                'time' => '12 mins ago'
            ],
            [
                'title' => 'UPSC Civil Services Prelims 2026 Official Answer Key Released on upsc.gov.in',
                'url' => 'article/upsc-cse-prelims-2026-official-answer-key-released/',
                'tag' => 'Latest',
                'time' => '45 mins ago'
            ],
            [
                'title' => 'CBSE Class 12th Board Revaluation & Verification Results 2026 Declared',
                'url' => 'article/cbse-class-12th-revaluation-results-2026-out/',
                'tag' => 'Exam Result',
                'time' => '2 hours ago'
            ],
            [
                'title' => 'SSC CGL 2026 Notification for 14,000+ Group B & C Vacancies: Apply by 30th Sept',
                'url' => 'article/ssc-cgl-2026-recruitment-notification-vacancies-eligibility/',
                'tag' => 'Govt Job',
                'time' => '4 hours ago'
            ]
        ];
    }

    public static function getHeroArticles(): array {
        return [
            'featured' => [
                'id' => 101,
                'slug' => 'neet-ug-2026-counselling-schedule-released-mcc-nic-in',
                'title' => 'NEET UG 2026 All India Quota (AIQ) Counselling Schedule Released by MCC: Check Round 1 Choice Filling Dates',
                'excerpt' => 'The Medical Counselling Committee (MCC) has officially published the 15% AIQ counselling calendar for MBBS/BDS admissions across government and deemed universities in India.',
                'category' => 'exam-results',
                'category_name' => 'Exam Results',
                'category_color' => '#1d4ed8',
                'author' => 'Sarkari.online Editorial',
                'published_at' => '2026-08-18 16:30:00',
                'updated_at' => '2026-08-18 18:15:00',
                'read_time' => '4 min read',
                'image' => 'neet-counselling-2026.svg',
                'image_alt' => 'NEET UG 2026 Counselling Schedule and Procedure',
                'is_verified' => true,
                'source_name' => 'Medical Counselling Committee (mcc.nic.in)',
                'source_url' => 'https://mcc.nic.in'
            ],
            'secondary' => [
                [
                    'id' => 102,
                    'slug' => 'jee-advanced-2026-qualifying-cutoff-marks-iit-delhi',
                    'title' => 'JEE Advanced 2026 Category-wise Qualifying Cutoff Marks Declared by IIT Delhi: Top 2.5 Lakh JEE Main Qualifiers Eligible',
                    'excerpt' => 'Detailed breakdown of minimum percentage of aggregate marks and subject-wise cutoffs required for Common Rank List (CRL) and reserved categories.',
                    'category' => 'entrance-exams',
                    'category_name' => 'Entrance Exams',
                    'category_color' => '#b91c1c',
                    'author' => 'Editorial Team',
                    'published_at' => '2026-08-18 14:00:00',
                    'updated_at' => null,
                    'read_time' => '3 min read',
                    'image' => 'jee-advanced-cutoff.svg',
                    'image_alt' => 'JEE Advanced 2026 Cutoff Marks Chart'
                ],
                [
                    'id' => 103,
                    'slug' => 'ibps-po-2026-prelims-admit-card-download-active',
                    'title' => 'IBPS PO Prelims 2026 Admit Card Out: Download CRP PO/MT XIV Call Letter at ibps.in',
                    'excerpt' => 'Institute of Banking Personnel Selection has made the online preliminary examination call letter available till the exam date. Check shift timings & guidelines.',
                    'category' => 'admit-cards',
                    'category_name' => 'Admit Cards',
                    'category_color' => '#0f766e',
                    'author' => 'Banking Desk',
                    'published_at' => '2026-08-18 11:20:00',
                    'updated_at' => '2026-08-18 13:45:00',
                    'read_time' => '2 min read',
                    'image' => 'ibps-po-admit-card.svg',
                    'image_alt' => 'IBPS PO 2026 Call Letter Download'
                ],
                [
                    'id' => 104,
                    'slug' => 'national-overseas-scholarship-2026-apply-online',
                    'title' => 'National Overseas Scholarship 2026 for SC/ST/De-notified Tribe Students: Apply by 31st August',
                    'excerpt' => 'Ministry of Social Justice and Empowerment invites online applications for 125 slots providing full tuition fee and living expenses in top global universities.',
                    'category' => 'scholarships',
                    'category_name' => 'Scholarships',
                    'category_color' => '#047857',
                    'author' => 'Scholarship Bureau',
                    'published_at' => '2026-08-18 09:15:00',
                    'updated_at' => null,
                    'read_time' => '4 min read',
                    'image' => 'national-overseas-scholarship.svg',
                    'image_alt' => 'National Overseas Scholarship 2026 Guidelines'
                ]
            ]
        ];
    }

    public static function getLatestArticles(int $limit = 6): array {
        $articles = [
            [
                'id' => 201,
                'slug' => 'rrb-ntpc-2026-recruitment-notification-exam-dates',
                'title' => 'RRB NTPC 2026 Notification for 11,558 Graduate & Undergraduate Posts: Check Syllabus & CBT 1 Dates',
                'excerpt' => 'Railway Recruitment Boards announce comprehensive notification across 21 regional RRBs. Important exam stages, negative marking criteria, and preparation strategy.',
                'category' => 'government-jobs',
                'category_name' => 'Government Jobs',
                'category_color' => '#c2410c',
                'published_at' => '2026-08-18 17:00:00',
                'updated_at' => null,
                'read_time' => '5 min read',
                'image' => 'rrb-ntpc-recruitment.svg'
            ],
            [
                'id' => 202,
                'slug' => 'gate-2027-official-syllabus-iit-roorkee-released',
                'title' => 'GATE 2027 Official Information Brochure & Subject Syllabus Released by IIT Roorkee',
                'excerpt' => 'IIT Roorkee launches the official GATE 2027 portal with two new paper combinations and revised section-wise weightage for Computer Science & Data Science.',
                'category' => 'exam-dates',
                'category_name' => 'Exam Dates',
                'category_color' => '#4338ca',
                'published_at' => '2026-08-18 15:10:00',
                'updated_at' => null,
                'read_time' => '3 min read',
                'image' => 'gate-2027-syllabus.svg'
            ],
            [
                'id' => 203,
                'slug' => 'cat-2026-registration-step-by-step-form-filling-guide',
                'title' => 'CAT 2026 Registration Process Step-by-Step Guide: Avoid Common Form Rejection Errors',
                'excerpt' => 'IIM Calcutta opens the Common Admission Test registration. How to properly upload caste certificates, photograph specifications, and work experience proofs.',
                'category' => 'entrance-exams',
                'category_name' => 'Entrance Exams',
                'category_color' => '#b91c1c',
                'published_at' => '2026-08-18 13:30:00',
                'updated_at' => '2026-08-18 14:00:00',
                'read_time' => '6 min read',
                'image' => 'cat-2026-registration.svg'
            ],
            [
                'id' => 204,
                'slug' => 'aiims-norcet-7-result-merit-list-download',
                'title' => 'AIIMS NORCET 7 Stage 1 Result 2026 Declared: Direct PDF Link & Stage 2 Exam Date Out',
                'excerpt' => 'All India Institute of Medical Sciences publishes the Nursing Officer Recruitment Common Eligibility Test result roll-number-wise list on aiimsexams.ac.in.',
                'category' => 'exam-results',
                'category_name' => 'Exam Results',
                'category_color' => '#1d4ed8',
                'published_at' => '2026-08-18 11:45:00',
                'updated_at' => null,
                'read_time' => '3 min read',
                'image' => 'aiims-norcet-result.svg'
            ],
            [
                'id' => 205,
                'slug' => 'top-5-ai-tools-for-upsc-aspirants-editorial-analysis',
                'title' => '5 Best Verified AI Research Tools for UPSC & State PCS Aspirants for Current Affairs Synthesis',
                'excerpt' => 'How civil services candidates are responsibly using AI summarizers, mind-mapping assistants, and PIB tracker tools without compromising answer-writing originality.',
                'category' => 'student-technology',
                'category_name' => 'Student Tech & AI',
                'category_color' => '#334155',
                'published_at' => '2026-08-17 19:20:00',
                'updated_at' => null,
                'read_time' => '4 min read',
                'image' => 'ai-tools-upsc.svg'
            ],
            [
                'id' => 206,
                'slug' => 'career-opportunities-in-semiconductor-design-india',
                'title' => 'Career Roadmap: Semiconductor & VLSI Design Engineering in India Post-2026',
                'excerpt' => 'With India Semiconductor Mission investments crossing $10B, explore high-paying VLSI engineering pathways, essential college projects, and top hiring firms.',
                'category' => 'career-guides',
                'category_name' => 'Career Guides',
                'category_color' => '#9a3412',
                'published_at' => '2026-08-17 14:15:00',
                'updated_at' => null,
                'read_time' => '7 min read',
                'image' => 'semiconductor-careers-india.svg'
            ]
        ];
        return array_slice($articles, 0, $limit);
    }

    public static function getExamUpdates(): array {
        return [
            [
                'id' => 301,
                'slug' => 'nta-ugc-net-june-2026-re-exam-dates-admit-card',
                'title' => 'UGC NET 2026 Re-Examination Schedule Released: Exam in CBT Mode Across 300+ Centers',
                'category' => 'exam-dates',
                'badge' => 'Dates Announced',
                'date' => '2026-08-18',
                'exam_date' => '04 - 12 Sept 2026',
                'status' => 'Confirmed'
            ],
            [
                'id' => 302,
                'slug' => 'ssc-chsl-tier-1-2026-final-answer-key-released',
                'title' => 'SSC CHSL 10+2 Tier 1 Final Answer Key with Response Sheet Released at ssc.gov.in',
                'category' => 'answer-keys',
                'badge' => 'Answer Key Active',
                'date' => '2026-08-18',
                'exam_date' => 'Objections till 24 Aug',
                'status' => 'Action Required'
            ],
            [
                'id' => 303,
                'slug' => 'ctet-july-2026-result-marksheet-digilocker-download',
                'title' => 'CBSE CTET 2026 Result Declared: How to Download Digital Marksheet via DigiLocker',
                'category' => 'exam-results',
                'badge' => 'Result Out',
                'date' => '2026-08-17',
                'exam_date' => 'Pass Percentage: 18.2%',
                'status' => 'Active'
            ],
            [
                'id' => 304,
                'slug' => 'clat-2027-application-form-syllabus-nlu-consortium',
                'title' => 'Consortium of NLUs Opens CLAT 2027 Registration for 5-Year LLB & LLM Programs',
                'category' => 'entrance-exams',
                'badge' => 'Registration Open',
                'date' => '2026-08-17',
                'exam_date' => 'Exam: 06 Dec 2026',
                'status' => 'Ongoing'
            ]
        ];
    }

    public static function getGovtJobs(): array {
        return [
            [
                'id' => 401,
                'slug' => 'ssc-cgl-2026-recruitment-notification-vacancies-eligibility',
                'title' => 'SSC Combined Graduate Level (CGL) 2026 Exam',
                'organization' => 'Staff Selection Commission (Govt of India)',
                'vacancies' => '14,582 Posts (Group B & C)',
                'qualification' => 'Bachelor’s Degree in any discipline',
                'salary' => 'Pay Level 4 to Level 8 (₹25,500 - ₹1,51,100)',
                'last_date' => '30 September 2026',
                'category' => 'government-jobs',
                'source' => 'ssc.gov.in'
            ],
            [
                'id' => 402,
                'slug' => 'sbi-clerk-junior-associates-2026-notification',
                'title' => 'SBI Junior Associates (Customer Support & Sales)',
                'organization' => 'State Bank of India (Central Recruitment)',
                'vacancies' => '8,283 Vacancies across India',
                'qualification' => 'Graduation in any stream',
                'salary' => '₹37,000/month approx. starting CTC',
                'last_date' => '15 September 2026',
                'category' => 'government-jobs',
                'source' => 'sbi.co.in/careers'
            ],
            [
                'id' => 403,
                'slug' => 'drdo-ceptam-11-technician-senior-technical-assistant',
                'title' => 'DRDO CEPTAM 11 (Senior Technical Assistant & Tech-A)',
                'organization' => 'Defence Research & Development Organisation',
                'vacancies' => '1,920 Technical Positions',
                'qualification' => 'Diploma in Engg / B.Sc / ITI',
                'salary' => 'Level 6 (₹35,400 - ₹1,12,400)',
                'last_date' => '22 September 2026',
                'category' => 'government-jobs',
                'source' => 'drdo.gov.in'
            ]
        ];
    }

    public static function getScholarships(): array {
        return [
            [
                'id' => 501,
                'slug' => 'nsp-central-sector-scholarship-scheme-college-university',
                'title' => 'National Scholarship Portal: Central Sector Scheme for College and University Students 2026',
                'amount' => '₹12,000 - ₹20,000 / year',
                'eligibility' => 'Top 20th percentile in Class 12th board exams with family income < ₹4.5 LPA',
                'deadline' => '31 October 2026',
                'provider' => 'Ministry of Education, GoI',
                'image' => 'nsp-scholarship.svg'
            ],
            [
                'id' => 502,
                'slug' => 'reliance-foundation-undergraduate-scholarship-2026',
                'title' => 'Reliance Foundation Undergraduate Scholarship 2026: 5,000 Merit-cum-Means Awards',
                'amount' => 'Up to ₹2,00,000 over degree duration',
                'eligibility' => 'First-year undergraduate students in any discipline across recognized Indian institutes',
                'deadline' => '15 October 2026',
                'provider' => 'Reliance Foundation',
                'image' => 'reliance-scholarship.svg'
            ],
            [
                'id' => 503,
                'slug' => 'fulbright-nehru-fellowships-2027-2028-applications',
                'title' => 'Fulbright-Nehru Master’s and Doctoral Fellowships for Indian Scholars to Study in USA',
                'amount' => 'Full Tuition + Monthly Stipend + Travel Support',
                'eligibility' => 'Indian citizens with 4-year degree & minimum 3 years work experience',
                'deadline' => '15 October 2026',
                'provider' => 'USIEF India',
                'image' => 'fulbright-scholarship.svg'
            ]
        ];
    }

    public static function getCareerGuides(): array {
        return [
            [
                'id' => 601,
                'slug' => 'complete-guide-to-data-science-careers-in-india',
                'title' => 'The Complete 2026 Guide to Breaking Into Data Science & Machine Learning in India',
                'excerpt' => 'Curriculum choices, real-world portfolio requirements, salary benchmarks by tier-1 and tier-2 cities, and interview expectations.',
                'read_time' => '8 min read',
                'published_at' => '2026-08-16',
                'image' => 'data-science-guide.svg'
            ],
            [
                'id' => 602,
                'slug' => 'isro-scientist-engineer-sc-recruitment-pathway',
                'title' => 'How to Become an ISRO Scientist (SC): Eligibility, ICRB Exam Pattern & Preparation Strategy',
                'excerpt' => 'A definitive roadmap for B.Tech Mechanical, Electronics, and Computer Science graduates targeting India’s premier space agency.',
                'read_time' => '6 min read',
                'published_at' => '2026-08-15',
                'image' => 'isro-scientist-guide.svg'
            ],
            [
                'id' => 603,
                'slug' => 'charted-accountancy-new-icai-scheme-timeline',
                'title' => 'ICAI New CA Education & Training Scheme: Foundation, Intermediate & Final Exam Changes Explained',
                'excerpt' => 'All structural adjustments in the 2-year articleship model, self-paced online learning modules, and passing percentages.',
                'read_time' => '5 min read',
                'published_at' => '2026-08-14',
                'image' => 'ca-new-scheme.svg'
            ]
        ];
    }

    public static function getTrendingNow(): array {
        return [
            [
                'rank' => 1,
                'title' => 'NEET UG 2026 AIQ Counselling Round 1 Registration Link Active at mcc.nic.in',
                'slug' => 'neet-ug-2026-counselling-schedule-released-mcc-nic-in',
                'category_name' => 'Exam Results',
                'views' => '84.2K reads',
                'category' => 'exam-results'
            ],
            [
                'rank' => 2,
                'title' => 'SSC CGL 2026 Official Notification PDF: 14,000+ Group B & C Vacancies',
                'slug' => 'ssc-cgl-2026-recruitment-notification-vacancies-eligibility',
                'category_name' => 'Govt Jobs',
                'views' => '62.8K reads',
                'category' => 'government-jobs'
            ],
            [
                'rank' => 3,
                'title' => 'JEE Advanced 2026 Category-wise Qualifying Cutoff Marks Released',
                'slug' => 'jee-advanced-2026-qualifying-cutoff-marks-iit-delhi',
                'category_name' => 'Entrance Exams',
                'views' => '51.4K reads',
                'category' => 'entrance-exams'
            ],
            [
                'rank' => 4,
                'title' => 'CUET UG 2026 City Intimation Slip & Exam Center Allotment',
                'slug' => 'cuet-ug-2026-city-intimation-slip-download',
                'category_name' => 'Admit Cards',
                'views' => '39.7K reads',
                'category' => 'admit-cards'
            ],
            [
                'rank' => 5,
                'title' => 'UPSC CSE Prelims 2026 Official Answer Key for GS Paper 1 and CSAT',
                'slug' => 'upsc-cse-prelims-2026-official-answer-key-released',
                'category_name' => 'Answer Keys',
                'views' => '33.1K reads',
                'category' => 'answer-keys'
            ]
        ];
    }

    public static function getPopularGuides(): array {
        return [
            [
                'title' => 'How to Calculate Percentile vs Normalized Marks in NTA Exams (JEE/CUET/NEET)',
                'slug' => 'how-to-calculate-percentile-vs-normalized-marks-nta-exams',
                'category' => 'entrance-exams',
                'category_name' => 'Exam Guides',
                'read_time' => '5 min read'
            ],
            [
                'title' => 'List of Top 25 Government Engineering Colleges in India with Low Fees (Under ₹1 Lakh/Yr)',
                'slug' => 'top-government-engineering-colleges-india-low-fees',
                'category' => 'college-updates',
                'category_name' => 'College Admissions',
                'read_time' => '7 min read'
            ],
            [
                'title' => 'Understanding Central Government Pay Matrix: 7th CPC Levels, Basic Pay, DA & HRA Calculations',
                'slug' => '7th-pay-commission-matrix-levels-basic-pay-explained',
                'category' => 'government-jobs',
                'category_name' => 'Salary Structure',
                'read_time' => '6 min read'
            ],
            [
                'title' => 'State vs Central OBC-NCL Certificate: Validity, Formats, and Rejection Prevention Guide',
                'slug' => 'obc-ncl-certificate-central-format-validity-guide',
                'category' => 'career-guides',
                'category_name' => 'Document Verification',
                'read_time' => '4 min read'
            ]
        ];
    }

    public static function getStudentTech(): array {
        return [
            [
                'id' => 701,
                'slug' => 'top-5-ai-tools-for-upsc-aspirants-editorial-analysis',
                'title' => '5 Responsible AI Research Tools for Civil Services Aspirants in 2026',
                'excerpt' => 'Explore how ethical AI assistants help organize lengthy PIB documents, parliamentary debate transcripts, and GS syllabus mind maps.',
                'tag' => 'AI Study Tools',
                'read_time' => '4 min read',
                'published_at' => '2026-08-17'
            ],
            [
                'id' => 702,
                'slug' => 'best-free-digital-note-taking-apps-for-competitive-exams',
                'title' => 'Best Open-Source & Free Digital Note-Taking Tools for Gate, JEE & NEET Revision',
                'excerpt' => 'Comparing Obsidian, Logseq, and Notion with spaced repetition plugins tailored for Indian exam revision cycles.',
                'tag' => 'Productivity',
                'read_time' => '5 min read',
                'published_at' => '2026-08-14'
            ],
            [
                'id' => 703,
                'slug' => 'essential-latex-and-python-tools-for-engineering-students',
                'title' => 'Essential Free Tech Stacks Every Indian Engineering Student Should Master by Year 2',
                'excerpt' => 'From LaTeX for research reports to Google Colab environments for compute-heavy college mini-projects.',
                'tag' => 'Developer Tools',
                'read_time' => '6 min read',
                'published_at' => '2026-08-12'
            ]
        ];
    }

    public static function getArticleBySlug(string $slug): ?array {
        $knownSlugs = [
            'neet-ug-2026-counselling-schedule-released-mcc-nic-in',
            'jee-advanced-2026-qualifying-cutoff-marks-iit-delhi',
            'ibps-po-2026-prelims-admit-card-download-active',
            'ssc-cgl-2026-recruitment-notification-vacancies-eligibility',
            'nsp-central-sector-scholarship-scheme-college-university'
        ];

        if (!in_array($slug, $knownSlugs, true)) {
            return null;
        }

        // Detailed full article data for the demo
        return [
            'id' => 101,
            'slug' => $slug ?: 'neet-ug-2026-counselling-schedule-released-mcc-nic-in',
            'title' => 'NEET UG 2026 All India Quota (AIQ) Counselling Schedule Released by MCC: Check Round 1 Choice Filling Dates',
            'excerpt' => 'The Medical Counselling Committee (MCC) under DGHS has officially notified the complete 4-round schedule for 15% All India Quota (AIQ), Deemed, Central Universities, AIIMS, and JIPMER MBBS/BDS admissions 2026.',
            'category' => 'exam-results',
            'category_name' => 'Exam Results',
            'category_color' => '#1d4ed8',
            'author' => [
                'name' => 'Dr. Rajesh Nair, M.Ed',
                'title' => 'Senior Education & Admissions Analyst',
                'bio' => 'Advising medical and engineering aspirants across India for over 14 years. Specializes in MCC/NTA regulations, merit quota matrixes, and institutional counselling.',
                'avatar' => 'author-rajesh.svg'
            ],
            'published_at' => '2026-08-18 16:30:00',
            'updated_at' => '2026-08-18 18:15:00',
            'read_time' => '5 min read',
            'image' => 'neet-counselling-2026.svg',
            'image_alt' => 'MCC NEET UG 2026 AIQ Counselling Schedule and Process',
            'is_verified' => true,
            'source' => [
                'name' => 'Medical Counselling Committee (MCC) / DGHS',
                'official_notice_ref' => 'Notification No. F.No.U-12021/01/2026-MEC',
                'source_url' => 'https://mcc.nic.in',
                'verified_at' => '2026-08-18 17:00:00 IST'
            ],
            'content_html' => <<<HTML
<p class="lead">The Medical Counselling Committee (MCC), Directorate General of Health Services (DGHS), has officially published the information bulletin and calendar for <strong>NEET UG 2026 All India Quota (AIQ) Counselling</strong>. Candidates who have qualified in the National Eligibility cum Entrance Test (UG) 2026 can participate in the online choice-filling and seat allocation procedure through the official portal at <code>mcc.nic.in</code>.</p>

<h2>Overview of NEET UG 2026 AIQ Counselling Stages</h2>
<p>The MCC counselling process governs admissions to the following seat pools across the country:</p>
<ul>
    <li><strong>15% All India Quota (AIQ) Seats:</strong> Across all state government medical and dental colleges without domicile restrictions.</li>
    <li><strong>100% MBBS/BDS Seats:</strong> In AIIMS institutions across India, JIPMER (Puducherry &amp; Karaikal), and Central Universities (BHU, AMU, Jamia Millia Islamia).</li>
    <li><strong>100% Deemed University Seats:</strong> For all private deemed medical institutions.</li>
    <li><strong>Armed Forces Medical College (AFMC) Pune:</strong> Initial registration and forwarding of merit list for subsequent screening.</li>
</ul>

<div class="info-callout">
    <div class="info-callout-icon">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
    </div>
    <div class="info-callout-body">
        <strong>Official Advisory from MCC:</strong> Candidates are strictly instructed not to share their login roll number and password with coaching centers or third-party agencies. All choice locking must be done personally by the candidate.
    </div>
</div>

<h2>NEET UG 2026 Round 1 Detailed Timeline</h2>
<p>Below is the verified timeline for the first round of All India Quota seat allotment:</p>

<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Counselling Event</th>
                <th>Start Date &amp; Time</th>
                <th>Closing Date &amp; Time</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Online Registration &amp; Fee Payment</strong></td>
                <td>21 August 2026 (10:00 AM)</td>
                <td>27 August 2026 (12:00 Noon)</td>
            </tr>
            <tr>
                <td><strong>Choice Filling &amp; Preference Locking</strong></td>
                <td>22 August 2026 (01:00 PM)</td>
                <td>27 August 2026 (11:55 PM)</td>
            </tr>
            <tr>
                <td><strong>Processing of Seat Allotment</strong></td>
                <td>28 August 2026</td>
                <td>29 August 2026</td>
            </tr>
            <tr>
                <td><strong>Publication of Round 1 Results</strong></td>
                <td colspan="2"><strong>30 August 2026 (Evening)</strong></td>
            </tr>
            <tr>
                <td><strong>Reporting to Allotted Medical Colleges</strong></td>
                <td>31 August 2026</td>
                <td>06 September 2026</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Step-by-Step Procedure to Register at mcc.nic.in</h2>
<ol>
    <li>Navigate to the official portal: <strong>mcc.nic.in</strong>.</li>
    <li>Click on the <em>"UG Medical Counselling"</em> tab on the homepage.</li>
    <li>Select <em>"New Registration 2026"</em> and enter your NEET UG Roll Number, Application Number, Mother's Name, and Date of Birth.</li>
    <li>Pay the non-refundable registration fee and refundable security deposit based on your category and institute preference (AIQ Government or Deemed).</li>
    <li>Fill and save your preferred sequence of medical colleges. <strong>Lock choices</strong> before the designated deadline.</li>
    <li>Download and print the filled choices slip for future documentation during college reporting.</li>
</ol>

<h2>Crucial Documents Required During College Reporting</h2>
<p>Selected candidates must produce original copies along with two sets of self-attested photocopies of the following credentials:</p>
<ul>
    <li>NEET UG 2026 Admit Card and Scorecard / Rank Letter</li>
    <li>MCC Provisional Seat Allotment Letter</li>
    <li>Class 10th and Class 12th Certificate &amp; Mark Sheet</li>
    <li>Valid Government Photo ID Proof (Aadhaar Card / Passport / PAN Card)</li>
    <li>Eight recent passport size photographs (same as uploaded on NEET application)</li>
    <li>Category / PwD / EWS Certificate (if applicable, in Central Government prescribed format)</li>
</ul>
HTML,
            'faqs' => [
                [
                    'question' => 'Is registration mandatory for each round of NEET UG 2026 counselling?',
                    'answer' => 'No. Candidates who registered in Round 1 do not need to register again for Round 2 or Round 3. However, fresh registration is permitted for new candidates in Round 2 and Round 3 with the payment of required fees.'
                ],
                [
                    'question' => 'Can a candidate participate in both AIQ 15% and State Quota 85% counselling?',
                    'answer' => 'Yes. Eligible candidates can participate simultaneously in MCC All India Quota counselling and their respective State Counselling Authority seat allotment rounds until they join a seat.'
                ],
                [
                    'question' => 'What is the refundable security deposit amount for AIQ Government seats?',
                    'answer' => 'For 15% AIQ Government and Central Universities, the refundable security deposit is ₹10,000 for UR/EWS candidates and ₹5,000 for SC/ST/OBC/PwD candidates. For Deemed Universities, the refundable security deposit is ₹2,00,000 across all categories.'
                ],
                [
                    'question' => 'What happens if a candidate is allotted a seat in Round 1 but does not join?',
                    'answer' => 'Round 1 offers a "Free Exit" facility. If a candidate does not report to the allotted college, their security deposit will not be forfeited and they can participate in Round 2 directly.'
                ]
            ],
            'related_articles' => [
                [
                    'title' => 'JEE Advanced 2026 Category-wise Qualifying Cutoff Marks Declared by IIT Delhi',
                    'slug' => 'jee-advanced-2026-qualifying-cutoff-marks-iit-delhi',
                    'category' => 'entrance-exams',
                    'category_name' => 'Entrance Exams',
                    'published_at' => '2026-08-18'
                ],
                [
                    'title' => 'Top 25 Government Medical Colleges in India with NIRF Ranking & Annual MBBS Fee Structure',
                    'slug' => 'top-government-medical-colleges-india-nirf-ranking-fees',
                    'category' => 'college-updates',
                    'category_name' => 'College Admissions',
                    'published_at' => '2026-08-15'
                ],
                [
                    'title' => 'How to Calculate Percentile vs Normalized Marks in NTA Exams (JEE/CUET/NEET)',
                    'slug' => 'how-to-calculate-percentile-vs-normalized-marks-nta-exams',
                    'category' => 'entrance-exams',
                    'category_name' => 'Exam Guides',
                    'published_at' => '2026-08-12'
                ]
            ]
        ];
    }

    public static function getCategoryArticles(string $categorySlug, int $page = 1, int $perPage = 6): array {
        $all = self::getLatestArticles(10);
        // Duplicate to simulate pagination
        $items = array_merge($all, $all);
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($items, $offset, $perPage);

        return [
            'items' => $paged,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    public static function searchArticles(string $query, int $page = 1, int $perPage = 6): array {
        $clean = strtolower(trim($query));
        $all = self::getLatestArticles(10);
        
        $results = [];
        foreach ($all as $item) {
            if ($clean === '' || str_contains(strtolower($item['title']), $clean) || str_contains(strtolower($item['excerpt']), $clean) || str_contains(strtolower($item['category_name']), $clean)) {
                $results[] = $item;
            }
        }
        
        $total = count($results);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($results, $offset, $perPage);

        return [
            'query' => $query,
            'items' => $paged,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }
}
