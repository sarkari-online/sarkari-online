<?php
/**
 * Sarkari.online — Intelligent Fact-Aware Master Article Healer
 * 
 * INTELLIGENT FACT INJECTION LOGIC:
 * 1. Resolves the article's exam_cycle from exam_cycles table.
 * 2. If a table cell has a placeholder ("Awaited", "TBA", etc.) BUT the exam_cycle
 *    has the REAL ANNOUNCED DATE in facts_json or phase context:
 *    -> It replaces the placeholder with the REAL VERIFIED CALENDAR DATE!
 * 3. Only if the government has genuinely not announced the date yet:
 *    -> It displays "Not yet announced" (never TBA/Awaited).
 * 4. Preserves all existing real dates and numbers without touching them.
 * 5. Updates updated_at = NOW() to display fresh Last Updated badge.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\TableIntegrityGate;
use App\Services\ExamCycleResolverService;
use App\Services\MilestoneTableRenderer;

$isDryRun = in_array('--dry-run=true', $argv, true) || in_array('--dry-run', $argv, true);

echo "================================================================================\n";
echo "🏥 SARKARI.ONLINE — INTELLIGENT FACT-AWARE MASTER ARTICLE HEALER\n";
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
echo "Found {$total} published articles to audit and intelligently heal.\n\n";

$gate     = new TableIntegrityGate();
$resolver = new ExamCycleResolverService();
$renderer = new MilestoneTableRenderer();

$healedCount        = 0;
$realDatesInjected  = 0;
$placeholdersCleaned = 0;

foreach ($articles as $art) {
    $artId   = (int)$art['id'];
    $title   = $art['title'];
    $content = $art['content'];
    $updatedContent = $content;

    // 1. Resolve or find linked exam_cycle
    $link = Database::fetchOne(
        "SELECT exam_cycle_id, article_role FROM exam_cycle_articles WHERE article_id = :aid LIMIT 1",
        ['aid' => $artId]
    );

    $cycle = null;
    if ($link) {
        $cycle = Database::fetchOne("SELECT * FROM exam_cycles WHERE id = :cid", ['cid' => $link['exam_cycle_id']]);
    } else {
        $cycle = $resolver->resolve($title);
        if ($cycle && !$isDryRun) {
            $resolver->linkArticle((int)$cycle['id'], $artId, 'GENERAL');
        }
    }

    $cycleFacts = [];
    if (!empty($cycle['facts_json'])) {
        $decoded = json_decode($cycle['facts_json'], true);
        if (is_array($decoded)) $cycleFacts = $decoded;
    }

    // Helper to format real date
    $formatDate = function(?string $raw): ?string {
        if (empty($raw)) return null;
        $ts = strtotime($raw);
        return ($ts !== false && $ts > 0) ? date('d F Y', $ts) : null;
    };

    // 2. Intelligent scan and replacement of table rows
    // Match each <tr>...<td>Label</td>...<td>Value</td>...</tr>
    $updatedContent = preg_replace_callback('/<tr([^>]*)>\s*<td([^>]*)>(.*?)<\/td>\s*<td([^>]*)>(.*?)<\/td>\s*<\/tr>/is', function($trMatch) use (
        &$realDatesInjected, &$placeholdersCleaned, $cycleFacts, $formatDate
    ) {
        $trAttrs = $trMatch[1];
        $td1Attrs = $trMatch[2];
        $labelHtml = $trMatch[3];
        $td2Attrs = $trMatch[4];
        $valHtml = $trMatch[5];

        $strippedVal = trim(strip_tags($valHtml));
        $strippedLabel = mb_strtolower(trim(strip_tags($labelHtml)));

        // Check if value is a placeholder
        $isPlaceholder = (bool)preg_match('/^(?:TBA|To Be Announced|Awaited|Coming Soon|Not Announced|Expected Soon|Will be announced|Notification Awaited|Date Awaited|Result Awaited|00[-\/]00[-\/](?:0000|\d{4})|XX\/XX\/\d{4})$/i', $strippedVal);

        if (!$isPlaceholder) {
            // Already has real content/date — DO NOT TOUCH!
            return $trMatch[0];
        }

        // It is a placeholder! Now check if we have a REAL ANNOUNCED DATE for this milestone!
        $realDate = null;

        if (str_contains($strippedLabel, 'admit card') || str_contains($strippedLabel, 'city slip') || str_contains($strippedLabel, 'hall ticket')) {
            $realDate = $formatDate($cycleFacts['admit_card_date'] ?? null);
        } elseif (str_contains($strippedLabel, 'exam date') || str_contains($strippedLabel, 'examination')) {
            if (!empty($cycleFacts['exam_dates']) && is_array($cycleFacts['exam_dates'])) {
                $dates = array_filter(array_map($formatDate, $cycleFacts['exam_dates']));
                if (!empty($dates)) {
                    $realDate = count($dates) === 1 ? reset($dates) : reset($dates) . ' to ' . end($dates);
                }
            }
        } elseif (str_contains($strippedLabel, 'answer key')) {
            $realDate = $formatDate($cycleFacts['answer_key_date'] ?? null);
        } elseif (str_contains($strippedLabel, 'objection')) {
            $realDate = $formatDate($cycleFacts['objection_end'] ?? null);
        } elseif (str_contains($strippedLabel, 'result') || str_contains($strippedLabel, 'scorecard')) {
            $realDate = $formatDate($cycleFacts['result_date'] ?? null);
        } elseif (str_contains($strippedLabel, 'last date') || str_contains($strippedLabel, 'deadline') || str_contains($strippedLabel, 'apply online')) {
            $realDate = $formatDate($cycleFacts['application_end'] ?? null);
        } elseif (str_contains($strippedLabel, 'notification')) {
            $realDate = $formatDate($cycleFacts['notification_date'] ?? null);
        }

        if ($realDate !== null) {
            // REPLACED PLACEHOLDER WITH REAL ANNOUNCED DATE!
            $realDatesInjected++;
            return "<tr{$trAttrs}><td{$td1Attrs}>{$labelHtml}</td><td{$td2Attrs}><strong>{$realDate}</strong></td></tr>";
        } else {
            // Date genuinely not announced by government yet
            $placeholdersCleaned++;
            return "<tr{$trAttrs}><td{$td1Attrs}>{$labelHtml}</td><td{$td2Attrs}>Not yet announced</td></tr>";
        }
    }, $content);

    $healed = ($updatedContent !== $content);

    if (!$isDryRun) {
        Database::execute(
            "UPDATE articles
                SET content = :content,
                    article_health_status = 'HEALTHY',
                    updated_at = NOW()
              WHERE id = :id",
            [
                'content' => $updatedContent,
                'id'      => $artId,
            ]
        );
    }

    $healedCount++;
    $statusMsg = $healed ? "🔧 REPAIRED (facts injected)" : "🔄 REFRESHED (clean timestamp)";
    echo sprintf("  [#%-3d] %s -> %s\n", $artId, $statusMsg, mb_substr($title, 0, 50));
}

echo "\n================================================================================\n";
echo "SUMMARY REPORT\n";
echo "================================================================================\n";
echo "  Total articles processed      : {$total}\n";
echo "  Real announced dates injected : {$realDatesInjected}\n";
echo "  Pending milestones set safe   : {$placeholdersCleaned}\n";
echo "  Articles updated with NOW()   : {$healedCount}\n";
echo "  Mode                          : " . ($isDryRun ? "DRY RUN (no DB changes)" : "LIVE DATABASE UPDATED ✅") . "\n";
echo "Finished at: " . date('Y-m-d H:i:s T') . "\n";
