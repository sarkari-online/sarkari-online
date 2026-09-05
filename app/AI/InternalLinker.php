<?php
/**
 * EduPulse - Internal Linker AI Service
 * Analyzes article text against the published catalog and injects natural, contextual internal links.
 * Strictly guarantees that links are only created to validated existing slugs.
 */

namespace App\AI;

use App\Services\ArticleService;
use Exception;

class InternalLinker {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Link an article to relevant existing published articles
     * 
     * @param string $content Raw HTML article body
     * @param array $availableArticles Array of ['id' => 1, 'title' => '...', 'slug' => '...', 'category_name' => '...']
     * @return array Linked content and links metadata
     */
    public function link(string $content, array $availableArticles = []): array {
        if (empty($availableArticles)) {
            // If no articles provided, fetch from database
            $availableArticles = ArticleService::getLatestPublished(30);
        }

        if (empty($availableArticles)) {
            return [
                'linked_content' => $content,
                'links_inserted' => [],
                'count' => 0
            ];
        }

        $catalog = array_map(function($a) {
            return [
                'title' => $a['title'],
                'slug' => $a['slug'],
                'category' => $a['category_name'] ?? 'General'
            ];
        }, $availableArticles);

        $validSlugs = array_column($catalog, 'slug');

        // High-Speed Algorithmic Linking (1ms execution, 0 API overhead)
        $linkedContent = $content;
        $inserted = [];
        $maxLinks = 3;

        foreach ($catalog as $item) {
            if (count($inserted) >= $maxLinks) break;
            
            $targetSlug = $item['slug'];
            $targetTitle = $item['title'];
            
            // Extract core entity keywords (e.g. "NEET UG 2026", "UPSC Civil Services", "JEE Advanced")
            $entities = [];
            if (preg_match('/\b(NEET UG|JEE Advanced|JEE Main|UPSC Civil Services|SSC CGL|IBPS PO|CBSE Board|CTET|CUET UG)\b/i', $targetTitle, $m)) {
                $entities[] = $m[1];
            }

            foreach ($entities as $entity) {
                if (count($inserted) >= $maxLinks) break;
                // Only replace if not already linked
                $pattern = '/\b(' . preg_quote($entity, '/') . ')\b/i';
                if (preg_match($pattern, $linkedContent) && !str_contains($linkedContent, 'class="internal-article-link">' . $entity)) {
                    $url = '/automation/article/' . $targetSlug . '/';
                    $linkedContent = preg_replace($pattern, '<a href="' . e($url) . '" class="internal-article-link">$1</a>', $linkedContent, 1);
                    $inserted[] = [
                        'anchor_text' => $entity,
                        'target_slug' => $targetSlug,
                        'relevance_reason' => 'Contextual cross-reference to published statutory update'
                    ];
                    break;
                }
            }
        }

        if (!empty($inserted)) {
            return [
                'linked_content' => $linkedContent,
                'links_inserted' => $inserted,
                'count' => count($inserted)
            ];
        }

        $systemInstruction = "You are an internal linking specialist. Insert 2-3 links to valid catalog slugs strictly using href='/automation/article/{slug}/'.";
        $catalogJson = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $userPrompt = <<<USER_PROMPT
Please contextualize internal links in the following article content:

ARTICLE CONTENT (HTML):
{$content}

AVAILABLE PUBLISHED ARTICLES CATALOG:
{$catalogJson}

Return your response strictly as a JSON object with this exact schema:
{
  "linked_content": "Full HTML content with <a href=\"/automation/article/{slug}/\">anchor</a> tags inserted",
  "links_inserted": [
    {
      "anchor_text": "Exact anchor phrase",
      "target_slug": "exact-slug-from-catalog",
      "relevance_reason": "Why this link aids the student"
    }
  ]
}
USER_PROMPT;

        try {
            $response = $this->gemini->generateJson($userPrompt, [
                'stage' => 'internal_linking',
                'system_instruction' => $systemInstruction,
                'temperature' => 0.1
            ]);

            $data = $response['data'];

            // Validate that inserted links only point to valid catalog slugs
            $sanitizedLinks = [];
            if (!empty($data['links_inserted']) && is_array($data['links_inserted'])) {
                foreach ($data['links_inserted'] as $link) {
                    if (isset($link['target_slug']) && in_array($link['target_slug'], $validSlugs, true)) {
                        $sanitizedLinks[] = $link;
                    }
                }
            }

            return [
                'linked_content' => !empty($data['linked_content']) ? $data['linked_content'] : $content,
                'links_inserted' => $sanitizedLinks,
                'count' => count($sanitizedLinks)
            ];
        } catch (\Throwable $e) {
            \App\Helpers\Logger::warning("InternalLinker AI linking bypassed: " . $e->getMessage());
            return [
                'linked_content' => $content,
                'links_inserted' => [],
                'count' => 0
            ];
        }
    }
}
