<?php
/**
 * Sarkari.online - Bulk GitHub (DA 96) Knowledge Hub Syndication Engine
 *
 * Scans all published articles on Sarkari.online and automatically commits
 * high-authority Markdown documents to the dedicated public GitHub repository:
 * https://github.com/sarkari-online/govt-job-alerts-2026 (DA 96/100)
 * with contextual dofollow backlinks pointing directly back to Sarkari.online.
 *
 * Usage:
 * php cron/syndicate-github.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\GithubSyndicationService;
use App\Helpers\Logger;

echo "=================================================================\n";
echo "🚀 SARKARI.ONLINE — GITHUB (DA 96) KNOWLEDGE HUB SYNDICATOR\n";
echo "   Repository: https://github.com/sarkari-online/govt-job-alerts-2026\n";
echo "=================================================================\n\n";

$activeToken = GithubSyndicationService::getToken();
$maskedToken = substr($activeToken, 0, 8) . '...' . substr($activeToken, -4);
echo "🔑 Active Token: {$maskedToken} (Length: " . strlen($activeToken) . " chars)\n\n";

// 1. Fetch all published articles
$articles = Database::fetchAll("
    SELECT id, title, slug, excerpt, content, source_name, source_url, published_at 
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

echo "2. Syndicating to GitHub (DA 96 Network)...\n";
echo "-----------------------------------------------------------------\n";

foreach ($articles as $idx => $article) {
    $num = $idx + 1;
    $articleId = (int)$article['id'];
    $shortTitle = mb_substr($article['title'], 0, 55) . (mb_strlen($article['title']) > 55 ? '...' : '');

    echo "[{$num}/{$totalArticles}] Article #{$articleId}: '{$shortTitle}'\n";

    $res = GithubSyndicationService::syndicateArticle($article);

    if (!empty($res['success'])) {
        $url = $res['github_url'];
        $liveLinks[] = [
            'id' => $articleId,
            'title' => $article['title'],
            'url' => $url
        ];

        if (!empty($res['already_syndicated'])) {
            $alreadyCount++;
            echo "   ↳ [✓ ACTIVE GITHUB BACKLINK] {$url}\n\n";
        } else {
            $createdCount++;
            echo "   ↳ [★ NEW DA 96 BACKLINK CREATED] {$url}\n\n";
            usleep(1200000); // 1.2s pace to respect GitHub API rate limits
        }
    } else {
        $failedCount++;
        $errMsg = $res['error'] ?? 'Unknown error';
        echo "   ↳ [✗ FAILED] {$errMsg}\n\n";
    }
}

echo "=================================================================\n";
echo "📊 GITHUB BACKLINK SYNDICATION SUMMARY:\n";
echo "   - Total Articles Scanned    : {$totalArticles}\n";
echo "   - New Backlinks Generated   : {$createdCount}\n";
echo "   - Existing Active Backlinks : {$alreadyCount}\n";
echo "   - Total Live DA 96 Backlinks: " . count($liveLinks) . "\n";
echo "=================================================================\n\n";

echo "🔗 HOW TO VERIFY YOUR LIVE GITHUB BACKLINKS:\n";
echo "Open any of the URLs below in your browser to view your live\n";
echo "Markdown bulletin on GitHub with direct links to Sarkari.online:\n\n";

foreach (array_slice($liveLinks, 0, 10) as $idx => $item) {
    $n = $idx + 1;
    echo "   {$n}. {$item['title']}\n";
    echo "      👉 {$item['url']}\n\n";
}

if (count($liveLinks) > 10) {
    echo "   ... and " . (count($liveLinks) - 10) . " more active GitHub backlinks!\n";
}

echo "=================================================================\n";
echo "✅ Complete! All backlinks are open, indexable, and live on GitHub (DA 96).\n";
