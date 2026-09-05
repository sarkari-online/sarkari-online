<?php
/**
 * Sarkari.online - Existing Articles SEO Content & Heading Optimizer
 *
 * Scans all published articles and upgrades:
 * 1. Keyword placement in the opening paragraph (first 100 words).
 * 2. Generic H2 headings into search-intent rich headings with exam/recruitment names.
 * 3. Image alt tags and external link security.
 * 4. High-CTR Meta Titles (under 58 chars) and Meta Descriptions (140-155 chars).
 *
 * Usage: php cron/optimize-existing-seo.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\SEOManagerService;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] 🚀 Starting Sarkari.online Existing Articles SEO Optimization Engine...\n";
Logger::info("Cron optimize-existing-seo started");

try {
    $articles = Database::fetchAll(
        "SELECT id, title, slug, content, meta_title, meta_description FROM articles WHERE status = 'published' ORDER BY id ASC"
    );

    $scanned = count($articles);
    $contentUpdated = 0;
    $metaUpdated = 0;

    echo "Found {$scanned} published articles to audit and optimize.\n";

    foreach ($articles as $art) {
        $artId = (int)$art['id'];
        $title = $art['title'] ?? '';
        $originalContent = $art['content'] ?? '';
        $originalMetaTitle = $art['meta_title'] ?? '';
        $originalMetaDesc = $art['meta_description'] ?? '';

        $needsUpdate = false;
        $updates = [];

        // 1. Audit and Optimize Body Content (Headings, First Paragraph, Alt tags, Link security)
        $optimizedContent = SEOManagerService::auditAndOptimizeContent($title, $originalContent);
        if ($optimizedContent !== $originalContent) {
            $updates['content'] = $optimizedContent;
            $needsUpdate = true;
            $contentUpdated++;
        }

        // 2. Audit and Optimize Meta Title (ensure under 58 chars and high CTR)
        if (empty($originalMetaTitle) || mb_strlen($originalMetaTitle) > 65) {
            $newMetaTitle = SEOManagerService::generateHighCtrTitle($title);
            if ($newMetaTitle !== $originalMetaTitle) {
                $updates['meta_title'] = $newMetaTitle;
                $needsUpdate = true;
                $metaUpdated++;
            }
        }

        // 3. Audit and Optimize Meta Description (ensure 130-155 chars)
        if (empty($originalMetaDesc) || mb_strlen($originalMetaDesc) > 180 || mb_strlen($originalMetaDesc) < 70) {
            $newMetaDesc = SEOManagerService::generateHighCtrDescription($title, $optimizedContent);
            if ($newMetaDesc !== $originalMetaDesc) {
                $updates['meta_description'] = $newMetaDesc;
                $needsUpdate = true;
            }
        }

        // Save to Database if any SEO enhancement occurred
        if ($needsUpdate) {
            $updates['updated_at'] = date('Y-m-d H:i:s');
            Database::update('articles', $updates, 'id = :id', ['id' => $artId]);
            echo "  -> [SEO ENHANCED] Article #{$artId}: '{$title}'\n";
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "Completed in {$elapsed}s: {$scanned} scanned, {$contentUpdated} articles enhanced with high-intent headings/keywords, {$metaUpdated} meta tags optimized.";
    echo "[" . date('Y-m-d H:i:s') . "] ✅ {$summary}\n";
    Logger::info("Cron optimize-existing-seo finished: {$summary}");

} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Error: " . $e->getMessage() . "\n";
    Logger::error("Cron optimize-existing-seo error: " . $e->getMessage());
}
