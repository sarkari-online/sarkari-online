<?php
/**
 * Sarkari.online - Automated Rich Schema Injection Service
 * Generates NewsArticle, FAQPage, Event, HowTo JSON-LD structured data
 * for maximum Google Rich Results eligibility.
 */

namespace App\Services;

use App\Database\Database;

class SchemaService {

    /**
     * Generate all applicable JSON-LD schema blocks for an article
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

        // All Sarkari.online articles are official statutory exam & recruitment news bulletins
        $articleType = 'NewsArticle';

        // 1. Primary Article / NewsArticle Schema
        $primarySchema = [
            '@context'         => 'https://schema.org',
            '@type'            => $articleType,
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
            'isAccessibleForFree' => true
        ];

        if ($articleType === 'NewsArticle') {
            $primarySchema['articleSection'] = $article['category_name'] ?? 'Education';
        }

        // Inject Google Sitelinks deep navigation anchors (hasPart schema)
        if (preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $hMatches)) {
            $hasPart = [];
            foreach ($hMatches[1] as $rawH2) {
                $hText = trim(strip_tags($rawH2));
                if (!empty($hText)) {
                    $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/u', '-', $hText)), '-');
                    $hasPart[] = [
                        '@type'               => 'WebPageElement',
                        'isAccessibleForFree' => true,
                        'cssSelector'         => '#' . $slug,
                        'name'                => $hText
                    ];
                }
            }
            if (!empty($hasPart)) {
                $primarySchema['hasPart'] = array_slice($hasPart, 0, 6);
            }
        }

        $schemas[] = $primarySchema;

        // 2. FAQPage Schema — detect FAQ content
        $faqs = self::extractFAQs($content, $article);
        if (!empty($faqs)) {
            $schemas[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array_map(function($faq) {
                    return [
                        '@type'          => 'Question',
                        'name'           => $faq['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => strip_tags($faq['answer'])
                        ]
                    ];
                }, $faqs)
            ];
        }

        // 3. HowTo Schema — detect step-by-step content
        if (self::hasStepContent($content)) {
            $steps = self::extractSteps($content);
            if (!empty($steps)) {
                $schemas[] = [
                    '@context'    => 'https://schema.org',
                    '@type'       => 'HowTo',
                    'name'        => $title,
                    'description' => $desc,
                    'step'        => array_map(function($step, $i) {
                        return [
                            '@type'    => 'HowToStep',
                            'position' => $i + 1,
                            'name'     => $step['name'],
                            'text'     => strip_tags($step['text'])
                        ];
                    }, $steps, array_keys($steps))
                ];
            }
        }

        // 4. Event Schema — for exam/recruitment categories
        $examCategories = ['entrance-exams', 'recruitment', 'government-jobs', 'results', 'admit-card'];
        if (in_array($categorySlug, $examCategories, true)) {
            $examDate = self::extractExamDate($content);
            $eventSchema = [
                '@context'  => 'https://schema.org',
                '@type'     => 'Event',
                'name'      => $title,
                'description' => $desc,
                'url'       => $url,
                'organizer' => [
                    '@type' => 'Organization',
                    'name'  => $article['source_name'] ?? 'Government of India',
                    'url'   => $article['source_url'] ?? 'https://india.gov.in'
                ],
                'eventStatus'   => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
                'location' => [
                    '@type' => 'VirtualLocation',
                    'url'   => $article['source_url'] ?? 'https://india.gov.in'
                ]
            ];
            if ($examDate) {
                $eventSchema['startDate'] = $examDate;
            }
            $schemas[] = $eventSchema;
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
     * Extract FAQ pairs from article content or stored FAQ data
     */
    private static function extractFAQs(string $content, array $article): array {
        $faqs = [];

        // Check stored FAQ JSON field
        if (!empty($article['faqs'])) {
            $stored = is_array($article['faqs']) ? $article['faqs'] : json_decode($article['faqs'], true);
            if (is_array($stored) && !empty($stored)) {
                return array_slice($stored, 0, 5);
            }
        }

        // Extract from H3 question patterns in content
        preg_match_all('/<h3[^>]*>([^<]+\?)<\/h3>\s*(<p[^>]*>[\s\S]*?<\/p>)/i', $content, $matches);
        for ($i = 0; $i < min(count($matches[1]), 5); $i++) {
            $question = trim(strip_tags($matches[1][$i]));
            $answer   = trim(strip_tags($matches[2][$i]));
            if (strlen($question) > 10 && strlen($answer) > 20) {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $faqs;
    }

    /**
     * Detect step-by-step content patterns
     */
    private static function hasStepContent(string $content): bool {
        return preg_match('/<ol[^>]*>/i', $content)
            || preg_match('/step\s*[1-9]/i', $content)
            || preg_match('/how to apply/i', $content);
    }

    /**
     * Extract steps from ordered list or Step N patterns
     */
    private static function extractSteps(string $content): array {
        $steps = [];

        // Try ordered list
        if (preg_match('/<ol[^>]*>([\s\S]*?)<\/ol>/i', $content, $olMatch)) {
            preg_match_all('/<li[^>]*>([\s\S]*?)<\/li>/i', $olMatch[1], $liMatches);
            foreach (array_slice($liMatches[1], 0, 8) as $i => $li) {
                $text = trim(strip_tags($li));
                if (strlen($text) > 10) {
                    $steps[] = ['name' => 'Step ' . ($i + 1), 'text' => $text];
                }
            }
        }

        return $steps;
    }

    /**
     * Try to extract an exam date from article content
     */
    private static function extractExamDate(string $content): ?string {
        // Match patterns like "15 September 2026", "September 15, 2026", "2026-09-15"
        $patterns = [
            '/\b(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(202[5-9])\b/i',
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(202[5-9])\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $dateStr = $m[0];
                $ts = strtotime($dateStr);
                if ($ts && $ts > time()) {
                    return date('Y-m-d', $ts);
                }
            }
        }
        return null;
    }
}
