<?php
declare(strict_types=1);

namespace App\AI;

use App\Services\ArticleIntent;

final class OutlineContracts
{
    public static function forIntent(ArticleIntent $intent): string
    {
        $tableRule = <<<RULE
CRITICAL TABLE & PLACEHOLDER INSTRUCTION:
- Immediately after the first <h2> heading and opening paragraph, include the literal placeholder: <!--DATES_MILESTONE_TABLE-->
- Do NOT generate a dates or statutory milestone table yourself — the system automatically inserts the verified dates table at that exact marker.
- Any other domain table you generate (Vacancy Distribution, Exam Pattern Comparison, Subject Weightage, Shift Timings, Challenge Fee Structure, Cutoffs, Regional Portals) MUST use standard semantic <table> tags and will be preserved untouched.
- NEVER generate two milestone or schedule dates tables back-to-back. Domain tables must contain distinct domain data (e.g. fees, vacancies, shifts), never duplicate milestone dates.

RULE;

        $contract = match ($intent) {
            ArticleIntent::ADMIT_CARD => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Admit Card / Exam City Intimation Slip Article:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT include any "How to Apply Online", "Application Fee", or "Eligibility Criteria" section!
- Do NOT generate generic 5-step download guides ("Visit website... click tab... download PDF"). Instead, provide direct portal URLs and specific login credential requirements (e.g., Registration Number and DOB).
- Do NOT generate generic exam-day advice ("transparent pouch", "reach early", "sleep well", "smartwatches banned") unless specifically supported by official instructions from the circular.
- Do NOT generate boilerplate disclaimers or repetitive authority verification sections.
- FAQs are OPTIONAL: maximum 3, strictly factual (e.g., city slip vs admit card distinction, login retrieval, reporting time policy). NEVER add exam stress, sleep, or generic server FAQs.

Every <h2> heading MUST contain the specific Examination/Recruitment entity name (e.g. "RRB NTPC CBT-2: ...").

Required Structural Flow:
1. Direct News & Availability Hook:
   - State clearly that admit cards or city intimation slips are live, exact notification code (e.g., CEN No.), stage, and dates.
2. <h2>[Exam Name]: City Intimation Slip vs e-Call Letter (Key Distinction)</h2>
   - Insert <!--DATES_MILESTONE_TABLE--> immediately after the opening paragraph of this section.
   - Clarify whether this release is the City Slip (travel booking) or final Admit Card (reporting slip released ~4 days prior).
3. <h2>[Exam Name]: Official CBT Schedule, Reporting Hours & Shift Timings</h2>
   - MANDATORY HTML <table>: Columns for Shift Name, Reporting Time, Gate Closure Cutoff, Exam Commencement, and Duration.
   - Gate Closure Rule: State official cutoff time after which entry is prohibited.
4. <h2>[Exam Name]: Regional Download Portals & Official Access Links</h2>
   - MANDATORY HTML <table>: Regional Boards/Zones with direct official portal URLs and required login parameters.
5. <h2>Frequently Asked Questions About [Exam Name] Admit Card</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::RESULT_CUTOFF => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Exam Result, Cutoff Marks & Scorecard Article:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT include "How to Apply", "Application Fee", or "Eligibility" sections!
- Do NOT generate generic 5-step download guides. State the direct scorecard login link and roll number lookup method concisely.
- Do NOT invent fake psychological or stress advice.
- FAQs are OPTIONAL: maximum 3, addressing re-evaluation rules, tie-breaking criteria, or next phase schedule.

Every <h2> heading MUST contain the specific Examination/Recruitment entity name.

Required Structural Flow:
1. Direct Announcement Hook:
   - What result was declared, exam stage, total qualified candidates, and scorecard availability.
2. <h2>[Exam Name]: Result Highlights, Direct Scorecard Link & Merit List PDF</h2>
   - Insert <!--DATES_MILESTONE_TABLE--> immediately after the opening paragraph of this section.
   - Direct links for Cutoff Notice PDF, Merit List Roll Numbers PDF, and Candidate Scorecard Login URL.
3. <h2>[Exam Name]: Category-Wise Cutoff Marks & Qualifying Percentiles</h2>
   - MANDATORY HTML <table>: Rows for General/UR, EWS, OBC-NCL, SC, ST, ESM, and PwBD categories.
   - Normalized marks vs raw marks explanation if normalization was applied.
4. <h2>Tie-Breaking Criteria & Normalization Formula for [Exam Name]</h2>
   - Official criteria used by the commission (Date of birth, subject section marks, or normalized score formula).
5. <h2>Next Stage Roadmap for Qualified Candidates in [Exam Name]</h2>
   - What comes next: Next Exam Phase (CBT-2 / Mains / Skill Test / Document Verification) and expected timeline.
6. <h2>Frequently Asked Questions About [Exam Name] Result & Cutoff</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::RECRUITMENT => <<<PROMPT
MANDATORY OUTLINE CONTRACT — New Recruitment / Job Notification & Application Guide:
This is the ONLY intent that should carry full Eligibility, Vacancies breakdown, and Application guidance.
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT generate generic 5-step download guides.
- Do NOT generate boilerplate disclaimers at the end.
- FAQs are OPTIONAL: maximum 3, strictly factual (final year eligibility, domicile rules, exam centres).

Every <h2> heading MUST contain the specific Recruitment entity name.

Required Structural Flow:
1. Inspiring Narrative Hook & Notification Overview:
   - Total vacancies, department/cadre names, pay matrix level (7th CPC), and recruitment cycle details.
2. <h2>[Recruitment Name]: Notification Highlights & Vacancy Distribution</h2>
   - Insert <!--DATES_MILESTONE_TABLE--> immediately after the opening paragraph of this section.
   - MANDATORY HTML <table>: Vacancy Distribution Table (Post names, Pay scale / 7th CPC Matrix, and category-wise vacancies: UR, OBC, SC, ST, EWS, Total).
3. <h2>Eligibility Criteria, Age Limits & Educational Qualifications for [Recruitment Name]</h2>
   - Format strictly as structured HTML bullet points (<ul><li>...</li></ul>):
     * <li><strong>Educational Qualification:</strong> Exact degree / diploma / minimum percentage required per discipline.</li>
     * <li><strong>Age Limit & Cut-off Date:</strong> Minimum and maximum age with the exact cut-off date.</li>
     * <li><strong>Category-Wise Age Relaxations:</strong> Clear breakdown (+3 yrs OBC-NCL, +5 yrs SC/ST, +10 yrs PwBD).</li>
     * <li><strong>Final Year Status:</strong> Explicit eligibility rule for awaiting final semester results.</li>
4. <h2>Application Fee, Payment Modes & Fee Exemptions for [Recruitment Name]</h2>
   - MANDATORY HTML <table>: Category-wise application fee breakdown and payment gateway rules.
5. <h2>Online Application & Registration Procedure for [Recruitment Name]</h2>
   - Concise direct instruction and required document specifications (photo dimensions, signature size, portal link).
6. <h2>Selection Process, Exam Pattern & Marking Scheme for [Recruitment Name]</h2>
   - Format as structured bullet points (<ul><li>...</li></ul>):
     * <li><strong>Stage 1 (Online CBT):</strong> Subjects, question count, maximum marks, and duration.</li>
     * <li><strong>Stage 2 (Skill / Trade / Interview):</strong> Requirements per stream.</li>
     * <li><strong>Negative Marking:</strong> Penalty per wrong answer (e.g. 0.25 marks deducted).</li>
7. <h2>Frequently Asked Questions About [Recruitment Name]</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::ANSWER_KEY => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Answer Key, Response Sheet & Objection Window:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT include application forms or eligibility sections.
- Do NOT generate generic 5-step download guides.
- FAQs are OPTIONAL: maximum 3, strictly on challenge fee refund rules, question IDs, and final key timeline.

Every <h2> heading MUST contain the specific Examination entity name.

Required Structural Flow:
1. Direct Answer Hook:
   - Provisional vs Final Answer Key status, exam dates covered, and deadline to submit challenges.
2. <h2>[Exam Name]: Answer Key Highlights & Direct Response Sheet Links</h2>
   - Insert <!--DATES_MILESTONE_TABLE--> immediately after the opening paragraph of this section.
   - Direct link to candidate response sheet, master question paper, and objection portal.
3. <h2>[Exam Name]: Challenge Fee Structure & Representation Guidelines</h2>
   - MANDATORY HTML <table>: Component, Official Rule / Amount: Processing Fee per question challenged, Payment Modes, Refund Terms for upheld challenges.
4. <h2>Procedure to Submit Objections Against [Exam Name] Answer Key</h2>
   - Clear concise points: Login with credentials, select Question ID, upload citation/proof, pay prescribed fee.
5. <h2>Calculation of Estimated Marks & Negative Marking Scheme for [Exam Name]</h2>
   - Raw score formula based on correct answers and penalty per incorrect response.
6. <h2>Frequently Asked Questions About [Exam Name] Answer Key</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::COUNSELLING => <<<PROMPT
MANDATORY OUTLINE CONTRACT — College Counselling, Seat Allotment & Cutoffs:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT include exam application forms or syllabus.
- Do NOT generate generic 5-step download guides.
- FAQs are OPTIONAL: maximum 3, strictly on seat upgrade, reporting deadlines, or fee refund.

Every <h2> heading MUST contain the specific Counselling/Admission entity name.

Required Structural Flow:
1. Direct Status Hook:
   - Which round is active, seat allotment date, and immediate candidate action.
2. <h2>[Counselling Name]: Complete Round Schedule & Critical Cutoff Deadlines</h2>
   - Insert <!--DATES_MILESTONE_TABLE--> immediately after the opening paragraph of this section.
   - Critical cutoff instructions and reporting timeframe.
3. <h2>Understanding Seat Acceptance: Freeze, Float & Slide Options Explained</h2>
   - Clear breakdown: Freeze (accept & exit), Float (accept with upgrade option), Slide (same institute branch upgrade).
4. <h2>Category-Wise Opening & Closing Ranks / Cutoff Analysis for [Counselling Name]</h2>
   - Cutoff matrix for top institutions/branches by category.
5. <h2>Mandatory Documents Required for Institute Physical Reporting & Verification</h2>
   - Allotment letter, Rank card, Admit card, Class 10/12 marksheets, Category/Caste certificate, Domicile certificate.
6. <h2>Frequently Asked Questions About [Counselling Name]</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::SYLLABUS_CHANGE => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Revised Syllabus, Exam Pattern & Scheme Change:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT generate generic 5-step download guides.
- Do NOT generate generic preparation tips ("study 8 hours a day", "sleep well"). Keep instructions strictly anchored in the pattern change.
- FAQs are OPTIONAL: maximum 3.

Every <h2> heading MUST contain the specific Examination entity name.

Required Structural Flow:
1. Direct News Hook:
   - Major structural modifications introduced by the commission and effective academic/recruitment cycle.
2. <h2>[Exam Name]: Old vs New Exam Pattern Comparison (Side-by-Side Analysis)</h2>
   - MANDATORY HTML <table>: Side-by-side comparison of Previous Scheme vs Revised Scheme (Questions, marks, duration, negative marking).
3. <h2>Detailed Subject-Wise Syllabus & Topic Weightage Breakdown for [Exam Name]</h2>
   - Granular breakdown of newly added topics, deleted chapters, and high-weightage sections.
4. <h2>Frequently Asked Questions About [Exam Name] New Syllabus</h2> (Optional — Max 3 factual FAQs only)
PROMPT,

            ArticleIntent::CORRIGENDUM => <<<PROMPT
MANDATORY OUTLINE CONTRACT — Official Corrigendum, Date Extension & Postponement:
CRITICAL NEGATIVE CONSTRAINTS:
- Do NOT generate generic 5-step download guides.
- Keep the article focused strictly on what changed, why, and what affected candidates must do.
- FAQs are OPTIONAL: maximum 3.

Every <h2> heading MUST contain the specific Examination/Recruitment entity name.

Required Structural Flow:
1. Direct Announcement Hook:
   - Exact date or provision amended by the statutory authority, official notice reference number, and immediate impact.
2. <h2>[Entity Name]: Original Schedule vs Revised Schedule (Before & After Matrix)</h2>
   - MANDATORY HTML <table>: Event Milestone, Earlier Gazetted Date, Revised / Extended New Date, Status.
3. <h2>Official Reason for Revision & Scope of Affected Candidates</h2>
   - Administrative, logistical, or official reasons stated by the authority, and whether all or specific candidates are affected.
4. <h2>Action Required by Registered Candidates & Next Steps</h2>
   - Clarify whether candidates need to re-apply or if existing applications remain valid.
5. <h2>Frequently Asked Questions About [Entity Name] Revised Schedule</h2> (Optional — Max 3 factual FAQs only)
PROMPT,
        };

        return $tableRule . "\n" . $contract;
    }
}
