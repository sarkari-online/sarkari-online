<?php
declare(strict_types=1);

namespace App\Services;

/**
 * NewsDispatchSanitizer
 * Filters news dispatches by recency in PHP and strips publication date metadata
 * BEFORE the context is ever passed to the LLM. The model literally cannot confuse
 * an article's publication date with an official event date if the publication date
 * is never exposed as extractable text.
 */
final class NewsDispatchSanitizer
{
    /**
     * Filter by recency and sanitize news dispatches for LLM context.
     * Consumes published_at for age filtering and strictly omits it from output.
     *
     * @param array $dispatches Array of ['headline' => string, 'body_snippet' => string, 'published_at' => string, 'source_url' => string]
     * @param int $maxAgeDays Maximum age of news in days (default: 14)
     * @return array Array of ['headline' => string, 'body_snippet' => string, 'source_url' => string]
     */
    public static function filterAndSanitize(array $dispatches, int $maxAgeDays = 14): array
    {
        $cutoff = strtotime("-{$maxAgeDays} days");
        $sanitized = [];

        foreach ($dispatches as $dispatch) {
            $pubTime = !empty($dispatch['published_at']) ? strtotime((string)$dispatch['published_at']) : false;
            if ($pubTime !== false && $pubTime < $cutoff) {
                continue; // Too old to be relevant
            }

            $headline = trim((string)($dispatch['headline'] ?? ''));
            $snippet = trim((string)($dispatch['body_snippet'] ?? ''));

            if (empty($headline) && empty($snippet)) {
                continue;
            }

            $sanitized[] = [
                'headline'     => $headline,
                'body_snippet' => $snippet,
                'source_url'   => $dispatch['source_url'] ?? '',
                // Deliberately NOT included: 'published_at'.
                // The LLM never sees this field. If a date matters, it must come
                // from the dispatch's own body text, not its RSS metadata.
            ];
        }

        return $sanitized;
    }
}
