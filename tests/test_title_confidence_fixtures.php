<?php
declare(strict_types=1);

namespace App\Tests;

require_once dirname(__DIR__) . '/config.php';

final class TitleBodyConfidenceDetector {
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
                    // Generic broad season check like "2025-26" without day/month
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

// -----------------------------------------------------------------------------
// ADVERSARIAL TEST RUNNER
// -----------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "🧪 TITLE-BODY CONFIDENCE CONFLATION ADVERSARIAL TEST SUITE\n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;

// Test 1: Article #689 Exact Case (True Positive)
$t1Title = "UPSSSC Junior Assistant & Lekhpal 2026: Exam Date Confirmed & Admit Card Updates";
$t1Html = "
<table>
    <tr><td>Exam Date</td><td>2025-26</td></tr>
    <tr><td>Admit Card</td><td>To be announced</td></tr>
</table>
<p>What is the examination date? The exact exam date has not yet been notified by UPSSSC. Candidates should monitor the official website for updates.</p>
";
$r1 = TitleBodyConfidenceDetector::check($t1Title, $t1Html);
echo "TEST 1: True Positive - Article #689 (Exam Date Confirmed vs Not Yet Notified)\n";
if (!empty($r1)) {
    echo "  RESULT: ✅ PASS (Correctly flagged confidence conflation)\n";
    echo "  Issue: " . $r1[0] . "\n\n";
    $passCount++;
} else {
    echo "  RESULT: ❌ FAIL (Missed #689 confidence conflation!)\n\n";
    $failCount++;
}

// Test 2: Mixed Claim - Legitimate Scoped Title (True Negative)
$t2Title = "UPSC NDA 2 2026: Admit Card Released, Exam Schedule Details";
$t2Html = "
<table>
    <tr><td>Admit Card</td><td>Available Now (Released September 07, 2026)</td></tr>
    <tr><td>Exam Date</td><td>To be announced</td></tr>
</table>
<p>Candidates can download their admit card using the link below.</p>
";
$r2 = TitleBodyConfidenceDetector::check($t2Title, $t2Html);
echo "TEST 2: True Negative - Mixed Claim (Admit Card Released, Exam Date TBA)\n";
if (empty($r2)) {
    echo "  RESULT: ✅ PASS (Correctly ignored unconfirmed exam date because title only claimed admit card was released)\n\n";
    $passCount++;
} else {
    echo "  RESULT: ❌ FAIL (False Positive! Flagged legitimate mixed title: " . json_encode($r2) . ")\n\n";
    $failCount++;
}

// Test 3: Fully Confirmed Article (True Negative)
$t3Title = "RRB NTPC CBT 2 2026: Exam Schedule Out & Shift Timings";
$t3Html = "
<table>
    <tr><td>Exam Date</td><td>September 17, 2026</td></tr>
    <tr><td>Admit Card</td><td>September 13, 2026</td></tr>
</table>
<p>The examination is scheduled on 17 September 2026 in two shifts.</p>
";
$r3 = TitleBodyConfidenceDetector::check($t3Title, $t3Html);
echo "TEST 3: True Negative - Fully Confirmed Real Dates\n";
if (empty($r3)) {
    echo "  RESULT: ✅ PASS (Clean confirmed title passed without issues)\n\n";
    $passCount++;
} else {
    echo "  RESULT: ❌ FAIL (False Positive on confirmed article!)\n\n";
    $failCount++;
}

// Test 4: Result Declared vs TBA (True Positive)
$t4Title = "UGC NET June 2026 Result Declared: Scorecard Link & Cutoffs";
$t4Html = "
<table>
    <tr><td>Scorecard Link</td><td>To be announced</td></tr>
    <tr><td>Result Status</td><td>Awaited by NTA</td></tr>
</table>
<p>The UGC NET June result date will be announced soon by National Testing Agency.</p>
";
$r4 = TitleBodyConfidenceDetector::check($t4Title, $t4Html);
echo "TEST 4: True Positive - Result Declared in Title vs Awaited in Body\n";
if (!empty($r4)) {
    echo "  RESULT: ✅ PASS (Caught Result Declared vs Awaited conflation)\n";
    echo "  Issue: " . $r4[0] . "\n\n";
    $passCount++;
} else {
    echo "  RESULT: ❌ FAIL (Missed Result Declared conflation!)\n\n";
    $failCount++;
}

echo "----------------------------------------------------------------------\n";
echo "SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";
exit($failCount === 0 ? 0 : 1);
