<?php
require_once dirname(__DIR__) . '/config.php';
use App\Database\Database;

try {
    $art = Database::fetchOne("SELECT id, title, content FROM articles WHERE slug LIKE '%wbjee%' LIMIT 1");
    if ($art) {
        $id = (int)$art['id'];
        $newContent = str_replace(
            ['December 2025', 'January 2026', 'February 2026', 'April 2026', 'May 2026', 'WBJEE 2026 Exam Schedule'],
            ['December 2026', 'January 2027', 'February 2027', 'April 2027', 'May 2027', 'WBJEE 2027 Exam Schedule'],
            $art['content']
        );
        $newTitle = str_replace('WBJEE 2026', 'WBJEE 2027', $art['title']);
        Database::update('articles', [
            'title' => $newTitle,
            'content' => $newContent
        ], 'id = :id', ['id' => $id]);
        echo "Successfully updated WBJEE article #{$id} with accurate 2027 upcoming roadmap dates.\n";
    } else {
        echo "No WBJEE article found to update.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
