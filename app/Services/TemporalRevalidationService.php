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
            // UPCOMING -> ACTIVE -> CLOSED -> ADMIT_CARD_RELEASED -> EXAM_COMPLETED -> DRAFT
            $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_name, a.source_url, 
                           a.meta_title, a.meta_description, a.lifecycle_status, a.published_at, a.updated_at
                    FROM articles a
                    WHERE a.status = 'published'
                      AND a.lifecycle_status IN ('upcoming', 'active', 'closed', 'admit_card_released', 'exam_completed', 'draft')
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

        // Statutory Authority Grounding: Ensure article is mapped to an authoritative .gov.in / official portal
        $currentSource = $articleData['source_url'] ?? '';
        if (empty($currentSource) || str_contains($currentSource, 'sarkari.online') || str_contains($currentSource, 'trends.google.com') || !filter_var($currentSource, FILTER_VALIDATE_URL)) {
            $resolved = AuthorityFactFetcherService::resolveAuthority($articleData['title'], $currentSource);
            if (!empty($resolved['portal']) && filter_var($resolved['portal'], FILTER_VALIDATE_URL)) {
                $articleData['source_url'] = $resolved['portal'];
                $articleData['authority_url'] = $resolved['portal'];
                $articleData['source_name'] = $resolved['name'];
                $articleData['authority_name'] = $resolved['name'];
                $articleData['authority_tier'] = 'tier_1a';
                Database::update('articles', [
                    'source_url' => $resolved['portal'],
                    'authority_url' => $resolved['portal'],
                    'source_name' => $resolved['name'],
                    'authority_name' => $resolved['name'],
                    'authority_tier' => 'tier_1a'
                ], 'id = :id', ['id' => $articleId]);
            }
        }

        $facts = TemporalFactService::getFactsMap($articleId);
        $lifecycle = $articleData['lifecycle_status'] ?? TemporalFactService::LIFECYCLE_ACTIVE;

        switch ($lifecycle) {
            case TemporalFactService::LIFECYCLE_DRAFT:
                return self::revalidateDraftStage($articleId, $articleData, $facts, $now);

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
     * Stage 0: Revalidate DRAFT / NEEDS_REVIEW articles that are published
     * Re-checks authoritative portal to see if official application/exam timeline has been declared.
     */
    private static function revalidateDraftStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $sourceUrl = $articleData['source_url'] ?? '';
        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'action' => 'draft_no_source'];
        }

        // 6-hour rate limit guard
        $lastVerified = 0;
        foreach ($facts as $f) {
            if (!empty($f['verified_at'])) {
                $ts = strtotime($f['verified_at']);
                if ($ts > $lastVerified) $lastVerified = $ts;
            }
        }
        if (($now->getTimestamp() - $lastVerified) < self::SOURCE_FRESHNESS_INTERVAL) {
            return ['success' => true, 'action' => 'draft_cached'];
        }

        $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
        if (empty($portalText)) {
            return ['success' => true, 'action' => 'draft_portal_empty'];
        }

        // Check for official deadline announcement
        $announcedDeadline = self::extractDeadlineFromPortalText($portalText, $now);
        if ($announcedDeadline !== null) {
            return self::applyNewlyAnnouncedDeadline($articleId, $articleData, $announcedDeadline, $sourceUrl, $now);
        }

        // Check for official Admit Card or Exam announcement
        if (preg_match('/(?:admit card|hall ticket|call letter)\s+(?:is\s+)?(?:released|out|available|download|live)/i', $portalText)) {
            return self::revalidateClosedStage($articleId, $articleData, $facts, $now);
        }

        // Refresh verified_at cache
        foreach ($facts as $f) {
            if (!empty($f['id'])) {
                Database::update('article_temporal_facts', ['verified_at' => $now->format('Y-m-d H:i:s')], 'id = :id', ['id' => $f['id']]);
                break;
            }
        }

        return ['success' => true, 'action' => 'draft_fresh'];
    }

    /**
     * Stage 1: Revalidate UPCOMING articles (Checks if application start date has arrived)
     */
    private static function revalidateUpcomingStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $startFact = $facts['application_start'] ?? null;
        $startVal = $startFact['fact_value'] ?? null;

        if (empty($startVal)) {
            $sourceUrl = $articleData['source_url'] ?? '';
            if (!empty($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
                $lastVerified = !empty($startFact['verified_at']) ? strtotime($startFact['verified_at']) : 0;
                if (($now->getTimestamp() - $lastVerified) >= self::SOURCE_FRESHNESS_INTERVAL) {
                    $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
                    if (!empty($portalText)) {
                        $announcedDeadline = self::extractDeadlineFromPortalText($portalText, $now);
                        if ($announcedDeadline !== null) {
                            return self::applyNewlyAnnouncedDeadline($articleId, $articleData, $announcedDeadline, $sourceUrl, $now);
                        }
                    }
                }
            }
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
     * - Gap 1: Detection of announced application deadline when previously NULL
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
            // Check if sourceUrl exists and can be polled
            if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
                return ['success' => true, 'action' => 'no_deadline_to_evaluate'];
            }

            // 6-hour rate limit guard
            $pendingFact = $facts['application_end'] ?? null;
            $lastVerified = !empty($pendingFact['verified_at']) ? strtotime($pendingFact['verified_at']) : 0;
            if (($now->getTimestamp() - $lastVerified) < self::SOURCE_FRESHNESS_INTERVAL) {
                return ['success' => true, 'action' => 'deadline_unannounced_cached'];
            }

            // Fetch official portal text
            $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
            if (empty($portalText)) {
                return ['success' => true, 'action' => 'portal_empty'];
            }

            $announcedDeadline = self::extractDeadlineFromPortalText($portalText, $now);
            if ($announcedDeadline !== null) {
                return self::applyNewlyAnnouncedDeadline($articleId, $articleData, $announcedDeadline, $sourceUrl, $now);
            }

            // Still unannounced: touch verified_at on pending fact to respect 6-hour cache
            if (!empty($pendingFact['id'])) {
                Database::update('article_temporal_facts', ['verified_at' => $now->format('Y-m-d H:i:s')], 'id = :id', ['id' => $pendingFact['id']]);
            }

            return ['success' => true, 'action' => 'deadline_still_unannounced'];
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
     *
     * INVARIANT: EXAM_COMPLETED MUST NOT BE BASED ON TIME ALONE!
     * Operational cutoff: 18:00 IST on exam date is retained as a timing boundary, not the sole factual proof.
     * Re-check authoritative source to verify there is NO official postponement, cancellation, or rescheduling.
     * Only allow EXAM_COMPLETED when authoritative source confirms absence of postponement.
     */
    private static function revalidateAdmitCardStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $examFact = $facts['exam_date'] ?? null;
        $examVal = $examFact['fact_value'] ?? null;

        if (empty($examVal)) {
            return ['success' => true, 'action' => 'admit_card_no_exam_date'];
        }

        // Operational cutoff: 18:00 IST on exam date
        $examTime = TemporalFactService::parseDateIST($examVal, '18:00:00');
        if ($examTime === null || $now <= $examTime) {
            return ['success' => true, 'action' => 'admit_card_active'];
        }

        // Check if database already has a recorded postponement or cancellation
        if (!empty($facts['exam_postponement']) || in_array(strtolower($facts['exam_status']['fact_value'] ?? ''), ['postponed', 'cancelled', 'rescheduled', 'deferred'], true)) {
            Logger::info("Article #{$articleId}: Exam date passed but postponement already recorded. Will NOT transition to EXAM_COMPLETED.");
            return ['success' => true, 'action' => 'exam_postponed_known'];
        }

        // Operational cutoff passed -> Re-check authoritative source for postponement/cancellation notices
        $sourceUrl = $articleData['source_url'] ?? '';
        if (!empty($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
            if (!empty($portalText)) {
                // Check for official postponement or reschedule
                if (preg_match('/(?:exam|examination|cbt|paper)\s+(?:has\s+been\s+|is\s+)?(?:postponed|cancelled|rescheduled|deferred|put on hold)/i', $portalText) ||
                    preg_match('/(?:postponement|cancellation|rescheduling)\s+of\s+(?:the\s+)?(?:exam|examination)/i', $portalText)) {

                    TemporalFactService::recordFact($articleId, 'exam_postponement', 'Postponed by official notice', $sourceUrl, [
                        'source_type' => 'official',
                        'confidence' => 'high',
                        'status' => 'verified'
                    ]);

                    $oldContent = $articleData['content'];
                    $postponeBanner = "<div class='notice-box notice-warning' style='background:#fefce8;border-left:4px solid #eab308;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Notice: Examination Postponed</strong> — The examination previously scheduled for {$examVal} has been postponed by official notification. Candidates are advised to monitor the official portal at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a> for rescheduled dates.</div>";
                    $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $postponeBanner . '$1', $oldContent, 1);

                    Database::insert('article_updates', [
                        'article_id' => $articleId,
                        'old_content' => $oldContent,
                        'new_content' => $newContent,
                        'reason' => "Official postponement verified on portal ({$sourceUrl}) after scheduled exam date. Prevented EXAM_COMPLETED transition.",
                        'source_url' => $sourceUrl,
                        'created_at' => $now->format('Y-m-d H:i:s')
                    ]);

                    Database::update('articles', [
                        'content' => Sanitizer::html($newContent),
                        'updated_at' => $now->format('Y-m-d H:i:s')
                    ], 'id = :id', ['id' => $articleId]);

                    Logger::warning("Article #{$articleId}: Exam date passed, but official postponement detected. Maintained ADMIT_CARD_RELEASED/POSTPONED.");
                    return ['success' => true, 'action' => 'exam_postponed'];
                }
            }
        }

        // INVARIANT (Rule 1 / TEST 21):
        // Absence of a postponement notice is NOT proof that an exam occurred.
        // Authoritative evidence of actual conduct/completion is required (conduct notice, answer key, scorecard, or completed status).
        // If completion evidence is unavailable, preserve safest current lifecycle and flag for verification.
        $hasConductedEvidence = 
            (!empty($portalText) && (
                preg_match('/(?:exam|examination|cbt)\s+(?:concluded|conducted|held|completed)\s+successfully/i', $portalText) ||
                preg_match('/(?:answer\s*key|response\s*sheet|provisional\s+key)\s+(?:for\s+exam\s+held|is\s+released)/i', $portalText)
            )) ||
            !empty($facts['answer_key']) ||
            !empty($facts['scorecard']) ||
            (($facts['exam_date']['status'] ?? '') === 'completed');

        if (!$hasConductedEvidence) {
            Logger::warning("Article #{$articleId}: Exam date passed ({$examVal}), zero postponement found, but NO authoritative completion evidence. Preserving ADMIT_CARD_RELEASED and flagging for verification.");
            return ['success' => true, 'action' => 'exam_completion_unverified', 'flag' => 'needs_verification'];
        }

        // Exam concluded with verified conduct evidence -> Transition to EXAM_COMPLETED
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
            'reason' => "Autonomous lifecycle transition: Exam date ({$examDateFormatted} IST) concluded with zero official postponement. Transitioned to EXAM_COMPLETED.",
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

    /**
     * Stage 5: Revalidate EXAM_COMPLETED articles
     *
     * Distinguishes post-exam official events:
     * - answer_key (NOT a result, does NOT trigger RESULT_RELEASED)
     * - provisional_merit_list (interim selection list, does NOT trigger RESULT_RELEASED)
     * - scorecard (standalone marks link, does NOT trigger RESULT_RELEASED unless final result declared)
     * - final_merit_list / result (ONLY these trigger RESULT_RELEASED)
     *
     * INVARIANT: RESULT_RELEASED remains the permanent active lifecycle state after result publication.
     * Do NOT automatically change lifecycle_status to ARCHIVED merely because result was released!
     */
    private static function revalidateExamCompletedStage(int $articleId, array $articleData, array $facts, DateTimeImmutable $now): array {
        $sourceUrl = $articleData['source_url'] ?? '';
        if (empty($sourceUrl) || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'action' => 'exam_completed_no_source'];
        }

        // 6-hour rate-limit guard
        $resFact = $facts['result_date'] ?? ($facts['final_result'] ?? null);
        $lastVerified = !empty($resFact['verified_at']) ? strtotime($resFact['verified_at']) : 0;
        if (($now->getTimestamp() - $lastVerified) < self::SOURCE_FRESHNESS_INTERVAL) {
            return ['success' => true, 'action' => 'exam_completed_cached'];
        }

        $portalText = (new AuthorityFactFetcherService())->fetchPortalText($sourceUrl);
        if (empty($portalText)) {
            return ['success' => true, 'action' => 'exam_completed_portal_empty'];
        }

        // Classify the post-exam event using official event classification
        $event = TemporalFactService::classifyPostExamEvent($portalText);
        if ($event === null) {
            if (!empty($resFact['id'])) {
                Database::update('article_temporal_facts', ['verified_at' => $now->format('Y-m-d H:i:s')], 'id = :id', ['id' => $resFact['id']]);
            }
            return ['success' => true, 'action' => 'exam_completed_fresh'];
        }

        $oldContent = $articleData['content'];

        // Event 1: Answer Key (NOT a result — maintains EXAM_COMPLETED)
        if ($event['type'] === TemporalFactService::EVENT_ANSWER_KEY) {
            if (empty($facts['answer_key'])) {
                TemporalFactService::recordFact($articleId, 'answer_key', $now->format('F d, Y'), $sourceUrl, [
                    'source_type' => 'official',
                    'confidence' => 'high',
                    'status' => 'verified'
                ]);

                $keyBanner = "<div class='notice-box notice-info' style='background:#f0f9ff;border-left:4px solid #0284c7;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Answer Key Released!</strong> — The official answer key / response sheet has been released. Candidates can download the key and submit objections via the official portal at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>.</div>";
                $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $keyBanner . '$1', $oldContent, 1);

                Database::insert('article_updates', [
                    'article_id' => $articleId,
                    'old_content' => $oldContent,
                    'new_content' => $newContent,
                    'reason' => "Official Answer Key release verified on portal ({$sourceUrl}). Maintained EXAM_COMPLETED stage.",
                    'source_url' => $sourceUrl,
                    'created_at' => $now->format('Y-m-d H:i:s')
                ]);

                Database::update('articles', [
                    'content' => Sanitizer::html($newContent),
                    'updated_at' => $now->format('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $articleId]);

                Logger::info("Article #{$articleId}: Answer Key verified. Maintained EXAM_COMPLETED.");
                return ['success' => true, 'action' => 'answer_key_released'];
            }
            return ['success' => true, 'action' => 'answer_key_already_recorded'];
        }

        // Event 2: Provisional Merit List (Interim list — maintains EXAM_COMPLETED, NOT RESULT_RELEASED)
        if ($event['type'] === TemporalFactService::EVENT_PROVISIONAL_MERIT) {
            if (empty($facts['provisional_merit_list'])) {
                TemporalFactService::recordFact($articleId, 'provisional_merit_list', $now->format('F d, Y'), $sourceUrl, [
                    'source_type' => 'official',
                    'confidence' => 'high',
                    'status' => 'verified'
                ]);

                $provBanner = "<div class='notice-box notice-warning' style='background:#fefce8;border-left:4px solid #ca8a04;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Provisional Merit List Released</strong> — The provisional selection list has been published. Please note this list is subject to document verification and final scrutiny. Check official portal at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>.</div>";
                $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $provBanner . '$1', $oldContent, 1);

                Database::insert('article_updates', [
                    'article_id' => $articleId,
                    'old_content' => $oldContent,
                    'new_content' => $newContent,
                    'reason' => "Provisional Merit List verified on portal ({$sourceUrl}). Maintained EXAM_COMPLETED stage (pending final selection outcome).",
                    'source_url' => $sourceUrl,
                    'created_at' => $now->format('Y-m-d H:i:s')
                ]);

                Database::update('articles', [
                    'content' => Sanitizer::html($newContent),
                    'updated_at' => $now->format('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $articleId]);

                Logger::info("Article #{$articleId}: Provisional Merit List verified. Maintained EXAM_COMPLETED.");
                return ['success' => true, 'action' => 'provisional_merit_released'];
            }
            return ['success' => true, 'action' => 'provisional_merit_already_recorded'];
        }

        // Event 3: Standalone Scorecard / Marks Link (maintains EXAM_COMPLETED)
        if ($event['type'] === TemporalFactService::EVENT_SCORECARD && !$event['is_final_outcome']) {
            if (empty($facts['scorecard'])) {
                TemporalFactService::recordFact($articleId, 'scorecard', $now->format('F d, Y'), $sourceUrl, [
                    'source_type' => 'official',
                    'confidence' => 'high',
                    'status' => 'verified'
                ]);

                $scoreBanner = "<div class='notice-box notice-info' style='background:#f0fdf4;border-left:4px solid #16a34a;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Candidate Scorecard Active</strong> — Individual scorecards and marks are now accessible on the official portal at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>.</div>";
                $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $scoreBanner . '$1', $oldContent, 1);

                Database::insert('article_updates', [
                    'article_id' => $articleId,
                    'old_content' => $oldContent,
                    'new_content' => $newContent,
                    'reason' => "Scorecard portal link verified on ({$sourceUrl}). Maintained EXAM_COMPLETED stage.",
                    'source_url' => $sourceUrl,
                    'created_at' => $now->format('Y-m-d H:i:s')
                ]);

                Database::update('articles', [
                    'content' => Sanitizer::html($newContent),
                    'updated_at' => $now->format('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $articleId]);

                Logger::info("Article #{$articleId}: Scorecard verified. Maintained EXAM_COMPLETED.");
                return ['success' => true, 'action' => 'scorecard_released'];
            }
            return ['success' => true, 'action' => 'scorecard_already_recorded'];
        }

        // Event 4 & 5: Final Result / Final Merit List -> Transition to RESULT_RELEASED
        if ($event['is_final_outcome'] === true) {
            $factKey = ($event['type'] === TemporalFactService::EVENT_FINAL_MERIT_LIST) ? 'final_merit_list' : 'result_date';
            TemporalFactService::recordFact($articleId, $factKey, $now->format('F d, Y'), $sourceUrl, [
                'source_type' => 'official',
                'confidence' => 'high',
                'status' => 'verified'
            ]);

            $oldTitle = $articleData['title'];
            $newTitle = preg_replace('/(?::\s*Exam Concluded|\(Exam Concluded\)).*$/i', ': Result Declared, Scorecard Link & Final Merit List Out', $oldTitle);
            if ($newTitle === $oldTitle) {
                $newTitle .= ' — Result Declared';
            }

            $resultBanner = "<div class='notice-box notice-success' style='background:#f0fdf4;border-left:4px solid #16a34a;padding:12px 16px;margin:16px 0;border-radius:4px;'><strong>Final Result Officially Declared!</strong> — The official examination result and final merit list have been declared. Candidates can verify selection outcomes, category-wise cutoffs, and scorecards at <a href='{$sourceUrl}' target='_blank' rel='noopener noreferrer'>{$sourceUrl}</a>.</div>";
            $newContent = preg_replace('/(<h2>.*?<\/h2>)/i', $resultBanner . '$1', $oldContent, 1);

            Database::insert('article_updates', [
                'article_id' => $articleId,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'reason' => "Autonomous lifecycle transition: Official {$event['label']} verified on portal ({$sourceUrl}). Transitioned to RESULT_RELEASED.",
                'source_url' => $sourceUrl,
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);

            // RESULT_RELEASED is the permanent active lifecycle state (NEVER auto-archived)
            Database::update('articles', [
                'title' => Sanitizer::string($newTitle),
                'content' => Sanitizer::html($newContent),
                'lifecycle_status' => TemporalFactService::LIFECYCLE_RESULT_RELEASED,
                'updated_at' => $now->format('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Article #{$articleId} transitioned to RESULT_RELEASED ({$event['label']}). State remains RESULT_RELEASED (never auto-archived).");
            return ['success' => true, 'action' => 'result_released', 'event' => $event['type']];
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
     * Helper: Extract application deadline date from official portal text
     */
    public static function extractDeadlineFromPortalText(string $portalText, DateTimeImmutable $now): ?string {
        $patterns = [
            '/(?:last date|closing date|deadline|apply online up to|registration end date|registration closes on|applications? (?:will\s+)?close on|online applications? closes? on|applications accepted till|apply till)\s*(?:is|on|:|till|up to)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
            '/(?:last date|application window|registration|submission of online application)\s*(?:for\s+[^.]+?)?\s*(?:is\s+)?extended\s+(?:up to|to|till)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
            '/(?:extended\s+(?:up to|to|till)?)\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
            '/(?:last date for submission of online application|closing date for online registration)\s*(?:is|on|:|till|up to)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
            '/(?:close|closes|conclude|concludes|ends)\s+(?:on|by)\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i'
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $portalText, $m)) {
                $parsed = TemporalFactService::parseDateIST($m[1], '23:59:59');
                if ($parsed !== null) {
                    return $parsed->format('F d, Y');
                }
            }
        }

        return null;
    }

    /**
     * Helper: Safely update in-place Key Dates table cells and prose in article HTML
     */
    public static function updateKeyDateInContent(string $content, string $factName, ?string $newDateStr, bool $isExtension = false): string {
        if (empty($newDateStr)) {
            return $content;
        }

        $formattedReplacement = $newDateStr . ($isExtension ? ' (Extended)' : '');

        if ($factName === 'application_end' || $factName === 'application_extension') {
            // 1. HTML Table Cell Replacement:
            // Matches: <td>Application Last Date</td><td>To Be Announced</td>
            // Or: <td>Last Date</td><td>September 02, 2026</td>
            // Or: <td>Last Date</td><td>October 10, 2026 (Extended)</td>
            $patternTable = '/(<(?:td|th)[^>]*>(?:Application\s+(?:Last\s+Date|Deadline|Closing\s+Date)|Last\s+Date(?:\s+to\s+Apply)?|Closing\s+Date)<\/(?:td|th)>\s*<(?:td|th)[^>]*>)(?:To\s+Be\s+Announced|TBA|Awaiting[^<]*|(?:[A-Za-z]+\s+\d{1,2},?\s+\d{4}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})(?:\s*\(Extended\))?)(<\/(?:td|th)>)/i';
            $content = preg_replace($patternTable, '$1' . $formattedReplacement . '$2', $content);

            // 2. Prose Text Replacement:
            // Matches: Last Date: To Be Announced  OR  Deadline: September 02, 2026 (Extended)
            $patternProse = '/(\b(?:last date|deadline|closing date)\s*(?:is|on|:)\s*)(?:to be announced|tba|awaiting[^.,<\n]*|(?:[A-Za-z]+\s+\d{1,2},?\s+\d{4}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})(?:\s*\(Extended\))?)/i';
            $content = preg_replace($patternProse, '$1' . $formattedReplacement, $content);
        } elseif ($factName === 'exam_date') {
            // Table cell for exam date
            $patternTable = '/(<(?:td|th)[^>]*>(?:Exam\s+Date|CBT\s+Date|Examination\s+Date)<\/(?:td|th)>\s*<(?:td|th)[^>]*>)(?:To\s+Be\s+Announced|TBA|Awaiting[^<]*|(?:[A-Za-z]+\s+\d{1,2},?\s+\d{4}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}))(<\/(?:td|th)>)/i';
            $content = preg_replace($patternTable, '$1' . $formattedReplacement . '$2', $content);
        }

        return $content;
    }

    /**
     * Helper: Apply newly announced application deadline
     */
    public static function applyNewlyAnnouncedDeadline(int $articleId, array $articleData, string $announcedDateStr, string $sourceUrl, DateTimeImmutable $now): array {
        $parsedDate = TemporalFactService::parseDateIST($announcedDateStr, '23:59:59');
        $validUntil = $parsedDate ? $parsedDate->format('Y-m-d H:i:s') : null;

        // 1. Record verified fact in article_temporal_facts
        TemporalFactService::recordFact($articleId, 'application_end', $announcedDateStr, $sourceUrl, [
            'source_type' => 'official',
            'confidence' => 'high',
            'status' => 'verified',
            'valid_until' => $validUntil
        ]);

        $oldContent = $articleData['content'];
        $newContent = self::updateKeyDateInContent($oldContent, 'application_end', $announcedDateStr, false);

        // 2. Insert snapshot into article_updates
        Database::insert('article_updates', [
            'article_id' => $articleId,
            'old_content' => $oldContent,
            'new_content' => $newContent,
            'reason' => "Official application deadline announced on portal ({$sourceUrl}): {$announcedDateStr}. Updated temporal facts and in-place key dates.",
            'source_url' => $sourceUrl,
            'created_at' => $now->format('Y-m-d H:i:s')
        ]);

        // 3. Resolve lifecycle
        $newLifecycle = TemporalFactService::resolveLifecycle($articleId, [], null, null, $now);

        // 4. Update article record in database
        Database::update('articles', [
            'content' => Sanitizer::html($newContent),
            'lifecycle_status' => $newLifecycle,
            'updated_at' => $now->format('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $articleId]);

        Logger::info("Article #{$articleId} successfully enriched: application deadline announced as {$announcedDateStr} (lifecycle: {$newLifecycle}).");

        return [
            'success' => true,
            'action' => 'deadline_announced',
            'deadline' => $announcedDateStr,
            'lifecycle' => $newLifecycle
        ];
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
        $newContent = self::updateKeyDateInContent($oldContent, 'application_extension', $extendedDateStr, true);

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
