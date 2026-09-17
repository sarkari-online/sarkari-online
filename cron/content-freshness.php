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
    "SELECT id, title, excerpt, content, slug, published_at FROM articles WHERE status = 'published' ORDER BY published_at ASC"
);

$scanned = 0;
$updated = 0;
$todayDateStr = date('Y-m-d');

foreach ($articles as $article) {
    $scanned++;
    $originalContent = $article['content'];
    $originalTitle   = $article['title'];
    $originalExcerpt = $article['excerpt'] ?? '';

    $content = $originalContent;
    $title   = $originalTitle;
    $excerpt = $originalExcerpt;
    $articleUpdated = false;

    // --------------------------------------------------------------------
    // 1. Year Freshness Engine (2024/2025 -> 2026 in forward-looking contexts)
    // --------------------------------------------------------------------
    $examContext = 'exam|notification|apply|application|registration|result|cutoff|admit.card|syllabus|vacancy|recruitment|eligibility|schedule|calendar|date|deadline|form|session';

    foreach ([$prevPrevYear, $prevYear] as $oldYear) {
        $content = preg_replace_callback(
            '/\b(' . $oldYear . ')\b(?=.{0,120}(?:' . $examContext . '))/i',
            function($m) use ($currentYear) {
                return $currentYear;
            },
            $content
        );

        $content = preg_replace_callback(
            '/(<h[23][^>]*>[^<]*)\b' . $oldYear . '\b([^<]*<\/h[23]>)/i',
            function($m) use ($currentYear) {
                return str_replace((string)($currentYear - 1), (string)$currentYear,
                       str_replace((string)($currentYear - 2), (string)$currentYear, $m[0]));
            },
            $content
        );
    }

    if ($content !== $originalContent) {
        $articleUpdated = true;
    }

    // --------------------------------------------------------------------
    // 2. Deadline & Transient Relative Term Transition Engine
    // If published_at was before today, terms like "Last Date Today", "Closing Today"
    // are factually expired and MUST transition to "Application Closed"!
    // --------------------------------------------------------------------
    $publishedDate = date('Y-m-d', strtotime($article['published_at'] ?? 'now'));
    $isPastPublication = ($publishedDate < $todayDateStr);

    if ($isPastPublication) {
        // A. Check and fix Title
        if (preg_match('/\b(Last Date Today|Closing Today|Ends Today|Last Day Today|Today Last Date)\b/i', $title)) {
            $title = preg_replace('/:\s*(?:Last Date Today|Closing Today|Ends Today|Last Day Today)[,:]?\s*/i', ': Application Closed: ', $title);
            $title = preg_replace('/\b(Last Date Today|Closing Today|Ends Today|Last Day Today)\b/i', 'Application Closed', $title);
            $articleUpdated = true;
        }

        // B. Check and fix Excerpt
        if (preg_match('/\b(is today|ends today|closing today|final day today|deadline is today)\b/i', $excerpt)) {
            $excerpt = preg_replace('/\b(?:final application deadline is today|last date is today|deadline is today)\b/i', 'online application process has closed', $excerpt);
            $excerpt = preg_replace('/\b(?:closing today|ends today)\b/i', 'has concluded', $excerpt);
            $articleUpdated = true;
        }

        // C. Check and fix Content Body
        if (preg_match('/(?:Final Application Day|Last Date Today|deadline is today|failing to complete the application process today)/i', $content)) {
            $content = preg_replace('/<h2([^>]*)>.*?(?:Final Application Day|Last Date Today).*?<\/h2>/i', '<h2$1>Online Application Window Concluded</h2>', $content);
            $content = preg_replace('/failing to complete the application process today[^.<]*/i', 'the online application window on the official portal is now officially closed. Registered candidates are preparing for the upcoming examination schedule', $content);
            $content = preg_replace('/deadline is today for \d+ vacancies/i', 'application process concluded for the announced vacancies', $content);
            $articleUpdated = true;
        }
    }

    // --------------------------------------------------------------------
    // 3. Absolute Calendar Date Expiry Engine
    // Extracts exact dates like "Apply by Sept 15", "Last Date: 12 August 2026"
    // and checks if date has passed relative to today.
    // --------------------------------------------------------------------
    // A. Check Title for "Apply by [Month] [Day]" or "Apply by [Day] [Month]"
    if (preg_match('/(?:Apply by|Last Date[:\s]+)\s*([0-9]{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|October|Nov|Dec)[a-z]*|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|October|Nov|Dec)[a-z]*\s+[0-9]{1,2})(?:[,\s]+(202[0-9]))?/i', $title, $dm)) {
        $extractedDateStr = trim($dm[1]);
        $extractedYear = !empty($dm[2]) ? $dm[2] : date('Y');
        $fullParsedDate = strtotime($extractedDateStr . ' ' . $extractedYear);

        if ($fullParsedDate !== false && $fullParsedDate < strtotime('today')) {
            // Date is in the past! Transition title
            $title = preg_replace('/:\s*Apply by\s+.*$/i', ' (Application Window Closed)', $title);
            $title = preg_replace('/Apply by\s+[0-9a-zA-Z,\s]+/i', 'Application Window Closed', $title);
            $articleUpdated = true;

            // Prepend Closed Warning Banner to content if not already present
            if (strpos($content, 'status-closed') === false) {
                $closedBanner = '<div class="alert-box status-closed" style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin-bottom:16px;border-radius:4px;">
<strong>⚠️ Notice:</strong> The registration / application deadline for this notification has ended. Please check the official portal for subsequent phases or upcoming exam cycle updates.
</div>';
                $content = preg_replace('/(<(?:p|h2)[^>]*>)/i', $closedBanner . '$1', $content, 1);
            }
        }
    }

    if ($articleUpdated) {
        Database::update('articles', [
            'title'      => $title,
            'excerpt'    => $excerpt,
            'content'    => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $article['id']]);

        $updated++;
        echo "  -> [REFRESHED / TRANSITIONED] Article #{$article['id']}: \"{$title}\"\n";
        Logger::info("Content Freshness: Transitioned Article #{$article['id']} - {$title}");
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Freshness check complete: {$scanned} articles scanned, {$updated} updated. ({$elapsed}s)\n";
Logger::info("Cron content-freshness finished", ['scanned' => $scanned, 'updated' => $updated, 'elapsed' => $elapsed]);
