<?php
/**
 * Sarkari.online - Autonomous Temporal Revalidation & Lifecycle Transition Service
 *
 * Scans active articles whose application or exam milestones have passed in Asia/Kolkata,
 * cross-checks official portals for extension circulars, transitions expired milestones to CLOSED,
 * strips active application CTAs, preserves historical facts, and logs immutable revisions.
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

    /**
     * Run full autonomous revalidation pass across published articles
     *
     * @param int $limit Max articles to revalidate per pass
     * @param DateTimeImmutable|null $now Reference time in Asia/Kolkata
     * @return array ['scanned' => int, 'extended' => int, 'closed' => int, 'updated' => int, 'errors' => array]
     */
    public static function revalidateAll(int $limit = 20, ?DateTimeImmutable $now = null): array {
        $now = $now ?: TemporalFactService::nowIST();
        $nowStr = $now->format('Y-m-d H:i:s');

        Logger::info("TemporalRevalidationService: Starting revalidation run at {$nowStr} IST");

        $stats = [
            'scanned' => 0,
            'extended' => 0,
            'closed' => 0,
            'updated' => 0,
            'errors' => []
        ];

        try {
            // Find published articles where lifecycle is ACTIVE or UPCOMING
            // and an application_end or valid_until has passed or is today
            $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_name, a.source_url, 
                           a.meta_title, a.meta_description, a.lifecycle_status, a.published_at
                    FROM articles a
                    WHERE a.status = 'published'
                      AND a.lifecycle_status IN ('active', 'upcoming')
                    ORDER BY a.updated_at ASC
                    LIMIT " . (int)$limit;

            $candidates = Database::fetchAll($sql);
            $stats['scanned'] = count($candidates);

            foreach ($candidates as $article) {
                try {
                    $res = self::revalidateArticle((int)$article['id'], $article, $now);
                    if (!empty($res['action'])) {
                        if ($res['action'] === 'extended') {
                            $stats['extended']++;
                            $stats['updated']++;
                        } elseif ($res['action'] === 'closed') {
                            $stats['closed']++;
                            $stats['updated']++;
                        }
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

        Logger::info("TemporalRevalidationService completed: {$stats['scanned']} scanned, {$stats['closed']} closed, {$stats['extended']} extended, {$stats['updated']} updated.");

        return $stats;
    }

    /**
     * Revalidate an individual article against its facts and official source
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

        // Find application deadline fact
        $deadlineFact = $facts['application_extension'] ?? ($facts['application_end'] ?? null);
        $deadlineVal = $deadlineFact['fact_value'] ?? null;

        // If no structured fact exists in DB yet, try regex extraction from content or title
        if (empty($deadlineVal)) {
            $combined = $articleData['title'] . ' ' . strip_tags($articleData['content']);
            if (preg_match('/(?:last date|deadline|closing date)\s*(?:is|on|:)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $combined, $m)) {
                $deadlineVal = $m[1];
                // Record fact into DB
                TemporalFactService::recordFact($articleId, 'application_end', $deadlineVal, $articleData['source_url'] ?? null);
                $facts = TemporalFactService::getFactsMap($articleId);
                $deadlineFact = $facts['application_end'] ?? null;
            }
        }

        if (empty($deadlineVal)) {
            return ['success' => true, 'action' => 'no_deadline_to_evaluate'];
        }

        // Parse deadline time in Asia/Kolkata
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

        // If deadline is still in the future, check if currently ACTIVE
        if ($now <= $deadlineTime) {
            return ['success' => true, 'action' => 'active_unexpired'];
        }

        // =========================================================================
        // DEADLINE HAS PASSED IN ASIA/KOLKATA
        // 1. Check Official Portal for Extension / Reopening Notice
        // =========================================================================
        $sourceUrl = $articleData['source_url'] ?? '';
        $isExtended = false;
        $extendedDateStr = null;

        if (!empty($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            try {
                $fetcher = new AuthorityFactFetcherService();
                $portalText = $fetcher->fetchPortalText($sourceUrl);

                if (!empty($portalText)) {
                    // Check for official extension notices
                    if (preg_match('/(?:last date|application window|registration)\s+(?:is\s+)?extended\s+(?:up to|to|till)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $portalText, $extMatch)) {
                        $parsedExt = TemporalFactService::parseDateIST($extMatch[1], '23:59:59');
                        if ($parsedExt !== null && $parsedExt > $now) {
                            $isExtended = true;
                            $extendedDateStr = $parsedExt->format('F d, Y');
                        }
                    }
                }
            } catch (Throwable $e) {
                Logger::warning("Revalidation portal check failed for Article #{$articleId}: " . $e->getMessage());
            }
        }

        // =========================================================================
        // 2. Official Extension Found -> Maintain ACTIVE with New Absolute Deadline
        // =========================================================================
        if ($isExtended && !empty($extendedDateStr)) {
            TemporalFactService::recordFact($articleId, 'application_extension', $extendedDateStr, $sourceUrl, [
                'source_type' => 'official',
                'confidence' => 'high',
                'status' => 'verified'
            ]);

            // Deterministically patch content with new extension date
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
                'content' => $newContent,
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

        // =========================================================================
        // 3. No Extension Found -> Deterministically Transition to CLOSED
        // =========================================================================
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
        ], $facts, TemporalFactService::LIFECYCLE_CLOSED, $now);

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
