<?php
/**
 * Professional Metaphor Sanitizer
 * Replaces phrases like "kill your momentum" -> "derail your momentum"
 * and "best weapon" -> "best asset" across published articles.
 *
 * Run on VPS: docker exec -it sarkari_app php cron/sanitize-metaphors.php
 */

if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once __DIR__ . '/../config.php';
use App\Database\Database;

echo "=== Professional Metaphor Sanitizer ===\n\n";

$replacements = [
    '/kill your focus/i' => 'derail your focus',
    '/kill your momentum/i' => 'halt your momentum',
    '/kill your academic dreams/i' => 'hold back your academic dreams',
    '/kill your ([a-z0-9\s]+) dream/i' => 'shatter your $1 dream',
    '/kill your dream/i' => 'shatter your dream',
    '/kill your ([a-z]+)/i' => 'disrupt your $1',
    '/is your best weapon/i' => 'is your greatest advantage',
    '/are your rank-deciding weapons/i' => 'are your rank-deciding strengths',
    '/your rank-deciding weapon/i' => 'your rank-deciding advantage',
    '/weaponized specifically to/i' => 'designed specifically to',
];

$articles = Database::fetchAll("SELECT id, slug, content FROM articles WHERE status = 'published'");
$updatedCount = 0;

foreach ($articles as $art) {
    $content = $art['content'];
    $original = $content;

    foreach ($replacements as $pattern => $replace) {
        $content = preg_replace($pattern, $replace, $content);
    }

    if ($content !== $original) {
        Database::execute("UPDATE articles SET content = ?, updated_at = NOW() WHERE id = ?", [$content, $art['id']]);
        echo "  ✅ Cleaned metaphors in article #{$art['id']} [{$art['slug']}]\n";
        $updatedCount++;
    }
}

echo "\nCompleted: {$updatedCount} articles polished.\n";
