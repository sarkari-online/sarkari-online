<?php
/**
 * Sarkari.online - Title-Body Confidence Conflation Detector
 *
 * Scans article title assertions against grounded facts and body content to prevent
 * confidence-conflation defects (e.g. Title aggressively claiming "Exam Date Confirmed"
 * while body/tables admit "Exam Date: To be announced / not yet notified").
 *
 * Entity-scoped to prevent false positives when one milestone is confirmed
 * while another milestone is legitimately pending.
 */

namespace App\Services;

class TitleBodyConfidenceDetector {

    // Map: confidence-word pattern in TITLE → which fact_type it is claiming
    private const TITLE_CLAIM_MAP = [
        '/\bexam date(s)?\s+(confirmed|announced|out|declared)\b/i' => 'exam_date',
        '/\b(exam date confirmed|dates confirmed)\b/i' => 'exam_date',
        '/\bexam schedule\s+(out|announced|released|confirmed)\b/i' => 'exam_date',
        '/\badmit card\s+(out|released|activated|available)\b/i' => 'admit_card_release_date',
        '/\bcity (slip|intimation)\s+(out|released|activated)\b/i' => 'city_slip_release_date',
        '/\bresult(s)?\s+(out|declared|announced)\b/i' => 'result_date',
        '/\banswer key\s+(out|released)\b/i' => 'answer_key_release_date',
    ];

    // Unconfirmed markers in body/facts
    private const UNCONFIRMED_MARKERS = [
        '/not yet (been )?notified/i',
        '/to be announced/i',
        '/\btba\b/i',
        '/\bawaited\b/i',
        '/not yet released/i',
        '/\btentative\b/i',
        '/yet to be/i',
        '/not released/i',
        '/date awaited/i',
        '/yet to be announced/i',
        '/\bexpected\s+(in|soon)\b/i'
    ];

    /**
     * Check an article title against its HTML body and raw_payload facts
     * Returns an array of detected violation issues
     */
    public static function check(string $title, string $html, array $rawPayload = []): array {
        $issues = [];
        $htmlWithBreaks = preg_replace('/<\/(td|tr|p|li|div|h[1-6]|dt|dd)>/i', ". \n", $html);
        $plainText = strip_tags($htmlWithBreaks);

        foreach (self::TITLE_CLAIM_MAP as $pattern => $factType) {
            if (!preg_match($pattern, $title)) {
                continue;
            }

            // 1. Check against structured grounded facts if available in raw_payload
            $groundedFactUnconfirmed = self::checkGroundedFacts($rawPayload, $factType);

            // 2. Scan body HTML / text near this entity (tables, FAQs, paragraphs)
            $bodyUnconfirmed = self::bodyMentionsUnconfirmed($html, $plainText, $factType);

            if ($groundedFactUnconfirmed || $bodyUnconfirmed) {
                $issues[] = "[Title-Body Confidence Conflation] Title claims '{$factType}' is confirmed, but body/facts indicate it is not yet available.";
            }
        }

        return $issues;
    }

    private static function checkGroundedFacts(array $rawPayload, string $factType): bool {
        $datesSchedule = $rawPayload['dates_schedule'] ?? [];
        if (!is_array($datesSchedule) || empty($datesSchedule)) {
            return false;
        }

        $keywords = self::getMilestoneKeywords($factType);

        foreach ($datesSchedule as $row) {
            $milestone = strtolower($row['milestone'] ?? '');
            $dateVal = strtolower($row['date'] ?? '');
            $status = strtolower($row['status'] ?? '');

            $matchesMilestone = false;
            foreach ($keywords as $kw) {
                if (str_contains($milestone, $kw)) {
                    $matchesMilestone = true;
                    break;
                }
            }

            if ($matchesMilestone) {
                if (str_contains($status, 'awaited') || str_contains($status, 'tentative') || str_contains($status, 'pending')) {
                    return true;
                }
                foreach (self::UNCONFIRMED_MARKERS as $marker) {
                    if (preg_match($marker, $dateVal)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function bodyMentionsUnconfirmed(string $html, string $plainText, string $factType): bool {
        $keywords = self::getMilestoneKeywords($factType);

        // A. Table row scan: e.g. <tr><td>Exam Date</td><td>To be announced / 2025-26</td></tr>
        if (preg_match_all('/<tr[^>]*>.*?<\/tr>/is', $html, $rows)) {
            foreach ($rows[0] as $row) {
                $rowText = strtolower(strip_tags($row));
                $hasEntity = false;
                foreach ($keywords as $kw) {
                    if (str_contains($rowText, $kw)) {
                        $hasEntity = true;
                        break;
                    }
                }
                if ($hasEntity) {
                    foreach (self::UNCONFIRMED_MARKERS as $marker) {
                        if (preg_match($marker, $rowText)) {
                            return true;
                        }
                    }
                    // Generic broad season check like "2025-26" without specific date
                    if (preg_match('/\b202[456]-[0-9]{2}\b/', $rowText) && !preg_match('/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i', $rowText)) {
                        return true;
                    }
                }
            }
        }

        // B. FAQ / Paragraph sentence scan
        $sentences = preg_split('/(?<=[.?!])\s+/u', $plainText);
        foreach ($sentences as $sentence) {
            $s = strtolower(trim($sentence));
            if (strlen($s) < 15) continue;

            $hasEntity = false;
            foreach ($keywords as $kw) {
                if (str_contains($s, $kw)) {
                    $hasEntity = true;
                    break;
                }
            }

            if ($hasEntity) {
                foreach (self::UNCONFIRMED_MARKERS as $marker) {
                    if (preg_match($marker, $s)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function getMilestoneKeywords(string $factType): array {
        return match ($factType) {
            'exam_date' => ['exam date', 'examination date', 'exam schedule', 'paper schedule'],
            'admit_card_release_date' => ['admit card', 'hall ticket', 'call letter'],
            'city_slip_release_date' => ['city slip', 'city intimation', 'intimation slip'],
            'result_date' => ['result', 'scorecard', 'marks'],
            'answer_key_release_date' => ['answer key', 'objection window', 'key challenge'],
            default => []
        };
    }
}
