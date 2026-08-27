<?php
/**
 * Sarkari.online - Autonomous High-DA Backlink Syndication Cron
 * Generates 2 companion articles daily and auto-publishes to Dev.to (DA 90+)
 * with contextual backlinks and canonical URLs pointing to Sarkari.online.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\BacklinkService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Starting Autonomous Backlink Syndicator...\n";
Logger::info("BacklinkSyndicator: Starting daily syndication cycle");

try {
    $service = new BacklinkService();
    $result = $service->syndicateLatest(2);

    if ($result['status'] === 'quota_reached') {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️ Daily backlink quota (2/day) already completed.\n";
    } else {
        $count = $result['syndicated_count'] ?? 0;
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Successfully published {$count} backlink articles to Dev.to!\n";
        foreach ($result['items'] as $item) {
            echo "   🔗 Dev.to: {$item['devto_url']}\n";
            echo "      Target: {$item['sarkari_url']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Error: " . $e->getMessage() . "\n";
    Logger::error("BacklinkSyndicator error: " . $e->getMessage());
}
