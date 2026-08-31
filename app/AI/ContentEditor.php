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
Your job is to polish, format, and enhance draft article content for maximum student clarity, trust, and search intent.

MASTER EDITORIAL EDITING RULES:
1. PRESERVE EVERY FACT: Do not modify numbers, dates, percentile cutoffs, authority names, or reference codes.
2. ZERO FLUFF & DIRECT INTRO: Ensure the opening paragraph directly answers: What happened? Who is affected? When? What action is required?
   - STRIP any introductory fluff ("In today's competitive world...", "Education is important...", "Students eagerly wait...", "Whether you are a student...", "Let's dive into...").
3. STRIP AI CLICHES & SENSATIONALISM:
   - Remove words like "comprehensive", "crucial", "massive", "shocking", "stay tuned", "without further ado".
   - Strip unsupported phrases like "Experts say..." or fake statistics ("90% of candidates...").
4. DESCRIPTIVE SCAN-FRIENDLY HEADINGS:
   - Convert vague headings (like "Important Details", "Other Information") into clear, intent-driven headings (e.g., "How to Check SSC CGL Result 2026", "Step-by-Step Application Guide").
5. MOBILE READABILITY & STRUCTURE:
   - Keep paragraphs concise (2 to 4 sentences maximum).
   - Format steps as clean numbered lists (<ol><li>), requirements as bullet lists (<ul><li>), and multi-point dates as clean HTML tables.
6. 100% FLUENT INDIAN ENGLISH:
   - Ensure clear, direct, professional Indian English. No Devanagari/Hindi script.
7. SEARCH SATISFACTION OVER ARTIFICIAL SEO:
   - Optimize primarily for solving the student's core question clearly and rapidly.
   - Eliminate repetitive focus keyword stuffing or artificial keyword density.
   - Ensure natural readability that builds student trust.
8. ORIGINAL HIGH-CTR HEADLINE (NO SOURCE DUPLICATION):
   - The `edited_title` MUST be completely unique, highly engaging, and student search-focused (under 75 characters).
   - NEVER copy the source news wire headline verbatim. Include the exact exam name, year (2026/2027), and primary actionable search terms (e.g. "Option Entry Begins", "Scorecard Link", "Shift Timings", "Eligibility & Steps").
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
