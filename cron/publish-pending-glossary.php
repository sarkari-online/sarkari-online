<?php
/**
 * Sarkari.online - Publish Pending Glossary Candidates Batch
 * Processes pending candidates from glossary_candidate_terms and publishes them live.
 * Usage: php cron/publish-pending-glossary.php [--limit=5]
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\GlossaryPipelineService;
use App\Database\Database;
use App\Helpers\Logger;

$limit = 5;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
}

echo "=== Sarkari.online - Publishing Pending Full Forms (Batch of {$limit}) ===\n";

$pipeline = new GlossaryPipelineService();
$candidates = GlossaryPipelineService::getPendingCandidates($limit);

if (empty($candidates)) {
    echo "No pending candidates found in queue.\n";
    exit(0);
}

echo "Found " . count($candidates) . " pending candidates. Starting generation...\n\n";

$count = 0;
foreach ($candidates as $cand) {
    $acr = $cand['acronym'];
    $hint = $cand['proposed_full_form_en'] ?? null;
    $cat = $cand['category'] ?? null;
    
    echo "Processing [{$acr}]... ";
    try {
        $res = $pipeline->generateAndPublish($acr, $hint, $cat, false);
        if (!empty($res['success'])) {
            $count++;
            $status = !empty($res['already_exists']) ? 'ALREADY LIVE' : 'PUBLISHED';
            echo "[{$status}] -> {$res['full_form_en']} ({$res['url']})\n";
        } else {
            echo "FAILED -> " . ($res['error'] ?? 'Unknown error') . "\n";
        }
    } catch (\Throwable $e) {
        echo "ERROR -> " . $e->getMessage() . "\n";
    }
    sleep(2); // pacing to respect API limits
}

echo "\n=== Completed: {$count}/" . count($candidates) . " processed successfully. ===\n";
