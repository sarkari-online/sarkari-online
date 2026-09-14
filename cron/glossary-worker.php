<?php
/**
 * Sarkari.online - Autonomous Indian Government Full Forms Scheduler Worker
 * Runs every 5 minutes (or via master worker daemon).
 * Automatically publishes verified terms during the 5 designated daily slots:
 * [09:30, 13:00, 16:30, 19:30, 22:00 IST]
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only access permitted.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\GlossaryPipelineService;
use App\Helpers\Logger;

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] 📚 Checking Glossary Autonomous 5-Slot Publishing Schedule...\n";

try {
    $pipeline = new GlossaryPipelineService();
    $state = GlossaryPipelineService::getSchedulerState();
    
    $isEnabled = !empty($state['enabled']);
    $publishedTodayCount = count($state['published_today'] ?? []);
    $dailyLimit = (int)($state['daily_limit'] ?? 5);
    $dueSlot = GlossaryPipelineService::getDueSlot($state);

    echo "  Status: " . ($isEnabled ? "ACTIVE" : "PAUSED") . "\n";
    echo "  Today's Published: {$publishedTodayCount} / {$dailyLimit}\n";
    echo "  Slots: " . implode(', ', $state['slots'] ?? []) . "\n";

    if (!$isEnabled) {
        echo "  ℹ️ Scheduler is paused in admin. Skipping.\n";
        exit(0);
    }

    if ($dueSlot) {
        echo "  ⚡ Slot {$dueSlot} IST is DUE! Triggering autonomous publication...\n";
        $result = $pipeline->runScheduledSlotIfDue();
        if ($result && !empty($result['success'])) {
            echo "  ✅ Successfully published: {$result['acronym']} ({$result['full_form_en']})\n";
            echo "     URL: {$result['url']}\n";
        } else {
            echo "  ⚠️ Slot check completed with notice: " . json_encode($result) . "\n";
        }
    } else {
        echo "  ℹ️ No slot currently due. Next slots pending: " . implode(', ', array_diff($state['slots'] ?? [], $state['executed_slots'] ?? [])) . "\n";
    }

} catch (\Throwable $e) {
    echo "❌ Error in glossary worker: " . $e->getMessage() . "\n";
    Logger::error("GlossaryWorker error: " . $e->getMessage());
    exit(1);
}
