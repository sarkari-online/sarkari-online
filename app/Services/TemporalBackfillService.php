<?php
/**
 * Sarkari.online - Safe Temporal Backfill & Initial Classification Engine
 *
 * Deterministically classifies existing articles into safe lifecycle states
 * without regenerating content. For articles where temporal facts cannot be verified,
 * it DOES NOT assume ACTIVE; it safely assigns DRAFT / REVIEW.
 * Supports complete DRY-RUN mode.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Services\TemporalFactService;
use App\Services\AuthorityVerificationService;
use DateTimeImmutable;
use Throwable;

class TemporalBackfillService {

    /**
     * Inspect and deterministically classify a single article
     *
     * @param array $article
     * @param DateTimeImmutable|null $now
     * @return array [lifecycle_status, facts, confidence, reason, needs_review]
     */
    public static function classifyArticle(array $article, ?DateTimeImmutable $now = null): array {
        $now = $now ?: TemporalFactService::nowIST();
        $title = $article['title'] ?? '';
        $content = $article['content'] ?? '';
        $sourceUrl = $article['source_url'] ?? '';
        $categorySlug = $article['category_slug'] ?? '';
        $lowerTitle = strtolower($title);
        $plainContent = strip_tags($content);

        // 1. Check Evergreen Preparation Materials
        if (
            str_contains($lowerTitle, 'previous year question') ||
            str_contains($lowerTitle, 'pyq') ||
            str_contains($lowerTitle, 'question paper pdf') ||
            str_contains($lowerTitle, 'solved paper') ||
            str_contains($lowerTitle, 'syllabus') ||
            str_contains($lowerTitle, 'exam pattern') ||
            str_contains($lowerTitle, 'marking scheme') ||
            $categorySlug === 'career-guides' ||
            $categorySlug === 'student-technology'
        ) {
            return [
                'lifecycle_status' => TemporalFactService::LIFECYCLE_EVERGREEN,
                'facts' => [],
                'confidence' => 'high',
                'reason' => 'Evergreen preparation material / syllabus / PYQ',
                'needs_review' => false
            ];
        }

        // 2. Check Historical Benchmarks (Past Year Cycles)
        if (preg_match('/\b(202[0-5])\b/', $lowerTitle, $ym)) {
            if (
                str_contains($lowerTitle, 'cut off') ||
                str_contains($lowerTitle, 'cutoff') ||
                str_contains($lowerTitle, 'result') ||
                str_contains($lowerTitle, 'merit list') ||
                str_contains($lowerTitle, 'scorecard')
            ) {
                return [
                    'lifecycle_status' => TemporalFactService::LIFECYCLE_HISTORICAL,
                    'facts' => [],
                    'confidence' => 'high',
                    'reason' => "Historical benchmark from {$ym[1]} cycle",
                    'needs_review' => false
                ];
            }
        }

        // 3. Extract Application Deadline from Content & HTML Tables
        $deadlineStr = null;
        if (preg_match('/(?:last date|deadline|closing date|application window closes)\s*(?:is|on|:)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $title . ' ' . $plainContent, $m)) {
            $deadlineStr = $m[1];
        }

        // Also check <td>Last Date</td><td>September 02, 2026</td>
        if (!$deadlineStr && preg_match('/<tr\b[^>]*>.*?<td\b[^>]*>.*?(?:last date|closing date|registration ends).*?<\/td>.*?<td\b[^>]*>(.*?)<\/td>.*?<\/tr>/is', $content, $trMatch)) {
            $candidateDate = trim(strip_tags($trMatch[1]));
            if (!TemporalFactService::isUnannouncedValue($candidateDate)) {
                $deadlineStr = $candidateDate;
            }
        }

        // 4. If Deadline Found: Check against now in Asia/Kolkata
        if (!empty($deadlineStr)) {
            $deadlineTime = TemporalFactService::parseDateIST($deadlineStr, '23:59:59');
            if ($deadlineTime !== null) {
                if ($now > $deadlineTime) {
                    // Deadline has passed -> Strictly CLOSED
                    return [
                        'lifecycle_status' => TemporalFactService::LIFECYCLE_CLOSED,
                        'facts' => [
                            'application_end' => [
                                'fact_name' => 'application_end',
                                'fact_value' => $deadlineStr,
                                'valid_until' => $deadlineTime->format('Y-m-d H:i:s'),
                                'source_url' => $sourceUrl
                            ]
                        ],
                        'confidence' => 'high',
                        'reason' => "Application deadline ({$deadlineTime->format('F d, Y')} IST) has passed",
                        'needs_review' => false
                    ];
                } else {
                    // Deadline is in the future:
                    // Check if source_url is authoritative
                    $isAuth = false;
                    if (!empty($sourceUrl)) {
                        $auth = AuthorityVerificationService::verify($sourceUrl);
                        $isAuth = $auth['is_valid'];
                    }

                    if ($isAuth) {
                        return [
                            'lifecycle_status' => TemporalFactService::LIFECYCLE_ACTIVE,
                            'facts' => [
                                'application_end' => [
                                    'fact_name' => 'application_end',
                                    'fact_value' => $deadlineStr,
                                    'valid_until' => $deadlineTime->format('Y-m-d H:i:s'),
                                    'source_url' => $sourceUrl
                                ]
                            ],
                            'confidence' => 'high',
                            'reason' => "Verified active deadline ({$deadlineTime->format('F d, Y')} IST) with authoritative source",
                            'needs_review' => false
                        ];
                    } else {
                        // Future deadline but source is unverified -> DO NOT ASSUME ACTIVE!
                        return [
                            'lifecycle_status' => TemporalFactService::LIFECYCLE_DRAFT,
                            'facts' => [
                                'application_end' => [
                                    'fact_name' => 'application_end',
                                    'fact_value' => $deadlineStr,
                                    'valid_until' => $deadlineTime->format('Y-m-d H:i:s'),
                                    'source_url' => $sourceUrl
                                ]
                            ],
                            'confidence' => 'low',
                            'reason' => "Future deadline detected ({$deadlineStr}) but source URL is missing or unverified; safest state DRAFT assigned",
                            'needs_review' => true
                        ];
                    }
                }
            }
        }

        // 5. Unverifiable Temporal Facts -> Safest Appropriate State is DRAFT (Flagged for Review)
        // Strictly DO NOT assume ACTIVE!
        return [
            'lifecycle_status' => TemporalFactService::LIFECYCLE_DRAFT,
            'facts' => [],
            'confidence' => 'unverified',
            'reason' => 'No verified deadline found in content or official source; safest state DRAFT assigned',
            'needs_review' => true
        ];
    }

    /**
     * Run classification across all published articles with dryRun support
     *
     * @param int $limit Max articles to classify
     * @param bool $dryRun If true, does NOT write to database
     * @return array
     */
    public static function processArticles(int $limit = 100, bool $dryRun = true): array {
        $now = TemporalFactService::nowIST();
        $articles = Database::fetchAll(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.source_url, a.lifecycle_status, c.slug AS category_slug 
             FROM articles a 
             LEFT JOIN categories c ON a.category_id = c.id 
             WHERE a.status = 'published' 
             ORDER BY a.id ASC LIMIT " . (int)$limit
        );

        $summary = [
            'total_inspected' => count($articles),
            'dry_run' => $dryRun,
            'counts' => [
                'closed' => 0,
                'active' => 0,
                'evergreen' => 0,
                'historical' => 0,
                'draft' => 0,
                'needs_review' => 0
            ],
            'items' => []
        ];

        foreach ($articles as $art) {
            $classification = self::classifyArticle($art, $now);
            $targetState = $classification['lifecycle_status'];

            $summary['counts'][$targetState] = ($summary['counts'][$targetState] ?? 0) + 1;
            if ($classification['needs_review']) {
                $summary['counts']['needs_review']++;
            }

            $itemRecord = [
                'id' => (int)$art['id'],
                'title' => $art['title'],
                'target_state' => $targetState,
                'reason' => $classification['reason'],
                'confidence' => $classification['confidence'],
                'needs_review' => $classification['needs_review']
            ];

            // If not dry-run, persist classification and facts
            if (!$dryRun) {
                Database::update('articles', [
                    'lifecycle_status' => $targetState
                ], 'id = :id', ['id' => $art['id']]);

                foreach ($classification['facts'] as $fname => $finfo) {
                    TemporalFactService::recordFact(
                        (int)$art['id'],
                        $fname,
                        $finfo['fact_value'] ?? null,
                        $finfo['source_url'] ?? null,
                        ['valid_until' => $finfo['valid_until'] ?? null]
                    );
                }
            }

            $summary['items'][] = $itemRecord;
        }

        return $summary;
    }
}
