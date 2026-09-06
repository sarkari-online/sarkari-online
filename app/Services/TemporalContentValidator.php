<?php
/**
 * Sarkari.online - Semantic & Context-Aware Temporal Content Validator (9-Point Audit)
 *
 * Implements strict pre-publish, pre-update, and regression auditing to eliminate
 * relative temporal misuse, ensure absolute dates, enforce CTA consistency,
 * protect unannounced milestones, and verify lifecycle alignment strictly in Asia/Kolkata.
 */

namespace App\Services;

use App\Helpers\Logger;
use App\Services\TemporalFactService;
use App\Services\AuthorityVerificationService;
use DateTimeImmutable;
use Throwable;

class TemporalContentValidator {

    /**
     * Forbidden transient relative phrases when presented as current static article facts
     */
    private const FORBIDDEN_RELATIVE_PATTERNS = [
        '/\blast date today\b/i',
        '/\bclosing today\b/i',
        '/\bcloses today\b/i',
        '/\bended today\b/i',
        '/\bends today\b/i',
        '/\bapply today\b/i',
        '/\bapply tonight\b/i',
        '/\bexam tomorrow\b/i',
        '/\bresult today\b/i',
        '/\badmit card released today\b/i',
        '/\bhours left to apply\b/i',
        '/\bfew hours left\b/i',
        '/\bdeadline is today\b/i',
        '/\bdeadline is tomorrow\b/i',
        '/\bapplication ends tonight\b/i',
        '/\btoday is the last date\b/i',
        '/\btoday is the final date\b/i',
        '/\btomorrow is the last date\b/i',
        '/\byesterday was the last date\b/i',
        '/\bdeadline expires today\b/i',
        '/\bportal closes tonight\b/i',
        '/\bclosing tonight\b/i'
    ];

    /**
     * Active Application Call-To-Action (CTA) phrases forbidden when lifecycle_status is CLOSED
     */
    private const ACTIVE_CTAS = [
        'apply online',
        'apply now',
        'registration open',
        'register now',
        'submit application',
        'submit online application form',
        'submit application form',
        'fill application form',
        'click here to apply',
        'online application link active',
        'direct apply link',
        'application window open'
    ];

    /**
     * Speculative / Hallucinated milestone phrases forbidden when milestone date is NULL/unannounced
     */
    private const SPECULATIVE_UNANNOUNCED_PATTERNS = [
        '/candidates are (?:now )?waiting for (?:the )?CBT exam/i',
        '/exam will be held in (?:january|february|march|april|may|june|july|august|september|october|november|december)/i',
        '/expected to be conducted on/i',
        '/expected exam date is/i',
        '/tentatively scheduled on \w+ \d+/i',
        '/likely to be released on \w+ \d+/i'
    ];

    /**
     * Validate article data against all 9 temporal integrity checks
     *
     * @param array $articleData [title, excerpt, content, meta_title, meta_description]
     * @param array $temporalFacts Structured temporal facts map [fact_name => row_or_value]
     * @param string $lifecycleStatus Resolved lifecycle status
     * @param DateTimeImmutable|null $now Reference time in Asia/Kolkata
     * @return array ['pass' => bool, 'violations' => array, 'warnings' => array]
     */
    public static function validate(
        array $articleData,
        array $temporalFacts = [],
        string $lifecycleStatus = 'active',
        ?DateTimeImmutable $now = null
    ): array {
        $now = $now ?: TemporalFactService::nowIST();
        $violations = [];
        $warnings = [];

        $title = $articleData['title'] ?? '';
        $content = $articleData['content'] ?? '';
        $excerpt = $articleData['excerpt'] ?? '';
        $metaTitle = $articleData['meta_title'] ?? $title;
        $metaDesc = $articleData['meta_description'] ?? $excerpt;

        $combinedText = "{$title}\n{$excerpt}\n{$metaTitle}\n{$metaDesc}\n" . strip_tags($content);

        // Normalize facts into structured format
        $factsMap = [];
        foreach ($temporalFacts as $k => $v) {
            if (is_array($v)) {
                $fName = $v['fact_name'] ?? $k;
                $fVal = $v['fact_value'] ?? ($v['value'] ?? ($v['date'] ?? null));
                $fSrc = $v['source_url'] ?? ($articleData['source_url'] ?? null);
                $fUntil = $v['valid_until'] ?? null;
                $factsMap[$fName] = [
                    'fact_name' => $fName,
                    'fact_value' => is_string($fVal) ? $fVal : null,
                    'source_url' => $fSrc,
                    'valid_until' => $fUntil,
                    'status' => $v['status'] ?? null
                ];
            } else {
                $factsMap[$k] = [
                    'fact_name' => $k,
                    'fact_value' => is_string($v) ? $v : null,
                    'source_url' => $articleData['source_url'] ?? null
                ];
            }
        }

        // =========================================================================
        // CHECK A: Relative Temporal Misuse (Rule 8: Do NOT globally ban "Last Date")
        // =========================================================================
        foreach (self::FORBIDDEN_RELATIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $combinedText, $matches)) {
                $violations[] = [
                    'check' => 'CHECK_A_RELATIVE_TEMPORAL_MISUSE',
                    'message' => "Prohibited transient relative expression found: '{$matches[0]}'. Static articles must use absolute dates (e.g. 'Last Date: September 2, 2026').",
                    'match' => $matches[0]
                ];
            }
        }

        // Check if "Today" is used in title or excerpt in an urgent context
        if (preg_match('/\b(today|tonight|tomorrow)\b/i', $title, $m)) {
            $violations[] = [
                'check' => 'CHECK_A_RELATIVE_TITLE',
                'message' => "Article title contains transient relative time word '{$m[0]}'. Permanent static titles must not use relative dates.",
                'match' => $m[0]
            ];
        }

        // =========================================================================
        // CHECK B: Absolute Date Presence for Critical Temporal Claims
        // =========================================================================
        // If article mentions "last date" or "deadline", it must be paired with an absolute date format
        // Valid: "Last Date: September 2, 2026", "Application closed on September 2, 2026", "Deadline: 02/09/2026"
        if (preg_match('/\b(?:last date|application deadline|closing date)\b/i', $combinedText)) {
            $hasAbsoluteDate = preg_match('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i', $combinedText) ||
                               preg_match('/\b\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b/i', $combinedText) ||
                               preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}\b/', $combinedText);

            if (!$hasAbsoluteDate) {
                $warnings[] = [
                    'check' => 'CHECK_B_ABSOLUTE_DATE_MISSING',
                    'message' => "Article mentions a deadline or last date but lacks a verified absolute calendar date format (e.g. 'September 2, 2026')."
                ];
            }
        }

        // =========================================================================
        // CHECK C: Source Provenance (Enforce: Verified Critical Date Requires Authoritative URL)
        // =========================================================================
        foreach (['application_end', 'exam_date', 'result_date'] as $criticalFactName) {
            if (isset($factsMap[$criticalFactName])) {
                $fact = $factsMap[$criticalFactName];
                $factVal = $fact['fact_value'] ?? null;
                if (!empty($factVal) && !TemporalFactService::isUnannouncedValue($factVal)) {
                    $sourceUrl = $fact['source_url'] ?? ($articleData['source_url'] ?? '');
                    if (empty($sourceUrl)) {
                        if (($fact['status'] ?? '') === 'verified') {
                            $violations[] = [
                                'check' => 'CHECK_C_VERIFIED_MISSING_SOURCE_PROVENANCE',
                                'message' => "Critical temporal fact '{$criticalFactName}' has verified date '{$factVal}' but missing source URL. A verified critical fact MUST have an authoritative source."
                            ];
                        } else {
                            $warnings[] = [
                                'check' => 'CHECK_C_SOURCE_PROVENANCE_MISSING',
                                'message' => "Critical temporal fact '{$criticalFactName}' has value '{$factVal}' but missing authoritative source URL."
                            ];
                        }
                    } else {
                        $auth = AuthorityVerificationService::verify($sourceUrl);
                        if (!$auth['is_valid'] && ($fact['status'] ?? '') === 'verified') {
                            $violations[] = [
                                'check' => 'CHECK_C_INVALID_AUTHORITY',
                                'message' => "Critical temporal fact '{$criticalFactName}' is marked verified but URL '{$sourceUrl}' is not a recognized statutory authority."
                            ];
                        }
                    }
                }
            }
        }

        // =========================================================================
        // CHECK D & E: Lifecycle & Deadline Consistency
        // =========================================================================
        $deadlineFact = $factsMap['application_extension'] ?? ($factsMap['application_end'] ?? null);
        if ($deadlineFact && !empty($deadlineFact['fact_value']) && !TemporalFactService::isUnannouncedValue($deadlineFact['fact_value'])) {
            $deadlineTime = null;
            if (!empty($deadlineFact['valid_until'])) {
                try {
                    $deadlineTime = new DateTimeImmutable($deadlineFact['valid_until'], TemporalFactService::getTimeZone());
                } catch (Throwable $e) {}
            }
            if ($deadlineTime === null) {
                $deadlineTime = TemporalFactService::parseDateIST($deadlineFact['fact_value'], '23:59:59');
            }

            if ($deadlineTime !== null) {
                // If deadline has passed in Asia/Kolkata
                if ($now > $deadlineTime) {
                    // Lifecycle MUST NOT be 'active' or 'upcoming'
                    if (in_array($lifecycleStatus, ['active', 'upcoming'], true)) {
                        $violations[] = [
                            'check' => 'CHECK_D_LIFECYCLE_DEADLINE_MISMATCH',
                            'message' => "Application deadline ({$deadlineTime->format('Y-m-d H:i:s')} IST) has passed, but lifecycle_status is '{$lifecycleStatus}'. It must transition to 'closed'."
                        ];
                    }

                    // Content must not claim registration is currently open/active
                    if (preg_match('/\b(registration is (?:now )?open|applications are being accepted|apply online now|window is live|application process is (?:still )?active|application window is (?:still )?active|applications are active|application is active)\b/i', $combinedText, $m) ||
                        preg_match('/<span[^>]*class=["\'][^"\']*status-pill[^"\']*["\'][^>]*>\s*Active\s*<\/span>/i', $content, $m)) {
                        $violations[] = [
                            'check' => 'CHECK_E_DEADLINE_PASSED_ACTIVE_COPY',
                            'message' => "Application deadline has passed, but content contains active statement: '{$m[0]}'.",
                            'match' => $m[0]
                        ];
                    }
                }
            }
        }

        // =========================================================================
        // CHECK F: CTA Consistency (Closed Articles MUST NOT have Active CTAs)
        // =========================================================================
        if ($lifecycleStatus === TemporalFactService::LIFECYCLE_CLOSED) {
            foreach (self::ACTIVE_CTAS as $cta) {
                // Check in title, excerpt, and content
                if (preg_match('/\b' . preg_quote($cta, '/') . '\b/i', $title, $m)) {
                    $violations[] = [
                        'check' => 'CHECK_F_CLOSED_TITLE_ACTIVE_CTA',
                        'message' => "Article lifecycle is CLOSED but title contains active CTA: '{$m[0]}'.",
                        'match' => $m[0]
                    ];
                }
                if (preg_match('/\b' . preg_quote($cta, '/') . '\b/i', $content, $m)) {
                    $violations[] = [
                        'check' => 'CHECK_F_CLOSED_CONTENT_ACTIVE_CTA',
                        'message' => "Article lifecycle is CLOSED but content body contains active CTA: '{$m[0]}'. Must use 'Application Closed'.",
                        'match' => $m[0]
                    ];
                }
            }

            // Check for active status pills in closed articles
            if (preg_match('/<span[^>]*class=["\'][^"\']*status-pill[^"\']*["\'][^>]*>\s*Active\s*<\/span>/i', $content, $m)) {
                $violations[] = [
                    'check' => 'CHECK_F_CLOSED_CONTENT_ACTIVE_PILL',
                    'message' => "Article lifecycle is CLOSED but content body contains active status pill: '{$m[0]}'.",
                    'match' => $m[0]
                ];
            }

            // Check for active application claims in closed articles
            if (preg_match('/\b(registration is (?:now )?open|applications are being accepted|apply online now|window is live|application process is (?:still )?active|application window is (?:still )?active|applications are active|application is active)\b/i', $combinedText, $m)) {
                $violations[] = [
                    'check' => 'CHECK_F_CLOSED_CONTENT_ACTIVE_CLAIM',
                    'message' => "Article lifecycle is CLOSED but content claims application is active: '{$m[0]}'.",
                    'match' => $m[0]
                ];
            }
        }

        // =========================================================================
        // CHECK G: Unannounced Milestone Protection
        // =========================================================================
        // If official source has exam_date = NULL, content must not invent/speculate
        $examFact = $factsMap['exam_date'] ?? null;
        $isExamUnannounced = empty($examFact) || empty($examFact['fact_value']) || TemporalFactService::isUnannouncedValue($examFact['fact_value']);

        if ($isExamUnannounced) {
            foreach (self::SPECULATIVE_UNANNOUNCED_PATTERNS as $pattern) {
                if (preg_match($pattern, $combinedText, $m)) {
                    $violations[] = [
                        'check' => 'CHECK_G_UNANNOUNCED_MILESTONE_SPECULATION',
                        'message' => "Official exam date is unannounced, but content contains speculative/hallucinated statement: '{$m[0]}'. Allowed: 'The exam date has not yet been officially announced.'",
                        'match' => $m[0]
                    ];
                }
            }
        }

        // =========================================================================
        // CHECK H: Title / SEO Consistency
        // =========================================================================
        if ($lifecycleStatus === TemporalFactService::LIFECYCLE_CLOSED) {
            if (preg_match('/\b(apply online|apply now|last date)\b/i', $metaTitle, $m) && !str_contains(strtolower($metaTitle), 'closed')) {
                $violations[] = [
                    'check' => 'CHECK_H_SEO_TITLE_INCONSISTENCY',
                    'message' => "SEO title contains '{$m[0]}' for a CLOSED article without indicating closed status.",
                    'match' => $m[0]
                ];
            }
        }

        // =========================================================================
        // CHECK I: Static Copy Temporal Consistency
        // =========================================================================
        // Ensure no internal conflict where intro claims open/closed opposite of the table
        if ($lifecycleStatus === TemporalFactService::LIFECYCLE_CLOSED) {
            if (preg_match('/<table\b[^>]*>.*?<\/table>/is', $content, $tableMatch)) {
                $tableText = strip_tags($tableMatch[0]);
                // If table mentions closed, but body intro mentions open
                if (str_contains(strtolower($title), 'apply online') && !str_contains(strtolower($title), 'closed')) {
                    $violations[] = [
                        'check' => 'CHECK_I_INTERNAL_TEMPORAL_CONFLICT',
                        'message' => "Internal conflict: Title prompts user to apply online while lifecycle is CLOSED."
                    ];
                }
            }
        }

        $pass = empty($violations);

        return [
            'pass' => $pass,
            'violations' => $violations,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate and apply deterministic auto-repairs for presentation inconsistencies
     * Note: Critical factual discrepancies (missing official facts) are NOT repaired into invented dates.
     *
     * @param array $articleData [title, excerpt, content, meta_title, meta_description]
     * @param array $temporalFacts
     * @param string $lifecycleStatus
     * @param DateTimeImmutable|null $now
     * @return array ['pass' => bool, 'repaired_data' => array, 'repairs_applied' => array, 'unresolved_violations' => array]
     */
    public static function validateAndRepair(
        array $articleData,
        array $temporalFacts = [],
        string $lifecycleStatus = 'active',
        ?DateTimeImmutable $now = null
    ): array {
        $now = $now ?: TemporalFactService::nowIST();
        $repairs = [];
        $repaired = $articleData;

        // 1. First run standard validation
        $initialAudit = self::validate($repaired, $temporalFacts, $lifecycleStatus, $now);
        if ($initialAudit['pass']) {
            return [
                'pass' => true,
                'repaired_data' => $repaired,
                'repairs_applied' => [],
                'unresolved_violations' => []
            ];
        }

        $deadlineFact = $temporalFacts['application_extension'] ?? ($temporalFacts['application_end'] ?? null);
        $absoluteDeadline = is_array($deadlineFact) ? ($deadlineFact['fact_value'] ?? '') : ($deadlineFact ?? '');

        // 2. Deterministic Repair for Relative Words in Titles/Content
        foreach (self::FORBIDDEN_RELATIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $repaired['title'])) {
                if (!empty($absoluteDeadline)) {
                    $repaired['title'] = preg_replace($pattern, "Last Date: {$absoluteDeadline}", $repaired['title']);
                } else {
                    $repaired['title'] = preg_replace($pattern, "Schedule & Deadline", $repaired['title']);
                }
                $repairs[] = "Repaired relative urgency in title";
            }

            if (preg_match($pattern, $repaired['excerpt'] ?? '')) {
                if (!empty($absoluteDeadline)) {
                    $repaired['excerpt'] = preg_replace($pattern, "concluded on {$absoluteDeadline}", $repaired['excerpt']);
                } else {
                    $repaired['excerpt'] = preg_replace($pattern, "deadline", $repaired['excerpt']);
                }
                $repairs[] = "Repaired relative urgency in excerpt";
            }

            if (preg_match($pattern, $repaired['content'] ?? '')) {
                if (!empty($absoluteDeadline)) {
                    $repaired['content'] = preg_replace($pattern, "concluded on {$absoluteDeadline}", $repaired['content']);
                } else {
                    $repaired['content'] = preg_replace($pattern, "official deadline", $repaired['content']);
                }
                $repairs[] = "Repaired relative urgency in content";
            }
        }

        // 3. Deterministic Repair for CLOSED Lifecycle (Strip Active CTAs & adjust Title)
        if ($lifecycleStatus === TemporalFactService::LIFECYCLE_CLOSED) {
            // Repair Title: Replace "Apply Online" or "Last Date Today" with "Application Closed: Next Stage & Updates"
            $oldTitle = $repaired['title'];
            $repaired['title'] = preg_replace('/:\s*Last Date Today.*$/i', ': Application Closed — Next Stage & Updates', $repaired['title']);
            $repaired['title'] = preg_replace('/\bApply Online\b/i', 'Application Closed', $repaired['title']);
            $repaired['title'] = preg_replace('/\bApply Now\b/i', 'Application Closed', $repaired['title']);
            if ($oldTitle !== $repaired['title']) {
                $repairs[] = "Updated title to reflect CLOSED lifecycle";
            }

            // Repair Content CTAs
            $content = $repaired['content'];
            $content = preg_replace('/>\s*(?:Apply Online|Apply Now|Click Here to Apply)\s*<\/a>/i', '>Application Closed (Portal Archive)</a>', $content);
            $content = preg_replace('/\b(?:Registration is open|Applications are being accepted)\b/i', "Application window closed on {$absoluteDeadline}", $content);
            $content = preg_replace('/\b(?:Submit Online Application Form|Submit Application Form)\b/i', 'Check Official Portal for Next Stage Updates', $content);
            $content = preg_replace('/<span([^>]*)class=(["\'][^"\']*status-pill[^"\']*["\'])([^>]*)>\s*Active\s*<\/span>/i', '<span$1class=$2 style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-weight:600;"$3>Closed</span>', $content);
            $content = preg_replace('/(Is the application window[^?]*\?[^<]*(?:<\/strong>)?\s*<br>\s*A:\s*)Yes, as of [^,]+, the application process is (?:still )?active\./i', '$1No, the application process for this recruitment has concluded.', $content);
            $content = preg_replace('/(Is the application window[^?]*\?\s*<br>\s*A:\s*)Yes, as of [^,]+, the application process is (?:still )?active\./i', '$1No, the application process for this recruitment has concluded.', $content);
            $content = preg_replace('/\b(?:Yes, )?as of [^,]+, the application process is (?:still )?active\.?/i', 'the application process has concluded.', $content);
            $content = preg_replace('/\b(?:the )?application process is (?:still )?active\b/i', 'the application process has concluded', $content);
            $content = preg_replace('/\bapplication window is (?:still )?active\b/i', 'application window has closed', $content);
            $content = preg_replace('/\bapplications are active\b/i', 'applications are closed', $content);
            $content = preg_replace('/\bapplication is active\b/i', 'application is closed', $content);
            if ($content !== $repaired['content']) {
                $repaired['content'] = $content;
                $repairs[] = "Repaired active CTA links, table pills, and FAQ in content to 'Application Closed'";
            }

            // Repair SEO Meta Title / Description
            if (!empty($repaired['meta_title'])) {
                $repaired['meta_title'] = preg_replace('/\bApply Online\b/i', 'Application Closed', $repaired['meta_title']);
                $repaired['meta_title'] = preg_replace('/\bApply Now\b/i', 'Application Closed', $repaired['meta_title']);
            }
        }

        // 4. Deterministic Repair for Speculative Unannounced Milestone statements
        $examFact = $temporalFacts['exam_date'] ?? null;
        $isExamUnannounced = empty($examFact) || empty($examFact['fact_value']) || TemporalFactService::isUnannouncedValue($examFact['fact_value']);

        if ($isExamUnannounced) {
            $safeUnannouncedStatement = "The exam date has not yet been officially announced by the commission.";
            foreach (self::SPECULATIVE_UNANNOUNCED_PATTERNS as $pattern) {
                if (preg_match($pattern, $repaired['content'])) {
                    $repaired['content'] = preg_replace($pattern, $safeUnannouncedStatement, $repaired['content']);
                    $repairs[] = "Replaced speculative unannounced milestone with standard unannounced declaration";
                }
            }
        }

        // 5. Re-run validation on repaired content
        $finalAudit = self::validate($repaired, $temporalFacts, $lifecycleStatus, $now);

        return [
            'pass' => $finalAudit['pass'],
            'repaired_data' => $repaired,
            'repairs_applied' => $repairs,
            'unresolved_violations' => $finalAudit['violations']
        ];
    }
}
