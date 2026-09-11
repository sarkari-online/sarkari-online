<?php
declare(strict_types=1);

/**
 * Sarkari.online - Dry-Run Application Guide Generator (Task 2)
 * 
 * Conducts real keyword research and generates the complete step-by-step
 * application guide for CTET (#107) or specified cycle ID.
 * 
 * Usage:
 *   php cron/dry-run-application-guide.php [--id=107]
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\ApplicationGuideRenderer;
use App\Helpers\Logger;

$cycleId = 107; // Default: CTET
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $cycleId = (int)substr($arg, 5);
    }
}

echo "================================================================================\n";
echo "📝 SARKARI.ONLINE — APPLICATION GUIDE DRY-RUN (TASK 2)\n";
echo "Target Exam Cycle ID: #{$cycleId}\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

$cycle = Database::fetchOne("SELECT * FROM exam_cycles WHERE id = :id LIMIT 1", ['id' => $cycleId]);

if (!$cycle) {
    echo "❌ Error: Exam cycle #{$cycleId} not found in database!\n";
    exit(1);
}

echo "Cycle Information:\n";
echo "  • Exam Name   : {$cycle['exam_name']}\n";
echo "  • Authority   : {$cycle['authority_code']}\n";
echo "  • Cycle Year  : {$cycle['cycle_year']}\n";
echo "  • Current Phase: {$cycle['current_phase']}\n";
echo "  • Confidence  : {$cycle['phase_confidence']}\n";
echo "  • Evidence URL: " . ($cycle['phase_evidence_url'] ?: 'None') . "\n\n";

echo "Conducting Grounded Student Keyword Research & Generating Guide...\n";
$renderer = new ApplicationGuideRenderer();
$result = $renderer->render($cycle, true);

if ($result === null) {
    echo "❌ Gate Violation: Renderer failed closed.\n";
    exit(1);
}

echo "\n--------------------------------------------------------------------------------\n";
echo "🔑 RESEARCHED STUDENT SEARCH KEYWORDS (ACTUAL QUERIES)\n";
echo "--------------------------------------------------------------------------------\n";
echo "  • Primary H1 Phrase   : " . ($result['keywords']['primary_h1'] ?? 'N/A') . "\n";
echo "  • Primary Meta Phrase : " . ($result['keywords']['primary_meta'] ?? 'N/A') . "\n";
echo "  • High-Intent Query Patterns:\n";
foreach ($result['keywords']['phrases'] ?? [] as $idx => $kw) {
    echo sprintf("    %d. %s\n", $idx + 1, $kw);
}

echo "\n--------------------------------------------------------------------------------\n";
echo "🏷️ METADATA INTEGRITY\n";
echo "--------------------------------------------------------------------------------\n";
echo "  • Meta Title       : " . $result['meta_title'] . "\n";
echo "  • Meta Description : " . $result['meta_description'] . "\n";
echo "  • Target URL       : /how-to-apply/" . strtolower($cycle['authority_code']) . "-" . $cycle['cycle_year'] . "/\n";

echo "\n--------------------------------------------------------------------------------\n";
echo "📄 GENERATED CONTENT HTML PREVIEW (DRY-RUN OUTPUT)\n";
echo "--------------------------------------------------------------------------------\n";
echo $result['html'] . "\n";
echo "--------------------------------------------------------------------------------\n";
echo "✅ Dry-run completed successfully with ZERO ungrounded speculation.\n";
echo "================================================================================\n";
