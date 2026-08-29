<?php
/**
 * Sarkari.online - Autonomous Blogger Syndication Cron
 * Completely isolated from main publishing pipeline.
 * Publishes max 1 high-DA companion post with DoFollow backlink.
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\BloggerService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Starting Blogger Syndication Pipeline...\n";

try {
    $force = in_array('--force', $argv ?? []);
    $service = new BloggerService();
    $results = $service->syndicateLatest(1, $force);

    if (empty($results)) {
        echo "No new articles pending syndication to Blogger.\n";
    } else {
        foreach ($results as $res) {
            echo "✅ Published to Blogger: {$res['title']}\n";
            echo "   URL: {$res['blogger_url']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    Logger::error("Blogger sync failed: " . $e->getMessage());
}
