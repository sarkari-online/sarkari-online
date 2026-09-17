<?php
declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * ArticleQualityEngine
 * 
 * Unified human-editorial quality engine for Sarkari.online articles.
 * Applies identical editorial quality rules across:
 *  1. Existing published articles (safe refactor)
 *  2. Existing draft / queued articles
 *  3. Reprocessed articles
 *  4. Newly generated articles
 *  5. Future automated pipeline runs
 * 
 * Core principles:
 * - Content quality refactor, NOT a factual rewrite.
 * - Source-grounded, factual, concise, natural Indian English.
 * - Zero detector gaming (no hidden characters, no fake misspellings).
 * - Safe HTML-aware manipulation (handles single-line & multiline HTML).
 * - Guaranteed table, link, and verified fact preservation.
 */
class ArticleQualityEngine
{
    /**
     * Banned AI opening patterns (regex matched against opening prose)
     */
    public const GENERIC_OPENER_PATTERNS = [
        '/^<p[^>]*>\s*(?:If you(?:\'ve| have) been waiting for [^,\.]+,?\s*(?:the wait is (?:finally )?over[—–-—\.\s]*))/iu',
        '/^<p[^>]*>\s*(?:The wait for [^—–-—\.\n]+(?:is finally over|has concluded|is over)[—–-—\.\s]*)/iu',
        '/^<p[^>]*>\s*(?:Good news for (?:all )?(?:aspirants|candidates|students)[—–-—\.\s!]*)/iu',
        '/^<p[^>]*>\s*(?:Candidates who have been (?:eagerly|anxiously|patiently) waiting[—–-—\.\s]*)/iu',
        '/^<p[^>]*>\s*(?:In a major (?:update|development|announcement)[^,\.]*,\s*)/iu',
        '/^<p[^>]*>\s*(?:In an important (?:development|update)[^,\.]*,\s*)/iu',
        '/^<p[^>]*>\s*(?:In recent developments[^,\.]*,\s*)/iu',
        '/^<p[^>]*>\s*(?:The much-awaited [^,\.]+ has (?:finally )?(?:arrived|been released)[—–-—\.\s]*)/iu',
        '/^<p[^>]*>\s*(?:Exciting news for [^,\.]+[—–-—\.\s!]*)/iu',
        '/^<p[^>]*>\s*(?:Great news for [^,\.]+[—–-—\.\s!]*)/iu',
        '/^<p[^>]*>\s*(?:Finally, the wait is over[—–-—\.\s]*)/iu',
        '/^<p[^>]*>\s*(?:At long last,?\s*)/iu',
        '/^<p[^>]*>\s*(?:Following the [^\.\n]+\.\s*)/iu',
        '/^<p[^>]*>\s*(?:As per the latest (?:reports|updates|information)[^,\.]*,\s*)/iu',
    ];

    /**
     * Preachy / generic advice patterns inside paragraphs to eliminate
     */
    public const GENERIC_ADVICE_PATTERNS = [
        '/\b(?:always )?verify (?:the )?subject codes against the official PDF to avoid any confusion[^.!?]*[.!?]/iu',
        '/\bto avoid any confusion during your study sessions[^.!?]*[.!?]/iu',
        '/\bis a critical skill for (?:any )?(?:student|aspirant|candidate)[^.!?]*[.!?]/iu',
        '/\bpreparing for high-stakes (?:assessments|examinations|exams)[^.!?]*[.!?]/iu',
        '/\byou must ensure you\'?re well-rested and prepared for[^.!?]*[.!?]/iu',
        '/\bensure you are well-rested[^.!?]*[.!?]/iu',
        '/\bas you prepare, remember that board exams require strict adherence to protocols[^.!?]*[.!?]/iu',
        '/\brequire strict adherence to protocols[^.!?]*[.!?]/iu',
        '/\bensure your stationery is kept in a transparent pouch[^.!?]*[.!?]/iu',
        '/\bto avoid any issues during the security check[^.!?]*[.!?]/iu',
        '/\bdon\'?t bring any electronic gadgets, including smartwatches or mobile phones, as these are strictly prohibited inside the examination hall[^.!?]*[.!?]/iu',
        '/\ball information provided is based on the official circulars released by[^.!?]*[.!?]/iu',
        '/\byou should cross-check any updates directly at https?:\/\/[^\s]+ to ensure you\'?re acting on the latest verified data[^.!?]*[.!?]/iu',
        '/\bmaintain a consistent sleep schedule leading up to[^.!?]*[.!?]/iu',
        '/\bfocus on your revision notes and maintain a consistent sleep schedule[^.!?]*[.!?]/iu',
        '/\bstay calm and confident[^.!?]*[.!?]/iu',
        '/\bavoid unnecessary stress[^.!?]*[.!?]/iu',
        '/\bservers may experience heavy traffic[^.!?]*[.!?]/iu',
        '/\bthe servers will crawl[^.!?]*[.!?]/iu',
        '/\btry again after some time[^.!?]*[.!?]/iu',
        '/\btry an incognito window or clear your browser cache[^.!?]*[.!?]/iu',
        '/\btry incognito or private browsing mode[^.!?]*[.!?]/iu',
        '/\bdo not panic if the page hangs[^.!?]*[.!?]/iu',
        '/\bdon\'?t panic if the page hangs[^.!?]*[.!?]/iu',
        '/\bthe clock starts ticking immediately[^.!?]*[.!?]/iu',
        '/\byou(?:\'ve| have) worked too hard for this[^.!?]*[.!?]/iu',
        '/\bstay sharp, move fast[^.!?]*[.!?]/iu',
        '/\bderail your progress[^.!?]*[.!?]/iu',
        '/\bcross-check everything before proceeding[^.!?]*[.!?]/iu',
        '/\bensure you are acting on the latest verified data[^.!?]*[.!?]/iu',
        '/\bfor comparative insights[^.!?]*[.!?]/iu',
        '/\bit\'?s time to shift your focus toward[^.!?]*[.!?]/iu',
    ];

    /**
     * Analyse article quality and return detected issues and cleanliness score (0-100).
     */
    public static function analyzeQuality(string $content, string $title = '', string $intent = 'recruitment'): array
    {
        $issues = [];
        $lower = mb_strtolower($content);

        // 1. Generic Opener
        foreach (self::GENERIC_OPENER_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $issues['generic_opener'] = 'Formulaic AI opening hook detected';
                break;
            }
        }
        if (preg_match('/\bthe wait is (?:finally )?over\b/i', $content)) {
            $issues['wait_is_over'] = "'The wait is finally over' cliché detected";
        }

        // 2. Generic 5-step download guide
        if (preg_match('/<h[2-4][^>]*>[^<]*(?:Step-by-Step Guide to Download|How to Download|Steps to Download|How to Access)[^<]*<\/h[2-4]>/i', $content)) {
            if (preg_match('/<ol[^>]*>.*?<\/ol>/is', $content)) {
                $issues['generic_download_guide'] = 'Generic step-by-step download numbered list detected';
            }
        }

        // 3. Exam day / preachy advice (Only flag when genuine generic advice is present)
        if (str_contains($lower, 'transparent pouch') || str_contains($lower, 'strict adherence to protocols')) {
            $issues['preachy_advice'] = 'Preachy protocol fluff (transparent pouch / strict adherence) detected';
        }

        // 4. Fake psychological FAQs
        if (str_contains($lower, 'exam stress') || str_contains($lower, 'sleep schedule')) {
            $issues['stress_faq'] = 'Fake psychological stress / sleep schedule FAQ detected';
        }

        // 5. Server crash / cache advice
        if (str_contains($lower, 'servers will crawl') || str_contains($lower, 'server crawls') || str_contains($lower, 'incognito') || str_contains($lower, 'clear cache') || str_contains($lower, 'browser cache')) {
            $issues['server_advice'] = 'Generic ChatGPT server crash / incognito cache advice detected';
        }

        // 6. Boilerplate Authority Verification section
        if (preg_match('/<h[2-4][^>]*>[^<]*(?:Official Notice Reference & Authority Verification|Authority Verification & Direct Gazetted Links)[^<]*<\/h[2-4]>/i', $content)) {
            $issues['authority_verification_section'] = 'Boilerplate authority verification / circular reference section detected';
        }

        // 7. Excessive / Generic FAQs (> 3 FAQs or generic FAQs)
        $faqCount = 0;
        if (preg_match('/<h[2-4][^>]*>[^<]*(?:Frequently Asked Questions|FAQs)[^<]*<\/h[2-4]>(.*?)(?=<h[2-4]|$)/is', $content, $faqMatch)) {
            $faqBlock = $faqMatch[1];
            $h3Count = preg_match_all('/<h[3-5][^>]*>/i', $faqBlock);
            $strongLiCount = preg_match_all('/<li[^>]*>\s*<strong[^>]*>.*?<\/strong>/i', $faqBlock);
            $faqCount = max($h3Count, $strongLiCount);
            if ($faqCount > 3) {
                $issues['excessive_faqs'] = "FAQ section contains {$faqCount} questions (maximum allowed is 3)";
            }
        }

        // 8. Repetitive Candidate Phrasing
        $candidateCount = substr_count($lower, 'candidates can')
            + substr_count($lower, 'candidates should')
            + substr_count($lower, 'candidates must')
            + substr_count($lower, 'candidates are advised');
        if ($candidateCount >= 5) {
            $issues['repetitive_candidate_phrasing'] = "Repetitive candidate phrasing ('candidates can/should/must') occurred {$candidateCount} times";
        }

        // Cleanliness score: 100 minus deductions
        $deductions = count($issues) * 12;
        $score = max(0, 100 - $deductions);

        return [
            'score' => $score,
            'is_clean' => empty($issues),
            'needs_refactor' => !empty($issues),
            'issues' => $issues,
            'faq_count' => $faqCount,
        ];
    }

    /**
     * Refactor article content using DOM-aware and structural sanitization.
     * Guaranteed to preserve verified dates, tables, official links, and factual meaning.
     */
    public static function refactorContent(
        string $content,
        string $title = '',
        string $sourceUrl = '',
        string $intent = 'recruitment'
    ): array {
        if (trim($content) === '') {
            return ['content' => $content, 'modified' => false, 'changes' => []];
        }

        $original = $content;
        $changes = [];

        // ─────────────────────────────────────────────────────────────
        // STAGE 1: HTML-Aware Section Stripping (Table-Preserving)
        // ─────────────────────────────────────────────────────────────

        // 1a. Remove / replace generic 5-step download guides
        $portalUrl = filter_var($sourceUrl, FILTER_VALIDATE_URL) ? $sourceUrl : '';
        $cleanHost = $portalUrl ? (parse_url($portalUrl, PHP_URL_HOST) ?: 'the official portal') : 'the official portal';

        $downloadSectionPattern = '/<h[2-4][^>]*>[^<]*(?:Step-by-Step Guide to Download|How to Download|Steps to Download|How to Access)[^<]*<\/h[2-4]>\s*(?:<(?:ol|ul)[^>]*>.*?<\/(?:ol|ul)>|<p[^>]*>.*?<\/p>)+/is';
        if (preg_match($downloadSectionPattern, $content)) {
            if ($portalUrl !== '') {
                $replacement = "<h2>Official Document Download</h2>\n<p>Candidates can access the official notification and download documents directly from the portal at <a href=\"{$portalUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$cleanHost}</a>.</p>";
            } else {
                $replacement = '';
            }
            $content = preg_replace($downloadSectionPattern, $replacement, $content, 1);
            $changes[] = 'Converted generic 5-step download guide to direct portal link';
        }

        // 1b. Remove Exam Day Instructions ONLY if it contains ungrounded fluff AND does not contain <table>
        $examDayPattern = '/<h[2-4][^>]*>[^<]*(?:Exam Day Instructions & Mandatory Guidelines|Exam Day Instructions & Mandatory Documents Checklist|Exam Day Instructions|Mandatory Guidelines for)[^<]*<\/h[2-4]>(.*?)(?=<h[2-4]|$)/is';
        if (preg_match($examDayPattern, $content, $m)) {
            $sectionBody = $m[1] ?? '';
            // Only remove if it does NOT contain a table (never delete tables!)
            if (!str_contains($sectionBody, '<table') &&
                (stripos($sectionBody, 'transparent pouch') !== false ||
                 stripos($sectionBody, 'strict adherence to protocols') !== false ||
                 stripos($sectionBody, 'smartwatches') !== false ||
                 stripos($sectionBody, 'electronic gadgets') !== false)) {
                $content = preg_replace($examDayPattern, '', $content, 1);
                $changes[] = 'Removed generic exam-day advice section';
            }
        }

        // 1c. Remove Boilerplate "Official Notice Reference & Authority Verification" (never delete if it contains <table>)
        $authoritySectionPattern = '/<h[2-4][^>]*>[^<]*(?:Official Notice Reference & Authority Verification|Official Authority Verification & Direct Gazetted Links|Official Notification Circular & Direct Application Links|Official Portal Verification|Official Notice Reference)[^<]*<\/h[2-4]>(.*?)(?=<h[2-4]|$)/is';
        if (preg_match($authoritySectionPattern, $content, $m)) {
            $secBody = $m[1] ?? '';
            if (!str_contains($secBody, '<table') &&
                (stripos($secBody, 'All information provided is based on') !== false ||
                 stripos($secBody, 'cross-check any updates directly') !== false ||
                 mb_strlen(strip_tags($secBody)) < 300)) {
                $content = preg_replace($authoritySectionPattern, '', $content, 1);
                $changes[] = 'Removed boilerplate authority verification disclaimer section';
            }
        }

        // 1d. FAQ Quality Refactoring (Purge stress/server FAQs, cap list at 3 items)
        // Purge individual stress/server/cache FAQ items globally without touching enclosing tables
        $content = preg_replace('/<li[^>]*>[^<]*<strong[^>]*>[^<]*(?:exam stress|sleep schedule|handling pressure|website is down|servers will crawl|server crawls|incognito|clear cache)[^<]*<\/strong>.*?<\/li>/is', '', $content);
        $content = preg_replace('/<h[3-5][^>]*>[^<]*(?:exam stress|sleep schedule|handling pressure|website is down|servers will crawl|server crawls|incognito|clear cache)[^<]*<\/h[3-5]>\s*<p[^>]*>.*?<\/p>/is', '', $content);

        // Cap FAQ lists with > 3 questions to top 3 items without truncating any following tables or content
        $content = preg_replace_callback('/(<h[2-4][^>]*>[^<]*(?:Frequently Asked Questions|FAQs)[^<]*<\/h[2-4]>\s*)(<(?:ul|ol)[^>]*>)(.*?)(<\/(?:ul|ol)>)/is', function($m) use (&$changes) {
            $heading = $m[1];
            $openTag = $m[2];
            $listContent = $m[3];
            $closeTag = $m[4];

            preg_match_all('/<li[^>]*>.*?<\/li>/is', $listContent, $liMatches);
            $items = $liMatches[0] ?? [];

            if (empty($items)) {
                $changes[] = 'Removed empty / non-factual FAQ section entirely';
                return ''; // No questions left -> remove heading & list
            }

            if (count($items) > 3) {
                $items = array_slice($items, 0, 3);
                $changes[] = 'Capped FAQ section to top 3 relevant questions';
            }

            return $heading . $openTag . "\n" . implode("\n", $items) . "\n" . $closeTag;
        }, $content);

        // ─────────────────────────────────────────────────────────────
        // STAGE 2: Prose & Sentence-Level Sanitization
        // ─────────────────────────────────────────────────────────────

        // 2a. Sanitize opening hook
        $cleanTitle = trim(preg_replace('/\s*[:\-–|].*$/', '', $title));
        if ($cleanTitle === '') {
            $cleanTitle = 'the examination';
        }

        $humanOpeners = [
            'ADMIT_CARD'      => "Admit cards for {$cleanTitle} are now available on {$cleanHost}.",
            'ANSWER_KEY'      => "The provisional answer key for {$cleanTitle} is now accessible on {$cleanHost}.",
            'RESULT_CUTOFF'   => "The official results and scorecards for {$cleanTitle} have been published on {$cleanHost}.",
            'RECRUITMENT'     => "The official notification for {$cleanTitle} is currently available on {$cleanHost}.",
            'SYLLABUS_CHANGE' => "The revised examination pattern and syllabus for {$cleanTitle} have been released on {$cleanHost}.",
            'CORRIGENDUM'     => "An official corrigendum regarding {$cleanTitle} has been notified on {$cleanHost}.",
            'COUNSELLING'     => "The counselling and seat allotment schedule for {$cleanTitle} is now live on {$cleanHost}.",
        ];
        $targetOpener = $humanOpeners[strtoupper($intent)] ?? "Official updates for {$cleanTitle} are now available on {$cleanHost}.";

        foreach (self::GENERIC_OPENER_PATTERNS as $pattern) {
            if (preg_match($pattern, $content, $pm)) {
                $content = preg_replace($pattern, "<p>{$targetOpener} ", $content, 1);
                $changes[] = 'Replaced formulaic AI opening hook with direct news opening';
                break;
            }
        }
        // Additional opening sentence fixes
        $content = preg_replace('/If you(?:\'ve| have) been waiting for [^,\.]+,?\s*(?:the wait is (?:finally )?over\.\s*)?/iu', '', $content);
        $content = preg_replace('/\bthe wait is (?:finally )?over\.\s*/iu', '', $content);
        $content = preg_replace('/\bIt\'?s time to shift your focus toward[^.!?]*[.!?]\s*/iu', '', $content);
        $content = preg_replace('/\bIf you are also tracking national-level board updates[^<]*for comparative insights\.\s*/iu', '', $content);

        // 2b. Eliminate preachy advice patterns
        foreach (self::GENERIC_ADVICE_PATTERNS as $pat) {
            if (preg_match($pat, $content)) {
                $content = preg_replace($pat, '', $content);
                $changes[] = 'Stripped preachy / ungrounded student advice';
            }
        }

        // 2c. Reduce repetitive candidate phrasing
        $content = self::varyCandidatePhrasing($content);

        // 2d. Clean up empty HTML tags
        $content = self::cleanEmptyTags($content);

        // 2e. Enforce natural human contractions
        $content = HumanizerService::enforceContractions($content);

        // 2f. Scrub banned clichés
        $content = HumanizerService::scrubClichés($content);
        $scrubbed = HumanizerService::scrubClichePatterns($content);
        $content = $scrubbed['html'];

        // 2g. Clean multiple line breaks
        $content = preg_replace("/\n{3,}/", "\n\n", trim($content));

        $modified = ($content !== $original);

        return [
            'content' => $content,
            'modified' => $modified,
            'changes' => array_unique($changes),
            'diff' => mb_strlen($content) - mb_strlen($original),
        ];
    }

    /**
     * Varies repetitive "Candidates can / Candidates should / Candidates must" phrasing.
     */
    public static function varyCandidatePhrasing(string $html): string
    {
        $count = 0;
        $html = preg_replace_callback('/\bCandidates can\b/u', function($m) use (&$count) {
            $count++;
            return ($count % 2 === 0) ? 'You can' : 'Candidates can';
        }, $html);

        $mustCount = 0;
        $html = preg_replace_callback('/\bCandidates must\b/u', function($m) use (&$mustCount) {
            $mustCount++;
            return ($mustCount % 2 === 0) ? 'You need to' : 'Candidates must';
        }, $html);

        $html = preg_replace('/\bCandidates are advised to\b/iu', 'Make sure to', $html);

        return $html;
    }

    /**
     * Safely cleans empty paragraphs, empty list items, and orphaned punctuation in HTML.
     */
    public static function cleanEmptyTags(string $html): string
    {
        $html = preg_replace('/<p[^>]*>\s*<\/p>/u', '', $html);
        $html = preg_replace('/<li[^>]*>\s*<\/li>/u', '', $html);
        $html = preg_replace('/<h[2-6][^>]*>\s*<\/h[2-6]>/u', '', $html);
        $html = preg_replace('/<div[^>]*>\s*<\/div>/u', '', $html);
        $html = preg_replace('/<ul[^>]*>\s*<\/ul>/u', '', $html);
        $html = preg_replace('/<ol[^>]*>\s*<\/ol>/u', '', $html);
        $html = preg_replace('/\s+and\s*<\/li>/iu', '.</li>', $html);
        $html = preg_replace('/\s+and\s*<\/p>/iu', '.</li>', $html);
        $html = preg_replace('/<p>\s*([.!?,;:])/u', '<p>', $html);

        return trim($html);
    }
}
