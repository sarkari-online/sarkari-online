<?php
declare(strict_types=1);

namespace App\Services;

/**
 * HumanizerService
 * Deterministic PHP-level Anti-AI Sanitization Engine.
 * Programmatically guarantees human-grade content across all generated and rewritten articles by:
 * 1. Enforcing verified conversational opening hooks (stripping robotic inverted pyramid / authority statements)
 * 2. Programmatically applying human contractions (eliminating #1 AI detection flag)
 * 3. Scrubbing hyper-formal academic jargon and AI cliches into direct mentor voice
 * 4. Cleaning banned transition markers and passive voice constructs
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
        '/\bcandidates are advised to\b/i'                                   => 'make sure you',
        '/\bcandidates are requested to\b/i'                                 => 'you need to',
        '/\bit is imperative that\b/i'                                       => 'you must',
        '/\bit is mandatory for candidates to\b/i'                           => 'you must',
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
            'ADMIT_CARD'      => "If you registered for {$cleanTitle}, head over to {$cleanHost} right now—your admit card is officially out. ",
            'ANSWER_KEY'      => "Got doubts about a question in your {$cleanTitle} paper? Check {$cleanHost} right away—the provisional answer key is officially live. ",
            'RESULT_CUTOFF'   => "If you appeared for {$cleanTitle}, head over to {$cleanHost} right now—the official scorecard and merit list are live. ",
            'RECRUITMENT'     => "If you're planning to apply for {$cleanTitle}, the official notification is now available on {$cleanHost}. ",
            'SYLLABUS_CHANGE' => "If you're preparing for {$cleanTitle}, review {$cleanHost} immediately—the revised syllabus and pattern are officially released. ",
            default           => "If you're tracking updates for {$cleanTitle}, check {$cleanHost} right away—the latest official bulletin is out. "
        };

        // Match common robotic AI openings (captures optional leading <p> tag to preserve valid HTML)
        $aiOpenerPatterns = [
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
     * Master Humanization Pipeline: Applies all anti-AI layers deterministically.
     */
    public static function humanize(string $content, string $examTitle, string $sourceUrl = '', string $intent = 'recruitment'): string
    {
        if (empty($content)) {
            return $content;
        }

        // 1. Sanitize Opening Hook
        $content = self::sanitizeOpeningHook($content, $examTitle, $sourceUrl, $intent);

        // 2. Enforce Contractions
        $content = self::enforceContractions($content);

        // 3. Scrub Banned Clichés
        $content = self::scrubClichés($content);

        return $content;
    }
}
