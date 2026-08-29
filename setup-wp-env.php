<?php
$envFile = __DIR__ . '/.env';
$token   = 'TDmR9VC$kK#193!#zhxj*yFLz*(X)6G^m8kB*qnG)(nkxJNJ^1RJEQcq7K*IXl1C';
$blogId  = '257038678';
$blogUrl = 'https://sarkarionlinealert.wordpress.com';
$current = file_get_contents($envFile);
if (str_contains($current, 'WORDPRESS_ACCESS_TOKEN')) {
    $current = preg_replace('/WORDPRESS_ACCESS_TOKEN=.*/m', "WORDPRESS_ACCESS_TOKEN={$token}", $current);
    $current = preg_replace('/WORDPRESS_BLOG_ID=.*/m',     "WORDPRESS_BLOG_ID={$blogId}",     $current);
    $current = preg_replace('/WORDPRESS_BLOG_URL=.*/m',    "WORDPRESS_BLOG_URL={$blogUrl}",    $current);
    file_put_contents($envFile, $current);
    echo "Updated existing keys.\n";
} else {
    $addition = "\n\n# WordPress.com Backlink Syndication\nWORDPRESS_ACCESS_TOKEN={$token}\nWORDPRESS_BLOG_ID={$blogId}\nWORDPRESS_BLOG_URL={$blogUrl}\n";
    file_put_contents($envFile, $current . $addition);
    echo "Appended new keys.\n";
}
$verify = file_get_contents($envFile);
echo str_contains($verify, 'WORDPRESS_ACCESS_TOKEN') ? "VERIFIED OK\n" : "FAILED\n";
