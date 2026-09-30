<?php
/**
 * Sarkari.online - Safe Article Pruning & Quality Gate
 * Preserves top active national exams as 'published'.
 * Sets outdated/expired/micro articles to 'draft' so they disappear from
 * homepage, categories, and sitemap, while article.php safely 301-redirects them.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🧹 Sarkari.online Safe Article Pruner Started...\n";

// List of top active/prestigious national government exams to keep published
$activeSlugsToKeep = [
    'ssc-cgl-2026-notification-apply',
    'ssc-gd-constable-2026-admit-card',
    'ssc-mts-havaldar-2026-notification',
    'ssc-cpo-sub-inspector-2026-exam',
    'ssc-stenographer-2026-exam-pattern-syllabus',
    'ssc-je-2026-apply-online',
    'rrb-ntpc-cbt-2-admit-card-2026-city-slip-exam-date',
    'rrb-group-d-2026-notification-application',
    'rrb-technician-2026-exam-schedule',
    'rrb-junior-engineer-2026-syllabus-roadmap',
    'rpf-constable-si-2026-notification',
    'upsc-ese-2026-notification-vacancies-dates',
    'upsc-cds-2026-notification-exam-schedule',
    'upsc-nda-2026-admit-card-download',
    'upsc-epfo-2026-admit-card',
    'bpsc-72nd-cce-prelims-2026-admit-card',
    'hssc-haryana-cet-2026-apply-online',
    'ctet-2026-syllabus-exam-pattern',
    'gate-2026-exam-dates-registration',
    'ibps-po-2026-exam-pattern-syllabus',
    'sbi-clerk-junior-associate-2026-syllabus',
    'indian-army-agniveer-recruitment-2026',
    'iaf-agniveer-vayu-recruitment-2026',
    'upssssc-pet-2026-registration-extended-dates',
];

try {
    // 1. Fetch all currently published articles
    $publishedArticles = Database::fetchAll("SELECT id, slug, title, published_at FROM articles WHERE status = 'published'");
    echo "Found " . count($publishedArticles) . " currently published articles in database.\n";

    $keptCount = 0;
    $draftedCount = 0;

    foreach ($publishedArticles as $art) {
        $slug = $art['slug'];
        $id = (int)$art['id'];

        // Keep if in approved list OR if published in the last 3 days
        $isRecent = false;
        if (!empty($art['published_at'])) {
            $isRecent = (time() - strtotime($art['published_at'])) <= (3 * 86400);
        }

        if (in_array($slug, $activeSlugsToKeep, true) || $isRecent) {
            echo "  [KEEP ACTIVE] #{$id}: {$slug}\n";
            $keptCount++;
        } else {
            // Move to draft
            Database::execute("UPDATE articles SET status = 'draft', updated_at = NOW() WHERE id = :id", ['id' => $id]);
            echo "  [MOVED TO DRAFT] #{$id}: {$slug}\n";
            $draftedCount++;
        }
    }

    echo "\n--------------------------------------------------\n";
    echo "Summary:\n";
    echo "  Total Published Kept: {$keptCount}\n";
    echo "  Moved to Draft:        {$draftedCount}\n";
    echo "--------------------------------------------------\n";

    // 2. Report active glossary count
    $glossaryCount = Database::fetchColumn("SELECT COUNT(*) FROM glossary_terms");
    echo "Active Full Forms (A-Z): {$glossaryCount} terms available.\n";

    Logger::info("ArticlePruning: {$draftedCount} outdated articles drafted, {$keptCount} kept active, {$glossaryCount} full-forms ready.");
    echo "✅ Pruning completed safely. Zero articles deleted, all data preserved.\n";

} catch (\Throwable $e) {
    echo "❌ Error during pruning: " . $e->getMessage() . "\n";
    Logger::error("ArticlePruning error: " . $e->getMessage());
    exit(1);
}
