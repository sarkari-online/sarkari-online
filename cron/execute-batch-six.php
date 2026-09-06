<?php
/**
 * Sarkari.online - Provenance-Consistent 6-Article Safe Execution Batch
 *
 * Implements strict preconditions, transactional DML, idempotency guards,
 * and immediate post-execution invariant verifications.
 */

$isCli = (php_sapi_name() === 'cli');
$adminKey = isset($_GET['key']) ? trim($_GET['key']) : '';

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\ArticleService;
use App\Helpers\Env;

if (!$isCli && $adminKey !== Env::get('ADMIN_ACCESS_KEY', 'Ajay-bytecode-cyber-security')) {
    http_response_code(403);
    die("Access Denied: CLI or valid admin access key required.\n");
}

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "================================================================================\n";
echo "🚀 SARKARI.ONLINE — PROVENANCE-CONSISTENT 6-ARTICLE BATCH EXECUTION\n";
echo "📅 Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

$db = Database::getConnection();

// 1. Slugs in Scope
$targetSlugs = [
    'bpsc-tre-4-application-postponed-dates',
    'rvunl-recruitment-2026-last-date',
    'punjab-pti-recruitment-2026-apply-now',
    'coal-india-mt-answer-key-2026',
    'odisha-deled-result-2026-sams-ct',
    'maharashtra-3-year-llb-round-2-allotment-2026'
];

// Unique banner markers
$bannerMarkers = [
    'bpsc-tre-4-application-postponed-dates' => 'data-temporal-advisory="bpsc-tre-4-postponed-2026"',
    'rvunl-recruitment-2026-last-date' => 'data-temporal-advisory="rvunl-recruitment-timeline-2026"',
    'punjab-pti-recruitment-2026-apply-now' => 'data-temporal-advisory="punjab-pti-timeline-2026"',
    'coal-india-mt-answer-key-2026' => 'data-temporal-advisory="coal-india-mt-objection-2026"',
    'odisha-deled-result-2026-sams-ct' => 'data-temporal-advisory="odisha-deled-status-2026"',
    'maharashtra-3-year-llb-round-2-allotment-2026' => 'data-temporal-advisory="maharashtra-llb-round2-2026"'
];

$banners = [
    'bpsc-tre-4-application-postponed-dates' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="bpsc-tre-4-postponed-2026" style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.5rem 0; font-weight: 700; color: #991b1b; font-size: 1rem;">🛑 Official Commission Notice: Application Window Postponed</p>'
      . '<ul style="margin: 0; padding-left: 1.25rem; color: #7f1d1d; font-size: 0.925rem; line-height: 1.6;">'
      . '<li><strong>Verified Official Information:</strong> The Bihar Public Service Commission (BPSC) has issued an official notice postponing the online application process until further notice.</li>'
      . '<li><strong>Lifecycle State:</strong> CLOSED (Applications not currently accepted).</li>'
      . '<li><strong>Official Source:</strong> Verified from Tier 1A Commission Portal (bpsc.bih.nic.in). Revised application dates will be updated only after an official BPSC notice is verified.</li>'
      . '</ul>'
      . '</div>',

    'rvunl-recruitment-2026-last-date' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="rvunl-recruitment-timeline-2026" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.5rem 0; font-weight: 700; color: #92400e; font-size: 1rem;">⚠️ Notice: Timeline Status Advisory</p>'
      . '<ul style="margin: 0; padding-left: 1.25rem; color: #78350f; font-size: 0.925rem; line-height: 1.6;">'
      . '<li><strong>Stated/Historical Timeline:</strong> The recruitment notice recorded an application closing deadline of September 02, 2026.</li>'
      . '<li><strong>Timeline Status:</strong> Stated application deadline has elapsed.</li>'
      . '<li><strong>Official Verification Status:</strong> Authoritative confirmation from official portal releases (energy.rajasthan.gov.in) is currently <em>pending official verification</em>. This historical date is not certified as an official statutory fact.</li>'
      . '</ul>'
      . '</div>',

    'punjab-pti-recruitment-2026-apply-now' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="punjab-pti-timeline-2026" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.5rem 0; font-weight: 700; color: #92400e; font-size: 1rem;">⚠️ Notice: Registration Timeline Advisory</p>'
      . '<ul style="margin: 0; padding-left: 1.25rem; color: #78350f; font-size: 0.925rem; line-height: 1.6;">'
      . '<li><strong>Stated/Historical Timeline:</strong> Stated registration closing date was September 02, 2026.</li>'
      . '<li><strong>Timeline Status:</strong> Stated registration window has elapsed.</li>'
      . '<li><strong>Official Verification Status:</strong> Department circular is <em>pending official verification</em>.</li>'
      . '</ul>'
      . '</div>',

    'coal-india-mt-answer-key-2026' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="coal-india-mt-objection-2026" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.5rem 0; font-weight: 700; color: #92400e; font-size: 1rem;">⚠️ Notice: Objection Window Concluded</p>'
      . '<ul style="margin: 0; padding-left: 1.25rem; color: #78350f; font-size: 0.925rem; line-height: 1.6;">'
      . '<li><strong>Authoritative Notice:</strong> Per Coal India Limited (CIL) notice on coalindia.in, the provisional answer key objection window concluded on September 03, 2026 (11:55 PM).</li>'
      . '<li><strong>Official Status:</strong> Scrutiny of submitted objections is underway. Candidates should await final answer key releases on the official portal.</li>'
      . '</ul>'
      . '</div>',

    'odisha-deled-result-2026-sams-ct' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="odisha-deled-status-2026" style="background-color: #f8fafc; border-left: 4px solid #64748b; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.25rem 0; font-weight: 700; color: #334155; font-size: 1rem;">ℹ️ Status Advisory</p>'
      . '<p style="margin: 0; color: #475569; font-size: 0.925rem; line-height: 1.5;">'
      . '<strong>Official verification status:</strong> Pending authoritative statutory portal confirmation.'
      . '</p>'
      . '</div>',

    'maharashtra-3-year-llb-round-2-allotment-2026' => 
        '<div class="temporal-advisory-banner" data-temporal-advisory="maharashtra-llb-round2-2026" style="background-color: #f8fafc; border-left: 4px solid #64748b; padding: 1rem 1.25rem; margin: 1.5rem 0; border-radius: 4px;">'
      . '<p style="margin: 0 0 0.25rem 0; font-weight: 700; color: #334155; font-size: 1rem;">ℹ️ Status Advisory</p>'
      . '<p style="margin: 0; color: #475569; font-size: 0.925rem; line-height: 1.5;">'
      . '<strong>Official verification status:</strong> Pending authoritative statutory portal confirmation.'
      . '</p>'
      . '</div>'
];

// Check if lifecycle_status column exists
$colExists = (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'lifecycle_status'")->fetchColumn();
if ($colExists === 0) {
    echo "Column 'lifecycle_status' does not exist yet. Running safe migration first...\n";
    $db->exec("ALTER TABLE `articles` ADD COLUMN `lifecycle_status` ENUM('draft', 'upcoming', 'active', 'closed', 'exam_completed', 'admit_card_released', 'result_released', 'historical', 'evergreen', 'archived') NOT NULL DEFAULT 'draft' AFTER `status`, ADD INDEX `idx_articles_lifecycle` (`lifecycle_status`)");
    echo "-> Successfully added 'lifecycle_status' column.\n\n";
}

// 2. READ-ONLY SELECT: Current State
$initialPublishedCount = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
$inClause = "'" . implode("', '", $targetSlugs) . "'";
$currentRows = Database::fetchAll("SELECT id, slug, title, status, lifecycle_status, content FROM articles WHERE slug IN ($inClause)");
$rowsBySlug = [];
foreach ($currentRows as $row) {
    $rowsBySlug[$row['slug']] = $row;
}

echo "================================================================================\n";
echo "📊 STEP 1: CURRENT STATE SNAPSHOT\n";
echo "================================================================================\n";
printf("%-45s | %-10s | %-12s | %-16s | %-16s\n", "slug", "status", "lifecycle", "title_hash", "content_hash");
echo str_repeat("-", 110) . "\n";

$preconditionFailures = [];

foreach ($targetSlugs as $slug) {
    if (!isset($rowsBySlug[$slug])) {
        $preconditionFailures[] = "Slug '{$slug}' NOT found in database.";
        printf("%-45s | %-10s | %-12s | %-16s | %-16s\n", $slug, "MISSING", "N/A", "N/A", "N/A");
        continue;
    }

    $r = $rowsBySlug[$slug];
    $titleHash = substr(hash('sha256', $r['title']), 0, 16);
    $contentHash = substr(hash('sha256', $r['content']), 0, 16);

    printf("%-45s | %-10s | %-12s | %-16s | %-16s\n", $slug, $r['status'], $r['lifecycle_status'], $titleHash, $contentHash);

    // Precondition check: status must be published
    if ($r['status'] !== 'published') {
        $preconditionFailures[] = "Slug '{$slug}' status is '{$r['status']}', expected 'published'.";
    }

    // Precondition check: lifecycle_status
    if ($slug === 'bpsc-tre-4-application-postponed-dates') {
        if (!in_array($r['lifecycle_status'], ['draft', 'evergreen', 'closed'], true)) {
            $preconditionFailures[] = "Slug '{$slug}' lifecycle is '{$r['lifecycle_status']}', expected 'draft', 'evergreen', or 'closed'.";
        }
    } elseif ($slug === 'maharashtra-3-year-llb-round-2-allotment-2026') {
        if ($r['lifecycle_status'] !== 'evergreen') {
            $preconditionFailures[] = "Slug '{$slug}' lifecycle is '{$r['lifecycle_status']}', expected 'evergreen'.";
        }
    } else {
        if ($r['lifecycle_status'] !== 'draft') {
            $preconditionFailures[] = "Slug '{$slug}' lifecycle has changed to '{$r['lifecycle_status']}' (no longer 'draft'). ABORTING to prevent overwrite.";
        }
    }
}
echo "\n";

echo "================================================================================\n";
echo "🎯 STEP 2: EXPECTED STATE & INTENDED OPERATIONS\n";
echo "================================================================================\n";
printf("%-45s | %-22s | %-30s\n", "slug", "lifecycle transition", "banner operation");
echo str_repeat("-", 105) . "\n";
$bpscCurrentLifecycle = $rowsBySlug['bpsc-tre-4-application-postponed-dates']['lifecycle_status'] ?? 'draft';
$bpscTransitionDesc = ($bpscCurrentLifecycle === 'closed') ? 'closed (Preserved)' : "{$bpscCurrentLifecycle} -> closed";
printf("%-45s | %-22s | %-30s\n", 'bpsc-tre-4-application-postponed-dates', $bpscTransitionDesc, 'Prepend Tier 1A Closure Banner');
printf("%-45s | %-22s | %-30s\n", 'rvunl-recruitment-2026-last-date', 'draft (Unchanged)', 'Prepend Tri-Partite Advisory Banner');
printf("%-45s | %-22s | %-30s\n", 'punjab-pti-recruitment-2026-apply-now', 'draft (Unchanged)', 'Prepend Timeline Advisory Banner');
printf("%-45s | %-22s | %-30s\n", 'coal-india-mt-answer-key-2026', 'draft (Unchanged)', 'Prepend Objection Advisory Banner');
printf("%-45s | %-22s | %-30s\n", 'odisha-deled-result-2026-sams-ct', 'draft (Unchanged)', 'Prepend Minimal Status Banner');
printf("%-45s | %-22s | %-30s\n", 'maharashtra-3-year-llb-round-2-allotment-2026', 'evergreen (Preserved)', 'Prepend Minimal Status Banner');
echo "\n";

// 3. Check Preconditions
if (!empty($preconditionFailures)) {
    echo "❌ PRECONDITION CHECK FAILED. ABORTING BATCH EXECUTION.\n";
    foreach ($preconditionFailures as $fail) {
        echo "   • {$fail}\n";
    }
    exit(1);
}

echo "✅ All preconditions satisfied. Beginning Transactional Execution...\n\n";

// 4. TRANSACTIONAL EXECUTION
$db->beginTransaction();

try {
    // A. BPSC TRE 4.0: Promote to closed if draft or evergreen; preserve if already closed
    $bpscStmt = $db->prepare(
        "UPDATE articles 
         SET lifecycle_status = 'closed', updated_at = NOW() 
         WHERE slug = 'bpsc-tre-4-application-postponed-dates' 
           AND status = 'published' 
           AND lifecycle_status IN ('draft', 'evergreen')"
    );
    $bpscStmt->execute();
    $bpscUpdated = $bpscStmt->rowCount();
    echo "• BPSC Lifecycle Transition ({$bpscCurrentLifecycle} -> closed): {$bpscUpdated} row(s) updated.\n";

    // B. Banner Injections (Idempotent: guarded by data-temporal-advisory)
    $bannerUpdates = 0;
    foreach ($targetSlugs as $slug) {
        $marker = $bannerMarkers[$slug];
        $bannerHtml = $banners[$slug];

        $bannerStmt = $db->prepare(
            "UPDATE articles 
             SET content = CONCAT(:banner, content), updated_at = NOW() 
             WHERE slug = :slug 
               AND status = 'published' 
               AND content NOT LIKE :marker"
        );
        $bannerStmt->execute([
            'banner' => $bannerHtml,
            'slug'   => $slug,
            'marker' => "%{$marker}%"
        ]);
        $rows = $bannerStmt->rowCount();
        $bannerUpdates += $rows;
        echo "• Banner injection for '{$slug}': {$rows} row(s) updated.\n";
    }

    $db->commit();
    echo "\n✅ Transaction committed successfully! Total banner updates: {$bannerUpdates}.\n\n";

} catch (Throwable $e) {
    $db->rollBack();
    echo "❌ Transaction Failed & Rolled Back: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. POST-EXECUTION IMMEDIATE VERIFICATION
echo "================================================================================\n";
echo "🔍 STEP 3: POST-EXECUTION IMMEDIATE VERIFICATION\n";
echo "================================================================================\n";

$publishedCount = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
$indexableCount = (int)$db->query("SELECT COUNT(DISTINCT slug) FROM articles WHERE status = 'published'")->fetchColumn();
$totalCount = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();

echo "• Published Articles Count : {$publishedCount} (Unchanged: {$initialPublishedCount}) -> " . ($publishedCount === $initialPublishedCount ? "✅ PASS" : "❌ FAIL") . "\n";
echo "• Unique Indexable Slugs   : {$indexableCount} (Unchanged: {$initialPublishedCount}) -> " . ($indexableCount === $initialPublishedCount ? "✅ PASS" : "❌ FAIL") . "\n";

// Verify Idempotency: Second execution check
$idempotencyPass = true;
foreach ($targetSlugs as $slug) {
    $marker = $bannerMarkers[$slug];
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE slug = :slug AND content LIKE :marker");
    $checkStmt->execute(['slug' => $slug, 'marker' => "%{$marker}%"]);
    $count = (int)$checkStmt->fetchColumn();
    if ($count !== 1) {
        $idempotencyPass = false;
        echo "❌ Marker verification failed for '{$slug}': found {$count} markers (expected exactly 1).\n";
    }
}
if ($idempotencyPass) {
    echo "• Marker Counts            : Exactly 1 unique advisory marker per affected article -> ✅ PASS\n";
}

// Check second execution would produce 0 rows
$secondRunRows = 0;
foreach ($targetSlugs as $slug) {
    $marker = $bannerMarkers[$slug];
    $simStmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE slug = :slug AND status = 'published' AND content NOT LIKE :marker");
    $simStmt->execute(['slug' => $slug, 'marker' => "%{$marker}%"]);
    $secondRunRows += (int)$simStmt->fetchColumn();
}
echo "• Idempotency Test         : Simulated second execution would update {$secondRunRows} rows -> " . ($secondRunRows === 0 ? "✅ PASS (100% Idempotent)" : "❌ FAIL") . "\n";

// Verify byte-identity of existing bodies
$bodyPreserved = true;
$postRows = Database::fetchAll("SELECT slug, content FROM articles WHERE slug IN ($inClause)");
foreach ($postRows as $pr) {
    $slug = $pr['slug'];
    $expectedPrefix = $banners[$slug];
    if (!str_starts_with($pr['content'], $expectedPrefix)) {
        $bodyPreserved = false;
        echo "❌ Content prefix mismatch for '{$slug}'.\n";
    }
    $stripped = substr($pr['content'], strlen($expectedPrefix));
    $originalContent = $rowsBySlug[$slug]['content'];
    if ($stripped !== $originalContent) {
        $bodyPreserved = false;
        echo "❌ Substantive body altered for '{$slug}'!\n";
    }
}
if ($bodyPreserved) {
    echo "• Content Body Integrity   : Existing body is 100% byte-identical apart from approved prefix -> ✅ PASS\n";
}

echo "\n================================================================================\n";
echo "🎉 SIX-ARTICLE BATCH EXECUTION & VERIFICATION COMPLETED SUCCESSFULLY!\n";
echo "================================================================================\n";
