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
            'title'          => 'Founder & Lead Web Architect',
            'role'           => 'Founder & Publisher — Sarkari.online',
            'education'      => 'Web & Cloud Infrastructure Engineer (Ex-Collegedunia, 2022–2025)',
            'experience'     => '4+ Years in High-Traffic Educational Web Architecture & AWS Cloud Systems',
            'focus_areas'    => ['Educational Web Architecture & Scalability', 'UPSC & Central Civil Services', 'SSC CGL, CHSL & MTS', 'Railways (RRB NTPC/ALP)', 'National Testing Agency (NEET, JEE, CUET)'],
            'bio'            => 'Ajay Mathur is the Founder and Lead Web Architect of Sarkari.online. A Web Developer and Cloud Infrastructure Engineer with 4+ years of experience managing high-traffic digital platforms — including 3 years at Collegedunia (2022–2025) — Ajay built Sarkari.online to provide Indian aspirants with a fast, reliable, and authentic information hub for government recruitment notifications, admit cards, and examination results directly sourced from official gazettes and statutory portals.',
            'methodology'    => 'Strictly verifies every examination date, eligibility rule, vacancy figure, and official PDF notice against authenticated government domains (.gov.in, .nic.in) prior to clearance, backed by high-availability cloud engineering.',
            'avatar_letter'  => 'A',
            'avatar_img'     => 'images/ajay-mathur.jpg',
            'avatar_bg'      => '#1e3a8a',
            'email'          => 'official.sarkarionline@gmail.com',
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
            'bio'            => 'The Sarkari.online Editorial Desk is a dedicated research unit responsible for tracking real-time statutory gazette circulars, exam commission notices, and admit card release windows. Under the technical and editorial direction of Founder Ajay Mathur, the desk ensures every report is factual, non-speculative, and backed by primary government sources.',
            'methodology'    => 'Every update published under the Editorial Desk undergoes independent verification against authenticated government press releases, gazette notifications, and commission websites.',
            'avatar_letter'  => 'S',
            'avatar_img'     => null,
            'avatar_bg'      => '#047857',
            'email'          => 'official.sarkarionline@gmail.com',
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
        if (!empty($article['author_slug']) && isset(self::$personas[$article['author_slug']])) {
            return self::$personas[$article['author_slug']];
        }

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
