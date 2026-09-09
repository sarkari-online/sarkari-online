<?php
declare(strict_types=1);

namespace App\Services;

final class HallucinationGuard
{
    /**
     * Check if any date in datesTable suspiciously equals today's generation date
     * without that date being physically present in the crawled source text.
     *
     * @param array $datesTable Key-value or list of dates
     * @param string $rawSourceText Crawled circular, notice board, or snippet text
     * @return array List of blocking issues found
     */
    public static function checkForSuspiciousTodayDate(array $datesTable, string $rawSourceText): array
    {
        $todayVariants = [
            date('Y-m-d'),
            date('d F Y'),
            date('j F Y'),
            date('F d, Y'),
            date('F j, Y'),
            date('d M Y'),
            date('M d, Y'),
            date('d/m/Y'),
            date('d-m-Y')
        ];

        // Check if today's date is genuinely present in source text
        $todayInSource = false;
        foreach ($todayVariants as $v) {
            if (stripos($rawSourceText, $v) !== false) {
                $todayInSource = true;
                break;
            }
        }

        // If today's date is genuinely in source circular (e.g. notice issued today), it is not a hallucination
        if ($todayInSource) {
            return [];
        }

        $issues = [];

        foreach ($datesTable as $field => $item) {
            $value = is_array($item) ? ($item['date'] ?? ($item['value'] ?? '')) : (string)$item;
            $fieldName = is_numeric($field) ? ($item['event'] ?? ($item['milestone'] ?? "Milestone #{$field}")) : $field;

            foreach ($todayVariants as $variant) {
                if (stripos($value, $variant) !== false) {
                    $issues[] = "BLOCKING: Suspicious '{$fieldName}' value ('{$value}') matches today's generation timestamp but is absent from source text (Hallucination signature).";
                    break;
                }
            }
        }

        return $issues;
    }
}
