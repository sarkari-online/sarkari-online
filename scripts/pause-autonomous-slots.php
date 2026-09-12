<?php
declare(strict_types=1);

/**
 * Sarkari.online - Pause Autonomous 3-Slot Publishing
 * Enforces GSC Indexing Stabilization Mode (0 AI tokens spent, manual publishing active).
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\AutoCronService;

AutoCronService::setAutonomousSlotsEnabled(false);

echo "================================================================================\n";
echo "⏸️ AUTONOMOUS 3-SLOT PUBLISHING PAUSED (GSC STABILIZATION MODE)\n";
echo "================================================================================\n\n";
echo "✅ Operational Status:\n";
echo "   - Scheduled Slots (10 AM, 2 PM, 6 PM) : ⏸️ PAUSED (0 AI tokens will be spent)\n";
echo "   - Gemini AI Topic Analyzer           : ⏸️ PAUSED\n";
echo "   - Statutory Trends Ingestion         : 🟢 ACTIVE (keeps real-time radar updated)\n";
echo "   - Manual 'Publish Now' by Admin      : 🟢 100% ACTIVE & UNLIMITED\n\n";
echo "Google Search Console will now focus 100% on indexing your existing 724 articles.\n";
echo "To resume anytime, click '▶️ Resume Auto Slots' in the Admin Panel or run:\n";
echo "   php scripts/resume-autonomous-slots.php\n\n";
echo "================================================================================\n";
