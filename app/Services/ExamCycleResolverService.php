<?php
declare(strict_types=1);
/**
 * ExamCycleResolverService — Phase A
 *
 * Promotes "exam" from an implicit string to a first-class stateful entity (exam_cycles row).
 *
 * Phase A behaviour (deliberately limited — no Grounded Gemini search yet):
 *   resolve(keyword) →
 *     1. Parse keyword → (authority_code, exam_name, cycle_year)
 *     2. Lookup exam_cycles table
 *     3a. Found  → return existing row as-is
 *     3b. Not found → INSERT new row at 'ANNUAL_CALENDAR_ONLY' phase and return it
 *
 * Phase B will extend this with PhaseTransitionCheck + Grounded Gemini search.
 *
 * CRITICAL ARCHITECTURE RULE (enforced here and in every future Phase):
 *   This service NEVER writes "TBA", "Awaited", "Coming Soon", or "To Be Announced"
 *   into any database column. If a fact is unknown, the column stays NULL.
 *   Display fallback text ("Not yet announced") is the PHP view layer's responsibility.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class ExamCycleResolverService
{
    // ─── Authority keyword map ────────────────────────────────────────────────
    // Maps authority name fragments (lowercase) → authority_code matching authority_portals.acronym.
    // Longer / more-specific patterns must come BEFORE shorter ones to avoid premature matches.
    private const AUTHORITY_MAP = [
        // Railways
        'rrb'             => 'RRB',
        'rrc'             => 'RRB',
        'rpf'             => 'RRB',
        'railway'         => 'RRB',
        'railways'        => 'RRB',

        // SSC
        'ssc'             => 'SSC',
        'staff selection' => 'SSC',

        // UPSC
        'upsc'            => 'UPSC',
        'union public service' => 'UPSC',
        'ias'             => 'UPSC',
        'civil services'  => 'UPSC',

        // Banking
        'ibps'            => 'IBPS',
        'sbi po'          => 'SBI',
        'sbi clerk'       => 'SBI',
        'sbi'             => 'SBI',
        'bank of baroda'  => 'BOB',

        // Defence
        'iaf'             => 'IAF',
        'air force'       => 'IAF',
        'indian army'     => 'ARMY',
        'army'            => 'ARMY',
        'indian navy'     => 'NAVY',
        'navy'            => 'NAVY',
        'coast guard'     => 'COASTGUARD',
        'drdo'            => 'DRDO',
        'isro'            => 'ISRO',
        'bsf'             => 'BSF',
        'crpf'            => 'CRPF',
        'cisf'            => 'CISF',
        'itbp'            => 'ITBP',
        'ssb'             => 'SSB',

        // NTA / National — specific exams BEFORE generic 'nta'
        'neet ug'         => 'NTA',
        'neet pg'         => 'NTA',
        'neet'            => 'NTA',
        'jee main'        => 'NTA',
        'jee advanced'    => 'NTA',
        'jee'             => 'NTA',
        'cuet'            => 'NTA',
        'pm yasasvi'      => 'NTA',
        'yasasvi'         => 'NTA',
        'nta'             => 'NTA',

        // UGC / CSIR
        'ugc net'         => 'UGC',
        'ugc'             => 'UGC',
        'csir net'        => 'CSIR',
        'csir'            => 'CSIR',

        // GATE
        'gate'            => 'GATE',

        // ICAR
        'icar aieea'      => 'ICAR',
        'icar'            => 'ICAR',

        // Education boards / councils
        'ctet'            => 'CTET',
        'cbse'            => 'CBSE',
        'aicte'           => 'AICTE',
        'ignou'           => 'IGNOU',
        'aiims'           => 'AIIMS',
        'nbems'           => 'NBEMS',
        'fmge'            => 'NBEMS',

        // Karnataka
        'kcet'            => 'KEA',
        'kpsc'            => 'KPSC',

        // Maharashtra
        'mht cet'         => 'MAHACET',
        'mhtcet'          => 'MAHACET',
        'mahacet'         => 'MAHACET',

        // UP State — specific before generic
        'upessc'          => 'UPESSC',
        'upsssc'          => 'UPSSSC',
        'upprpb'          => 'UPPRPB',
        'up police'       => 'UPPRPB',
        'uppsc'           => 'UPPSC',
        'uptet'           => 'UPBEB',

        // Bihar
        'bpsc'            => 'BPSC',
        'bssc'            => 'BSSC',
        'csbc'            => 'CSBC',
        'bseb'            => 'BSEB',

        // MP
        'mppsc'           => 'MPPSC',

        // Rajasthan
        'rpsc'            => 'RPSC',
        'rsmssb'          => 'RSMSSB',
        'rvunl'           => 'RVUNL',

        // Haryana
        'hssc'            => 'HSSC',
        'hpsc'            => 'HPSC',
        'bseh'            => 'BSEH',

        // HP
        'hpbose'          => 'HPBOSE',

        // Uttarakhand
        'ukpsc'           => 'UKPSC',
        'upmsp'           => 'UPMSP',

        // West Bengal
        'wbjeeb'          => 'WBJEE',
        'wbjee'           => 'WBJEE',
        'wbssc'           => 'WBSSC',

        // Schools / scholarships
        'kvs'             => 'KVS',
        'nvs'             => 'NVS',
        'dsssb'           => 'DSSSB',
        'pmsss'           => 'AICTE',

        // PSU / Central bodies
        'coal india'      => 'COALINDIA',
        'josaa'           => 'JOSAA',
        'mcc'             => 'MCC',
        'nsp'             => 'NSP',

        // India Post
        'india post'      => 'INDPOST',
        'gds'             => 'INDPOST',

        // Delhi University
        'du sol'          => 'DUSOL',
        'delhi university' => 'DUSOL',

        // Bar Council
        'aibe'            => 'BCI',
        'bar council'     => 'BCI',

        // Punjab
        'punjab pti'      => 'PPSC',
    ];

    // ─── Phase forward-map (for reference — used in Phase B PhaseTransitionCheck) ──
    // Phase A doesn't use this but it is defined here as the canonical ordering.
    public const PHASE_ORDER = [
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

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a free-text keyword to a canonical exam_cycles row.
     *
     * @param  string     $keyword  e.g. "RRB Group D 2026", "SSC CGL 2025"
     * @return array|null  Associative array of exam_cycles row, or null on parse failure.
     */
    public function resolve(string $keyword): ?array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            Logger::warning('ExamCycleResolver: empty keyword passed to resolve()');
            return null;
        }

        // Step 1 — Parse keyword into canonical parts
        $parsed = $this->parseKeyword($keyword);
        if ($parsed === null) {
            Logger::warning("ExamCycleResolver: could not parse authority/year from '{$keyword}'");
            return null;
        }

        [$authorityCode, $examName, $cycleYear, $cycleIdentifier] = $parsed;

        Logger::info(
            "ExamCycleResolver: resolved '{$keyword}' → " .
            "authority={$authorityCode}, exam='{$examName}', year={$cycleYear}" .
            ($cycleIdentifier ? ", id={$cycleIdentifier}" : '')
        );

        // Step 2 — Lookup existing cycle
        $existing = $this->findCycle($authorityCode, $examName, $cycleYear, $cycleIdentifier);
        if ($existing !== null) {
            Logger::info("ExamCycleResolver: found existing exam_cycle id={$existing['id']} (phase={$existing['current_phase']})");
            return $existing;
        }

        // Step 3 — Insert new cycle at ANNUAL_CALENDAR_ONLY (safe cold-start phase)
        // RULE: never write TBA/Awaited here; facts_json stays NULL until Phase B enriches it.
        $newId = $this->createCycle($authorityCode, $examName, $cycleYear, $cycleIdentifier);
        if ($newId === null) {
            Logger::error("ExamCycleResolver: INSERT failed for '{$keyword}'");
            return null;
        }

        $row = $this->findById($newId);
        Logger::info("ExamCycleResolver: created new exam_cycle id={$newId} for '{$keyword}'");
        return $row;
    }

    // ─── Keyword Parser ───────────────────────────────────────────────────────

    /**
     * Extract (authority_code, exam_name, cycle_year, cycle_identifier|null) from free text.
     *
     * Returns null if authority or year cannot be determined.
     *
     * KEY DESIGN: exam_name is extracted from the portion BEFORE the first ":" in the title.
     * "RRB Group D 2026: Notification..." and "RRB Group D 2026: Admit Card..." both resolve
     * to exam_name="RRB Group D" → share ONE exam_cycle row (correct).
     * "RRB NTPC 2026: ..." → exam_name="RRB NTPC" → DIFFERENT row (correct).
     */
    private function parseKeyword(string $keyword): ?array
    {
        $lower = mb_strtolower($keyword);

        // 1. Extract year (4-digit, 2020–2040)
        preg_match('/\b(20[2-4]\d)\b/', $keyword, $yearMatch);
        $cycleYear = isset($yearMatch[1]) ? (int)$yearMatch[1] : (int)date('Y');

        // 2. Extract cycle identifier — CEN-01/2024, Phase-VIII, etc.
        $cycleIdentifier = null;
        if (preg_match('/\b(CEN[-\s]?[\w\/]+)\b/i', $keyword, $cenMatch)) {
            $cycleIdentifier = strtoupper(trim($cenMatch[1]));
        } elseif (preg_match('/\b(Phase[-\s]?(?:I{1,3}|IV|VI{0,3}|VIII?|IX|X|[0-9]+))\b/i', $keyword, $phaseMatch)) {
            $cycleIdentifier = ucfirst(strtolower(trim($phaseMatch[1])));
        }

        // 3. Authority matching — longest match wins (most-specific first)
        $authorityCode = null;
        $matchedLen    = 0;
        foreach (self::AUTHORITY_MAP as $fragment => $code) {
            if (str_contains($lower, $fragment) && strlen($fragment) > $matchedLen) {
                $authorityCode = $code;
                $matchedLen    = strlen($fragment);
            }
        }

        if ($authorityCode === null) {
            return null; // Cannot determine authority — caller must handle
        }

        // 4. exam_name = title portion BEFORE the first ":" → strip year → strip trailing role-words
        //    "RRB Group D 2026: Notification..." → exam part "RRB Group D 2026" → "RRB Group D"
        //    "NEET PG 2026 Answer Key: ..."      → exam part "NEET PG 2026 Answer Key"
        //                                          → after stripping "answer key" → "NEET PG"
        $examPart = strpos($keyword, ':') !== false
            ? trim(strstr($keyword, ':', true))
            : $keyword;

        // Strip year from exam part
        $examPart = trim(preg_replace('/\b20[2-4]\d\b/', '', $examPart));

        // Strip role-descriptor words from the END (they don't distinguish one exam from another)
        $roleWords = [
            'notification', 'recruitment', 'result', 'admit', 'card', 'answer', 'key',
            'syllabus', 'registration', 'application', 'guide', 'complete', 'official',
            'update', 'schedule', 'declared', 'released', 'concluded', 'scorecard',
            'august', 'september', 'october', 'november', 'december', 'january',
            'february', 'march', 'april', 'may', 'june', 'july',
        ];
        $words = preg_split('/\s+/', $examPart);
        while (!empty($words) && in_array(mb_strtolower(end($words)), $roleWords, true)) {
            array_pop($words);
        }
        $examName = implode(' ', $words);

        if ($cycleIdentifier) {
            $examName = str_ireplace($cycleIdentifier, '', $examName);
        }

        $examName = preg_replace('/\s+/', ' ', trim($examName));
        $examName = $this->toTitleCase($examName);

        if (mb_strlen($examName) < 3) {
            $examName = $authorityCode . ' Exam';
        }

        return [$authorityCode, $examName, $cycleYear, $cycleIdentifier];
    }

    // ─── DB Helpers ───────────────────────────────────────────────────────────

    private function findCycle(
        string  $authorityCode,
        string  $examName,
        int     $cycleYear,
        ?string $cycleIdentifier
    ): ?array {
        // Try exact match first (respects cycle_identifier = NULL distinctness)
        if ($cycleIdentifier !== null) {
            $row = Database::fetchOne(
                "SELECT * FROM exam_cycles
                  WHERE authority_code    = :authority_code
                    AND exam_name         = :exam_name
                    AND cycle_year        = :cycle_year
                    AND cycle_identifier  = :cycle_identifier
                  LIMIT 1",
                [
                    'authority_code'   => $authorityCode,
                    'exam_name'        => $examName,
                    'cycle_year'       => $cycleYear,
                    'cycle_identifier' => $cycleIdentifier,
                ]
            );
            if ($row) return $row;
        } else {
            $row = Database::fetchOne(
                "SELECT * FROM exam_cycles
                  WHERE authority_code   = :authority_code
                    AND exam_name        = :exam_name
                    AND cycle_year       = :cycle_year
                    AND cycle_identifier IS NULL
                  LIMIT 1",
                [
                    'authority_code' => $authorityCode,
                    'exam_name'      => $examName,
                    'cycle_year'     => $cycleYear,
                ]
            );
            if ($row) return $row;
        }

        // Normalized Fuzzy Match: Catches variations like "Cbse Ctet" vs "Ctet Teacher Eligibility Test"
        // while strictly preserving distinct exams (NEET UG vs NEET PG, JEE Main vs JEE Advanced).
        $sql = "SELECT * FROM exam_cycles 
                 WHERE authority_code = :authority_code 
                   AND cycle_year = :cycle_year " . 
                   ($cycleIdentifier !== null ? "AND cycle_identifier = :cycle_identifier" : "AND cycle_identifier IS NULL");
        $params = ['authority_code' => $authorityCode, 'cycle_year' => $cycleYear];
        if ($cycleIdentifier !== null) {
            $params['cycle_identifier'] = $cycleIdentifier;
        }

        try {
            $candidates = Database::fetchAll($sql, $params);
            if (!empty($candidates)) {
                $normTarget = $this->normalizeExamName($examName);
                if ($normTarget !== '') {
                    foreach ($candidates as $cand) {
                        $normCand = $this->normalizeExamName($cand['exam_name']);
                        if ($normCand === '') continue;

                        if ($normTarget === $normCand) {
                            Logger::info("ExamCycleResolver: Fuzzy matched '{$examName}' to existing cycle '{$cand['exam_name']}' (#{$cand['id']})");
                            return $cand;
                        }

                        $targetTokens = array_filter(explode(' ', $normTarget));
                        $candTokens   = array_filter(explode(' ', $normCand));
                        $intersection = array_intersect($targetTokens, $candTokens);

                        if (!empty($intersection) && (count($intersection) === count($targetTokens) || count($intersection) === count($candTokens))) {
                            Logger::info("ExamCycleResolver: Token-containment matched '{$examName}' to existing cycle '{$cand['exam_name']}' (#{$cand['id']})");
                            return $cand;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::warning("ExamCycleResolver fuzzy match error: " . $e->getMessage());
        }

        return null;
    }

    private function normalizeExamName(string $name): string
    {
        $lower = mb_strtolower(trim($name));
        $noiseWords = [
            'cbse', 'notification', 'recruitment', 'exam', 'examination', 'test',
            'online', 'form', 'guide', 'syllabus', 'teacher', 'eligibility',
            'admit', 'card', 'result', 'scorecard', 'official', 'update'
        ];
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower);
        $words = preg_split('/\s+/', (string)$cleaned, -1, PREG_SPLIT_NO_EMPTY);
        $filtered = array_filter($words, fn($w) => !in_array($w, $noiseWords, true));
        return implode(' ', $filtered);
    }

    private function createCycle(
        string  $authorityCode,
        string  $examName,
        int     $cycleYear,
        ?string $cycleIdentifier
    ): ?int {
        try {
            Database::execute(
                "INSERT INTO exam_cycles
                    (authority_code, exam_name, cycle_year, cycle_identifier,
                     current_phase, phase_confidence, phase_detected_at, created_at, updated_at)
                 VALUES
                    (:authority_code, :exam_name, :cycle_year, :cycle_identifier,
                     'ANNUAL_CALENDAR_ONLY', 'INFERRED', NOW(), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    updated_at = NOW()",   // harmless no-op on race condition
                [
                    'authority_code'   => $authorityCode,
                    'exam_name'        => $examName,
                    'cycle_year'       => $cycleYear,
                    'cycle_identifier' => $cycleIdentifier,
                ]
            );

            $row = Database::fetchOne("SELECT LAST_INSERT_ID() AS lid");
            $lid = (int)($row['lid'] ?? 0);

            if ($lid === 0) {
                // Duplicate key path — fetch existing row
                $existing = $this->findCycle($authorityCode, $examName, $cycleYear, $cycleIdentifier);
                return $existing ? (int)$existing['id'] : null;
            }

            return $lid;
        } catch (Throwable $e) {
            Logger::error('ExamCycleResolver: createCycle failed — ' . $e->getMessage());
            return null;
        }
    }

    private function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM exam_cycles WHERE id = :id LIMIT 1",
            ['id' => $id]
        ) ?: null;
    }

    /**
     * Link an article to an exam_cycle in exam_cycle_articles.
     * Call this from backfill script and future pipeline steps.
     */
    public function linkArticle(int $examCycleId, int $articleId, string $role = 'GENERAL'): bool
    {
        $allowedRoles = ['NOTIFICATION','ADMIT_CARD','ANSWER_KEY','RESULT','SYLLABUS','CUTOFF','GENERAL'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'GENERAL';
        }

        try {
            Database::execute(
                "INSERT IGNORE INTO exam_cycle_articles
                     (exam_cycle_id, article_id, article_role, linked_at)
                 VALUES
                     (:exam_cycle_id, :article_id, :role, NOW())",
                [
                    'exam_cycle_id' => $examCycleId,
                    'article_id'    => $articleId,
                    'role'          => $role,
                ]
            );
            return true;
        } catch (Throwable $e) {
            Logger::error("ExamCycleResolver: linkArticle({$examCycleId},{$articleId}) failed — " . $e->getMessage());
            return false;
        }
    }

    // ─── Utility ──────────────────────────────────────────────────────────────

    private function toTitleCase(string $str): string
    {
        // Title-case but keep short prepositions/conjunctions lowercase
        $lowers = ['of', 'the', 'and', 'in', 'for', 'to', 'a', 'an', 'by', 'at'];
        $words  = explode(' ', mb_strtolower($str));
        foreach ($words as $i => &$word) {
            if ($i === 0 || !in_array($word, $lowers, true)) {
                $word = ucfirst($word);
            }
        }
        return implode(' ', $words);
    }
}
