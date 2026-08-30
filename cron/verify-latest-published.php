<?php
/**
 * Sarkari.online - Latest Published Article Audit & Fact Verification Inspector
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "=================================================================\n";
echo "🔍 SARKARI.ONLINE — LATEST PUBLISHED ARTICLE INTEGRITY AUDIT\n";
echo "=================================================================\n\n";

$latest = Database::fetchOne("
    SELECT id, title, slug, category_id, status, created_at, published_at, updated_at, content 
    FROM articles 
    ORDER BY id DESC 
    LIMIT 1
");

if (!$latest) {
    echo "No articles found in database.\n";
    exit;
}

echo "Latest Article in Database:\n";
echo "  ID           : #{$latest['id']}\n";
echo "  Title        : {$latest['title']}\n";
echo "  Slug         : {$latest['slug']}\n";
echo "  Status       : {$latest['status']}\n";
echo "  Published At : {$latest['published_at']}\n";
echo "  Updated At   : {$latest['updated_at']}\n\n";

// Check content integrity
$content = $latest['content'];
echo "--- FACTUAL CONTENT SCAN ---\n";

// 1. Check for statutory authority attribution
$authorities = ['NBEMS', 'NTA', 'UPSC', 'SSC', 'CBSE', 'SBI', 'IBPS', 'natboard.edu.in', 'csirnet.nta.nic.in'];
$foundAuth = [];
foreach ($authorities as $auth) {
    if (stripos($content, $auth) !== false) {
        $foundAuth[] = $auth;
    }
}
echo "Authority Citations Found: " . (!empty($foundAuth) ? implode(', ', $foundAuth) : 'None') . "\n";

// 2. Check for Shift Timings / Marking Scheme
if (stripos($content, 'marking scheme') !== false || stripos($content, '+4') !== false || stripos($content, 'negative marking') !== false) {
    echo "✓ Official Marking Scheme (+4 / -1 / Negative Marking) present.\n";
} else {
    echo "ℹ Marking scheme not detected in body.\n";
}

if (stripos($content, 'tie-breaking') !== false || stripos($content, 'tie breaking') !== false) {
    echo "✓ Official Tie-Breaking criteria present.\n";
}

// 3. Scan for any suspicious years or placeholders
if (preg_match_all('/(202[0-5])/', $content, $matches)) {
    echo "⚠️ Warning: Found outdated years: " . implode(', ', array_unique($matches[0])) . "\n";
} else {
    echo "✓ Zero outdated historical years (2020-2025) in content.\n";
}

// Extract excerpt of content
echo "\n--- FIRST 400 CHARACTERS OF CONTENT ---\n";
echo substr(strip_tags($content), 0, 400) . "...\n";
echo "=================================================================\n";
