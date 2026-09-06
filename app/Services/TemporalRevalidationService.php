<?php
/**
 * Sarkari.online - Complete Lifecycle Temporal Revalidation & Event Transition Service
 *
 * Autonomously monitors articles across their COMPLETE lifecycle:
 * UPCOMING → ACTIVE → CLOSED → ADMIT_CARD_RELEASED → EXAM_COMPLETED → RESULT_RELEASED
 *
 * Implements:
 * 1. Post-deadline transitions to CLOSED (with CTA stripping and historical preservation).
 * 2. Pre-expiry source freshness checks to catch deadline extensions announced days prior.
 * 3. Continuous monitoring of CLOSED articles for official Admit Card releases and Exam schedules.
 * 4. Verified event-based transitions for Exam Completion and Result Releases (never future guessing).
 * 5. 6-hour rate-limiting to protect official statutory portals from being hammered.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Services\AuthorityFactFetcherService;
use App\Services\TemporalFactService;
use App\Services\TemporalContentValidator;
use DateTimeImmutable;
use Throwable;

class TemporalRevalidationService {

    // Minimum interval before re-checking an official external portal (6 hours)
    public const SOURCE_FRESHNESS_INTERVAL = 21600;

    /**
     * Run full autonomous revalidation pass across published articles covering the complete lifecycle
     *
     * @param int $limit Max articles to revalidate per pass
     * @param DateTimeImmutable|null $now Reference time in Asia/Kolkata
     * @return array ['scanned' => int, 'extended' => int, 'closed' => int, 'admit_card_released' => int, 'exam_completed' => int, 'result_released' => int, 'updated' => int, 'errors' => array]
     */
    public static function revalidateAll(int $limit = 20, ?DateTimeImmutable $now = null): array {
        $now = $now ?: TemporalFactService::nowIST();
        $nowStr = $now->format('Y-m-d H:i:s');

        Logger::info("TemporalRevalidationService: Starting complete lifecycle revalidation at {$nowStr} IST");

        $stats = [
            'scanned' => 0,
            'extended' => 0,
            'closed' => 0,
            'admit_card_released' => 0,
            'exam_completed' => 0,
            'result_released' => 0,
            'updated' => 0,
            'errors' => []
        ];

        try {
            // Monitor the COMPLETE lifecycle:
            // UPCOMING -> ACTIVE -> CLOSED -> ADMIT_CARD_RELEASED -> EXAM_COMPLETED
            $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_name, a.source_url, 
                           a.meta_title, a.meta_description, a.lifecycle_status, a.published_at, a.updated_at
                    FROM articles a
                    WHERE a.status = 'published'
                      AND a.lifecycle_status IN ('upcoming', 'active', 'closed', 'admit_card_released', 'exam_completed')
                    ORDER BY a.updated_at ASC
                    LIMIT " . (int)$limit;

            $candidates = Database::fetchAll($sql);
            $stats['scanned'] = count($candidates);

            foreach ($candidates as $article) {
                try {
                    $res = self::revalidateArticle((int)$article['id'], $article, $now);
                    $act = $res['action'] ?? '';
                    if (!empty($act) && isset($stats[$act])) {
                        $stats[$act]++;
                        $stats['updated']++;
                    }
                } catch (Throwable $e) {
                    $stats['errors'][] = "Article #{$article['id']}: " . $e->getMessage();
                    Logger::error("TemporalRevalidationService error on Article #{$article['id']}: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "Database query failed: " . $e->getMessage();
            Logger::error("TemporalRevalidationService query failed: " . $e->getMessage());
        }

        Logger::info("TemporalRevalidationService complete: {$stats['scanned']} scanned, {$stats['closed']} closed, {$stats['extended']} extended, {$stats['admit_card_released']} admits, {$stats['exam_completed']} exams, {$stats['result_released']} results.");

        return $stats;
    }

    /**
     * Revalidate an individual article based on its current lifecycle stage and verified facts
     *
     * @param int $articleId
     * @param array|null $articleData
     * @param DateTimeImmutable|null $now Reference time in Asia/Kolkata
     * @return array
     */
    public static function revalidateArticle(int $articleId, ?array $articleData = null, ?DateTimeImmutable $now = null): array {
        $now = $now ?: TemporalFactService::nowIST();

        if ($articleData === null) {
            $articleData = Database::fetchOne("SELECT * FROM articles WHERE id = :id LIMIT 1", ['id' => $articleId]);
            if (!$articleData) {
                return ['success' => false, 'action' => 'not_found'];
            }
        }

        $facts = TemporalFactService::getFactsMap($articleId);
        $lifecycle = $articleData['lifecycle_status'] ?? TemporalFactService::LIFECYCLE_ACTIVE;

        switch ($lifecycle) {
            case TemporalFactService::LIFECYCLE_UPCOMING:
                return self::revalidateUpcomingStage($articleId, $articleData, $facts, $now);

            case TemporalFactService::LIFECYCLE_ACTIVE:
                return self::revalidateActiveStage($articleId, $articleData, $facts, $now);

            case TemporalFactService::LIFECYCLE_CLOSED:
                return self::revalidateClosedStage($articleId, $articleData, $facts, $now);

            case TemporalFactService::LIFECYCLE_ADMIT_CARD_RELEASED:
                return self::revalidateAdmitCardStage($articleId, $articleData, $facts, $now);

            case TemporalFactService::LIFECYCLE_EXAM_COMPLETED:
                return self::revalidateExamCompletedStage($articleId, $articleData, $facts, $now);

            default:
                return ['success' => true, 'action' => 'no_action_needed'];
        }
    }

    /**
     * Stage 1: Revalidate UPCOMING articles (Checks if application start date has arrived)
     */
    private static function revalidateUpcomingStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $startFact = $facts['application_start'] ?? null;
        $startVal = $startFact['fact_value'] ?? null;

        if (empty($startVal)) {
            return ['success' => true, 'action' => 'upcoming_no_start_date'];
        }

        $startTime = TemporalFactService::parseDateIST($startVal, '00:00:00');
        if ($startTime !== null && $now >= $startTime) {
            // Application has officially commenced -> Transition to ACTIVE
            Database::update('articles', [
                'lifecycle_status' => TemporalFactService::LIFECYCLE_ACTIVE,
                'updated_at' => $now->format('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Database::insert('article_updates', [
                'article_id' => $articleId,
                'old_content' => $articleData['content'],
                'new_content' => $articleData['content'],
                'reason' => "Autonomous lifecycle transition: Application window opened on {$startTime->format('F d, Y')}. Transitioned to ACTIVE.",
                'source_url' => $articleData['source_url'],
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);

            Logger::info("Article #{$articleId} transitioned from UPCOMING to ACTIVE (Application started).");
            return ['success' => true, 'action' => 'active'];
        }

        return ['success' => true, 'action' => 'upcoming_pending'];
    }

    /**
     * Stage 2: Revalidate ACTIVE articles
     * Handles:
     * - Deadline expiry -> Transition to CLOSED
     * - Point 3: Pre-expiry source freshness checks to catch extensions announced before deadline
     */
    private static function revalidateActiveStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $deadlineFact = $facts['application_extension'] ?? ($facts['application_end'] ?? null);
        $deadlineVal = $deadlineFact['fact_value'] ?? null;
        $sourceUrl = $articleData['source_url'] ?? '';

        // If no structured fact in DB yet, extract from content
        if (empty($deadlineVal)) {
            $combined = $articleData['title'] . ' ' . strip_tags($articleData['content']);
            if (preg_match('/(?:last date|deadline|closing date)\s*(?:is|on|:)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $combined, $m)) {
                $deadlineVal = $m[1];
                TemporalFactService::recordFact($articleId, 'application_end', $deadlineVal, $sourceUrl);
                $facts = TemporalFactService::getFactsMap($articleId);
                $deadlineFact = $facts['application_end'] ?? null;
            }
        }

        if (empty($deadlineVal)) {
            return ['success' => true, 'action' => 'no_deadline_to_evaluate'];
        }

        $deadlineTime = null;
        if (!empty($deadlineFact['valid_until'])) {
            try {
                $deadlineTime = new DateTimeImmutable($deadlineFact['valid_until'], TemporalFactService::getTimeZone());
            } catch (Throwable $e) {}
        }
        if ($deadlineTime === null) {
            $deadlineTime = TemporalFactService::parseDateIST($deadlineVal, '23:59:59');
        }

        if ($deadlineTime === null) {
            return ['success' => true, 'action' => 'unparseable_deadline'];
        }

        // -------------------------------------------------------------------------
        // CASE A: Deadline has passed in Asia/Kolkata -> Check Extension or CLOSE
        // -------------------------------------------------------------------------
        if ($now > $deadlineTime) {
            $extension = self::checkOfficialExtension($sourceUrl, $now);
            if ($extension !== null) {
                // Official extension confirmed
                return self::applyDeadlineExtension($articleId, $articleData, $extension, $sourceUrl, $now);
            }

            // No extension found -> Transition to CLOSED
            return self::applyClosedTransition($articleId, $articleData, $deadlineTime, $sourceUrl, $now);
        }

        // -------------------------------------------------------------------------
        // CASE B: Deadline is in future -> PRE-EXPIRY SOURCE FRESHNESS (Point 3)
        // Checks portal if verified_at is older than 6 hours (does NOT hammer websites)
        // -------------------------------------------------------------------------
        $lastVerified = !empty($deadlineFact['verified_at']) ? strtotime($deadlineFact['verified_at']) : 0;
        $isFreshnessDue = ($now->getTimestamp() - $lastVerified) >= self::SOURCE_FRESHNESS_INTERVAL;

        if ($isFreshnessDue && !empty($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $extension = self::checkOfficialExtension($sourceUrl, $now);
            if ($extension !== null) {
                $newDeadlineTime = TemporalFactService::parseDateIST($extension, '23:59:59');
                if ($newDeadlineTime !== null && $newDeadlineTime > $deadlineTime) {
                    Logger::info("Pre-expiry extension detected for Article #{$articleId}: extended from {$deadlineVal} to {$extension}");
                    return self::applyDeadlineExtension($articleId, $articleData, $extension, $sourceUrl, $now);
                }
            }

            // Touch verified_at on the existing fact to refresh cache window
            if (!empty($deadlineFact['id'])) {
                Database::update('article_temporal_facts', [
                    'verified_at' => $now->format('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $deadlineFact['id']]);
            }

            return ['success' => true, 'action' => 'active_freshness_verified'];
        }

        return ['success' => true, 'action' => 'active_unexpired'];
    }

    /**
     * Stage 3: Revalidate CLOSED articles (Point 2)
     * Continuous monitoring of CLOSED articles for:
     * - Official Admit Card Release
     * - Official Exam Schedule Announcement (without guessing)
     */
    private static function revalidateClosedStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $sourceUrl = $articleData['source_url'] ?? '';
        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'action' => 'closed_no_source'];
        }

        // 6-hour rate-limit guard
        $examFact = $facts['exam_date'] ?? null;
        $lastVerified = !empty($examFact['verified_at']) ? strtotime($examFact['verified_at']) : 0;
        if (($now->getTimestamp() - $lastVerified) < self::SOURCE_FRESHNESS_INTERVAL) {
            return ['success' => true, 'action' => 'closed_cached'];
        }

        $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
        if (empty($portalText)) {
            return ['success' => true, 'action' => 'closed_portal_empty'];
        }

        // 1. Check for Official Admit Card Release
        if (preg_match('/(?:admit card|hall ticket|call letter)\s+(?:is\s+)?(?:released|out|available|download|live)/i', $portalText, $admitMatch)) {
            TemporalFactService::recordFact($articleId, 'admit_card_date', $now->format('F d, Y'), $sourceUrl, [
                'source_type' => 'official',
                'confidence' => 'high',
                'status' => 'verified'
            ]);

            $oldContent = $articleData['content'];
            $oldTitle = $articleData['title'];

            $newTitle = preg_replace('/(?::\s*Application Closed|\(Application Closed\)).*$/i', ': Admit Card Released, Hall Ticket Link & Shift Timings', $oldTitle);
            if ($newTitle === $oldTitle) {
                $newTitle .= ' — Admit Card Released';
            }

            $admitBanner = "<div class='notice-box notice-success' style='background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Admit Card Released!</strong> — The official hall ticket / admit card has been officially released. Candidates can download their admit card via the official portal at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>. Check reporting hours and mandatory exam guidelines below.</div>";
            $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $admitBanner . '$1', $oldContent, 1);

            Database::insert('article_updates', [
                'article_id' => $articleId,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'reason' => "Autonomous lifecycle transition: Official Admit Card release verified on portal ({$sourceUrl}). Transitioned to ADMIT_CARD_RELEASED.",
                'source_url' => $sourceUrl,
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);

            Database::update('articles', [
                'title' => Sanitizer::string($newTitle),
                'content' => Sanitizer::html($newContent),
                'lifecycle_status' => TemporalFactService::LIFECYCLE_ADMIT_CARD_RELEASED,
                'updated_at' => $now->format('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Article #{$articleId} transitioned from CLOSED to ADMIT_CARD_RELEASED.");
            return ['success' => true, 'action' => 'admit_card_released'];
        }

        // 2. Check for Official Exam Date Announcement (Schedule announced, but future)
        if (preg_match('/(?:exam date|cbt date|exam will be held on|examination scheduled for)\s*(?:is|on|:)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $portalText, $examMatch)) {
            $parsedExam = TemporalFactService::parseDateIST($examMatch[1], '23:59:59');
            if ($parsedExam !== null && $parsedExam > $now) {
                $examDateFormatted = $parsedExam->format('F d, Y');
                TemporalFactService::recordFact($articleId, 'exam_date', $examDateFormatted, $sourceUrl, [
                    'source_type' => 'official',
                    'confidence' => 'high',
                    'status' => 'verified',
                    'valid_until' => $parsedExam->format('Y-m-d H:i:s')
                ]);

                // Update exam date in content table from TBA to exact date
                $oldContent = $articleData['content'];
                $newContent = preg_replace('/(Exam Date\s*<\/td>\s*<td[^>]*>).*?(<\/td>)/i', '$1' . $examDateFormatted . '$2', $oldContent);

                if ($newContent !== $oldContent) {
                    Database::insert('article_updates', [
                        'article_id' => $articleId,
                        'old_content' => $oldContent,
                        'new_content' => $newContent,
                        'reason' => "Official exam date announced on portal: {$examDateFormatted}. Updated exam milestone facts.",
                        'source_url' => $sourceUrl,
                        'created_at' => $now->format('Y-m-d H:i:s')
                    ]);

                    Database::update('articles', [
                        'content' => Sanitizer::html($newContent),
                        'updated_at' => $now->format('Y-m-d H:i:s')
                    ], 'id = :id', ['id' => $articleId]);

                    Logger::info("Article #{$articleId}: Official exam date verified as {$examDateFormatted}. Maintained CLOSED.");
                    return ['success' => true, 'action' => 'exam_date_announced'];
                }
            }
        }

        // Refresh cache timestamp
        if (!empty($examFact['id'])) {
            Database::update('article_temporal_facts', ['verified_at' => $now->format('Y-m-d H:i:s')], 'id = :id', ['id' => $examFact['id']]);
        }

        return ['success' => true, 'action' => 'closed_fresh'];
    }

    /**
     * Stage 4: Revalidate ADMIT_CARD_RELEASED articles (Checks if exam has actually completed)
     */
    private static function revalidateAdmitCardStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $examFact = $facts['exam_date'] ?? null;
        $examVal = $examFact['fact_value'] ?? null;

        if (empty($examVal)) {
            return ['success' => true, 'action' => 'admit_card_no_exam_date'];
        }

        $examTime = TemporalFactService::parseDateIST($examVal, '23:59:59');
        if ($examTime !== null && $now > $examTime) {
            // Exam has actually concluded in Asia/Kolkata -> Transition to EXAM_COMPLETED
            $oldContent = $articleData['content'];
            $oldTitle = $articleData['title'];
            $examDateFormatted = $examTime->format('F d, Y');

            $newTitle = preg_replace('/(?::\s*Admit Card Released|\(Admit Card Released\)).*$/i', ': Exam Concluded, Answer Key & Cutoff Updates', $oldTitle);
            if ($newTitle === $oldTitle) {
                $newTitle .= ' — Exam Concluded';
            }

            $examBanner = "<div class='notice-box notice-info' style='background:#f8fafc;border-left:4px solid #0284c7;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Exam Concluded</strong> — The examination concluded on {$examDateFormatted}. Candidates are currently awaiting the official provisional answer key release and objection submission window.</div>";
            $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $examBanner . '$1', $oldContent, 1);

            Database::insert('article_updates', [
                'article_id' => $articleId,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'reason' => "Autonomous lifecycle transition: Exam date ({$examDateFormatted} IST) passed. Transitioned to EXAM_COMPLETED.",
                'source_url' => $articleData['source_url'],
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);

            Database::update('articles', [
                'title' => Sanitizer::string($newTitle),
                'content' => Sanitizer::html($newContent),
                'lifecycle_status' => TemporalFactService::LIFECYCLE_EXAM_COMPLETED,
                'updated_at' => $now->format('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Article #{$articleId} transitioned from ADMIT_CARD_RELEASED to EXAM_COMPLETED.");
            return ['success' => true, 'action' => 'exam_completed'];
        }

        return ['success' => true, 'action' => 'admit_card_active'];
    }

    /**
     * Stage 5: Revalidate EXAM_COMPLETED articles (Checks for official Result declaration)
     */
    private static function revalidateExamCompletedStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $sourceUrl = $articleData['source_url'] ?? '';
        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'action' => 'exam_completed_no_source'];
        }

        // 6-hour rate-limit guard
        $resFact = $facts['result_date'] ?? null;
        $lastVerified = !empty($resFact['verified_at']) ? strtotime($resFact['verified_at']) : 0;
        if (($now->getTimestamp() - $lastVerified) < self::SOURCE_FRESHNESS_INTERVAL) {
            return ['success' => true, 'action' => 'exam_completed_cached'];
        }

        $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
        if (empty($portalText)) {
            return ['success' => true, 'action' => 'exam_completed_portal_empty'];
        }

        // Check for official Result declaration
        if (preg_match('/(?:result|scorecard|merit list)\s+(?:is\s+)?(?:declared|released|published|available|out)/i', $portalText, $resMatch)) {
            TemporalFactService::recordFact($articleId, 'result_date', $now->format('F d, Y'), $sourceUrl, [
                'source_type' => 'official',
                'confidence' => 'high',
                'status' => 'verified'
            ]);

            $oldContent = $articleData['content'];
            $oldTitle = $articleData['title'];

            $newTitle = preg_replace('/(?::\s*Exam Concluded|\(Exam Concluded\)).*$/i', ': Result Declared, Scorecard Link & Merit List Out', $oldTitle);
            if ($newTitle === $oldTitle) {
                $newTitle .= ' — Result Declared';
            }

            $resultBanner = "<div class='notice-box notice-success' style='background:#f0fdf4;border-left:4px solid #16a34a;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Result Officially Declared!</strong> — The official examination result and scorecard link are now active. Candidates can verify their scorecards and category-wise cutoffs at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>.</div>";
            $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $resultBanner . '$1', $oldContent, 1);

            Database::insert('article_updates', [
                'article_id' => $articleId,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'reason' => "Autonomous lifecycle transition: Official Result declaration verified on portal ({$sourceUrl}). Transitioned to RESULT_RELEASED.",
                'source_url' => $sourceUrl,
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);

            Database::update('articles', [
                'title' => Sanitizer::string($newTitle),
                'content' => Sanitizer::html($newContent),
                'lifecycle_status' => TemporalFactService::LIFECYCLE_RESULT_RELEASED,
                'updated_at' => $now->format('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Article #{$articleId} transitioned from EXAM_COMPLETED to RESULT_RELEASED.");
            return ['success' => true, 'action' => 'result_released'];
        }

        if (!empty($resFact['id'])) {
            Database::update('article_temporal_facts', ['verified_at' => $now->format('Y-m-d H:i:s')], 'id = :id', ['id' => $resFact['id']]);
        }

        return ['success' => true, 'action' => 'exam_completed_fresh'];
    }

    /**
     * Helper: Check official portal for extension circulars
     */
    private static function checkOfficialExtension(string $sourceUrl, DateTimeImmutable $now): ?string {
        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
            if (empty($portalText)) {
                return null;
            }

            if (preg_match('/(?:last date|application window|registration)\s+(?:is\s+)?extended\s+(?:up to|to|till)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $portalText, $m)) {
                $parsed = TemporalFactService::parseDateIST($m[1], '23:59:59');
                if ($parsed !== null && $parsed > $now) {
                    return $parsed->format('F d, Y');
                }
            }
        } catch (Throwable $e) {
            Logger::warning("checkOfficialExtension failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Helper: Apply deadline extension
     */
    private static function applyDeadlineExtension(int $articleId, array $articleData, string $extendedDateStr, string $sourceUrl, DateTimeImmutable $now): array {
        TemporalFactService::recordFact($articleId, 'application_extension', $extendedDateStr, $sourceUrl, [
            'source_type' => 'official',
            'confidence' => 'high',
            'status' => 'verified'
        ]);

        $oldContent = $articleData['content'];
        $newContent = preg_replace(
            '/(\b(?:last date|deadline)\s*(?:is|on|:)?\s*)([A-Za-z]+\s+\d{1,2},?\s+\d{4})/i',
            '$1' . $extendedDateStr . ' (Extended)',
            $oldContent
        );

        Database::insert('article_updates', [
            'article_id' => $articleId,
            'old_content' => $oldContent,
            'new_content' => $newContent,
            'reason' => "Official deadline extended to {$extendedDateStr}. Updated temporal facts and maintained ACTIVE state.",
            'source_url' => $sourceUrl,
            'created_at' => $now->format('Y-m-d H:i:s')
        ]);

        Database::update('articles', [
            'content' => Sanitizer::html($newContent),
            'lifecycle_status' => TemporalFactService::LIFECYCLE_ACTIVE,
            'updated_at' => $now->format('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $articleId]);

        Logger::info("Article #{$articleId} extended to {$extendedDateStr}. Maintained ACTIVE.");

        return [
            'success' => true,
            'action' => 'extended',
            'new_deadline' => $extendedDateStr
        ];
    }

    /**
     * Helper: Apply closed transition with CTA stripping and historical preservation
     */
    private static function applyClosedTransition(int $articleId, array $articleData, DateTimeImmutable $deadlineTime, string $sourceUrl, DateTimeImmutable $now): array {
        $oldContent = $articleData['content'];
        $oldTitle = $articleData['title'];
        $deadlineFormatted = $deadlineTime->format('F d, Y');

        // Deterministic Title Patch
        $newTitle = $oldTitle;
        $newTitle = preg_replace('/:\s*Last Date Today.*$/i', ": Application Closed — {$deadlineFormatted}", $newTitle);
        $newTitle = preg_replace('/\bApply Online\b/i', 'Application Closed', $newTitle);
        $newTitle = preg_replace('/\bApply Now\b/i', 'Application Closed', $newTitle);
        if ($newTitle === $oldTitle && !str_contains(strtolower($newTitle), 'closed')) {
            $newTitle .= ' (Application Closed)';
        }

        // Deterministic Content Patch
        $newContent = $oldContent;
        // Strip active CTA buttons / links
        $newContent = preg_replace('/>\s*(?:Apply Online|Apply Now|Click Here to Apply|Register Now)\s*<\/a>/i', '>Application Closed (Official Portal)</a>', $newContent);
        // Replace relative urgency in intro
        $newContent = preg_replace('/\bfinal application deadline is today\b/i', "application process officially concluded on {$deadlineFormatted}", $newContent);
        $newContent = preg_replace('/\bFailing to complete the application process today[^.<>]*\./i', "The application window concluded on {$deadlineFormatted}. Candidates are awaiting admit card and exam schedule notifications.", $newContent);
        $newContent = preg_replace('/\bLast Date Today\b/i', "Last Date: {$deadlineFormatted}", $newContent);

        // Inject Closed Status Banner if not present
        if (!str_contains($newContent, 'Application Status: CLOSED')) {
            $statusBanner = "<div class='notice-box notice-warning' style='background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Application Status: CLOSED</strong> — The registration window officially concluded on {$deadlineFormatted}. Applications are no longer being accepted. Historical recruitment details and upcoming exam announcements are preserved below.</div>";
            $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $statusBanner . '$1', $newContent, 1);
        }

        // Deterministic Excerpt & Meta Title Patch
        $newExcerpt = $articleData['excerpt'];
        $newExcerpt = preg_replace('/\bApply Online\b/i', 'Application Closed', $newExcerpt);
        $newExcerpt = preg_replace('/\b(?:is today|closes today)\b/i', "closed on {$deadlineFormatted}", $newExcerpt);

        $newMetaTitle = $articleData['meta_title'] ?: $newTitle;
        $newMetaTitle = preg_replace('/\bApply Online\b/i', 'Application Closed', $newMetaTitle);
        $newMetaTitle = preg_replace('/\bApply Now\b/i', 'Application Closed', $newMetaTitle);

        // Validate repaired content before persisting
        $audit = TemporalContentValidator::validate([
            'title' => $newTitle,
            'content' => $newContent,
            'excerpt' => $newExcerpt,
            'meta_title' => $newMetaTitle,
            'meta_description' => $articleData['meta_description']
        ], [], TemporalFactService::LIFECYCLE_CLOSED, $now);

        // Record Snapshot in article_updates Table
        Database::insert('article_updates', [
            'article_id' => $articleId,
            'old_content' => $oldContent,
            'new_content' => $newContent,
            'reason' => "Autonomous lifecycle transition: Application deadline passed on {$deadlineFormatted} (IST). Transitioned to CLOSED, removed active CTAs, and preserved historical facts.",
            'source_url' => $sourceUrl,
            'created_at' => $now->format('Y-m-d H:i:s')
        ]);

        // Update Article Record in Database
        Database::update('articles', [
            'title' => Sanitizer::string($newTitle),
            'content' => Sanitizer::html($newContent),
            'excerpt' => Sanitizer::string($newExcerpt),
            'meta_title' => Sanitizer::string($newMetaTitle),
            'lifecycle_status' => TemporalFactService::LIFECYCLE_CLOSED,
            'updated_at' => $now->format('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $articleId]);

        Logger::info("Article #{$articleId} ('{$articleData['title']}') successfully transitioned to CLOSED (Deadline: {$deadlineFormatted}).");

        return [
            'success' => true,
            'action' => 'closed',
            'deadline' => $deadlineFormatted,
            'violations_count' => count($audit['violations'])
        ];
    }
}
