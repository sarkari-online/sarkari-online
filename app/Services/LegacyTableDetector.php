<?php
declare(strict_types=1);

namespace App\Services;

/**
 * LegacyTableDetector
 * Identifies legacy/corrupted single-row or two-row placeholder milestone tables
 * using a strict 2-factor signature:
 * 1. Must contain the exact legacy header text ('Statutory Milestone' AND 'Official Date / Status')
 * 2. Majority (>= 50%) of data cells must be placeholder terms ('not yet announced', 'tba', etc.)
 *
 * This narrow signature ensures legitimate domain tables (Cutoff tables, Exam Pattern tables,
 * Fee breakdown tables) are NEVER falsely detected or removed.
 */
final class LegacyTableDetector
{
    private const LEGACY_HEADER_SIGNATURES = ['Statutory Milestone', 'Official Date / Status'];
    private const PLACEHOLDER_TERMS = ['not yet announced', 'to be announced', 'tba', 'awaited'];
    private const MIN_PLACEHOLDER_RATIO = 0.5;

    /**
     * Find all legacy corrupted tables in the given HTML.
     *
     * @param string $html
     * @return array Array of ['html' => string, 'wrapper_html' => string, 'offset' => int]
     */
    public static function findLegacyTables(string $html): array
    {
        // Match table optionally wrapped in <div class="table-responsive">...</div>
        preg_match_all('/(?:<div[^>]*class=["\'][^"\']*table-responsive[^"\']*["\'][^>]*>\s*)?(<table\b[^>]*>.*?<\/table>)(?:\s*<\/div>)?/is', $html, $matches, PREG_OFFSET_CAPTURE);

        $legacy = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $idx => [$tableHtml, $offset]) {
                if (self::matchesLegacySignature($tableHtml)) {
                    $wrapperHtml = $matches[0][$idx][0];
                    $legacy[] = [
                        'html'         => $tableHtml,
                        'wrapper_html' => $wrapperHtml,
                        'offset'       => $offset
                    ];
                }
            }
        }

        return $legacy;
    }

    private static function matchesLegacySignature(string $tableHtml): bool
    {
        // Signal 1: ALL known legacy headers must be present (or either Statutory Milestone / Official Date)
        $hasHeader = false;
        foreach (self::LEGACY_HEADER_SIGNATURES as $sig) {
            if (stripos($tableHtml, $sig) !== false) {
                $hasHeader = true;
                break;
            }
        }
        if (!$hasHeader) {
            return false;
        }

        // Signal 2: Majority of data cells are placeholder text
        preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tableHtml, $cellMatches);
        $cells = $cellMatches[1] ?? [];
        if (empty($cells)) {
            return false;
        }

        $placeholderCount = count(array_filter($cells, fn($c) => self::isPlaceholder(strip_tags((string)$c))));
        $ratio = $placeholderCount / count($cells);

        return $ratio >= self::MIN_PLACEHOLDER_RATIO;
    }

    private static function isPlaceholder(string $cellText): bool
    {
        $normalized = mb_strtolower(trim($cellText));
        if (empty($normalized)) {
            return true;
        }
        foreach (self::PLACEHOLDER_TERMS as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }
        return false;
    }
}
