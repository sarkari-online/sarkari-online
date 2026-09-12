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
use App\Services\SEOManagerService;
use App\Services\ArticleUpdateService;
use App\Services\TemporalFactService;
use App\Services\TemporalContentValidator;
use App\Services\TitleBodyConfidenceDetector;
use App\Services\GlossaryService;
use App\Services\FactCompletenessRules;
use App\Services\HallucinationGuard;
use App\Services\IntentClassifierService;
use App\Services\ArticleIntent;
use App\Services\ExamCycleResolverService;
use App\Services\PhaseTransitionCheck;
use App\Services\TableIntegrityGate;
use App\Services\GoogleIndexingService;
use App\Services\IndexNowService;
use App\Services\TelegraphSyndicationService;
use App\Services\GithubSyndicationService;
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
        $matchingArticle = TrendService::findMatchingArticle($trend['keyword']);
        if (!$force && $matchingArticle) {
            $rawPayload = !empty($trend['raw_payload']) ? (is_array($trend['raw_payload']) ? $trend['raw_payload'] : (json_decode($trend['raw_payload'], true) ?: [])) : [];
            TrendService::markStatus($trendId, 'published', [
                'processed_at' => date('Y-m-d H:i:s'),
                'raw_payload' => array_merge($rawPayload, [
                    'reason' => "Topic already covered in published Article #{$matchingArticle['id']} ('{$matchingArticle['title']}'). Preserved vetted content without rewrite."
                ])
            ]);
            Logger::info("Trend #{$trendId} marked processed: topic already covered in Article #{$matchingArticle['id']} ('{$matchingArticle['title']}'). Preserved vetted content.");
            return [
                'success' => true,
                'article_id' => (int)$matchingArticle['id'],
                'trend_id' => $trendId,
                'title' => $matchingArticle['title'],
                'status' => 'published',
                'updated' => false
            ];
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

        // ── Step 3A.5: ExamCycleContext ───────────────────────────────────────
        // Resolve (or create) the exam_cycle row for this keyword, then run
        // PhaseTransitionCheck to detect the real current phase via Grounded Gemini.
        // The detected phase flows into $verifiedFacts so downstream steps
        // (AuthorityFactFetcherService prompt, ArticleGenerator) are phase-aware.
        $examCycle = null;
        $examPhase = 'ANNUAL_CALENDAR_ONLY';
        try {
            $cycleResolver = new ExamCycleResolverService();
            $examCycle     = $cycleResolver->resolve($trend['keyword']);
            if ($examCycle !== null) {
                $needsCheck = (
                    empty($examCycle['last_verified_at']) ||
                    strtotime($examCycle['last_verified_at']) < time() - 6 * 3600 ||
                    ($examCycle['phase_confidence'] ?? '') === 'STALE'
                );
                if ($needsCheck) {
                    $phaseChecker = new PhaseTransitionCheck();
                    $examCycle    = $phaseChecker->check($examCycle);
                }
                $examPhase = $examCycle['current_phase'] ?? 'ANNUAL_CALENDAR_ONLY';
                // Inject phase context into verifiedFacts so ArticleGenerator is phase-aware
                $verifiedFacts['_exam_cycle_id']    = (int)$examCycle['id'];
                $verifiedFacts['_exam_phase']        = $examPhase;
                $verifiedFacts['_exam_facts_json']   = $examCycle['facts_json'] ?? null;
                $verifiedFacts['_phase_evidence_url'] = $examCycle['phase_evidence_url'] ?? null;
                Logger::info("PipelineService: ExamCycleContext resolved — cycle #{$examCycle['id']}, phase={$examPhase}");
            }
        } catch (Throwable $cycleEx) {
            Logger::warning("PipelineService: ExamCycleContext failed (non-blocking): " . $cycleEx->getMessage());
        }
        // ── End Step 3A.5 ─────────────────────────────────────────────────────


        // Safety Gate: Newly discovered or unverified authorities are unconditionally held for human review (bypassed if admin clicked Publish Now)
        if (!$force && ($resolvedAuth['verification_status'] ?? 'verified') !== 'verified') {
            $reason = "Authority '{$resolvedAuth['name']}' (" . ($resolvedAuth['portal'] ?: 'No portal') . ") is pending human verification. Auto-publish held.";
            TrendService::markStatus($trendId, 'needs_enrichment', [
                'raw_payload' => array_merge($rawPayload, [
                    'enrichment_reason' => $reason,
                    'first_attempted_at' => date('Y-m-d H:i:s'),
                    'retry_count' => 0
                ])
            ]);
            Logger::warning("PipelineService: Trend #{$trendId} held: {$reason}");
            return ['success' => false, 'trend_id' => $trendId, 'status' => 'needs_enrichment', 'error' => $reason];
        }

        $authorityName = (!empty($verifiedFacts['authority_name']) && !str_contains(strtolower($verifiedFacts['authority_name']), 'statutory examination board'))
            ? $verifiedFacts['authority_name']
            : $resolvedAuth['name'];
        $officialPortal = (!empty($verifiedFacts['official_portal']) && !str_contains($verifiedFacts['official_portal'], 'sarkari.online'))
            ? $verifiedFacts['official_portal']
            : $resolvedAuth['portal'];

        // Pre-Generation Fact Completeness Gate
        $intentClassifier = new IntentClassifierService();
        $detectedIntent = $intentClassifier->classify($trend['keyword'], $snippet);
        $completeness = FactCompletenessRules::evaluate($detectedIntent, $verifiedFacts);

        if (!$completeness->isComplete) {
            $missingList = implode(', ', $completeness->missingFacts);
            Logger::info("PipelineService: Trend #{$trendId} missing mandatory facts for {$detectedIntent->value}: {$missingList}. Attempting targeted query...");
            $targetedQuery = "{$authorityName} {$trend['keyword']} notification last date application fee";
            $retryFacts = $factFetcher->fetchFactsForTopic($targetedQuery, $categorySlug, $officialPortal, $snippet);
            $mergedFacts = array_merge($verifiedFacts, $retryFacts);
            $completeness = FactCompletenessRules::evaluate($detectedIntent, $mergedFacts);
            if ($completeness->isComplete) {
                $verifiedFacts = $mergedFacts;
            }
        }

        if (!$completeness->isComplete && !$force) {
            $missingList = implode(', ', $completeness->missingFacts);
            $reason = "Missing mandatory statutory facts for {$detectedIntent->value}: {$missingList}";
            TrendService::markStatus($trendId, 'needs_enrichment', [
                'raw_payload' => array_merge($rawPayload, [
                    'enrichment_reason' => $reason,
                    'missing_facts' => $completeness->missingFacts,
                    'first_attempted_at' => date('Y-m-d H:i:s'),
                    'retry_count' => 0
                ])
            ]);
            Logger::warning("PipelineService: Trend #{$trendId} held in needs_enrichment: {$reason}");
            return ['success' => false, 'trend_id' => $trendId, 'status' => 'needs_enrichment', 'error' => $reason];
        } elseif (!$completeness->isComplete && $force) {
            Logger::info("PipelineService: Admin force-publish triggered — proceeding with verified available facts for '{$trend['keyword']}'");
        }

        $sourceData = [
            'keyword' => $trend['keyword'],
            'source_name' => $authorityName,
            'source_url' => $officialPortal,
            'reference' => $verifiedFacts['official_notice_ref'] ?? ($rawPayload['source_attribution']['reference'] ?? ''),
            'notes' => $rawPayload['reasoning'] ?? $trend['keyword'],
            'snippet' => $rawPayload['snippet'] ?? ($trend['category_hint'] ?? ''),
            'verified_facts' => $verifiedFacts,
            'force' => $force
        ];

        // Temporal Grounding: Extract Structured Temporal Facts & Resolve Deterministic Lifecycle strictly in Asia/Kolkata
        $extractedFacts = $this->extractTemporalFacts($verifiedFacts, $trend['keyword'], $officialPortal);
        $resolvedLifecycle = TemporalFactService::resolveLifecycle(0, $extractedFacts, $categorySlug, $trend['keyword']);
        $sourceData['resolved_lifecycle'] = $resolvedLifecycle;
        $sourceData['temporal_facts'] = $extractedFacts;

        // 1. Generate Draft
        $angle = $rawPayload['suggested_original_angle'] ?? 'Comprehensive student instructions';
        try {
            $genResult = $this->generator->generate($trend['keyword'], $sourceData, $categorySlug, $angle, $resolvedLifecycle);
        } catch (\App\Services\UnresolvedIntentException $e) {
            Logger::warning("PipelineService: Trend #{$trendId} intent could not be resolved with high confidence: " . $e->getMessage());
            if ($force) {
                Logger::info("PipelineService: Admin force publish — retrying generation with default RECRUITMENT intent");
                $sourceData['verified_facts']['_intent'] = 'recruitment';
                $genResult = $this->generator->generate($trend['keyword'], $sourceData, $categorySlug, 'Comprehensive recruitment instructions', $resolvedLifecycle);
            } else {
                TrendService::markStatus($trendId, 'approved', [
                    'trend_score' => 75,
                    'raw_payload' => array_merge($rawPayload, [
                        'needs_human_review' => true,
                        'review_reason' => 'Ambiguous intent classification requires editorial verification'
                    ])
                ]);
                return [
                    'success' => false,
                    'trend_id' => $trendId,
                    'error' => 'Intent ambiguous — routed safely to human editorial review queue.'
                ];
            }
        }
        $paceDelay = $force ? 500000 : 2500000;
        usleep($paceDelay); // Pacing delay (500ms for admin force, 2.5s for autonomous background)

        // 2. Fact Check
        $factAudit = $this->checker->check([
            'title' => $genResult['title'],
            'content' => $genResult['content']
        ], $sourceData);
        usleep($paceDelay);

        // 3. Editorial Polish & Format
        $polished = $this->editor->polish($genResult['title'], $genResult['content'], $categorySlug, $resolvedLifecycle);
        usleep($paceDelay);

        // 4. Contextual Internal Linking
        $availableArticles = ArticleService::getLatestPublished(20);
        $linking = $this->linker->link($polished['edited_content'], $availableArticles);
        usleep($paceDelay);

        // 5. Search Engine Optimization (SEO)
        $seoData = $this->seoGen->generate($polished['edited_title'], $linking['linked_content'], $categorySlug, $resolvedLifecycle);

        // 5b. Senior SEO Manager: Audit & Enhance Keyword Placement, Subheadings & Links
        try {
            $targetKeywords = $seoData['target_keywords'] ?? [];
            $seoOptimizedContent = SEOManagerService::auditAndOptimizeContent(
                $polished['edited_title'],
                $linking['linked_content'],
                $targetKeywords
            );
            if (!empty($seoOptimizedContent)) {
                $linking['linked_content'] = $seoOptimizedContent;
            }
            if (empty($seoData['seo_title']) || mb_strlen($seoData['seo_title']) > 65) {
                $seoData['seo_title'] = SEOManagerService::generateHighCtrTitle($polished['edited_title']);
            }
            if (empty($seoData['meta_description']) || mb_strlen($seoData['meta_description']) > 180) {
                $seoData['meta_description'] = SEOManagerService::generateHighCtrDescription($polished['edited_title'], $linking['linked_content']);
            }
        } catch (Throwable $e) {
            Logger::warning("SEOManagerService content audit warning: " . $e->getMessage());
        }

        // 5c. Mandatory Temporal Content Validator & Deterministic Auto-Repair (9-Point Audit)
        $temporalAudit = TemporalContentValidator::validateAndRepair([
            'title' => $polished['edited_title'],
            'content' => $linking['linked_content'],
            'excerpt' => $seoData['excerpt'],
            'meta_title' => $seoData['seo_title'],
            'meta_description' => $seoData['meta_description'],
            'source_url' => $officialPortal
        ], $extractedFacts, $resolvedLifecycle);

        if (!$temporalAudit['pass']) {
            Logger::warning("TemporalContentValidator flagged unresolved violations for Trend #{$trendId}: " . json_encode($temporalAudit['unresolved_violations']));
        } else {
            $repairedData = $temporalAudit['repaired_data'];
            $polished['edited_title'] = $repairedData['title'];
            $linking['linked_content'] = $repairedData['content'];
            $seoData['excerpt'] = $repairedData['excerpt'];
            $seoData['seo_title'] = $repairedData['meta_title'];
            $seoData['meta_description'] = $repairedData['meta_description'];
            if (!empty($temporalAudit['repairs_applied'])) {
                Logger::info("TemporalContentValidator auto-repaired " . count($temporalAudit['repairs_applied']) . " items for Trend #{$trendId}: " . implode('; ', $temporalAudit['repairs_applied']));
            }
        }

        // ── Step 3E.5: TableIntegrityGate ─────────────────────────────────────
        // Scan for TBA/Awaited/placeholder strings inside <td> table cells.
        // First auto-repair any violations, then verify clean.
        $tableGate = new TableIntegrityGate();
        $linking['linked_content'] = $tableGate->repair($linking['linked_content']);
        $tableViolations = $tableGate->scan($linking['linked_content']);
        if (!empty($tableViolations) && !$force) {
            $reason = "TableIntegrityGate blocked: " . implode('; ', $tableViolations);
            Database::execute(
                "UPDATE articles SET article_health_status='NEEDS_REVIEW' WHERE trend_id=:tid",
                ['tid' => $trendId]
            );
            TrendService::markStatus($trendId, 'needs_enrichment', [
                'raw_payload' => array_merge($rawPayload, [
                    'enrichment_reason'  => $reason,
                    'first_attempted_at' => date('Y-m-d H:i:s'),
                    'retry_count'        => 0,
                ])
            ]);
            Logger::warning("PipelineService: Trend #{$trendId} blocked by TableIntegrityGate: " . count($tableViolations) . " violation(s)");
            return ['success' => false, 'trend_id' => $trendId, 'status' => 'needs_enrichment', 'error' => $reason];
        } elseif (!empty($tableViolations) && $force) {
            Logger::warning("PipelineService: TableIntegrityGate warnings logged on admin force publish: " . implode('; ', $tableViolations));
        }
        // ── End Step 3E.5 ─────────────────────────────────────────────────────

        // 5d. Mechanical Intent & Anti-Boilerplate Lint Safety Gate (Section 5)
        $detectedIntent = $genResult['_intent'] ?? '';
        $lintViolations = self::lintContentIntegrity($linking['linked_content'], $detectedIntent, $genResult, $sourceData);
        if (!empty($lintViolations)) {
            Logger::warning("PipelineService: Mechanical Lint Gate caught violations for Trend #{$trendId}: " . implode('; ', $lintViolations));
            
            // Hard block on required field placeholders or today-date hallucinations
            foreach ($lintViolations as $v) {
                if (str_starts_with($v, 'BLOCKING:')) {
                    if ($force) {
                        Logger::info("PipelineService: Admin force publish — proceeding despite lint notification: {$v}");
                        continue;
                    }
                    $reason = "Post-generation lint gate blocked publication: {$v}";
                    TrendService::markStatus($trendId, 'needs_enrichment', [
                        'raw_payload' => array_merge($rawPayload, [
                            'enrichment_reason' => $reason,
                            'first_attempted_at' => date('Y-m-d H:i:s'),
                            'retry_count' => 0
                        ])
                    ]);
                    Logger::warning("PipelineService: Trend #{$trendId} blocked by lint gate: {$reason}");
                    return ['success' => false, 'trend_id' => $trendId, 'status' => 'needs_enrichment', 'error' => $reason];
                }
            }

            // Check if violation is a forbidden major structural section
            $hasForbiddenSection = false;
            foreach ($lintViolations as $v) {
                if (str_contains($v, 'Forbidden section')) {
                    $hasForbiddenSection = true;
                    break;
                }
            }

            if ($hasForbiddenSection) {
                Logger::info("PipelineService: Triggering safe intent-compliant regeneration for Trend #{$trendId}...");
                try {
                    $correctionInstruction = "CRITICAL EDITORIAL REVISION: The previous draft improperly included forbidden sections for intent '{$detectedIntent}' (such as 'How to Apply' or 'Eligibility'). Completely exclude these sections. Strictly adhere to the {$detectedIntent} OUTLINE CONTRACT.";
                    $genResult = $this->generator->generate($trend['keyword'], $sourceData, $categorySlug, $correctionInstruction, $resolvedLifecycle);
                    $polished = $this->editor->polish($genResult['title'], $genResult['content'], $categorySlug, $resolvedLifecycle);
                    $linking = $this->linker->link($polished['edited_content'], $availableArticles);
                    Logger::info("PipelineService: Re-generation successful without forbidden sections.");
                } catch (Throwable $e) {
                    Logger::warning("PipelineService: Re-generation fallback failed (" . $e->getMessage() . "). Safely applying emergency auto-clean.");
                    $linking['linked_content'] = self::autoCleanLintIssues($linking['linked_content'], $detectedIntent);
                }
            } else {
                // Minor cliché cleanup
                $linking['linked_content'] = self::autoCleanLintIssues($linking['linked_content'], $detectedIntent);
            }
        }

        // 5e. Title-Body Confidence Conflation Safety Gate
        $confidenceViolations = TitleBodyConfidenceDetector::check($polished['edited_title'], $linking['linked_content'], $extractedFacts);
        if (!empty($confidenceViolations)) {
            Logger::warning("PipelineService: Title-Body Confidence Conflation detected for Trend #{$trendId}: " . implode('; ', $confidenceViolations));
            // Neutralize title to truthful milestone status
            $repairedTitle = preg_replace('/\b(exam date(s)?\s+confirmed|dates confirmed|exam date confirmed)\b/i', 'Exam Status & Schedule Timeline', $polished['edited_title']);
            $repairedTitle = preg_replace('/\b(result(s)?\s+declared|result out)\b/i', 'Result Status & Scorecard Updates', $repairedTitle);
            $repairedTitle = preg_replace('/\b(admit card\s+out|admit card released)\b/i', 'Admit Card Status & Guidelines', $repairedTitle);
            $polished['edited_title'] = trim($repairedTitle);
            $seoData['seo_title'] = $polished['edited_title'];
            Logger::info("PipelineService: Neutralized over-confident title to: '{$polished['edited_title']}'");
        }

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
        if (!$temporalAudit['pass']) {
            $safetyPass['pass'] = false;
            $safetyPass['reasons'][] = "Failed temporal integrity audit: " . ($temporalAudit['unresolved_violations'][0]['message'] ?? 'temporal violations');
        }
        if (($sourceData['verified_facts']['extraction_confidence'] ?? '') === 'low') {
            $safetyPass['pass'] = false;
            $safetyPass['reasons'][] = "Authority fact extraction confidence was low — requires human editorial review";
        }
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
        if ($force) {
            $finalStatus = 'published';
            Logger::info("PipelineService: Admin force publish — ensuring status is published (Score: {$finalScore})");
        } elseif (!$safetyPass['pass'] || $finalScore < 70) {
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
            // Check if this new trend has meaningful new factual updates for the existing article!
            try {
                $updateService = new ArticleUpdateService();
                $updateRes = $updateService->processArticleUpdate((int)$existingBySlug['id'], $sourceData);
                if (!empty($updateRes['updated'])) {
                    TrendService::markStatus($trendId, 'published', [
                        'processed_at' => date('Y-m-d H:i:s'),
                        'raw_payload' => array_merge($rawPayload, [
                            'updated_existing_article_id' => $existingBySlug['id'],
                            'change_summary' => $updateRes['change_summary'] ?? 'Updated with new official circular facts'
                        ])
                    ]);
                    Logger::info("🚀 Article #{$existingBySlug['id']} AUTOMATICALLY UPDATED with new official circular facts from Trend #{$trendId}!");
                    return [
                        'success' => true,
                        'article_id' => (int)$existingBySlug['id'],
                        'trend_id' => $trendId,
                        'title' => $existingBySlug['title'],
                        'status' => 'published',
                        'updated' => true
                    ];
                }
            } catch (Throwable $e) {
                Logger::warning("Auto-update attempt on Article #{$existingBySlug['id']} failed: " . $e->getMessage());
            }

            TrendService::markStatus($trendId, 'rejected', ['raw_payload' => ['reason' => "Duplicate: Article already exists with matching slug #{$existingBySlug['id']} ({$existingBySlug['slug']})"]]);
            Logger::warning("Pipeline aborted: Suggested slug '{$targetSlug}' collides with existing Article #{$existingBySlug['id']}. Duplicate generation prevented.");
            return ['success' => false, 'trend_id' => $trendId, 'error' => "Similar article already exists (Article #{$existingBySlug['id']}: '{$existingBySlug['title']}')."];
        }

        // 8. Persist Article in Database directly
        $author = Database::fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $authorId = $author ? (int)$author['id'] : null;
        $now = date('Y-m-d H:i:s');

        // Re-resolve category taxonomy based on final polished title & content
        $finalAutoCat = CategoryService::autoResolveCategory($polished['edited_title'], $linking['linked_content'], $categorySlug);
        if ($finalAutoCat) {
            $categoryId = (int)$finalAutoCat['id'];
        }

        $articleId = ArticleService::create([
            'trend_id' => $trendId,
            'title' => mb_substr($polished['edited_title'], 0, 250),
            'slug' => mb_substr($seoData['slug_suggestion'], 0, 190),
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'excerpt' => $seoData['excerpt'],
            'content' => $linking['linked_content'],
            'status' => $finalStatus,
            'lifecycle_status' => $resolvedLifecycle,
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

        // 8b. Persist Structured Temporal Facts into article_temporal_facts provenance store
        foreach ($extractedFacts as $factName => $factInfo) {
            $val = is_array($factInfo) ? ($factInfo['value'] ?? ($factInfo['fact_value'] ?? null)) : $factInfo;
            $src = is_array($factInfo) ? ($factInfo['source_url'] ?? $officialPortal) : $officialPortal;
            try {
                TemporalFactService::recordFact($articleId, $factName, $val, $src);
            } catch (Throwable $e) {
                Logger::error("PipelineService: Failed to record temporal fact '{$factName}' for Article #{$articleId}: " . $e->getMessage());
            }
        }

        // 8c. Link article to exam_cycle (Phase B — non-blocking)
        if (!empty($examCycle['id'])) {
            try {
                $cycleResolver2 = new ExamCycleResolverService();
                // Detect role from intent
                $intentToRole = [
                    'admit_card'     => 'ADMIT_CARD',
                    'answer_key'     => 'ANSWER_KEY',
                    'result_cutoff'  => 'RESULT',
                    'syllabus_change'=> 'SYLLABUS',
                    'recruitment'    => 'NOTIFICATION',
                    'counselling'    => 'RESULT',
                    'corrigendum'    => 'NOTIFICATION',
                ];
                $articleRole = $intentToRole[strtolower($genResult['_intent'] ?? '')] ?? 'GENERAL';
                $cycleResolver2->linkArticle((int)$examCycle['id'], $articleId, $articleRole);
                Logger::info("PipelineService: Article #{$articleId} linked to exam_cycle #{$examCycle['id']} as {$articleRole}");
            } catch (Throwable $linkEx) {
                Logger::warning("PipelineService: exam_cycle link failed for Article #{$articleId}: " . $linkEx->getMessage());
            }
        }

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

        // 10c. Autonomous Acronym Harvester: scan for unknown acronyms into candidate queue
        try {
            GlossaryService::scanContentForCandidates((int)$articleId, $linking['linked_content'] . ' ' . $polished['edited_title']);
        } catch (Throwable $e) {
            Logger::error("PipelineService: Acronym Harvester error for Article #{$articleId}: " . $e->getMessage());
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

        // 12. Real-Time Search Engine Indexing & High-Authority Backlink Syndication
        if ($finalStatus === 'published') {
            // Google Indexing API is strictly halted for non-JobPosting articles
            // per Google Search Central policy to prevent algorithmic suppression on fresh domains.
            // Organic crawl is managed via XML sitemap (<lastmod>) and GSC.

            try {
                if (IndexNowService::isConfigured()) {
                    $inRes = IndexNowService::pingArticle($articleId);
                    Logger::info("PipelineService: IndexNow ping for Article #{$articleId}: " . ($inRes['message'] ?? 'Done'));
                }
            } catch (Throwable $e) {
                Logger::warning("PipelineService: IndexNow ping error: " . $e->getMessage());
            }

            try {
                $publishedArt = ArticleService::getById($articleId);
                if ($publishedArt) {
                    TelegraphSyndicationService::syndicateArticle($publishedArt);
                    GithubSyndicationService::syndicateArticle($publishedArt);
                }
            } catch (Throwable $e) {
                Logger::warning("PipelineService: Syndication error: " . $e->getMessage());
            }
        }

        return [
            'success' => ($finalStatus === 'published'),
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
        // Prioritize newest trends (id DESC) with highest scores, strictly excluding trends flagged for human review or generic placeholders
        // Uses portable JSON_UNQUOTE comparison to guarantee 100% identical evaluation on both MySQL and MariaDB
        $sql = "SELECT id, keyword, raw_payload FROM trends 
                WHERE status = 'approved' 
                  AND (raw_payload IS NULL 
                       OR JSON_EXTRACT(raw_payload, '$.needs_human_review') IS NULL 
                       OR JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.needs_human_review')) != 'true')
                ORDER BY trend_score DESC, id DESC LIMIT 25";
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

    /**
     * Extract structured temporal facts from verified authority bulletin and keyword context
     */
    public static function extractTemporalFacts(array $verifiedFacts, string $keyword, string $officialPortal): array {
        $facts = [];

        // Check dates_schedule from AuthorityFactFetcherService
        if (!empty($verifiedFacts['dates_schedule']) && is_array($verifiedFacts['dates_schedule'])) {
            foreach ($verifiedFacts['dates_schedule'] as $item) {
                $milestone = strtolower($item['milestone'] ?? '');
                $date = $item['date'] ?? null;
                if (TemporalFactService::isUnannouncedValue($date)) {
                    $date = null;
                }

                if (str_contains($milestone, 'admit') || str_contains($milestone, 'hall ticket')) {
                    $facts['admit_card_date'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'exam') || str_contains($milestone, 'cbt') || str_contains($milestone, 'tier')) {
                    $facts['exam_date'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'result') || str_contains($milestone, 'merit') || str_contains($milestone, 'scorecard')) {
                    $facts['result_date'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'extend') || str_contains($milestone, 'extension')) {
                    $facts['application_extension'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'last date') || str_contains($milestone, 'end') || str_contains($milestone, 'deadline') || str_contains($milestone, 'close')) {
                    $facts['application_end'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'start') || str_contains($milestone, 'commence') || str_contains($milestone, 'begin') || str_contains($milestone, 'open')) {
                    $facts['application_start'] = ['value' => $date, 'source_url' => $officialPortal];
                } elseif (str_contains($milestone, 'answer key') || str_contains($milestone, 'objection')) {
                    $facts['answer_key_date'] = ['value' => $date, 'source_url' => $officialPortal];
                }
            }
        }

        // If deadline not found in schedule, check if keyword mentions a specific deadline
        if (empty($facts['application_end'])) {
            if (preg_match('/(?:last date|deadline|closing date)\s*(?:is|on|:)?\s*([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i', $keyword, $m)) {
                $facts['application_end'] = ['value' => $m[1], 'source_url' => $officialPortal];
            }
        }

        // Zero Date Hallucination: missing critical milestones must be explicitly NULL
        foreach (['exam_date', 'result_date', 'admit_card_date'] as $reqFact) {
            if (!isset($facts[$reqFact])) {
                $facts[$reqFact] = ['value' => null, 'source_url' => $officialPortal];
            }
        }

        return $facts;
    }

    /**
     * Mechanical Intent & Anti-Boilerplate Lint Safety Gate
     * Catches forbidden sections and banned AI clichés mechanically
     */
    public static function lintContentIntegrity(string $html, string $intent, array $articleData = [], array $sourceData = []): array {
        $violations = [];
        $lower = strtolower($html);

        // 1. Forbidden sections per intent
        if ($intent === 'admit_card' || $intent === 'result_cutoff') {
            if (preg_match('/<h2[^>]*>.*?(how to apply|step-by-step online application|detailed eligibility criteria|age limits & qualifications).*?<\/h2>/i', $html, $m)) {
                $violations[] = "Forbidden section for intent '{$intent}': " . strip_tags($m[0]);
            }
        }

        // 2. Banned generic clichés
        $bannedPhrases = [
            "in today's digital world",
            "in this article, we will discuss",
            "without further ado",
            "stay tuned",
            "it is important to note that",
            "as we all know",
            "comprehensive guide"
        ];
        foreach ($bannedPhrases as $bp) {
            if (str_contains($lower, $bp)) {
                $violations[] = "Banned cliché found: '{$bp}'";
            }
        }

        // 3. Post-Generation Fact Completeness & Hallucination Guard (Defense-in-Depth)
        $datesTable = $articleData['dates_table'] ?? [];
        $rawSourceText = ($sourceData['verified_facts']['raw_source_text'] ?? '')
                       . ' ' . ($sourceData['notes'] ?? '')
                       . ' ' . ($sourceData['snippet'] ?? '')
                       . ' ' . ($sourceData['reference'] ?? '')
                       . ' ' . ($sourceData['keyword'] ?? '');
        $todayIssues = HallucinationGuard::checkForSuspiciousTodayDate($datesTable, $rawSourceText);
        $violations = array_merge($violations, $todayIssues);

        try {
            $intentEnum = ArticleIntent::tryFrom($intent);
            if ($intentEnum) {
                foreach (FactCompletenessRules::requiredFieldsFor($intentEnum) as $reqField) {
                    $val = $datesTable[$reqField] ?? null;
                    if ($val === ArticleGenerator::NOT_YET_ANNOUNCED_LABEL || empty($val)) {
                        $violations[] = "BLOCKING: Required field '{$reqField}' is unresolved ('Not Yet Officially Announced') — cannot auto-publish";
                    }
                }
            }
        } catch (Throwable $e) {}

        return $violations;
    }

    /**
     * Mechanical cleaner for forbidden sections and clichés
     */
    public static function autoCleanLintIssues(string $html, string $intent): string {
        // Strip forbidden sections for admit card / results
        if ($intent === 'admit_card' || $intent === 'result_cutoff') {
            $html = preg_replace('/<h2[^>]*>.*?(how to apply|step-by-step online application|detailed eligibility criteria|age limits & qualifications).*?<\/h2>[\s\S]*?(?=<h2|$)/i', '', $html);
        }

        // Strip banned clichés
        $bannedMap = [
            '/in today\'s digital world,?\s*/i' => '',
            '/without further ado,?\s*/i' => '',
            '/stay tuned for further updates\.?\s*/i' => 'Refer to the official portal for subsequent updates.',
            '/it is important to note that\s*/i' => 'Note that ',
            '/as we all know,?\s*/i' => '',
            '/in this comprehensive guide,?\s*/i' => 'In this official briefing, '
        ];
        foreach ($bannedMap as $pat => $rep) {
            $html = preg_replace($pat, $rep, $html);
        }

        return trim($html);
    }
}

