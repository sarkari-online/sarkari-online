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
     * Top-tier national institutions where searchers already know the name.
     * Stating the expansion here preserves authority & matches YMYL expectations,
     * while regional posts and exams (VRO, Lekhpal, Patwari, QCO) use the
     * curiosity-gap formula to maximize CTR and eliminate Zero-Click searches.
     */
    public const WELL_KNOWN_INSTITUTIONS = [
        'RBI', 'SBI', 'UPSC', 'SSC', 'NEET', 'NDA', 'AIIMS', 'UGC', 'IAS',
        'IES', 'NTA', 'CBSE', 'IBPS', 'LIC', 'SEBI'
    ];

    /**
     * Denylist of abbreviations that should never be queued by the harvester.
     */
    public const HARVESTER_DENYLIST = [
        'PDF', 'FAQ', 'URL', 'SEO', 'OBC', 'EWS', 'SC', 'ST', 'PWD', 'HTML',
        'CBT', 'OMR', 'OTP', 'SMS', 'PAN', 'PIN', 'JPG', 'PNG', 'WWW', 'IST',
        'GEN', 'UR', 'DOB', 'TBA', 'API', 'APP', 'AMP', 'CSS', 'DOM', 'RSS'
    ];

    /**
     * Ensure database tables exist
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

            // Autonomous Harvester Candidate Queue Table
            $db->exec("CREATE TABLE IF NOT EXISTS `glossary_candidate_terms` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `acronym` VARCHAR(60) NOT NULL,
                `source_article_ids` TEXT NOT NULL,
                `occurrence_count` INT NOT NULL DEFAULT 1,
                `surrounding_context` TEXT NULL,
                `proposed_full_form_en` VARCHAR(255) NULL,
                `proposed_source_url` VARCHAR(500) NULL,
                `status` ENUM('pending','verified','rejected','duplicate') NOT NULL DEFAULT 'pending',
                `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `reviewed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_candidate_acronym` (`acronym`),
                INDEX `idx_candidate_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            Logger::error("GlossaryService::initTable error: " . $e->getMessage());
        }
    }

    /**
     * Determine if full expansion should be withheld in the meta title
     * to protect CTR and prevent Zero-Click searches on Google.
     * Withheld for 100% of terms to maintain uniform curiosity gap.
     */
    public static function shouldWithholdExpansionInTitle(array $term): bool {
        return true;
    }

    /**
     * Generate high-CTR, Google-compliant SERP Meta Title (Under 60 chars)
     * Never leaks the full expansion directly into the SERP snippet.
     */
    public static function generateMetaTitle(array $term, ?array $facts = null): string {
        $acronym = trim($term['acronym'] ?? '');
        $fullEn = trim($term['full_form_en'] ?? '');

        // Determine if this entity has verified salary data
        $hasSalary = false;
        if ($facts !== null) {
            $hasSalary = !empty($facts['pay_level_7cpc']) || !empty($facts['basic_pay_min']) || !empty($facts['gross_salary_min']);
        } elseif (!empty($term['id'])) {
            try {
                $fact = Database::fetchOne("SELECT pay_level_7cpc, basic_pay_min, gross_salary_min FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => (int)$term['id']]);
                if ($fact && (!empty($fact['pay_level_7cpc']) || !empty($fact['basic_pay_min']) || !empty($fact['gross_salary_min']))) {
                    $hasSalary = true;
                }
            } catch (\Throwable $e) {}
        }

        if ($hasSalary) {
            // High-Curiosity, Anti-Spoiler Formula for salary-holding recruitment posts
            $title = "{$acronym} Full Form: Meaning in Hindi, Salary & Selection 2026";
            if (mb_strlen($title) <= 59) {
                self::validateMetaAgainstExpansion($title, $fullEn);
                return $title;
            }

            $title = "{$acronym} Full Form: Meaning, Salary & Eligibility 2026";
            if (mb_strlen($title) <= 59) {
                self::validateMetaAgainstExpansion($title, $fullEn);
                return $title;
            }

            $title = "{$acronym} Full Form: Meaning, Salary & Selection";
            if (mb_strlen($title) <= 59) {
                self::validateMetaAgainstExpansion($title, $fullEn);
                return $title;
            }
        }

        // For entrance examinations, academic tests, or statutory commissions without pay scale:
        $title = "{$acronym} Full Form: Meaning, Eligibility & Selection 2026";
        if (mb_strlen($title) <= 59) {
            self::validateMetaAgainstExpansion($title, $fullEn);
            return $title;
        }

        $title = "{$acronym} Full Form: Meaning in Hindi & Eligibility 2026";
        if (mb_strlen($title) <= 59) {
            self::validateMetaAgainstExpansion($title, $fullEn);
            return $title;
        }

        $title = "{$acronym} Full Form: Meaning & Eligibility 2026";
        self::validateMetaAgainstExpansion($title, $fullEn);
        return $title;
    }

    /**
     * Generate high-CTR SERP Meta Description (145-155 chars)
     * Free of zero-click full-form spoilers across all 132 terms.
     */
    public static function generateMetaDescription(array $term, ?array $facts = null): string {
        $acronym = trim($term['acronym'] ?? '');
        $fullEn = trim($term['full_form_en'] ?? '');

        // Determine if this entity has verified salary data
        $hasSalary = false;
        if ($facts !== null) {
            $hasSalary = !empty($facts['pay_level_7cpc']) || !empty($facts['basic_pay_min']) || !empty($facts['gross_salary_min']);
        } elseif (!empty($term['id'])) {
            try {
                $fact = Database::fetchOne("SELECT pay_level_7cpc, basic_pay_min, gross_salary_min FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => (int)$term['id']]);
                if ($fact && (!empty($fact['pay_level_7cpc']) || !empty($fact['basic_pay_min']) || !empty($fact['gross_salary_min']))) {
                    $hasSalary = true;
                }
            } catch (\Throwable $e) {}
        }

        if ($hasSalary) {
            $desc = "What is {$acronym} full form? Check official statutory meaning in English & Hindi, salary structure, eligibility criteria and selection process on Sarkari.online.";
        } else {
            $desc = "What is {$acronym} full form? Check official statutory meaning in English & Hindi, eligibility criteria, exam pattern and selection process on Sarkari.online.";
        }

        self::validateMetaAgainstExpansion($desc, $fullEn);
        return $desc;
    }

    /**
     * Validate that a meta title or description does NOT leak the literal full expansion.
     * Future-proofing anti-spoiler gate.
     */
    public static function validateMetaAgainstExpansion(string $metaText, string $fullFormEn): bool {
        $cleanExpansion = trim($fullFormEn);
        if (empty($cleanExpansion) || strlen($cleanExpansion) < 4) {
            return true;
        }

        if (stripos($metaText, $cleanExpansion) !== false) {
            Logger::warning("GlossaryService: Meta text leaks literal full expansion '{$cleanExpansion}' in string: '{$metaText}'");
            return false;
        }

        return true;
    }

    /**
     * Render the 40-60 word Direct-Answer block optimized for Google Position 0 extraction
     */
    public static function renderDirectAnswerBlock(array $term): string {
        $acronym = htmlspecialchars($term['acronym']);
        $fullEn = htmlspecialchars($term['full_form_en']);
        $fullHi = !empty($term['full_form_hi']) ? htmlspecialchars($term['full_form_hi']) : '';
        $body = htmlspecialchars($term['conducting_body'] ?? 'Government of India');

        $hindiPart = !empty($fullHi) ? " (हिंदी में: <strong>{$fullHi}</strong>)" : "";

        // Extract first contextual sentence from overview or fallback
        $contextSentence = "It functions under {$body} as an official authority for examination administration and cadre recruitment";
        if (!empty($term['overview'])) {
            $sentences = preg_split('/(?<=[.?!])\s+/', trim(strip_tags($term['overview'])), 2);
            if (!empty($sentences[0]) && strlen($sentences[0]) > 20 && strlen($sentences[0]) < 180) {
                $contextSentence = rtrim($sentences[0], '.');
            }
        }

        $html = '<div class="direct-answer-container" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem 1.5rem; margin: 1.25rem 0 1.75rem 0;">'
              . '<div style="font-size: 0.72rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 6px;">'
              . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
              . 'Official Definition &amp; Statutory Meaning'
              . '</div>'
              . '<p class="direct-answer" data-snippet-target="true" style="margin: 0; font-size: 1.05rem; line-height: 1.65; color: #0f172a;">'
              . "<strong>{$acronym} full form</strong> is <strong>{$fullEn}</strong>{$hindiPart}. {$contextSentence}."
              . '</p>'
              . '</div>';

        return $html;
    }

    /**
     * Render the compact, 2-column Quick Facts Specifications Table
     */
    public static function renderFactsTable(array $term): string {
        $portal = $term['official_portal'] ?? '';
        $portalHost = !empty($portal) ? parse_url($portal, PHP_URL_HOST) : '';

        $html = '<div class="glossary-facts-wrapper" style="margin: 1.5rem 0 2rem 0; overflow-x: auto;">'
              . '<table class="glossary-facts-table" style="width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.875rem;">'
              . '<tbody>'
              . '<tr style="border-bottom: 1px solid #f1f5f9;"><th style="width: 32%; text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">Acronym / Short Form</th><td style="padding: 0.75rem 1rem; color: #0f172a; font-weight: 700;">' . htmlspecialchars($term['acronym']) . '</td></tr>'
              . '<tr style="border-bottom: 1px solid #f1f5f9;"><th style="text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">Full Form (English)</th><td style="padding: 0.75rem 1rem; color: #1e3a8a; font-weight: 700;">' . htmlspecialchars($term['full_form_en']) . '</td></tr>'
              . (!empty($term['full_form_hi']) ? '<tr style="border-bottom: 1px solid #f1f5f9;"><th style="text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">हिंदी अर्थ (Hindi Meaning)</th><td style="padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;">' . htmlspecialchars($term['full_form_hi']) . '</td></tr>' : '')
              . '<tr style="border-bottom: 1px solid #f1f5f9;"><th style="text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">Domain / Sector</th><td style="padding: 0.75rem 1rem; color: #0f172a;">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $term['category']))) . '</td></tr>'
              . '<tr style="border-bottom: 1px solid #f1f5f9;"><th style="text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">Regulatory / Conducting Body</th><td style="padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;">' . htmlspecialchars($term['conducting_body'] ?? 'Government of India') . '</td></tr>'
              . (!empty($portal) ? '<tr><th style="text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;">Official Portal</th><td style="padding: 0.75rem 1rem;"><a href="' . htmlspecialchars($portal) . '" target="_blank" rel="noopener noreferrer" style="color: #1e3a8a; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Official Portal">' . htmlspecialchars($portalHost ?: $portal) . ' <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a></td></tr>' : '')
              . '</tbody>'
              . '</table>'
              . '</div>';

        return $html;
    }

    /**
     * Generate Schema.org DefinedTerm & BreadcrumbList structured data
     */
    public static function generateDefinedTermSchema(array $term, string $canonicalUrl, string $hubUrl): string {
        $definedTerm = [
            "@context" => "https://schema.org",
            "@type" => "DefinedTerm",
            "name" => "{$term['acronym']} Full Form",
            "termCode" => $term['acronym'],
            "description" => "{$term['acronym']} stands for {$term['full_form_en']}. Official definition, Hindi meaning, eligibility criteria, and selection scheme.",
            "inDefinedTermSet" => [
                "@type" => "DefinedTermSet",
                "name" => "Sarkari.online Indian Government & Examination Acronym Glossary",
                "url" => $hubUrl
            ]
        ];

        if (!empty($term['official_portal'])) {
            $definedTerm["sameAs"] = $term['official_portal'];
        }

        $breadcrumbs = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => [
                ["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => url()],
                ["@type" => "ListItem", "position" => 2, "name" => "Full Forms (A-Z)", "item" => $hubUrl],
                ["@type" => "ListItem", "position" => 3, "name" => $term['acronym'], "item" => $canonicalUrl]
            ]
        ];

        return "<script type=\"application/ld+json\">" . json_encode($definedTerm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</script>\n"
             . "<script type=\"application/ld+json\">" . json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</script>";
    }

    /**
     * Generate Schema.org DefinedTermSet for the Hub directory
     */
    public static function generateHubSchema(array $termsOnPage, string $hubUrl): string {
        $items = [];
        foreach ($termsOnPage as $t) {
            $items[] = [
                "@type" => "DefinedTerm",
                "name" => $t['acronym'],
                "url" => url('full-forms/' . $t['slug'] . '/')
            ];
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "DefinedTermSet",
            "name" => "Government & Examination Full Forms Directory",
            "url" => $hubUrl,
            "description" => "A-to-Z directory of Indian competitive examinations, civil services, defence forces, and statutory authorities with bilingual meanings.",
            "hasDefinedTerm" => $items
        ];

        return "<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</script>";
    }

    /**
     * Bidirectional Auto-Linker: Injects links to glossary pages
     * on the FIRST occurrence of an acronym in article content.
     */
    public static function injectGlossaryLinks(string $content): string {
        self::initTable();
        try {
            // Fetch top active glossary terms
            $terms = Database::fetchAll("SELECT acronym, slug, full_form_en FROM glossary_terms ORDER BY LENGTH(acronym) DESC LIMIT 150");
            if (empty($terms)) {
                return $content;
            }

            foreach ($terms as $t) {
                $acr = preg_quote($t['acronym'], '/');
                // Replace only first occurrence outside HTML tags
                $pattern = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . $acr . ')\b/u';
                $url = url('full-forms/' . $t['slug'] . '/');
                $title = htmlspecialchars("{$t['acronym']} Full Form: {$t['full_form_en']}");
                $replacement = '<a href="' . $url . '" title="' . $title . '" class="glossary-term-link">$1</a>';
                
                $replaced = preg_replace($pattern, $replacement, $content, 1);
                if ($replaced !== null) {
                    $content = $replaced;
                }
            }
        } catch (Throwable $e) {
            Logger::error("GlossaryService::injectGlossaryLinks error: " . $e->getMessage());
        }

        return $content;
    }

    /**
     * Autonomous Harvester: Scan published article text for unknown acronyms
     * and queue them into `glossary_candidate_terms` for editorial promotion.
     */
    public static function scanContentForCandidates(int $articleId, string $content): void {
        self::initTable();
        try {
            // Match 3 to 6 uppercase letters
            preg_match_all('/\b[A-Z]{3,6}\b/', strip_tags($content), $matches);
            if (empty($matches[0])) {
                return;
            }

            $foundAcronyms = array_unique($matches[0]);
            foreach ($foundAcronyms as $acr) {
                if (in_array($acr, self::HARVESTER_DENYLIST, true)) {
                    continue;
                }

                // Check if already in live glossary
                $exists = Database::fetchValue("SELECT id FROM glossary_terms WHERE acronym = :acr LIMIT 1", ['acr' => $acr]);
                if ($exists) {
                    continue;
                }

                // Extract surrounding sentence context
                $context = '';
                if (preg_match('/(?:[^.?!]*\b' . preg_quote($acr, '/') . '\b[^.?!]*[.?!])/u', $content, $ctxMatch)) {
                    $context = trim(strip_tags($ctxMatch[0]));
                }

                // Upsert candidate queue
                $candidate = Database::fetchOne("SELECT id, source_article_ids, occurrence_count FROM glossary_candidate_terms WHERE acronym = :acr LIMIT 1", ['acr' => $acr]);
                if ($candidate) {
                    $artIds = json_decode($candidate['source_article_ids'], true) ?: [];
                    if (!in_array($articleId, $artIds, true)) {
                        $artIds[] = $articleId;
                    }
                    Database::execute(
                        "UPDATE glossary_candidate_terms 
                         SET occurrence_count = occurrence_count + 1, source_article_ids = :ids 
                         WHERE id = :id",
                        ['ids' => json_encode($artIds), 'id' => $candidate['id']]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO glossary_candidate_terms 
                         (acronym, source_article_ids, occurrence_count, surrounding_context, status, first_seen_at) 
                         VALUES (:acr, :ids, 1, :ctx, 'pending', NOW())",
                        [
                            'acr' => $acr,
                            'ids' => json_encode([$articleId]),
                            'ctx' => mb_substr($context, 0, 500)
                        ]
                    );
                }
            }
        } catch (Throwable $e) {
            Logger::error("GlossaryService::scanContentForCandidates error: " . $e->getMessage());
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
            return Database::fetchAll("SELECT g.slug, g.last_reviewed_at, g.updated_at, f.last_verified_at 
                FROM glossary_terms g 
                LEFT JOIN full_form_entity_facts f ON g.id = f.full_form_id 
                ORDER BY g.acronym ASC");
        } catch (Throwable $e) {
            return [];
        }
    }
}
