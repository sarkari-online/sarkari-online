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
echo "2. Purging repetitive DigiLocker/APAAR and non-educational junk...\n";
Database::query("
    DELETE FROM trends 
    WHERE LOWER(keyword) LIKE '%digilocker%' 
       OR LOWER(keyword) LIKE '%apaar%' 
       OR LOWER(keyword) LIKE '%abc id%' 
       OR LOWER(keyword) LIKE '%pmkvy%' 
       OR LOWER(keyword) LIKE '%nats%' 
       OR LOWER(keyword) LIKE '%otr%' 
       OR LOWER(keyword) LIKE '%aadhaar%'
       OR LOWER(keyword) LIKE '%pragati scholarship%'
       OR LOWER(keyword) LIKE '%saksham scholarship%'
       OR LOWER(keyword) LIKE '%swanath scholarship%'
       OR LOWER(keyword) LIKE '%mamata%'
");

echo "   ✓ Purged repetitive topics.\n\n";

// 3. Ingest Fresh Real-Time Trends from Official Statutory Portals & Live Education RSS Feeds
echo "3. Ingesting fresh real-time notices from statutory portals and news feeds...\n";
$trendService = new TrendService();
$newTrends = $trendService->fetchAllSources(10);
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
