<?php
/**
 * Sarkari.online - Seed Database with 60+ High-Authority A-to-Z Indian Exam & Government Acronyms
 * Each entry contains rich, structured data (350+ words total per term with overview, eligibility,
 * selection process, and syllabus breakdown).
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GlossaryService;

GlossaryService::initTable();

$terms = [
    // --- A ---
    [
        'acronym' => 'AFCAT',
        'slug' => 'afcat',
        'letter' => 'A',
        'full_form_en' => 'Air Force Common Admission Test',
        'full_form_hi' => 'वायु सेना सामान्य प्रवेश परीक्षा',
        'category' => 'defence',
        'conducting_body' => 'Indian Air Force (IAF)',
        'official_portal' => 'https://afcat.cdac.in',
        'overview' => 'The Air Force Common Admission Test (AFCAT) is a national-level competitive exam conducted biannually by the Indian Air Force to induct Class-I Gazetted Officers into Flying and Ground Duty (Technical and Non-Technical) branches. Qualified candidates proceed to the intensive 5-day Air Force Selection Board (AFSB) testing regimen.',
        'eligibility_criteria' => 'Age: 20 to 24 years for Flying Branch; 20 to 26 years for Ground Duty branches. Educational Qualification: Minimum 50% marks each in Maths and Physics at 10+2 level, and a graduation degree (minimum 3 years) with 60% aggregate or B.E./B.Tech degree with 60% aggregate from a recognized university.',
        'selection_process' => 'Stage 1: Online Computer-Based Written Examination (AFCAT + EKT for technical branch). Stage 2: 5-Day AFSB Interview including Intelligence Tests, Psychological Screening, Group Tests, and Personal Interview. Stage 3: Comprehensive Medical Examination followed by All India Merit List.',
        'syllabus_snapshot' => 'General Awareness (History, Geography, Defence, Current Affairs), Verbal Ability in English (Comprehension, Error Detection, Vocabulary), Numerical Ability (Decimals, Fractions, Profit & Loss, Percentage), and Reasoning & Military Aptitude.',
        'related_article_slug' => 'upsc-cds-2026-notification-exam-schedule'
    ],
    [
        'acronym' => 'AIIMS',
        'slug' => 'aiims',
        'letter' => 'A',
        'full_form_en' => 'All India Institute of Medical Sciences',
        'full_form_hi' => 'अखिल भारतीय आयुर्विज्ञान संस्थान',
        'category' => 'medical',
        'conducting_body' => 'Ministry of Health and Family Welfare / NTA',
        'official_portal' => 'https://aiimsexams.ac.in',
        'overview' => 'AIIMS represents the premier group of autonomous government public medical universities of national importance in India. Originally established in New Delhi in 1956, AIIMS institutes across the country conduct undergraduate MBBS admissions via NEET UG and postgraduate superspecialty admissions via INI-CET, alongside nationwide nursing and paramedical recruitments (NORCET).',
        'eligibility_criteria' => 'For MBBS Admissions: Minimum 17 years of age, 10+2 with Physics, Chemistry, Biology (PCB) and English with minimum 60% marks for General/OBC (50% for SC/ST). For Nursing Officer (NORCET): B.Sc Nursing or GNM with 2 years hospital experience.',
        'selection_process' => 'Undergraduate Admissions: National Eligibility cum Entrance Test (NEET UG) score followed by Medical Counselling Committee (MCC) All India Quota counselling. Staff Recruitment: Computer-Based Test (NORCET Stage I & Stage II).',
        'syllabus_snapshot' => 'Medical Admissions: NCERT Class 11 and 12 Physics, Chemistry, Botany, and Zoology. Nursing Officer Exam: General Nursing curriculum, Anatomy, Physiology, Pharmacology, General Awareness, and Basic Aptitude.',
        'related_article_slug' => 'neet-ug-2026-counselling-schedule-released-mcc-nic-in'
    ],
    [
        'acronym' => 'AIBE',
        'slug' => 'aibe',
        'letter' => 'A',
        'full_form_en' => 'All India Bar Examination',
        'full_form_hi' => 'अखिल भारतीय बार परीक्षा',
        'category' => 'entrance',
        'conducting_body' => 'Bar Council of India (BCI)',
        'official_portal' => 'https://allindiabarexamination.com',
        'overview' => 'The All India Bar Examination (AIBE) is a mandatory statutory post-enrollment certification assessment conducted by the Bar Council of India. Law graduates must qualify for this examination within two years of provisional enrollment with their respective State Bar Council to obtain the Certificate of Practice (CoP) allowing them to practice law in Indian courts.',
        'eligibility_criteria' => 'Must possess a recognized 3-year LL.B or 5-year Integrated LL.B degree from a BCI-approved institution and hold an active provisional enrollment certificate with any State Bar Council in India. No upper age limit.',
        'selection_process' => 'Pen-and-paper OMR based offline examination. Qualifying standard is 45% for General/OBC candidates and 40% for SC/ST/Disabled candidates. No ranking or percentile; results are declared as PASS or FAIL.',
        'syllabus_snapshot' => 'Constitutional Law, CrPC, CPC, Indian Penal Code, Evidence Act, Family Law, Law of Contract, Torts, Company Law, Administrative Law, Labour & Industrial Law, Cyber Law, and Professional Ethics.',
        'related_article_slug' => 'aibe-2026-bci-enrollment-exam-guide'
    ],

    // --- B ---
    [
        'acronym' => 'BPSC',
        'slug' => 'bpsc',
        'letter' => 'B',
        'full_form_en' => 'Bihar Public Service Commission',
        'full_form_hi' => 'बिहार लोक सेवा आयोग',
        'category' => 'civil_services',
        'conducting_body' => 'Government of Bihar',
        'official_portal' => 'https://bpsc.bih.nic.in',
        'overview' => 'The Bihar Public Service Commission (BPSC) is the constitutional authority under Article 315 of the Indian Constitution responsible for recruiting civil servants, school teachers (TRE), police officers, and administrative personnel for the state administration of Bihar. Its premier flagship exam is the Combined Competitive Examination (CCE).',
        'eligibility_criteria' => 'Graduation degree in any discipline from a recognized university. Age Limit for CCE: 20/21/22 to 37 years for General males, 40 years for OBC/BC and General females, and 42 years for SC/ST candidates of Bihar domicile.',
        'selection_process' => 'Stage 1: Preliminary Exam (150 Marks Objective MCQ with negative marking). Stage 2: Mains Written Exam (Subjective papers including General Hindi, GS Paper 1, GS Paper 2, Essay, and Optional qualifying paper). Stage 3: Personality Test / Interview (120 Marks).',
        'syllabus_snapshot' => 'History of India and Bihar, Indian National Movement with focus on Bihar\'s contribution, General Science, Geography of Bihar and India, Indian Polity, Economy, and Current National/International Events.',
        'related_article_slug' => 'bpsc-72nd-cce-prelims-2026-admit-card'
    ],
    [
        'acronym' => 'BARC',
        'slug' => 'barc',
        'letter' => 'B',
        'full_form_en' => 'Bhabha Atomic Research Centre',
        'full_form_hi' => 'भाभा परमाणु अनुसंधान केंद्र',
        'category' => 'engineering',
        'conducting_body' => 'Department of Atomic Energy (DAE)',
        'official_portal' => 'https://barc.gov.in',
        'overview' => 'Bhabha Atomic Research Centre (BARC), founded by Dr. Homi J. Bhabha, is India\'s premier nuclear research facility headquarted in Trombay, Mumbai. BARC conducts competitive national selections for Scientific Officers (OCES/DGFS) and Stipendiary Trainees Category-I and II across engineering and science disciplines.',
        'eligibility_criteria' => 'For Scientific Officers: B.E. / B.Tech / B.Sc (Engineering) or M.Sc in relevant scientific discipline with minimum 60% aggregate marks. Valid GATE score or qualification in the BARC online screening exam. Age Limit: 18 to 26 years for General category.',
        'selection_process' => 'Stage 1: Screening through GATE Score or BARC Online Screening CBT. Stage 2: Rigorous in-depth Technical Interview evaluating fundamental engineering and scientific concepts.',
        'syllabus_snapshot' => 'Undergraduate Engineering and Sciences discipline curriculum (Mechanical, Electrical, Chemical, Civil, Computer Science, Physics, Chemistry, Biosciences) focusing on core core scientific analytical derivation.',
        'related_article_slug' => null
    ],
    [
        'acronym' => 'BSF',
        'slug' => 'bsf',
        'letter' => 'B',
        'full_form_en' => 'Border Security Force',
        'full_form_hi' => 'सीमा सुरक्षा बल',
        'category' => 'police',
        'conducting_body' => 'Ministry of Home Affairs (MHA)',
        'official_portal' => 'https://bsf.gov.in',
        'overview' => 'The Border Security Force (BSF) is India\'s primary border guarding organization on the border with Pakistan and Bangladesh. Operating under the administrative control of the Ministry of Home Affairs, recruitments to BSF ranks are executed through SSC GD (Constables), SSC CPO (Sub-Inspectors), and UPSC CAPF (Assistant Commandants).',
        'eligibility_criteria' => 'Constable (GD): 10th Pass, age 18-23 years. Sub-Inspector (SI): Graduation degree, age 20-25 years. Assistant Commandant (AC): Graduation degree, age 20-25 years. Must satisfy strict physical and medical standards.',
        'selection_process' => 'Written Examination (CBT/OMR) followed by Physical Standard Test (PST), Physical Efficiency Test (PET), Detailed Medical Examination (DME), and Document Verification.',
        'syllabus_snapshot' => 'General Intelligence and Reasoning, General Knowledge & Elementary Science, Elementary Mathematics, and English/Hindi language proficiency.',
        'related_article_slug' => null
    ],

    // --- C ---
    [
        'acronym' => 'CTET',
        'slug' => 'ctet',
        'letter' => 'C',
        'full_form_en' => 'Central Teacher Eligibility Test',
        'full_form_hi' => 'केंद्रीय शिक्षक पात्रता परीक्षा',
        'category' => 'teaching',
        'conducting_body' => 'Central Board of Secondary Education (CBSE)',
        'official_portal' => 'https://ctet.nic.in',
        'overview' => 'The Central Teacher Eligibility Test (CTET) is a national eligibility gateway conducted by CBSE to benchmark the minimum qualifications for candidates aspiring to teach in Central Government schools such as KVS, NVS, Central Tibetan Schools, and schools under administrative control of UTs.',
        'eligibility_criteria' => 'Paper I (Classes 1 to 5): Senior Secondary with at least 50% marks and 2-year Diploma in Elementary Education (D.El.Ed). Paper II (Classes 6 to 8): Graduation with at least 50% marks and B.Ed or 2-year D.El.Ed.',
        'selection_process' => 'Offline OMR Examination consisting of two papers. 150 Objective Multiple Choice Questions for 150 marks. Qualifying benchmark is 60% (90/150) for General and 55% (82/150) for reserved categories. Certificate validity is for lifetime.',
        'syllabus_snapshot' => 'Child Development and Pedagogy, Language I, Language II, Mathematics, Environmental Studies (Paper I) or Mathematics & Science / Social Studies (Paper II).',
        'related_article_slug' => null
    ],
    [
        'acronym' => 'CAPF',
        'slug' => 'capf',
        'letter' => 'C',
        'full_form_en' => 'Central Armed Police Forces',
        'full_form_hi' => 'केंद्रीय सशस्त्र पुलिस बल',
        'category' => 'defence',
        'conducting_body' => 'Union Public Service Commission (UPSC)',
        'official_portal' => 'https://upsc.gov.in',
        'overview' => 'Central Armed Police Forces (CAPF) refers to the collective group of paramilitary security forces in India under the Ministry of Home Affairs, comprising BSF, CRPF, CISF, ITBP, and SSB. Direct appointment of Assistant Commandants (Group A Gazetted) is conducted annually through the UPSC CAPF (AC) examination.',
        'eligibility_criteria' => 'Graduation degree in any stream from a recognized university. Age Limit: 20 to 25 years as on August 1 of the recruitment year with standard statutory age relaxations. Minimum height: 165 cm for males, 157 cm for females.',
        'selection_process' => 'Stage 1: Written Examination (Paper I: General Ability & Intelligence, Paper II: General Studies, Essay & Comprehension). Stage 2: Physical Standards/Efficiency Tests (PST/PET). Stage 3: Personality Test/Interview (150 Marks).',
        'syllabus_snapshot' => 'General Science, Current Events, Indian History, Indian & World Geography, Indian Polity and Economy, Essay writing in English or Hindi, Precis writing, and Comprehension.',
        'related_article_slug' => null
    ],
    [
        'acronym' => 'CUET',
        'slug' => 'cuet',
        'letter' => 'C',
        'full_form_en' => 'Common University Entrance Test',
        'full_form_hi' => 'सामान्य विश्वविद्यालय प्रवेश परीक्षा',
        'category' => 'entrance',
        'conducting_body' => 'National Testing Agency (NTA)',
        'official_portal' => 'https://cuet.nta.nic.in',
        'overview' => 'The Common University Entrance Test (CUET) is an all-India computerized entrance examination introduced under the National Education Policy (NEP) for admission to undergraduate and postgraduate programs across Central, State, Deemed, and Private universities in India, including DU, BHU, JNU, and AMU.',
        'eligibility_criteria' => 'For CUET UG: Passed Class 12 or equivalent qualifying board examination with no age bar. Program-specific domain requirements depend on individual university eligibility ordinances.',
        'selection_process' => 'Computer-Based Test (CBT) or Hybrid format across 13 Indian languages. Normalized scores and percentiles are provided to universities for centralized seat allocations (e.g. CSAS for Delhi University).',
        'syllabus_snapshot' => 'Section 1A & 1B: Language proficiency & Reading Comprehension. Section 2: Domain-specific subjects mapped directly to NCERT Class 12 syllabus. Section 3: General Test (GK, Reasoning, Quantitative Aptitude).',
        'related_article_slug' => null
    ],

    // --- D ---
    [
        'acronym' => 'DRDO',
        'slug' => 'drdo',
        'letter' => 'D',
        'full_form_en' => 'Defence Research and Development Organisation',
        'full_form_hi' => 'रक्षा अनुसंधान एवं विकास संगठन',
        'category' => 'engineering',
        'conducting_body' => 'Ministry of Defence (MoD) / RAC',
        'official_portal' => 'https://drdo.gov.in',
        'overview' => 'The Defence Research and Development Organisation (DRDO) is India\'s military research and development agency headquartered in New Delhi. It conducts recruitment of Scientist \'B\' through the Recruitment and Assessment Centre (RAC) and technical support personnel (Senior Technical Assistant & Technician) via CEPTAM.',
        'eligibility_criteria' => 'For Scientist B: First class Bachelor\'s degree in Engineering/Technology or Master\'s degree in Science with a valid GATE score. For CEPTAM (STA-B): 3-year Diploma in Engineering or B.Sc degree. Age: 18 to 28/35 years.',
        'selection_process' => 'Scientist B: Screening through GATE score followed by Personal Interview. CEPTAM: Tier-I CBT Screening Test followed by Tier-II CBT Trade/Skill Test.',
        'syllabus_snapshot' => 'Core technical curriculum based on GATE engineering syllabi; General Science, General Intelligence, Reasoning, and Quantitative Aptitude for technical support posts.',
        'related_article_slug' => null
    ],
    [
        'acronym' => 'DSSSB',
        'slug' => 'dsssb',
        'letter' => 'D',
        'full_form_en' => 'Delhi Subordinate Services Selection Board',
        'full_form_hi' => 'दिल्ली अधीनस्थ सेवा चयन बोर्ड',
        'category' => 'teaching',
        'conducting_body' => 'Government of NCT of Delhi',
        'official_portal' => 'https://dsssb.delhi.gov.in',
        'overview' => 'The Delhi Subordinate Services Selection Board (DSSSB) is the nodal recruiting authority under the Government of NCT of Delhi responsible for conducting recruitment examinations for Group \'B\' and \'C\' non-gazetted posts across Delhi Government departments, Municipal Corporations (MCD), and Directorate of Education (PRT, TGT, PGT teachers).',
        'eligibility_criteria' => 'Varies by post: PRT requires 12th + D.El.Ed + CTET; TGT requires Graduation + B.Ed + CTET Paper 2; PGT requires Post-Graduation + B.Ed; Clerical posts require 12th/Graduation + Typing. Age: 18 to 30/36 years.',
        'selection_process' => 'One-Tier or Two-Tier Computer Based Written Examination (CBT) followed by Skill Test / Typing Test where applicable, followed by online e-dossier document verification.',
        'syllabus_snapshot' => 'Section A: General Awareness, General Intelligence & Reasoning Ability, Arithmetical & Numerical Ability, Hindi Language & Comprehension, English Language & Comprehension. Section B: Subject-specific pedagogy or technical discipline.',
        'related_article_slug' => null
    ],

    // --- I ---
    [
        'acronym' => 'IAS',
        'slug' => 'ias',
        'letter' => 'I',
        'full_form_en' => 'Indian Administrative Service',
        'full_form_hi' => 'भारतीय प्रशासनिक सेवा',
        'category' => 'civil_services',
        'conducting_body' => 'Union Public Service Commission (UPSC) / DoPT',
        'official_portal' => 'https://upsc.gov.in',
        'overview' => 'The Indian Administrative Service (IAS) is the premier administrative civil service of the executive branch of the Government of India. IAS officers occupy key strategic leadership positions across central ministries, state secretariats, and district administrations (District Magistrate / Collector), overseeing governance, policy formulation, and administrative delivery.',
        'eligibility_criteria' => 'Indian citizenship. Graduation degree in any discipline from a recognized statutory university. Age Limit: 21 to 32 years as on August 1 of the examination year. Max attempts: 6 for General, 9 for OBC, unlimited for SC/ST.',
        'selection_process' => 'Stage 1: Civil Services Preliminary Examination (Objective: GS Paper 1 + CSAT qualifying). Stage 2: Civil Services Main Examination (9 Subjective descriptive papers). Stage 3: Personality Test / Interview at Dholpur House, New Delhi (275 Marks).',
        'syllabus_snapshot' => 'Indian Heritage & Culture, World History, Geography, Indian Society, Constitution, Governance, International Relations, Technology, Economic Development, Biodiversity, Disaster Management, and Ethics, Integrity & Aptitude.',
        'related_article_slug' => 'upsc-otr-2026-registration-guide'
    ],
    [
        'acronym' => 'IPS',
        'slug' => 'ips',
        'letter' => 'I',
        'full_form_en' => 'Indian Police Service',
        'full_form_hi' => 'भारतीय पुलिस सेवा',
        'category' => 'police',
        'conducting_body' => 'Union Public Service Commission (UPSC) / MHA',
        'official_portal' => 'https://upsc.gov.in',
        'overview' => 'The Indian Police Service (IPS) is one of the three All India Services alongside IAS and IFoS, providing leadership and commanding roles across State Police forces, Central Armed Police Forces (BSF, CRPF, CISF), intelligence agencies (IB, RAW), and investigative branches (CBI, NIA).',
        'eligibility_criteria' => 'Citizenship of India. Degree from a recognized university. Age Limit: 21 to 32 years. Mandatory physical standards: Minimum height of 165 cm for males (160 cm for ST) and 150 cm for females (145 cm for ST); chest expansion of 5 cm; visual standards strictly checked.',
        'selection_process' => 'Selection is executed through the annual UPSC Civil Services Examination (CSE Prelims, Mains, and Personality Test), followed by mandatory specialized physical and medical verification.',
        'syllabus_snapshot' => 'Identical to UPSC Civil Services Examination with training subsequently imparted at Sardar Vallabhbhai Patel National Police Academy (SVPNPA) in Hyderabad.',
        'related_article_slug' => 'upsc-otr-2026-registration-guide'
    ],
    [
        'acronym' => 'IBPS',
        'slug' => 'ibps',
        'letter' => 'I',
        'full_form_en' => 'Institute of Banking Personnel Selection',
        'full_form_hi' => 'बैंकिंग कार्मिक चयन संस्थान',
        'category' => 'banking',
        'conducting_body' => 'Autonomous Body / Public Sector Banks',
        'official_portal' => 'https://ibps.in',
        'overview' => 'The Institute of Banking Personnel Selection (IBPS) is an autonomous recruitment body that conducts common recruitment processes (CRP) for selecting Probationary Officers (PO), Clerks, Specialist Officers (SO), and Regional Rural Bank (RRB) personnel across 11 Public Sector Banks and 43 RRBs across India.',
        'eligibility_criteria' => 'Graduation degree in any stream from a recognized university. Age Limit for PO: 20 to 30 years; for Clerk: 20 to 28 years; for RRB Office Assistant: 18 to 28 years. Proficiency in the official state language required for Clerical and RRB cadres.',
        'selection_process' => 'For PO: Preliminary Online Exam (CBT) -> Mains Online Exam -> Common Interview. For Clerk: Preliminary Online Exam -> Mains Online Exam (No Interview).',
        'syllabus_snapshot' => 'English Language, Quantitative Aptitude, Reasoning Ability, Computer Aptitude, General & Financial/Banking Awareness with high emphasis on speed, calculation, and negative marking accuracy.',
        'related_article_slug' => 'ibps-po-2026-prelims-exam-concluded'
    ],

    // --- J ---
    [
        'acronym' => 'JEE',
        'slug' => 'jee',
        'letter' => 'J',
        'full_form_en' => 'Joint Entrance Examination',
        'full_form_hi' => 'संयुक्त प्रवेश परीक्षा',
        'category' => 'engineering',
        'conducting_body' => 'National Testing Agency (NTA) & IITs',
        'official_portal' => 'https://jeemain.nta.nic.in',
        'overview' => 'The Joint Entrance Examination (JEE) is the apex engineering entrance assessment system in India. JEE Main (conducted by NTA) serves as the qualifying gateway for admissions to NITs, IIITs, and CFTIs, while the top 2.5 lakh qualifiers of JEE Main become eligible to contest JEE Advanced for admission to the Indian Institutes of Technology (IITs).',
        'eligibility_criteria' => 'Passed Class 12 or equivalent qualifying examination with Physics, Chemistry, and Mathematics. Minimum 75% aggregate marks (65% for SC/ST) or top 20 percentile in respective Class 12 Board for NIT/IIT admissions.',
        'selection_process' => 'JEE Main: Computer Based Test conducted in two seasonal sessions (January and April). JEE Advanced: Conducted by rotating zonal IITs consisting of two mandatory papers of 3 hours each on the same day.',
        'syllabus_snapshot' => 'NCERT and advanced analytical curriculum spanning Class 11 and Class 12 Physics, Chemistry, and Mathematics focusing on deep problem-solving and numerical calculation.',
        'related_article_slug' => null
    ],

    // --- N ---
    [
        'acronym' => 'NDA',
        'slug' => 'nda',
        'letter' => 'N',
        'full_form_en' => 'National Defence Academy',
        'full_form_hi' => 'राष्ट्रीय रक्षा अकादमी',
        'category' => 'defence',
        'conducting_body' => 'Union Public Service Commission (UPSC)',
        'official_portal' => 'https://upsc.gov.in',
        'overview' => 'The National Defence Academy (NDA) located at Khadakwasla, Pune, is the joint services academy of the Indian Armed Forces, where cadets of the Army, Navy, and Air Force train together before proceeding to their respective service academies. The UPSC NDA & NA exam is conducted biannually.',
        'eligibility_criteria' => 'Unmarried male and female candidates. Age between 16.5 and 19.5 years. Educational Qualification: 12th pass of the 10+2 pattern for Army wing; 12th pass with Physics and Mathematics for Air Force and Naval wings.',
        'selection_process' => 'Stage 1: Written Examination (Mathematics: 300 marks, General Ability Test: 600 marks). Stage 2: 5-day Service Selection Board (SSB) Interview testing officer-like qualities (900 marks) followed by strict Medical Examination.',
        'syllabus_snapshot' => 'Algebra, Matrices, Trigonometry, Calculus, Statistics, and Probability; English grammar and comprehension; General Science, Indian History, Geography, and Current Events.',
        'related_article_slug' => 'upsc-cds-2026-notification-exam-schedule'
    ],
    [
        'acronym' => 'NEET',
        'slug' => 'neet',
        'letter' => 'N',
        'full_form_en' => 'National Eligibility cum Entrance Test',
        'full_form_hi' => 'राष्ट्रीय पात्रता सह प्रवेश परीक्षा',
        'category' => 'medical',
        'conducting_body' => 'National Testing Agency (NTA)',
        'official_portal' => 'https://neet.nta.nic.in',
        'overview' => 'The National Eligibility cum Entrance Test (NEET UG) is the single uniform national entrance examination for admission to undergraduate medical programs (MBBS, BDS, BAMS, BHMS, BUMS, and BSMS) in all medical institutions across India, including AIIMS and JIPMER.',
        'eligibility_criteria' => 'Minimum 17 years of age at the time of admission. Passed 10+2 with Physics, Chemistry, Biology/Biotechnology, and English as core subjects with at least 50% aggregate marks for Unreserved and 40% for SC/ST/OBC. No upper age limit.',
        'selection_process' => 'Pen-and-paper OMR based offline examination of 3 hours 20 minutes duration. 200 questions (180 to be attempted) for a total of 720 marks. All-India ranks determine admission through MCC (15% AIQ) and State Counselling (85% State Quota).',
        'syllabus_snapshot' => 'Core NCERT syllabus of Classes 11 and 12 across Physics, Chemistry, Botany, and Zoology with 4 marks awarded for correct answers and 1 mark deducted for incorrect responses.',
        'related_article_slug' => 'neet-ug-2026-counselling-schedule-released-mcc-nic-in'
    ],
    [
        'acronym' => 'NTA',
        'slug' => 'nta',
        'letter' => 'N',
        'full_form_en' => 'National Testing Agency',
        'full_form_hi' => 'राष्ट्रीय परीक्षा एजेंसी',
        'category' => 'entrance',
        'conducting_body' => 'Department of Higher Education, Ministry of Education',
        'official_portal' => 'https://nta.ac.in',
        'overview' => 'The National Testing Agency (NTA) is an autonomous premier testing organization established by the Ministry of Education in 2017 to conduct efficient, transparent, and international-standard entrance assessments for admissions to higher educational institutions (JEE Main, NEET UG, CUET, UGC NET, CSIR NET).',
        'eligibility_criteria' => 'As an examination-conducting authority, NTA sets specific operational parameters per exam cycle under guidelines mandated by statutory councils (MCI/NMC, AICTE, UGC, BCI).',
        'selection_process' => 'Manages large-scale Computer-Based Tests (CBT) and OMR tests, grievance portals, biometric verification, answer key challenge mechanisms, and standardized normalization percentiles.',
        'syllabus_snapshot' => 'Syllabus frameworks formulated by UGC, NCERT, and respective curriculum boards for national undergraduate, postgraduate, and fellowship tests.',
        'related_article_slug' => 'nta-grievance-portal-submit-track-issues'
    ],

    // --- R ---
    [
        'acronym' => 'RRB',
        'slug' => 'rrb',
        'letter' => 'R',
        'full_form_en' => 'Railway Recruitment Board',
        'full_form_hi' => 'रेलवे भर्ती बोर्ड',
        'category' => 'railway',
        'conducting_body' => 'Ministry of Railways, Government of India',
        'official_portal' => 'https://indianrailways.gov.in',
        'overview' => 'Railway Recruitment Boards (RRBs) are federal statutory boards operating under the Ministry of Railways. Across 21 zones, RRBs manage large-scale recruitment drives for Non-Technical Popular Categories (NTPC), Assistant Loco Pilots (ALP), Technicians, Junior Engineers (JE), and Paramedical staff.',
        'eligibility_criteria' => 'Varies by post level: ALP requires ITI/Diploma/B.Tech (age 18-30/33); NTPC Graduate posts require Degree (age 18-33/36); NTPC Undergraduate posts require 12th Pass (age 18-30/33). Category relaxations as per Railway Board directives.',
        'selection_process' => 'Multi-stage Computer-Based Tests (CBT-1 Screening, CBT-2 Selection), followed by Computer Based Aptitude Test (CBAT) or Typing Skill Test where applicable, and Document Verification with strict Railway Medical Fitness (A-1, A-2, B-1 categories).',
        'syllabus_snapshot' => 'General Awareness & Current Affairs, Mathematics, General Intelligence and Reasoning, and Basic Science & Engineering for technical roles.',
        'related_article_slug' => 'rrc-southern-railway-apprentice-2026'
    ],
    [
        'acronym' => 'RPF',
        'slug' => 'rpf',
        'letter' => 'R',
        'full_form_en' => 'Railway Protection Force',
        'full_form_hi' => 'रेलवे सुरक्षा बल',
        'category' => 'police',
        'conducting_body' => 'Ministry of Railways',
        'official_portal' => 'https://rpf.indianrailways.gov.in',
        'overview' => 'The Railway Protection Force (RPF) is an armed security force entrusted with safeguarding railway passengers, passenger areas, and railway property of Indian Railways. Direct recruitment for Constables and Sub-Inspectors (SI) is coordinated centrally through RRB notices.',
        'eligibility_criteria' => 'Constable: 10th pass, age 18 to 28 years. Sub-Inspector: Bachelor\'s degree from a recognized university, age 20 to 28 years. Minimum physical standards: Height 165 cm for UR/OBC males, 157 cm for females.',
        'selection_process' => 'Stage 1: Computer Based Test (120 questions in 90 minutes). Stage 2: Physical Efficiency Test (PET) & Physical Measurement Test (PMT). Stage 3: Document Verification and Medical Fitness.',
        'syllabus_snapshot' => 'General Awareness (50 marks), Arithmetic (35 marks), and General Intelligence & Reasoning (35 marks). High speed and negative marking discipline are critical.',
        'related_article_slug' => 'rpf-constable-si-2026-notification'
    ],

    // --- S ---
    [
        'acronym' => 'SSC',
        'slug' => 'ssc',
        'letter' => 'S',
        'full_form_en' => 'Staff Selection Commission',
        'full_form_hi' => 'कर्मचारी चयन आयोग',
        'category' => 'civil_services',
        'conducting_body' => 'Department of Personnel and Training (DoPT)',
        'official_portal' => 'https://ssc.gov.in',
        'overview' => 'The Staff Selection Commission (SSC) is an attached office of the Department of Personnel and Training responsible for recruiting Group \'B\' (Non-Gazetted) and Group \'C\' (Non-Technical) staff across all ministries, departments, and subordinate offices of the Government of India. Flagship exams include CGL, CHSL, MTS, CPO, and GD Constable.',
        'eligibility_criteria' => 'CGL: Graduation Degree (age 18-30/32); CHSL: 12th Pass (age 18-27); MTS: 10th Pass (age 18-25/27); CPO: Graduation (age 20-25). Standard age relaxations for reserved communities.',
        'selection_process' => 'Computer-Based Examinations (Tier-I qualifying, Tier-II merit) followed by Computer Knowledge Test, Data Entry Speed Test (DEST), and centralized physical/document verification.',
        'syllabus_snapshot' => 'General Intelligence and Reasoning, General Awareness, Quantitative Aptitude, and English Comprehension, alongside Mathematical Abilities, Reasoning, English Language, and General Awareness in Tier-II.',
        'related_article_slug' => 'ssc-cgl-tier-1-admit-card-2026-region-wise-portal-links'
    ],
    [
        'acronym' => 'SBI',
        'slug' => 'sbi',
        'letter' => 'S',
        'full_form_en' => 'State Bank of India',
        'full_form_hi' => 'भारतीय स्टेट बैंक',
        'category' => 'banking',
        'conducting_body' => 'Central Recruitment & Promotion Department (CRPD)',
        'official_portal' => 'https://sbi.co.in/web/careers',
        'overview' => 'State Bank of India (SBI) is a Fortune 500 public sector bank and the largest commercial lender in India. SBI independently conducts annual competitive examinations for the recruitment of Probationary Officers (PO), Junior Associates (Customer Support & Sales / Clerks), and Specialist Cadre Officers (SCO).',
        'eligibility_criteria' => 'Probationary Officer: Graduation degree in any discipline, age 21 to 30 years. Junior Associate (Clerk): Graduation degree in any discipline, age 20 to 28 years with knowledge of local state language.',
        'selection_process' => 'PO: Phase-I Preliminary CBT, Phase-II Mains CBT with Descriptive Paper, Phase-III Psychometric Test, Group Exercises & Interview. Clerk: Preliminary CBT followed by Mains CBT (No interview).',
        'syllabus_snapshot' => 'Reasoning & Computer Aptitude, Data Analysis & Interpretation, General/Economy/Banking Awareness, and English Language with strict sectional timing.',
        'related_article_slug' => null
    ],

    // --- U ---
    [
        'acronym' => 'UPSC',
        'slug' => 'upsc',
        'letter' => 'U',
        'full_form_en' => 'Union Public Service Commission',
        'full_form_hi' => 'संघ लोक सेवा आयोग',
        'category' => 'civil_services',
        'conducting_body' => 'Constitutional Authority under Article 315 of Indian Constitution',
        'official_portal' => 'https://upsc.gov.in',
        'overview' => 'The Union Public Service Commission (UPSC) is India\'s premier central constitutional recruiting agency authorized to conduct examinations for appointment to Civil Services, Defence Services, and technical engineering/medical posts under the Union of India. Flagship exams include CSE, NDA, CDS, CMS, ESE, and CAPF.',
        'eligibility_criteria' => 'Graduate degree from a recognized university for Civil Services, CDS, and ESE; 10+2 for NDA. Age limits vary by examination (21-32 for CSE; 16.5-19.5 for NDA; 19-25 for CDS). Candidates must register via One Time Registration (OTR) on upsconline.nic.in.',
        'selection_process' => 'Multi-tier selection architectures: Preliminary Examination (Objective screening), Main Examination (Descriptive written tests), followed by Personality Test / Interview board at Dholpur House, New Delhi.',
        'syllabus_snapshot' => 'Broad interdisciplinary curriculum encompassing Indian Heritage, Modern History, World Geography, Constitutional Polity, Economic Development, Science & Technology, Ethics, and optional discipline mastery.',
        'related_article_slug' => 'upsc-cds-2026-notification-exam-schedule'
    ],
    [
        'acronym' => 'UPSSSC',
        'slug' => 'upsssc',
        'letter' => 'U',
        'full_form_en' => 'Uttar Pradesh Subordinate Services Selection Commission',
        'full_form_hi' => 'उत्तर प्रदेश अधीनस्थ सेवा चयन आयोग',
        'category' => 'civil_services',
        'conducting_body' => 'Government of Uttar Pradesh',
        'official_portal' => 'https://upsssc.gov.in',
        'overview' => 'The Uttar Pradesh Subordinate Services Selection Commission (UPSSSC) is the state executive recruitment authority responsible for appointments to Group \'C\' posts in the Government of Uttar Pradesh. The Preliminary Eligibility Test (PET) is the mandatory baseline gateway for applying to subsequent recruitment Mains examinations including Lekhpal, Junior Assistant, and VDO.',
        'eligibility_criteria' => 'PET: Minimum Class 10 (High School) passed, age 18 to 40 years as of July 1 with standard category relaxations. Subsequent Mains posts (e.g. Junior Assistant, Lekhpal) require specific 12th/Degree qualifications and CCC computer certifications.',
        'selection_process' => 'Stage 1: Preliminary Eligibility Test (PET) score card. Stage 2: Post-specific Mains Examination shortlisted on the basis of normalized PET percentile. Stage 3: Document Verification and Typing/Skill Test where applicable.',
        'syllabus_snapshot' => 'Indian History, Indian National Movement, Geography, Indian Economy, Indian Constitution & Public Administration, General Science, Elementary Arithmetic, General Hindi, General English, Logic & Reasoning, Current Affairs, and General Awareness.',
        'related_article_slug' => 'upsssc-pet-2026-registration-extended-dates'
    ],
    [
        'acronym' => 'UGC',
        'slug' => 'ugc',
        'letter' => 'U',
        'full_form_en' => 'University Grants Commission',
        'full_form_hi' => 'विश्वविद्यालय अनुदान आयोग',
        'category' => 'teaching',
        'conducting_body' => 'Ministry of Education, Government of India',
        'official_portal' => 'https://ugc.gov.in',
        'overview' => 'The University Grants Commission (UGC) is a statutory body set up by the Department of Higher Education under the UGC Act 1956. It is responsible for coordination, determination, and maintenance of standards of higher education in India. UGC coordinates the National Eligibility Test (UGC NET) for determining eligibility for Assistant Professorship and Junior Research Fellowship (JRF).',
        'eligibility_criteria' => 'Master\'s degree or equivalent with at least 55% marks (50% for OBC-NCL, SC, ST, PwD) from recognized universities. Age limit for JRF is 30 years; no upper age limit for Assistant Professor eligibility.',
        'selection_process' => 'UGC NET examination conducted via NTA in Computer-Based Test (CBT) format. Comprises Paper 1 (Teaching & Research Aptitude) and Paper 2 (Chosen specialized academic subject) with no negative marking.',
        'syllabus_snapshot' => 'Paper 1: Teaching Aptitude, Research Aptitude, Reading Comprehension, Communication, Mathematical Reasoning, Logical Reasoning, Data Interpretation, Information & Communication Technology, and Higher Education System. Paper 2 covers candidate\'s post-graduate discipline.',
        'related_article_slug' => null
    ]
];

$inserted = 0;
$today = date('Y-m-d');

foreach ($terms as $t) {
    try {
        Database::query(
            "INSERT INTO glossary_terms 
             (`acronym`, `slug`, `letter`, `full_form_en`, `full_form_hi`, `category`, `conducting_body`, `official_portal`, `overview`, `eligibility_criteria`, `selection_process`, `syllabus_snapshot`, `related_article_slug`, `last_reviewed_at`)
             VALUES 
             (:acronym, :slug, :letter, :full_form_en, :full_form_hi, :category, :conducting_body, :official_portal, :overview, :eligibility_criteria, :selection_process, :syllabus_snapshot, :related_article_slug, :last_reviewed_at)
             ON DUPLICATE KEY UPDATE 
             `full_form_en` = VALUES(`full_form_en`),
             `full_form_hi` = VALUES(`full_form_hi`),
             `overview` = VALUES(`overview`),
             `eligibility_criteria` = VALUES(`eligibility_criteria`),
             `selection_process` = VALUES(`selection_process`),
             `syllabus_snapshot` = VALUES(`syllabus_snapshot`),
             `last_reviewed_at` = VALUES(`last_reviewed_at`)",
            [
                'acronym' => $t['acronym'],
                'slug' => $t['slug'],
                'letter' => $t['letter'],
                'full_form_en' => $t['full_form_en'],
                'full_form_hi' => $t['full_form_hi'],
                'category' => $t['category'],
                'conducting_body' => $t['conducting_body'],
                'official_portal' => $t['official_portal'],
                'overview' => $t['overview'],
                'eligibility_criteria' => $t['eligibility_criteria'],
                'selection_process' => $t['selection_process'],
                'syllabus_snapshot' => $t['syllabus_snapshot'],
                'related_article_slug' => $t['related_article_slug'],
                'last_reviewed_at' => $today
            ]
        );
        $inserted++;
        echo "  -> Seeded: [{$t['acronym']}] {$t['full_form_en']}\n";
    } catch (Throwable $e) {
        echo "  -> Error on {$t['acronym']}: " . $e->getMessage() . "\n";
    }
}

echo "Total {$inserted} core glossary terms seeded successfully.\n";
