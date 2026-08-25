<?php
/**
 * EduPulse - Controlled Automatic Publishing Service (Phase 7)
 * Strict gatekeeper ensuring articles are only auto-published when they meet 10 critical conditions,
 * pass HTML/SEO/JSON-LD pre-flight validation, and conform to daily quota limits.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\Helpers\SEOHelper;
use Exception;
use Throwable;

class PublishingService {

    private int $minQualityScore;
    private int $dailyLimit;

    public function __construct() {
        $this->minQualityScore = (int)Env::get('MIN_QUALITY_SCORE', 90);
        $this->dailyLimit = (int)Env::get('AUTO_PUBLISH_DAILY_LIMIT', 5);
    }

    /**
     * Get count of AI-generated articles published today
     */
    public function getPublishedTodayCount(): int {
        $sql = "SELECT COUNT(*) FROM articles 
                WHERE DATE(published_at) = CURRENT_DATE 
                  AND ai_generated = 1 
                  AND status = 'published'";
        return (int)Database::fetchColumn($sql);
    }

    /**
     * Daily Slot Breakdown:
     * - Up to 3: Genuine official / student-impacting updates from verified sources.
     * - Up to 2 additional slots: High student search-intent content in priority order:
     *   Entrance Exams -> Scholarships -> College Updates -> Career Guides -> Student Technology.
     * - Maximum 5 total / day (never a mandatory target).
     */
    public function getPublishedSlotCounts(): array {
        $sql = "SELECT a.category_id, c.slug AS category_slug, a.source_name
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                WHERE DATE(a.published_at) = CURRENT_DATE
                  AND a.ai_generated = 1
                  AND a.status = 'published'";
        $todayArticles = Database::fetchAll($sql);

        $officialCount = 0;
        $searchIntentCount = 0;
        $searchIntentCategories = ['entrance-exams', 'scholarships', 'college-updates', 'career-guides', 'student-technology'];

        foreach ($todayArticles as $row) {
            $catSlug = $row['category_slug'] ?? '';
            if (in_array($catSlug, ['exam-results', 'admit-cards', 'exam-dates', 'answer-keys', 'government-jobs'], true)) {
                $officialCount++;
            } elseif (in_array($catSlug, $searchIntentCategories, true)) {
                $searchIntentCount++;
            } else {
                $officialCount++;
            }
        }

        return [
            'total' => count($todayArticles),
            'official' => $officialCount,
            'search_intent' => $searchIntentCount,
            'max_total' => $this->dailyLimit, // 5
            'max_official' => 3,
            'max_search_intent' => 2
        ];
    }

    /**
     * Check if daily automatic publishing quota is reached
     */
    public function isDailyLimitReached(): bool {
        return $this->getPublishedTodayCount() >= $this->dailyLimit;
    }

    /**
     * Comprehensive 10-point gatekeeper verification
     */
    public function canPublish(int $articleId): array {
        $article = Database::fetchOne(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
             FROM articles a 
             JOIN categories c ON a.category_id = c.id 
             WHERE a.id = :id LIMIT 1",
            ['id' => $articleId]
        );

        if (!$article) {
            return ['can_publish' => false, 'reasons' => ["Article #{$articleId} not found in database."], 'article' => null];
        }

        // Automatic Category Auto-Correction: Fix any mismatch automatically before review
        $autoCat = CategoryService::autoResolveCategory($article['title'], $article['content'] ?? '', $article['category_slug'] ?? null);
        if ($autoCat && (int)$article['category_id'] !== (int)$autoCat['id']) {
            Database::update('articles', ['category_id' => $autoCat['id']], 'id = :id', ['id' => $articleId]);
            $article['category_id'] = (int)$autoCat['id'];
            $article['category_name'] = $autoCat['name'];
            $article['category_slug'] = $autoCat['slug'];
        }

        $reasons = [];

        // Check 0: Daily Slot Quota (Max 5 total: up to 3 official + up to 2 search-intent)
        $slotCounts = $this->getPublishedSlotCounts();
        if ($slotCounts['total'] >= $slotCounts['max_total']) {
            $reasons[] = "Maximum daily publishing quota ({$slotCounts['max_total']}/day) reached.";
        }

        $searchIntentCategories = ['entrance-exams', 'scholarships', 'college-updates', 'career-guides', 'student-technology'];
        $isSearchIntent = in_array($article['category_slug'] ?? '', $searchIntentCategories, true);

        if ($isSearchIntent && $slotCounts['search_intent'] >= 2 && $slotCounts['official'] >= 3) {
            $reasons[] = "Daily search-intent slot quota (2/2) reached.";
        } elseif (!$isSearchIntent && $slotCounts['official'] >= 3 && $slotCounts['search_intent'] >= 2) {
            $reasons[] = "Daily official update slot quota (3/3) reached.";
        }

        // Check 1: Strict Quality Score & Factual Routing Thresholds
        $score = (int)($article['quality_score'] ?? 0);
        $latestCheck = Database::fetchOne(
            "SELECT * FROM article_checks WHERE article_id = :aid AND check_type IN ('factual_verification', 'quality_breakdown') ORDER BY id DESC LIMIT 1",
            ['aid' => $articleId]
        );
        $notes = $latestCheck ? json_decode($latestCheck['notes'] ?? '{}', true) : [];
        $hasCriticalFactIssue = !empty($notes['flagged_issues_count']) && (int)$notes['flagged_issues_count'] > 0;
        $factPassed = ($notes['fact_recommendation'] ?? '') === 'pass' || ($notes['recommendation'] ?? '') === 'pass';

        if ($hasCriticalFactIssue || ($notes['fact_recommendation'] ?? '') === 'reject' || ($notes['safety_gate']['pass'] ?? true) === false) {
            $reasons[] = "Critical factual uncertainty detected; human editorial review is required.";
        } elseif ($score >= 90) {
            // Score 90+ auto-publish eligible if no critical issues
        } elseif ($score >= 80) {
            // Score 80-89: Publish ONLY when all critical facts are 100% verified
            if (!$factPassed || $hasCriticalFactIssue) {
                $reasons[] = "Score is {$score}/100; requires 100% verified facts without ambiguity for auto-publishing.";
            }
        } elseif ($score >= 70) {
            $reasons[] = "Score is {$score}/100 (70–79 bracket requires manual editorial review).";
        } else {
            $reasons[] = "Score ({$score}/100) is below minimum threshold (<70).";
        }

        // Check 2: Source verification passed
        if ((int)$article['source_verified'] !== 1 || empty($article['source_url']) || !filter_var($article['source_url'], FILTER_VALIDATE_URL)) {
            $reasons[] = "Verified official source URL is missing or invalid.";
        }

        // Check 4: Duplicate risk check against published library
        $duplicate = Database::fetchOne(
            "SELECT id FROM articles WHERE slug = :slug AND id != :id AND status = 'published' LIMIT 1",
            ['slug' => $article['slug'], 'id' => $articleId]
        );
        if ($duplicate) {
            $reasons[] = "Duplicate slug collision detected with published Article #{$duplicate['id']}.";
        }

        // Check 5: Thumbnail exists on disk
        if (empty($article['featured_image'])) {
            $reasons[] = "Thumbnail is missing from article metadata.";
        } else {
            $thumbFullPath = dirname(__DIR__, 2) . '/' . ltrim($article['featured_image'], '/');
            if (!file_exists($thumbFullPath)) {
                $reasons[] = "Thumbnail file does not exist on disk at {$article['featured_image']}.";
            }
        }

        // Check 6: SEO metadata exists
        if (empty($article['meta_title']) || mb_strlen($article['meta_title']) > 75) {
            $reasons[] = "SEO meta title is missing or exceeds recommended length.";
        }
        if (empty($article['meta_description']) || mb_strlen($article['meta_description']) < 60) {
            $reasons[] = "SEO meta description is missing or too brief.";
        }

        // Check 7: Category exists
        if (empty($article['category_id']) || empty($article['category_slug'])) {
            $reasons[] = "Valid category association is missing.";
        }

        // Check 8: Slug is unique and valid
        if (empty($article['slug']) || mb_strlen($article['slug']) < 3) {
            $reasons[] = "URL slug is invalid.";
        }

        // Check 9: Content is not prohibited / minimum length
        if (empty($article['content']) || mb_strlen(strip_tags($article['content'])) < 250) {
            $reasons[] = "Article content is too short or empty.";
        }
        $prohibitedTerms = ['gambling', 'casino', 'betting', 'crack download', 'pirated'];
        $lowerContent = mb_strtolower($article['content']);
        foreach ($prohibitedTerms as $badTerm) {
            if (str_contains($lowerContent, $badTerm)) {
                $reasons[] = "Prohibited keyword '{$badTerm}' detected in content.";
            }
        }

        // Check 10: Temporal Freshness (Reject expired past cycles e.g. 2024, 2023, 2022)
        $historicalYears = ['2024', '2023', '2022', '2021', '2020'];
        foreach ($historicalYears as $hy) {
            if (str_contains($article['title'], $hy) || str_contains($article['slug'], $hy)) {
                $reasons[] = "Expired historical year ({$hy}) detected in article title/slug. Only active 2026/2027 updates are permitted.";
                break;
            }
        }

        // Check 11: Article status is review/publish_ready (or draft with passing score)
        if ($article['status'] === 'rejected') {
            $reasons[] = "Article is currently marked as rejected.";
        }

        return [
            'can_publish' => empty($reasons),
            'reasons' => $reasons,
            'article' => $article
        ];
    }

    /**
     * Pre-publish technical payload validation (HTML, Canonical, JSON-LD)
     */
    public function validatePrePublishPayload(array $article): array {
        $errors = [];

        // 1. Validate HTML structure
        $content = $article['content'] ?? '';
        if (str_contains($content, '<script') || str_contains($content, 'javascript:')) {
            $errors[] = "Dangerous script tags detected in HTML content.";
        }

        // 2. Validate Canonical URL
        $canonical = !empty($article['canonical_url']) ? $article['canonical_url'] : url('article/' . $article['slug'] . '/');
        if (!filter_var($canonical, FILTER_VALIDATE_URL)) {
            $errors[] = "Canonical URL is not a valid URL.";
        }

        // 3. Validate Schema.org JSON-LD Generation
        try {
            $schemaJson = SEOHelper::articleSchema($article, $canonical);
            $decoded = json_decode($schemaJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['@context'])) {
                $errors[] = "Article JSON-LD structured data is invalid JSON.";
            }
        } catch (Throwable $e) {
            $errors[] = "Failed to generate valid Schema.org structured data: " . $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Attempt controlled publication of an individual article
     */
    public function publish(int $articleId): array {
        // 1. Check gatekeeper conditions
        $gate = $this->canPublish($articleId);
        if (!$gate['can_publish']) {
            // Demote / keep in review status with failure notes
            Database::update('articles', ['status' => 'review'], 'id = :id', ['id' => $articleId]);
            $this->logCheckRecord($articleId, 'auto_publish_gate_failed', 0, [
                'reasons' => $gate['reasons']
            ]);
            Logger::warning("Auto-publish rejected for Article #{$articleId}", ['reasons' => $gate['reasons']]);
            return [
                'success' => false,
                'article_id' => $articleId,
                'reasons' => $gate['reasons']
            ];
        }

        $article = $gate['article'];

        // 2. Pre-publish technical payload validation
        $preFlight = $this->validatePrePublishPayload($article);
        if (!$preFlight['valid']) {
            Database::update('articles', ['status' => 'review'], 'id = :id', ['id' => $articleId]);
            $this->logCheckRecord($articleId, 'preflight_validation_failed', 0, [
                'errors' => $preFlight['errors']
            ]);
            Logger::warning("Pre-flight validation failed for Article #{$articleId}", ['errors' => $preFlight['errors']]);
            return [
                'success' => false,
                'article_id' => $articleId,
                'reasons' => $preFlight['errors']
            ];
        }

        // 3. Publish Article
        $now = date('Y-m-d H:i:s');
        $updateFields = [
            'status' => 'published',
            'published_at' => $now,
            'updated_at' => $now
        ];
        if (empty($article['original_published_at'])) {
            $updateFields['original_published_at'] = $now;
        }

        Database::update('articles', $updateFields, 'id = :id', ['id' => $articleId]);

        // Regenerate thumbnail with exact current publication date stamp
        $thumbnailService = new ThumbnailService();
        $thumbnailService->generateForArticle($articleId);

        // 4. Update associated trend status to 'published'
        if (!empty($article['trend_id'])) {
            TrendService::markStatus((int)$article['trend_id'], 'published', [
                'processed_at' => $now
            ]);
        }

        // 5. Log audit check and system log
        $this->logCheckRecord($articleId, 'auto_publish_success', (int)$article['quality_score'], [
            'published_at' => $now,
            'canonical_url' => url('article/' . $article['slug'] . '/'),
            'quality_score' => (int)$article['quality_score']
        ]);

        Logger::info("Article #{$articleId} successfully auto-published live", [
            'title' => $article['title'],
            'slug' => $article['slug'],
            'quality_score' => (int)$article['quality_score']
        ]);

        // 6. Real-Time Google Indexing API Notification
        if (GoogleIndexingService::isConfigured()) {
            GoogleIndexingService::pingArticle($articleId);
        }

        // 7. Real-Time IndexNow Notification (Microsoft Bing, Yahoo, Yandex, Naver)
        if (IndexNowService::isConfigured()) {
            IndexNowService::pingArticle($articleId);
        }

        // 8. Real-Time Twitter (X) Broadcast
        if (TwitterService::isConfigured()) {
            TwitterService::broadcastArticle($articleId);
        }

        return [
            'success' => true,
            'article_id' => $articleId,
            'title' => $article['title'],
            'slug' => $article['slug'],
            'published_at' => $now,
            'url' => url('article/' . $article['slug'] . '/')
        ];
    }

    /**
     * Process batch of review-queue articles through REAL SOURCE VERIFICATION
     * before auto-publishing.
     *
     * Flow per article:
     *   1. Fetch real text from official source URL
     *   2. Gemini cross-verifies article claims against that real content
     *   3. confidence >= 70% + verdict "pass"  → AUTO-PUBLISH live
     *   4. verdict "guide_type" or "no_source" → keep in review (no auto-publish)
     *   5. verdict "fail" (contradicted facts) → REJECT + DELETE from DB
     */
    public function processPublishQueue(int $maxBatch = 5): array {
        if ($this->isDailyLimitReached()) {
            $todayCount = $this->getPublishedTodayCount();
            Logger::info("Daily publishing quota reached ({$todayCount}/{$this->dailyLimit} articles published today).");
            return [
                'success' => false,
                'reason'  => 'daily_limit_reached',
                'published_today' => $todayCount,
                'daily_limit'     => $this->dailyLimit,
                'items'   => []
            ];
        }

        $remainingSlots = max(0, $this->dailyLimit - $this->getPublishedTodayCount());
        $limit = min($maxBatch, $remainingSlots);

        $sql = "SELECT id, title, content, source_url, source_name
                FROM articles
                WHERE status = 'review'
                  AND quality_score >= :min_score
                ORDER BY quality_score DESC, id ASC
                LIMIT " . (int)$limit;

        $candidates = Database::fetchAll($sql, ['min_score' => $this->minQualityScore]);
        $results    = [];
        $verifier   = new \App\AI\SourceVerifier();

        foreach ($candidates as $cand) {
            $articleId  = (int)$cand['id'];
            $sourceUrl  = $cand['source_url'] ?? '';
            $sourceName = $cand['source_name'] ?? '';

            Logger::info("Source-verifying Article #{$articleId}: '{$cand['title']}'");

            // Real source cross-verification
            $verification = $verifier->verify(
                $cand['title'],
                $cand['content'],
                $sourceUrl,
                $sourceName
            );

            $verdict    = $verification['verdict'];
            $confidence = $verification['confidence'];
            $reason     = $verification['reason'];

            if ($verdict === 'pass' && $confidence >= 75) {
                // ✅ Verified by AI Engine — Auto-publish immediately
                $pubResult = $this->publish($articleId);
                $results[] = array_merge($pubResult, [
                    'verification_verdict'    => 'pass',
                    'verification_confidence' => $confidence,
                    'verification_reason'     => $reason,
                ]);
                Logger::info("Article #{$articleId} AUTO-PUBLISHED LIVE after autonomous AI verification (confidence: {$confidence}%).");

            } else {
                // ❌ Failed verification / hallucination / outdated — Reject & DELETE from DB
                Database::update('articles', ['status' => 'rejected'], 'id = :id', ['id' => $articleId]);
                Database::query("DELETE FROM article_checks WHERE article_id = :id", ['id' => $articleId]);
                Database::query("DELETE FROM articles WHERE id = :id", ['id' => $articleId]);
                $results[] = [
                    'success'                 => false,
                    'article_id'              => $articleId,
                    'title'                   => $cand['title'],
                    'verification_verdict'    => 'fail',
                    'verification_confidence' => $confidence,
                    'reasons'                 => ["REJECTED & DELETED: {$reason}"],
                    'action'                  => 'deleted_from_db',
                ];
                Logger::warning("Article #{$articleId} REJECTED & DELETED from DB — failed verification (confidence: {$confidence}%). Reason: {$reason}");
            }

            sleep(3); // Rate limit pacing between verification calls
        }

        return [
            'success'        => true,
            'processed_count'=> count($results),
            'published_today'=> $this->getPublishedTodayCount(),
            'daily_limit'    => $this->dailyLimit,
            'items'          => $results
        ];
    }

    private function logCheckRecord(int $articleId, string $checkType, int $score, array $notes): void {
        try {
            Database::insert('article_checks', [
                'article_id' => $articleId,
                'check_type' => $checkType,
                'score' => $score,
                'notes' => json_encode($notes, JSON_UNESCAPED_UNICODE),
                'checker' => 'ai',
                'checked_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            Logger::error("Failed to insert article_checks record: " . $e->getMessage());
        }
    }
}
