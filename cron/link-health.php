<?php
/**
 * Sarkari.online - Broken Link Auto-Detector & Fixer
 * Weekly scan of all outbound links in published articles.
 * Dead links are auto-replaced with Wayback Machine archives.
 * Usage: php cron/link-health.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

$startTime   = microtime(true);
$MAX_ARTICLES = 20; // Safety: max articles per run to avoid timeouts

echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Link Health Check...\n";
Logger::info("Cron link-health started");

$articles = Database::fetchAll(
    "SELECT id, title, content FROM articles WHERE status = 'published' ORDER BY RAND() LIMIT {$MAX_ARTICLES}"
);

$checkedLinks = 0;
$brokenLinks  = 0;
$fixedLinks   = 0;
$siteHost     = parse_url(SITE_URL, PHP_URL_HOST);

foreach ($articles as $article) {
    $content  = $article['content'];
    $modified = false;

    // Extract all external href URLs
    preg_match_all('/href=["\'](https?:\/\/(?!' . preg_quote($siteHost, '/') . ')[^"\'>\s]+)["\']/i', $content, $matches);
    $urls = array_unique($matches[1]);

    foreach ($urls as $url) {
        $checkedLinks++;
        $status = self_checkUrl($url);

        if ($status >= 400 || $status === 0) {
            $brokenLinks++;
            echo "  -> [BROKEN] {$url} (HTTP {$status})\n";

            // Try Wayback Machine
            $archiveUrl = self_getWaybackUrl($url);

            if ($archiveUrl) {
                $content  = str_replace('"' . $url . '"', '"' . $archiveUrl . '"', $content);
                $content  = str_replace("'" . $url . "'", "'" . $archiveUrl . "'", $content);
                $modified = true;
                $fixedLinks++;
                echo "     -> [FIXED via Wayback] {$archiveUrl}\n";
                Logger::info("Link fixed: {$url} → {$archiveUrl} in Article #{$article['id']}");
            } else {
                // Mark as broken with data attribute for manual review
                $content  = str_replace(
                    'href="' . $url . '"',
                    'href="' . $url . '" data-link-status="broken" data-checked="' . date('Y-m-d') . '"',
                    $content
                );
                $modified = true;
                Logger::warning("Broken link unresolvable: {$url} in Article #{$article['id']}");
            }
        }

        sleep(1); // Rate limit between URL checks
    }

    if ($modified) {
        Database::update('articles', ['content' => $content], 'id = :id', ['id' => $article['id']]);
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Link health check done: {$checkedLinks} links checked, {$brokenLinks} broken, {$fixedLinks} auto-fixed. ({$elapsed}s)\n";
Logger::info("Cron link-health finished", ['checked' => $checkedLinks, 'broken' => $brokenLinks, 'fixed' => $fixedLinks]);

// ── Helper Functions ───────────────────────────────────────────────────────

function self_checkUrl(string $url): int {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Sarkari.online LinkBot/1.0)'
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

function self_getWaybackUrl(string $url): ?string {
    $apiUrl = 'https://archive.org/wayback/available?url=' . urlencode($url);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Sarkari.online LinkBot/1.0'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (empty($response)) return null;

    $data = json_decode($response, true);
    return $data['archived_snapshots']['closest']['url'] ?? null;
}
