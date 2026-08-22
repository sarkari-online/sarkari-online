<?php
/**
 * EduPulse - Comprehensive SEO & Structured Data Helper
 * Generates Schema.org JSON-LD (Article, Breadcrumbs, Organization, WebSite, FAQPage),
 * Open Graph, Twitter/X Cards, and canonical URLs.
 */

namespace App\Helpers;

class SEOHelper {

    /**
     * Generate Organization JSON-LD
     */
    public static function organizationSchema(): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => SITE_NAME,
            'url' => SITE_URL,
            'description' => SITE_DESCRIPTION,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url('assets/images/logo.png')
            ],
            'sameAs' => []
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Generate WebSite JSON-LD with Sitelinks Searchbox
     */
    public static function websiteSchema(): string {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => SITE_NAME,
            'url' => SITE_URL,
            'description' => SITE_DESCRIPTION,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('search/?q={search_term_string}'),
                'query-input' => 'required name=search_term_string'
            ]
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
