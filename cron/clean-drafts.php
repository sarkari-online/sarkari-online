<?php
/**
 * Sarkari.online - Clean Unpublished Review Queue Drafts
 */
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

try {
    $count = Database::query("DELETE FROM articles WHERE status IN ('draft', 'pending_review')")->rowCount();
    // Also reset those trends to detected so they can be generated with new 1000+ words prompt
    Database::query("UPDATE trends SET status = 'detected' WHERE status IN ('approved', 'analyzing', 'generated')");
    echo "Success: {$count} old short drafts deleted and trends reset for fresh 1000+ words generation.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
