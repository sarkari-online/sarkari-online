<?php
/**
 * Backfill: Link existing published articles to exam_cycles (Phase A)
 *
 * PURPOSE
 *   One-time CLI script that:
 *     1. Fetches all published articles (title + content)
 *     2. Extracts 1-3 keyword candidates per article (title-based NER)
 *     3. Calls ExamCycleResolverService::resolve() on each candidate
 *     4. Links the article to the resulting exam_cycle row via exam_cycle_articles
 *     5. Also detects article_role (NOTIFICATION / ADMIT_CARD / etc.) from intent keywords
 *
 *   THIS SCRIPT:
 *   - Does NOT edit article content (zero content changes)
 *   - Does NOT replace any table in articles
 *   - Does NOT call any LLM
 *   - Only writes to: exam_cycles + exam_cycle_articles
 *   - In --dry-run=true mode: zero DB writes, only report output
 *
 * USAGE
 *   php cron/backfill-exam-cycles.php                 # live mode
 *   php cron/backfill-exam-cycles.php --dry-run=true  # report only
 *
 * SAFE TO RE-RUN — uses INSERT IGNORE so duplicates are silently skipped.
 */

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\ExamCycleResolverService;

// ─── CLI args ─────────────────────────────────────────────────────────────────
$dryRun = false;
foreach ($argv as $arg) {
    if (str_contains($arg, '--dry-run=true')) {
        $dryRun = true;
    }
}

echo "=== Backfill: exam_cycles linking ===\n";
echo "Mode     : " . ($dryRun ? "DRY-RUN (no DB writes)" : "LIVE") . "\n";
echo "Started  : " . date('Y-m-d H:i:s') . "\n\n";

// ─── Role detection keyword map (mirrors IntentClassifierService logic) ────────
const ROLE_KEYWORDS = [
    'ADMIT_CARD'  => ['admit card','hall ticket','city slip','city intimation','e-call letter','call letter'],
    'RESULT'      => ['result','scorecard','merit list','rank list','marksheet','cut off','cutoff'],
    'ANSWER_KEY'  => ['answer key','response sheet','objection window','provisional key','final key'],
    'NOTIFICATION'=> ['recruitment','notification','apply online','vacancy','vacancies','bharti','online form'],
    'SYLLABUS'    => ['syllabus','exam pattern','marking scheme','scheme changed','new syllabus'],
    'CUTOFF'      => ['cutoff','cut off','qualifying marks','minimum marks'],
];

function detectRole(string $title): string
{
    $lower = mb_strtolower($title);
    $scores = [];
    foreach (ROLE_KEYWORDS as $role => $keywords) {
        $scores[$role] = 0;
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                $scores[$role]++;
            }
        }
    }
    arsort($scores);
    $topRole  = array_key_first($scores);
    $topScore = reset($scores);
    return ($topScore > 0) ? $topRole : 'GENERAL';
}

// ─── Extract candidate keywords from article title ────────────────────────────
// Strategy: title is the most reliable source; we strip role-words and hope
// what remains is "Authority ExamName Year" pattern.
function extractKeywords(string $title, string $content): array
{
    $candidates = [$title]; // always try the full title first

    // Also try to pull year-anchored substrings like "RRB Group D 2026"
    // by matching authority name + surrounding words
    if (preg_match('/\b([A-Z]{2,10}(?:\s+[A-Za-z]+){0,5}\s+20[2-4]\d)\b/', $title, $m)) {
        $candidates[] = trim($m[1]);
    }

    return array_unique(array_filter($candidates));
}

// ─── Fetch all published articles ─────────────────────────────────────────────
$articles = Database::fetchAll(
    "SELECT id, title, content, slug, status FROM articles WHERE status = 'published' ORDER BY id ASC"
);

$total     = count($articles);
$linked    = 0;
$unmatched = [];
$matched   = [];

echo "Found {$total} published articles. Processing...\n\n";

$resolver = new ExamCycleResolverService();

foreach ($articles as $article) {
    $articleId = (int)$article['id'];
    $title     = trim($article['title']);
    $content   = trim($article['content'] ?? '');

    $role      = detectRole($title);
    $keywords  = extractKeywords($title, $content);

    $resolved  = null;
    $usedKw    = null;

    foreach ($keywords as $kw) {
        $result = $resolver->resolve($kw);
        if ($result !== null) {
            $resolved = $result;
            $usedKw   = $kw;
            break;
        }
    }

    if ($resolved === null) {
        $unmatched[] = [
            'id'    => $articleId,
            'title' => $title,
            'slug'  => $article['slug'] ?? '',
            'reason'=> 'Could not parse authority_code or year from title',
        ];
        continue;
    }

    $examCycleId = (int)$resolved['id'];

    $matched[] = [
        'article_id'    => $articleId,
        'title'         => $title,
        'slug'          => $article['slug'] ?? '',
        'exam_cycle_id' => $examCycleId,
        'authority'     => $resolved['authority_code'],
        'exam_name'     => $resolved['exam_name'],
        'cycle_year'    => $resolved['cycle_year'],
        'phase'         => $resolved['current_phase'],
        'role'          => $role,
        'keyword_used'  => $usedKw,
    ];

    if (!$dryRun) {
        $resolver->linkArticle($examCycleId, $articleId, $role);
    }

    $linked++;
}

// ─── Report ───────────────────────────────────────────────────────────────────

$separator = str_repeat('─', 80);

echo $separator . "\n";
echo "SUMMARY\n";
echo $separator . "\n";
printf("  Total articles   : %d\n",  $total);
printf("  Successfully matched : %d (%.1f%%)\n", $linked, $total > 0 ? ($linked / $total * 100) : 0);
printf("  Unmatched        : %d\n",  count($unmatched));
echo "\n";

if (!empty($matched)) {
    echo $separator . "\n";
    echo "MATCHED ARTICLES (will be linked to exam_cycles)\n";
    echo $separator . "\n";
    printf("  %-6s  %-8s  %-10s  %-4s  %-30s  %-12s  %-6s  %s\n",
        'Art-ID', 'Cycle-ID', 'Authority', 'Year', 'Exam Name', 'Role', 'Phase', 'Article Title');
    echo str_repeat('-', 110) . "\n";

    foreach ($matched as $m) {
        printf("  %-6d  %-8d  %-10s  %-4d  %-30s  %-12s  %-25s  %s\n",
            $m['article_id'],
            $m['exam_cycle_id'],
            $m['authority'],
            $m['cycle_year'],
            mb_substr($m['exam_name'], 0, 30),
            $m['role'],
            $m['phase'],
            mb_substr($m['title'], 0, 50)
        );
    }
    echo "\n";
}

if (!empty($unmatched)) {
    echo $separator . "\n";
    echo "UNMATCHED ARTICLES (not linked — manual review recommended)\n";
    echo $separator . "\n";
    printf("  %-6s  %-55s  %s\n", 'Art-ID', 'Title', 'Reason');
    echo str_repeat('-', 110) . "\n";
    foreach ($unmatched as $u) {
        printf("  %-6d  %-55s  %s\n",
            $u['id'],
            mb_substr($u['title'], 0, 55),
            $u['reason']
        );
    }
    echo "\n";
}

echo $separator . "\n";
if ($dryRun) {
    echo "DRY-RUN COMPLETE — no data was written to the database.\n";
    echo "Re-run without --dry-run=true to apply changes.\n";
} else {
    echo "LIVE BACKFILL COMPLETE — exam_cycle_articles rows inserted.\n";
    echo "exam_cycles created/found and linked to {$linked} articles.\n";
}
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
