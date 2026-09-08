<?php
declare(strict_types=1);

namespace App\Tests;

class BodyStalenessDetector {
    private const HISTORICAL_COMPARATIVE_PATTERNS = [
        '/\b(unlike|as opposed to|compared with|compared to|difference between|whereas|in contrast to)\b/i',
        '/\b(concluded|completed|cleared|qualified|appeared in|conducted on|held on|earlier|previous stage|prior stage)\b/i',
        '/\b(who passed|who cleared|who qualified|shortlisted based on|candidates of cbt[ -]?[12]|candidates of tier[ -]?[12])\b/i',
        '/\b(cbt[ -]?[12] was|tier[ -]?[12] was|prelims was|preliminary was|mains was)\b/i'
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

        $stageRank = 0;
        $priorPatterns = [];
        $currentStageLabel = '';

        // Generalized Multi-Stage Lifecycle Gate:
        // Rank 4: Interview / Personality Test / DV / PET / PST / Skill Test
        if (preg_match('/\b(interview|personality\s*test|document\s*verification|\bdv\b|pet\b|pst\b|physical\s*(?:endurance|efficiency|standard)\s*test|medical\s*exam|skill\s*test|typing\s*test)\b/i', $tLower, $m)) {
            $stageRank = 4;
            $currentStageLabel = strtoupper($m[0]);
            $priorPatterns = [
                '/\b(cbt[ -]?[12]|tier[ -]?[12]|prelims?|preliminary|mains?|phase[ -]?[i]{1,2})\b/i'
            ];
        }
        // Rank 3: Tier 3 / Stage 3 / CBT 3 / Phase III / Descriptive
        elseif (preg_match('/\b(tier[ -]?3|stage[ -]?3|cbt[ -]?3|phase[ -]?iii|descriptive\s*(?:paper|exam))\b/i', $tLower, $m)) {
            $stageRank = 3;
            $currentStageLabel = strtoupper($m[0]);
            $priorPatterns = [
                '/\b(cbt[ -]?[12]|tier[ -]?[12]|prelims?|preliminary|phase[ -]?[i]{1,2})\b/i'
            ];
        }
        // Rank 2: Tier 2 / Stage 2 / CBT 2 / Phase II / Mains
        elseif (preg_match('/\b(cbt[ -]?2|tier[ -]?2|phase[ -]?ii|stage[ -]?2|mains?|main exam)\b/i', $tLower, $m)) {
            $stageRank = 2;
            $currentStageLabel = strtoupper($m[0]);
            $priorPatterns = [
                '/\b(cbt[ -]?1|tier[ -]?1|phase[ -]?i|stage[ -]?1|prelims?|preliminary)\b/i'
            ];
        }

        // Rank 0 or 1: Initial stage or un-staged exam -> skip phase mismatch
        if ($stageRank < 2) {
            return [];
        }

        // 1. Strip out non-body prose (related links, also read callouts, tickers, sidebars)
        $cleanHtml = preg_replace('/<div[^>]*class=[\'"][^\'"]*(also-read|related-articles|sidebar|ticker)[^\'"]*[\'"][^>]*>.*?<\/div>/is', '', $html);
        $plainText = strip_tags($cleanHtml);

        // 2. Break into individual sentences
        $sentences = preg_split('/(?<=[.?!])\s+/u', $plainText);

        foreach ($sentences as $sentence) {
            $s = trim($sentence);
            if (mb_strlen($s) < 20) continue;

            $mentionsPriorPhase = false;
            $priorPhaseName = '';

            foreach ($priorPatterns as $pattern) {
                if (preg_match($pattern, $s, $m)) {
                    $mentionsPriorPhase = true;
                    $priorPhaseName = strtoupper($m[0]);
                    break;
                }
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
                $issues[] = "Active {$priorPhaseName} assertion found in {$currentStageLabel} article: \"" . mb_substr($s, 0, 90) . "...\"";
            }
        }

        return $issues;
    }
}

// Adversarial Test Cases
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
    ],
    'Case F (Multi-Stage: SSC CGL Tier 3 with stale Tier 1 prose)' => [
        'title' => 'SSC CGL Tier 3 Descriptive Exam 2026: Admit Card & Schedule Out',
        'text' => 'Candidates can get the latest updates on the SSC CGL Tier 1 exam schedule and hall ticket download link here.'
    ],
    'Case G (Multi-Stage: UPSC Interview with stale Prelims admit card)' => [
        'title' => 'UPSC Civil Services 2026: Personality Test & Interview Schedule Out',
        'text' => 'Download your Prelims admit card download link and exam schedule on the official portal.'
    ]
];

echo "\n======================================================================\n";
echo "🧪 GENERALIZED MULTI-STAGE STALENESS DETECTOR TEST SUITE\n";
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
