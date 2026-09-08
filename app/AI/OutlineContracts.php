<?php
declare(strict_types=1);

namespace App\AI;

use App\Services\ArticleIntent;

final class OutlineContracts
{
    public static function forIntent(ArticleIntent $intent): string
    {
        return match ($intent) {
            ArticleIntent::ADMIT_CARD => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Admit Card / Exam City Intimation Slip Article:
CRITICAL NEGATIVE CONSTRAINT: Do NOT include any "How to Apply Online", "Application Fee", or "Age Limit / Eligibility Criteria" section! The candidates have ALREADY completed the application process months ago.

Every <h2> heading MUST contain the specific Examination/Recruitment entity name (e.g. "RRB NTPC CBT-2: ...").

Required Structural Flow:
1. Compelling Direct Mentor Introduction:
   - What was officially announced/released, for which exact notification code (e.g. CEN No.) and stage (e.g. CBT-2 for Undergraduate posts).
   - Direct link timeline and callout.
2. <h2>[Exam Name]: City Intimation Slip vs e-Call Letter (Key Distinction)</h2>
   - Clear explanatory callout box: Detail why the City Intimation Slip is NOT the admit card (it only shows exam city, state, date, and shift to facilitate travel/train bookings).
   - When the actual e-Call Letter / Admit Card will be released (usually 4 days before the candidate's exam date).
3. <h2>[Exam Name]: Official CBT Schedule, Reporting Hours & Shift Timings</h2>
   - MANDATORY HTML <table>: Columns for Shift Name, Reporting Time, Gate Closure Cutoff, Exam Commencement, and Duration.
   - Strict Gate Closure Policy: Emphasize zero-tolerance entry ban once gates close.
4. <h2>[Exam Name]: Regional Download Portals & Direct Official Links</h2>
   - MANDATORY HTML <table>: Regional Boards/Zones (e.g. RRB Allahabad, Mumbai, Bhopal, Kolkata, Chennai, etc. or SSC NR, CR, ER) with direct portal URLs.
5. <h2>Step-by-Step Guide to Download [Exam Name] Admit Card & City Slip</h2>
   - Exact login credentials required (Registration Number + Date of Birth in DD/MM/YYYY).
   - Instructions on downloading and saving the PDF.
6. <h2>Exam Day Instructions & Mandatory Documents Checklist for [Exam Name]</h2>
   - Original Govt Photo ID Proofs (Aadhaar Card, PAN, Voter ID, Driving License, Passport).
   - Coloured printed admit card with recent passport-size photograph.
   - Biometric verification rules (Aadhaar-based biometric authentication).
   - Barred electronic items (Smartwatches, Bluetooth devices, earphones, mobile phones).
7. <h2>Frequently Asked Questions (FAQs) About [Exam Name] Hall Ticket</h2>
   - 5 to 6 genuine, highly searched candidate queries regarding login password recovery, shift timing, city change requests (not permitted), and biometric screening.
8. <h2>Official Notice Reference & Authority Verification for [Exam Name]</h2>
PROMPT,

            ArticleIntent::RESULT_CUTOFF => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Exam Result, Cutoff Marks & Scorecard Article:
CRITICAL NEGATIVE CONSTRAINT: Do NOT include "How to Apply", "Application Fee", or "Eligibility" sections!

Every <h2> heading MUST contain the specific Examination/Recruitment entity name.

Required Structural Flow:
1. Direct Announcement Hook:
   - What result was declared, which exam stage (e.g. Tier-1, Prelims, CBT-1), total qualified candidates, and scorecard availability.
2. <h2>[Exam Name]: Result Highlights, Direct Scorecard Link & Merit List PDF</h2>
   - Direct download links for Cutoff Notice PDF, Merit List Roll Numbers PDF, and Candidate Scorecard Login URL.
3. <h2>[Exam Name]: Category-Wise Cutoff Marks & Qualifying Percentiles</h2>
   - MANDATORY HTML <table>: Rows for General/UR, EWS, OBC-NCL, SC, ST, ESM, and PwBD categories.
   - Normalized marks vs raw marks explanation if normalization was applied.
4. <h2>Tie-Breaking Criteria & Normalization Formula for [Exam Name]</h2>
   - Explicit criteria used by the commission (Date of birth / older candidates prioritized, marks in specific subject sections, alphabetical order).
5. <h2>Next Stage Roadmap & Action Plan for Qualified Candidates in [Exam Name]</h2>
   - What comes next: Next Exam Phase (CBT-2 / Mains / Descriptive / Typing / Physical Test / Document Verification).
   - Schedule or expected timeline for the next stage.
6. <h2>How to Check [Exam Name] Result & Download Scorecard Online</h2>
   - Step-by-step instructions: Application No / Roll No + DOB login.
7. <h2>Frequently Asked Questions (FAQs) About [Exam Name] Result & Cutoff</h2>
   - 5 to 6 genuine FAQs addressing re-evaluation/re-checking rules, scorecard download expiry, and next stage preparation.
8. <h2>Official Authority Verification & Direct Gazetted Links for [Exam Name]</h2>
PROMPT,

            ArticleIntent::RECRUITMENT => <<<PROMPT
MANDATORY OUTLINE CONTRACT — New Recruitment / Job Notification & Application Guide:
This is the ONLY intent that should carry full Eligibility, Vacancies breakdown, and How-to-Apply guidance.

Every <h2> heading MUST contain the specific Recruitment entity name.

Required Structural Flow:
1. Inspiring Narrative Hook & Notification Overview:
   - Total vacancies, department/cadre names, pay matrix level (7th CPC), and why this recruitment cycle is significant.
2. <h2>[Recruitment Name]: Notification Highlights, Vacancy Distribution & Key Dates</h2>
   - MANDATORY HTML <table>: Key Dates (Notification release, Online registration start date, Last date to apply, Fee payment deadline, Correction window, Exam date).
   - Vacancy Distribution Table: Post names, Pay scale, and category-wise vacancies (UR, OBC, SC, ST, EWS).
3. <h2>Eligibility Criteria, Age Limits & Educational Qualifications for [Recruitment Name]</h2>
   - Minimum and maximum age limit with the exact cutoff date (e.g. as on 01/08/2026).
   - Category-wise age relaxations (SC/ST: 5 years, OBC: 3 years, PwBD: 10 years).
   - Required educational degree, diploma, or certifications per post.
4. <h2>Application Fee, Payment Modes & Fee Exemptions for [Recruitment Name]</h2>
   - Category-wise application fee table (General/OBC, SC/ST/Women/Ex-SM).
5. <h2>Step-by-Step Online Application & OTR Registration Guide for [Recruitment Name]</h2>
   - One-Time Registration (OTR) steps, document upload dimensions (photo, signature), avoiding server payment timeouts.
6. <h2>Selection Process, Exam Pattern & Marking Scheme for [Recruitment Name]</h2>
   - Stages: Tier-1 / CBT, Tier-2, Skill Test / Typing, Physical Standards (if applicable), Document Verification, Medical Examination.
   - Negative marking penalty per incorrect answer.
7. <h2>Frequently Asked Questions (FAQs) About [Recruitment Name]</h2>
   - 5 to 6 genuine questions regarding final year students eligibility, domicile certificates, other state eligibility.
8. <h2>Official Notification Circular & Direct Application Links for [Recruitment Name]</h2>
PROMPT,

            ArticleIntent::ANSWER_KEY => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Answer Key, Response Sheet & Objection Window:
CRITICAL NEGATIVE CONSTRAINT: Do NOT include application forms or eligibility sections.

Every <h2> heading MUST contain the specific Examination entity name.

Required Structural Flow:
1. Direct Answer Hook:
   - Whether Provisional or Final Answer Key is released, exam dates for which key is available, and deadline to submit challenges.
2. <h2>[Exam Name]: Answer Key Highlights & Direct PDF / Login Links</h2>
   - Direct link to candidate response sheet, master question paper, and objection portal.
3. <h2>[Exam Name]: Objection Window Schedule & Challenge Fee Structure</h2>
   - MANDATORY HTML <table>: Objection Start Date & Time, Final Deadline, Processing Fee per question challenged (e.g. ₹50 or ₹100), and refund rules for valid challenges.
4. <h2>Step-by-Step Process to Raise Objections Against [Exam Name] Answer Key</h2>
   - Numbered steps on logging in, selecting Question ID, uploading documentary proof / reference book citation, and fee payment.
5. <h2>Calculation of Estimated Marks & Negative Marking Scheme for [Exam Name]</h2>
   - How to compute tentative raw score: Correct marks awarded minus negative penalty.
6. <h2>Frequently Asked Questions (FAQs) About [Exam Name] Answer Key</h2>
   - 5 to 6 genuine candidate queries on challenge validity, final answer key timeline, and score calculation.
7. <h2>Official Portal Verification for [Exam Name]</h2>
PROMPT,

            ArticleIntent::COUNSELLING => <<<PROMPT
MANDATORY OUTLINE CONTRACT — College Counselling, Seat Allotment & Cutoffs:
CRITICAL NEGATIVE CONSTRAINT: Do NOT include exam application forms or syllabus.

Every <h2> heading MUST contain the specific Counselling/Admission entity name.

Required Structural Flow:
1. Direct Status Hook:
   - Which round is active (e.g. Round 1, Round 2, Mop-Up, Spot Round, CAP Round 3), seat allotment date, and immediate candidate action.
2. <h2>[Counselling Name]: Complete Round Schedule & Critical Cutoff Deadlines</h2>
   - MANDATORY HTML <table>: Choice Filling / Option Entry, Provisional Allotment Date, Seat Acceptance Window, Physical Reporting at Allotted Institute.
3. <h2>Understanding Seat Acceptance: Freeze, Float & Slide Options Explained</h2>
   - Clear breakdown of candidate choices:
     * Freeze: Accept seat and exit counselling.
     * Float: Accept seat with option to upgrade in higher preference in subsequent rounds.
     * Slide: Accept seat in same institute with branch upgrade option.
4. <h2>Category-Wise Opening & Closing Ranks / Cutoff Analysis for [Counselling Name]</h2>
   - Cutoff matrix for top institutions/branches by category (General, OBC, SC, ST, EWS).
5. <h2>Mandatory Documents Required for Institute Physical Reporting & Verification</h2>
   - Allotment letter, Rank card, Admit card, Class 10/12 marksheets, Category/Caste certificate, Domicile certificate, Medical fitness, Anti-ragging affidavit.
6. <h2>Fee Payment, Seat Confirmation & UGC Refund Policy Rules</h2>
   - Seat acceptance fee amount and UGC fee refund timeline policies.
7. <h2>Frequently Asked Questions (FAQs) About [Counselling Name]</h2>
   - 5 to 6 genuine FAQs on what happens if a candidate doesn't report, spot round eligibility, and document discrepancies.
8. <h2>Official Counselling Portal & Direct Allotment Result Links</h2>
PROMPT,

            ArticleIntent::SYLLABUS_CHANGE => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Revised Syllabus, Exam Pattern & Scheme Change:
Every <h2> heading MUST contain the specific Examination entity name.

Required Structural Flow:
1. Direct News Hook:
   - What major structural modifications were introduced by the commission and effective from which academic/recruitment cycle.
2. <h2>[Exam Name]: Old vs New Exam Pattern Comparison (Side-by-Side Analysis)</h2>
   - MANDATORY HTML <table>: Side-by-side comparison of Previous Scheme vs Revised Scheme (Number of questions, total marks, time duration, negative marking).
3. <h2>Detailed Subject-Wise Syllabus & Topic Weightage Breakdown for [Exam Name]</h2>
   - Granular breakdown of newly added topics, deleted chapters, and high-weightage sections.
4. <h2>Strategic Preparation Roadmap & Recommended Study Approach for Revised Pattern</h2>
   - Actionable preparation tips adapting to the new question format.
5. <h2>Frequently Asked Questions (FAQs) About [Exam Name] New Syllabus</h2>
6. <h2>Official Gazette / Syllabus Notification PDF Download Links</h2>
PROMPT,

            ArticleIntent::CORRIGENDUM => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Official Corrigendum, Date Extension & Postponement:
Every <h2> heading MUST contain the specific Examination/Recruitment entity name.

Required Structural Flow:
1. Direct Announcement Hook:
   - What exact date or provision was amended by the statutory authority, the official notice reference number, and immediate impact.
2. <h2>[Entity Name]: Original Schedule vs Revised Schedule (Before & After Matrix)</h2>
   - MANDATORY HTML <table>: Event Milestone, Earlier Gazetted Date, Revised / Extended New Date, Status.
3. <h2>Official Reason for Revision & Scope of Affected Candidates</h2>
   - Technical server maintenance, court directives, administrative logistics, or cyclone/weather postponement reasons stated by the authority.
   - Clarify whether all candidates or specific regional centres/categories are affected.
4. <h2>Action Required by Registered Candidates & Next Steps</h2>
   - Clarify whether candidates need to re-apply or if existing applications remain valid.
5. <h2>Frequently Asked Questions (FAQs) About [Entity Name] Revised Schedule</h2>
6. <h2>Official Notice Reference & Gazetted Circular Links</h2>
PROMPT,
        };
    }
}
