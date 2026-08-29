<?php
/**
 * Sarkari.online - Thumbnail WebP to PNG Converter
 * Ensures every WebP thumbnail has an equivalent PNG version for Google Blogger,
 * WhatsApp, Facebook, and RSS reader compatibility.
 */
require_once dirname(__DIR__) . '/config.php';

$thumbDir = dirname(__DIR__) . '/uploads/thumbnails';
if (!is_dir($thumbDir)) {
    die("Thumbnails directory not found.\n");
}

echo "Converting WebP thumbnails to PNG in {$thumbDir}...\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($thumbDir));
$count = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
        $webpPath = $file->getPathname();
        $pngPath  = preg_replace('/\.webp$/i', '.png', $webpPath);

        if (!file_exists($pngPath)) {
            $img = @imagecreatefromwebp($webpPath);
            if ($img) {
                imagepng($img, $pngPath, 6);
                imagedestroy($img);
                $count++;
            }
        }
    }
}

echo "Done: {$count} PNG thumbnail(s) generated.\n";
