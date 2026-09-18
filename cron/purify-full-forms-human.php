<?php
declare(strict_types=1);

/**
 * Sarkari.online — Purify Full Forms Human Editorial Cleaner
 *
 * Scans all glossary_terms (A-Z Full Forms) and strips out AI coaching fluff:
 * - "I've seen many candidates get rejected..."
 * - "Don't underestimate the..."
 * - "It's a classic mistake..."
 * - "When the notification drops, the server traffic is insane..."
 * - "The interview panel isn't looking for bookish knowledge..."
 * - "Don't take the Group Task lightly..."
 * - "every engineering graduate dreams of joining..."
 * - Preachy ungrounded advice.
 *
 * Converts all overview, eligibility, selection, and syllabus sections to
 * objective 3rd-person Indian education journalism / gazette standards.
 *
 * Usage:
 *   php cron/purify-full-forms-human.php --dry-run
 *   php cron/purify-full-forms-human.php --live
 *   php cron/purify-full-forms-human.php --acronym=HPCL --live
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

$dryRun = true;
$targetAcronym = null;

foreach ($argv as $arg) {
    if ($arg === '--live' || $arg === '--execute' || $arg === '--dry-run=false') {
        $dryRun = false;
    } elseif ($arg === '--dry-run' || $arg === '--dry-run=true') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--acronym=')) {
        $targetAcronym = strtoupper(trim(substr($arg, 10)));
    }
}

echo "================================================================================\n";
echo "🧹 SARKARI.ONLINE — FULL FORMS HUMAN-EDITORIAL PURIFIER\n";
echo "Mode   : " . ($dryRun ? "\033[33m🔍 DRY-RUN (Preview changes)\033[0m" : "\033[32m⚡ LIVE UPDATE (DB will be updated)\033[0m") . "\n";
if ($targetAcronym) {
    echo "Target : Acronym {$targetAcronym}\n";
} else {
    echo "Scope  : All Glossary Terms (A-Z Directory)\n";
}
echo "================================================================================\n\n";

$sql = "SELECT id, acronym, full_form_en, category, conducting_body, overview, eligibility_criteria, selection_process, syllabus_snapshot FROM glossary_terms WHERE 1=1";
$params = [];
if ($targetAcronym) {
    $sql .= " AND acronym = :acr";
    $params['acr'] = $targetAcronym;
}
$sql .= " ORDER BY id ASC";

try {
    $terms = Database::fetchAll($sql, $params);
} catch (\Throwable $e) {
    echo "❌ Error querying glossary_terms: " . $e->getMessage() . "\n";
    exit(1);
}

$totalTerms = count($terms);
echo "Found {$totalTerms} glossary term(s) to evaluate.\n\n";

$refactorCount = 0;
$cleanCount = 0;

$mentorPurgeRegexes = [
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:I\'ve seen many candidates|I have seen many candidates|don\'t underestimate|it\'s a classic mistake|classic mistake|traffic is insane|server traffic is insane)\b[^<\.!?]*[\.!?]\s*/iu',
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:the interview panel isn\'t looking for bookish knowledge|bookish knowledge; they want practical|don\'t take the (?:group task|interview|exam) lightly)\b[^<\.!?]*[\.!?]\s*/iu',
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:there\'s no second chance|there is no second chance|dreams of joining|every engineering graduate dreams)\b[^<\.!?]*[\.!?]\s*/iu',
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:don\'t wait for the last day to upload|keep your documents scanned and ready|ensure your photograph background is plain white)\b[^<\.!?]*[\.!?]\s*/iu',
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:any discrepancy here can lead to immediate disqualification|ensure every single certificate is original and matches)\b[^<\.!?]*[\.!?]\s*/iu',
    '/(?<=^|>|\.|\!|\?)\s*[^<\.!?]*?\b(?:stay updated with the official portal, as they don\'t send personal reminders)\b[^<\.!?]*[\.!?]\s*/iu',
];

function sanitizeGlossaryField(string $text, array $patterns): string {
    $cleaned = $text;
    foreach ($patterns as $pat) {
        for ($i = 0; $i < 3; $i++) {
            if (preg_match($pat, $cleaned)) {
                $cleaned = preg_replace($pat, ' ', $cleaned);
            } else {
                break;
            }
        }
    }
    // Clean up multiple spaces or leading/trailing whitespace
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    return trim($cleaned);
}

foreach ($terms as $term) {
    $termId = (int)$term['id'];
    $acr = $term['acronym'];
    $fullEn = $term['full_form_en'];

    $modified = false;
    $changes = [];

    $fields = ['overview', 'eligibility_criteria', 'selection_process', 'syllabus_snapshot'];
    $updates = [];

    // Special authoritative gazette rewrite for HPCL
    if ($acr === 'HPCL') {
        $updates['overview'] = "Hindustan Petroleum Corporation Limited (HPCL) is a Maharatna Central Public Sector Enterprise (CPSE) under the Ministry of Petroleum and Natural Gas, Government of India. Headquartered in Mumbai, Maharashtra, HPCL operates oil refining, fuel pipeline networks, and downstream energy marketing infrastructure across India. Engineering officer recruitments are conducted annually via GATE score shortlisting or statutory Computer Based Testing followed by corporate assessment rounds.";
        $updates['eligibility_criteria'] = "Candidates require a full-time four-year B.E. or B.Tech degree from an AICTE/UGC recognized university in Mechanical, Civil, Electrical, Chemical, or Instrumentation Engineering. General category applicants must secure at least 60% aggregate marks, relaxed to 50% for SC, ST, and PwBD categories. The standard age limit is 25 to 27 years for the general category, with statutory upper age relaxations of 3 years for OBC-NCL, 5 years for SC/ST, and 10 years for PwBD applicants.";
        $updates['selection_process'] = "The selection procedure comprises three sequential stages: (1) Computer Based Test (CBT) or shortlisting based on valid GATE scores; (2) Group Task (GT) and Personal Interview (PI) evaluating technical domain competencies; and (3) Pre-employment Medical Examination and Document Verification of original academic certificates and category credentials.";
        $updates['syllabus_snapshot'] = "The technical domain evaluates core engineering fundamentals according to the candidate's discipline, including Thermodynamics, Fluid Mechanics, Strength of Materials, Power Systems, and Circuit Theory. The general aptitude component assesses Quantitative Aptitude, Logical Reasoning, Data Interpretation, English Language proficiency, and current affairs relevant to the energy sector.";
        $modified = true;
        $changes[] = "Replaced HPCL coaching monologue with authoritative 3rd-person gazette reporting";
    } else {
        foreach ($fields as $f) {
            $original = (string)($term[$f] ?? '');
            if (empty($original)) continue;

            $purified = sanitizeGlossaryField($original, $mentorPurgeRegexes);
            if ($purified !== $original) {
                $updates[$f] = $purified;
                $modified = true;
                $changes[] = "Stripped coaching/mentor phrases from {$f}";
            }
        }
    }

    if ($modified) {
        $refactorCount++;
        echo "📄 [#{$termId}] {$acr} ({$fullEn})\n";
        foreach ($changes as $ch) {
            echo "   ✂️ {$ch}\n";
        }

        if (!$dryRun) {
            $setClauses = [];
            $setParams = ['id' => $termId];
            foreach ($updates as $k => $v) {
                $setClauses[] = "`{$k}` = :{$k}";
                $setParams[$k] = $v;
            }
            $setClauses[] = "`updated_at` = NOW()";
            $updateSql = "UPDATE `glossary_terms` SET " . implode(', ', $setClauses) . " WHERE `id` = :id";
            Database::execute($updateSql, $setParams);
            echo "   💾 SAVED: Database updated successfully.\n\n";
        } else {
            echo "   🔍 DRY-RUN: Changes previewed.\n\n";
        }
    } else {
        $cleanCount++;
    }
}

echo "================================================================================\n";
echo "📊 FULL FORMS REPROCESSOR SUMMARY\n";
echo "Total Evaluated : {$totalTerms}\n";
echo "Refactored      : {$refactorCount}\n";
echo "Already Clean   : {$cleanCount}\n";
echo "================================================================================\n";
