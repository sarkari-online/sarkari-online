<?php
/**
 * EduPulse - Content Editor AI Service
 * Refines editorial tone, formats structured tables/steps, improves student readability,
 * and eliminates passive/repetitive language while strictly preserving factual data.
 */

namespace App\AI;

use Exception;

class ContentEditor {

    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Polish and edit an article
     * 
     * @param string $title Article Title
     * @param string $content Article Body Content (HTML)
     * @param string $category Category Slug
     * @return array Polished content and editorial summary
     */
    public function polish(string $title, string $content, string $category = 'exam-results'): array {
        $systemInstruction = <<<PROMPT
You are the Managing Editor for Sarkari.online, an independent Indian education and recruitment platform.
Your job is to polish, format, and enhance draft article content for maximum student clarity and mobile engagement.

EDITORIAL POLISHING GUIDELINES:
1. PRESERVE EVERY FACT: Do not modify numbers, dates, percentile cutoffs, authority names, or reference codes.
2. ENHANCE FORMATTING: Convert dense paragraphs into clean sub-sections (<h2>, <h3>), scannable bullet points (<ul>), numbered step sequences (<ol>), or comparison tables.
3. REMOVE JARGON & FLUFF: Strip repetitive filler words, sensationalist clickbait words ("massive", "shocking"), and vague speculations.
4. MOBILE READABILITY: Keep paragraphs concise (2 to 4 sentences maximum).
PROMPT;

        $userPrompt = <<<USER_PROMPT
Please polish and editorially enhance this draft article:

HEADLINE: {$title}
CATEGORY: {$category}

RAW CONTENT:
{$content}

Return your response strictly as a JSON object with this exact schema:
{
  "edited_title": "Polished headline",
  "edited_content": "Full enhanced HTML content with clean markup",
  "readability_score": 92,
  "improvements_made": [
    "Converted registration steps into an ordered list",
    "Streamlined paragraph flow for mobile readers"
  ]
}
USER_PROMPT;

        $response = $this->gemini->generateJson($userPrompt, [
            'stage' => 'content_editing',
            'system_instruction' => $systemInstruction,
            'temperature' => 0.15
        ]);

        $data = $response['data'];

        return [
            'edited_title' => $data['edited_title'] ?? $title,
            'edited_content' => $data['edited_content'] ?? $content,
            'readability_score' => (int)($data['readability_score'] ?? 85),
            'improvements_made' => $data['improvements_made'] ?? []
        ];
    }
}
