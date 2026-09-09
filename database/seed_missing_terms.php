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
    ],
    [
        "acronym" => "AAO",
        "slug" => "aao",
        "letter" => "A",
        "full_form_en" => "Assistant Administrative Officer",
        "full_form_hi" => "सहायक प्रशासनिक अधिकारी",
        "category" => "banking",
        "conducting_body" => "Life Insurance Corporation of India (LIC) / CAG",
        "official_portal" => "https://licindia.in",
        "overview" => "Assistant Administrative Officer (AAO) is a premier entry-level managerial post in the Life Insurance Corporation of India (LIC) and statutory audit cadres. LIC AAO recruits across Generalist, IT, Chartered Accountant, Actuarial, and Rajbhasha disciplines, overseeing policy administration, underwriting, and claim processing.",
        "eligibility_criteria" => "Bachelor's Degree in any discipline from a recognized Indian University or Institution. Age limit: 21 to 30 years with statutory relaxations for reserved categories.",
        "selection_process" => "Phase-I: Preliminary Objective Examination -> Phase-II: Mains Examination (Objective + English Descriptive Test) -> Interview -> Pre-Recruitment Medical Examination.",
        "syllabus_snapshot" => "Reasoning Ability, Quantitative Aptitude, General Knowledge & Current Affairs, Insurance and Financial Market Awareness, and English Language with descriptive essay/letter writing.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "ALP",
        "slug" => "alp",
        "letter" => "A",
        "full_form_en" => "Assistant Loco Pilot",
        "full_form_hi" => "सहायक लोको पायलट",
        "category" => "railway",
        "conducting_body" => "Railway Recruitment Boards (RRB), Ministry of Railways",
        "official_portal" => "https://indianrailways.gov.in",
        "overview" => "Assistant Loco Pilot (ALP) is a critical frontline technical post under Indian Railways responsible for assisting the Loco Pilot in driving electric and diesel passenger, express, and freight locomotives. ALPs monitor track signals, locomotive mechanical instruments, and ensure passenger safety.",
        "eligibility_criteria" => "Matriculation / 10th Pass plus ITI in specified trades (Fitter, Electrician, Machinist, etc.), or 3-year Diploma / Degree in Mechanical, Electrical, Electronics, or Automobile Engineering. Age: 18 to 33 years. Strict A-1 Medical Standard (Distant Vision: 6/6, 6/6 without glasses).",
        "selection_process" => "First Stage CBT (CBT-1: 75 Questions, 60 mins) -> Second Stage CBT (CBT-2 Part A & Part B Trade Test) -> Computer-Based Aptitude Test (CBAT - Psycho Test) -> Document Verification and Medical Examination.",
        "syllabus_snapshot" => "Mathematics, General Intelligence & Reasoning, Basic Science and Engineering (Engineering Drawing, Work Power Energy, Electricity, Heat & Temperature), and technical trade subjects.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "APFC",
        "slug" => "apfc",
        "letter" => "A",
        "full_form_en" => "Assistant Provident Fund Commissioner",
        "full_form_hi" => "सहायक भविष्य निधि आयुक्त",
        "category" => "civil_services",
        "conducting_body" => "Union Public Service Commission (UPSC) / EPFO",
        "official_portal" => "https://upsc.gov.in",
        "overview" => "Assistant Provident Fund Commissioner (APFC) is a Group 'A' statutory administrative position in the Employees' Provident Fund Organisation (EPFO). APFCs perform quasi-judicial inquiries under the EPF & MP Act 1952, administer provident fund, pension, and insurance schemes for millions of industrial workers, and oversee compliance and recovery.",
        "eligibility_criteria" => "Bachelor's degree from a recognized university. Desirable: Degree in Law / Post Graduate Diploma in Company Law / Labour Laws / Management. Age limit: Up to 35 years for General/EWS, 38 for OBC, 40 for SC/ST.",
        "selection_process" => "Recruitment Test (Pen-and-paper OMR / CBT objective examination) carrying 75% weightage followed by Interview carrying 25% weightage.",
        "syllabus_snapshot" => "General English, Indian Culture & Heritage, Population, Development & Globalization, Governance & Constitution, Accounting & Auditing, Industrial Relations & Labour Laws, Social Security in India, General Science, and Elementary Maths.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "ASO",
        "slug" => "aso",
        "letter" => "A",
        "full_form_en" => "Assistant Section Officer",
        "full_form_hi" => "सहायक अनुभाग अधिकारी",
        "category" => "civil_services",
        "conducting_body" => "Staff Selection Commission (SSC CGL) / State Public Service Commissions",
        "official_portal" => "https://ssc.gov.in",
        "overview" => "Assistant Section Officer (ASO) is a Group 'B' non-gazetted ministerial post in the Central Secretariat Service (CSS), Ministry of External Affairs (MEA), Railway Board, Armed Forces Headquarters (AFHQ), and Intelligence Bureau (IB). ASOs draft official government files, process administrative notes, and handle inter-ministerial communications.",
        "eligibility_criteria" => "Bachelor's degree in any discipline from a recognized university. Age limit: 20 to 30 years (up to 32 for certain cadres) with statutory quotas.",
        "selection_process" => "SSC Combined Graduate Level (CGL) Tier-I and Tier-II Computer-Based Examination, Computer Proficiency Test (DEST / CPT), and Document Verification.",
        "syllabus_snapshot" => "Mathematical Abilities, Reasoning and General Intelligence, English Language and Comprehension, General Awareness, and Computer Knowledge Module.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "BDO",
        "slug" => "bdo",
        "letter" => "B",
        "full_form_en" => "Block Development Officer",
        "full_form_hi" => "खंड विकास अधिकारी",
        "category" => "civil_services",
        "conducting_body" => "State Public Service Commissions (UPPSC, BPSC, MPPSC, etc.)",
        "official_portal" => "https://panchayat.gov.in",
        "overview" => "The Block Development Officer (BDO) is the administrative head of a Community Development Block under the Panchayati Raj System. As chief executive officer of the Panchayat Samiti, the BDO coordinates rural development schemes (MGNREGA, PMAY-G, Swachh Bharat), oversees rural infrastructure, and disburses state government development funds.",
        "eligibility_criteria" => "Bachelor's degree in any stream from a recognized university. Age: 21 to 40/42 years depending on the state civil services rules.",
        "selection_process" => "State Public Service Commission Combined State Civil Services Examination (Preliminary MCQ screening -> Descriptive Mains Examination -> Personality Test/Interview).",
        "syllabus_snapshot" => "General Studies (Indian History, Geography, Polity, Economy, State Special Knowledge, Panchayat Administration), General Hindi, and Essay Writing.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "CPO",
        "slug" => "cpo",
        "letter" => "C",
        "full_form_en" => "Central Police Organisation",
        "full_form_hi" => "केंद्रीय पुलिस संगठन",
        "category" => "police",
        "conducting_body" => "Staff Selection Commission (SSC)",
        "official_portal" => "https://ssc.gov.in",
        "overview" => "Central Police Organisation (CPO) refers to the nationwide competitive examination (SSC CPO) conducted by the Staff Selection Commission to recruit Sub-Inspectors (SI) in Delhi Police and the Central Armed Police Forces (BSF, CISF, CRPF, ITBP, SSB).",
        "eligibility_criteria" => "Bachelor's Degree in any discipline from a recognized University. For Sub-Inspector in Delhi Police, male candidates must possess a valid Driving License for LMV (Motorcycle and Car). Age limit: 20 to 25 years with category relaxations.",
        "selection_process" => "Paper-I (CBT - 200 Marks) -> Physical Standard Test (PST) & Physical Endurance Test (PET) -> Paper-II (English Comprehension - 200 Marks) -> Detailed Medical Examination (DME).",
        "syllabus_snapshot" => "Paper-I: General Intelligence, General Knowledge, Quantitative Aptitude, English Comprehension. Paper-II: English Language, Vocabulary, Spellings, Grammar, Sentence Structure, and Comprehension.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "CSAT",
        "slug" => "csat",
        "letter" => "C",
        "full_form_en" => "Civil Services Aptitude Test",
        "full_form_hi" => "सिविल सेवा अभिवृत्ति परीक्षा",
        "category" => "civil_services",
        "conducting_body" => "Union Public Service Commission (UPSC)",
        "official_portal" => "https://upsc.gov.in",
        "overview" => "Civil Services Aptitude Test (CSAT), officially designated as General Studies Paper-II of the UPSC Civil Services (Preliminary) Examination, is a mandatory qualifying aptitude test introduced in 2011 to assess analytical ability, comprehension, and decision-making skills of civil services aspirants.",
        "eligibility_criteria" => "Graduate degree in any discipline; eligible candidates appearing for UPSC CSE Prelims must appear for CSAT. Minimum qualifying score is 33% (66 marks out of 200).",
        "selection_process" => "Offline OMR pen-and-paper examination comprising 80 multiple-choice questions (200 marks, 2 hours) with 1/3rd negative marking. While qualifying in nature, scoring below 33% disqualifies candidate regardless of Paper-I score.",
        "syllabus_snapshot" => "Reading Comprehension, Interpersonal skills including communication skills, Logical reasoning and analytical ability, Decision-making and problem-solving, General mental ability, and Basic numeracy (Class 10 level).",
        "related_article_slug" => null
    ],
    [
        "acronym" => "CSE",
        "slug" => "cse",
        "letter" => "C",
        "full_form_en" => "Civil Services Examination",
        "full_form_hi" => "सिविल सेवा परीक्षा",
        "category" => "civil_services",
        "conducting_body" => "Union Public Service Commission (UPSC)",
        "official_portal" => "https://upsc.gov.in",
        "overview" => "The Civil Services Examination (CSE) is India's premier nationwide competitive examination conducted annually by the UPSC to select officers for All India Services (IAS, IPS) and Central Civil Services (IFS, IRS, IA&AS, IDAS, etc.) across 24 premier administrative cadres.",
        "eligibility_criteria" => "Bachelor's degree from a recognized university. Age limit: 21 to 32 years for General (6 attempts), 35 years for OBC (9 attempts), and 37 years for SC/ST (unlimited attempts).",
        "selection_process" => "Stage 1: Preliminary Examination (Objective: GS Paper-I and CSAT Paper-II). Stage 2: Main Examination (Written: 9 descriptive papers totaling 1750 marks). Stage 3: Personality Test / Interview (275 marks). Final rank list based on 2025 marks.",
        "syllabus_snapshot" => "History, Geography, Indian Polity, Governance, Constitution, Social Justice, International Relations, Economic Development, Environment & Biodiversity, Security, Ethics & Integrity, Essay, and chosen Optional Subject.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "DGP",
        "slug" => "dgp",
        "letter" => "D",
        "full_form_en" => "Director General of Police",
        "full_form_hi" => "पुलिस महानिदेशक",
        "category" => "police",
        "conducting_body" => "Ministry of Home Affairs / State Governments (UPSC empanelment)",
        "official_portal" => "https://mha.gov.in",
        "overview" => "Director General of Police (DGP) is the highest-ranking police officer in an Indian State or Union Territory, who heads the State Police Force. DGP is an officer of the Indian Police Service (IPS) holding a 3-star rank wearing national emblem over crossed sword and baton, serving in the apex pay scale (Level 17 / Level 16 of the 7th CPC).",
        "eligibility_criteria" => "Senior Indian Police Service (IPS) officer of Additional Director General rank with minimum 30 years of meritorious service, empanelled by UPSC following Prakash Singh judgment guidelines.",
        "selection_process" => "State government sends list of senior-most eligible IPS officers to UPSC; UPSC committee shortlists panel of three officers based on service records; State Government appoints DGP from this panel for a minimum fixed tenure of 2 years.",
        "syllabus_snapshot" => "Recruited initially through UPSC Civil Services Examination (IPS cadre) followed by specialized tactical, legal, counter-terror, and administrative training at the Sardar Vallabhbhai Patel National Police Academy (SVPNPA), Hyderabad.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "OSSC",
        "slug" => "ossc",
        "letter" => "O",
        "full_form_en" => "Odisha Staff Selection Commission",
        "full_form_hi" => "ओडिशा कर्मचारी चयन आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Odisha",
        "official_portal" => "https://ossc.gov.in",
        "overview" => "The Odisha Staff Selection Commission (OSSC) is the statutory recruitment authority established under the Odisha Staff Selection Commission Rules 1993, headquartered in Bhubaneswar. It conducts competitive recruitment examinations for Group 'B' and Group 'C' non-gazetted technical, administrative, and paramedical cadres under the State Government of Odisha.",
        "eligibility_criteria" => "HSC / 10+2 / Bachelor's Degree depending on the post; ability to read, write, and speak Odia (passed Middle School / Matriculation with Odia language). Age limit: 21 to 38 years with state quota concessions.",
        "selection_process" => "Combined Graduate Level Recruitment Examination (CGLRE): Preliminary Examination (CBT/OMR) -> Main Written Examination (Descriptive/Objective) -> Computer Skill Test -> Document Verification.",
        "syllabus_snapshot" => "Arithmetic, Data Interpretation, Logical Reasoning, Current Events, Computer Awareness, General English, and Odia Language Comprehension.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "PO",
        "slug" => "po",
        "letter" => "P",
        "full_form_en" => "Probationary Officer",
        "full_form_hi" => "परिवीक्षाधीन अधिकारी",
        "category" => "banking",
        "conducting_body" => "Institute of Banking Personnel Selection (IBPS) / State Bank of India (SBI)",
        "official_portal" => "https://ibps.in",
        "overview" => "Probationary Officer (PO), officially designated as Assistant Manager (Junior Management Grade Scale-I / JMGS-I), is the primary officer-cadre entry-level post in Indian Public Sector Banks (PNB, BoB, Canara, etc.) and State Bank of India (SBI). POs undergo a 2-year probation receiving intensive training in branch banking, loan appraisal, credit management, foreign exchange, and risk assessment.",
        "eligibility_criteria" => "Graduation in any discipline from a recognized University. Age limit: 20 to 30 years with statutory relaxations (3 years for OBC, 5 years for SC/ST).",
        "selection_process" => "Phase-I: Preliminary Examination (100 Qs, 100 marks, 1 hour) -> Phase-II: Main Examination (Objective 200 marks + English Letter & Essay Descriptive 25 marks) -> Phase-III: Psychometric Test, Group Discussion (GD), and Personal Interview.",
        "syllabus_snapshot" => "Reasoning & Computer Aptitude, Quantitative Aptitude / Data Analysis & Interpretation, General Economy & Banking Awareness, and English Language.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "PSC",
        "slug" => "psc",
        "letter" => "P",
        "full_form_en" => "Public Service Commission",
        "full_form_hi" => "लोक सेवा आयोग",
        "category" => "civil_services",
        "conducting_body" => "State Governments under Article 315 of the Constitution of India",
        "official_portal" => "https://dopt.gov.in",
        "overview" => "A Public Service Commission (PSC) is an independent constitutional authority mandated under Part XIV, Articles 315 to 323 of the Constitution of India. Each State PSC is empowered to conduct competitive examinations, advise state governors on recruitment rules, promotions, and disciplinary matters regarding civil servants.",
        "eligibility_criteria" => "Candidates holding Bachelor's or Master's degrees from UGC-recognized universities; age generally 21 to 35-42 years depending on state-specific recruitment statutes.",
        "selection_process" => "Standardized 3-tier competitive framework: Preliminary Screening Test -> Comprehensive Main Written Examination -> Personality Test / Viva-Voce.",
        "syllabus_snapshot" => "State history, language, culture, Indian Constitution, administrative law, public administration, economics, ethics, and general mental ability.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "RRC",
        "slug" => "rrc",
        "letter" => "R",
        "full_form_en" => "Railway Recruitment Cell",
        "full_form_hi" => "रेलवे भर्ती प्रकोष्ठ",
        "category" => "railway",
        "conducting_body" => "Zonal Indian Railways (Northern, Western, Central, Eastern, Southern, etc.)",
        "official_portal" => "https://indianrailways.gov.in",
        "overview" => "Railway Recruitment Cell (RRC) constitutes the zonal recruitment apparatus established in 2005 across all 16 Railway Zones of Indian Railways. RRCs are mandated to manage recruitments for Level-1 (formerly Group 'D') posts including Track Maintainer Grade-IV, Pointsman, Gateman, Porter, and technical workshop assistants.",
        "eligibility_criteria" => "10th pass from NCERT/recognized board, or National Apprenticeship Certificate (NAC) granted by NCVT, or 10th pass plus ITI. Age limit: 18 to 33 years with category relaxations.",
        "selection_process" => "Computer-Based Test (CBT - 100 questions, 90 minutes) -> Physical Efficiency Test (PET: Weight lifting & running) -> Document Verification (DV) & Comprehensive Railway Medical Examination.",
        "syllabus_snapshot" => "General Science (Class 10 Physics, Chemistry, Life Sciences), Mathematics (Arithmetic, Algebra, Geometry), General Intelligence and Reasoning, and General Awareness on Current Affairs.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "RAS",
        "slug" => "ras",
        "letter" => "R",
        "full_form_en" => "Rajasthan Administrative Service",
        "full_form_hi" => "राजस्थान प्रशासनिक सेवा",
        "category" => "civil_services",
        "conducting_body" => "Rajasthan Public Service Commission (RPSC)",
        "official_portal" => "https://rpsc.rajasthan.gov.in",
        "overview" => "The Rajasthan Administrative Service (RAS) is the premier provincial civil service cadre of the state of Rajasthan. RAS officers manage sub-divisional administration as Sub-Divisional Magistrates (SDM), handle land revenue administration, oversee public distribution, and are periodically promoted into the Indian Administrative Service (IAS).",
        "eligibility_criteria" => "Degree of any recognized University. Candidates must possess working knowledge of Hindi written in Devanagari script and knowledge of Rajasthani culture. Age limit: 21 to 40 years.",
        "selection_process" => "Combined Competitive Examination conducted by RPSC: Preliminary Examination (200 marks, single paper) -> Main Examination (4 descriptive papers of 200 marks each: GS-I, GS-II, GS-III, General Hindi & English) -> Personality and Viva-Voce Examination (100 marks).",
        "syllabus_snapshot" => "History, Art, Culture & Heritage of Rajasthan; Indian & World Geography; Indian Constitution & Administrative Ethics; General Hindi (Grammar, Translation, Essay); and General English.",
        "related_article_slug" => null
    ],
    [
        "acronym" => "UPPSC",
        "slug" => "uppsc",
        "letter" => "U",
        "full_form_en" => "Uttar Pradesh Public Service Commission",
        "full_form_hi" => "उत्तर प्रदेश लोक सेवा आयोग",
        "category" => "civil_services",
        "conducting_body" => "Government of Uttar Pradesh",
        "official_portal" => "https://uppsc.up.nic.in",
        "overview" => "The Uttar Pradesh Public Service Commission (UPPSC), located at Kasturba Gandhi Marg, Prayagraj, is the constitutional authority under Article 315 responsible for recruiting officers into the Provincial Civil Service (PCS), Provincial Police Service (PPS), Review Officer / Assistant Review Officer (RO/ARO), and technical/medical services of Uttar Pradesh.",
        "eligibility_criteria" => "Graduate degree in any stream from a recognized Indian university. Age limit: 21 to 40 years with 5 years relaxation for SC, ST, OBC, skilled sportspersons, and state employees of UP.",
        "selection_process" => "Combined State / Upper Subordinate Services (PCS) Exam: Prelims (GS Paper-I merit rank + CSAT Paper-II qualifying 33%) -> Mains (6 Compulsory General Studies Papers + General Hindi + Essay) -> Interview (100 marks).",
        "syllabus_snapshot" => "Indian History, National Movement, Geography, Polity, Economy, Environment; UP-Specific GS Papers 5 & 6 (Uttar Pradesh History, Polity, Geography, Economy, Agriculture, Culture); General Hindi and Essay.",
        "related_article_slug" => null
    ]
];

$today = date('Y-m-d');
$inserted = 0;

foreach ($missingTerms as $t) {
    try {
        Database::query(
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
