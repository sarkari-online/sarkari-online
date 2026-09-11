<?php
declare(strict_types=1);

/**
 * Migration: Create authority_phase_gap_stats table (Task 3 Infra)
 * 
 * Stores statistical transition gap ranges (min_days, max_days, sample_size)
 * strictly derived from historical closed cycles of statutory authorities.
 * 
 * Safe to re-run: uses IF NOT EXISTS guard.
 * Zero Gemini calls — pure statistical foundation.
 * 
 * Run: php database/migrations/create_authority_phase_gap_stats_table.php
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;

echo "=== Migration: create_authority_phase_gap_stats_table ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

Database::execute("
    CREATE TABLE IF NOT EXISTS authority_phase_gap_stats (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        authority_code VARCHAR(50) NOT NULL,
        from_phase VARCHAR(50) NOT NULL,
        to_phase VARCHAR(50) NOT NULL,
        min_days_observed INT NULL,
        max_days_observed INT NULL,
        sample_size INT NOT NULL DEFAULT 0,
        evidence_cycles TEXT NULL COMMENT 'comma-separated cycle_identifiers or IDs used as evidence',
        last_calculated_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_gap (authority_code, from_phase, to_phase),
        INDEX idx_authority (authority_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Historical phase gap statistics across closed cycles per authority.';
");

echo "✅ Table 'authority_phase_gap_stats' created / verified successfully.\n";
