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
     * @param string $lifecycleStatus Resolved lifecycle status
     * @return array SEO metadata dictionary
     */
    public function generate(string $title, string $content, string $category = 'exam-results', string $lifecycleStatus = 'active'): array {
        $systemInstruction = <<<PROMPT
You are a top-tier Indian education SEO strategist.
Your task is to generate high-CTR, accurate meta tags, URL slugs, and genuinely helpful FAQ items based on the provided article.
Current Lifecycle State: {$lifecycleStatus}.

SEO SPECIFICATIONS:
- SEO TITLE: Must be between 50 and 60 characters strictly. Front-load the exact exam/authority name and year (e.g. "UPSC Result 2026 Declared: Direct Link"). ALWAYS retain active search milestone verbs ("Declared", "Released", "Out", "Direct Link", "Merit List PDF") and NEVER use passive words like "Status". Do NOT append website name (handled automatically in template).
- META DESCRIPTION: Must be between 130 and 155 characters strictly. Must contain high-volume search queries (e.g. "direct link", "dates", "eligibility", "how to apply/check", "cutoff"), verified official portal name, and an urgent, helpful call to action to maximize Google Search CTR.
- SLUG: 3 to 6 words, lowercase, hyphen-separated, containing only letters and numbers (e.g. "mht-cet-2026-cap-round-4-option-entry").
- KEYWORDS: Array of 6 to 8 high-intent Indian student search queries (e.g. ["MHT CET CAP round 4 option entry", "MHT CET 2026 option form dates", "how to fill MHT CET option form"]).
- FAQS: Generate 2 to 3 genuinely useful FAQs that students or aspirants frequently ask. Do NOT generate obvious or generic fluff. Provide concise, 1-2 sentence direct answers.
- LIFECYCLE-AWARE CTAS: Current lifecycle is '{$lifecycleStatus}'. If lifecycle is 'closed', strictly NEVER use 'Apply Online', 'Apply Now', or 'Registration Open'. Use 'Application Closed', 'Key Dates', or 'Next Stage'.
- NEVER USE TRANSIENT RELATIVE WORDS: Strictly never use "Today", "Tonight", "Tomorrow", "Last Date Today", "Closing Today" in seo_title, meta_description, or excerpt. Always use specific calendar dates (e.g. "Last Date: September 02, 2026") or evergreen search phrasing.
PROMPT;

        $plainContent = substr(strip_tags($content), 0, 2500);

        $userPrompt = <<<USER_PROMPT
Please generate SEO metadata for this article:

ARTICLE TITLE: {$title}
CATEGORY: {$category}
LIFECYCLE STATUS: {$lifecycleStatus}
ARTICLE CONTENT EXCERPT:
{$plainContent}

Return your response strictly as a JSON object with this exact schema:
{
  "seo_title": "High-CTR search meta title (50-60 characters, front-loaded with exam name and action keyword like 'Declared' or 'Direct Link')",
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
