<?php
if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once dirname(__DIR__) . '/config.php';
use App\Database\Database;
use App\Services\SettingsService;

$a = Database::fetchOne("SELECT id, title, slug, featured_image, trend_id FROM articles WHERE slug = 'aicte-doctoral-fellowship-2026-application' LIMIT 1");
if (!$a) { echo "Article not found (already deleted).\n"; } else {
    echo "Found: #" . $a['id'] . " — " . $a['slug'] . "\n";
    Database::delete('article_checks', 'article_id = :id', ['id' => $a['id']]);
    if (!empty($a['featured_image'])) {
        $p = '/var/www/html/' . ltrim($a['featured_image'], '/');
        if (file_exists($p)) { @unlink($p); echo "Thumbnail deleted.\n"; }
    }
    if (!empty($a['trend_id'])) {
        Database::query("UPDATE trends SET status = 'approved' WHERE id = :tid", ['tid' => (int)$a['trend_id']]);
        echo "Trend #" . $a['trend_id'] . " reset to approved.\n";
    }
    Database::delete('articles', 'id = :id', ['id' => $a['id']]);
    echo "DELETED Article #" . $a['id'] . "\n";
}

$state = ['date' => date('Y-m-d'), 'completed_slots' => [1], 'slot_history' => [1 => ['executed_at' => '2026-09-04 09:17:59', 'article_id' => 666]]];
SettingsService::set('cron_daily_slots_state', json_encode($state), 'json', 'Daily autonomous slot execution state');
echo "Slot state confirmed: 1/3 done. Next slot: 2:00 PM IST.\n";
