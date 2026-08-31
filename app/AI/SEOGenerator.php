<?php
/**
 * EduPulse - SEO Generator AI Service
 * Generates search-optimized metadata, concise slugs, and authoritative student FAQs.
 */

namespace App\AI;

use Exception;

class SEOGenerator {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Generate complete SEO metadata package
     * 
     * @param string $title Article Title
     * @param string $content Article Body Content
     * @param string $category Category slug
     * @return array SEO metadata dictionary
     */
    public function generate(string $title, string $content, string $category = 'exam-results'): array {
        $systemInstruction = <<<PROMPT
You are a top-tier Indian education SEO strategist.
Your task is to generate high-CTR, accurate meta tags, URL slugs, and genuinely helpful FAQ items based on the provided article.

SEO SPECIFICATIONS:
- SEO TITLE: Must be between 45 and 58 characters strictly. Must include the exact examination name, year, and highest-volume search intent keywords (e.g. "MHT CET 2026 CAP Round 4 Option Entry: Dates & Steps"). Do NOT add website name (handled automatically).
- META DESCRIPTION: Must be between 130 and 155 characters strictly. Must contain high-volume search queries (e.g. "direct link", "dates", "eligibility", "how to apply/check", "cutoff"), verified official portal name, and an urgent, helpful call to action to maximize Google Search CTR.
- SLUG: 3 to 6 words, lowercase, hyphen-separated, containing only letters and numbers (e.g. "mht-cet-2026-cap-round-4-option-entry").
- KEYWORDS: Array of 6 to 8 high-intent Indian student search queries (e.g. ["MHT CET CAP round 4 option entry", "MHT CET 2026 option form dates", "how to fill MHT CET option form"]).
- FAQS: Generate 2 to 3 genuinely useful FAQs that students or aspirants frequently ask. Do NOT generate obvious or generic fluff. Provide concise, 1-2 sentence direct answers.
PROMPT;

        $plainContent = substr(strip_tags($content), 0, 2500);

        $userPrompt = <<<USER_PROMPT
Please generate SEO metadata for this article:

ARTICLE TITLE: {$title}
CATEGORY: {$category}
ARTICLE CONTENT EXCERPT:
{$plainContent}

Return your response strictly as a JSON object with this exact schema:
{
  "seo_title": "Optimized meta title under 55 characters",
  "meta_description": "Compelling meta description between 120-155 characters",
  "slug_suggestion": "clean-kebab-case-slug",
  "excerpt": "Lead excerpt for card previews under 160 characters",
  "target_keywords": ["keyword 1", "keyword 2", "keyword 3", "keyword 4", "keyword 5", "keyword 6"],
  "faqs": [
    {
      "question": "Direct candidate question?",
      "answer": "Accurate, concise factual answer."
    }
  ]
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'seo_generation',
            'system_instruction' => $systemInstruction,
            'temperature' => 0.2
        ]);

        $data = $response['data'];

        $required = ['seo_title', 'meta_description', 'slug_suggestion', 'excerpt'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("SEOGenerator output missing field: {$field}");
            }
        }

        return $data;
    }
}
