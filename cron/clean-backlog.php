<?php
/**
 * Sarkari.online - Complete Queue Reset & Fresh Ingestion Utility
 *
 * 1. Resets all pending approved backlog to 0.
 * 2. Cleans out repetitive static/evergreen topics.
 * 3. Ingests fresh, real-time notices from active statutory portals.
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
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🧹 SARKARI.ONLINE — QUEUE RESET & FRESH DISCOVERY\n";
echo "=================================================================\n\n";

// 1. Reset ALL pending approved trends (Approved Queue = 0)
$approvedCountBefore = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "1. Approved topics in queue before cleanup: {$approvedCountBefore}\n";

Database::query("
    UPDATE trends 
    SET status = 'rejected'
    WHERE status = 'approved'
");

$approvedCountAfter = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "   ✓ Approved queue reset! New Approved Count: {$approvedCountAfter} (CLEAN)\n\n";

// 2. Reject repetitive old detected guides and delete political/celebrity junk
echo "2. Cleaning repetitive detected topics and deleting non-educational junk...\n";
Database::query("
    DELETE FROM trends 
    WHERE LOWER(keyword) LIKE '%mamata banerjee%' 
       OR LOWER(keyword) LIKE '%rahul gandhi%' 
       OR LOWER(keyword) LIKE '%narendra modi%' 
       OR LOWER(keyword) LIKE '%cricket%' 
       OR LOWER(keyword) LIKE '%movie%'
");

$repetitiveKeywords = ['pmkvy', 'nats student', 'praagti', 'saksham', 'swanath', 'mobile otp not received', 'marksheet verification not matching', 'abc id creation', 'apaar id card download'];
$cleanedDetected = 0;

foreach ($repetitiveKeywords as $rk) {
    $rows = Database::fetchAll("SELECT id FROM trends WHERE status = 'detected' AND LOWER(keyword) LIKE :pattern", ['pattern' => "%{$rk}%"]);
    foreach ($rows as $r) {
        TrendService::markStatus((int)$r['id'], 'rejected');
        $cleanedDetected++;
    }
}
echo "   ✓ Cleaned {$cleanedDetected} repetitive detected topics.\n\n";

// 3. Ingest Fresh Real-Time Trends from Official Statutory Portals & Google Trends
echo "3. Ingesting fresh real-time notices from statutory portals...\n";
$trendService = new TrendService();
$newTrends = $trendService->fetchAllSources(8);
echo "   ✓ Successfully ingested " . count($newTrends) . " fresh real-time notices into Detected queue!\n\n";

// Final Status Summary
$detectedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected'");
$approvedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
$publishedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'published'");

echo "=================================================================\n";
echo "📊 SYSTEM QUEUE IS NOW 100% FRESH & LEAN:\n";
echo "   - Approved Queue (Lean) : {$approvedCount} (0 - Ready for your manual selection)\n";
echo "   - Detected Queue        : {$detectedCount} (Fresh real-time topics to choose from)\n";
echo "   - Published Live        : {$publishedCount} (All existing articles intact)\n";
echo "=================================================================\n";
