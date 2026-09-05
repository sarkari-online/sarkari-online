<?php
/**
 * EduPulse - Comprehensive SEO & Structured Data Helper
 * Generates Schema.org JSON-LD (Article, Breadcrumbs, Organization, WebSite, FAQPage),
 * Open Graph, Twitter/X Cards, and canonical URLs.
 */

namespace App\Helpers;

class SEOHelper {

    /**
     * Generate Organization JSON-LD with verified branding & Knowledge Graph identifiers
     */
    public static function organizationSchema(): string {
        $siteUrl = rtrim(SITE_URL, '/') . '/';
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => SITE_NAME,
            'alternateName' => ['Sarkari Online', 'sarkari.online', 'SarkariOnline', 'Sarkari Result'],
            'url' => $siteUrl,
            'description' => SITE_DESCRIPTION,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url('assets/favicon-192x192.png'),
                'width' => 192,
                'height' => 192
            ],
            'sameAs' => [
                'https://twitter.com/SarkariOnline'
            ]
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate WebSite JSON-LD adhering to Google Site Names & Sitelinks Searchbox specifications
     */
    public static function websiteSchema(): string {
        $siteUrl = rtrim(SITE_URL, '/') . '/';
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => SITE_NAME,
            'alternateName' => [
                'Sarkari Online',
                'SarkariOnline',
                'sarkari.online',
                'Sarkari Result Online'
            ],
            'url' => $siteUrl,
            'description' => SITE_DESCRIPTION,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('search/?q={search_term_string}')
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate SiteNavigationElement JSON-LD for Google Sitelinks Rich Snippets
     */
    public static function siteNavigationSchema(): string {
        $navItems = [
            ['name' => 'Latest Government Jobs 2026', 'url' => url('latest-jobs/'), 'description' => 'Online form, vacancies & last dates'],
            ['name' => 'Sarkari Exam Results', 'url' => url('category/exam-results/'), 'description' => 'Live scorecards & merit lists'],
            ['name' => 'Admit Cards & Hall Tickets', 'url' => url('category/admit-cards/'), 'description' => 'Direct download links & exam city slips'],
            ['name' => 'State Government Jobs (All 28 States)', 'url' => url('state-jobs/'), 'description' => 'Regional PSC & Subordinate board alerts'],
            ['name' => 'Exam Dates & Schedules', 'url' => url('category/exam-dates/'), 'description' => 'Official calendars & notification dates'],
            ['name' => 'Student Utilities & Calculators', 'url' => url('tools/'), 'description' => 'Salary, CGPA, and Age calculators']
        ];

        $elements = [];
        foreach ($navItems as $index => $item) {
            $elements[] = [
                '@type' => 'SiteNavigationElement',
                'position' => $index + 1,
                'name' => $item['name'],
                'description' => $item['description'],
                'url' => $item['url']
            ];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => $elements
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate BreadcrumbList JSON-LD
     */
    public static function breadcrumbSchema(array $crumbs): string {
        $items = [];
        foreach ($crumbs as $index => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['label']
            ];
            if (!empty($crumb['url'])) {
                $item['item'] = url($crumb['url']);
            } else {
                $item['item'] = SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
            }
            $items[] = $item;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate Article / NewsArticle JSON-LD
     */
    public static function articleSchema(array $article, string $canonicalUrl): string {
        $publishedAt = !empty($article['published_at']) ? date('c', strtotime($article['published_at'])) : date('c');
        $updatedAt = !empty($article['updated_at']) ? date('c', strtotime($article['updated_at'])) : $publishedAt;

        $authorName = 'Sarkari.online Editorial Desk';
        if (!empty($article['author_username'])) {
            $authorName = $article['author_username'];
        } elseif (!empty($article['author']) && is_array($article['author'])) {
            $authorName = $article['author']['name'];
        }

        $image = !empty($article['og_image']) ? url($article['og_image']) : (!empty($article['featured_image']) ? url($article['featured_image']) : url('assets/images/default-card.jpg'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article['title'],
            'description' => $article['meta_description'] ?? $article['excerpt'] ?? '',
            'image' => [
                '@type' => 'ImageObject',
                'url' => $image,
                'width' => 1200,
                'height' => 675
            ],
            'datePublished' => $publishedAt,
            'dateModified' => $updatedAt,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl
            ],
            'author' => [
                '@type' => 'Person',
                'name' => $authorName
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => url('assets/images/logo.png')
                ]
            ],
            'articleSection' => $article['category_name'] ?? 'Education'
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate FAQPage JSON-LD
     */
    public static function faqSchema(array $faqs): ?string {
        if (empty($faqs)) {
            return null;
        }

        $mainEntity = [];
        foreach ($faqs as $faq) {
            if (!empty($faq['question']) && !empty($faq['answer'])) {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($faq['answer'])
                    ]
                ];
            }
        }

        if (empty($mainEntity)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
