<?php
/**
 * Sarkari.online - Backfill Architecture: Audit and Migrate Published Articles
 *
 * Safe, idempotent CLI audit and migration engine for back-catalog articles.
 * Enforces Intent-Driven Architecture, OutlineContracts, and FeaturedSnippet alignment.
 *
 * Tier 1 (Mechanical Safe Fixes): Field-level repairs (Direct Answer, Excerpt, Status, Phase-mismatch).
 *                               Never touches content body HTML.
 * Tier 2 (Structural Violations): Forbidden headings & body-level staleness detected;
 *                               Flagged for review by default.
 *                               Regeneration requires explicit --regenerate flag.
 *
 * Usage:
 *   php cron/audit-and-migrate-published-articles.php                  # Safe Dry-Run on next 20 articles
 *   php cron/audit-and-migrate-published-articles.php --article-ids=703  # Test on specific article
 *   php cron/audit-and-migrate-published-articles.php --live --limit=20 # Commit Tier-1 fixes & flag Tier-2
 *   php cron/audit-and-migrate-published-articles.php --live --regenerate --limit=10 # Opt-in to regenerate Tier-2
 *   php cron/audit-and-migrate-published-articles.php --resolve-ids=703,704 # Manually mark resolved articles as compliant
 *   php cron/audit-and-migrate-published-articles.php --rollback=RUN_ID # Revert a specific batch run
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\ArticleIntent;
use App\Services\IntentClassifierService;
use App\Services\FeaturedSnippetService;
use App\Services\PipelineService;
use App\AI\ArticleGenerator;
use App\AI\Gemini;

const CURRENT_AUDIT_VERSION = 1;

// -----------------------------------------------------------------------------
// CLI Argument Parsing
// -----------------------------------------------------------------------------
$longopts = [
    'dry-run',
    'live',
    'limit:',
    'offset-id:',
    'article-ids:',
    'resolve-ids:',
    'regenerate',
    'force',
    'rollback:',
    'force-rollback',
    'run-id::',
    'help'
];

$options = getopt('', $longopts);

if (isset($options['help'])) {
    echo <<<HELP
Sarkari.online Published Articles Audit & Migration Engine

OPTIONS:
  --dry-run              (Default) Preview changes without committing to database
  --live                 Commit changes to database and save recovery snapshots
  --limit=N              Process N articles per batch (Default: 20)
  --offset-id=N          Keyset cursor start ID (Default: 0)
  --article-ids=X,Y,Z    Process specific comma-separated article IDs
  --resolve-ids=X,Y,Z    Manually transition flagged articles to compliant status
  --regenerate           Opt-in to auto-regenerate articles with Tier 2 structural violations
  --force                Re-audit articles even if already compliant at CURRENT_AUDIT_VERSION
  --rollback=RUN_ID      Rollback all changes made by a specific audit_run_id
  --force-rollback       Force rollback even if article was manually edited after snapshot
  --help                 Show this help screen

HELP;
    exit(0);
}

$isDryRun = !isset($options['live']);
$limit = (int)($options['limit'] ?? 20);
$startId = (int)($options['offset-id'] ?? 0);
$allowRegenerate = isset($options['regenerate']);
$forceReaudit = isset($options['force']);
$forceRollback = isset($options['force-rollback']);
$rollbackRunId = isset($options['rollback']) ? trim((string)$options['rollback']) : null;
$resolveIds = isset($options['resolve-ids']) ? array_filter(array_map('intval', explode(',', (string)$options['resolve-ids']))) : null;
$explicitIds = isset($options['article-ids']) ? array_filter(array_map('intval', explode(',', (string)$options['article-ids']))) : null;
$runId = $options['run-id'] ?? bin2hex(random_bytes(16));

// -----------------------------------------------------------------------------
// Database Initialization & Idempotency Safeguards
// -----------------------------------------------------------------------------
$pdo = Database::getConnection();

function ensureSchemaExists(PDO $pdo): void {
    // 1. Check if columns exist on articles table
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `articles` LIKE 'architecture_audit_status'");
        if (!$stmt->fetch()) {
            $pdo->exec("
                ALTER TABLE `articles`
                ADD COLUMN `architecture_audit_status` ENUM('unaudited','compliant','flagged','regenerated','skipped') NOT NULL DEFAULT 'unaudited',
                ADD COLUMN `architecture_audit_version` SMALLINT NULL,
                ADD COLUMN `architecture_audited_at` DATETIME NULL,
                ADD INDEX `idx_audit_status` (`architecture_audit_status`)
            ");
            echo "✅ Schema Migration: Added architecture_audit columns to `articles`.\n";
        }
    } catch (Throwable $e) {
        echo "⚠️ Note on schema columns: " . $e->getMessage() . "\n";
    }

    // 2. Check if snapshots table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `article_migration_snapshots` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `article_id` BIGINT NOT NULL,
                `snapshot_json` LONGTEXT NOT NULL,
                `audit_run_id` CHAR(36) NOT NULL,
                `action_taken` ENUM('tier1_autofix','tier2_regenerated','flagged_only') NOT NULL,
                `original_updated_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_article` (`article_id`),
                INDEX `idx_run` (`audit_run_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Add original_updated_at column if not exists
        $colCheck = $pdo->query("SHOW COLUMNS FROM `article_migration_snapshots` LIKE 'original_updated_at'");
        if (!$colCheck->fetch()) {
            $pdo->exec("ALTER TABLE `article_migration_snapshots` ADD COLUMN `original_updated_at` DATETIME NULL AFTER `action_taken`");
        }
    } catch (Throwable $e) {
        echo "⚠️ Note on snapshot table: " . $e->getMessage() . "\n";
    }
}

ensureSchemaExists($pdo);

// -----------------------------------------------------------------------------
// Manual Resolution Handler (--resolve-ids=X,Y,Z)
// -----------------------------------------------------------------------------
if (!empty($resolveIds)) {
    echo "================================================================================\n";
    echo "✅ MANUAL RESOLUTION: TRANSITIONING FLAGGED ARTICLES TO COMPLIANT\n";
    echo "================================================================================\n\n";

    $in = implode(',', $resolveIds);
    $stmt = $pdo->prepare("
        UPDATE `articles` SET
            `architecture_audit_status` = 'compliant',
            `architecture_audit_version` = :ver,
            `architecture_audited_at` = NOW()
        WHERE `id` IN ({$in})
    ");
    $stmt->execute([':ver' => CURRENT_AUDIT_VERSION]);
    $affected = $stmt->rowCount();

    echo "Successfully marked {$affected} articles as compliant (Version " . CURRENT_AUDIT_VERSION . ").\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// Rollback Handler (with Manual Edit Race Guard)
// -----------------------------------------------------------------------------
if (!empty($rollbackRunId)) {
    echo "================================================================================\n";
    echo "⏪ SARKARI.ONLINE MIGRATION ROLLBACK: RUN {$rollbackRunId}\n";
    echo "================================================================================\n\n";

    $stmt = $pdo->prepare("SELECT * FROM article_migration_snapshots WHERE audit_run_id = :run_id ORDER BY id DESC");
    $stmt->execute([':run_id' => $rollbackRunId]);
    $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($snapshots)) {
        die("❌ No snapshots found for audit_run_id: {$rollbackRunId}\n");
    }

    echo "Found " . count($snapshots) . " snapshot records to inspect.\n";
    $restoredCount = 0;
    $skippedCount = 0;

    foreach ($snapshots as $snap) {
        $orig = json_decode($snap['snapshot_json'], true);
        if (!$orig || empty($orig['id'])) {
            continue;
        }

        $artId = (int)$orig['id'];

        // Guard: Check if article was manually edited after snapshot
        $current = Database::fetchOne("SELECT updated_at FROM articles WHERE id = :id", ['id' => $artId]);
        if ($current && !empty($snap['original_updated_at'])) {
            $snapTime = strtotime($snap['original_updated_at']);
            $currTime = strtotime($current['updated_at']);
            // If current updated_at is more than 5 seconds newer than snapshot time
            if ($currTime > ($snapTime + 5) && !$forceRollback) {
                echo "  ⚠️ SKIPPED Article #{$artId}: Manually edited at {$current['updated_at']} after snapshot ({$snap['original_updated_at']}). Use --force-rollback to overwrite.\n";
                $skippedCount++;
                continue;
            }
        }

        $updStmt = $pdo->prepare("
            UPDATE `articles` SET
                `title` = :title,
                `excerpt` = :excerpt,
                `content` = :content,
                `meta_description` = :meta_description,
                `og_description` = :og_description,
                `status_text` = :status_text,
                `lifecycle_status` = :lifecycle_status,
                `raw_payload` = :raw_payload,
                `architecture_audit_status` = 'unaudited',
                `architecture_audit_version` = NULL,
                `architecture_audited_at` = NOW()
            WHERE `id` = :id
        ");

        $updStmt->execute([
            ':title' => $orig['title'] ?? '',
            ':excerpt' => $orig['excerpt'] ?? '',
            ':content' => $orig['content'] ?? '',
            ':meta_description' => $orig['meta_description'] ?? '',
            ':og_description' => $orig['og_description'] ?? '',
            ':status_text' => $orig['status_text'] ?? null,
            ':lifecycle_status' => $orig['lifecycle_status'] ?? 'active',
            ':raw_payload' => is_array($orig['raw_payload'] ?? null) ? json_encode($orig['raw_payload']) : ($orig['raw_payload'] ?? null),
            ':id' => $artId
        ]);

        $restoredCount++;
        echo "  ↺ Restored Article #{$artId}: " . mb_substr($orig['title'] ?? '', 0, 50) . "...\n";
    }

    echo "\n✅ Rollback Result: {$restoredCount} restored, {$skippedCount} skipped.\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// Structural Violation Detector (Deterministic, Zero LLM cost)
// -----------------------------------------------------------------------------
class OutlineViolationDetector {
    private const FORBIDDEN_HEADINGS = [
        ArticleIntent::ADMIT_CARD->value => [
            'how to apply',
            'online application',
            'step-by-step online application',
            'application fee',
            'detailed eligibility criteria',
            'eligibility criteria',
            'age limits & qualifications',
            'age limit'
        ],
        ArticleIntent::RESULT_CUTOFF->value => [
            'how to apply',
            'online application',
            'application fee',
            'eligibility criteria'
        ],
        ArticleIntent::ANSWER_KEY->value => [
            'how to apply',
            'online application',
            'application fee',
            'eligibility criteria'
        ]
    ];

    public static function findViolations(string $html, ArticleIntent $intent): array {
        $forbidden = self::FORBIDDEN_HEADINGS[$intent->value] ?? [];
        if (empty($forbidden)) {
            return [];
        }

        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $matches);
        $found = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $heading) {
                $normalized = mb_strtolower(trim(strip_tags($heading)));
                foreach ($forbidden as $phrase) {
                    if (str_contains($normalized, $phrase)) {
                        $found[] = trim(strip_tags($heading));
                        break;
                    }
                }
            }
        }
        return array_unique($found);
    }
}

// -----------------------------------------------------------------------------
// Body-Level Staleness Detector (Sentence-level prose scanner with context gates)
// -----------------------------------------------------------------------------
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

    public static function check(string $html, string $title, ArticleIntent $intent): array {
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

        // 2. Tokenize into individual sentences
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

            // Check if sentence has comparative or historical markers (e.g. "unlike CBT-1", "qualified CBT-1")
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

// -----------------------------------------------------------------------------
// Article Auditor (Tier 1 + Tier 2 Analyzer)
// -----------------------------------------------------------------------------
class ArticleAuditResult {
    public function __construct(
        public readonly int $articleId,
        public readonly ArticleIntent $detectedIntent,
        public readonly ?string $storedIntent,
        public readonly array $tier1Fixes,      // field => ['old' => ..., 'new' => ...]
        public readonly array $tier2Violations, // forbidden headings & body staleness
        public readonly bool $needsRegeneration
    ) {}
}

class ArticleAuditor {
    private IntentClassifierService $classifier;

    public function __construct(IntentClassifierService $classifier) {
        $this->classifier = $classifier;
    }

    public function audit(array $article): ArticleAuditResult {
        $title = $article['title'] ?? '';
        $content = $article['content'] ?? '';
        $excerpt = $article['excerpt'] ?? '';
        $rawPayload = is_array($article['raw_payload'] ?? null)
            ? $article['raw_payload']
            : (json_decode($article['raw_payload'] ?? '', true) ?: []);

        // 1. Classify intent from title + excerpt
        $detectedIntent = $this->classifier->classify($title, mb_substr(strip_tags($content), 0, 400));
        $storedIntent = $rawPayload['_intent'] ?? ($article['category_slug'] ?? null);

        // 2. Structural violation check (Tier 2: Heading scans)
        $tier2Violations = OutlineViolationDetector::findViolations($content, $detectedIntent);

        // 3. Body-level staleness check (Tier 2: Read-only detection)
        $bodyStaleness = BodyStalenessDetector::check($content, $title, $detectedIntent);
        foreach ($bodyStaleness as $staleIssue) {
            $tier2Violations[] = "[Body Staleness] " . $staleIssue;
        }

        // 4. Field-level mechanical checks (Tier 1: Safe field fixes only)
        $tier1Fixes = [];
        $tLower = strtolower($title);
        $eLower = strtolower($excerpt);

        // A. Phase-mismatch detection (e.g. CBT 2 in title, but CBT 1 in excerpt)
        $isCbt2Title = str_contains($tLower, 'cbt 2') || str_contains($tLower, 'cbt-2') || str_contains($tLower, 'stage 2') || str_contains($tLower, 'tier 2') || str_contains($tLower, 'tier-2');
        $isCbt1Excerpt = str_contains($eLower, 'cbt 1') || str_contains($eLower, 'cbt-1') || str_contains($eLower, 'tier 1') || str_contains($eLower, 'tier-1') || str_contains($eLower, 'prelims');

        // Check if raw_payload direct_answer has phase mismatch
        $rawDirectAnswer = $rawPayload['direct_answer'] ?? '';
        $rawDaLower = strtolower($rawDirectAnswer);
        $isRawDaMismatched = $isCbt2Title && (str_contains($rawDaLower, 'cbt 1') || str_contains($rawDaLower, 'cbt-1'));

        if (($isCbt2Title && $isCbt1Excerpt) || $isRawDaMismatched || mb_strlen($excerpt) < 30) {
            $freshLead = $this->extractVettedLead($content, $title);
            if (!empty($freshLead) && $freshLead !== $excerpt) {
                $tier1Fixes['excerpt'] = ['old' => $excerpt, 'new' => $freshLead];
                $tier1Fixes['meta_description'] = ['old' => $article['meta_description'] ?? '', 'new' => mb_substr($freshLead, 0, 250)];
                $tier1Fixes['og_description'] = ['old' => $article['og_description'] ?? '', 'new' => mb_substr($freshLead, 0, 250)];
            }
        }

        // B. Status text staleness
        $currentStatus = $article['status_text'] ?? '';
        $expectedStatus = $this->deriveStatusText($title, $content, $article['lifecycle_status'] ?? 'active');
        if (!empty($expectedStatus) && $expectedStatus !== $currentStatus) {
            $tier1Fixes['status_text'] = ['old' => $currentStatus, 'new' => $expectedStatus];
        }

        // C. Clean raw_payload direct_answer if it was mismatched
        if ($isRawDaMismatched) {
            $freshDirect = $tier1Fixes['excerpt']['new'] ?? $this->extractVettedLead($content, $title);
            $newPayload = $rawPayload;
            $newPayload['direct_answer'] = $freshDirect;
            $tier1Fixes['raw_payload'] = ['old' => '(mismatched raw direct_answer)', 'new' => '(aligned with vetted lead)'];
        }

        // D. Outdated future dates (strictly scoped to excerpt forward-looking milestones)
        if (preg_match('/\b(202[345])\b(?=.*?(exam|admit card|hall ticket|city slip|result|schedule))/i', $excerpt, $ym)) {
            $fixedExcerpt = preg_replace('/\b(202[345])\b(?=.*?(exam|admit card|hall ticket|city slip|result|schedule))/i', '2026', $excerpt);
            if ($fixedExcerpt !== $excerpt && !isset($tier1Fixes['excerpt'])) {
                $tier1Fixes['excerpt'] = ['old' => $excerpt, 'new' => $fixedExcerpt];
            }
        }

        $needsRegen = !empty($tier2Violations);

        return new ArticleAuditResult(
            articleId: (int)$article['id'],
            detectedIntent: $detectedIntent,
            storedIntent: $storedIntent,
            tier1Fixes: $tier1Fixes,
            tier2Violations: $tier2Violations,
            needsRegeneration: $needsRegen
        );
    }

    private function extractVettedLead(string $content, string $title): string {
        $plain = strip_tags($content);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $sentences = preg_split('/(?<=[.?!])\s+/', $plain);
        if (!empty($sentences[0]) && mb_strlen($sentences[0]) > 40) {
            $lead = trim($sentences[0]);
            if (!empty($sentences[1]) && mb_strlen($lead) < 120) {
                $lead .= ' ' . trim($sentences[1]);
            }
            if (mb_strlen($lead) <= 280) {
                return $lead;
            }
        }
        return "Official updates regarding {$title} have been issued. Eligible candidates can check their schedule and guidelines on the statutory portal.";
    }

    private function deriveStatusText(string $title, string $content, string $lifecycle): string {
        $t = strtolower($title);
        if (str_contains($t, 'city slip') || str_contains($t, 'city intimation')) {
            return 'Exam City Slip Active';
        }
        if (str_contains($t, 'admit card') || str_contains($t, 'hall ticket')) {
            if ($lifecycle === 'admit_card_released') {
                return 'Hall Ticket Download Active';
            }
            return 'Admit Card: Download Link Upcoming';
        }
        if (str_contains($t, 'result')) {
            if ($lifecycle === 'result_released') {
                return 'Scorecard & Merit List Declared';
            }
            return 'Result: Under Evaluation / Awaited';
        }
        if (str_contains($t, 'answer key')) {
            return 'Provisional Key & Objection Live';
        }
        if ($lifecycle === 'closed') {
            return 'Application Window Closed';
        }
        return 'Official Gazetted Update Live';
    }
}

// -----------------------------------------------------------------------------
// Article Migration Writer
// -----------------------------------------------------------------------------
class ArticleMigrationWriter {
    public function __construct(
        private PDO $db,
        private string $runId
    ) {}

    public function saveSnapshot(array $article, string $actionTaken): void {
        $stmt = $this->db->prepare("
            INSERT INTO `article_migration_snapshots`
            (`article_id`, `snapshot_json`, `audit_run_id`, `action_taken`, `original_updated_at`, `created_at`)
            VALUES (:aid, :snap, :run_id, :action, :up_at, NOW())
        ");
        $stmt->execute([
            ':aid' => (int)$article['id'],
            ':snap' => json_encode($article, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':run_id' => $this->runId,
            ':action' => $actionTaken,
            ':up_at' => $article['updated_at'] ?? null
        ]);
    }

    public function applyTier1Fixes(array $article, ArticleAuditResult $result): void {
        if (empty($result->tier1Fixes)) {
            return;
        }

        $this->saveSnapshot($article, 'tier1_autofix');

        $updates = [];
        $params = [':id' => $result->articleId];

        if (isset($result->tier1Fixes['excerpt'])) {
            $updates[] = "`excerpt` = :excerpt";
            $params[':excerpt'] = $result->tier1Fixes['excerpt']['new'];
        }
        if (isset($result->tier1Fixes['meta_description'])) {
            $updates[] = "`meta_description` = :meta_description";
            $params[':meta_description'] = $result->tier1Fixes['meta_description']['new'];
        }
        if (isset($result->tier1Fixes['og_description'])) {
            $updates[] = "`og_description` = :og_description";
            $params[':og_description'] = $result->tier1Fixes['og_description']['new'];
        }
        if (isset($result->tier1Fixes['status_text'])) {
            $updates[] = "`status_text` = :status_text";
            $params[':status_text'] = $result->tier1Fixes['status_text']['new'];
        }
        if (isset($result->tier1Fixes['raw_payload'])) {
            $raw = is_array($article['raw_payload'] ?? null)
                ? $article['raw_payload']
                : (json_decode($article['raw_payload'] ?? '', true) ?: []);
            $raw['direct_answer'] = $result->tier1Fixes['excerpt']['new'] ?? ($raw['direct_answer'] ?? '');
            $updates[] = "`raw_payload` = :raw_payload";
            $params[':raw_payload'] = json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $updates[] = "`architecture_audited_at` = NOW()";

        if (!empty($updates)) {
            $sql = "UPDATE `articles` SET " . implode(', ', $updates) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    public function markCompliant(int $articleId): void {
        $stmt = $this->db->prepare("
            UPDATE `articles` SET
                `architecture_audit_status` = 'compliant',
                `architecture_audit_version` = :ver,
                `architecture_audited_at` = NOW()
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':ver' => CURRENT_AUDIT_VERSION,
            ':id' => $articleId
        ]);
    }

    public function flagForReview(int $articleId, ArticleAuditResult $result): void {
        $stmt = $this->db->prepare("
            UPDATE `articles` SET
                `architecture_audit_status` = 'flagged',
                `architecture_audit_version` = :ver,
                `architecture_audited_at` = NOW()
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':ver' => CURRENT_AUDIT_VERSION,
            ':id' => $articleId
        ]);
    }

    public function regenerate(array $article, ArticleAuditResult $result): void {
        $this->saveSnapshot($article, 'tier2_regenerated');

        $generator = new ArticleGenerator();
        $sourceInfo = [
            'source_name' => $article['source_name'] ?? 'Statutory Authority',
            'source_url' => $article['source_url'] ?? '',
            'snippet' => mb_substr(strip_tags($article['content']), 0, 300)
        ];

        $regen = $generator->generate($article['title'], $sourceInfo);
        $cleanHtml = PipelineService::autoCleanLintIssues($regen['article_html'], $result->detectedIntent->value);

        $stmt = $this->db->prepare("
            UPDATE `articles` SET
                `content` = :content,
                `excerpt` = :excerpt,
                `meta_description` = :meta_description,
                `og_description` = :og_description,
                `architecture_audit_status` = 'regenerated',
                `architecture_audit_version` = :ver,
                `architecture_audited_at` = NOW()
            WHERE `id` = :id
        ");

        $stmt->execute([
            ':content' => $cleanHtml,
            ':excerpt' => $regen['excerpt'] ?? $article['excerpt'],
            ':meta_description' => mb_substr($regen['meta_description'] ?? ($article['meta_description'] ?? ''), 0, 255),
            ':og_description' => mb_substr($regen['meta_description'] ?? ($article['og_description'] ?? ''), 0, 255),
            ':ver' => CURRENT_AUDIT_VERSION,
            ':id' => (int)$article['id']
        ]);
    }
}

// -----------------------------------------------------------------------------
// Output Reporter
// -----------------------------------------------------------------------------
class AuditReporter {
    public static function printArticleSummary(ArticleAuditResult $result, array $article, bool $isDryRun): void {
        echo "── Article #{$result->articleId}: {$article['title']}\n";
        echo "   Intent: detected={$result->detectedIntent->value} (stored: " . ($result->storedIntent ?? 'none') . ")\n";

        foreach ($result->tier1Fixes as $field => $fix) {
            echo "   [TIER 1] {$field}:\n";
            echo "     - OLD: " . self::truncate($fix['old']) . "\n";
            echo "     + NEW: " . self::truncate($fix['new']) . "\n";
        }

        if (!empty($result->tier2Violations)) {
            echo "   [TIER 2] ⚠️ Violations found:\n";
            foreach ($result->tier2Violations as $viol) {
                echo "     • " . self::truncate($viol) . "\n";
            }
            echo "     → FLAGGED for review" . ($isDryRun ? " (use --live to commit, --regenerate to auto-fix)" : "") . "\n";
        }

        if (empty($result->tier1Fixes) && empty($result->tier2Violations)) {
            echo "   ✓ Fully compliant, no changes needed\n";
        }
        echo "\n";
    }

    private static function truncate(string $s, int $len = 95): string {
        $clean = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($clean) > $len ? mb_substr($clean, 0, $len) . '…' : $clean;
    }
}

// -----------------------------------------------------------------------------
// Execution Engine (Cursor Pagination)
// -----------------------------------------------------------------------------
echo "================================================================================\n";
echo "🚀 SARKARI.ONLINE BACK-CATALOG AUDIT & MIGRATION ENGINE\n";
echo "📅 Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "🔧 Mode     : " . ($isDryRun ? "READ-ONLY PREFLIGHT (DRY-RUN)" : "LIVE EXECUTION (COMMITTING CHANGES)") . "\n";
echo "🆔 Run ID   : {$runId}\n";
if ($allowRegenerate) {
    echo "⚡ Warning  : --regenerate is ACTIVE. Tier 2 structural violation articles will be rebuilt.\n";
}
echo "================================================================================\n\n";

if (!$isDryRun) {
    echo "⚠️  LIVE MODE ACTIVE — database writes will occur in 3 seconds. Press Ctrl+C to abort...\n";
    sleep(3);
}

$classifier = new IntentClassifierService();
$auditor = new ArticleAuditor($classifier);
$writer = new ArticleMigrationWriter($pdo, $runId);

function fetchBatch(PDO $pdo, int $lastId, int $limit, ?array $explicitIds, bool $force): array {
    if (!empty($explicitIds)) {
        $in = implode(',', array_map('intval', $explicitIds));
        $stmt = $pdo->query("SELECT * FROM `articles` WHERE `id` IN ({$in}) ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $where = "WHERE `id` > :lastId AND `status` = 'published'";
    if (!$force) {
        $where .= " AND (`architecture_audit_version` IS NULL OR `architecture_audit_version` < :ver)";
    }

    $sql = "SELECT * FROM `articles` {$where} ORDER BY `id` ASC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lastId', $lastId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    if (!$force) {
        $stmt->bindValue(':ver', CURRENT_AUDIT_VERSION, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$processed = 0;
$tier1FixedCount = 0;
$flaggedCount = 0;
$regeneratedCount = 0;
$lastId = $startId;

do {
    $batch = fetchBatch($pdo, $lastId, $limit, $explicitIds, $forceReaudit);
    if (empty($batch)) {
        break;
    }

    foreach ($batch as $article) {
        $artId = (int)$article['id'];

        // Shared advisory lock: standard across audit and pipeline writes
        $lockStmt = $pdo->query("SELECT GET_LOCK('sarkari_article_write_{$artId}', 0)");
        if (!$lockStmt->fetchColumn()) {
            echo "  ⤷ Skipping #{$artId} — locked by another process\n";
            continue;
        }

        try {
            $result = $auditor->audit($article);
            AuditReporter::printArticleSummary($result, $article, $isDryRun);

            if (!$isDryRun) {
                // Tier 1 field fixes
                if (!empty($result->tier1Fixes)) {
                    $writer->applyTier1Fixes($article, $result);
                    $tier1FixedCount++;
                }

                // Tier 2 handling
                if ($result->needsRegeneration) {
                    $flaggedCount++;
                    if ($allowRegenerate) {
                        $writer->regenerate($article, $result);
                        $regeneratedCount++;
                    } else {
                        $writer->flagForReview($artId, $result);
                    }
                } else {
                    // Guard: A flagged article can NEVER be silently unflagged by a routine run!
                    // Only an explicit --force re-audit or --resolve-ids can transition flagged -> compliant.
                    $wasFlagged = ($article['architecture_audit_status'] ?? '') === 'flagged';
                    if ($wasFlagged && !$forceReaudit) {
                        echo "   🔒 Status: Article #{$artId} remains FLAGGED (requires --force or --resolve-ids to unflag)\n";
                    } else {
                        $writer->markCompliant($artId);
                    }
                }
            } else {
                if (!empty($result->tier1Fixes)) $tier1FixedCount++;
                if ($result->needsRegeneration) $flaggedCount++;
            }

            $processed++;
        } catch (Throwable $e) {
            echo "❌ Error auditing article #{$artId}: " . $e->getMessage() . "\n";
            Logger::error("Audit failed for article #{$artId}: " . $e->getMessage());
        } finally {
            $pdo->exec("SELECT RELEASE_LOCK('sarkari_article_write_{$artId}')");
        }

        $lastId = $artId;
    }

} while (empty($explicitIds) && count($batch) === $limit);

echo "\n================================================================================\n";
echo "🏁 AUDIT BATCH COMPLETE (Run ID: {$runId})\n";
echo "Articles Processed     : {$processed}\n";
echo "Tier 1 Fields Repaired : {$tier1FixedCount}\n";
echo "Tier 2 Flagged Articles: {$flaggedCount}\n";
if ($allowRegenerate && !$isDryRun) {
    echo "Regenerated Articles   : {$regeneratedCount}\n";
}
echo "Mode                   : " . ($isDryRun ? "DRY-RUN (Zero writes committed)" : "LIVE (Snapshots saved)") . "\n";
if (!$isDryRun) {
    echo "To rollback this run   : php cron/audit-and-migrate-published-articles.php --rollback={$runId}\n";
} else {
    echo "To apply changes       : Re-run with --live\n";
}
echo "================================================================================\n";
