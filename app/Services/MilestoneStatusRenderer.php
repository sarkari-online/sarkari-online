<?php
declare(strict_types=1);

namespace App\Services;

/**
 * MilestoneStatusRenderer
 * Formats table milestone cell values with transparent provenance and visual badges.
 */
final class MilestoneStatusRenderer
{
    /**
     * Render HTML badge with source provenance
     *
     * @param mixed $fact Fact payload array or raw display string
     * @return string Formatted HTML
     */
    public static function renderBadge(mixed $fact): string
    {
        if (is_string($fact)) {
            $clean = trim($fact);
            if (empty($clean) || stripos($clean, 'not yet') !== false || stripos($clean, 'awaiting') !== false) {
                return '<span class="status-pill status-pill-upcoming">Not Yet Officially Announced</span>';
            }
            return '<strong>' . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . '</strong>';
        }

        if (!is_array($fact)) {
            return '<span class="status-pill status-pill-upcoming">Not Yet Officially Announced</span>';
        }

        $val = trim((string)($fact['value'] ?? ($fact['date'] ?? '')));
        $confidence = $fact['source_confidence'] ?? ($fact['status'] ?? 'confirmed');
        $basis = trim((string)($fact['tentative_basis'] ?? ''));

        if (empty($val) || $confidence === 'unavailable' || stripos($val, 'not yet') !== false) {
            return '<span class="status-pill status-pill-upcoming">Not Yet Officially Announced</span>';
        }

        if ($confidence === 'tentative_estimate' && !empty($basis)) {
            return sprintf(
                '<span class="status-pill status-pill-tentative" style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; font-weight:600; font-size:0.85rem;">%s (Expected)</span>'
                . '<div class="basis-note" style="font-size:0.75rem; color:#64748b; margin-top:3px; line-height:1.2;"><strong>Basis:</strong> %s</div>',
                htmlspecialchars($val, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($basis, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<strong class="text-success">%s</strong>',
            htmlspecialchars($val, ENT_QUOTES, 'UTF-8')
        );
    }
}
