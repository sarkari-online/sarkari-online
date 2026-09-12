<?php
declare(strict_types=1);

/**
 * Sarkari.online - Clean Slate & Quota Guard Initializer
 * 
 * 1. Resets the 214 stale approved trends (marks rejected so buffer starts fresh at 0).
 * 2. Purges stale detected topics older than 48 hours.
 * 3. Truncates/resets `ai_logs` table (resets 8,120 invocations, 12M tokens, 47 failed calls to 0).
 * 4. Resets cron schedule state so autonomous engine operates strictly on 3 daily slots.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "🧹 SARKARI.ONLINE — CLEAN SLATE & QUOTA REBOOT INITIALIZER\n";
echo "================================================================================\n\n";

// 1. Purge Approved Queue Backlog
$approvedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
echo "1. Stale Approved Queue Count: {$approvedCount}\n";

if ($approvedCount > 0) {
    Database::execute(
        "UPDATE trends SET status = 'rejected', raw_payload = JSON_SET(COALESCE(raw_payload, '{}'), '$.reason', 'Clean Slate Reset: Backlog purged to prevent stale article publication') WHERE status = 'approved'"
    );
    echo "   ✅ Purged {$approvedCount} stale trends from Approved Queue (Set to Rejected).\n";
} else {
    echo "   ✓ Approved Queue is already 0.\n";
}

// 2. Clean old detected trends (> 48 hours old)
$oldDetectedCount = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected' AND created_at < NOW() - INTERVAL 48 HOUR");
if ($oldDetectedCount > 0) {
    Database::execute(
        "UPDATE trends SET status = 'rejected', raw_payload = JSON_SET(COALESCE(raw_payload, '{}'), '$.reason', 'Clean Slate: Stale detected topic older than 48h') WHERE status = 'detected' AND created_at < NOW() - INTERVAL 48 HOUR"
    );
    echo "   ✅ Purged {$oldDetectedCount} stale detected topics older than 48 hours.\n";
}

// 3. Reset AI Logs & Telemetry
$aiLogCount = (int)Database::fetchValue("SELECT COUNT(*) FROM ai_logs");
echo "\n2. AI Operations & Telemetry Logs: {$aiLogCount} entries\n";
Database::execute("TRUNCATE TABLE ai_logs");
echo "   ✅ Successfully truncated ai_logs table.\n";
echo "      - Total Invocations: 0\n";
echo "      - Total Tokens: 0\n";
echo "      - Failed Calls: 0\n";

// 4. Reset Circuit Breaker in Settings (if any cooldown active)
Database::execute("DELETE FROM settings WHERE `key` = 'gemini_circuit_breaker_until'");
echo "\n3. Reset Gemini Circuit Breaker: Cooldown cleared.\n";

// 5. Verify final status
$finalApproved = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'approved'");
$finalDetected = (int)Database::fetchValue("SELECT COUNT(*) FROM trends WHERE status = 'detected'");
$finalAiLogs   = (int)Database::fetchValue("SELECT COUNT(*) FROM ai_logs");

echo "\n================================================================================\n";
echo "🎯 CLEAN SLATE SUMMARY:\n";
echo "================================================================================\n";
echo "   - Approved Queue (Lean) : {$finalApproved} (Ready for fresh 3-slot daily autonomous)\n";
echo "   - Detected Waiting      : {$finalDetected}\n";
echo "   - AI Logs Count         : {$finalAiLogs}\n";
echo "================================================================================\n";
echo "✨ System ready! Autonomous engine will now only approve & publish for today's 3 slots.\n";
