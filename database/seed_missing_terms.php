<?php
/**
 * Sarkari.online - Seed Database with High-Value Ranking & State Commission Acronyms
 * Adds NSP, NTA, CUET, UPSSSC, BSSC, BPSC, RPSC, CTET, TGT, PRT, CAPF, CISF, CRPF
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GlossaryService;

GlossaryService::initTable();

$missingTerms = [
    [
        "acronym" => "NSP",
        "slug" => "nsp",
        "letter" => "N",
        "full_form_en" => "National Scholarship Portal",
        "full_form_hi" => "राष्ट्रीय छात्रवृत्ति पोर्टल",
        "category" => "entrance",
        "conducting_body" => "Ministry of Electronics and Information Technology (MeitY), Government of India",
        "official_portal" => "https://scholarships.gov.in",
        "overview" => "The National Scholarship Portal (NSP) is a dedicated digital Mission Mode Project under the Digital India initiative. It acts as a single-window centralized common application and disbursement gateway for educational scholarships offered by central ministries, UGC, AICTE, and state governments across pre-matric, post-matric, and higher technical studies.",
        "eligibility_criteria" => "Enrolled student at a recognized school, college, or university with a minimum prescribed academic score (typically 50% or above). Annual family income ceiling ranges from Rs 1.5 Lakh to Rs 8 Lakh depending on the specific Central or State scholarship scheme.",
        "selection_process" => "Online application via One-Time Registration (OTR), biometric Aadhaar authentication, institutional verification by the school/college nodal officer, district/state approval, and Direct Benefit Transfer (DBT) into the student's Aadhaar-seeded bank account.",
        "syllabus_snapshot" => "Merit scholarships administered via NSP include National Means-cum-Merit Scholarship (NMMS), Central Sector Scheme of Scholarship for College and University Students, and Post-Matric Scholarships for Minorities and SC/ST candidates.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "NTA",
        "slug" => "nta",
        "letter" => "N",
        "full_form_en" => "National Testing Agency",
        "full_form_hi" => "राष्ट्रीय परीक्षा एजेंसी",
        "category" => "entrance",
        "conducting_body" => "Department of Higher Education, Ministry of Education, Government of India",
        "official_portal" => "https://nta.ac.in",
        "overview" => "The National Testing Agency (NTA) is an autonomous, self-sustained statutory testing organization registered under the Societies Registration Act 1860. Established following Union Cabinet approval in 2017, NTA conducts premier national entrance examinations including NEET-UG, JEE-Main, CUET, UGC-NET, and CSIR-NET using specialized Computer-Based Testing (CBT) and psychometric protocols.",
        "eligibility_criteria" => "Eligibility varies by examination: 10+2 with Physics, Chemistry, Mathematics/Biology for JEE Main / NEET UG; Master's degree with minimum 55% marks (50% for reserved categories) for UGC-NET; Bachelor's degree for graduate entrance tests.",
        "selection_process" => "Conducts nationwide Computer-Based Tests (CBT) and Pen-and-Paper OMR assessments across secure testing centers, computes normalized percentile scores (NTA Score), and releases official answer keys and merit ranks for central counselling.",
        "syllabus_snapshot" => "Harmonized with National Education Policy (NEP) guidelines, NCERT Class 11 and 12 curricula for undergraduate competitive tests, and specialized university postgraduate syllabi for UGC-NET and CSIR-NET examinations.",
        "related_article_slug" => "neet-ug-2026-counselling-schedule-released-mcc-nic-in"
    ],
    [
        "acronym" => "CUET",
        "slug" => "cuet",
        "letter" => "C",
        "full_form_en" => "Common University Entrance Test",
        "full_form_hi" => "सामान्य विश्वविद्यालय प्रवेश परीक्षा",
        "category" => "entrance",
        "conducting_body" => "National Testing Agency (NTA) / University Grants Commission (UGC)",
        "official_portal" => "https://exams.nta.ac.in/CUET-UG",
        "overview" => "The Common University Entrance Test (CUET) is a standardized national entrance examination for admission into undergraduate (CUET-UG) and postgraduate (CUET-PG) programs across all 45+ Central Universities, as well as participating State, Deemed, and Private Universities across India.",
        "eligibility_criteria" => "CUET-UG: Passed Class 12 or equivalent examination from a recognized board; no age limit. CUET-PG: Recognized Bachelor's degree in relevant subject with minimum prescribed marks as mandated by participating universities.",
        "selection_process" => "Computer-Based Test (CBT) comprising Section 1A & 1B (Languages), Section 2 (Domain-Specific Subjects), and Section 3 (General Test). University-level merit lists prepared based on normalized NTA scores followed by online seat allotment.",
        "syllabus_snapshot" => "Section 1: Reading comprehension, literary aptitude, vocabulary. Section 2: Class 12 NCERT curriculum across chosen domain subjects. Section 3: General Knowledge, Current Affairs, Numerical Ability, Reasoning, Quantitative Reasoning.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "UPSSSC",
        "slug" => "upsssc",
        "letter" => "U",
        "full_form_en" => "Uttar Pradesh Subordinate Services Selection Commission",
        "full_form_hi" => "उत्तर प्रदेश अधीनस्थ सेवा चयन आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Uttar Pradesh",
        "official_portal" => "https://upsssc.gov.in",
        "overview" => "The Uttar Pradesh Subordinate Services Selection Commission (UPSSSC) is the state statutory recruiting body established under the UPSSSC Act 2014, headquartered in Lucknow. It conducts recruitments for Group 'C' and Group 'B' non-gazetted administrative, technical, and revenue positions including Lekhpal, Junior Assistant, Village Development Officer (VDO), and Forest Guard.",
        "eligibility_criteria" => "Preliminary Eligibility Test (PET) scorecard mandatory for Group C posts. Educational qualification ranges from 10+2 with CCC computer certificate (Junior Assistant) to Graduation with relevant diploma (VDO/Lekhpal). Age limit: 18/21 to 40 years with state quota relaxations.",
        "selection_process" => "Tier 1: Annual Preliminary Eligibility Test (PET). Tier 2: Post-specific Mains Written Examination (shortlisting based on PET percentile cutoff). Tier 3: Typing / Skill Test (for ministerial posts) followed by Document Verification.",
        "syllabus_snapshot" => "PET: General Knowledge, Indian History & National Movement, Indian Constitution, Arithmetic, General Hindi, Logical Reasoning, Graph & Table Interpretation. Mains: Post-specific statutory duties, rural economy, and advanced Hindi.",
        "related_article_slug" => "upsssc-junior-assistant-lekhpal-2026-admit-card"
    ],
    [
        "acronym" => "BSSC",
        "slug" => "bssc",
        "letter" => "B",
        "full_form_en" => "Bihar Staff Selection Commission",
        "full_form_hi" => "बिहार कर्मचारी चयन आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Bihar",
        "official_portal" => "https://bssc.bihar.gov.in",
        "overview" => "The Bihar Staff Selection Commission (BSSC), located in Patna, is mandated to conduct competitive examinations and interviews for appointment to Group 'C' and non-gazetted technical posts under the Government of Bihar, including the Inter Level Combined Competitive Examination and Graduate Level (CGL) recruitments.",
        "eligibility_criteria" => "Inter Level: Intermediate (10+2) from a recognized Board. Graduate Level: Bachelor's degree in any discipline. Age limit: 21 to 37 years for Male General, 40 years for Female/BC/EBC, and 42 years for SC/ST.",
        "selection_process" => "Stage 1: Preliminary Objective Screening Examination (150 questions, 600 marks). Stage 2: Mains Examination (General Hindi qualifying paper + General Studies/Maths paper). Stage 3: Typing / Computer Proficiency / Physical Efficiency Test (as applicable).",
        "syllabus_snapshot" => "General Studies (History, Geography, Polity, Bihar Special), General Science (Physics, Chemistry, Biology), Mathematics, and Mental Ability & Logical Reasoning.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "BPSC",
        "slug" => "bpsc",
        "letter" => "B",
        "full_form_en" => "Bihar Public Service Commission",
        "full_form_hi" => "बिहार लोक सेवा आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Bihar",
        "official_portal" => "https://bpsc.bih.nic.in",
        "overview" => "The Bihar Public Service Commission (BPSC) is the constitutional authority under Article 315 of the Constitution of India responsible for recruiting officers into the Bihar Administrative Service (BAS), Bihar Police Service (BPS), and other provincial civil services, as well as state school teachers under the Teacher Recruitment Examination (TRE).",
        "eligibility_criteria" => "Bachelor's degree from a recognized university. Age limit: 20/21/22 to 37 years for General Male; relaxation up to 40 years for EBC/BC and 42 years for SC/ST.",
        "selection_process" => "Combined Competitive Examination (CCE): Prelims (150 Marks Objective MCQ with negative marking) -> Mains (General Hindi qualifying, GS Paper 1, GS Paper 2, Essay, and Optional qualifying) -> Personal Interview (120 Marks).",
        "syllabus_snapshot" => "Prelims: General Science, Current Events, History of India and Bihar, Geography with Bihar rivers, Indian Polity & Economy, Indian National Movement. Mains: Modern Indian history, statistics, Indian Constitution, science & technology applications.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "RPSC",
        "slug" => "rpsc",
        "letter" => "R",
        "full_form_en" => "Rajasthan Public Service Commission",
        "full_form_hi" => "राजस्थान लोक सेवा आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Rajasthan",
        "official_portal" => "https://rpsc.rajasthan.gov.in",
        "overview" => "The Rajasthan Public Service Commission (RPSC), headquartered at Ghughra Ghati, Ajmer, is the apex constitutional recruitment body of Rajasthan. It conducts the Rajasthan Administrative Services (RAS / RTS) Combined Competitive Examination, School Lecturer, Assistant Professor, and Sub-Inspector recruitments.",
        "eligibility_criteria" => "Graduate degree in any discipline from a UGC recognized university. Age limit: 21 to 40 years with 5 years relaxation for Rajasthan native male reserved categories and 10 years for female reserved categories.",
        "selection_process" => "RAS Examination: Prelims (Single paper of 200 marks, 150 questions) -> Mains (4 Descriptive papers of 200 marks each: GS 1, GS 2, GS 3, General Hindi & English) -> Personality Test / Viva-Voce (100 Marks).",
        "syllabus_snapshot" => "Rajasthan Art, Culture, Heritage, History & Geography; Indian Polity, Economy & Science; Quantitative Aptitude & Reasoning; General Hindi (Grammar, Comprehension, Essay) and General English.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "CTET",
        "slug" => "ctet",
        "letter" => "C",
        "full_form_en" => "Central Teacher Eligibility Test",
        "full_form_hi" => "केंद्रीय शिक्षक पात्रता परीक्षा",
        "category" => "teaching",
        "conducting_body" => "Central Board of Secondary Education (CBSE)",
        "official_portal" => "https://ctet.nic.in",
        "overview" => "The Central Teacher Eligibility Test (CTET) is a national qualifying examination conducted biannually by CBSE in accordance with NCTE guidelines under the Right to Education (RTE) Act 2009. Clearing CTET is mandatory for appointment as teachers in Central Government schools (KVS, NVS, Central Tibetan Schools) and UT administrative schools.",
        "eligibility_criteria" => "Paper 1 (Class 1 to 5): Senior Secondary (50%) with 2-year Diploma in Elementary Education (D.El.Ed). Paper 2 (Class 6 to 8): Graduation with D.El.Ed or B.Ed with minimum 50% marks.",
        "selection_process" => "National OMR / CBT examination of 150 objective questions (150 marks, 2.5 hours). Qualifying mark is 60% (90 marks) for General and 55% (82 marks) for SC/ST/OBC. CTET qualifying certificate holds lifetime validity.",
        "syllabus_snapshot" => "Child Development & Pedagogy, Language 1 (Compulsory), Language 2 (Compulsory), Mathematics, and Environmental Studies (for Paper 1) or Mathematics & Science / Social Science (for Paper 2).",
        "related_article_slug" => null
    ],
    [
        "acronym" => "TGT",
        "slug" => "tgt",
        "letter" => "T",
        "full_form_en" => "Trained Graduate Teacher",
        "full_form_hi" => "प्रशिक्षित स्नातक शिक्षक",
        "category" => "teaching",
        "conducting_body" => "KVS, NVS, DSSSB, State School Education Boards",
        "official_portal" => "https://kvsangathan.nic.in",
        "overview" => "Trained Graduate Teacher (TGT) is a gazetted/non-gazetted teaching cadre in Indian government and autonomous secondary schools responsible for instructing students in Classes 6 through 10 in specialized disciplines such as Mathematics, Natural Science, Social Science, Hindi, English, and Sanskrit.",
        "eligibility_criteria" => "Four-year integrated degree course of Regional College of Education of NCERT or Bachelor's degree with at least 50% marks in the concerned subjects/combination and B.Ed degree; qualified CTET Paper-II. Maximum age: 35 years with statutory quota concessions.",
        "selection_process" => "Computer-Based Test (CBT) covering General English, General Hindi, General Awareness, Reasoning, Computer Literacy, Perspectives on Education & Leadership, and Subject-Specific Competence -> Professional Competency / Class Demo & Interview.",
        "syllabus_snapshot" => "Pedagogical perspectives (Understanding the Learner, Teaching-Learning Environment, School Organization), Subject Knowledge aligned with CBSE Secondary curriculum (NCERT Class 6-10 with graduation level difficulty).",
        "related_article_slug" => null
    ],
    [
        "acronym" => "PRT",
        "slug" => "prt",
        "letter" => "P",
        "full_form_en" => "Primary Teacher",
        "full_form_hi" => "प्राथमिक शिक्षक",
        "category" => "teaching",
        "conducting_body" => "KVS, NVS, DSSSB, State Basic Education Boards",
        "official_portal" => "https://kvsangathan.nic.in",
        "overview" => "Primary Teacher (PRT) is the foundational pedagogical cadre in government schools tasked with imparting elementary education to children in Classes 1 through 5, focusing on foundational literacy, numeracy (FLN), and holistic cognitive development.",
        "eligibility_criteria" => "Senior Secondary (10+2) with at least 50% marks and 2-year Diploma in Elementary Education (D.El.Ed) or 4-year B.El.Ed; qualified Central Teacher Eligibility Test (CTET Paper-I). Maximum age: 30 years with governmental relaxations.",
        "selection_process" => "Written Examination (CBT) covering General English, Hindi, General Knowledge, Reasoning Ability, Computer Literacy, Pedagogy -> Demo Class Teaching and Interview.",
        "syllabus_snapshot" => "Child Development and Pedagogy, Environmental Studies (EVS), Basic Mathematics, Hindi and English language comprehension.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "CAPF",
        "slug" => "capf",
        "letter" => "C",
        "full_form_en" => "Central Armed Police Forces",
        "full_form_hi" => "केंद्रीय सशस्त्र पुलिस बल",
        "category" => "defence",
        "conducting_body" => "Union Public Service Commission (UPSC) / Ministry of Home Affairs",
        "official_portal" => "https://upsc.gov.in",
        "overview" => "Central Armed Police Forces (CAPF) refers to the collective group of central internal security and border-guarding forces under the Ministry of Home Affairs (MHA), Government of India: BSF, CRPF, CISF, ITBP, and SSB. UPSC conducts an annual national competitive examination to directly recruit Assistant Commandants (AC - Group 'A' Gazetted Officers) across these five forces.",
        "eligibility_criteria" => "Bachelor's degree in any discipline from a recognized university. Age limit: 20 to 25 years with statutory age concessions for OBC (3 years) and SC/ST (5 years). Both male and female candidates meeting prescribed physical standards are eligible.",
        "selection_process" => "Stage 1: Written Examination (Paper 1: General Ability & Intelligence - 250 marks; Paper 2: General Studies, Essay & Comprehension - 200 marks). Stage 2: Physical Standards Test (PST) & Physical Efficiency Test (PET: 100m, 800m race, Long Jump, Shot Put). Stage 3: Medical Examination. Stage 4: Interview / Personality Test (150 Marks).",
        "syllabus_snapshot" => "General Mental Ability, General Science, Current Events of National and International Importance, Indian Polity & Economy, History of India, World Geography, Essay Writing in Hindi/English, and Precise Writing/Comprehension in English.",
        "related_article_slug" => "upsc-cds-2026-notification-exam-schedule"
    ]
];

$today = date('Y-m-d');
$inserted = 0;

foreach ($missingTerms as $t) {
    try {
        Database::execute(
            "INSERT INTO `glossary_terms` 
             (`acronym`, `slug`, `letter`, `full_form_en`, `full_form_hi`, `category`, `conducting_body`, `official_portal`, `overview`, `eligibility_criteria`, `selection_process`, `syllabus_snapshot`, `related_article_slug`, `last_reviewed_at`)
             VALUES 
             (:acronym, :slug, :letter, :full_form_en, :full_form_hi, :category, :conducting_body, :official_portal, :overview, :eligibility_criteria, :selection_process, :syllabus_snapshot, :related_article_slug, :last_reviewed_at)
             ON DUPLICATE KEY UPDATE 
             `full_form_en` = VALUES(`full_form_en`),
             `full_form_hi` = VALUES(`full_form_hi`),
             `letter` = VALUES(`letter`),
             `category` = VALUES(`category`),
             `conducting_body` = VALUES(`conducting_body`),
             `official_portal` = VALUES(`official_portal`),
             `overview` = VALUES(`overview`),
             `eligibility_criteria` = VALUES(`eligibility_criteria`),
             `selection_process` = VALUES(`selection_process`),
             `syllabus_snapshot` = VALUES(`syllabus_snapshot`),
             `related_article_slug` = VALUES(`related_article_slug`),
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
        echo "  -> Seeded: [{$t['letter']}] [{$t['acronym']}] {$t['full_form_en']}\n";
    } catch (Throwable $e) {
        echo "  -> Error on {$t['acronym']}: " . $e->getMessage() . "\n";
    }
}

echo "Successfully processed {$inserted} high-value ranking acronyms into glossary_terms.\n";
