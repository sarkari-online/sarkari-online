<?php
declare(strict_types=1);
/**
 * TableIntegrityGate — Phase B (Step 3E.5 in PipelineService)
 * Scans generated article HTML for forbidden placeholder strings inside <td> elements.
 * Any violation -> article blocked from publishing -> routed to needs_enrichment.
 * This is the LAST LINE OF DEFENSE against TBA/Awaited strings reaching users.
 */

namespace App\Services;

use App\Helpers\Logger;

class TableIntegrityGate
{
    private const FORBIDDEN_PATTERNS = [
        '/\bTBA\b/i'                         => 'Forbidden abbreviation "TBA"',
        '/\bTo Be Announced\b/i'              => 'Forbidden placeholder "To Be Announced"',
        '/\bAwaited\b/i'                      => 'Forbidden placeholder "Awaited"',
        '/\bComing Soon\b/i'                  => 'Forbidden placeholder "Coming Soon"',
        '/\bNot Announced\b/i'                => 'Forbidden placeholder "Not Announced"',
        '/\bWill Be Announced\b/i'            => 'Forbidden placeholder "Will Be Announced"',
        '/\bYet to be released\b/i'           => 'Forbidden placeholder "Yet to be released"',
        '/\bExpected Soon\b/i'                => 'Forbidden placeholder "Expected Soon"',
        '/\bNotification Awaited\b/i'         => 'Forbidden placeholder "Notification Awaited"',
        '/\bDate Awaited\b/i'                 => 'Forbidden placeholder "Date Awaited"',
        '/\bResult Awaited\b/i'               => 'Forbidden placeholder "Result Awaited"',
        '/\b00[-\/]00[-\/](?:0000|\d{4})\b/' => 'Suspicious zero-date pattern',
        '/XX\/XX\/\d{4}/'                     => 'Placeholder date XX/XX/YYYY',
    ];

    /**
     * Scan HTML for forbidden placeholders inside <td> table cells.
     * Exempts authenticated grounded tentative badges (<span class="status-pill status-pill-tentative">)
     * carrying a verifiable basis note.
     * 
     * @param  string $htmlContent  Full article HTML
     * @return array                Violation description strings (empty = clean)
     */
    public function scan(string $htmlContent): array
    {
        $violations = [];
        if (preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $htmlContent, $matches)) {
            foreach ($matches[1] as $idx => $cellHtml) {
                // If this is an authenticated grounded tentative badge with basis, exempt from violation
                if (str_contains($cellHtml, 'status-pill-tentative') && str_contains($cellHtml, 'basis-note')) {
                    continue;
                }

                $cellText = strip_tags($cellHtml);
                foreach (self::FORBIDDEN_PATTERNS as $pattern => $description) {
                    if (preg_match($pattern, $cellText)) {
                        $snippet    = mb_substr($cellText, 0, 60);
                        $violation  = "{$description} in table cell #{$idx}: \"{$snippet}\"";
                        $violations[] = $violation;
                        Logger::warning("TableIntegrityGate: {$violation}");
                        break; // one violation per cell
                    }
                }
            }
        }
        return $violations;
    }

    /** Quick boolean — true if content is clean. */
    public function isClean(string $htmlContent): bool
    {
        return empty($this->scan($htmlContent));
    }

    /**
     * Mechanically auto-repair forbidden placeholders inside <td> table cells,
     * replacing them with professional, fact-compliant text ("Not yet announced").
     */
    public function repair(string $html): string
    {
        return preg_replace_callback('/<td\b([^>]*)>(.*?)<\/td>/is', function ($m) {
            $attrs = $m[1];
            $body  = $m[2];

            // If this is an authenticated grounded tentative badge with basis, preserve it untouched
            if (str_contains($body, 'status-pill-tentative') && str_contains($body, 'basis-note')) {
                return "<td{$attrs}>{$body}</td>";
            }

            $clean = strip_tags($body);

            $hasViolation = false;
            foreach (self::FORBIDDEN_PATTERNS as $pat => $desc) {
                if (preg_match($pat, $clean)) {
                    $hasViolation = true;
                    break;
                }
            }

            if ($hasViolation) {
                if (preg_match('/class="status-pill[^"]*"/i', $body)) {
                    $body = '<span class="status-pill status-pill-upcoming">Not yet announced</span>';
                } else {
                    $body = 'Not yet announced';
                }
            }

            return "<td{$attrs}>{$body}</td>";
        }, $html);
    }

    private function extractTableCellContents(string $html): array
    {
        $cells = [];
        if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $html, $matches)) {
            foreach ($matches[1] as $cellHtml) {
                $cells[] = strip_tags($cellHtml);
            }
        }
        return $cells;
    }
}
