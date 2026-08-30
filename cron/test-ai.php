<?php
/**
 * Sarkari.online - Real-Time AI Connectivity & Diagnostics Validator
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\Gemini;
use App\Database\Database;

echo "=======================================================\n";
echo "🤖 SARKARI.ONLINE — AI PIPELINE CONNECTIVITY DIAGNOSTIC\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================================\n\n";

$currentDbModel = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'gemini_model' LIMIT 1") ?: 'not set in DB';
echo "1. Database Model Configured: {$currentDbModel}\n";

$circuitBreaker = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'gemini_circuit_breaker_until' LIMIT 1");
if ($circuitBreaker && (int)$circuitBreaker > time()) {
    $left = (int)$circuitBreaker - time();
    echo "2. Circuit Breaker: ACTIVE (cooldown has {$left}s remaining)\n";
} else {
    echo "2. Circuit Breaker: CLEAR (ready for calls)\n";
}

echo "\n3. Testing live Gemini API invocation...\n";
try {
    $gemini = new Gemini();
    $res = $gemini->generate("Reply with exact words: 'Gemini Engine Connected Successfully'");
    echo "   ✅ HTTP SUCCESS!\n";
    echo "   Model:    " . $res['model'] . "\n";
    echo "   Tokens:   " . $res['tokens_used'] . "\n";
    echo "   Response: " . trim($res['text']) . "\n\n";
    echo "=======================================================\n";
    echo "🎉 AI PIPELINE IS 100% OPERATIONAL!\n";
    echo "=======================================================\n";
} catch (\Throwable $e) {
    echo "   ❌ FAILED: " . $e->getMessage() . "\n";
    echo "=======================================================\n";
}
