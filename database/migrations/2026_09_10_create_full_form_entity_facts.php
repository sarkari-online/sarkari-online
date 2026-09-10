<?php
/**
 * Sarkari.online - Migration: Create full_form_entity_facts table
 * 
 * Stores verified salary structures (7th CPC / PSU pay scale), career growth paths,
 * grounded FAQs, and evidence provenance for full-form directory entries.
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting migration: create_full_form_entity_facts...\n";

try {
    $db = Database::getConnection();

    $tableSql = "CREATE TABLE IF NOT EXISTS `full_form_entity_facts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `full_form_id` BIGINT UNSIGNED NOT NULL,
        `pay_level_7cpc` VARCHAR(20) NULL,
        `basic_pay_min` INT NULL,
        `basic_pay_max` INT NULL,
        `gross_salary_min` INT NULL,
        `gross_salary_max` INT NULL,
        `allowances_summary` VARCHAR(500) NULL,
        `career_growth_summary` TEXT NULL,
        `faqs_json` JSON NULL,
        `evidence_url` VARCHAR(500) NULL,
        `confidence` ENUM('VERIFIED','INFERRED','UNAVAILABLE') NOT NULL DEFAULT 'UNAVAILABLE',
        `last_verified_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_full_form` (`full_form_id`),
        CONSTRAINT `fk_ffef_full_form` FOREIGN KEY (`full_form_id`) REFERENCES `glossary_terms` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($tableSql);
    echo "-> Successfully created or verified 'full_form_entity_facts' table.\n";
    Logger::info("Migration create_full_form_entity_facts executed successfully.");

} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    Logger::error("Migration create_full_form_entity_facts failed: " . $e->getMessage());
    exit(1);
}
