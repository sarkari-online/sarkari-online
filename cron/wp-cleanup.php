<?php
/**
 * One-time: Create syndication_log table + delete WordPress duplicate posts
 * Run once: docker exec sarkari_app php /var/www/html/cron/wp-cleanup.php
 */
if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Env;

// 1. Create DB table for syndication history
Database::query("CREATE TABLE IF NOT EXISTS syndication_log (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform      VARCHAR(50)  NOT NULL DEFAULT 'wordpress',
    article_id    INT UNSIGNED NOT NULL,
    platform_post_id VARCHAR(100) DEFAULT NULL,
    platform_url  TEXT         DEFAULT NULL,
    article_title VARCHAR(500) DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform_article (platform, article_id),
    INDEX idx_platform_date    (platform, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "✅ syndication_log table ready.\n";

// 2. Delete ALL WordPress posts via API (cleanup duplicates)
$token  = '';
$blogId = '257038678';

// Try .env first
$token = (string) Env::get('WORDPRESS_ACCESS_TOKEN', '');

// Fallback: read from cache JSON
if (empty($token)) {
    $cacheFile = dirname(__DIR__) . '/storage/cache/wp_credentials.json';
    if (file_exists($cacheFile)) {
        $creds = json_decode(file_get_contents($cacheFile), true) ?? [];
        $token = $creds['WORDPRESS_ACCESS_TOKEN'] ?? '';
    }
}

if (empty($token)) { echo "❌ No WP token found.\n"; exit(1); }

// Fetch all posts
$ch = curl_init("https://public-api.wordpress.com/rest/v1.1/sites/{$blogId}/posts/?number=100&status=publish");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "User-Agent: SarkariCleanup/1.0"],
    CURLOPT_TIMEOUT        => 20,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) { echo "❌ WP API error: HTTP $code\n"; exit(1); }

$posts = json_decode($res, true)['posts'] ?? [];
echo "📋 Found " . count($posts) . " published posts to delete.\n";

$deleted = 0;
foreach ($posts as $post) {
    $pid = $post['ID'];
    $ch2 = curl_init("https://public-api.wordpress.com/rest/v1.1/sites/{$blogId}/posts/{$pid}/delete");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "User-Agent: SarkariCleanup/1.0"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $r = curl_exec($ch2);
    $c = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    if ($c === 200) {
        echo "🗑️  Deleted: [{$pid}] {$post['title']}\n";
        $deleted++;
    } else {
        echo "⚠️  Failed to delete [{$pid}]: HTTP $c\n";
    }
    usleep(300000); // 0.3s between calls
}

echo "\n✅ Cleaned up {$deleted} posts. WordPress is fresh!\n";
echo "ℹ️  Now run: docker exec sarkari_app php /var/www/html/cron/wordpress-sync.php\n";
