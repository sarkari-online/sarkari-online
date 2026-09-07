<?php
/**
 * Sarkari.online - A-to-Z Government & Examination Full Forms Glossary Service
 * High-performance data provider with full-text search, alphabet filtering,
 * and comprehensive seed dictionary.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use Throwable;

class GlossaryService {

    /**
     * Ensure database table exists
     */
    public static function initTable(): void {
        try {
            $db = Database::getConnection();
            $db->exec("CREATE TABLE IF NOT EXISTS `glossary_terms` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `acronym` VARCHAR(60) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `letter` CHAR(1) NOT NULL,
                `full_form_en` VARCHAR(255) NOT NULL,
                `full_form_hi` VARCHAR(255) NULL,
                `category` VARCHAR(60) NOT NULL,
                `conducting_body` VARCHAR(255) NULL,
                `official_portal` VARCHAR(255) NULL,
                `overview` TEXT NOT NULL,
                `eligibility_criteria` TEXT NULL,
                `selection_process` TEXT NULL,
                `syllabus_snapshot` TEXT NULL,
                `related_article_slug` VARCHAR(255) NULL,
                `last_reviewed_at` DATE NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_glossary_slug` (`slug`),
                INDEX `idx_glossary_letter` (`letter`),
                INDEX `idx_glossary_category` (`category`),
                FULLTEXT KEY `idx_glossary_search` (`acronym`, `full_form_en`, `full_form_hi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            Logger::error("GlossaryService::initTable error: " . $e->getMessage());
        }
    }

    /**
     * Fetch terms with optional letter, category, and search query filters
     */
    public static function getTerms(?string $letter = null, ?string $category = null, ?string $query = null, int $limit = 100, int $offset = 0): array {
        self::initTable();
        $conditions = [];
        $params = [];

        if (!empty($letter) && strtoupper($letter) !== 'ALL') {
            $conditions[] = "letter = :letter";
            $params['letter'] = strtoupper(substr($letter, 0, 1));
        }

        if (!empty($category) && strtolower($category) !== 'all') {
            $conditions[] = "category = :category";
            $params['category'] = strtolower(trim($category));
        }

        if (!empty($query)) {
            $cleanQ = trim($query);
            $conditions[] = "(acronym LIKE :q1 OR full_form_en LIKE :q2 OR full_form_hi LIKE :q3 OR conducting_body LIKE :q4)";
            $params['q1'] = "%{$cleanQ}%";
            $params['q2'] = "%{$cleanQ}%";
            $params['q3'] = "%{$cleanQ}%";
            $params['q4'] = "%{$cleanQ}%";
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        $sql = "SELECT id, acronym, slug, letter, full_form_en, full_form_hi, category, conducting_body, official_portal, overview, eligibility_criteria, selection_process, syllabus_snapshot, related_article_slug, last_reviewed_at 
                FROM glossary_terms 
                {$whereClause} 
                ORDER BY acronym ASC 
                LIMIT {$limit} OFFSET {$offset}";

        try {
            return Database::fetchAll($sql, $params);
        } catch (Throwable $e) {
            Logger::error("GlossaryService::getTerms error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single term by slug
     */
    public static function getBySlug(string $slug): ?array {
        self::initTable();
        $cleanSlug = strtolower(trim($slug, '/'));
        try {
            return Database::fetchOne("SELECT * FROM glossary_terms WHERE slug = :slug LIMIT 1", ['slug' => $cleanSlug]);
        } catch (Throwable $e) {
            Logger::error("GlossaryService::getBySlug error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get term counts per alphabet letter
     */
    public static function getAlphabetCounts(): array {
        self::initTable();
        $counts = [];
        for ($i = 65; $i <= 90; $i++) {
            $counts[chr($i)] = 0;
        }

        try {
            $rows = Database::fetchAll("SELECT letter, COUNT(*) as cnt FROM glossary_terms GROUP BY letter");
            foreach ($rows as $r) {
                $counts[$r['letter']] = (int)$r['cnt'];
            }
        } catch (Throwable $e) {}

        return $counts;
    }

    /**
     * Get all categories with counts
     */
    public static function getCategoryCounts(): array {
        self::initTable();
        try {
            return Database::fetchAll("SELECT category, COUNT(*) as cnt FROM glossary_terms GROUP BY category ORDER BY cnt DESC");
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Total terms count
     */
    public static function getTotalCount(): int {
        self::initTable();
        try {
            return (int)Database::fetchValue("SELECT COUNT(*) FROM glossary_terms");
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get related terms for a given category/letter
     */
    public static function getRelatedTerms(int $currentId, string $category, int $limit = 6): array {
        self::initTable();
        try {
            return Database::fetchAll(
                "SELECT id, acronym, slug, full_form_en, full_form_hi, category 
                 FROM glossary_terms 
                 WHERE category = :category AND id != :id 
                 ORDER BY RAND() 
                 LIMIT {$limit}",
                ['category' => $category, 'id' => $currentId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get all terms for XML Sitemap
     */
    public static function getAllForSitemap(): array {
        self::initTable();
        try {
            return Database::fetchAll("SELECT slug, last_reviewed_at, updated_at FROM glossary_terms ORDER BY acronym ASC");
        } catch (Throwable $e) {
            return [];
        }
    }
}
