<?php
/**
 * Sarkari.online - Master Architecture & Quality Restorer Healer
 * Restores articles to their gold-standard original architecture:
 * - Checks pre-rewrite snapshots in article_migration_snapshots
 * - Positions the Statutory Milestone / Dates Table immediately under the first H2 section
 * - Positions Domain Tables (Vacancies, Fees, Shifts, Cutoffs) into their respective sections
 * - Formats FAQs cleanly into <h3>Question?</h3><p>Answer</p>
 * - Eliminates tables dumped at the bottom after the FAQ
 * - Enforces title entity keywords in all <h2> headings for SEO
 * 
 * Usage:
 *   php cron/heal-article-editorial-quality.php --id=728
 *   php cron/heal-article-editorial-quality.php --batch=10
 *   php cron/heal-article-editorial-quality.php --all
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only access permitted.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\ArticleStructuralRestorerService;
use App\Services\IntentClassifierService;

$options = getopt('', ['dry-run::', 'batch::', 'id::', 'intent::', 'all']);
$isDryRun = isset($options['dry-run']) && ($options['dry-run'] === 'true' || $options['dry-run'] === '1');
$isAll = isset($options['all']);
$batchLimit = $isAll ? 9999 : (int)($options['batch'] ?? 10);
$targetId = isset($options['id']) ? (int)$options['id'] : null;
$filterIntent = $options['intent'] ?? null;

echo "========================================================================\n";
echo "🚀 Sarkari.online: Master Architecture & Quality Restorer Healer\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Simulate Only, No DB Write)" : "LIVE PRODUCTION RESTORATION") . "\n";
echo "Scope: " . ($isAll ? "ALL PUBLISHED ARTICLES" : ($targetId ? "Single Article #{$targetId}" : "Batch of {$batchLimit}")) . "\n";
echo "========================================================================\n\n";

$sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_name, a.source_url, a.published_at,
               c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'";
$params = [];

if ($targetId) {
    $sql .= " AND a.id = :id";
    $params['id'] = $targetId;
}

$sql .= " ORDER BY a.id DESC LIMIT " . $batchLimit;

$articles = Database::fetchAll($sql, $params);
$totalArticles = count($articles);
echo "Fetched {$totalArticles} published article(s) to process.\n\n";

$restorer = new ArticleStructuralRestorerService();
$classifier = new IntentClassifierService();

$processed = 0;
$currentIndex = 0;
$stats = [
    'processed'            => 0,
    'saved'                => 0,
    'restored_snapshots'   => 0,
    'structural_repairs'   => 0,
    'verified_authority'   => 0,
    'unverified_authority' => 0,
];

foreach ($articles as $art) {
    $currentIndex++;
    $artId = (int)$art['id'];
    $title = $art['title'];
    $slug = $art['slug'];

    $intentEnum = $classifier->classify($title, $art['excerpt'] ?? '');
    $intentKey = strtoupper($intentEnum->value);

    if ($filterIntent && strtoupper($filterIntent) !== $intentKey) {
        continue;
    }

    $pct = round(($currentIndex / $totalArticles) * 100);
    echo "------------------------------------------------------------------------\n";
    echo "[{$currentIndex}/{$totalArticles}] ({$pct}%) ▶ Processing Article #{$artId}: [{$intentKey}]\n";
    echo "  Title: {$title}\n";
    echo "  Slug: https://sarkari.online/article/{$slug}/\n";

    // ─── Check exam_cycles link ──────────────────────────────────────────────
    $cycleLink = Database::fetchOne(
        "SELECT eca.exam_cycle_id, ec.id, ec.authority_code, ec.exam_name, ec.facts_json, ec.phase_evidence_url
         FROM exam_cycle_articles eca
         JOIN exam_cycles ec ON ec.id = eca.exam_cycle_id
         WHERE eca.article_id = :aid
         LIMIT 1",
        ['aid' => $artId]
    );

    $cycleFacts = [];
    $isAuthorityVerified = false;

    if ($cycleLink && !empty($cycleLink['authority_code'])) {
        $authorityName = $cycleLink['authority_code'];
        $cycleFacts = !empty($cycleLink['facts_json']) ? (json_decode($cycleLink['facts_json'], true) ?: []) : [];
        $isAuthorityVerified = true;
        $stats['verified_authority']++;
        echo "  🏛️  Authority : {$authorityName} (Verified via exam_cycle #{$cycleLink['exam_cycle_id']})\n";
    } else {
        $cleanExam = trim(preg_replace('/\s*[:\-–|].*$/', '', $title));
        $cleanExam = trim(preg_replace('/\b20[2-4]\d\b/', '', $cleanExam));
        $cleanExam = trim(preg_replace('/\s+/', ' ', $cleanExam));
        $authorityName = "the recruiting authority for {$cleanExam}";
        $isAuthorityVerified = false;
        $stats['unverified_authority']++;
        echo "  🏛️  Authority : Generic Fallback: \"{$authorityName}\"\n";
    }

    $art['source_name'] = $authorityName;

    // Restore or Heal Structure
    $result = $restorer->restoreOrHeal($art, $cycleFacts, $cycleLink ?: null);

    if (!empty($result['content'])) {
        $wordCount = str_word_count(strip_tags($result['content']));
        $tableCount = preg_match_all('/<table\b/i', $result['content']);
        $faqCount = preg_match_all('/<h3\b[^>]*>.*?<\/h3>/i', $result['content']);

        if ($result['restored_from'] === 'snapshot') {
            $stats['restored_snapshots']++;
            echo "  💾 Restored from pre-rewrite snapshot (Run ID: {$result['run_id']})\n";
        } else {
            $stats['structural_repairs']++;
            echo "  🛠️  Repaired to Gold-Standard Architecture (Milestone table at top, Domain tables in sections, Clean <h3> FAQs)\n";
        }

        echo "  📊 Architecture: Words: {$wordCount} | Tables: {$tableCount} | FAQs: {$faqCount}\n";

        if ($isDryRun) {
            echo "  [DRY-RUN] Preview:\n";
            echo "  " . mb_substr(strip_tags($result['content']), 0, 250) . "...\n\n";
        } else {
            Database::execute(
                "UPDATE articles SET content = :content, excerpt = :excerpt, source_name = :sname, source_verified = :sver, updated_at = NOW() WHERE id = :id",
                [
                    'content' => $result['content'],
                    'excerpt' => $result['excerpt'] ?? $art['excerpt'],
                    'sname'   => $authorityName,
                    'sver'    => $isAuthorityVerified ? 1 : 0,
                    'id'      => $artId
                ]
            );
            echo "  💾 Successfully saved to database!\n\n";
            $stats['saved']++;
        }
        $stats['processed']++;
    } else {
        echo "  ❌ Failed to process #{$artId}.\n\n";
    }
}

echo "========================================================================\n";
echo "📊 SARKARI.ONLINE — ARCHITECTURE RESTORATION SUMMARY\n";
echo "========================================================================\n";
echo "Total Processed        : {$stats['processed']}/{$totalArticles}\n";
echo "Restored from Snapshots: {$stats['restored_snapshots']}\n";
echo "Repaired Structurally  : {$stats['structural_repairs']}\n";
if (!$isDryRun) {
    echo "Saved to Database      : {$stats['saved']}\n";
}
echo "========================================================================\n";
