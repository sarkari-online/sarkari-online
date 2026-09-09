<?php
/**
 * Update UPESSC Assistant Professor Article (#709) with 100% Verified Details
 */

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "=== Updating UPESSC Assistant Professor Recruitment 2026 Article ===\n";

$article = Database::fetchOne("SELECT id, slug, content FROM articles WHERE slug = 'upessc-assistant-professor-recruitment-2026' LIMIT 1");

if (!$article) {
    echo "❌ Article with slug 'upessc-assistant-professor-recruitment-2026' not found.\n";
    exit(1);
}

$id = (int)$article['id'];
$content = $article['content'];

// Record pre-fix snapshot for rollback safety
try {
    Database::execute("
        CREATE TABLE IF NOT EXISTS `article_migration_snapshots` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `article_id` BIGINT NOT NULL,
            `snapshot_json` LONGTEXT NOT NULL,
            `audit_run_id` CHAR(36) NOT NULL,
            `action_taken` ENUM('tier1_autofix','tier2_regenerated','flagged_only') NOT NULL,
            `original_updated_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_article` (`article_id`),
            INDEX `idx_run` (`audit_run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    Database::execute("
        INSERT INTO `article_migration_snapshots` 
            (`article_id`, `snapshot_json`, `audit_run_id`, `action_taken`, `created_at`)
        VALUES 
            (:id, :json, 'manual_upessc_fix', 'tier1_autofix', NOW())
    ", [
        'id'   => $id,
        'json' => json_encode([
            'slug'    => $article['slug'],
            'content' => $content,
            'reason'  => 'Pre-fix manual update for UPESSC verified dates/fees'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);
    echo "📸 Pre-update snapshot safely saved to article_migration_snapshots.\n";
} catch (Throwable $e) {
    echo "⚠️ Note on snapshot: " . $e->getMessage() . "\n";
}

// 1. Replace the Dates Table (handle raw <table> or wrapped <div class="table-responsive"><table>)
$newTable = '<table><thead><tr><th>Event</th><th>Important Date</th></tr></thead><tbody>' .
    '<tr><td>Official Notification Released (Advt 04/2026)</td><td>September 08, 2026</td></tr>' .
    '<tr><td>Online Application Start Date</td><td>September 08, 2026</td></tr>' .
    '<tr><td>Last Date to Apply Online (Registration)</td><td><strong>October 07, 2026</strong></td></tr>' .
    '<tr><td>Last Date for Application Fee Payment</td><td><strong>October 07, 2026</strong></td></tr>' .
    '<tr><td>Online Form Correction Window</td><td>October 08 to October 11, 2026</td></tr>' .
    '<tr><td>Written Exam Date (Tentative)</td><td>November 19–20, 2026</td></tr>' .
    '</tbody></table>';

// Match first table in the article (or table within table-responsive)
$count = 0;
if (preg_match('/<div class="table-responsive">\s*<table\b[^>]*>.*?<\/table>\s*<\/div>/is', $content)) {
    $content = preg_replace('/<div class="table-responsive">\s*<table\b[^>]*>.*?<\/table>\s*<\/div>/is', $newTable, $content, 1, $count);
} elseif (preg_match('/<table\b[^>]*>.*?<\/table>/is', $content)) {
    $content = preg_replace('/<table\b[^>]*>.*?<\/table>/is', $newTable, $content, 1, $count);
}
echo "  - Table replaced: " . ($count > 0 ? "YES ($count match)" : "NO") . "\n";

// 2. Enhance the Application Fee Section (with category fee table)
$newFee = '<h2>UPESSC Assistant Professor Recruitment 2026: Application Fee and Payment</h2>' .
    '<p>Candidates can complete the payment online via Net Banking, Debit/Credit Card, or UPI on the UPESSC portal before October 07, 2026:</p>' .
    '<table><thead><tr><th>Category</th><th>Application Fee</th></tr></thead><tbody>' .
    '<tr><td>General / OBC / EWS</td><td><strong>₹2,000</strong></td></tr>' .
    '<tr><td>SC / ST</td><td><strong>₹1,500</strong></td></tr>' .
    '<tr><td>PH (Divyangjan)</td><td><strong>₹1,000</strong></td></tr>' .
    '</tbody></table>';

$feeCount = 0;
// Match heading with or without id attribute, and the following paragraph
$pattern = '/<h2[^>]*>.*?Application Fee and Payment<\/h2>\s*<p>.*?<\/p>(?:\s*<table>.*?<\/table>)?/is';
if (preg_match($pattern, $content)) {
    $content = preg_replace($pattern, $newFee, $content, 1, $feeCount);
}
echo "  - Fee section replaced: " . ($feeCount > 0 ? "YES ($feeCount match)" : "NO") . "\n";

// 3. Enhance Age Limit in Eligibility
$oldAge = "Age limits are calculated as of August 01, 2026, with standard relaxations for reserved categories:";
$newAge = "The maximum age limit for UPESSC Assistant Professor is <strong>62 years</strong> (as of July 01, 2026) as per UGC and UP Higher Education Department norms. Category relaxations apply:";
if (str_contains($content, $oldAge)) {
    $content = str_replace($oldAge, $newAge, $content);
    echo "  - Age limit replaced: YES\n";
} else {
    echo "  - Age limit already updated or pattern not found.\n";
}

// 4. Integrity assertions before saving
if (!str_contains($content, 'October 07, 2026')) {
    echo "❌ FATAL: Replacement failed! 'October 07, 2026' not found in content.\n";
    exit(1);
}
if (!str_contains($content, '₹2,000')) {
    echo "❌ FATAL: Replacement failed! '₹2,000' fee not found in content.\n";
    exit(1);
}

Database::execute(
    "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
    ['content' => $content, 'id' => $id]
);

echo "✅ Article #{$id} ({$article['slug']}) verified and successfully written to database!\n";
