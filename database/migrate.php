<?php
/**
 * EduPulse - Database Migration & Seed Runner
 * Executes database/schema.sql and runs initial seeding.
 */

require_once dirname(__DIR__) . '/app/Helpers/Env.php';
require_once dirname(__DIR__) . '/app/Helpers/Logger.php';
require_once dirname(__DIR__) . '/app/Database/Database.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "=== EduPulse Database Migration & Setup ===\n";

try {
    $pdo = Database::getConnection();
    echo "[✓] Connected to MySQL database successfully.\n";

    // 1. Run Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("schema.sql not found at {$schemaFile}");
    }

    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo "[✓] Database schema executed successfully.\n";

    // 2. Run Seeders
    require_once __DIR__ . '/seeds/seed.php';

    echo "[✓] Migration & Seeding completed successfully!\n";
} catch (Throwable $e) {
    echo "[✗] Migration Error: " . $e->getMessage() . "\n";
    Logger::critical("Migration failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    exit(1);
}
