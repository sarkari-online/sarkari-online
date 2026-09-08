<?php
/**
 * Sarkari.online - Modern Schema & Structured Data Service
 * Implements supported Google Search structured data: NewsArticle, Article, BreadcrumbList,
 * and restricted JobPosting (strictly for individual active recruitment vacancies).
 * (HowTo and FAQPage rich-result schemas are deprecated/removed per modern Google Search standards).
 */

namespace App\Services;

class SchemaService {

    /**
     * Generate all currently supported JSON-LD schema blocks for an article
     */
    public static function generate(array $article, string $categorySlug = ''): array {
        $schemas = [];
        $content = $article['content'] ?? '';
        $title   = $article['title'] ?? '';
        $url     = !empty($article['canonical_url']) ? $article['canonical_url'] : (SITE_URL . '/article/' . ($article['slug'] ?? '') . '/');
        $pubDate = !empty($article['published_at']) ? date('c', strtotime($article['published_at'])) : date('c');
        $modDate = !empty($article['updated_at'])   ? date('c', strtotime($article['updated_at']))   : $pubDate;
        $image   = !empty($article['featured_image']) ? url($article['featured_image']) : url('assets/images/default-share.jpg');
        $desc    = strip_tags($article['excerpt'] ?? $article['meta_description'] ?? '');

        $authorData = \App\Services\AuthorService::getAuthorForArticle($article);
        $authorUrl  = SITE_URL . '/author/' . $authorData['slug'] . '/';

        // 1. Primary Article / NewsArticle Schema (100% supported by Google Search)
        $primarySchema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'NewsArticle',
            'headline'         => $title,
            'description'      => $desc,
            'url'              => $url,
            'datePublished'    => $pubDate,
            'dateModified'     => $modDate,
            'image'            => [
                '@type'  => 'ImageObject',
                'url'    => $image,
                'width'  => 1200,
                'height' => 675
            ],
            'author'           => [
                '@type'    => 'Person',
                'name'     => $authorData['name'],
                'jobTitle' => $authorData['title'],
                'url'      => $authorUrl,
                'image'    => !empty($authorData['avatar_img']) ? url($authorData['avatar_img']) : url('assets/favicon-192x192.png'),
                'sameAs'   => !empty($authorData['linkedin']) ? $authorData['linkedin'] : $authorUrl,
                'worksFor' => [
                    '@type' => 'NewsMediaOrganization',
                    'name'  => SITE_NAME,
                    'url'   => SITE_URL
                ]
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => SITE_NAME,
                'url'   => SITE_URL,
                'logo'  => [
                    '@type'  => 'ImageObject',
                    'url'    => url('assets/sarkari-logo-transparent.png'),
                    'width'  => 200,
                    'height' => 60
                ],
                'publishingPrinciples' => url('editorial-policy/'),
                'correctionsPolicy'    => url('fact-checking-policy/#corrections')
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $url
            ],
            'inLanguage'       => 'en-IN',
            'isAccessibleForFree' => true,
            'articleSection'   => $article['category_name'] ?? 'Education'
        ];

        $schemas[] = $primarySchema;

        // 2. BreadcrumbList Schema (Crucial for mobile and desktop SERP breadcrumbs)
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => rtrim(SITE_URL, '/') . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $article['category_name'] ?? 'Education',
                    'item' => url('category/' . ($categorySlug ?: 'career-guides') . '/')
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $title,
                    'item' => $url
                ]
            ]
        ];

        // 3. Restricted JobPosting Schema (Strictly for active recruitment vacancies with verified deadline)
        $rawPayload = !empty($article['raw_payload'])
            ? (is_array($article['raw_payload']) ? $article['raw_payload'] : (json_decode($article['raw_payload'], true) ?: []))
            : [];
        $jobSchema = self::generateJobPosting($article, $rawPayload);
        if ($jobSchema !== null) {
            $schemas[] = $jobSchema;
        }

        return $schemas;
    }

    /**
     * Generate JobPosting Schema strictly for active recruitment notifications.
     * 
     * COMPLIANCE RULES:
     * - Returns null if lifecycle_status is 'closed', 'historical', 'exam_completed', etc.
     * - Returns null if article is admit card, result, syllabus, or answer key.
     * - Returns null if application deadline has passed (preventing Google manual action for stale jobs).
     * - Includes official authority sameAs, employmentType, and IN addressCountry.
     */
    public static function generateJobPosting(array $article, array $rawPayload = []): ?array {
        // 1. Strict Lifecycle Gate: Return null immediately if closed or past recruitment phase
        $lifecycle = $article['lifecycle_status'] ?? 'draft';
        if (in_array($lifecycle, ['closed', 'exam_completed', 'admit_card_released', 'result_released', 'historical', 'archived'], true)) {
            return null;
        }

        $title = $article['title'] ?? '';
        $lowerTitle = mb_strtolower($title);
        $content = $article['content'] ?? '';

        // 2. Strict Intent Gate: Negative keywords (never apply to non-recruitment milestones)
        $negativeKeywords = ['admit card', 'hall ticket', 'result', 'answer key', 'syllabus', 'exam date', 'exam city', 'cut off', 'merit list'];
        foreach ($negativeKeywords as $neg) {
            if (str_contains($lowerTitle, $neg)) {
                return null;
            }
        }

        // Positive Intent Gate: Must be genuine recruitment/vacancy
        $contentType = $article['content_type'] ?? '';
        $isRecruitment = ($contentType === 'recruitment_page')
            || ((str_contains($lowerTitle, 'recruitment') || str_contains($lowerTitle, 'vacancy') || str_contains($lowerTitle, 'bharti') || str_contains($lowerTitle, 'posts'))
                && (str_contains($lowerTitle, 'apply') || str_contains($lowerTitle, 'notification') || str_contains($lowerTitle, 'online form')));

        if (!$isRecruitment) {
            return null;
        }

        // 3. Extract and Verify Application Deadline
        $deadlineDate = null;

        // Priority 1: Check raw_payload grounded facts
        $groundedFacts = $rawPayload['authority_provenance']['grounded_facts'] 
            ?? $rawPayload['grounded_facts'] 
            ?? [];

        if (!empty($groundedFacts['application_deadline'])) {
            $parsedTs = strtotime($groundedFacts['application_deadline']);
            if ($parsedTs && $parsedTs > time()) {
                $deadlineDate = date('Y-m-d', $parsedTs);
            }
        }

        // Priority 2: Fallback to regex extraction from content
        if (!$deadlineDate) {
            $deadlineDate = self::extractDeadlineDate($content);
        }

        // Without a valid FUTURE deadline, return null to avoid Google manual actions on stale JobPosting
        if (!$deadlineDate) {
            return null;
        }

        $validThrough = date('c', strtotime($deadlineDate . ' 23:59:59'));
        $pubDate = !empty($article['published_at']) ? date('c', strtotime($article['published_at'])) : date('c');
        $desc = strip_tags($article['excerpt'] ?? $article['meta_description'] ?? '');
        $url = !empty($article['canonical_url']) ? $article['canonical_url'] : (SITE_URL . '/article/' . ($article['slug'] ?? '') . '/');

        // Resolve Organization details
        $orgName = $rawPayload['authority_provenance']['authority_name'] 
            ?? $article['source_name'] 
            ?? 'Government Statutory Authority';
        if (in_array(strtolower(trim((string)$orgName)), ['official statutory authority', 'statutory authority', 'official authority'], true)) {
            $resolved = \App\Services\AuthorityFactFetcherService::resolveAuthority($title);
            $orgName = $resolved['name'];
        }

        $portalUrl = $rawPayload['authority_provenance']['official_portal_url'] 
            ?? $article['source_url'] 
            ?? null;
        if (empty($portalUrl) || str_contains(strtolower((string)$portalUrl), 'sarkari.online')) {
            $resolved = \App\Services\AuthorityFactFetcherService::resolveAuthority($title);
            $portalUrl = $resolved['portal'] ?? SITE_URL;
        }

        return [
            '@context'           => 'https://schema.org',
            '@type'              => 'JobPosting',
            'title'              => $title,
            'description'        => $desc,
            'datePosted'         => $pubDate,
            'validThrough'       => $validThrough,
            'employmentType'     => 'FULL_TIME',
            'hiringOrganization' => [
                '@type'  => 'Organization',
                'name'   => $orgName,
                'sameAs' => $portalUrl
            ],
            'jobLocation'        => [
                '@type'   => 'Place',
                'address' => [
                    '@type'          => 'PostalAddress',
                    'addressCountry' => 'IN'
                ]
            ],
            'url'                => $url,
            'directApply'        => true
        ];
    }

    /**
     * Render all schemas as <script> tags for injection into <head>
     */
    public static function injectIntoHead(array $schemas): string {
        $output = '';
        foreach ($schemas as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $output .= "\n    <script type=\"application/ld+json\">\n    {$json}\n    </script>";
        }
        return $output;
    }

    /**
     * Try to extract an active future application deadline date from article content
     */
    private static function extractDeadlineDate(string $content): ?string {
        $patterns = [
            '/(?:last\s*date(?:\s+to\s+apply)?|apply\s*by|application\s*deadline|closing\s*date)(?:\s+is)?\s*[:\-]?\s*(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+202[5-9])/i',
            '/(?:last\s*date(?:\s+to\s+apply)?|apply\s*by|application\s*deadline|closing\s*date)(?:\s+is)?\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]202[5-9])/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $dateStr = trim($m[1]);
                $ts = strtotime($dateStr);
                if ($ts && $ts > time()) {
                    return date('Y-m-d', $ts);
                }
            }
        }

        return null;
    }
}
