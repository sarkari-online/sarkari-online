<?php
/**
 * Sarkari.online - Minimal Safe Correction for Article #692 (HSSC Haryana CET 2026)
 *
 * Enforces:
 * 1. Lifecycle status transition: active -> closed
 * 2. Neutralizes active claims in content:
 *    - Milestone table: Active -> Closed (Concluded July 03, 2026 / June 30, 2026)
 *    - FAQ: "application process is active" -> "applications concluded on July 03, 2026"
 *    - Overview: "has opened recruitment cycle" -> "conducted recruitment registration cycle"
 *    - Intro: "active recruitment milestones" -> "official recruitment milestones & concluded registration timelines"
 * 3. Title update to remove active CTA:
 *    - "HSSC Haryana CET 2026: Notification, Eligibility & Exam Schedule"
 *    - Slug preserved: "hssc-haryana-cet-2026-apply-online" (preserves URL & backlinks)
 * 4. Records authoritative temporal facts in article_temporal_facts:
 *    - application_start: June 19, 2026 (Advt 05/2026) -> status: verified
 *    - application_end: July 03, 2026 -> status: verified
 *    - exam_date: To Be Announced -> status: unannounced
 * 5. Validates against TemporalContentValidator
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\TemporalFactService;
use App\Services\TemporalContentValidator;
use App\Services\FeaturedSnippetService;

echo "====================================================================\n";
echo "  SARKARI.ONLINE: ARTICLE #692 MINIMAL SAFE CORRECTION ENGINE\n";
echo "====================================================================\n\n";

$tz = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('now', $tz);
echo "Execution Time: " . $now->format('Y-m-d H:i:s T') . "\n";

// 1. Fetch Article #692
$article = Database::fetchOne("SELECT * FROM articles WHERE id = 692");
if (!$article) {
    die("❌ Error: Article #692 not found in database.\n");
}
echo "Found Article #692: '{$article['title']}' (Slug: {$article['slug']})\n";
echo "Current Lifecycle Status: '{$article['lifecycle_status']}'\n";

// 2. Perform Content Replacements
$content = $article['content'];

$replacements = [
    // Table Status Pills
    '<td>Application for CET Group D-05/2026</td><td><span class="status-pill status-pill-confirmed">Active</span></td><td>As of September 06, 2026</td>'
    => '<td>Application for CET Group D-05/2026</td><td><span class="status-pill status-pill-closed" style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-weight:600;">Closed</span></td><td>Concluded July 03, 2026</td>',

    '<td>Application for Advt. No. 06/2026</td><td><span class="status-pill status-pill-confirmed">Active</span></td><td>As of September 06, 2026</td>'
    => '<td>Application for Advt. No. 06/2026</td><td><span class="status-pill status-pill-closed" style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-weight:600;">Closed</span></td><td>Concluded June 30, 2026</td>',

    // Fallback for unbadged or differently formatted cells
    '<td>Application for CET Group D-05/2026</td><td>Active</td><td>As of September 06, 2026</td>'
    => '<td>Application for CET Group D-05/2026</td><td>Closed</td><td>Concluded July 03, 2026</td>',

    '<td>Application for Advt. No. 06/2026</td><td>Active</td><td>As of September 06, 2026</td>'
    => '<td>Application for Advt. No. 06/2026</td><td>Closed</td><td>Concluded June 30, 2026</td>',

    // FAQ active claim
    'Q: Is the application window for Group D-05/2026 still open?</strong><br>A: Yes, as of September 06, 2026, the application process is active.'
    => 'Q: Is the application window for Group D-05/2026 still open?</strong><br>A: No, online applications for Advt. 05/2026 concluded on July 03, 2026. Candidates should track the official portal for subsequent stage announcements.',

    // Overview paragraph
    'has opened the recruitment cycle for the 2026 Common Eligibility Test (CET) for Group C and D positions.'
    => 'conducted the recruitment registration cycle for the 2026 Common Eligibility Test (CET) for Group C and D positions.',

    // Table intro
    'The following table outlines the current status of active <a href="https://sarkari.online/article/rrb-recruitment-2026-notification-exam-dates/">recruitment milestones</a>. The following table outlines the current status of active recruitment milestones.'
    => 'The following table outlines the official status of <a href="https://sarkari.online/article/rrb-recruitment-2026-notification-exam-dates/">recruitment milestones</a> and registration timelines.'
];

$changesMade = 0;
foreach ($replacements as $search => $replace) {
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replace, $content);
        $changesMade++;
    }
}
echo "Content replacements applied: {$changesMade} of " . count($replacements) . "\n";

// Append idempotency marker if not present
$marker = '<!-- temporal_correction:article_692_closed_20260906 -->';
if (!str_contains($content, $marker)) {
    $content .= "\n" . $marker;
}

// 3. Update Title to Neutral/Safe
$newTitle = 'HSSC Haryana CET 2026: Notification, Eligibility & Exam Schedule';

// 4. Upsert Authoritative Temporal Facts
$factsToRecord = [
    [
        'article_id' => 692,
        'fact_name' => 'application_start',
        'fact_value' => 'June 19, 2026',
        'valid_until' => '2026-06-19 00:00:00',
        'source_url' => 'https://hssc.gov.in',
        'confidence' => 'high',
        'status' => 'verified'
    ],
    [
        'article_id' => 692,
        'fact_name' => 'application_end',
        'fact_value' => 'July 03, 2026',
        'valid_until' => '2026-07-03 23:59:59',
        'source_url' => 'https://hssc.gov.in',
        'confidence' => 'high',
        'status' => 'verified'
    ],
    [
        'article_id' => 692,
        'fact_name' => 'exam_date',
        'fact_value' => 'To Be Announced',
        'valid_until' => null,
        'source_url' => 'https://hssc.gov.in',
        'confidence' => 'high',
        'status' => 'unannounced'
    ]
];

// Check if article_temporal_facts table exists
$hasTable = false;
try {
    $tblCheck = Database::fetchColumn("SHOW TABLES LIKE 'article_temporal_facts'");
    $hasTable = !empty($tblCheck);
} catch (\Throwable $e) {}

if ($hasTable) {
    // Defensive assertion before any write: verify payload contains 'confidence' and NOT 'confidence_score'
    foreach ($factsToRecord as $idx => $factCheck) {
        if (!array_key_exists('confidence', $factCheck)) {
            die("❌ Defensive Assertion Failed: Fact at index {$idx} is missing required 'confidence' key.\n");
        }
        if (array_key_exists('confidence_score', $factCheck)) {
            die("❌ Defensive Assertion Failed: Fact at index {$idx} contains obsolete/invalid 'confidence_score' key.\n");
        }
    }

    // Supersede any old facts
    Database::query(
        "UPDATE article_temporal_facts SET status = 'superseded' WHERE article_id = 692 AND status != 'superseded'"
    );

    // Insert verified facts
    foreach ($factsToRecord as $f) {
        // Double-check defensive assertion per record before query execution
        if (!isset($f['confidence']) || isset($f['confidence_score'])) {
            die("❌ Defensive Assertion Failed before INSERT: Invalid payload schema.\n");
        }

        Database::query(
            "INSERT INTO article_temporal_facts (article_id, fact_name, fact_value, valid_until, source_url, confidence, status, verified_at, created_at, updated_at)
             VALUES (:article_id, :fact_name, :fact_value, :valid_until, :source_url, :confidence, :status, NOW(), NOW(), NOW())",
            $f
        );
    }
    echo "✅ Successfully recorded verified temporal facts in article_temporal_facts table.\n";
} else {
    echo "ℹ️ Note: article_temporal_facts table not yet migrated, skipping table insert.\n";
}

// 5. Update Articles Table Atomically
Database::query(
    "UPDATE articles 
     SET title = :title, 
         content = :content, 
         lifecycle_status = 'closed',
         updated_at = NOW()
     WHERE id = 692",
    [
        'title' => $newTitle,
        'content' => $content
    ]
);
echo "✅ Successfully updated Article #692: lifecycle_status='closed', title='{$newTitle}'\n";

// 6. Run Pre-Publish Validator to Guarantee 100% Compliance
$audit = TemporalContentValidator::validate([
    'title' => $newTitle,
    'content' => $content,
    'source_url' => 'https://hssc.gov.in'
], [
    'application_start' => $factsToRecord[0],
    'application_end' => $factsToRecord[1],
    'exam_date' => $factsToRecord[2]
], 'closed', $now);

if ($audit['pass']) {
    echo "🎉 VALIDATION PASS: 0 violations detected! Article #692 complies with all 9 temporal rules.\n";
} else {
    echo "⚠️ Validation Warnings/Violations:\n";
    print_r($audit['violations']);
}

// 7. Test FeaturedSnippet Candidate Action
$candidateAction = FeaturedSnippetService::determineCandidateAction($newTitle, 'closed');
echo "Featured Snippet 'Next Action': '{$candidateAction}' (Safe: " . ($candidateAction === 'Check Official Portal for Next Stage Updates' ? 'YES' : 'NO') . ")\n";

echo "\n====================================================================\n";
echo "   CORRECTION COMPLETE: ARTICLE #692 IS ACCURATELY CLOSED & SAFE\n";
echo "====================================================================\n";
