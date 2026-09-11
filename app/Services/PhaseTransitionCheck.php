<?php
declare(strict_types=1);
/**
 * PhaseTransitionCheck — Phase B
 * Detects real exam lifecycle phase and updates exam_cycles DB row.
 * 
 * DESIGN FOR ZERO-API-COST / FREE-TIER COMPATIBILITY:
 * 1. Uses CircularCrawlerService to pull live notices from official .gov.in/.nic.in portals.
 * 2. Uses Deterministic Linked-Article Heuristics (if result/admit card/key article exists).
 * 3. Calls Gemini via generateJson() without paid search grounding tools (100% Free Tier compatible).
 * 4. Fallback-safe: if Gemini hits rate limit, heuristic cleanly elevates phase without failing.
 * 5. Strictly monotonic: phase only advances forward, never backward.
 * 6. TBA / Awaited placeholders are strictly forbidden and scrubbed to null.
 */

namespace App\Services;

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class PhaseTransitionCheck
{
    private Gemini $gemini;

    private const PHASE_ORDER = [
        'ANNUAL_CALENDAR_ONLY'   => 0,
        'NOTIFICATION_RELEASED'  => 1,
        'APPLICATION_OPEN'       => 2,
        'APPLICATION_CORRECTION' => 3,
        'APPLICATION_CLOSED'     => 4,
        'ADMIT_CARD_AWAITED'     => 5,
        'ADMIT_CARD_RELEASED'    => 6,
        'EXAM_SCHEDULED'         => 7,
        'EXAM_CONDUCTED'         => 8,
        'ANSWER_KEY_OBJECTION'   => 9,
        'FINAL_KEY_RELEASED'     => 10,
        'RESULT_DECLARED'        => 11,
        'PET_DV_STAGE'           => 12,
        'FINAL_SELECTION'        => 13,
        'CYCLE_CLOSED'           => 14,
    ];

    private const PHASE_DESCRIPTIONS = [
        'ANNUAL_CALENDAR_ONLY'   => 'Only annual exam calendar announced; no official notification PDF yet',
        'NOTIFICATION_RELEASED'  => 'Official recruitment notification PDF published on authority portal',
        'APPLICATION_OPEN'       => 'Online application form currently accepting submissions',
        'APPLICATION_CORRECTION' => 'Application correction window is open',
        'APPLICATION_CLOSED'     => 'Application form submission window has closed',
        'ADMIT_CARD_AWAITED'     => 'Exam date confirmed but admit card not yet released',
        'ADMIT_CARD_RELEASED'    => 'Admit card / hall ticket / city intimation slip available for download',
        'EXAM_SCHEDULED'         => 'Exam date announced and approaching (exam not yet conducted)',
        'EXAM_CONDUCTED'         => 'Exam conducted; result not yet declared',
        'ANSWER_KEY_OBJECTION'   => 'Provisional answer key released; objection window is open',
        'FINAL_KEY_RELEASED'     => 'Final answer key released after objection processing',
        'RESULT_DECLARED'        => 'Final result / merit list published',
        'PET_DV_STAGE'           => 'Physical Efficiency Test / Document Verification stage ongoing',
        'FINAL_SELECTION'        => 'Final selection list / appointment letters being issued',
        'CYCLE_CLOSED'           => 'This recruitment cycle is fully closed',
    ];

    private const FORBIDDEN_PATTERNS = [
        '/\bTBA\b/i',
        '/\bTo Be Announced\b/i',
        '/\bAwaited\b/i',
        '/\bComing Soon\b/i',
        '/\bNot Announced\b/i',
        '/\bWill be announced\b/i',
        '/\bYet to be released\b/i',
        '/\bExpected Soon\b/i',
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Detect real current phase and update exam_cycles row.
     */
    public function check(array $cycle): array
    {
        $cycleId      = (int)$cycle['id'];
        $currentPhase = $cycle['current_phase'] ?? 'ANNUAL_CALENDAR_ONLY';
        $currentIdx   = self::PHASE_ORDER[$currentPhase] ?? 0;

        $examLabel  = trim(($cycle['authority_code'] ?? '') . ' ' . ($cycle['exam_name'] ?? '') . ' ' . ($cycle['cycle_year'] ?? ''));
        $cycleIdStr = !empty($cycle['cycle_identifier']) ? " ({$cycle['cycle_identifier']})" : '';
        $today      = date('d F Y');

        // ── 1. Deterministic Heuristic from Linked Articles & Titles ─────────
        $inferredFromArticles = $this->inferPhaseFromLinkedArticles($cycleId, $cycle);
        $bestPhase = $currentPhase;
        $bestIdx   = $currentIdx;
        $evidenceUrl = $cycle['phase_evidence_url'] ?? null;
        $confidence  = $cycle['phase_confidence'] ?? 'INFERRED';

        if ($inferredFromArticles !== null) {
            $inferredIdx = self::PHASE_ORDER[$inferredFromArticles['phase']] ?? 0;
            if ($inferredIdx > $bestIdx) {
                $bestPhase   = $inferredFromArticles['phase'];
                $bestIdx     = $inferredIdx;
                $evidenceUrl = $inferredFromArticles['evidence_url'] ?? $evidenceUrl;
                $confidence  = 'VERIFIED';
                Logger::info("PhaseTransitionCheck: Deterministic article evidence elevated cycle #{$cycleId} to {$bestPhase}");
            }
        }

        // ── 2. Gather Official Portal Crawl Data ──────────────────────────────
        $crawlContext = '';
        try {
            $auth = AuthorityFactFetcherService::resolveAuthority($examLabel);
            $portal = $auth['portal'] ?? '';
            if (!empty($portal)) {
                $crawler = new CircularCrawlerService();
                $crawlData = $crawler->gather($portal, $examLabel, false);
                if (!empty($crawlData['documents'])) {
                    foreach (array_slice($crawlData['documents'], 0, 3) as $doc) {
                        $crawlContext .= "[NOTICE: {$doc['url']}]\n" . mb_substr($doc['text'], 0, 800) . "\n\n";
                    }
                }
            }
        } catch (Throwable $e) {
            Logger::debug("PhaseTransitionCheck: Circular crawl skipped: " . $e->getMessage());
        }

        // ── 3. AI Phase Refinement via Gemini (Free Tier JSON Mode) ───────────
        $forwardPhases = array_filter(
            self::PHASE_ORDER,
            fn(int $idx) => $idx >= $bestIdx,
            ARRAY_FILTER_USE_BOTH
        );

        $phaseListText = '';
        foreach ($forwardPhases as $phaseName => $idx) {
            $desc = self::PHASE_DESCRIPTIONS[$phaseName] ?? '';
            $phaseListText .= "  - {$phaseName}: {$desc}\n";
        }

        $contextBlock = !empty($crawlContext) ? "OFFICIAL RECENT PORTAL CIRCULARS:\n{$crawlContext}\n" : "";

        $prompt = <<<PROMPT
You are a senior exam lifecycle verification analyst for Sarkari.online. Today: {$today}.

EXAM: {$examLabel}{$cycleIdStr}
CURRENT VERIFIED STAGE: {$bestPhase}

{$contextBlock}
Analyze the lifecycle of this Indian government examination.
ALLOWED FORWARD PHASES (cannot go backward from {$bestPhase}):
{$phaseListText}

STRICT INSTRUCTIONS:
1. Identify the furthest officially confirmed phase. If unsure, stay at {$bestPhase}.
2. For dates or numbers, return null if unknown or not announced.
3. NEVER write "TBA", "Awaited", "Coming Soon", "To Be Announced", or "Expected Soon". Strings like that are STRICTLY FORBIDDEN.
4. Return ONLY valid JSON:
{
  "detected_phase": "PHASE_NAME",
  "confidence": "VERIFIED",
  "evidence_url": "https://official-portal-or-notice-url",
  "next_expected_transition": "YYYY-MM-DD or null",
  "facts": {
    "notification_date": "YYYY-MM-DD or null",
    "application_start": "YYYY-MM-DD or null",
    "application_end": "YYYY-MM-DD or null",
    "admit_card_date": "YYYY-MM-DD or null",
    "exam_dates": ["YYYY-MM-DD"] or null,
    "answer_key_date": "YYYY-MM-DD or null",
    "result_date": "YYYY-MM-DD or null",
    "vacancies": integer or null,
    "application_fee_general": integer or null,
    "required_documents": [
      {"item": "Document Name", "mandatory": true, "applies_to": "All Candidates"}
    ] or null
  }
}
PROMPT;

        $response = $this->callGeminiSafe($prompt);
        if ($response !== null) {
            $validated = $this->validateResponse($response, $bestIdx);
            if ($validated !== null) {
                $bestPhase   = $validated['detected_phase'];
                $confidence  = $validated['confidence'];
                $evidenceUrl = $validated['evidence_url'] ?: $evidenceUrl;
                $facts       = $validated['facts'];
                $nextTrans   = $validated['next_expected_transition'];

                $this->updateCycle($cycleId, [
                    'detected_phase'           => $bestPhase,
                    'confidence'               => $confidence,
                    'evidence_url'             => $evidenceUrl,
                    'next_expected_transition' => $nextTrans,
                    'facts'                    => $facts,
                ]);
            }
        } elseif ($bestPhase !== $currentPhase) {
            // Heuristic elevated the phase even if Gemini was rate-limited!
            $this->updateCycle($cycleId, [
                'detected_phase'           => $bestPhase,
                'confidence'               => $confidence,
                'evidence_url'             => $evidenceUrl,
                'next_expected_transition' => null,
                'facts'                    => [],
            ]);
        }

        $updated = Database::fetchOne("SELECT * FROM exam_cycles WHERE id = :id LIMIT 1", ['id' => $cycleId]);
        return $updated ?: $cycle;
    }

    /**
     * Deterministic heuristic: if articles already exist in DB for this exam,
     * inspect their roles and titles to determine minimum proven phase.
     */
    private function inferPhaseFromLinkedArticles(int $cycleId, array $cycle): ?array
    {
        $rows = Database::fetchAll(
            "SELECT a.id, a.title, a.source_url, eca.article_role
               FROM exam_cycle_articles eca
               JOIN articles a ON a.id = eca.article_id
              WHERE eca.exam_cycle_id = :cid",
            ['cid' => $cycleId]
        );

        if (empty($rows)) {
            // Fallback: search articles by exam name keywords
            $examName = $cycle['exam_name'] ?? '';
            if (mb_strlen($examName) >= 4) {
                $rows = Database::fetchAll(
                    "SELECT id, title, source_url, 'GENERAL' as article_role
                       FROM articles
                      WHERE title LIKE :q AND status = 'published' LIMIT 5",
                    ['q' => '%' . $examName . '%']
                );
            }
        }

        if (empty($rows)) return null;

        $highestPhase = null;
        $highestIdx   = -1;
        $evidenceUrl  = null;

        foreach ($rows as $r) {
            $t = mb_strtolower($r['title']);
            $role = strtoupper($r['article_role'] ?? '');

            $candidatePhase = null;

            if ($role === 'RESULT' || str_contains($t, 'result') || str_contains($t, 'merit list') || str_contains($t, 'scorecard')) {
                $candidatePhase = 'RESULT_DECLARED';
            } elseif ($role === 'ANSWER_KEY' || str_contains($t, 'answer key') || str_contains($t, 'objection')) {
                $candidatePhase = 'ANSWER_KEY_OBJECTION';
            } elseif (str_contains($t, 'concluded') || str_contains($t, 'shift timing') || str_contains($t, 'analysis')) {
                $candidatePhase = 'EXAM_CONDUCTED';
            } elseif ($role === 'ADMIT_CARD' || str_contains($t, 'admit card') || str_contains($t, 'city slip') || str_contains($t, 'hall ticket')) {
                $candidatePhase = 'ADMIT_CARD_RELEASED';
            } elseif ($role === 'NOTIFICATION' || str_contains($t, 'notification') || str_contains($t, 'apply online') || str_contains($t, 'registration')) {
                $candidatePhase = 'APPLICATION_OPEN';
            }

            if ($candidatePhase !== null) {
                $idx = self::PHASE_ORDER[$candidatePhase] ?? 0;
                if ($idx > $highestIdx) {
                    $highestIdx   = $idx;
                    $highestPhase = $candidatePhase;
                    $evidenceUrl  = !empty($r['source_url']) ? $r['source_url'] : null;
                }
            }
        }

        if ($highestPhase !== null) {
            return ['phase' => $highestPhase, 'evidence_url' => $evidenceUrl];
        }

        return null;
    }

    private function callGeminiSafe(string $prompt): ?array
    {
        // Don't call if circuit breaker is already active
        if (Gemini::isCircuitBreakerActive()) {
            return null;
        }

        try {
            // Standard JSON mode without search grounding tools — works 100% on Free Tier!
            $response = $this->gemini->generateJson($prompt, [
                'stage'       => 'phase_transition_check',
                'temperature' => 0.0,
            ]);
            return $response['data'] ?? null;
        } catch (Throwable $e) {
            Logger::warning("PhaseTransitionCheck: Gemini free-tier call skipped/failed: " . $e->getMessage());
            return null;
        }
    }

    private function validateResponse(array $data, int $currentIdx): ?array
    {
        $detectedPhase = $data['detected_phase'] ?? null;
        if (!isset(self::PHASE_ORDER[$detectedPhase])) return null;
        if (self::PHASE_ORDER[$detectedPhase] < $currentIdx) return null;

        $confidence  = in_array($data['confidence'] ?? '', ['VERIFIED', 'INFERRED'], true) ? $data['confidence'] : 'INFERRED';
        $evidenceUrl = $data['evidence_url'] ?? null;

        return [
            'detected_phase'           => $detectedPhase,
            'confidence'               => $confidence,
            'evidence_url'             => $evidenceUrl,
            'next_expected_transition' => $this->parseDate($data['next_expected_transition'] ?? null),
            'facts'                    => $this->sanitizeFacts($data['facts'] ?? []),
        ];
    }

    private function sanitizeFacts(array $facts): array
    {
        foreach ($facts as $key => $value) {
            if (is_string($value)) {
                foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                    if (preg_match($pattern, $value)) { $facts[$key] = null; break; }
                }
            } elseif (is_array($value)) {
                $cleaned = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $forbidden = false;
                        foreach (self::FORBIDDEN_PATTERNS as $p) {
                            if (preg_match($p, $item)) { $forbidden = true; break; }
                        }
                        if (!$forbidden) {
                            $cleaned[] = $item;
                        }
                    } elseif (is_array($item)) {
                        $forbidden = false;
                        foreach ($item as $subK => $subV) {
                            if (is_string($subV)) {
                                foreach (self::FORBIDDEN_PATTERNS as $p) {
                                    if (preg_match($p, $subV)) { $forbidden = true; break 2; }
                                }
                            }
                        }
                        if (!$forbidden && !empty($item['item'])) {
                            $cleaned[] = [
                                'item'        => (string)$item['item'],
                                'mandatory'   => !empty($item['mandatory']),
                                'applies_to'  => !empty($item['applies_to']) ? (string)$item['applies_to'] : 'All Candidates',
                            ];
                        }
                    }
                }
                $facts[$key] = empty($cleaned) ? null : array_values($cleaned);
            }
        }
        return $facts;
    }

    private function parseDate(?string $raw): ?string
    {
        if (empty($raw)) return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null;
    }

    private function updateCycle(int $cycleId, array $v): void
    {
        $existing = Database::fetchOne("SELECT facts_json FROM exam_cycles WHERE id = :id LIMIT 1", ['id' => $cycleId]);
        $mergedFacts = [];
        if (!empty($existing['facts_json'])) {
            $prev = json_decode($existing['facts_json'], true);
            if (is_array($prev)) {
                $mergedFacts = $prev;
            }
        }
        if (!empty($v['facts']) && is_array($v['facts'])) {
            foreach ($v['facts'] as $fk => $fv) {
                if ($fv !== null) {
                    $mergedFacts[$fk] = $fv;
                }
            }
        }
        $factsJson = !empty($mergedFacts) ? json_encode($mergedFacts, JSON_UNESCAPED_UNICODE) : null;

        Database::execute(
            "UPDATE exam_cycles SET
                current_phase            = :phase,
                phase_confidence         = :confidence,
                phase_evidence_url       = :evidence_url,
                phase_detected_at        = NOW(),
                next_expected_transition = :next_transition,
                facts_json               = :facts_json,
                last_verified_at         = NOW(),
                updated_at               = NOW()
             WHERE id = :id",
            [
                'phase'           => $v['detected_phase'],
                'confidence'      => $v['confidence'],
                'evidence_url'    => $v['evidence_url'],
                'next_transition' => $v['next_expected_transition'],
                'facts_json'      => $factsJson,
                'id'              => $cycleId,
            ]
        );
    }
}
