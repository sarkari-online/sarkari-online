<?php
declare(strict_types=1);

/**
 * Sarkari.online - Targeted Healer for Article #722 (KVS LDCE 2026 Answer Key)
 * 
 * Replaces redundant duplicate date rows in Table 2 with a clean, professional
 * Challenge Fee Structure & Representation Guidelines table, eliminating
 * back-to-back duplicate date tables.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "🏥 SARKARI.ONLINE — HEALING ARTICLE #722 (KVS LDCE ANSWER KEY)\n";
echo "================================================================================\n\n";

$article = Database::fetchOne(
    "SELECT id, title, slug, content FROM articles WHERE id = 722 OR slug = 'kvs-ldce-2026-answer-key-status' LIMIT 1"
);

if (!$article) {
    echo "❌ Article #722 not found in database.\n";
    exit(1);
}

echo "Found Article #{$article['id']}: {$article['title']}\n";

$oldTableRegex = '/<table>\s*<thead>\s*<tr>\s*<th>Milestone<\/th>\s*<th>Details<\/th>\s*<\/tr>\s*<\/thead>\s*<tbody>.*?<\/tbody>\s*<\/table>/is';

$cleanTableHtml = <<<HTML
<div class="table-responsive"><table class="data-table">
<thead>
<tr><th>Challenge Component</th><th>Official Fee &amp; Policy Details</th></tr>
</thead>
<tbody>
<tr><td>Processing Fee per Question</td><td>As specified in the official notification per question challenged</td></tr>
<tr><td>Fee Refund Policy</td><td>100% Refundable if the challenge is accepted &amp; verified by the expert committee</td></tr>
<tr><td>Submission Mode</td><td>Online candidate login portal at kvsangathan.nic.in</td></tr>
</tbody>
</table></div>
HTML;

if (!preg_match($oldTableRegex, $article['content'])) {
    echo "ℹ️ Target duplicate table pattern not found. Content may already be healed.\n";
    exit(0);
}

$newContent = preg_replace($oldTableRegex, $cleanTableHtml, $article['content'], 1);

Database::execute(
    "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
    [
        'content' => $newContent,
        'id' => (int)$article['id']
    ]
);

echo "✅ Successfully healed Article #{$article['id']}!\n";
echo "Duplicate milestone rows replaced with clean Fee & Policy table.\n";
