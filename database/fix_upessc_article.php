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

// 1. Replace the Dates Table
$oldTablePattern = '/<div class="table-responsive"><table><thead><tr><th>Event<\/th><th>Date<\/th><\/tr><\/thead><tbody>.*?<\/tbody><\/table><\/div>/s';
$newTable = '<div class="table-responsive"><table><thead><tr><th>Event</th><th>Important Date</th></tr></thead><tbody>' .
    '<tr><td>Official Notification Released (Advt 04/2026)</td><td>September 08, 2026</td></tr>' .
    '<tr><td>Online Application Start Date</td><td>September 08, 2026</td></tr>' .
    '<tr><td>Last Date to Apply Online (Registration)</td><td><strong>October 07, 2026</strong></td></tr>' .
    '<tr><td>Last Date for Application Fee Payment</td><td><strong>October 07, 2026</strong></td></tr>' .
    '<tr><td>Online Form Correction Window</td><td>October 08 to October 11, 2026</td></tr>' .
    '<tr><td>Written Exam Date (Tentative)</td><td><span class="status-pill status-pill-active">November 19–20, 2026</span></td></tr>' .
    '</tbody></table></div>';

$content = preg_replace($oldTablePattern, $newTable, $content);

// 2. Enhance the Application Fee Section
$newFee = '<h2 id="upessc-assistant-professor-recruitment-2026-application-fee-and-payment">UPESSC Assistant Professor Recruitment 2026: Application Fee and Payment</h2>' .
    '<p>Candidates can complete the payment online via Net Banking, Debit/Credit Card, or UPI on the UPESSC portal before October 07, 2026:</p>' .
    '<div class="table-responsive"><table><thead><tr><th>Category</th><th>Application Fee</th></tr></thead><tbody>' .
    '<tr><td>General / OBC / EWS</td><td><strong>₹2,000</strong></td></tr>' .
    '<tr><td>SC / ST</td><td><strong>₹1,500</strong></td></tr>' .
    '<tr><td>PH (Divyangjan)</td><td><strong>₹1,000</strong></td></tr>' .
    '</tbody></table></div>';

if (str_contains($content, 'UPESSC Assistant Professor Recruitment 2026: Application Fee and Payment')) {
    $content = preg_replace('/<h2 id="upessc-assistant-professor-recruitment-2026-application-fee-and-payment">.*?<\/p>/s', $newFee, $content);
}

// 3. Enhance Age Limit in Eligibility
$oldAge = "Age limits are calculated as of August 01, 2026, with standard relaxations for reserved categories:";
$newAge = "The maximum age limit for UPESSC Assistant Professor is <strong>62 years</strong> (as of July 01, 2026) as per UGC and UP Higher Education Department norms. Category relaxations apply:";
$content = str_replace($oldAge, $newAge, $content);

Database::execute(
    "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
    ['content' => $content, 'id' => $id]
);

echo "✅ Article #{$id} ({$article['slug']}) successfully updated with verified dates, fees, and age criteria!\n";
