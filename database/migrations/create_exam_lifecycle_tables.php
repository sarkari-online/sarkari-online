<?php
/**
 * Migration: Phase A — Exam Lifecycle Foundation Tables
 *
 * Creates:
 *   1. exam_cycles        — one row per concrete recruitment/exam cycle (the new stateful entity)
 *   2. exam_cycle_articles — M:N join between exam_cycles and articles
 *   3. ALTER articles     — adds article_health_status column
 *
 * Safe to re-run: all DDL uses IF NOT EXISTS / IF EXISTS guards.
 * Does NOT touch article content, pipeline logic, or any existing data.
 *
 * Run: php /var/www/html/database/migrations/create_exam_lifecycle_tables.php
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;

echo "=== Migration: create_exam_lifecycle_tables ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// ─── 1. exam_cycles ──────────────────────────────────────────────────────────
echo "Step 1/3: Creating exam_cycles table...\n";

Database::execute("
    CREATE TABLE IF NOT EXISTS exam_cycles (
        id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        authority_code         VARCHAR(50)  NOT NULL,
        exam_name              VARCHAR(255) NOT NULL,
        cycle_year             YEAR         NOT NULL,
        cycle_identifier       VARCHAR(100) NULL COMMENT 'e.g. CEN-09/2025, Phase-VIII — disambiguates same-year multi-cycle exams',

        current_phase          ENUM(
            'ANNUAL_CALENDAR_ONLY',
            'NOTIFICATION_RELEASED',
            'APPLICATION_OPEN',
            'APPLICATION_CORRECTION',
            'APPLICATION_CLOSED',
            'ADMIT_CARD_AWAITED',
            'ADMIT_CARD_RELEASED',
            'EXAM_SCHEDULED',
            'EXAM_CONDUCTED',
            'ANSWER_KEY_OBJECTION',
            'FINAL_KEY_RELEASED',
            'RESULT_DECLARED',
            'PET_DV_STAGE',
            'FINAL_SELECTION',
            'CYCLE_CLOSED'
        ) NOT NULL DEFAULT 'ANNUAL_CALENDAR_ONLY',

        phase_confidence       ENUM('VERIFIED','INFERRED','STALE') NOT NULL DEFAULT 'INFERRED',
        phase_evidence_url     VARCHAR(500) NULL  COMMENT 'Official URL that confirmed current phase',
        phase_detected_at      DATETIME     NULL,
        next_expected_transition DATE        NULL  COMMENT 'Hint for when healer should re-check this cycle',

        facts_json             JSON         NULL   COMMENT 'Verified facts: vacancies, dates, fees etc. — never TBA strings',
        last_verified_at       DATETIME     NULL,

        created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY uniq_cycle  (authority_code, exam_name, cycle_year, cycle_identifier),
        INDEX idx_authority    (authority_code),
        INDEX idx_phase        (current_phase),
        INDEX idx_confidence   (phase_confidence),
        INDEX idx_next_transition (next_expected_transition)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='One row per concrete recruitment/exam cycle. The stateful entity hub.';
");

echo "  ✅ exam_cycles table created / verified.\n";

// ─── 2. exam_cycle_articles ───────────────────────────────────────────────────
echo "Step 2/3: Creating exam_cycle_articles join table...\n";

Database::execute("
    CREATE TABLE IF NOT EXISTS exam_cycle_articles (
        exam_cycle_id  BIGINT UNSIGNED NOT NULL,
        article_id     BIGINT UNSIGNED NOT NULL,
        article_role   ENUM(
            'NOTIFICATION',
            'ADMIT_CARD',
            'ANSWER_KEY',
            'RESULT',
            'SYLLABUS',
            'CUTOFF',
            'GENERAL'
        ) NOT NULL DEFAULT 'GENERAL',
        linked_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY  (exam_cycle_id, article_id),
        INDEX idx_article_id (article_id),
        INDEX idx_role       (article_role),

        CONSTRAINT fk_eca_cycle
            FOREIGN KEY (exam_cycle_id) REFERENCES exam_cycles(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Maps exam_cycles <-> articles with role context.';
");

echo "  ✅ exam_cycle_articles table created / verified.\n";

// ─── 3. ALTER articles — add article_health_status ───────────────────────────
echo "Step 3/3: Adding article_health_status column to articles...\n";

// Check if column already exists before ALTER (idempotent guard)
$col = Database::fetchOne("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'articles'
      AND COLUMN_NAME  = 'article_health_status'
");

if ($col) {
    echo "  ⏭  article_health_status column already exists — skipping ALTER.\n";
} else {
    Database::execute("
        ALTER TABLE articles
            ADD COLUMN article_health_status
                ENUM('HEALTHY','NEEDS_REVIEW','ESCALATED','HEALING_IN_PROGRESS')
                NOT NULL DEFAULT 'HEALTHY'
                AFTER status
    ");
    echo "  ✅ article_health_status column added to articles.\n";
}

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n=== Migration complete ===\n";
echo "Tables: exam_cycles, exam_cycle_articles\n";
echo "Column: articles.article_health_status\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
