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
    echo "⚠️ Queue empty after reset. Running real-time statutory trend fetch...\n";
    $service = new \App\Services\TrendService();
    $recorded = $service->fetchAllSources(10);
    echo "   -> Ingested " . count($recorded) . " real-time trends.\n";

    $trend = Database::fetchOne(
        "SELECT id, keyword, status, trend_score FROM trends 
         WHERE status IN ('detected', 'approved') 
         ORDER BY id DESC LIMIT 1"
    );
    if ($trend) {
        Database::execute("UPDATE trends SET status = 'approved', trend_score = 98 WHERE id = :id", ['id' => $trend['id']]);
        echo "   -> Promoted fresh trend #{$trend['id']} ('{$trend['keyword']}') to approved.\n";
    }
}

if (!$trend) {
    echo "⚠️ Checking recently reset clean-slate topics to resurrect for Slot 2...\n";
    $trend = Database::fetchOne(
        "SELECT id, keyword, status, trend_score FROM trends 
         WHERE raw_payload LIKE '%Clean Slate Reset%' 
         ORDER BY id DESC LIMIT 1"
    );
    if ($trend) {
        Database::execute("UPDATE trends SET status = 'approved', trend_score = 98 WHERE id = :id", ['id' => $trend['id']]);
        echo "   -> Resurrected clean-slate trend #{$trend['id']} ('{$trend['keyword']}') for Slot 2.\n";
    }
}

if (!$trend) {
    echo "❌ No trends found to generate.\n";
    exit(1);
}

$trendId = (int)$trend['id'];
$keyword = $trend['keyword'];
echo "2. Selected Trend for Slot 2: [#{$trendId}] '{$keyword}'\n\n";

// 3. Execute Pipeline Generation with Force = true
echo "3. Generating Article via PipelineService...\n";
ini_set('memory_limit', '512M');
set_time_limit(300);

$maxAttempts = 3;
$published = false;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        Database::execute("DELETE FROM settings WHERE `key` = 'gemini_circuit_breaker_until'");
        
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
            $published = true;
            break;
        } else {
            $err = $res['error'] ?? 'Unknown generation failure';
            echo "\n⚠️ Generation Attempt {$attempt}/{$maxAttempts} failed: {$err}\n";
            if ($attempt < $maxAttempts) {
                echo "   Waiting 8s before retry...\n";
                sleep(8);
            }
        }
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        echo "\n⚠️ Attempt {$attempt}/{$maxAttempts} Exception: {$msg}\n";
        if ($attempt < $maxAttempts) {
            Database::execute("DELETE FROM settings WHERE `key` = 'gemini_circuit_breaker_until'");
            echo "   Clearing cooldown and waiting 10s before retry attempt " . ($attempt + 1) . "...\n";
            sleep(10);
        } else {
            echo "\n❌ Generation Failed after {$maxAttempts} attempts.\n";
            exit(1);
        }
    }
}

if (!$published) {
    exit(1);
}
