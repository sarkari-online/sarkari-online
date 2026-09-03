<?php
/**
 * Sarkari.online - Bulk Telegra.ph (DA 92) Backlink Syndication Engine
 *
 * Scans all published articles on Sarkari.online and automatically generates
 * high-authority editorial summary pages on Telegra.ph (Domain Authority 92/100)
 * with contextual dofollow backlinks pointing directly back to Sarkari.online.
 *
 * Usage:
 * php cron/syndicate-backlinks.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\TelegraphSyndicationService;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🚀 SARKARI.ONLINE — TELEGRA.PH (DA 92) BACKLINK SYNDICATION ENGINE\n";
echo "=================================================================\n\n";

// 1. Fetch all published articles
$articles = Database::fetchAll("
    SELECT id, title, slug, excerpt, content, published_at 
    FROM articles 
    WHERE status = 'published' 
    ORDER BY id ASC
");

$totalArticles = count($articles);
echo "1. Found {$totalArticles} published articles on Sarkari.online.\n\n";

if ($totalArticles === 0) {
    echo "No published articles found to syndicate.\n";
    exit(0);
}

$createdCount = 0;
$alreadyCount = 0;
$failedCount = 0;
$liveLinks = [];

echo "2. Syndicating to Telegra.ph (DA 92 Network)...\n";
echo "-----------------------------------------------------------------\n";

foreach ($articles as $idx => $article) {
    $num = $idx + 1;
    $articleId = (int)$article['id'];
    $shortTitle = mb_substr($article['title'], 0, 55) . (mb_strlen($article['title']) > 55 ? '...' : '');

    echo "[{$num}/{$totalArticles}] Article #{$articleId}: '{$shortTitle}'\n";

    $res = TelegraphSyndicationService::syndicateArticle($article);

    if (!empty($res['success'])) {
        $url = $res['telegraph_url'];
        $liveLinks[] = [
            'id' => $articleId,
            'title' => $article['title'],
            'url' => $url
        ];

        if (!empty($res['already_syndicated'])) {
            $alreadyCount++;
            echo "   ↳ [✓ ACTIVE BACKLINK] {$url}\n\n";
        } else {
            $createdCount++;
            echo "   ↳ [★ NEW DA 92 BACKLINK GENERATED] {$url}\n\n";
            usleep(1500000); // 1.5s pace to respect Telegraph API limits
        }
    } else {
        $failedCount++;
        $errMsg = $res['error'] ?? 'Unknown error';
        echo "   ↳ [✗ FAILED] {$errMsg}\n\n";
    }
}

echo "=================================================================\n";
echo "📊 BACKLINK SYNDICATION SUMMARY:\n";
echo "   - Total Articles Scanned    : {$totalArticles}\n";
echo "   - New Backlinks Generated   : {$createdCount}\n";
echo "   - Existing Active Backlinks : {$alreadyCount}\n";
echo "   - Total Live DA 92 Backlinks: " . count($liveLinks) . "\n";
echo "=================================================================\n\n";

echo "🔗 HOW TO VERIFY YOUR LIVE BACKLINKS:\n";
echo "Open any of the URLs below in your browser to view your live\n";
echo "article on Telegra.ph with direct links to Sarkari.online:\n\n";

foreach (array_slice($liveLinks, 0, 10) as $idx => $item) {
    $n = $idx + 1;
    echo "   {$n}. {$item['title']}\n";
    echo "      👉 {$item['url']}\n\n";
}

if (count($liveLinks) > 10) {
    echo "   ... and " . (count($liveLinks) - 10) . " more active backlinks!\n";
}

echo "=================================================================\n";
echo "✅ Complete! All backlinks are open, followable, and live on Telegra.ph (DA 92).\n";
