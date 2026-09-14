<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use App\Services\ArticleIntent;
use App\Services\IntentClassifierService;
use App\Services\IntentStructureMap;
use App\Services\SelectionFlowchartRenderer;
use App\Services\SalaryTableRenderer;
use Throwable;

/**
 * ArticleRewriteService
 * Section-scoped editorial rewrite engine designed to elevate article quality,
 * guarantee structural diversity per intent, and eliminate formulaic boilerplate
 * for long-term Google AdSense compliance.
 */
class ArticleRewriteService
{
    private Gemini $gemini;
    private IntentClassifierService $classifier;

    private const FORBIDDEN_PHRASES = [
        'Following the ',
        'streamline the recruitment process',
        'digital governance initiative',
        'crucial step',
        'serves as a testament',
        'in today\'s competitive era',
        'in today\'s digital era',
        'candidates are advised to',
        'it is worth noting that',
        'in conclusion',
        'without further ado',
        'stay tuned',
        'centralized repository',
        'pivotal role in ensuring',
        'candidates are awaiting the release of'
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
        $this->classifier = new IntentClassifierService();
    }

    /**
     * Rewrite an existing article into an intent-structured, high-utility guide.
     */
    public function rewriteArticle(array $article, array $cycleFacts = []): ?array
    {
        $title = $article['title'] ?? '';
        $existingContent = $article['content'] ?? '';
        $sourceName = $article['source_name'] ?? 'Official Examination Authority';
        $sourceUrl = $article['source_url'] ?? '';

        // Classify intent
        $intentEnum = $this->classifier->classify($title, $article['excerpt'] ?? '');
        $intentKey = strtoupper($intentEnum->value);
        $sections = IntentStructureMap::getSections($intentKey);

        Logger::info("ArticleRewriteService: Rewriting article #{$article['id']} with intent [{$intentKey}] (" . count($sections) . " sections)");

        // Extract existing tables or structured blocks to preserve them 100% intact
        $tables = $this->extractTables($existingContent);

        $assembledHtml = '';

        foreach ($sections as $sectionKey) {
            $sectionTitle = IntentStructureMap::getSectionTitle($sectionKey);

            // Special structural handlers
            if ($sectionKey === 'selection_process_flowchart') {
                $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                $assembledHtml .= SelectionFlowchartRenderer::render() . "\n\n";
                continue;
            }

            if ($sectionKey === 'salary_and_perks' && !empty($cycleFacts)) {
                $salaryTable = SalaryTableRenderer::render($cycleFacts);
                if ($salaryTable) {
                    $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                    $assembledHtml .= $salaryTable . "\n\n";
                    continue;
                }
            }

            // Build section-scoped prompt
            $prompt = $this->buildSectionPrompt($title, $sourceName, $intentKey, $sectionKey, $cycleFacts, $existingContent);

            try {
                $response = $this->gemini->generate($prompt, [
                    'stage' => 'editorial_section_rewrite',
                    'temperature' => 0.4
                ]);

                $sectionProse = trim($response['text'] ?? '');
                // Clean any stray markdown headings or forbidden phrases
                $sectionProse = preg_replace('/^#+\s*.*$/m', '', $sectionProse);
                $sectionProse = trim($sectionProse);

                if (!empty($sectionProse) && mb_strlen($sectionProse) >= 40) {
                    $assembledHtml .= "<h2>" . htmlspecialchars($sectionTitle) . "</h2>\n";
                    $assembledHtml .= $this->wrapInParagraphs($sectionProse) . "\n\n";

                    // Intelligently slot preserved tables into relevant sections
                    if (($sectionKey === 'urgency_brief' || $sectionKey === 'role_overview' || $sectionKey === 'result_overview') && !empty($tables)) {
                        $firstTable = array_shift($tables);
                        $assembledHtml .= $firstTable . "\n\n";
                    }
                }
            } catch (Throwable $e) {
                Logger::warning("ArticleRewriteService: Section [{$sectionKey}] failed: " . $e->getMessage());
            }

            usleep(500000); // 0.5s pacing
        }

        // Append any remaining original tables so no factual data is lost
        foreach ($tables as $t) {
            $assembledHtml .= $t . "\n\n";
        }

        // Generate clean, humanized excerpt
        $excerptPrompt = "Write a concise, 2-sentence journalistic summary of this update for Indian exam aspirants: Title: \"{$title}\", Authority: \"{$sourceName}\". Direct, authoritative, no clichés like 'Following the'. Return plain text only.";
        $newExcerpt = $article['excerpt'] ?? '';
        try {
            $exRes = $this->gemini->generate($excerptPrompt, ['stage' => 'excerpt_rewrite', 'temperature' => 0.3]);
            $candidateEx = trim($exRes['text'] ?? '');
            if (!empty($candidateEx) && mb_strlen($candidateEx) >= 30) {
                $newExcerpt = $candidateEx;
            }
        } catch (Throwable $e) {
            // Keep existing excerpt
        }

        return [
            'content' => trim($assembledHtml),
            'excerpt' => $newExcerpt,
            'intent'  => $intentKey
        ];
    }

    /**
     * Build focused, intent-and-section-specific prompt.
     */
    public function buildSectionPrompt(
        string $examTitle,
        string $authority,
        string $intent,
        string $sectionKey,
        array $facts,
        string $rawContext
    ): string {
        $forbiddenStr = implode('", "', self::FORBIDDEN_PHRASES);
        $contextSnippet = mb_substr(strip_tags($rawContext), 0, 800);

        return <<<PROMPT
You are a senior Indian education journalist and competitive exam mentor with 15+ years of experience writing for Sarkari.online.
Write the content for the section: "{$sectionKey}"
For the examination update: "{$examTitle}"
Issuing Authority: {$authority}
Article Intent: {$intent}

BACKGROUND CONTEXT FROM PREVIOUS REPORT:
{$contextSnippet}

EDITORIAL GUIDELINES:
1. Write 2 to 3 substantive paragraphs with high utility for candidates.
2. Maintain natural authority — clear, direct, empathetic, and professional.
3. NEVER use these forbidden machine clichés: ["{$forbiddenStr}"].
4. Grounded Realities: Mention real candidate concerns (e.g. login credentials, server loads, official portal URLs, fee challan, document sizes).
5. Do NOT invent or fabricate any dates or numbers not mentioned in the background context. If a date is not confirmed, simply provide practical guidance on how candidates should prepare.
6. Return clean HTML paragraphs (<p>...</p>) and bullet points (<ul><li>...</li></ul>) where helpful. Do NOT include <h2> or <h1> tags.
PROMPT;
    }

    private function extractTables(string $html): array
    {
        $tables = [];
        if (preg_match_all('/<div class="table-responsive"[^>]*>.*?<\/table>\s*<\/div>|<table[^>]*>.*?<\/table>/is', $html, $matches)) {
            $tables = $matches[0];
        }
        return $tables;
    }

    private function wrapInParagraphs(string $text): string
    {
        if (str_contains($text, '<p>') || str_contains($text, '<ul>')) {
            return $text;
        }

        $parts = preg_split('/\n\s*\n/', $text);
        $html = '';
        foreach ($parts as $p) {
            $clean = trim($p);
            if (!empty($clean)) {
                $html .= "<p>" . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . "</p>\n";
            }
        }
        return $html;
    }
}
