<?php
/**
 * Sarkari.online - Clean Queue Backlog Utility
 * Discards duplicate, repetitive older topics (PMKVY, NATS, OTR) from approved queue.
 *
 * Run command on server:
 * docker exec sarkari_app php /var/www/html/cron/clean-backlog.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\TrendService;

echo "=================================================================\n";
echo "🧹 SARKARI.ONLINE — APPROVED QUEUE BACKLOG CLEANER\n";
echo "=================================================================\n\n";

$beforeApproved = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "Approved topics in queue before cleanup: {$beforeApproved}\n";

$cleaned = TrendService::cleanRepetitiveBacklog();

$afterApproved = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "Cleaned repetitive topics: {$cleaned}\n";
echo "Approved topics remaining in lean queue: {$afterApproved}\n\n";

echo "=================================================================\n";
echo "✅ Queue cleanup complete. Auto-approval 5-cap is now active!\n";
echo "=================================================================\n";
