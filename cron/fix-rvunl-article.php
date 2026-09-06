<?php
/**
 * Sarkari.online - Manual/Automated Fix for RVUNL Recruitment Article
 * Updates Article (slug: rvunl-recruitment-2026-last-date)
 * Transitions from "Last Date Today" to "Application Closed: Exam Date & Next Stage"
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting RVUNL Recruitment Article Fix...\n";

$slug = 'rvunl-recruitment-2026-last-date';
$article = Database::fetchOne(
    "SELECT id, title, excerpt, content FROM articles WHERE slug = :slug OR slug LIKE '%rvunl%' LIMIT 1",
    ['slug' => $slug]
);

if (!$article) {
    echo "❌ Article with slug '{$slug}' not found.\n";
    exit(1);
}

$id = (int)$article['id'];
echo "Found Article #{$id}: '{$article['title']}'\n";

$newTitle = "RVUNL Recruitment 2026: Application Closed for 2005 Posts, Exam Date & Next Stage";
$newExcerpt = "RVUNL Recruitment 2026 online application process has closed for 2,005 vacancies. Check CBT exam date, admit card updates, shift timings, and syllabus.";

$content = $article['content'];

// 1. Replace "Final Application Day" or "Last Date Today" heading
$content = preg_replace(
    '/<h2[^>]*id="[^"]*"[^>]*>.*?Final Application Day.*?<\/h2>/i',
    '<h2 id="rvunl-recruitment-2026-application-concluded">RVUNL Recruitment 2026: Online Application Process Concluded</h2>',
    $content
);

// 2. Replace the introductory paragraph asserting "today is the deadline"
$oldIntroRegex = '/The Rajasthan Rajya Vidyut Utpadan Nigam Limited \(RVUNL\) has officially set September 02, 2026, as the final deadline.*?permanent disqualification from this recruitment cycle\./s';
$newIntro = 'The online application and fee submission window for the 2,005 technical and administrative posts under RVUNL Recruitment 2026 officially concluded on September 02, 2026. The application portal at energy.rajasthan.gov.in is now closed for fresh registrations. Candidates who successfully submitted their applications before the deadline can access their submitted forms and printouts until September 15, 2026. The recruitment process now enters the Computer Based Test (CBT) phase, and candidates are awaiting the official examination schedule and shift allocation from the corporation.';

if (preg_match($oldIntroRegex, $content)) {
    $content = preg_replace($oldIntroRegex, $newIntro, $content);
} else {
    // General replacement for any "today" urgency in first paragraph
    $content = preg_replace(
        '/<p>The Rajasthan Rajya Vidyut Utpadan Nigam Limited.*?<\/p>/s',
        '<p>' . $newIntro . '</p>',
        $content,
        1
    );
}

// 3. Update the Timeline Table to reflect Closed status
$content = str_replace(
    '<td>September 02, 2026</td>' . "\n" . '      <td><span class="status-pill status-pill-confirmed">Confirmed</span></td>',
    '<td>September 02, 2026</td>' . "\n" . '      <td><span class="status-pill status-pill-closed" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;">Closed (Concluded)</span></td>',
    $content
);

// 4. Update Step 1 intro if it says "lock their submissions on the final day"
$content = str_replace(
    'Applicants aiming to lock their submissions on the final day should adhere to the following procedure:',
    'For candidates who completed the registration process or require a reprint of their submitted application form, follow these official portal steps:',
    $content
);

// 5. Update FAQ #1 from "What is the last date" to status
$content = str_replace(
    'What is the last date to apply for RVUNL Recruitment 2026?',
    'Is the application process for RVUNL Recruitment 2026 still open?',
    $content
);
$content = str_replace(
    '<p>The last date to submit online applications and complete fee payments for the 2,005 posts is September 02, 2026.</p>',
    '<p>No, the online application window officially closed on September 02, 2026. No further applications or fee payments are being accepted for this recruitment cycle. Candidates are now preparing for the upcoming Computer Based Test (CBT).</p>',
    $content
);

// 6. Update direct answer snippet in content if present
$content = str_replace(
    'RVUNL Recruitment 2026 final application deadline is today for 2005 vacancies. Check direct link, eligibility, and how to apply online.',
    'RVUNL Recruitment 2026 online application process concluded on September 02, 2026. Check CBT exam date, admit card release schedule, and syllabus.',
    $content
);

// Update database
Database::update('articles', [
    'title'      => $newTitle,
    'excerpt'    => $newExcerpt,
    'content'    => $content,
    'updated_at' => date('Y-m-d H:i:s')
], 'id = :id', ['id' => $id]);

echo "✅ SUCCESS: Article #{$id} has been updated!\n";
echo "   New Title   : {$newTitle}\n";
echo "   New Excerpt : {$newExcerpt}\n";
echo "   Status      : Concluded / Closed\n";
Logger::info("Manually corrected RVUNL article #{$id} to reflect closed deadline and CBT next stage.");
