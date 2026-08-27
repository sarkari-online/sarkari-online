<?php
require_once dirname(__DIR__) . '/config.php';
use App\Database\Database;

try {
    $art = Database::fetchOne("SELECT id, title, meta_title, meta_description, excerpt, content FROM articles WHERE slug LIKE '%wbjee%' LIMIT 1");
    if ($art) {
        $id = (int)$art['id'];

        $find    = ['WBJEE 2026', 'wbjee 2026', 'December 2025', 'January 2026', 'February 2026', 'April 2026', 'May 2026'];
        $replace = ['WBJEE 2027', 'wbjee 2027', 'December 2026', 'January 2027', 'February 2027', 'April 2027', 'May 2027'];

        $newTitle       = str_replace($find, $replace, $art['title']       ?? '');
        $newMetaTitle   = str_replace($find, $replace, $art['meta_title']  ?? '');
        $newMetaDesc    = str_replace($find, $replace, $art['meta_description'] ?? '');
        $newExcerpt     = str_replace($find, $replace, $art['excerpt']     ?? '');
        $newContent     = str_replace($find, $replace, $art['content']     ?? '');

        Database::update('articles', [
            'title'            => $newTitle,
            'meta_title'       => $newMetaTitle,
            'meta_description' => $newMetaDesc,
            'excerpt'          => $newExcerpt,
            'content'          => $newContent,
            'updated_at'       => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);

        echo "SUCCESS: Article #{$id} fully updated to WBJEE 2027.\n";
        echo "  Old Title: " . $art['title'] . "\n";
        echo "  New Title: " . $newTitle . "\n";
        echo "  Old Meta : " . $art['meta_title'] . "\n";
        echo "  New Meta : " . $newMetaTitle . "\n";
    } else {
        echo "No WBJEE article found.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
