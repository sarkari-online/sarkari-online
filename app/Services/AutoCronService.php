<?php
/**
 * Sarkari.online - Autonomous Self-Healing Cron Runner
 * Ensures content generation, trend analysis, and publishing run 24/7 autonomously
 * without needing any manual terminal commands or external cron setups.
 */

namespace App\Services;

use App\Helpers\Logger;
use Throwable;

class AutoCronService {

    private const SCHEDULE = [
        'fetch-trends.php'      => 900,  // 15 minutes
        'analyze-trends.php'    => 1200, // 20 minutes
        'generate-articles.php' => 1800, // 30 minutes
        'publish-articles.php'  => 2700, // 45 minutes
        'content-freshness.php' => 86400, // Daily (24h)
        'seo-audit.php'         => 86400, // Daily (24h)
    ];

    /**
     * Check if any cron jobs are due and spawn them asynchronously in background
     */
    public static function checkAndRun(): void {
        try {
            $lockDir = dirname(__DIR__, 2) . '/storage/cache';
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0775, true);
            }

            $stateFile = $lockDir . '/cron_schedule_state.json';
            $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?: []) : [];

            $now = time();
            $cronDir = dirname(__DIR__, 2) . '/cron';
            $phpBin = PHP_BINARY ?: 'php';

            foreach (self::SCHEDULE as $script => $intervalSeconds) {
                $lastRun = $state[$script] ?? 0;

                if (($now - $lastRun) >= $intervalSeconds) {
                    $scriptPath = $cronDir . '/' . $script;
                    if (!file_exists($scriptPath)) {
                        continue;
                    }

                    // Update state timestamp immediately to prevent race condition
                    $state[$script] = $now;
                    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

                    // Launch asynchronously in background (non-blocking)
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        @pclose(@popen("start /B {$phpBin} \"{$scriptPath}\"", "r"));
                    } else {
                        @exec("{$phpBin} \"{$scriptPath}\" > /dev/null 2>&1 &");
                    }

                    Logger::info("AutoCron launched background job: {$script}");
                }
            }
        } catch (Throwable $e) {
            // Silently ignore to avoid impacting web response
        }
    }
}
