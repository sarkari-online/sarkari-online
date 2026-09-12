<?php
declare(strict_types=1);

/**
 * Sarkari.online - Resume Autonomous 3-Slot Publishing
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\AutoCronService;

AutoCronService::setAutonomousSlotsEnabled(true);

echo "================================================================================\n";
echo "▶️ AUTONOMOUS 3-SLOT PUBLISHING RESUMED\n";
echo "================================================================================\n\n";
echo "✅ Operational Status:\n";
echo "   - Scheduled Slots (10 AM, 2 PM, 6 PM) : 🟢 ACTIVE\n";
echo "   - Gemini AI Topic Analyzer           : 🟢 ACTIVE\n";
echo "   - Real-time Trend Radar              : 🟢 ACTIVE\n";
echo "   - Manual 'Publish Now' by Admin      : 🟢 100% ACTIVE & UNLIMITED\n\n";
echo "Autonomous publishing engine is running on standard daily slot schedule.\n";
echo "================================================================================\n";
