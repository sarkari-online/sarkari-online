<?php
/**
 * Sarkari.online - Migration: Create Temporal Facts Table & Article Lifecycle Status
 *
 * Adds lifecycle_status ENUM to `articles` and creates `article_temporal_facts` table
 * for structured date provenance and time-sensitive milestone tracking.
 * Safe & idempotent: checks column/table existence before executing.
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting migration: create_temporal_facts...\n";

try {
    $db = Database::getConnection();

    // 1. Check if lifecycle_status column exists on articles table
    $colCheckSql = "SELECT COUNT(*) FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'articles' 
                      AND COLUMN_NAME = 'lifecycle_status'";
    $colExists = (int)$db->query($colCheckSql)->fetchColumn();

    if ($colExists === 0) {
        echo "Adding 'lifecycle_status' column to 'articles' table...\n";
        $alterSql = "ALTER TABLE `articles` 
                     ADD COLUMN `lifecycle_status` ENUM(
                         'draft', 'upcoming', 'active', 'closed', 'exam_completed', 
                         'admit_card_released', 'result_released', 'historical', 'evergreen', 'archived'
                     ) NOT NULL DEFAULT 'active' AFTER `status`,
                     ADD INDEX `idx_articles_lifecycle` (`lifecycle_status`)";
        $db->exec($alterSql);
        echo "-> Successfully added 'lifecycle_status' column with index.\n";
    } else {
        echo "-> Column 'lifecycle_status' already exists on 'articles'. Skipping ALTER.\n";
    }

    // 2. Create article_temporal_facts table
    echo "Creating 'article_temporal_facts' table if not exists...\n";
    $tableSql = "CREATE TABLE IF NOT EXISTS `article_temporal_facts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `article_id` BIGINT UNSIGNED NOT NULL,
        `fact_name` VARCHAR(64) NOT NULL,
        `fact_value` VARCHAR(255) NULL,
        `source_url` VARCHAR(500) NULL,
        `source_type` ENUM('official', 'statutory_board', 'gazette', 'secondary_wire') NOT NULL DEFAULT 'official',
        `verified_at` DATETIME NOT NULL,
        `valid_from` DATETIME NULL,
        `valid_until` DATETIME NULL,
        `timezone` VARCHAR(32) NOT NULL DEFAULT 'Asia/Kolkata',
        `confidence` ENUM('high', 'medium', 'low', 'unverified') NOT NULL DEFAULT 'high',
        `status` ENUM('verified', 'unverified', 'pending', 'expired', 'superseded') NOT NULL DEFAULT 'verified',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_temp_facts_article` (`article_id`),
        INDEX `idx_temp_facts_name` (`fact_name`),
        INDEX `idx_temp_facts_valid_until` (`valid_until`),
        INDEX `idx_temp_facts_art_name_stat` (`article_id`, `fact_name`, `status`),
        CONSTRAINT `fk_temp_facts_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($tableSql);
    echo "-> Table 'article_temporal_facts' created or confirmed.\n";

    echo "[" . date('Y-m-d H:i:s') . "] Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    Logger::critical("Migration create_temporal_facts failed: " . $e->getMessage());
    exit(1);
}
