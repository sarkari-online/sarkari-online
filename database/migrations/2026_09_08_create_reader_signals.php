<?php
/**
 * Sarkari.online - Migration: Create reader_signals table
 * 
 * Supports the Aspirant Experience & Verification Signal Module (E-E-A-T First-Hand Experience).
 * All submissions land strictly in 'pending_review' for editorial moderation.
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting migration: create_reader_signals...\n";

try {
    $db = Database::getConnection();

    $tableSql = "CREATE TABLE IF NOT EXISTS `reader_signals` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `article_id` BIGINT UNSIGNED NOT NULL,
        `signal_type` ENUM('correction', 'center_experience', 'official_circular') NOT NULL DEFAULT 'center_experience',
        `candidate_name` VARCHAR(100) NULL,
        `exam_center_city` VARCHAR(100) NULL,
        `reporting_time_observed` VARCHAR(100) NULL,
        `biometric_status` VARCHAR(100) NULL,
        `source_circular_url` VARCHAR(500) NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('pending_review', 'verified', 'dismissed') NOT NULL DEFAULT 'pending_review',
        `ip_hash` VARCHAR(64) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_reader_signals_article` (`article_id`),
        INDEX `idx_reader_signals_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($tableSql);
    echo "-> Successfully created or verified 'reader_signals' table.\n";
    Logger::info("Migration create_reader_signals executed successfully.");

} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    Logger::error("Migration create_reader_signals failed: " . $e->getMessage());
    exit(1);
}
