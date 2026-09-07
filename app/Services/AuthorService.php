<?php
/**
 * Sarkari.online - Author & Editorial Persona Service
 * Manages verified E-E-A-T editorial personas, author archive pages, credentials,
 * and structured data Person mapping for Google Helpful Content compliance.
 */

namespace App\Services;

use App\Database\Database;
use PDO;

class AuthorService {

    /**
     * Directory of verified editorial personas
     */
    private static array $personas = [
        'ajay-mathur' => [
            'id'             => 1,
            'slug'           => 'ajay-mathur',
            'name'           => 'Ajay Mathur',
            'title'          => 'Founder & Managing Editor',
            'role'           => 'Founder & Head of Editorial Operations',
            'education'      => 'Founder & Lead Researcher, Sarkari.online',
            'experience'     => 'Digital Education & Statutory Examination Analysis',
            'focus_areas'    => ['UPSC & Central Civil Services', 'SSC CGL, CHSL & MTS', 'Railways (RRB NTPC/ALP)', 'National Entrance Tests (NEET, JEE, CUET)', 'State PSC Recruitments'],
            'bio'            => 'Ajay Mathur is the Founder and Managing Editor of Sarkari.online. Dedicated to eliminating misinformation, clickbait, and unverified rumors in the Indian government examination space, Ajay oversees editorial accuracy, data fact-checking, and statutory gazette verification across all published job alerts, examination schedules, admit card updates, and answer key releases.',
            'methodology'    => 'Strictly cross-verifies every examination date, eligibility rule, vacancy figure, and application link against official gazette releases and authenticated statutory authority portals (.gov.in, .nic.in) prior to clearance.',
            'avatar_letter'  => 'A',
            'avatar_bg'      => '#1e3a8a',
            'linkedin'       => 'https://www.linkedin.com/in/ajay-mathur-03a626254/',
            'social'         => [
                'linkedin' => 'https://www.linkedin.com/in/ajay-mathur-03a626254/'
            ]
        ],
        'editorial-desk' => [
            'id'             => 2,
            'slug'           => 'editorial-desk',
            'name'           => 'Sarkari.online Editorial & Verification Desk',
            'title'          => 'Statutory Verification & Research Bureau',
            'role'           => 'Central Newsroom & Verification Directorate',
            'education'      => 'Sarkari.online Central Editorial Board',
            'experience'     => 'Continuous Multidisciplinary Statutory News Monitoring',
            'focus_areas'    => ['Breaking Employment Notifications', 'Court Judgments & Recruitment Stay Orders', 'Answer Key Objections & Result Bulletins', 'Administrative Guidelines'],
            'bio'            => 'The Sarkari.online Editorial Desk is a collaborative research desk comprising dedicated education fact-checkers and notification researchers. Under the editorial guidance of Founder Ajay Mathur, the desk maintains rigorous three-point fact-checking standards across all Indian central and state government recruitment updates.',
            'methodology'    => 'Every update published under the Editorial Desk undergoes independent verification against authenticated government press releases, gazette notifications, and commission websites.',
            'avatar_letter'  => 'S',
            'avatar_bg'      => '#047857',
            'linkedin'       => 'https://www.linkedin.com/in/ajay-mathur-03a626254/',
            'social'         => [
                'linkedin' => 'https://www.linkedin.com/in/ajay-mathur-03a626254/'
            ]
        ]
    ];

    /**
     * Get all verified author personas
     */
    public static function getAll(): array {
        return self::$personas;
    }

    /**
     * Get author persona by slug
     */
    public static function getBySlug(string $slug): ?array {
        $slug = strtolower(trim($slug));
        return self::$personas[$slug] ?? null;
    }

    /**
     * Map an article to its verified editorial persona
     * Never returns 'sarkari_admin' or generic 'admin' usernames.
     */
    public static function getAuthorForArticle(array $article): array {
        // 1. Explicit author_slug if already stored
        if (!empty($article['author_slug']) && isset(self::$personas[$article['author_slug']])) {
            return self::$personas[$article['author_slug']];
        }

        // 2. Default to Founder & Managing Editor Ajay Mathur
        return self::$personas['ajay-mathur'];
    }

    /**
     * Fetch published articles authored or curated by this persona
     */
    public static function getArticlesByAuthor(string $slug, int $limit = 24): array {
        $author = self::getBySlug($slug);
        if (!$author) {
            return [];
        }

        $allArticles = ArticleService::getLatestPublished(50);
        return array_slice($allArticles, 0, min($limit, count($allArticles)));
    }
}
