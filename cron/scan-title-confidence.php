<?php
/**
 * Sarkari.online - Read-Only Title-Body Confidence Conflation Scanner
 *
 * Scans all published articles to detect factual self-contradictions
 * between the Title/H1 and the body/tables.
 *
 * 100% READ-ONLY — ZERO DATABASE WRITES.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\TitleBodyConfidenceDetector;

echo "================================================================================\n";
echo "🔍 SARKARI.ONLINE TITLE-BODY CONFIDENCE CONFLATION AUDIT SCAN\n";
echo "📅 Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "🔧 Mode     : READ-ONLY SCAN (ZERO DATABASE WRITES)\n";
echo "================================================================================\n\n";

$pdo = Database::getConnection();

$stmt = $pdo->query("
    SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.architecture_audit_status, t.raw_payload
    FROM articles a
    LEFT JOIN trends t ON a.trend_id = t.id
    WHERE a.status = 'published'
    ORDER BY a.id ASC
");

$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($articles);
$flaggedCount = 0;
$cleanCount = 0;
$flaggedList = [];

foreach ($articles as $art) {
    $id = (int)$art['id'];
    $title = $art['title'] ?? '';
    $content = $art['content'] ?? '';
    $rawPayload = is_array($art['raw_payload'] ?? null)
        ? $art['raw_payload']
        : (json_decode($art['raw_payload'] ?? '', true) ?: []);

    $issues = TitleBodyConfidenceDetector::check($title, $content, $rawPayload);

    if (!empty($issues)) {
        $flaggedCount++;
        $flaggedList[] = [
            'id' => $id,
            'title' => $title,
            'slug' => $art['slug'],
            'issues' => $issues
        ];

        echo "── ⚠️ Flagged Article #{$id}: {$title}\n";
        foreach ($issues as $iss) {
            echo "   • {$iss}\n";
        }
        echo "\n";
    } else {
        $cleanCount++;
    }
}

echo "================================================================================\n";
echo "📊 SCAN COMPLETE\n";
echo "================================================================================\n";
echo "Total Articles Scanned : {$total}\n";
echo "Compliant / Clean      : {$cleanCount}\n";
echo "Confidence Mismatches  : {$flaggedCount}\n\n";

if (!empty($flaggedList)) {
    echo "📋 SUMMARY OF FLAGGED ARTICLES:\n";
    foreach ($flaggedList as $f) {
        echo "  - Article #{$f['id']}: {$f['title']}\n";
    }
    echo "\nNext Step: Review proposed truthful titles for these articles.\n";
} else {
    echo "✅ No title-body confidence conflations found across the catalog!\n";
}
echo "================================================================================\n";
