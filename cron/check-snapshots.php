<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "========================================================================\n";
echo "🔍 Checking Article Snapshots & Backup Status\n";
echo "========================================================================\n\n";

try {
    $count = Database::fetchOne("SELECT COUNT(*) as cnt FROM article_migration_snapshots");
    echo "Total snapshots in article_migration_snapshots: " . ($count['cnt'] ?? 0) . "\n\n";

    if (($count['cnt'] ?? 0) > 0) {
        $runs = Database::fetchAll("
            SELECT audit_run_id, action_taken, COUNT(*) as cnt, MIN(created_at) as first_created, MAX(created_at) as last_created
            FROM article_migration_snapshots
            GROUP BY audit_run_id, action_taken
            ORDER BY last_created DESC
        ");

        echo "Snapshot Batches:\n";
        foreach ($runs as $r) {
            echo "  - Run ID: {$r['audit_run_id']} | Action: {$r['action_taken']} | Count: {$r['cnt']} | Date: {$r['last_created']}\n";
        }
        echo "\n";

        // Sample snapshot for article #728 if exists
        $sample = Database::fetchOne("
            SELECT article_id, snapshot_json, created_at 
            FROM article_migration_snapshots 
            ORDER BY id DESC LIMIT 1
        ");
        if ($sample) {
            $data = json_decode($sample['snapshot_json'], true);
            echo "Sample snapshot for Article #{$sample['article_id']} (Created: {$sample['created_at']}):\n";
            echo "  Title: " . ($data['title'] ?? 'N/A') . "\n";
            echo "  Content length: " . strlen($data['content'] ?? '') . " bytes\n";
            echo "  Has tables in snapshot: " . (str_contains($data['content'] ?? '', '<table') ? 'YES' : 'NO') . "\n";
            echo "  Has H3 FAQs in snapshot: " . (str_contains($data['content'] ?? '', '<h3>') ? 'YES' : 'NO') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "Error querying article_migration_snapshots: " . $e->getMessage() . "\n";
}

// Check other tables in database
try {
    $tables = Database::fetchAll("SHOW TABLES LIKE '%article%'");
    echo "\nArticle-related tables in DB:\n";
    foreach ($tables as $t) {
        echo "  - " . array_values($t)[0] . "\n";
    }
} catch (Throwable $e) {
    echo "Error checking tables: " . $e->getMessage() . "\n";
}
