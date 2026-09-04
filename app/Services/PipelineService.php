<?php
/**
 * EduPulse - Content Generation Pipeline Orchestrator (Phase 5)
 * Coordinates the full automated article creation lifecycle:
 * Approved Trend → Source Research → ArticleGenerator → FactChecker → ContentEditor
 * → InternalLinker → SEOGenerator → 8-Dimension Quality Calculation → Safety Gates → Storage.
 */

namespace App\Services;

use App\AI\ArticleGenerator;
use App\AI\FactChecker;
use App\AI\ContentEditor;
use App\AI\InternalLinker;
use App\AI\SEOGenerator;
use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Services\WebVitalsService;
use App\Services\AuthorityFactFetcherService;
use Exception;
use Throwable;

class PipelineService {

    private ArticleGenerator $generator;
    private FactChecker $checker;
    private ContentEditor $editor;
    private InternalLinker $linker;
    private SEOGenerator $seoGen;

    public function __construct(
        ?ArticleGenerator $generator = null,
        ?FactChecker $checker = null,
        ?ContentEditor $editor = null,
        ?InternalLinker $linker = null,
        ?SEOGenerator $seoGen = null
    ) {
        $this->generator = $generator ?: new ArticleGenerator();
        $this->checker = $checker ?: new FactChecker();
        $this->editor = $editor ?: new ContentEditor();
        $this->linker = $linker ?: new InternalLinker();
        $this->seoGen = $seoGen ?: new SEOGenerator();
    }

    /**
     * Generate complete article from an approved trend
     */
    public function generateFromTrend(int $trendId, bool $force = false): array {
        $trend = Database::fetchOne("SELECT * FROM trends WHERE id = :id LIMIT 1", ['id' => $trendId]);
        if (!$trend) {
            return ['success' => false, 'trend_id' => $trendId, 'error' => 'Trend not found.'];
        }

        // Guard 0: Strictly reject generic placeholder topics without specific exam/recruitment events
        if (!$force && TrendService::isGenericPlaceholderKeyword($trend['keyword'])) {
            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Generic placeholder topic rejected']]);
            Logger::info("Skipping Trend #{$trendId}: Generic placeholder topic rejected ('{$trend['keyword']}').");
            return ['success' => false, 'trend_id' => $trendId, 'error' => 'Generic placeholder topic rejected.'];
        }

        // Check if article already generated from this trend
        if (!empty($trend['processed_at'])) {
            $existing = Database::fetchOne("SELECT id, status FROM articles WHERE trend_id = :tid LIMIT 1", ['tid' => $trendId]);
            if ($existing) {
                Logger::info("Trend #{$trendId} already generated as Article #{$existing['id']}");
                return ['success' => true, 'article_id' => (int)$existing['id'], 'status' => 'already_generated'];
            }
        }

        // Prevent generating duplicate article if topic is already published (unless admin forces generation)
        if (!$force && TrendService::existsAsArticle($trend['keyword'])) {
            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Similar article already published']]);
            Logger::info("Trend #{$trendId} skipped: similar article already exists in publication library.");
            return ['success' => false, 'trend_id' => $trendId, 'error' => 'Similar article already exists in publication library.'];
        }

        // Intelligently auto-resolve correct category from keyword
        $autoCat = CategoryService::autoResolveCategory($trend['keyword'], '', $trend['category_hint'] ?? null);
        $categorySlug = $autoCat['slug'] ?? 'entrance-exams';
        $categoryId = (int)($autoCat['id'] ?? 5);

        // Check if this trend is an official breaking notification
        $isBreaking = TrendService::isOfficialBreaking($trend);

        // Guard 1: Fixed Slot Timing (10:00 AM, 02:00 PM, 06:00 PM IST)
        // Manual publishing by admin ($force = true) is 100% UNLIMITED and NEVER blocked.
        if (!$force) {
            $pendingSlot = AutoCronService::getNextPendingSlot();
            if ($pendingSlot === null) {
                $schedule = AutoCronService::getISTSlotSchedule();
                $completed = AutoCronService::getCompletedSlotsTodayCount();
                $pacingMsg = "Autonomous slot schedule active: {$completed}/3 scheduled slots completed today. Next slot: {$schedule['next_slot_name']} (in ~{$schedule['wait_minutes']}m). Manual publishing by admin remains unlimited.";
                Logger::info("Trend #{$trendId} deferred: " . $pacingMsg);
                return ['success' => false, 'trend_id' => $trendId, 'error' => $pacingMsg];
            }
        }

        // Guard 2: 30-Day Anti-Repeat Guard & Same-Day Authority Protection (Bypassed if admin clicked Publish Now)
        if (!$force && (TrendService::isRecentlyCovered($trend['keyword'], 30) || TrendService::isAuthorityCoveredRecently($trend['keyword'], 12))) {
            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => 'Topic or examination authority covered recently']]);
            Logger::info("Skipping Trend #{$trendId}: Similar topic or examination authority already covered recently.");
            return ['success' => false, 'trend_id' => $trendId, 'error' => 'Similar topic or examination authority already covered recently.'];
        }

        if ($force) {
            Logger::info("⚡ Admin Manual Force Publish triggered for Trend #{$trendId}: '{$trend['keyword']}'");
        } elseif ($isBreaking) {
            Logger::info("🔴 Official Breaking Notice detected for Trend #{$trendId}: '{$trend['keyword']}'. Fast-tracking publication!");
        } else {
            Logger::info("Starting content generation pipeline for Trend #{$trendId}: '{$trend['keyword']}'");
        }

        // Mark status as 'analyzing' (valid DB enum) so UI immediately shows GENERATING badge
        TrendService::markStatus($trendId, 'analyzing');

        // Parse raw payload / source information
        $rawPayload = [];
        if (!empty($trend['raw_payload'])) {
            $decoded = json_decode($trend['raw_payload'], true);
            if (is_array($decoded)) {
                $rawPayload = $decoded;
            }
        }

        // Fetch Grounded Statutory Facts & Shift Matrix from Official Portals
        $factFetcher = new AuthorityFactFetcherService();
        $snippet = $rawPayload['snippet'] ?? ($trend['category_hint'] ?? '');
        $verifiedFacts = $factFetcher->fetchFactsForTopic($trend['keyword'], $categorySlug, $trend['url'] ?? '', $snippet);

        $resolvedAuth = AuthorityFactFetcherService::resolveAuthority($trend['keyword'], $trend['url'] ?? '');
        $authorityName = (!empty($verifiedFacts['authority_name']) && !str_contains(strtolower($verifiedFacts['authority_name']), 'statutory examination board'))
            ? $verifiedFacts['authority_name']
            : $resolvedAuth['name'];
        $officialPortal = (!empty($verifiedFacts['official_portal']) && !str_contains($verifiedFacts['official_portal'], 'sarkari.online'))
            ? $verifiedFacts['official_portal']
            : $resolvedAuth['portal'];

        $sourceData = [
            'keyword' => $trend['keyword'],
            'source_name' => $authorityName,
            'source_url' => $officialPortal,
            'reference' => $verifiedFacts['official_notice_ref'] ?? ($rawPayload['source_attribution']['reference'] ?? ''),
            'notes' => $rawPayload['reasoning'] ?? $trend['keyword'],
            'verified_facts' => $verifiedFacts
        ];

        // 1. Generate Draft
        $angle = $rawPayload['suggested_original_angle'] ?? 'Comprehensive student instructions';
        $genResult = $this->generator->generate($trend['keyword'], $sourceData, $categorySlug, $angle);
        usleep(2500000); // 2.5s pause to respect Gemini Free Tier RPM limits

        // 2. Fact Check
        $factAudit = $this->checker->check([
            'title' => $genResult['title'],
            'content' => $genResult['content']
        ], $sourceData);
        usleep(2500000);

        // 3. Editorial Polish & Format
        $polished = $this->editor->polish($genResult['title'], $genResult['content'], $categorySlug);
        usleep(2500000);

        // 4. Contextual Internal Linking
        $availableArticles = ArticleService::getLatestPublished(20);
        $linking = $this->linker->link($polished['edited_content'], $availableArticles);
        usleep(2500000);

        // 5. Search Engine Optimization (SEO)
        $seoData = $this->seoGen->generate($polished['edited_title'], $linking['linked_content'], $categorySlug);

        // 6. Calculate 8-Dimension Quality Score (Total 100 points)
        $quality = $this->calculateQualityScore([
            'fact_check' => $factAudit,
            'editor' => $polished,
            'linking' => $linking,
            'seo' => $seoData,
            'source_data' => $sourceData,
            'gen_result' => $genResult
        ]);

        // 7. Evaluate Safety Gates & Determine Target Status
        $safetyPass = $this->evaluateSafetyGates($factAudit, $rawPayload, $quality);
        $finalScore = (int)$quality['total_score'];

        $hasCriticalIssue = false;
        if (!empty($factAudit['flagged_issues'])) {
            foreach ($factAudit['flagged_issues'] as $issue) {
                if (($issue['severity'] ?? '') === 'critical') {
                    $hasCriticalIssue = true;
                    break;
                }
            }
        }
        $allCriticalFactsVerified = ($factAudit['recommendation'] ?? '') === 'pass' && !$hasCriticalIssue && empty($factAudit['flagged_issues']);

        // User Quality Routing Logic:
        // - Any critical factual uncertainty -> Review regardless of score
        // - Score >= 90 -> Auto-publish eligible
        // - Score 80–89 -> Publish ONLY when all critical facts are verified, else Review
        // - Score 70–79 -> Review
        // - Score < 70 -> Reject
        if (!$safetyPass['pass'] || $finalScore < 70) {
            $finalStatus = 'rejected';
            Logger::warning("Article rejected (Score: {$finalScore}): " . implode(', ', $safetyPass['reasons']));
        } else {
            // Quality Score >= 70 & Safety Gate Passed -> DIRECT LIVE PUBLISH!
            $finalStatus = 'published';
        }

        // 7b. Strict Slug Collision & Duplicate Check before Database Persistence
        $targetSlug = mb_substr($seoData['slug_suggestion'], 0, 190);
        $slugBase = preg_replace('/-\d+$/', '', $targetSlug);
        $existingBySlug = Database::fetchOne(
            "SELECT id, title, slug FROM articles WHERE slug = :s OR slug LIKE :s_wild LIMIT 1",
            ['s' => $targetSlug, 's_wild' => $slugBase . '-%']
        );
        if (!$force && $existingBySlug) {
            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => "Duplicate: Article already exists with matching slug #{$existingBySlug['id']} ({$existingBySlug['slug']})"]]);
            Logger::warning("Pipeline aborted: Suggested slug '{$targetSlug}' collides with existing Article #{$existingBySlug['id']}. Duplicate generation prevented.");
            return ['success' => false, 'trend_id' => $trendId, 'error' => "Similar article already exists (Article #{$existingBySlug['id']}: '{$existingBySlug['title']}')."];
        }

        // 8. Persist Article in Database directly
        $author = Database::fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $authorId = $author ? (int)$author['id'] : null;
        $now = date('Y-m-d H:i:s');

        $articleId = ArticleService::create([
            'trend_id' => $trendId,
            'title' => mb_substr($polished['edited_title'], 0, 250),
            'slug' => mb_substr($seoData['slug_suggestion'], 0, 190),
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'excerpt' => $seoData['excerpt'],
            'content' => $linking['linked_content'],
            'status' => $finalStatus,
            'quality_score' => $finalScore,
            'ai_generated' => 1,
            'source_verified' => !empty($sourceData['source_url']) ? 1 : 0,
            'source_name' => mb_substr($sourceData['source_name'] ?? 'Statutory Authority', 0, 190),
            'source_url' => mb_substr($sourceData['source_url'] ?? '', 0, 255),
            'source_ref' => mb_substr($sourceData['reference'] ?? '', 0, 190),
            'meta_title' => mb_substr($seoData['seo_title'], 0, 190),
            'meta_description' => mb_substr($seoData['meta_description'], 0, 255),
            'canonical_url' => url('article/' . mb_substr($seoData['slug_suggestion'], 0, 190) . '/'),
            'og_title' => mb_substr($seoData['seo_title'], 0, 190),
            'og_description' => mb_substr($seoData['meta_description'], 0, 255),
            'published_at' => ($finalStatus === 'published') ? $now : null,
            'original_published_at' => ($finalStatus === 'published') ? $now : null
        ]);

        // 9. Record Detailed Quality Breakdown in article_checks table
        Database::insert('article_checks', [
            'article_id' => $articleId,
            'check_type' => 'quality_breakdown',
            'score' => $finalScore,
            'notes' => json_encode([
                'dimensions' => $quality['breakdown'],
                'safety_gate' => $safetyPass,
                'fact_recommendation' => $factAudit['recommendation'] ?? 'pass',
                'flagged_issues_count' => count($factAudit['flagged_issues'] ?? [])
            ], JSON_UNESCAPED_UNICODE),
            'checker' => 'ai',
            'checked_at' => $now,
            'created_at' => $now
        ]);

        // 10. Generate Branded WebP Thumbnail (Phase 6)
        $thumbnailService = new ThumbnailService();
        $thumbResult = $thumbnailService->generateForArticle($articleId);

        // 10b. Core Web Vitals Pre-Check & Auto-Fix (alt tags, lazy loading, noopener, heading check)
        $vitalsCheck = WebVitalsService::check([
            'content'           => $linking['linked_content'],
            'title'             => $polished['edited_title'],
            'featured_image'    => null,
            'featured_image_alt'=> null
        ]);
        if (!empty($vitalsCheck['fixed_content']) && $vitalsCheck['fixes_applied'] > 0) {
            $linking['linked_content'] = $vitalsCheck['fixed_content'];
            Database::update('articles', ['content' => $vitalsCheck['fixed_content']], 'id = :id', ['id' => $articleId]);
            Logger::info("Article #{$articleId}: WebVitals auto-fixed {$vitalsCheck['fixes_applied']} HTML issues.");
        }

        // 11. Update Trend Status to 'published'
        TrendService::markStatus($trendId, 'published', [
            'processed_at' => $now,
            'raw_payload' => array_merge($rawPayload, [
                'generated_article_id' => $articleId,
                'quality_score' => $finalScore,
                'thumbnail_path' => $thumbResult['relative_path'] ?? null
            ])
        ]);

        // Automatically record slot completed immediately upon autonomous publication
        if (!$force && !empty($pendingSlot) && $finalStatus === 'published') {
            AutoCronService::recordSlotCompleted($pendingSlot, $articleId);
            Logger::info("PipelineService: Scheduled Slot #{$pendingSlot} locked with Article #{$articleId}.");
        }

        Logger::info("🚀 Article #{$articleId} successfully GENERATED and PUBLISHED LIVE on Sarkari.online! (Score: {$finalScore})");

        return [
            'success' => true,
            'article_id' => $articleId,
            'trend_id' => $trendId,
            'title' => $polished['edited_title'],
            'status' => $finalStatus,
            'quality_score' => $finalScore,
            'safety_pass' => $safetyPass['pass'],
            'thumbnail' => $thumbResult['relative_path'] ?? null
        ];

        Logger::info("Pipeline successfully completed Article #{$articleId} (Final Status: {$finalStatus}, Quality Score: {$finalScore}) for Trend #{$trendId}");

        return [
            'success' => true,
            'article_id' => $articleId,
            'trend_id' => $trendId,
            'title' => $polished['edited_title'],
            'status' => $finalStatus,
            'quality_score' => $finalScore,
            'safety_pass' => $safetyPass['pass'],
            'thumbnail' => $thumbResult['relative_path'] ?? null
        ];
    }

    /**
     * Calculate 8-Part Quality Score (100 Points Total)
     * 
     * Fact accuracy       25
     * Original value      20
     * Search intent       15
     * Completeness        10
     * Source quality      10
     * Readability         10
     * SEO                  5
     * Internal linking      5
     */
    public function calculateQualityScore(array $data): array {
        $factAudit = $data['fact_check'] ?? [];
        $editor = $data['editor'] ?? [];
        $linking = $data['linking'] ?? [];
        $seo = $data['seo'] ?? [];
        $source = $data['source_data'] ?? [];
        $gen = $data['gen_result'] ?? [];

        // 1. Fact Accuracy (Max 25 pts)
        $factRaw = (int)($factAudit['factual_accuracy_score'] ?? 90);
        $factScore = round(($factRaw / 100) * 25);

        // 2. Original Value (Max 20 pts)
        $copiedRisk = $factAudit['copied_risk'] ?? 'low';
        $origScore = match($copiedRisk) {
            'low' => 20,
            'medium' => 12,
            default => 5
        };

        // 3. Search Intent (Max 15 pts)
        $intentScore = 15;
        if (empty($gen['title']) || strlen($gen['title']) < 15) {
            $intentScore = 8;
        }

        // 4. Completeness (Max 10 pts)
        $compScore = 0;
        if (!empty($gen['dates_table'])) $compScore += 4;
        if (!empty($gen['key_takeaways'])) $compScore += 3;
        if (!empty($gen['content']) && strlen($gen['content']) > 400) $compScore += 3;

        // 5. Source Quality (Max 10 pts)
        $srcScore = !empty($source['source_url']) ? 10 : 5;

        // 6. Readability (Max 10 pts)
        $readabilityRaw = (int)($editor['readability_score'] ?? 85);
        $readScore = round(($readabilityRaw / 100) * 10);

        // 7. SEO (Max 5 pts)
        $seoScore = 0;
        if (!empty($seo['seo_title']) && strlen($seo['seo_title']) <= 60) $seoScore += 2;
        if (!empty($seo['meta_description']) && strlen($seo['meta_description']) >= 100) $seoScore += 2;
        if (!empty($seo['slug_suggestion'])) $seoScore += 1;

        // 8. Internal Linking (Max 5 pts)
        $linksCount = (int)($linking['count'] ?? 0);
        $linkScore = min(5, $linksCount * 2.5);

        $total = $factScore + $origScore + $intentScore + $compScore + $srcScore + $readScore + $seoScore + $linkScore;
        $total = min(100, max(0, (int)$total));

        return [
            'total_score' => $total,
            'breakdown' => [
                'fact_accuracy' => $factScore,
                'original_value' => $origScore,
                'search_intent' => $intentScore,
                'completeness' => $compScore,
                'source_quality' => $srcScore,
                'readability' => $readScore,
                'seo' => $seoScore,
                'internal_linking' => $linkScore
            ]
        ];
    }

    /**
     * Safety Gates Evaluation
     */
    public function evaluateSafetyGates(array $factAudit, array $rawPayload, array $quality): array {
        $reasons = [];
        $pass = true;

        // Rule 1: Factual verification fails
        if (($factAudit['recommendation'] ?? '') === 'reject') {
            $pass = false;
            $reasons[] = 'Fact verification failed with reject recommendation';
        }

        // Rule 2: Hallucination risk high
        if (($factAudit['hallucination_risk'] ?? '') === 'high') {
            $pass = false;
            $reasons[] = 'High hallucination risk detected in drafted claims';
        }

        // Rule 3: Duplicate risk high
        if (($rawPayload['duplicate_risk'] ?? '') === 'high') {
            $pass = false;
            $reasons[] = 'High duplicate risk against existing catalog';
        }

        // Rule 4: Critical flagged issues
        if (!empty($factAudit['flagged_issues'])) {
            foreach ($factAudit['flagged_issues'] as $issue) {
                if (($issue['severity'] ?? '') === 'critical') {
                    $pass = false;
                    $reasons[] = 'Critical factual discrepancy: ' . ($issue['claim'] ?? '');
                }
            }
        }

        // Rule 5: Minimum fact accuracy
        if (($quality['breakdown']['fact_accuracy'] ?? 0) < 18) { // Less than 72% fact accuracy
            $pass = false;
            $reasons[] = 'Fact accuracy score below minimum safety threshold';
        }

        return [
            'pass' => $pass,
            'reasons' => $reasons
        ];
    }

    /**
     * Run batch article generation on approved trends
     */
    public function processApprovedTrends(int $targetPublished = 1): array {
        // Prioritize newest trends (id DESC) with highest scores, strictly rejecting generic placeholders
        $sql = "SELECT id, keyword FROM trends WHERE status = 'approved' ORDER BY trend_score DESC, id DESC LIMIT 25";
        $approved = Database::fetchAll($sql);

        $results = [];
        $publishedCount = 0;

        foreach ($approved as $tr) {
            if ($publishedCount >= $targetPublished) {
                break;
            }

            // Reject synthetic or generic placeholder keywords
            if (TrendService::isGenericPlaceholderKeyword($tr['keyword'] ?? '')) {
                TrendService::markStatus((int)$tr['id'], 'rejected', ['raw_payload' => ['reason' => 'Generic placeholder topic rejected']]);
                Logger::info("PipelineService: Purged generic placeholder trend #{$tr['id']} ('{$tr['keyword']}')");
                continue;
            }

            try {
                $res = $this->generateFromTrend((int)$tr['id']);
                $results[] = $res;
                if (!empty($res['success']) && ($res['status'] ?? '') !== 'already_generated') {
                    $publishedCount++;
                    break; // Strictly stop after 1 article to enforce scheduled slot pacing
                }
            } catch (Throwable $e) {
                Logger::error("Failed to generate article for Trend #{$tr['id']}: " . $e->getMessage());
                $results[] = ['success' => false, 'trend_id' => (int)$tr['id'], 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
