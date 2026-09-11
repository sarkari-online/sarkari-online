<?php
/**
 * Sarkari.online - Rebuild GitHub Pages Landing Hub (DA 96)
 *
 * Fetches all published articles, generates the dynamic index.html
 * with live search, tags, and timestamps, and commits to main branch.
 *
 * Usage:
 * php cron/rebuild-github-landing.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\GithubSyndicationService;

echo "=================================================================\n";
echo "🚀 SARKARI.ONLINE — GITHUB PAGES LANDING HUB REBUILDER\n";
echo "   Target: https://sarkari-online.github.io/govt-job-alerts-2026/\n";
echo "=================================================================\n\n";

$res = GithubSyndicationService::rebuildLandingPage();

if (!empty($res['success'])) {
    echo "✅ SUCCESS: Landing page updated!\n";
    echo "   - Curated Bulletins: " . ($res['total_notices'] ?? 0) . "\n";
    echo "   - Live URL: " . ($res['landing_url'] ?? '') . "\n";
    echo "   - Commit URL: " . ($res['commit_url'] ?? '') . "\n";
} else {
    echo "❌ FAILED: " . ($res['error'] ?? 'Unknown error') . "\n";
}
