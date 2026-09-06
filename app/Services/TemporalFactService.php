<?php
/**
 * Sarkari.online - Source-Backed Temporal Fact Service & Lifecycle Resolution Engine
 *
 * Manages structured date provenance, authoritative source verification,
 * Asia/Kolkata timezone calculations, zero-hallucination unannounced date guards,
 * and deterministic article lifecycle state machine.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Services\AuthorityVerificationService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class TemporalFactService {

    public const TIMEZONE = 'Asia/Kolkata';

    // Core Lifecycle States
    public const LIFECYCLE_DRAFT                = 'draft';
    public const LIFECYCLE_UPCOMING             = 'upcoming';
    public const LIFECYCLE_ACTIVE               = 'active';
    public const LIFECYCLE_CLOSED               = 'closed';
    public const LIFECYCLE_EXAM_COMPLETED       = 'exam_completed';
    public const LIFECYCLE_ADMIT_CARD_RELEASED  = 'admit_card_released';
    public const LIFECYCLE_RESULT_RELEASED      = 'result_released';
    public const LIFECYCLE_HISTORICAL           = 'historical';
    public const LIFECYCLE_EVERGREEN            = 'evergreen';
    public const LIFECYCLE_ARCHIVED             = 'archived';

    // Supported Critical Fact Names
    public const VALID_FACT_NAMES = [
        'application_start',
        'application_end',
        'application_extension',
        'fee_deadline',
        'admit_card_date',
        'exam_date',
        'answer_key_date',
        'result_date',
        'counselling_date',
        'document_verification_date'
    ];

    private static ?DateTimeZone $tzInstance = null;

    public static function getTimeZone(): DateTimeZone {
        if (self::$tzInstance === null) {
            self::$tzInstance = new DateTimeZone(self::TIMEZONE);
        }
        return self::$tzInstance;
    }

    /**
     * Get current DateTime in Asia/Kolkata (+05:30)
     */
    public static function nowIST(): DateTimeImmutable {
        return new DateTimeImmutable('now', self::getTimeZone());
    }

    /**
     * Parse human or standard date string into DateTimeImmutable strictly in Asia/Kolkata
     *
     * @param string|null $dateStr
     * @param string $fallbackTime Default time component (e.g. '23:59:59' for end-of-day deadlines)
     * @return DateTimeImmutable|null
     */
    public static function parseDateIST(?string $dateStr, string $fallbackTime = '23:59:59'): ?DateTimeImmutable {
        if ($dateStr === null) {
            return null;
        }

        $clean = trim($dateStr);
        if ($clean === '' || self::isUnannouncedValue($clean)) {
            return null;
        }

        // Clean out ordinal suffixes like 2nd, 3rd, 15th
        $normalized = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $clean);
        // Normalize multiple spaces
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        try {
            // If string does not contain time (no colon), append fallback time
            if (!str_contains($normalized, ':')) {
                // Remove trailing dots/commas
                $normalized = rtrim($normalized, ' .,');
                $normalized .= ' ' . $fallbackTime;
            }

            return new DateTimeImmutable($normalized, self::getTimeZone());
        } catch (Throwable $e) {
            // Try standard regex extraction for "September 02, 2026" or "02 September 2026" or "2026-09-02"
            if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $clean, $m)) {
                return new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]} {$fallbackTime}", self::getTimeZone());
            }
            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $clean, $m)) {
                return new DateTimeImmutable("{$m[3]}-{$m[2]}-{$m[1]} {$fallbackTime}", self::getTimeZone());
            }
            return null;
        }
    }

    /**
     * Checks if a value signifies an unannounced, pending, or unknown milestone
     */
    public static function isUnannouncedValue(?string $val): bool {
        if ($val === null) {
            return true;
        }
        $v = strtolower(trim($val));
        if ($v === '' || $v === 'null' || $v === 'nil' || $v === 'none') {
            return true;
        }

        $patterns = [
            'tba', 'to be announced', 'to be notified', 'awaiting', 'pending', 
            'tentative', 'expected', 'likely', 'will be updated', 'not announced',
            'yet to be announced', 'soon', 'coming soon', 'not declared'
        ];

        foreach ($patterns as $p) {
            if (str_contains($v, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a verified or unannounced temporal fact into the provenance store
     *
     * @param int $articleId
     * @param string $factName
     * @param string|null $factValue
     * @param string|null $sourceUrl
     * @param array $options [source_type, valid_from, valid_until, confidence, status]
     * @return array Stored record info
     */
    public static function recordFact(
        int $articleId,
        string $factName,
        ?string $factValue,
        ?string $sourceUrl = null,
        array $options = []
    ): array {
        if (!in_array($factName, self::VALID_FACT_NAMES, true)) {
            throw new \InvalidArgumentException("Invalid temporal fact name: {$factName}");
        }

        $now = self::nowIST();
        $verifiedAt = $options['verified_at'] ?? $now->format('Y-m-d H:i:s');

        // Unknown / unannounced date rule: fact_value MUST be NULL and status CANNOT be verified
        if (self::isUnannouncedValue($factValue)) {
            $factValue = null;
            $status = 'pending';
            $confidence = 'unverified';
            $sourceType = 'official';
        }

        // Verified Fact Provenance Enforcement:
        // A critical temporal fact can ONLY be status = 'verified' if source_url is present AND authoritative.
        // Never allow verified critical date + NULL source_url!
        if ($factValue !== null) {
            if (empty($sourceUrl)) {
                $status = 'unverified';
                $confidence = 'low';
                $sourceType = 'official';
            } else {
                $authCheck = AuthorityVerificationService::verify($sourceUrl);
                if (!$authCheck['is_valid']) {
                    // Secondary wire or third-party portal cannot become authoritative for critical dates
                    $sourceType = 'secondary_wire';
                    $confidence = 'medium';
                    $status = 'unverified';
                } else {
                    $sourceType = ($authCheck['tier'] === 'tier_1a_government') ? 'official' : 'statutory_board';
                    $confidence = 'high';
                    $status = 'verified';
                }
            }
        }

        // Calculate valid_until for deadline-based facts if not provided
        $validUntil = $options['valid_until'] ?? null;
        $validFrom = $options['valid_from'] ?? null;

        if ($validUntil === null && $factValue !== null) {
            $parsedDate = self::parseDateIST($factValue);
            if ($parsedDate !== null) {
                $validUntil = $parsedDate->format('Y-m-d H:i:s');
            }
        }

        // Prevent uncontrolled duplicates / Versioning strategy
        // Check if a fact already exists for this article and fact_name
        $existing = Database::fetchOne(
            "SELECT * FROM article_temporal_facts 
             WHERE article_id = :aid AND fact_name = :fname AND status IN ('verified', 'unverified', 'pending')
             ORDER BY id DESC LIMIT 1",
            ['aid' => $articleId, 'fname' => $factName]
        );

        if ($existing) {
            // Secondary source cannot override conflicting official source
            if ($existing['source_type'] === 'official' && $sourceType === 'secondary_wire' && $existing['fact_value'] !== $factValue) {
                Logger::warning("TemporalFactService: Secondary source ({$sourceUrl}) attempted to override official fact '{$factName}' for Article #{$articleId}. Rejected.");
                return [
                    'id' => (int)$existing['id'],
                    'article_id' => $articleId,
                    'fact_name' => $factName,
                    'fact_value' => $existing['fact_value'],
                    'status' => $existing['status'],
                    'overridden' => false
                ];
            }

            // If same value and valid_until, update verified_at timestamp rather than inserting duplicate
            if ($existing['fact_value'] === $factValue && $existing['valid_until'] === $validUntil) {
                Database::update('article_temporal_facts', [
                    'verified_at' => $verifiedAt,
                    'source_url' => $sourceUrl ?: $existing['source_url'],
                    'confidence' => $confidence,
                    'status' => $status
                ], 'id = :id', ['id' => $existing['id']]);

                return [
                    'id' => (int)$existing['id'],
                    'article_id' => $articleId,
                    'fact_name' => $factName,
                    'fact_value' => $factValue,
                    'status' => $status,
                    'action' => 'updated_timestamp'
                ];
            }

            // Value has changed (e.g. extension or official announcement)
            // Supersede the existing fact record
            Database::update('article_temporal_facts', [
                'status' => 'superseded'
            ], 'id = :id', ['id' => $existing['id']]);
        }

        // Insert new verified record
        $factId = Database::insert('article_temporal_facts', [
            'article_id' => $articleId,
            'fact_name' => $factName,
            'fact_value' => $factValue,
            'source_url' => $sourceUrl,
            'source_type' => $sourceType,
            'verified_at' => $verifiedAt,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'timezone' => self::TIMEZONE,
            'confidence' => $confidence,
            'status' => $status,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s')
        ]);

        return [
            'id' => (int)$factId,
            'article_id' => $articleId,
            'fact_name' => $factName,
            'fact_value' => $factValue,
            'source_url' => $sourceUrl,
            'valid_until' => $validUntil,
            'status' => $status,
            'action' => 'inserted'
        ];
    }

    /**
     * Resolve the deterministic lifecycle state of an article based on verified facts and IST time.
     *
     * Precedence:
     * 1. HISTORICAL / EVERGREEN: Cutoffs, PYQs, Syllabus, Retired archives
     * 2. RESULT_RELEASED: Verified result publication event (NOT future announced date!)
     * 3. EXAM_COMPLETED: Exam has actually taken place (NOT future exam date!)
     * 4. ADMIT_CARD_RELEASED: Hall ticket officially available
     * 5. CLOSED: Application deadline has passed without extension
     * 6. ACTIVE: Application deadline is current/active
     * 7. UPCOMING: Application starts in the future
     *
     * @param int $articleId
     * @param array $facts Optional pre-loaded facts map [fact_name => fact_row_or_value]
     * @param string|null $categorySlug
     * @param string|null $topic
     * @param DateTimeImmutable|null $now Reference time (default: nowIST())
     * @return string One of self::LIFECYCLE_*
     */
    public static function resolveLifecycle(
        int $articleId,
        array $facts = [],
        ?string $categorySlug = null,
        ?string $topic = null,
        ?DateTimeImmutable $now = null
    ): string {
        $now = $now ?: self::nowIST();

        // 1. Check for Historical / Evergreen Topic Patterns
        if ($topic !== null) {
            $lowerTopic = strtolower($topic);
            if (
                str_contains($lowerTopic, 'previous year question') ||
                str_contains($lowerTopic, 'pyq') ||
                str_contains($lowerTopic, 'question paper pdf') ||
                str_contains($lowerTopic, 'solved papers')
            ) {
                return self::LIFECYCLE_EVERGREEN;
            }
            if (
                str_contains($lowerTopic, 'syllabus') ||
                str_contains($lowerTopic, 'exam pattern') ||
                str_contains($lowerTopic, 'marking scheme')
            ) {
                return self::LIFECYCLE_EVERGREEN;
            }
            if (
                str_contains($lowerTopic, 'cut off') ||
                str_contains($lowerTopic, 'cutoff marks') ||
                str_contains($lowerTopic, 'opening closing rank')
            ) {
                // If past year, it's historical benchmark
                if (preg_match('/\b(202[0-5])\b/', $lowerTopic)) {
                    return self::LIFECYCLE_HISTORICAL;
                }
            }
        }

        // If facts not passed, load from database
        if (empty($facts) && $articleId > 0) {
            $facts = self::getFactsMap($articleId);
        }

        // Normalize facts into structured lookup
        $factValues = [];
        $factRows = [];
        foreach ($facts as $key => $val) {
            if (is_array($val) && isset($val['fact_name'])) {
                $factValues[$val['fact_name']] = $val['fact_value'] ?? null;
                $factRows[$val['fact_name']] = $val;
            } else {
                $factValues[$key] = $val;
            }
        }

        // 2. Check RESULT_RELEASED
        // CRITICAL: A future announced result date does NOT mean RESULT_RELEASED!
        // Only if result has ACTUALLY been released:
        // Either status is marked 'released' or result_date <= now in IST
        $resultVal = $factValues['result_date'] ?? null;
        if (!empty($resultVal) && !self::isUnannouncedValue($resultVal)) {
            $resultTime = self::parseDateIST($resultVal, '00:00:00');
            $isExplicitlyReleased = false;
            if (isset($factRows['result_date'])) {
                $row = $factRows['result_date'];
                if (($row['status'] ?? '') === 'released' || str_contains(strtolower($row['fact_value'] ?? ''), 'declared') || str_contains(strtolower($row['fact_value'] ?? ''), 'released')) {
                    $isExplicitlyReleased = true;
                }
            }

            if ($resultTime !== null && $now >= $resultTime) {
                return self::LIFECYCLE_RESULT_RELEASED;
            }
            if ($isExplicitlyReleased) {
                return self::LIFECYCLE_RESULT_RELEASED;
            }
        }

        // 3. Check EXAM_COMPLETED
        // CRITICAL: A future exam date does NOT mean EXAM_COMPLETED!
        // Application closed Sept 2, exam date announced for Oct 10 -> current state before Oct 10 is CLOSED, NOT EXAM_COMPLETED!
        $examVal = $factValues['exam_date'] ?? null;
        if (!empty($examVal) && !self::isUnannouncedValue($examVal)) {
            $examTime = self::parseDateIST($examVal, '23:59:59');
            if ($examTime !== null && $now > $examTime) {
                return self::LIFECYCLE_EXAM_COMPLETED;
            }
        }

        // 4. Check ADMIT_CARD_RELEASED
        $admitVal = $factValues['admit_card_date'] ?? null;
        if (!empty($admitVal) && !self::isUnannouncedValue($admitVal)) {
            $admitTime = self::parseDateIST($admitVal, '00:00:00');
            $isAdmitReleased = false;
            if (isset($factRows['admit_card_date'])) {
                $row = $factRows['admit_card_date'];
                if (($row['status'] ?? '') === 'released' || str_contains(strtolower($row['fact_value'] ?? ''), 'released') || str_contains(strtolower($row['fact_value'] ?? ''), 'out')) {
                    $isAdmitReleased = true;
                }
            }
            if ($admitTime !== null && $now >= $admitTime) {
                return self::LIFECYCLE_ADMIT_CARD_RELEASED;
            }
            if ($isAdmitReleased) {
                return self::LIFECYCLE_ADMIT_CARD_RELEASED;
            }
        }

        // 5. Check Application Deadline (application_extension or application_end)
        $deadlineVal = $factValues['application_extension'] ?? ($factValues['application_end'] ?? null);
        $deadlineRow = $factRows['application_extension'] ?? ($factRows['application_end'] ?? null);

        if (!empty($deadlineVal) && !self::isUnannouncedValue($deadlineVal)) {
            // Check valid_until from row or parse value
            $deadlineTime = null;
            if (!empty($deadlineRow['valid_until'])) {
                try {
                    $deadlineTime = new DateTimeImmutable($deadlineRow['valid_until'], self::getTimeZone());
                } catch (Throwable $e) {}
            }

            if ($deadlineTime === null) {
                $deadlineTime = self::parseDateIST($deadlineVal, '23:59:59');
            }

            if ($deadlineTime !== null) {
                if ($now > $deadlineTime) {
                    return self::LIFECYCLE_CLOSED;
                }

                // Check application_start for UPCOMING
                $startVal = $factValues['application_start'] ?? null;
                if (!empty($startVal) && !self::isUnannouncedValue($startVal)) {
                    $startTime = self::parseDateIST($startVal, '00:00:00');
                    if ($startTime !== null && $now < $startTime) {
                        return self::LIFECYCLE_UPCOMING;
                    }
                }

                return self::LIFECYCLE_ACTIVE;
            }
        }

        // Check if application_start is in the future
        $startVal = $factValues['application_start'] ?? null;
        if (!empty($startVal) && !self::isUnannouncedValue($startVal)) {
            $startTime = self::parseDateIST($startVal, '00:00:00');
            if ($startTime !== null && $now < $startTime) {
                return self::LIFECYCLE_UPCOMING;
            }
        }

        return self::LIFECYCLE_ACTIVE;
    }

    /**
     * Fetch active facts for an article as an associative map [fact_name => row]
     */
    public static function getFactsMap(int $articleId): array {
        if ($articleId <= 0) {
            return [];
        }

        try {
            $rows = Database::fetchAll(
                "SELECT * FROM article_temporal_facts 
                 WHERE article_id = :aid AND status IN ('verified', 'unverified', 'pending')
                 ORDER BY id ASC",
                ['aid' => $articleId]
            );

            $map = [];
            foreach ($rows as $r) {
                $map[$r['fact_name']] = $r;
            }
            return $map;
        } catch (Throwable $e) {
            Logger::error("TemporalFactService getFactsMap failed for Article #{$articleId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a specific temporal fact record has expired against reference time in IST
     */
    public static function isExpired(array $fact, ?DateTimeImmutable $now = null): bool {
        if (empty($fact['valid_until'])) {
            return false;
        }

        $now = $now ?: self::nowIST();
        try {
            $validUntil = new DateTimeImmutable($fact['valid_until'], self::getTimeZone());
            return $now > $validUntil;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Find articles with expired or expiring active facts that require revalidation
     */
    public static function getArticlesNeedingRevalidation(int $limit = 20): array {
        $nowStr = self::nowIST()->format('Y-m-d H:i:s');

        try {
            // Find published articles where lifecycle is 'active' or 'upcoming'
            // but valid_until <= NOW() or null (needs check)
            $sql = "SELECT DISTINCT a.id, a.title, a.slug, a.source_url, a.lifecycle_status, f.fact_name, f.valid_until, f.fact_value
                    FROM articles a
                    JOIN article_temporal_facts f ON a.id = f.article_id
                    WHERE a.status = 'published'
                      AND a.lifecycle_status IN ('active', 'upcoming')
                      AND f.fact_name IN ('application_end', 'application_extension', 'exam_date', 'result_date')
                      AND f.status = 'verified'
                      AND f.valid_until IS NOT NULL
                      AND f.valid_until <= :now
                    ORDER BY f.valid_until ASC
                    LIMIT " . (int)$limit;

            return Database::fetchAll($sql, ['now' => $nowStr]);
        } catch (Throwable $e) {
            Logger::error("TemporalFactService getArticlesNeedingRevalidation error: " . $e->getMessage());
            return [];
        }
    }
}
