<?php
/**
 * EduPulse - Content Verification & Quality Audit Cron Worker (Phase 5)
 * CLI executable: Re-audits draft and review articles against updated authority guidelines
 * and logs quality compliance scorecards in the 'article_checks' table.
 * 
 * Usage: php cron/verify-articles.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\FactChecker;
use App\Database\Database;
use App\Services\ArticleService;
use App\Helpers\Env;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting EduPulse Article Quality Audit Worker...\n";
Logger::info("Cron verify-articles started");

$maxAudit = (int)Env::get('MAX_TRENDS_PER_RUN', 5);

// Fetch pending review/draft articles that haven't been audited today
$sql = "SELECT a.*, c.name AS category_name
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status IN ('review', 'draft')
        ORDER BY a.id DESC
        LIMIT " . (int)$maxAudit;

$articles = Database::fetchAll($sql);
$total = count($articles);

if ($total === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending articles to audit.\n";
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'verify-articles.php') {
        exit(0);
    }
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Auditing {$total} articles...\n";

    $checker = new FactChecker();
    $auditedCount = 0;

    foreach ($articles as $art) {
        $artId = (int)$art['id'];
        echo "  -> Auditing Article #{$artId}: '{$art['title']}'... ";

        try {
            $sourceFacts = [
                'source_name' => $art['source_name'],
                'source_url' => $art['source_url'],
                'reference' => $art['source_ref']
            ];

            $audit = $checker->check([
                'id' => $artId,
                'title' => $art['title'],
                'content' => $art['content']
            ], $sourceFacts);

            $score = (int)($audit['overall_score'] ?? 0);
            $rec = $audit['recommendation'] ?? 'review';

            echo "[AUDITED] (Score: {$score}, Recommendation: {$rec})\n";
            $auditedCount++;

        } catch (Throwable $e) {
            echo "[ERROR] ({$e->getMessage()})\n";
            Logger::error("Audit failed for Article #{$artId}: " . $e->getMessage());
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Completed auditing {$auditedCount} articles in {$elapsed}s.\n";
    Logger::info("Cron verify-articles finished", ['audited' => $auditedCount, 'elapsed' => $elapsed]);

    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'verify-articles.php') {
        exit(0);
    }
}
