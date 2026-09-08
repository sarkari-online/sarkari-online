<?php
/**
 * Sarkari.online - Reopen and Run Scheduled Slot 3
 * Reopens Slot 3 if it was erroneously locked by a rejected article,
 * and immediately triggers autonomous generation of the next approved trend.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\SettingsService;
use App\Services\AutoCronService;
use App\Helpers\Logger;

echo "================================================================================\n";
echo "🚀 SARKARI.ONLINE: REOPEN & EXECUTE SCHEDULED SLOT 3\n";
echo "📅 Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "================================================================================\n\n";

// 1. Re-open Slot 3 for today
$val = SettingsService::get('cron_daily_slots_state', null);
if (!empty($val)) {
    $decoded = is_array($val) ? $val : json_decode($val, true);
    if (is_array($decoded)) {
        $decoded['completed_slots'] = array_values(array_diff($decoded['completed_slots'] ?? [], [3]));
        unset($decoded['slot_history'][3]);
        SettingsService::set('cron_daily_slots_state', json_encode($decoded), 'json');
        echo "✅ Successfully re-opened Slot 3 in cron_daily_slots_state.\n";
    }
}

// 2. Reset schedule state timestamp for generate so AutoCron runs immediately
try {
    $stateVal = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'cron_schedule_state' LIMIT 1");
    $state = !empty($stateVal) ? (json_decode($stateVal, true) ?: []) : [];
    $state['generate'] = 0;
    Database::query("UPDATE settings SET value = :v WHERE `key` = 'cron_schedule_state'", [':v' => json_encode($state)]);
    
    $cacheFile = dirname(__DIR__) . '/storage/cache/cron_schedule_state.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
    echo "✅ Reset generation cooldown timestamp.\n";
} catch (Throwable $e) {
    echo "⚠️ Note on schedule state: " . $e->getMessage() . "\n";
}

// 3. Trigger autonomous generation
echo "\n⚡ Executing AutoCronService::checkAndRun() for Slot 3...\n\n";
AutoCronService::checkAndRun();

echo "\n================================================================================\n";
echo "✅ EXECUTION FINISHED\n";
echo "================================================================================\n";
