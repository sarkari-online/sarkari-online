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
                '@type' => 'Organization',
                'name'  => SITE_NAME,
                'url'   => SITE_URL
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
                ]
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

        // 3. Restricted JobPosting Schema (Strictly for individual recruitment vacancies with verified deadline)
        // Must NEVER be applied to syllabus, salary guides, admit cards, or result pages
        $contentType = $article['content_type'] ?? '';
        $isActualRecruitment = ($contentType === 'recruitment_page')
            || (str_contains(mb_strtolower($title), 'recruitment') && str_contains(mb_strtolower($title), 'apply online'))
            || (str_contains(mb_strtolower($title), 'vacancy') && str_contains(mb_strtolower($title), 'posts'));

        if ($isActualRecruitment && !str_contains(mb_strtolower($title), 'syllabus') && !str_contains(mb_strtolower($title), 'admit card') && !str_contains(mb_strtolower($title), 'result')) {
            $deadlineDate = self::extractDeadlineDate($content);
            if (!empty($deadlineDate)) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'JobPosting',
                    'title' => $title,
                    'description' => $desc,
                    'datePosted' => $pubDate,
                    'validThrough' => date('c', strtotime($deadlineDate . ' 23:59:59')),
                    'employmentType' => 'FULL_TIME',
                    'hiringOrganization' => [
                        '@type' => 'Organization',
                        'name' => $article['source_name'] ?? 'Government of India / Statutory Commission',
                        'sameAs' => $article['source_url'] ?? SITE_URL
                    ],
                    'jobLocation' => [
                        '@type' => 'Place',
                        'address' => [
                            '@type' => 'PostalAddress',
                            'addressCountry' => 'IN'
                        ]
                    ],
                    'url' => $url
                ];
            }
        }

        return $schemas;
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
            '/(?:last\s*date|apply\s*by|deadline|closing\s*date)\s*[:\-]?\s*(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(202[5-9])/i',
            '/(?:last\s*date|apply\s*by|deadline)\s*[:\-]?\s*(\d{1,2})[\/\-](\d{1,2})[\/\-](202[5-9])/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $ts = strtotime($m[0]);
                if ($ts && $ts > time()) {
                    return date('Y-m-d', $ts);
                }
            }
        }

        return null;
    }
}
