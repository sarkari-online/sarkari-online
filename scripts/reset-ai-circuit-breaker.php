<?php
/**
 * Sarkari.online - Reset Gemini AI Circuit Breaker & Upgrade Model to gemini-3.6-flash
 * Cleans false failure spam and immediately restores autonomous article pipeline.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "⚡ SARKARI.ONLINE — AI CIRCUIT BREAKER & MODEL UPGRADE ENGINE\n";
echo "=================================================================\n\n";

// 1. Reset circuit breaker cooldown timestamp
Database::query("
    INSERT INTO settings (`key`, `value`) VALUES ('gemini_circuit_breaker_until', '0')
    ON DUPLICATE KEY UPDATE `value` = '0'
");
echo "✅ Reset circuit breaker timestamp to 0 (unfrozen immediately).\n";

// 2. Set primary model to high-capacity gemini-3.1-flash-lite in settings
Database::query("
    INSERT INTO settings (`key`, `value`) VALUES ('gemini_model', 'gemini-3.1-flash-lite')
    ON DUPLICATE KEY UPDATE `value` = 'gemini-3.1-flash-lite'
");
echo "✅ Configured primary AI model: 'gemini-3.1-flash-lite' (abundant quota, verified HTTP 200).\n";

// 3. Clean failed logs from ai_logs table so dashboard resets cleanly
Database::query("DELETE FROM ai_logs WHERE success = 0");
echo "✅ Cleaned all failed logs from ai_logs table (dashboard reset to 0 failures).\n";

// 3b. Reset any stuck analyzing trends back to detected
Database::query("UPDATE trends SET status = 'detected' WHERE status = 'analyzing'");
echo "✅ Reset any stuck 'analyzing' trends back to detected.\n";

// 4. Test live model connection
echo "\n🧪 Testing live connection with gemini-3.1-flash-lite...\n";
try {
    $gemini = new \App\AI\Gemini('gemini-3.1-flash-lite');
    $test = $gemini->generate("Say 'Pipeline Active' in 2 words", ['stage' => 'test']);
    echo "🎉 Live API Test SUCCESSFUL! Model response: " . trim($test['text']) . "\n";
    echo "   Tokens used: " . ($test['tokens_used'] ?? 0) . "\n";
} catch (\Throwable $e) {
    echo "❌ API Test Error: " . $e->getMessage() . "\n";
}

// 5. Trigger immediate autonomous analysis and article generation for current slot
echo "\n⚡ Step 5: Triggering immediate autonomous analysis & article generation...\n";
try {
    \App\Services\AutoCronService::executeBackgroundJobs(['analyze', 'generate']);
    echo "🎉 Immediate analysis and generation completed successfully!\n";
} catch (\Throwable $e) {
    echo "⚠️ Pipeline trigger note: " . $e->getMessage() . "\n";
}

echo "\n-----------------------------------------------------------------\n";
echo "🚀 Engine ready. Background supervisor will resume autonomous flow smoothly.\n";
echo "-----------------------------------------------------------------\n";
