<?php
/**
 * EduPulse - Local Background Cron Daemon Runner
 * Emulates live server Linux Crontab on local Mac environment.
 * Executes full pipeline in sequence with safe pauses.
 */

require_once dirname(__DIR__) . '/config.php';

echo "=======================================================\n";
echo "  Sarkari.online Local Automated Cron Daemon Started   \n";
echo "  Emulating Live Linux Server Crontab Schedule         \n";
echo "=======================================================\n\n";

$iteration = 0;
while (true) {
    $iteration++;
    $time = date('Y-m-d H:i:s');
    echo "[{$time}] [Loop #{$iteration}] Checking automated pipeline...\n";

    // 1. Fetch Trends
    echo "  [Step 1/4] Running Trend Ingestion...\n";
    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/cron/fetch-trends.php'));

    // Safe pause
    sleep(2);

    // 2. Analyze Detected Trends
    echo "  [Step 2/4] Running Topic Analyzer...\n";
    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/cron/analyze-trends.php'));

    // Safe pause
    sleep(2);

    // 3. Generate Articles for Approved Trends
    echo "  [Step 3/4] Running Article Generation Pipeline...\n";
    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/cron/generate-articles.php'));

    // Safe pause
    sleep(2);

    // 4. Controlled Auto-Publishing Gatekeeper
    echo "  [Step 4/4] Running Publishing Gatekeeper...\n";
    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/cron/publish-articles.php'));

    echo "[{$time}] Pipeline cycle complete. Sleeping for 45 seconds before next cycle...\n\n";
    sleep(45);
}
