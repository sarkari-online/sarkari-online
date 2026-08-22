-- ==============================================================================
-- EduPulse - Production Database Schema (MySQL 8+)
-- Character set: utf8mb4, Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Admin & Editorial Staff)
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(64) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'editor', 'author') NOT NULL DEFAULT 'editor',
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `description` TEXT NULL,
    `color` VARCHAR(24) NOT NULL DEFAULT '#1e3a8a',
    `bg_light` VARCHAR(24) NOT NULL DEFAULT '#eff6ff',
    `icon` VARCHAR(64) NOT NULL DEFAULT 'award',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_slug` (`slug`),
    INDEX `idx_categories_sort` (`sort_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Articles Table (Core Content Repository)
CREATE TABLE IF NOT EXISTS `articles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trend_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `excerpt` TEXT NULL,
    `content` LONGTEXT NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `author_id` BIGINT UNSIGNED NULL,
    `status` ENUM('draft', 'review', 'published', 'rejected') NOT NULL DEFAULT 'draft',
    `quality_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ai_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `source_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `featured_image` VARCHAR(255) NULL,
    `featured_image_alt` VARCHAR(255) NULL,
    `meta_title` VARCHAR(255) NULL,
    `meta_description` VARCHAR(300) NULL,
    `canonical_url` VARCHAR(255) NULL,
    `og_title` VARCHAR(255) NULL,
    `og_description` VARCHAR(300) NULL,
    `og_image` VARCHAR(255) NULL,
    `source_name` VARCHAR(191) NULL,
    `source_url` VARCHAR(500) NULL,
    `source_ref` VARCHAR(191) NULL,
    `published_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `original_published_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_articles_slug` (`slug`),
    INDEX `idx_articles_status_pub` (`status`, `published_at`),
    INDEX `idx_articles_category` (`category_id`),
    INDEX `idx_articles_author` (`author_id`),
    INDEX `idx_articles_quality` (`quality_score`),
    FULLTEXT KEY `ft_articles_search` (`title`, `excerpt`, `content`),
    CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Sources Table (Official Government/Exam Authorities)
CREATE TABLE IF NOT EXISTS `sources` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `base_url` VARCHAR(255) NOT NULL,
    `adapter_class` VARCHAR(120) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `robots_checked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sources_name` (`name`),
    INDEX `idx_sources_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Trends Table (Trend Detection Queue)
CREATE TABLE IF NOT EXISTS `trends` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `keyword` VARCHAR(191) NOT NULL,
    `normalized_hash` VARCHAR(64) NULL,
    `source` VARCHAR(64) NOT NULL,
    `url` VARCHAR(500) NULL,
    `trend_score` INT NOT NULL DEFAULT 50,
    `raw_payload` TEXT NULL,
    `category_id` INT UNSIGNED NULL,
    `category_hint` VARCHAR(64) NULL,
    `status` ENUM('detected', 'analyzing', 'approved', 'rejected', 'generated', 'published', 'failed') NOT NULL DEFAULT 'detected',
    `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `analyzed_at` DATETIME NULL,
    `processed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_trends_status` (`status`),
    INDEX `idx_trends_norm_hash` (`normalized_hash`),
    INDEX `idx_trends_score` (`trend_score` DESC),
    INDEX `idx_trends_detected` (`detected_at` DESC),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Article Checks Table (Quality & Fact Verification Logs)
CREATE TABLE IF NOT EXISTS `article_checks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` BIGINT UNSIGNED NOT NULL,
    `check_type` VARCHAR(64) NOT NULL,
    `score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `checker` ENUM('ai', 'human') NOT NULL DEFAULT 'human',
    `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_checks_article` (`article_id`),
    INDEX `idx_checks_type` (`check_type`),
    CONSTRAINT `fk_checks_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. AI Logs Table (Audit Trail for Pipeline Stages)
CREATE TABLE IF NOT EXISTS `ai_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` BIGINT UNSIGNED NULL,
    `trend_id` BIGINT UNSIGNED NULL,
    `pipeline_stage` VARCHAR(64) NOT NULL,
    `prompt_summary` TEXT NULL,
    `response_summary` LONGTEXT NULL,
    `tokens_used` INT UNSIGNED NOT NULL DEFAULT 0,
    `success` TINYINT(1) NOT NULL DEFAULT 1,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ai_logs_article` (`article_id`),
    INDEX `idx_ai_logs_trend` (`trend_id`),
    INDEX `idx_ai_logs_stage` (`pipeline_stage`),
    INDEX `idx_ai_logs_success` (`success`),
    CONSTRAINT `fk_ai_logs_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ai_logs_trend` FOREIGN KEY (`trend_id`) REFERENCES `trends` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Settings Table (Key-Value Dynamic Configuration)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL,
    `value` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Article Updates Table (Change History & Revision Snapshots)
CREATE TABLE IF NOT EXISTS `article_updates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id` BIGINT UNSIGNED NOT NULL,
    `old_content` LONGTEXT NOT NULL,
    `new_content` LONGTEXT NOT NULL,
    `reason` TEXT NOT NULL,
    `source_url` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_article_updates_article` (`article_id`),
    CONSTRAINT `fk_article_updates_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
