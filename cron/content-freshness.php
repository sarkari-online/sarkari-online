<?php
/**
 * Sarkari.online - Content Freshness Engine
 * Scans all published articles for outdated years in exam/result contexts
 * and auto-updates them to keep content evergreen.
 * Usage: php cron/content-freshness.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

$startTime = microtime(true);
$currentYear = (int)date('Y');
$prevYear = $currentYear - 1;
$prevPrevYear = $currentYear - 2;

echo "[" . date('Y-m-d H:i:s') . "] Starting Sarkari.online Content Freshness Engine...\n";
Logger::info("Cron content-freshness started");

$articles = Database::fetchAll(
    "SELECT id, title, content, slug FROM articles WHERE status = 'published' ORDER BY published_at ASC"
);

$scanned = 0;
$updated = 0;

foreach ($articles as $article) {
    $scanned++;
    $original = $article['content'];
    $content  = $original;

    // Replace outdated years only in future-event contexts (not historical references)
    // Pattern: old year followed within 120 chars by exam-related words
    $examContext = 'exam|notification|apply|application|registration|result|cutoff|admit.card|syllabus|vacancy|recruitment|eligibility|schedule|calendar|date|deadline|form|session';

    foreach ([$prevPrevYear, $prevYear] as $oldYear) {
        // Replace year in forward-looking sentences
        $content = preg_replace_callback(
            '/\b(' . $oldYear . ')\b(?=.{0,120}(?:' . $examContext . '))/i',
            function($m) use ($currentYear) {
                return $currentYear;
            },
            $content
        );

        // Also update year in title-like headings (h2, h3 tags)
        $content = preg_replace_callback(
            '/(<h[23][^>]*>[^<]*)\b' . $oldYear . '\b([^<]*<\/h[23]>)/i',
            function($m) use ($currentYear) {
                return str_replace((string)($currentYear - 1), (string)$currentYear,
                       str_replace((string)($currentYear - 2), (string)$currentYear, $m[0]));
            },
            $content
        );
    }

    if ($content !== $original) {
        Database::update('articles', [
            'content'    => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $article['id']]);

        $updated++;
        echo "  -> [REFRESHED] Article #{$article['id']}: \"{$article['title']}\"\n";
        Logger::info("Content Freshness: Updated Article #{$article['id']} - {$article['title']}");
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Freshness check complete: {$scanned} articles scanned, {$updated} updated. ({$elapsed}s)\n";
Logger::info("Cron content-freshness finished", ['scanned' => $scanned, 'updated' => $updated, 'elapsed' => $elapsed]);
