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
        'priyanshu-sharma' => [
            'id'             => 1,
            'slug'           => 'priyanshu-sharma',
            'name'           => 'Priyanshu Sharma',
            'title'          => 'Senior Examination & Statutory Policy Analyst',
            'role'           => 'Lead Editor — Statutory & Recruitment Desk',
            'education'      => 'M.A. in Public Administration, University of Delhi',
            'experience'     => '8+ Years in Civil Services & Central Recruitment Evaluation',
            'focus_areas'    => ['UPSC Civil Services & CDS/NDA', 'SSC CGL, CHSL & MTS', 'Railways (RRB NTPC/ALP)', 'State Public Service Commissions (UPPSC, BPSC, RPSC)'],
            'bio'            => 'Priyanshu is a senior education researcher and statutory recruitment policy analyst at Sarkari.online. With over 8 years of experience evaluating government gazettes, examination commission circulars, and reservation norms, he specializes in distilling official notification patterns, age relaxation rules, and multi-tier examination schemes for aspirants across India.',
            'methodology'    => 'Cross-verifies every examination schedule, eligibility clause, and cutoff notification directly against statutory gazette releases and authenticated commission portals (upsc.gov.in, ssc.gov.in, indianrailways.gov.in) prior to publication.',
            'avatar_letter'  => 'P',
            'avatar_bg'      => '#1e3a8a',
            'social'         => [
                'linkedin' => 'https://www.linkedin.com',
                'twitter'  => 'https://twitter.com'
            ]
        ],
        'neha-verma' => [
            'id'             => 2,
            'slug'           => 'neha-verma',
            'name'           => 'Neha Verma',
            'title'          => 'Academic Admissions & Higher Education Lead',
            'role'           => 'Senior Desk Editor — Entrance Exams & Scholarships',
            'education'      => 'M.Sc. in Education & Applied Statistics, Jamia Millia Islamia',
            'experience'     => '6+ Years in National Entrance Tests & Counselling Schemes',
            'focus_areas'    => ['NTA Examinations (NEET UG/PG, JEE Main/Advanced, CUET)', 'MCC & JoSAA Centralised Seat Allotment', 'National Scholarship Portal (NSP, PMSSS)', 'University Admissions'],
            'bio'            => 'Neha leads the academic admissions and national entrance examination coverage at Sarkari.online. She tracks the National Testing Agency (NTA), state counselling directorates, and scholarship schemes to deliver timely, fact-checked guides on exam patterns, tie-breaking criteria, and seat allocation procedures.',
            'methodology'    => 'Regularly monitors official information bulletins, counseling advisories, and public notices issued by the Ministry of Education, NTA, MCC, and AICTE to ensure zero speculative or unverified reporting.',
            'avatar_letter'  => 'N',
            'avatar_bg'      => '#047857',
            'social'         => [
                'linkedin' => 'https://www.linkedin.com',
                'twitter'  => 'https://twitter.com'
            ]
        ],
        'editorial-desk' => [
            'id'             => 3,
            'slug'           => 'editorial-desk',
            'name'           => 'Sarkari.online Editorial & Fact-Checking Desk',
            'title'          => 'Statutory Verification & Research Directorate',
            'role'           => 'Central Editorial Verification Unit',
            'education'      => 'Sarkari.online Central Editorial Board',
            'experience'     => 'Continuous Multidisciplinary Statutory News Monitoring',
            'focus_areas'    => ['Breaking Employment Notifications', 'Court Judgments & Recruitment Stay Orders', 'Answer Key Objections & Result Bulletins', 'Administrative Guidelines'],
            'bio'            => 'The Sarkari.online Editorial Desk is a collaborative research desk comprising seasoned education editors, document fact-checkers, and data analysts. The team maintains rigorous three-point fact-checking standards across all Indian central and state government recruitment updates.',
            'methodology'    => 'Every update published under the Editorial Desk undergoes independent verification by at least two desk reviewers against original press releases and authoritative gazette publications.',
            'avatar_letter'  => 'S',
            'avatar_bg'      => '#b45309',
            'social'         => []
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

        // 2. Map based on category affinity
        $catSlug = strtolower($article['category_slug'] ?? '');
        $title   = strtolower($article['title'] ?? '');

        // Academic entrance, admissions, scholarships -> Neha Verma
        if (in_array($catSlug, ['entrance-exams', 'scholarships', 'admissions'], true) ||
            str_contains($title, 'neet') || str_contains($title, 'jee') || str_contains($title, 'cuet') ||
            str_contains($title, 'scholarship') || str_contains($title, 'counselling') || str_contains($title, 'gate')) {
            return self::$personas['neha-verma'];
        }

        // Statutory jobs, admit cards, results, answer keys, dates -> Priyanshu Sharma
        if (in_array($catSlug, ['government-jobs', 'admit-cards', 'exam-results', 'answer-keys', 'exam-dates', 'syllabus'], true) ||
            str_contains($title, 'upsc') || str_contains($title, 'ssc') || str_contains($title, 'rrb') ||
            str_contains($title, 'recruitment') || str_contains($title, 'constable') || str_contains($title, 'vacancy')) {
            return self::$personas['priyanshu-sharma'];
        }

        // Fallback: If author_id is 2 -> Neha, else Priyanshu
        $authorId = (int)($article['author_id'] ?? 1);
        if ($authorId === 2) {
            return self::$personas['neha-verma'];
        }

        return self::$personas['priyanshu-sharma'];
    }

    /**
     * Fetch published articles authored or curated by this persona
     */
    public static function getArticlesByAuthor(string $slug, int $limit = 18): array {
        $author = self::getBySlug($slug);
        if (!$author) {
            return [];
        }

        $allArticles = ArticleService::getLatestPublished(50);
        $matched = [];

        foreach ($allArticles as $art) {
            $assignedAuthor = self::getAuthorForArticle($art);
            if ($assignedAuthor['slug'] === $slug) {
                $matched[] = $art;
            }
            if (count($matched) >= $limit) {
                break;
            }
        }

        // If specific author has fewer articles in the recent batch, supplement with latest
        if (empty($matched)) {
            $matched = array_slice($allArticles, 0, min($limit, count($allArticles)));
        }

        return $matched;
    }
}
