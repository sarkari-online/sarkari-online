<?php
declare(strict_types=1);

/**
 * Sarkari.online - Dedicated Slot 2 Article Generator & Publisher
 * Unblocks circuit breaker, selects the top approved trend, forces generation,
 * records Slot 2 completion, and prints full diagnostic progress.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\PipelineService;
use App\Services\AutoCronService;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "⚡ SARKARI.ONLINE — FORCE PUBLISH SLOT 2 (NOON SLOT 2:00 PM - 5:59 PM)\n";
echo "================================================================================\n\n";

// 1. Unblock any active Gemini Circuit Breaker
Database::execute("DELETE FROM settings WHERE `key` = 'gemini_circuit_breaker_until'");
echo "1. Circuit Breaker: Cleared & Unblocked.\n";

// 2. Find Candidate Trend from Approved Queue
$trend = Database::fetchOne(
    "SELECT id, keyword, status, trend_score FROM trends 
     WHERE status = 'approved' 
     ORDER BY trend_score DESC, id DESC LIMIT 1"
);

if (!$trend) {
    echo "⚠️ No 'approved' trend found. Checking 'detected' queue for top fresh trend...\n";
    $trend = Database::fetchOne(
        "SELECT id, keyword, status, trend_score FROM trends 
         WHERE status = 'detected' 
         ORDER BY id DESC LIMIT 1"
    );
    if ($trend) {
        Database::execute("UPDATE trends SET status = 'approved', trend_score = 95 WHERE id = :id", ['id' => $trend['id']]);
        echo "   -> Auto-promoted detected trend #{$trend['id']} ('{$trend['keyword']}') to approved.\n";
    }
}

if (!$trend) {
    echo "❌ No approved or detected trends found in database.\n";
    exit(1);
}

$trendId = (int)$trend['id'];
$keyword = $trend['keyword'];
echo "2. Selected Trend for Slot 2: [#{$trendId}] '{$keyword}'\n\n";

// 3. Execute Pipeline Generation with Force = true
echo "3. Generating Article via PipelineService...\n";
ini_set('memory_limit', '512M');
set_time_limit(300);

try {
    $pipeline = new PipelineService();
    $res = $pipeline->generateFromTrend($trendId, true);

    if (!empty($res['success']) && !empty($res['article_id'])) {
        $artId = (int)$res['article_id'];
        $art = Database::fetchOne("SELECT title, slug, quality_score FROM articles WHERE id = :id LIMIT 1", ['id' => $artId]);
        
        // Record Slot 2 as officially completed for today
        AutoCronService::recordSlotCompleted(2, $artId);

        $url = 'https://sarkari.online/article/' . ($art['slug'] ?? '') . '/';
        echo "\n🎉 SUCCESS! Article #{$artId} PUBLISHED LIVE FOR SLOT 2!\n";
        echo "   - Title : {$art['title']}\n";
        echo "   - Score : {$art['quality_score']} / 100\n";
        echo "   - URL   : {$url}\n\n";
        echo "================================================================================\n";
        echo "✅ Slot 2 is now officially marked PUBLISHED on the dashboard!\n";
        echo "================================================================================\n";
    } else {
        $err = $res['error'] ?? 'Unknown generation failure';
        echo "\n❌ Generation Failed: {$err}\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "\n❌ Exception during generation: " . $e->getMessage() . "\n";
    exit(1);
}
