<?php
/**
 * Sarkari.online - Auto-repair Table Placeholders
 * Uses TableIntegrityGate to scan and auto-repair all forbidden placeholders
 * ("TBA", "Awaited", "To Be Announced") inside published article tables.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\TableIntegrityGate;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🛡️ Sarkari.online Table Integrity Auto-Repair Started...\n";

$gate = new TableIntegrityGate();
$articles = Database::fetchAll("SELECT id, title, content FROM articles WHERE status = 'published'");
echo "Scanning " . count($articles) . " published articles for table placeholder violations...\n";

$fixedCount = 0;
foreach ($articles as $art) {
    $content = $art['content'] ?? '';
    $violations = $gate->scan($content);
    if (!empty($violations)) {
        echo "  [FOUND VIOLATIONS] #{$art['id']}: {$art['title']}\n";
        foreach ($violations as $v) {
            echo "    - {$v}\n";
        }

        $repaired = $gate->repair($content);
        Database::execute("UPDATE articles SET content = :c, updated_at = NOW() WHERE id = :id", [
            'c' => $repaired,
            'id' => (int)$art['id']
        ]);

        $remaining = $gate->scan($repaired);
        if (empty($remaining)) {
            echo "    ✅ Repaired successfully (0 violations left).\n";
            $fixedCount++;
        } else {
            echo "    ⚠️ Warning: " . count($remaining) . " violations remain.\n";
        }
    }
}

echo "\n--------------------------------------------------\n";
echo "Summary: {$fixedCount} articles successfully repaired to 100% clean.\n";
echo "--------------------------------------------------\n";
Logger::info("TableIntegrityAutoRepair: {$fixedCount} articles repaired.");
