<?php
/**
 * Sarkari.online — Master Article Healer & Freshness Synchronizer
 * 
 * 1. Audits all published articles.
 * 2. Neutralizes and cleans all "TBA", "Awaited", "Coming Soon" placeholders inside <td> tags
 *    replacing them with standard "Not yet announced".
 * 3. Ensures every article is linked to its exam_cycles record in exam_cycle_articles.
 * 4. Marks article_health_status = 'HEALTHY'.
 * 5. Updates updated_at = NOW() so that the live website shows the green "Last Updated: [Date, Time] IST" badge.
 * 6. Supports --dry-run=true mode.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\TableIntegrityGate;
use App\Services\ExamCycleResolverService;

$isDryRun = in_array('--dry-run=true', $argv, true) || in_array('--dry-run', $argv, true);

echo "================================================================================\n";
echo "🏥 SARKARI.ONLINE — MASTER ARTICLE HEALER & FRESHNESS SYNCHRONIZER\n";
echo "Mode      : " . ($isDryRun ? "DRY-RUN (No database writes)" : "LIVE EXECUTION") . "\n";
echo "Started at: " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

$articles = Database::fetchAll(
    "SELECT id, title, slug, content, published_at, updated_at, article_health_status
       FROM articles
      WHERE status = 'published'
      ORDER BY id ASC"
);

$total = count($articles);
echo "Found {$total} published articles to audit and heal.\n\n";

$gate = new TableIntegrityGate();
$resolver = new ExamCycleResolverService();

$healedCount   = 0;
$linkedCount   = 0;
$cleanCount    = 0;
$violationsFixed = 0;

foreach ($articles as $art) {
    $artId = (int)$art['id'];
    $title = $art['title'];
    $content = $art['content'];
    $needsUpdate = false;

    // 1. Scan for table violations
    $violations = $gate->scan($content);
    $hadViolations = !empty($violations);

    if ($hadViolations) {
        // Clean placeholders inside <td>...</td>
        $cleanedContent = preg_replace_callback('/<td([^>]*)>(.*?)<\/td>/is', function($match) use (&$violationsFixed) {
            $attrs = $match[1];
            $inner = $match[2];
            $stripped = trim(strip_tags($inner));

            // Replace forbidden placeholders
            if (preg_match('/^(?:TBA|To Be Announced|Awaited|Coming Soon|Not Announced|Expected Soon|Will be announced|Notification Awaited|Date Awaited|Result Awaited|00[-\/]00[-\/](?:0000|\d{4})|XX\/XX\/\d{4})$/i', $stripped)) {
                $violationsFixed++;
                return "<td{$attrs}>Not yet announced</td>";
            }
            return $match[0];
        }, $content);

        if ($cleanedContent !== $content) {
            $content = $cleanedContent;
            $needsUpdate = true;
        }
    }

    // 2. Check exam_cycle linking
    $linkExists = Database::fetchOne(
        "SELECT exam_cycle_id FROM exam_cycle_articles WHERE article_id = :aid LIMIT 1",
        ['aid' => $artId]
    );

    $linkedCycleId = null;
    if (!$linkExists) {
        $cycle = $resolver->resolve($title);
        if ($cycle && !empty($cycle['id'])) {
            $linkedCycleId = (int)$cycle['id'];
            if (!$isDryRun) {
                $resolver->linkArticle($linkedCycleId, $artId, 'GENERAL');
            }
            $linkedCount++;
        }
    } else {
        $linkedCycleId = (int)$linkExists['exam_cycle_id'];
    }

    // 3. Health status check
    $currentHealth = $art['article_health_status'] ?? 'HEALTHY';
    if ($currentHealth !== 'HEALTHY') {
        $needsUpdate = true;
    }

    // Always update updated_at = NOW() to ensure live site displays fresh "Last Updated" timestamp
    $needsUpdate = true;

    if ($needsUpdate) {
        $healedCount++;
        if (!$isDryRun) {
            Database::execute(
                "UPDATE articles
                    SET content = :content,
                        article_health_status = 'HEALTHY',
                        updated_at = NOW()
                  WHERE id = :id",
                [
                    'content' => $content,
                    'id'      => $artId,
                ]
            );
        }
        $flag = $hadViolations ? "🔧 HEALED (table repaired)" : "🔄 REFRESHED (fresh timestamp)";
        echo sprintf("  [#%-3d] %s -> %s\n", $artId, $flag, mb_substr($title, 0, 55));
    } else {
        $cleanCount++;
    }
}

echo "\n================================================================================\n";
echo "SUMMARY REPORT\n";
echo "================================================================================\n";
echo "  Total articles audited       : {$total}\n";
echo "  Articles healed & refreshed  : {$healedCount}\n";
echo "  Table violations neutralized : {$violationsFixed}\n";
echo "  New exam cycles linked       : {$linkedCount}\n";
echo "  Mode                         : " . ($isDryRun ? "DRY RUN (no DB changes)" : "LIVE DATABASE UPDATED ✅") . "\n";
echo "Finished at: " . date('Y-m-d H:i:s T') . "\n";
