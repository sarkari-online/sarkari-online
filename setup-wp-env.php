<?php
// Try multiple locations
$locations = [
    '/var/www/html/.env',
    '/var/www/html/storage/cache/wp_credentials.json',
];

$token   = 'TDmR9VC$kK#193!#zhxj*yFLz*(X)6G^m8kB*qnG)(nkxJNJ^1RJEQcq7K*IXl1C';
$blogId  = '257038678';
$blogUrl = 'https://sarkarionlinealert.wordpress.com';

// Check .env permissions
$envFile = '/var/www/html/.env';
echo "📄 .env path: $envFile\n";
echo "   Exists: " . (file_exists($envFile) ? 'yes' : 'no') . "\n";
echo "   Perms:  " . (file_exists($envFile) ? decoct(fileperms($envFile) & 0777) : 'N/A') . "\n";
echo "   Owner:  " . (file_exists($envFile) ? posix_getpwuid(fileowner($envFile))['name'] : 'N/A') . "\n";
echo "   Running as: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "   Writable: " . (is_writable($envFile) ? 'YES' : 'NO') . "\n\n";

// Method 1: Try writing .env via stream
$line1 = "\n\n# WordPress.com Backlink Syndication\n";
$line2 = "WORDPRESS_ACCESS_TOKEN=$token\n";
$line3 = "WORDPRESS_BLOG_ID=$blogId\n";
$line4 = "WORDPRESS_BLOG_URL=$blogUrl\n";

$fp = @fopen($envFile, 'a');
if ($fp) {
    fwrite($fp, $line1 . $line2 . $line3 . $line4);
    fclose($fp);
    echo "✅ Written to .env via fopen\n";
} else {
    echo "❌ fopen failed on .env\n";
    
    // Method 2: Write to storage/cache as JSON fallback
    $cacheDir  = '/var/www/html/storage/cache';
    $cacheFile = $cacheDir . '/wp_credentials.json';
    @mkdir($cacheDir, 0777, true);
    $json = json_encode(['WORDPRESS_ACCESS_TOKEN' => $token, 'WORDPRESS_BLOG_ID' => $blogId, 'WORDPRESS_BLOG_URL' => $blogUrl], JSON_PRETTY_PRINT);
    if (file_put_contents($cacheFile, $json) !== false) {
        echo "✅ Credentials saved to: $cacheFile\n";
    } else {
        echo "❌ Both methods failed.\n";
    }
}
