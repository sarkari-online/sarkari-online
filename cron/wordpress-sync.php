<?php
/**
 * Sarkari.online — WordPress.com Autonomous Syndication Cron
 * Publishes 2 SEO-optimized companion articles per day to WordPress.com (DA 95)
 * with canonical backlinks to Sarkari.online.
 *
 * Schedule: Run once daily via server cron
 *   0 10 * * * docker exec sarkari_app php /var/www/html/cron/wordpress-sync.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\WordPressService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 WordPress.com Syndication Pipeline starting...\n";

// ── Step 1: DB sanity check ─────────────────────────────────────────
try {
    $count = \App\Database\Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");
    echo "✅ DB connected — {$count} published articles available\n";
} catch (\Throwable $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Step 3: Run syndication ─────────────────────────────────────────
try {
    $service = new WordPressService();
    $result  = $service->syndicateLatest(2); // max 2 posts/day

    if ($result['status'] === 'quota_reached') {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️  Daily WordPress quota (2/day) already completed.\n";
        exit(0);
    }

    $count = $result['syndicated_count'] ?? 0;

    if ($count === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️  No new articles pending WordPress syndication.\n";
        exit(0);
    }

    echo "[" . date('Y-m-d H:i:s') . "] ✅ Published {$count} article(s) to WordPress.com!\n";
    foreach ($result['items'] as $item) {
        echo "   🔗 WordPress : {$item['wordpress_url']}\n";
        echo "   🎯 Backlink  : {$item['sarkari_url']}\n";
        echo "   📝 Title     : {$item['title']}\n\n";
    }

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    Logger::error("WordPressSync failed: " . $e->getMessage());
    exit(1);
}
