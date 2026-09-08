<?php
declare(strict_types=1);

namespace App\Tests;

class BodyStalenessDetector {
    private const HISTORICAL_COMPARATIVE_PATTERNS = [
        '/\b(unlike|as opposed to|compared with|compared to|difference between|whereas|in contrast to)\b/i',
        '/\b(concluded|completed|cleared|qualified|appeared in|conducted on|held on|earlier|previous stage|prior stage)\b/i',
        '/\b(who passed|who cleared|who qualified|shortlisted based on|candidates of cbt[ -]?1)\b/i',
        '/\b(cbt[ -]?1 was|tier[ -]?1 was|prelims was|preliminary was)\b/i'
    ];

    private const ACTIVE_DIRECTIVE_PATTERNS = [
        '/\b(get the latest|check here|download your|direct link to download|steps to download)\b/i',
        '/\b(admit card download link|city intimation slip link|hall ticket release date|active download link)\b/i',
        '/\b(exam schedule|exam date|shift timings?)\b.*?\b(announced|released|out now|published|activated|scheduled on)\b/i',
        '/\b(announced|released|out now|published|activated|scheduled on)\b.*?\b(exam schedule|exam date|shift timings?)\b/i'
    ];

    public static function check(string $html, string $title): array {
        $issues = [];
        $tLower = strtolower($title);

        $isCbt2 = (bool)preg_match('/\b(cbt[ -]?2|tier[ -]?2|phase[ -]?ii|stage[ -]?2)\b/i', $tLower);
        $isMains = (bool)preg_match('/\b(mains?|main exam)\b/i', $tLower);

        if (!$isCbt2 && !$isMains) {
            return [];
        }

        // 1. Strip out non-body prose (related links, also read callouts, tickers)
        $cleanHtml = preg_replace('/<div[^>]*class=[\'"][^\'"]*(also-read|related-articles|sidebar|ticker)[^\'"]*[\'"][^>]*>.*?<\/div>/is', '', $html);
        $plainText = strip_tags($cleanHtml);

        // 2. Break into individual sentences
        $sentences = preg_split('/(?<=[.?!])\s+/u', $plainText);

        foreach ($sentences as $sentence) {
            $s = trim($sentence);
            if (mb_strlen($s) < 20) continue;

            $mentionsPriorPhase = false;
            $priorPhaseName = '';

            if ($isCbt2 && preg_match('/\b(cbt[ -]?1|tier[ -]?1)\b/i', $s, $m)) {
                $mentionsPriorPhase = true;
                $priorPhaseName = $m[0];
            } elseif ($isMains && preg_match('/\b(prelims?|preliminary|tier[ -]?1)\b/i', $s, $m)) {
                $mentionsPriorPhase = true;
                $priorPhaseName = $m[0];
            }

            if (!$mentionsPriorPhase) {
                continue;
            }

            // Check if sentence has comparative or historical markers
            $isHistoricalOrComparative = false;
            foreach (self::HISTORICAL_COMPARATIVE_PATTERNS as $pattern) {
                if (preg_match($pattern, $s)) {
                    $isHistoricalOrComparative = true;
                    break;
                }
            }

            if ($isHistoricalOrComparative) {
                continue; // Legitimate historical or comparative reference!
            }

            // Check if sentence makes active directive assertions about the prior phase
            $isActiveDirective = false;
            foreach (self::ACTIVE_DIRECTIVE_PATTERNS as $pattern) {
                if (preg_match($pattern, $s)) {
                    $isActiveDirective = true;
                    break;
                }
            }

            if ($isActiveDirective) {
                $issues[] = "Active {$priorPhaseName} assertion found in " . ($isCbt2 ? "CBT-2" : "Mains") . " article: \"" . mb_substr($s, 0, 100) . "...\"";
            }
        }

        return $issues;
    }
}

// Adversarial Test Cases requested by Claude
$tests = [
    'Case A (Comparative - Unlike CBT-1)' => [
        'title' => 'RRB NTPC CBT 2 Admit Card 2026: City Slip Out, Exam Date',
        'text' => 'Candidates should note that unlike the CBT-1 exam schedule, CBT-2 gate closure is strictly enforced.'
    ],
    'Case B (Historical Concluded - CBT-1 stage concluded)' => [
        'title' => 'RRB NTPC CBT 2 Admit Card 2026: City Slip Out, Exam Date',
        'text' => 'The CBT-1 stage concluded on 15 August; the next stage schedule is below.'
    ],
    'Case C (Historical Qualified - Article #703 real background)' => [
        'title' => 'RRB NTPC CBT 2 Admit Card 2026: City Slip Out, Exam Date',
        'text' => 'The Railway Recruitment Board is conducting the 2nd Stage Computer Based Test (CBT-2) for 45,900+ candidates who qualified the CBT-1 stage across 3,058 undergraduate-level vacancies under Centralised Employment Notice CEN 07/2025.'
    ],
    'Case D (Cross-Article Ticker / Also Read)' => [
        'title' => 'RRB NTPC CBT 2 Admit Card 2026: City Slip Out, Exam Date',
        'text' => '<div class="related-articles-section"><a href="#">RRB Technician 2026: CBT 1 Exam Schedule, Shift Timings & Guidelines</a></div><p>Candidates preparing for CBT-2 can access their exam city details below.</p>'
    ],
    'Case E (Real Stale Sentence - Article #703 Bug)' => [
        'title' => 'RRB NTPC CBT 2 Admit Card 2026: City Slip Out, Exam Date',
        'text' => 'Get the latest updates on the RRB NTPC 2026 CBT 1 exam schedule, admit card release timeline, and steps to download hall ticket.'
    ]
];

echo "\n======================================================================\n";
echo "🧪 BODY STALENESS DETECTOR ADVERSARIAL TEST SUITE\n";
echo "======================================================================\n\n";

foreach ($tests as $name => $test) {
    $res = BodyStalenessDetector::check($test['text'], $test['title']);
    echo "TEST: {$name}\n";
    if (empty($res)) {
        echo "  RESULT: ✅ PASS (Correctly ignored as legitimate / non-stale)\n\n";
    } else {
        echo "  RESULT: 🚨 FLAGGED AS STALE: {$res[0]}\n\n";
    }
}
