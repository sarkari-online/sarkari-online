<?php
/**
 * Sarkari.online - Configure 3 Daily Articles & IST Slots (10:00 AM, 02:00 PM, 06:00 PM)
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\AutoCronService;
use App\Services\PublishingService;

echo "=================================================================\n";
echo "🕒 SARKARI.ONLINE — PUBLISHING SLOTS & DAILY LIMIT CONFIGURATION\n";
echo "=================================================================\n\n";

// 1. Update database setting to 3 daily articles
Database::query("
    INSERT INTO settings (`key`, `value`) VALUES ('AUTO_PUBLISH_DAILY_LIMIT', '3')
    ON DUPLICATE KEY UPDATE `value` = '3'
");
echo "✅ Set AUTO_PUBLISH_DAILY_LIMIT = 3 in database settings.\n";

// 2. Fetch active IST schedule
$schedule = AutoCronService::getISTSlotSchedule();
echo "📅 Current IST Slot Status:\n";
echo "   - Current Time: " . $schedule['current_time'] . "\n";
echo "   - Unlocked Slots Today: " . $schedule['unlocked_slots'] . " / " . $schedule['max_daily'] . "\n";
echo "   - Next Slot: " . $schedule['next_slot_name'] . "\n";
echo "   - Wait Duration: ~" . $schedule['wait_minutes'] . " minutes\n";

// 3. Check today's autonomous slots & published AI count
$slotsState = AutoCronService::getDailySlotsState();
$completedSlots = AutoCronService::getCompletedSlotsTodayCount();
$pendingSlot = AutoCronService::getNextPendingSlot();
$pub = new PublishingService();
$todayTotal = $pub->getPublishedTodayCount();

echo "\n📊 Today's Progress:\n";
echo "   - Autonomous Scheduled Slots Completed: " . $completedSlots . " / 3\n";
echo "   - Total Published Articles Today: " . $todayTotal . " (Includes manual & scheduled)\n";

if ($schedule['unlocked_slots'] == 0) {
    echo "   - Status: ⏸️ Pre-10:00 AM window. System will start Slot 1 at 10:00 AM IST.\n";
} elseif ($pendingSlot !== null) {
    echo "   - Status: 🟢 Slot {$pendingSlot} is UNLOCKED & READY to generate/publish!\n";
} else {
    echo "   - Status: ⏳ All currently unlocked slots completed. Pacing active until " . $schedule['next_slot_name'] . ".\n";
}

echo "\n-----------------------------------------------------------------\n";
echo "🚀 Fixed Publishing Schedule Active: 10:00 AM | 02:00 PM | 06:00 PM IST\n";
echo "   (Manual publishing by admin remains 100% unlimited at any time)\n";
echo "-----------------------------------------------------------------\n";
