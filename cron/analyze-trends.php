<?php
/**
 * EduPulse - Trend Analysis AI Cron Worker (Phase 4)
 * CLI executable: Selects 'detected' trends, locks them as 'analyzing', evaluates editorial viability
 * via TopicAnalyzer, and transitions status to 'approved' or 'rejected'.
 * 
 * Usage: php cron/analyze-trends.php
 */

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'edupulse_cron_secret')) {
    http_response_code(403);
    die("Access Denied: Cron worker can only be executed via CLI.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\AI\TopicAnalyzer;
use App\Services\TrendService;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Helpers\Env;
use App\Helpers\Logger;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting EduPulse Trend Analysis Worker...\n";
Logger::info("Cron analyze-trends started");

$maxPerRun = (int)Env::get('MAX_TRENDS_PER_RUN', 10);
$minScore = (int)Env::get('MIN_TREND_SCORE', 75);

$pendingTrends = TrendService::getPendingForAnalysis($maxPerRun);
$totalPending = count($pendingTrends);

if ($totalPending === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending 'detected' trends to analyze.\n";
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'analyze-trends.php') {
        exit(0);
    }
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Processing {$totalPending} pending trends...\n";

$analyzer = new TopicAnalyzer();
$approvedCount = 0;
$rejectedCount = 0;
$failedCount = 0;

// Fetch recent existing articles to check duplication in prompt
$existingArticles = ArticleService::getLatestPublished(20);

foreach ($pendingTrends as $trend) {
    $trendId = (int)$trend['id'];
    $keyword = $trend['keyword'];

    echo "  -> Analyzing trend #{$trendId}: '{$keyword}'... ";

    // 1. Lock trend as 'analyzing'
    TrendService::markStatus($trendId, 'analyzing');

    try {
        $sourceInfo = [
            'source_name' => $trend['source'],
            'source_url' => $trend['url'],
            'snippet' => $trend['raw_payload'] ?? ''
        ];

        // 2. Call TopicAnalyzer
        $analysis = $analyzer->analyze($keyword, $sourceInfo, $existingArticles);

        $isRecommended = !empty($analysis['publish_recommendation']);
        $priorityScore = (int)($analysis['priority_score'] ?? 0);
        $categorySlug = $analysis['category'] ?? ($trend['category_hint'] ?: 'exam-results');

        // Resolve category ID
        $cat = CategoryService::getBySlug($categorySlug);
        $categoryId = $cat ? (int)$cat['id'] : $trend['category_id'];

        if ($isRecommended && $priorityScore >= $minScore) {
            // 3a. Approve trend
            TrendService::markStatus($trendId, 'approved', [
                'category_id' => $categoryId,
                'trend_score' => $priorityScore,
                'raw_payload' => $analysis
            ]);
            echo "[APPROVED] (Score: {$priorityScore}, Category: {$categorySlug})\n";
            $approvedCount++;
        } else {
            // 3b. Reject trend
            TrendService::markStatus($trendId, 'rejected', [
                'category_id' => $categoryId,
                'trend_score' => $priorityScore,
                'raw_payload' => $analysis
            ]);
            $reason = $analysis['reasoning'] ?? 'Below priority threshold';
            echo "[REJECTED] (Reason: {$reason})\n";
            $rejectedCount++;
        }

    } catch (Throwable $e) {
        TrendService::markStatus($trendId, 'failed', [
            'raw_payload' => ['error' => $e->getMessage()]
        ]);
        echo "[FAILED] ({$e->getMessage()})\n";
        Logger::error("Trend #{$trendId} analysis failed: " . $e->getMessage());
        $failedCount++;
    }

    // Gentle 3-second pacing between trends to respect Gemini rate limits
    sleep(3);
}

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Finished: {$approvedCount} approved, {$rejectedCount} rejected, {$failedCount} failed in {$elapsed}s.\n";
    Logger::info("Cron analyze-trends finished", [
        'approved' => $approvedCount,
        'rejected' => $rejectedCount,
        'failed' => $failedCount,
        'elapsed' => $elapsed
    ]);
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'analyze-trends.php') {
    exit(0);
}
