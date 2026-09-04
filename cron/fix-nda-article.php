<?php
/**
 * Sarkari.online - Accurate Fact-Correction for UPSC NDA 2 2026 Article
 * Updates exam date to Sept 13, 2026 and accurate shift timings (Maths & GAT).
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🎯 SARKARI.ONLINE — UPSC NDA 2 2026 ARTICLE FACT CORRECTION\n";
echo "=================================================================\n\n";

$slug = 'upsc-nda-2026-admit-card-download';

// 1. Fetch the article
$article = Database::fetchOne("SELECT id, title, slug FROM articles WHERE slug = :slug", ['slug' => $slug]);

if (!$article) {
    echo "❌ Article with slug '{$slug}' not found in database!\n";
    exit(1);
}

$articleId = (int)$article['id'];
echo "Found Article #{$articleId}: '{$article['title']}'\n";

// Accurate title, excerpt, and content
$newTitle = 'UPSC NDA 2 2026 Admit Card Out: Download Link, Exam Date & Shift Timings';
$newExcerpt = 'UPSC has officially released the NDA 2 2026 e-admit card on September 3, 2026. The written examination for 394 vacancies is scheduled for September 13, 2026 across two shifts. Download hall ticket and check reporting guidelines.';

$newContent = <<<HTML
<p>The Union Public Service Commission (UPSC) has officially released the e-Admit Card for the <strong>National Defence Academy &amp; Naval Academy Examination (II) 2026</strong> on <strong>September 03, 2026</strong>. The written examination is scheduled to be conducted nationwide on <strong>September 13, 2026 (Sunday)</strong> across designated examination centres for admission to the Army, Navy, and Air Force wings of the NDA. Candidates must download their hall tickets from the official portal at <strong>upsc.gov.in</strong> or <strong>upsconline.nic.in</strong> before the examination date.</p>

<h2 id="upsc-nda-2026-key-highlights">UPSC NDA 2 2026: Key Examination Highlights</h2>
<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Statutory Parameter</th>
                <th>Official Examination Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Conducting Commission</strong></td>
                <td>Union Public Service Commission (UPSC)</td>
            </tr>
            <tr>
                <td><strong>Examination Name</strong></td>
                <td>National Defence Academy &amp; Naval Academy Exam (II) 2026</td>
            </tr>
            <tr>
                <td><strong>Total Vacancies</strong></td>
                <td><strong>394 Posts</strong> (Army, Navy, Air Force &amp; Naval Academy)</td>
            </tr>
            <tr>
                <td><strong>Admit Card Release Date</strong></td>
                <td><strong>September 03, 2026 (Active Now)</strong></td>
            </tr>
            <tr>
                <td><strong>Written Examination Date</strong></td>
                <td><strong>September 13, 2026 (Sunday)</strong></td>
            </tr>
            <tr>
                <td><strong>Selection Stages</strong></td>
                <td>Written Examination (900 Marks) &rarr; SSB Interview (900 Marks) &rarr; Medicals</td>
            </tr>
            <tr>
                <td><strong>Official Portals</strong></td>
                <td><a href="https://upsc.gov.in" target="_blank" rel="noopener">upsc.gov.in</a> &bull; <a href="https://upsconline.nic.in" target="_blank" rel="noopener">upsconline.nic.in</a></td>
            </tr>
        </tbody>
    </table>
</div>

<h2 id="upsc-nda-2026-shift-timings">Official Exam Schedule &amp; Shift Timings (September 13, 2026)</h2>
<p>The UPSC NDA (II) 2026 examination comprises two objective papers conducted on the same day. Candidates must note that <strong>entry into the examination venue closes strictly 30 minutes before the scheduled commencement of each shift</strong> (at 09:30 AM for Paper 1 and 01:30 PM for Paper 2). No candidate will be admitted after gate closure under any circumstances.</p>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Paper / Subject</th>
                <th>Subject Code</th>
                <th>Reporting Time</th>
                <th>Gate Closure</th>
                <th>Exam Timing</th>
                <th>Duration</th>
                <th>Total Marks</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Paper 1: Mathematics</strong></td>
                <td>Code 01</td>
                <td>08:30 AM</td>
                <td><strong>09:30 AM</strong></td>
                <td><strong>10:00 AM &ndash; 12:30 PM</strong></td>
                <td>2.5 Hours (150 Mins)</td>
                <td>300 Marks</td>
            </tr>
            <tr>
                <td><strong>Paper 2: General Ability Test (GAT)</strong></td>
                <td>Code 02</td>
                <td>12:45 PM</td>
                <td><strong>01:30 PM</strong></td>
                <td><strong>02:00 PM &ndash; 04:30 PM</strong></td>
                <td>2.5 Hours (150 Mins)</td>
                <td>600 Marks</td>
            </tr>
            <tr>
                <td colspan="6"><strong>Total Composite Score (Written Stage)</strong></td>
                <td><strong>900 Marks</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<h2 id="upsc-nda-marking-scheme">Marking Scheme &amp; Negative Marking</h2>
<p>Both papers consist strictly of Objective Type (Multiple Choice) questions evaluated on OMR answer sheets:</p>
<ul>
    <li><strong>Mathematics:</strong> 120 Questions (2.5 marks each). Penalty of <strong>0.83 marks</strong> is deducted for each incorrect response (one-third negative marking).</li>
    <li><strong>General Ability Test (GAT):</strong> 150 Questions (4 marks each), divided into Part A (English &ndash; 200 marks) and Part B (General Knowledge &ndash; 400 marks). Penalty of <strong>1.33 marks</strong> is deducted for each incorrect answer.</li>
    <li><strong>Unattempted Questions:</strong> No marks are deducted for unanswered questions.</li>
    <li><strong>Writing Instrument:</strong> Candidates must use only a <strong>Black Ballpoint Pen</strong> to darken the OMR bubbles. Pens of any other color or pencils are strictly prohibited.</li>
</ul>

<h2 id="how-to-download-upsc-nda-admit-card-2026">Step-by-Step Guide to Download UPSC NDA 2 2026 Admit Card</h2>
<ol>
    <li>Navigate to the official portal at <a href="https://upsconline.nic.in" target="_blank" rel="noopener"><strong>upsconline.nic.in</strong></a> or <a href="https://upsc.gov.in" target="_blank" rel="noopener"><strong>upsc.gov.in</strong></a>.</li>
    <li>Click on the link <strong>"e-Admit Cards for Various Examinations of UPSC"</strong>.</li>
    <li>Select <strong>"National Defence Academy &amp; Naval Academy Examination (II), 2026"</strong>.</li>
    <li>Carefully read the statutory examination instructions and click on <strong>'Yes'</strong> to confirm print preview.</li>
    <li>Choose your login verification method:
        <ul>
            <li><strong>By Registration ID:</strong> Enter your 11-digit Registration ID and Date of Birth (DD/MM/YYYY).</li>
            <li><strong>By Roll Number:</strong> Enter your 7-digit Roll Number and Date of Birth.</li>
        </ul>
    </li>
    <li>Enter the security Captcha code shown on the screen and click <strong>Submit</strong>.</li>
    <li>Your UPSC NDA 2 2026 e-Admit Card will appear. Download the PDF and take <strong>at least two clear printouts on A4 paper</strong>.</li>
</ol>

<h2 id="exam-day-mandatory-items">Mandatory Documents to Carry on Exam Day</h2>
<p>Candidates must produce the following original credentials at the frisking booth and examination hall:</p>
<ul>
    <li><strong>Hard Copy of E-Admit Card:</strong> Printed clearly on A4 paper.</li>
    <li><strong>Original Photo Identity Proof:</strong> The identical ID card specified in your online application form (e.g., Aadhaar Card, Voter ID, PAN Card, Passport, Driving License, or School/College ID).</li>
    <li><strong>Two Identical Passport-size Photographs:</strong> Required if the photograph on the e-Admit Card is blurred, faint, or missing.</li>
    <li><strong>Stationery:</strong> Good quality Black Ballpoint Pens.</li>
    <li><strong>Analogue Watch:</strong> Only simple wristwatches without digital or smart features are allowed.</li>
</ul>

<div class="info-callout" style="background-color: #fef2f2; border-left-color: #ef4444; color: #991b1b;">
    <div>
        <strong>Strictly Prohibited Items:</strong> Mobile phones, smartwatches, Bluetooth devices, earphones, calculators, log tables, study material, bags, and metallic items are barred from the examination premises. Possession of any electronic device, even in switched-off mode, will lead to immediate disqualification and debarment.
    </div>
</div>

<h2 id="frequently-asked-questions">Frequently Asked Questions (FAQs)</h2>

<h3 id="what-is-the-upsc-nda-2-2026-exam-date">What is the UPSC NDA 2 2026 examination date?</h3>
<p>The UPSC NDA (II) 2026 written examination is officially scheduled for <strong>September 13, 2026 (Sunday)</strong> across two shifts.</p>

<h3 id="when-was-the-upsc-nda-2-admit-card-released">When was the UPSC NDA 2 2026 admit card released?</h3>
<p>UPSC officially uploaded the e-admit cards on <strong>September 03, 2026</strong>. Registered candidates can download their hall tickets using Registration ID or Roll Number.</p>

<h3 id="what-are-the-shift-timings-for-mathematics-and-gat">What are the shift timings for Mathematics and GAT papers?</h3>
<p>Mathematics (Paper 1) will be held from <strong>10:00 AM to 12:30 PM</strong>, and GAT (Paper 2) will be held from <strong>02:00 PM to 04:30 PM</strong>. Entry gates close 30 minutes prior to each shift (09:30 AM and 01:30 PM).</p>

<h3 id="how-many-vacancies-are-available-in-nda-2-2026">How many vacancies are available in NDA 2 2026?</h3>
<p>A total of <strong>394 vacancies</strong> are notified across the National Defence Academy (Army, Navy, Air Force wings) and the Naval Academy (10+2 Cadet Entry Scheme).</p>

<h3 id="is-there-negative-marking-in-upsc-nda">Is there negative marking in the UPSC NDA examination?</h3>
<p>Yes. Negative marking of one-third is applicable for wrong answers: 0.83 marks are deducted for Mathematics and 1.33 marks are deducted for GAT.</p>
HTML;

Database::query("
    UPDATE articles 
    SET title = :title, 
        meta_title = :meta_title,
        excerpt = :excerpt, 
        meta_description = :meta_description,
        content = :content, 
        updated_at = NOW() 
    WHERE id = :id
", [
    'id' => $articleId,
    'title' => $newTitle,
    'meta_title' => $newTitle . ' | Sarkari.online',
    'excerpt' => $newExcerpt,
    'meta_description' => $newExcerpt,
    'content' => $newContent
]);

echo "✅ SUCCESS: Article #{$articleId} has been updated with 100% accurate UPSC NDA 2 2026 dates and shift timings!\n";
echo "   - Title: {$newTitle}\n";
echo "   - Admit Card Date: September 3, 2026\n";
echo "   - Exam Date: September 13, 2026\n";
echo "   - Maths Timing: 10:00 AM – 12:30 PM (300 Marks)\n";
echo "   - GAT Timing: 02:00 PM – 04:30 PM (600 Marks)\n";
echo "   - Vacancies: 394 Posts\n";
echo "=================================================================\n";
