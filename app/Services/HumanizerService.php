<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\ArticleQualityEngine;

/**
 * HumanizerService
 * Deterministic PHP-level Anti-AI Sanitization Engine.
 * Programmatically guarantees human-grade content across all generated and rewritten articles.
 */
class HumanizerService
{
    /**
     * Banned words & robotic clichés regex mappings
     */
    private const CLICHE_MAP = [
        // Complex AI phrases -> Natural plain terms
        '/intermittent connectivity errors/i'                                => 'server traffic slowdowns',
        '/financial commitment required/i'                                   => 'non-refundable fee',
        '/substantiate why a specific question is flawed/i'                  => 'prove why an answer is wrong',
        '/discrepancies in personal information can lead to complications/i' => 'any mismatch at the gate will cause serious trouble',
        '/ensure you have your supporting evidence ready/i'                  => 'keep your textbook proof ready',
        '/digital governance initiative/i'                                  => 'online portal',
        '/streamline the recruitment process/i'                              => 'speed up hiring',
        '/serves as a testament to/i'                                        => 'highlights the importance of',
        '/serves as a testament/i'                                           => 'is proof',
        '/pivotal role in ensuring/i'                                        => 'key role in',
        '/pivotal role/i'                                                    => 'key role',
        '/pivotal/i'                                                         => 'vital',
        '/crucial step for candidates/i'                                     => 'essential step',
        '/crucial step/i'                                                    => 'key step',
        '/a crucial/i'                                                       => 'a key',
        '/is crucial/i'                                                      => 'is essential',
        '/crucial/i'                                                         => 'critical',
        '/delve into/i'                                                      => 'look at',
        '/delve deeper/i'                                                    => 'look closer',
        '/delve/i'                                                           => 'explore',
        '/in today\'s digital era/i'                                         => 'today',
        '/in today\'s competitive era/i'                                     => 'in this competitive exam',
        '/without further ado/i'                                             => 'straight to the point',
        '/stay tuned/i'                                                      => 'keep checking back',
        '/centralized repository/i'                                          => 'official website',
        '/it is worth noting that/i'                                         => 'note that',
        '/it is important to note that/i'                                    => 'remember that',
        '/in conclusion/i'                                                   => 'finally',
        '/\bmoreover\b/i'                                                    => 'also',
        '/\bfurthermore\b/i'                                                 => 'also',
        '/\butilize\b/i'                                                     => 'use',
        '/\butilizing\b/i'                                                   => 'using',
        '/\butilized\b/i'                                                    => 'used',
        '/\bplethora of\b/i'                                                 => 'many',
        '/\btapestry of\b/i'                                                 => 'range of',
        '/\bbeacon of hope\b/i'                                              => 'top choice',
        '/\bparamount importance\b/i'                                        => 'top priority',
        '/\bparamount\b/i'                                                   => 'vital',
        '/\bmultifaceted approach\b/i'                                       => 'step-by-step method',
        '/\bmultifaceted\b/i'                                                => 'multi-step',
        '/\bfosters transparency\b/i'                                        => 'ensures fair grading',
        '/\beliminate the redundancy\b/i'                                    => 'save time',
        '/\btestament to the\b/i'                                            => 'proof of the',
        '/\bseamless experience\b/i'                                         => 'smooth process',
        '/\bcomprehensive guide\b/i'                                         => 'complete walkthrough',
        '/\bembark on\b/i'                                                   => 'start',
        '/\bembarks on\b/i'                                                  => 'starts',
        '/\bcandidates are advised to\b/i'                                   => 'candidates should',
        '/\bcandidates are requested to\b/i'                                 => 'candidates are required to',
        '/\bit is imperative that\b/i'                                       => 'regulations require that',
        '/\bit is mandatory for candidates to\b/i'                           => 'candidates must',
    ];

    /**
     * Contraction mappings to inject natural human rhythm
     */
    private const CONTRACTION_MAP = [
        '/\bDo not\b/'     => "Don't",
        '/\bdo not\b/'     => "don't",
        '/\bCannot\b/'     => "Can't",
        '/\bcannot\b/'     => "can't",
        '/\bCan not\b/'    => "Can't",
        '/\bcan not\b/'    => "can't",
        '/\bYou will\b/'   => "You'll",
        '/\byou will\b/'   => "you'll",
        '/\bIt is\b/'      => "It's",
        '/\bit is\b/'      => "it's",
        '/\bThere is\b/'   => "There's",
        '/\bthere is\b/'   => "there's",
        '/\bAre not\b/'    => "Aren't",
        '/\bare not\b/'    => "aren't",
        '/\bWill not\b/'   => "Won't",
        '/\bwill not\b/'   => "won't",
        '/\bHave not\b/'   => "Haven't",
        '/\bhave not\b/'   => "haven't",
        '/\bWe have\b/'    => "We've",
        '/\bwe have\b/'    => "we've",
        '/\bYou have\b/'   => "You've",
        '/\byou have\b/'   => "you've",
        '/\bIs not\b/'     => "Isn't",
        '/\bis not\b/'     => "isn't",
        '/\bWas not\b/'    => "Wasn't",
        '/\bwas not\b/'    => "wasn't",
        '/\bWere not\b/'   => "Weren't",
        '/\bwere not\b/'   => "weren't",
        '/\bDid not\b/'    => "Didn't",
        '/\bdid not\b/'    => "didn't",
        '/\bDoes not\b/'   => "Doesn't",
        '/\bdoes not\b/'   => "doesn't",
        '/\bShould not\b/' => "Shouldn't",
        '/\bshould not\b/' => "shouldn't",
        '/\bCould not\b/'  => "Couldn't",
        '/\bcould not\b/'  => "couldn't",
        '/\bWould not\b/'  => "Wouldn't",
        '/\bwould not\b/'  => "wouldn't",
    ];

    /**
     * Programmatically enforce natural contractions across text.
     */
    public static function enforceContractions(string $text): string
    {
        return (string)preg_replace(array_keys(self::CONTRACTION_MAP), array_values(self::CONTRACTION_MAP), $text);
    }

    /**
     * Programmatically scrub hyper-formal academic jargon into human mentor phrasing.
     */
    public static function scrubClichés(string $text): string
    {
        return (string)preg_replace(array_keys(self::CLICHE_MAP), array_values(self::CLICHE_MAP), $text);
    }

    /**
     * Replaces formulaic AI opening sentences with conversational aspirant-first hooks.
     */
    public static function sanitizeOpeningHook(string $prose, string $examTitle, string $sourceUrl = '', string $intent = 'recruitment'): string
    {
        $cleanHost = !empty($sourceUrl) ? preg_replace('/^www\./i', '', parse_url($sourceUrl, PHP_URL_HOST) ?? '') : 'the official portal';
        if (empty($cleanHost)) {
            $cleanHost = 'the official portal';
        }

        // Clean exam title of subtitle tags for conversational flow
        $cleanTitle = trim(preg_replace('/\s*[:\-–|].*$/', '', $examTitle));

        $intentUpper = strtoupper($intent);
        $replacementHook = match ($intentUpper) {
            'ADMIT_CARD'      => "Official admit cards and examination venue allocations for {$cleanTitle} are accessible via {$cleanHost}. ",
            'ANSWER_KEY'      => "Provisional answer keys and recorded candidate response schedules for {$cleanTitle} are published on {$cleanHost}. ",
            'RESULT_CUTOFF'   => "Official scorecards and category-wise qualifying thresholds for {$cleanTitle} are issued on {$cleanHost}. ",
            'RECRUITMENT'     => "Statutory recruitment details and application instructions for {$cleanTitle} are notified on {$cleanHost}. ",
            'SYLLABUS_CHANGE' => "The revised examination syllabus and evaluation pattern for {$cleanTitle} are notified on {$cleanHost}. ",
            default           => "Official updates and schedule parameters for {$cleanTitle} are published on {$cleanHost}. "
        };

        // Match common robotic AI openings (captures optional leading <p> tag to preserve valid HTML)
        $aiOpenerPatterns = [
            '/^(<p>)?(?:If you(?:\'ve| have) been waiting for [^\.\n]+,?\s*the wait is (?:finally )?over[—–-—\.\s]*)/i',
            '/^(<p>)?(?:The wait for [^—–-—\.\n]+(?:is finally over|has concluded|is over)[—–-—\.\s]*)/i',
            '/^(<p>)?(?:The [A-Z][A-Za-z\s]+ has (?:released|announced|declared|published|issued)[^\.\n]+\.\s*)/i',
            '/^(<p>)?(?:\bFollowing the [^\.\n]+\.\s*)/i',
            '/^(<p>)?(?:\bAs per the latest [^\.\n]+\.\s*)/i',
            '/^(<p>)?(?:\bIn a major update[^\.\n]+\.\s*)/i',
            '/^(<p>)?(?:\bIn an important development[^\.\n]+\.\s*)/i',
            '/^(<p>)?(?:\bIn recent developments[^\.\n]+\.\s*)/i',
        ];

        foreach ($aiOpenerPatterns as $pattern) {
            if (preg_match($pattern, $prose, $m)) {
                $leadP = !empty($m[1]) ? $m[1] : '';
                $prose = preg_replace($pattern, $leadP . $replacementHook, $prose, 1);
                break;
            }
        }

        return $prose;
    }

    /**
     * Deduplicate paragraphs with > 70% textual similarity within the same article.
     */
    public static function deduplicateParagraphs(string $html, float $threshold = 0.70): array
    {
        $duplicatesRemoved = 0;
        if (!preg_match_all('/<p(?:\s+[^>]*)?>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) {
            return ['html' => $html, 'duplicates_removed' => 0];
        }

        $seenParagraphs = [];

        foreach ($matches as $match) {
            $fullTag = $match[0];
            $text = trim(strip_tags($match[1]));
            if (mb_strlen($text) < 40) {
                continue;
            }

            $isDuplicate = false;
            foreach ($seenParagraphs as $seenText) {
                similar_text(strtolower($text), strtolower($seenText), $percent);
                if ($percent / 100.0 >= $threshold) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                $html = str_replace($fullTag, '', $html);
                $duplicatesRemoved++;
            } else {
                $seenParagraphs[] = $text;
            }
        }

        return [
            'html'               => $html,
            'duplicates_removed' => $duplicatesRemoved
        ];
    }

    private const ENCYCLOPEDIC_OPENER_PATTERN =
        '/<p>\s*([A-Z][A-Za-z0-9\s\-]{1,60})\s+is\s+an?\s+[^.]{1,80}?\s+and\s+an?\s+[^.]{1,80}?\.\s*/u';

    private const ANTITHESIS_PATTERNS = [
        '/\b[\w\s]{1,50}?\bisn\'?t\s+just\s+(?:about\s+)?[^;]{1,80};\s*it\'?s\s+(?:about\s+)?[^.!?]{1,80}[.!?]/iu',
        '/\bit\'?s\s+not\s+just\s+an?\s+[\w\s]{1,40};\s*it\'?s\s+an?\s+[\w\s]{1,60}[.!?]/iu',
        '/\bnot\s+just\s+[\w\s]{1,40};\s*(?:it\'?s|it\s+is)\s+[\w\s]{1,60}[.!?]/iu',
        '/\bit\'?s\s+a\s+(?:straightforward|simple)\s+but\s+rigorous\s+process[^.!?]*[.!?]/iu',
        '/\btests?\s+both\s+your\s+technical\s+brain\s+and\s+your\s+attention\s+to\s+detail[^.!?]*[.!?]/iu',
    ];

    private const MOTIVATIONAL_CLICHE_PATTERNS = [
        '/\bstay\s+focused,?\s+stay\s+updated\b[^.!?]*[.!?]/iu',
        '/\bthe\s+competition\s+is\s+fierce\b[^.!?]*[.!?]/iu',
        '/\b(?:be\s+)?sharp\s+with\s+your\s+(?:technical\s+)?fundamentals\b[^.!?]*[.!?]/iu',
        '/\bbackbone\s+of\s+india\'?s\s+[\w\s]{1,30}[.!?]/iu',
        '/\bdon\'?t\s+ignore\s+the\s+fine\s+print\b(?![^.!?]*\d)[^.!?]*[.!?]/iu',
        '/\bkeep\s+an?\s+eye\s+on\s+the\s+official\s+portal\b(?![^.!?]*\d)[^.!?]*[.!?]/iu',
        '/(?:once the|when the)[^.!?]+objection window[^.!?]+(?:clock starts ticking|clock is ticking)[^.!?]*[.!?]/iu',
        '/\byou(?:\'ve| have) only got \d+ hours to make your move[^.!?]*[.!?]/iu',
        '/\bdon\'?t wait until the (?:clock hits the )?final hour[^.!?]*[.!?]/iu',
        '/\bthe clock starts ticking immediately[^.!?]*[.!?]/iu',
        '/\bif you miss that \d+-hour challenge window[^.!?]*[.!?]/iu',
        '/\bit\'?s a hard deadline, and the board doesn\'?t accept excuses[^.!?]*[.!?]/iu',
        '/\bthe portal won\'?t reopen[^.!?]*[.!?]/iu',
        '/\b(?:when thousands of aspirants hit the portal|the servers will crawl|site traffic gets crazy)[^.!?]*[.!?]/iu',
        '/\bif the page hangs, try an incognito window or clear your browser cache[^.!?]*[.!?]/iu',
        '/\btry incognito or private browsing mode[^.!?]*[.!?]/iu',
        '/\bit\'?s a quick fix that often clears those annoying session timeouts[^.!?]*[.!?]/iu',
        '/\bhave your application number and date of birth saved in a notepad file[^.!?]*[.!?]/iu',
        '/\btechnical glitches happen when thousands of aspirants log in at once[^.!?]*[.!?]/iu',
        '/\bserver down\?\s*try incognito[^.!?]*[.!?]/iu',
        '/\bdon\'?t panic if the page hangs;\s*just refresh and try again[^.!?]*[.!?]/iu',
        '/\bserver loads spike during these windows[^.!?]*[.!?]/iu',
        '/\bif your proof is weak, they won\'?t even look at it[^.!?]*[.!?]/iu',
        '/\bit\'?s a red flag[.!]?/iu',
        '/\bdo it now, not when the heat is on[^.!?]*[.!?]/iu',
        '/\byou don\'?t want these administrative errors haunting your results later[^.!?]*[.!?]/iu',
        '/\bkeep your evidence ready before you start[^.!?]*[.!?]/iu',
        '/\bensure your document scans are small enough to upload quickly[^.!?]*[.!?]/iu',
        '/\bkeep your documents ready before you even log in to avoid upload failures[^.!?]*[.!?]/iu',
        '/\byou(?:\'ve| have) worked too hard for this[^.!?]*[.!?]/iu',
        '/\bdon\'?t let a technical hiccup or a minor typo derail your progress[^.!?]*[.!?]/iu',
        '/\bstay sharp, move fast, and get your objections in[^.!?]*[.!?]/iu',
        '/\bhere\'?s what you should keep in mind to stay ahead:\s*/iu',
        '/\bhere\'?s the ground reality:\s*the servers will crawl\.\s*/iu',
        '/\byou don\'?t want to leave this until the final hour[^.!?]*[.!?]/iu',
        '/\bthe wait is (?:finally )?over[.!]?/iu',
        '/\bIt\'?s time to shift your focus toward[^.!?]*[.!?]/iu',
        '/\bfor comparative insights[^.!?]*[.!?]/iu',
        '/\bfor a comprehensive (?:analysis|understanding|overview)[^.!?]*[.!?]/iu',
        '/\bUnderstanding [^.!?]+ is a critical skill for (?:any )?(?:student|aspirant)[^.!?]*[.!?]/iu',
        '/\bis a critical skill for (?:any )?(?:student|aspirant|candidate)[^.!?]*[.!?]/iu',
        '/\bpreparing for high-stakes (?:assessments|examinations|exams)[^.!?]*[.!?]/iu',
        '/\bYou must ensure you\'?re well-rested and prepared for[^.!?]*[.!?]/iu',
        '/\bto avoid any confusion during your study sessions[^.!?]*[.!?]/iu',
        '/\bLook for the [\'"]?(?:Notifications|Examination|Latest Announcements)[\'"]? tab on the homepage[^.!?]*[.!?]/iu',
        '/\bThe PDF will open in a new tab;?\s*check [^.!?]+[.!?]/iu',
        '/\bDownload and save the file for (?:your )?(?:future )?reference[^.!?]*[.!?]/iu',
        '/\bAs you prepare, remember that board exams require strict adherence to protocols[^.!?]*[.!?]/iu',
        '/\brequire strict adherence to protocols[^.!?]*[.!?]/iu',
        '/\bEnsure your stationery is kept in a transparent pouch[^.!?]*[.!?]/iu',
        '/\bto avoid any issues during the security check[^.!?]*[.!?]/iu',
        '/\bDon\'?t bring any electronic gadgets, including smartwatches or mobile phones, as these are strictly prohibited inside the examination hall[^.!?]*[.!?]/iu',
        '/\bHow do I handle exam stress\?[^.!?]*[.!?]\s*Focus on your revision notes and maintain a consistent sleep schedule[^.!?]*[.!?]/iu',
        '/\bmaintain a consistent sleep schedule leading up to[^.!?]*[.!?]/iu',
        '/\bAll information provided is based on the official circulars released by[^.!?]*[.!?]/iu',
        '/\bYou should cross-check any updates directly at https?:\/\/[^\s]+ to ensure you\'?re acting on the latest verified data[^.!?]*[.!?]/iu',
    ];

    /**
     * Scrub encyclopedic openers, antithesis formulas, and motivational clichés deterministically.
     */
    public static function scrubClichePatterns(string $html): array
    {
        $removedSentences = [];
        $cleaned = $html;

        // Pattern A: strip the encyclopedic opener, leave the rest of the paragraph intact
        $cleaned = preg_replace_callback(self::ENCYCLOPEDIC_OPENER_PATTERN, function ($m) use (&$removedSentences) {
            $removedSentences[] = ['type' => 'encyclopedic_opener', 'text' => trim($m[0])];
            return '<p>';
        }, $cleaned);

        // Pattern B: strip antithesis clauses entirely
        foreach (self::ANTITHESIS_PATTERNS as $pattern) {
            $cleaned = preg_replace_callback($pattern, function ($m) use (&$removedSentences) {
                $removedSentences[] = ['type' => 'antithesis_cliche', 'text' => trim($m[0])];
                return '';
            }, $cleaned);
        }

        // Pattern C: strip motivational bookends
        foreach (self::MOTIVATIONAL_CLICHE_PATTERNS as $pattern) {
            $cleaned = preg_replace_callback($pattern, function ($m) use (&$removedSentences) {
                $removedSentences[] = ['type' => 'motivational_cliche', 'text' => trim($m[0])];
                return '';
            }, $cleaned);
        }

        // Cleanup: collapse resulting empty <p></p>, double spaces, orphaned punctuation
        $cleaned = preg_replace('/<p>\s*<\/p>/u', '', $cleaned);
        $cleaned = preg_replace('/\s{2,}/u', ' ', $cleaned);
        $cleaned = preg_replace('/<p>\s*([.!?,;:])/u', '<p>', $cleaned);

        return ['html' => trim($cleaned), 'removed' => $removedSentences];
    }

    /**
     * Measure sentence length variance (Std Dev < 3.0) to detect uniform robotic cadence.
     */
    public static function detectUniformCadenceParagraphs(string $html): array
    {
        $flagged = [];
        if (!preg_match_all('/<p(?:\s+[^>]*)?>(.*?)<\/p>/is', $html, $matches)) {
            return [];
        }

        foreach ($matches[1] as $index => $body) {
            $text = trim(strip_tags($body));
            if ($text === '') {
                continue;
            }

            $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', $text);
            $sentences = array_filter($sentences, fn ($s) => trim($s) !== '');
            if (count($sentences) < 4) {
                continue;
            }

            $lengths = array_map(fn ($s) => str_word_count($s), $sentences);
            $mean = array_sum($lengths) / count($lengths);
            $variance = array_sum(array_map(fn ($l) => ($l - $mean) ** 2, $lengths)) / count($lengths);
            $stdDev = sqrt($variance);

            if ($stdDev < 3.0) {
                $flagged[] = [
                    'paragraph_index' => $index,
                    'sentence_count' => count($sentences),
                    'lengths' => $lengths,
                    'std_dev' => round($stdDev, 2),
                    'text' => $text,
                ];
            }
        }

        return $flagged;
    }

    /**
     * Build small targeted prompt for rhythm rewrite (preserving all facts).
     */
    public static function buildRhythmRewritePrompt(array $flaggedParagraph, array $verifiedFacts): string
    {
        $originalText = $flaggedParagraph['text'];
        $factsBlock = json_encode($verifiedFacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Rewrite this paragraph to vary sentence length naturally — mix short, punchy sentences (3-8 words) with longer explanatory ones (15-25 words). 
Do not add, remove, or alter any fact. Do not add new claims not present in the original text or in these verified facts: {$factsBlock}

Original paragraph:
{$originalText}

Rules:
- Keep every factual detail exactly as stated (dates, numbers, names, URLs).
- Do not use semicolon-antithesis constructions ("not just X; it's Y").
- Do not add generic motivational filler ("stay focused, stay updated").
- Return ONLY the rewritten paragraph text, no preamble.
PROMPT;
    }

    /**
     * Converts dense, repetitive qualifications and selection paragraphs into structured, candidate-friendly bullet points.
     */
    public static function structureDenseProseLists(string $html): string
    {
        // Pattern 1: Convert paragraphs containing Degree/Qualifications + Age Limit into clean <ul><li> lists
        $html = preg_replace_callback('/<p>(?=.*?(?:Bachelor\'?s\s+degree|B\.E\.|B\.Tech|graduation|diploma|10\+2|matriculation))(?=.*?(?:age\s+limit|years\s+old|minimum\s+age|maximum\s+age))(.*?)<\/p>/is', function($m) {
            $text = trim($m[1]);
            if (str_contains($text, '<ul>') || str_contains($text, '<ol>') || str_contains($text, '<table>')) {
                return $m[0];
            }

            $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $text);
            $sentences = array_filter(array_map('trim', $sentences), fn($s) => !empty($s));
            if (count($sentences) < 2) {
                return $m[0];
            }

            $listItems = '';
            foreach ($sentences as $s) {
                $cleanSentence = rtrim($s, '.');
                $listItems .= "<li>{$cleanSentence}.</li>\n";
            }

            return "<ul class=\"eligibility-breakdown\" style=\"margin: 1rem 0 1.25rem 1.25rem; line-height: 1.7;\">\n{$listItems}</ul>";
        }, $html);

        // Pattern 2: Convert paragraphs explaining selection stages into clean bullet points
        $html = preg_replace_callback('/<p>(?=.*?(?:Computer-Based\s+Test|written\s+exam|Tier-1|CBT|Prelims))(?=.*?(?:interview|skill\s+test|document\s+verification|physical\s+test))(.*?)<\/p>/is', function($m) {
            $text = trim($m[1]);
            if (str_contains($text, '<ul>') || str_contains($text, '<ol>') || str_contains($text, '<table>')) {
                return $m[0];
            }

            $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $text);
            $sentences = array_filter(array_map('trim', $sentences), fn($s) => !empty($s));
            if (count($sentences) < 2) {
                return $m[0];
            }

            $listItems = '';
            foreach ($sentences as $s) {
                $cleanSentence = rtrim($s, '.');
                $listItems .= "<li>{$cleanSentence}.</li>\n";
            }

            return "<ul class=\"selection-stage-breakdown\" style=\"margin: 1rem 0 1.25rem 1.25rem; line-height: 1.7;\">\n{$listItems}</ul>";
        }, $html);

        return $html;
    }

    /**
     * Master Humanization Pipeline: delegates to ArticleQualityEngine for structural cleanliness,
     * then applies rhythm, contractions, and list structuring.
     */
    public static function humanize(string $content, string $examTitle, string $sourceUrl = '', string $intent = 'recruitment'): string
    {
        if (empty($content)) {
            return $content;
        }

        // 1. Unified ArticleQualityEngine structural refactoring
        $qualityResult = ArticleQualityEngine::refactorContent($content, $examTitle, $sourceUrl, $intent);
        $content = $qualityResult['content'];

        // 2. Structure Dense Prose Lists
        $content = self::structureDenseProseLists($content);

        return $content;
    }
}
