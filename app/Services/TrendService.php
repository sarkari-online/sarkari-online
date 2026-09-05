<?php
/**
 * EduPulse - Trend Detection & Lifecycle Management Service
 * Manages trend discovery adapters, keyword normalization, deduplication against existing library,
 * category validation, and database status lifecycle transitions.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Services\TrendSources\TrendSourceInterface;
use App\Services\TrendSources\GoogleTrendsAdapter;
use App\Services\TrendSources\RssFeedAdapter;
use App\Services\TrendSources\OfficialSourcesAdapter;
use App\Services\TrendSources\EvergreenTopicsAdapter;
use App\Services\TrendSources\GovtJobsAdapter;
use Exception;
use Throwable;

class TrendService {

    /** @var TrendSourceInterface[] */
    private array $adapters = [];

    public function __construct(array $adapters = []) {
        if (!empty($adapters)) {
            $this->adapters = $adapters;
        } else {
            // Default built-in source adapters: Live Official Portals, Govt Jobs Radar, Evergreen Catalog, Exam RSS Feeds, and Google Trends
            $this->adapters = [
                new OfficialSourcesAdapter(),
                new GovtJobsAdapter(),
                new EvergreenTopicsAdapter(),
                new RssFeedAdapter(),
                new GoogleTrendsAdapter()
            ];
        }
    }

    /**
     * Register a new trend source adapter
     */
    public function registerAdapter(TrendSourceInterface $adapter): void {
        $this->adapters[] = $adapter;
    }

    /**
     * Normalize a keyword string for comparison
     */
    public static function normalizeKeyword(string $keyword): string {
        $clean = mb_strtolower(trim($keyword));
        $clean = preg_replace('/\s+/', ' ', $clean);
        // Remove special punctuation
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean);
        return trim($clean);
    }

    /**
     * Generate unique hash for deduplication
     */
    public static function normalizedHash(string $keyword): string {
        $normalized = self::normalizeKeyword($keyword);
        // Strip common Indian news noise words to detect duplicate intent
        $stopWords = ['released', 'declared', 'announced', 'direct link', 'how to check', 'download', 'today', 'live updates', 'out', 'pdf', 'online'];
        foreach ($stopWords as $sw) {
            $normalized = str_replace($sw, '', $normalized);
        }
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
        return hash('sha256', $normalized);
    }

    /**
     * Check if a keyword is a generic synthetic placeholder that lacks specific exam/event context
     */
    public static function isGenericPlaceholderKeyword(string $keyword): bool {
        $kwLower = mb_strtolower(trim($keyword));

        // Reject generic template patterns
        if (preg_match('/latest\s+notification\b.*exam\s+schedule/i', $kwLower)) {
            return true;
        }
        if (preg_match('/exam\s+schedule,\s*result\s+and\s+recruitment\s+update/i', $kwLower)) {
            return true;
        }
        if (preg_match('/latest\s+notification\s+[a-z]+\s+202\d/i', $kwLower)) {
            return true;
        }
        if (preg_match('/latest\s+notification\s*:\s*exam\s+schedule/i', $kwLower)) {
            return true;
        }

        return false;
    }

    /**
     * Check if trend already exists in trends table (including previously rejected/processed items)
     */
    public static function existsAsTrend(string $keyword, int $days = 14): bool {
        $hash = self::normalizedHash($keyword);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $existing = Database::fetchOne(
            "SELECT id FROM trends WHERE normalized_hash = :hash AND (created_at >= :since OR detected_at >= :since) LIMIT 1",
            ['hash' => $hash, 'since' => $since]
        );

        return $existing !== null;
    }

    /**
     * Check if similar article already exists in published articles
     */
    public static function existsAsArticle(string $keyword): bool {
        $clean = mb_strtolower(trim($keyword));
        $normalized = self::normalizeKeyword($keyword);
        $words = explode(' ', $normalized);
        $keyWords = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, ['the', 'and', 'for', 'with', 'from', 'update', 'latest', 'notification', '2026', '2027'], true)));

        if (empty($keyWords)) {
            return false;
        }

        // 1. Direct Slug / Keyword match
        $slugGuess = Sanitizer::slug($keyword);
        $existingBySlug = Database::fetchOne("SELECT id FROM articles WHERE slug LIKE :s LIMIT 1", ['s' => '%' . mb_substr($slugGuess, 0, 35) . '%']);
        if ($existingBySlug) return true;

        // 2. Specific key entity pairs check (e.g. 'nsp scholarship', 'upsc nda', 'ssc cgl', etc.)
        $entities = [
            ['nsp', 'scholarship'],
            ['national', 'scholarship'],
            ['upsc', 'nda'],
            ['upsc', 'cds'],
            ['ssc', 'je'],
            ['ssc', 'cgl'],
            ['ssc', 'chsl'],
            ['neet', 'ug'],
            ['jee', 'main'],
            ['rrb', 'ntpc'],
            ['rrb', 'recruitment'],
            ['bpsc', 'tre'],
            ['sbi', 'po'],
            ['ibps', 'rrb'],
            ['cbse', 'board']
        ];

        foreach ($entities as $pair) {
            if (str_contains($clean, $pair[0]) && str_contains($clean, $pair[1])) {
                $p1 = '%' . $pair[0] . '%';
                $p2 = '%' . $pair[1] . '%';
                $found = Database::fetchOne("SELECT id FROM articles WHERE (LOWER(title) LIKE :p1 AND LOWER(title) LIKE :p2) OR (LOWER(slug) LIKE :p1 AND LOWER(slug) LIKE :p2) LIMIT 1", [
                    'p1' => $p1,
                    'p2' => $p2
                ]);
                if ($found) return true;
            }
        }

        // 3. Match any 2 distinct significant keywords in existing articles
        if (count($keyWords) >= 2) {
            $w1 = '%' . $keyWords[0] . '%';
            $w2 = '%' . $keyWords[1] . '%';
            $found = Database::fetchOne("SELECT id FROM articles WHERE (LOWER(title) LIKE :w1 AND LOWER(title) LIKE :w2) OR (LOWER(slug) LIKE :w1 AND LOWER(slug) LIKE :w2) LIMIT 1", [
                'w1' => $w1,
                'w2' => $w2
            ]);
            if ($found) return true;
        }

        return false;
    }

    /**
     * Check if a topic was published recently (e.g. within 30 days) to prevent spam repetition
     */
    public static function isRecentlyCovered(string $keyword, int $days = 30): bool {
        $normalized = self::normalizeKeyword($keyword);
        $words = explode(' ', $normalized);
        $keyWords = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, ['the', 'and', 'for', 'with', 'from', 'update', 'latest', 'notification', '2026', '2027'], true)));

        if (empty($keyWords)) {
            return false;
        }

        $searchTerms = array_slice($keyWords, 0, min(3, count($keyWords)));
        $likePattern = '%' . implode('%', $searchTerms) . '%';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $existing = Database::fetchOne(
            "SELECT id FROM articles WHERE title LIKE :pattern AND (published_at >= :cutoff1 OR created_at >= :cutoff2) LIMIT 1",
            ['pattern' => $likePattern, 'cutoff1' => $cutoff, 'cutoff2' => $cutoff]
        );

        return $existing !== null;
    }

    /**
     * Check if an article covering the same examination authority or exam was published recently (default 12 hours)
     * to prevent publishing multiple articles about the same board/commission on the same day.
     */
    public static function isAuthorityCoveredRecently(string $keyword, int $hours = 12): bool {
        $kwLower = mb_strtolower($keyword);
        $entities = ['upsc', 'nda', 'cds', 'ssc', 'nta', 'cbse', 'rrb', 'ibps', 'ugc', 'neet', 'jee', 'ctet', 'gate', 'cat', 'clat', 'aibe'];

        $detectedEntity = null;
        foreach ($entities as $ent) {
            if (preg_match('/\b' . preg_quote($ent, '/') . '\b/i', $kwLower)) {
                $detectedEntity = $ent;
                break;
            }
        }

        // Also check full names
        if (!$detectedEntity) {
            if (str_contains($kwLower, 'union public service commission')) $detectedEntity = 'upsc';
            elseif (str_contains($kwLower, 'staff selection commission')) $detectedEntity = 'ssc';
            elseif (str_contains($kwLower, 'national testing agency')) $detectedEntity = 'nta';
            elseif (str_contains($kwLower, 'central board of secondary education')) $detectedEntity = 'cbse';
            elseif (str_contains($kwLower, 'railway recruitment board')) $detectedEntity = 'rrb';
        }

        if (!$detectedEntity) {
            return false;
        }

        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        $pattern = '%' . $detectedEntity . '%';

        $existing = Database::fetchOne(
            "SELECT id, title FROM articles WHERE (LOWER(title) LIKE :p OR LOWER(slug) LIKE :p) AND (published_at >= :since OR created_at >= :since) LIMIT 1",
            ['p' => $pattern, 'since' => $since]
        );

        return $existing !== null;
    }

    /**
     * Check if a trend qualifies as a TRUE Official Breaking Alert
     */
    public static function isOfficialBreaking(array $trend): bool {
        $source = strtolower($trend['source'] ?? '');
        $keyword = strtolower($trend['keyword'] ?? '');
        $raw = is_array($trend['raw_payload'] ?? null) ? json_encode($trend['raw_payload']) : strtolower((string)($trend['raw_payload'] ?? ''));

        // Strictly reject evergreen catalog or guides
        if (str_contains($source, 'evergreen') || str_contains($source, 'guide') || str_contains($raw, 'evergreen')) {
            return false;
        }

        // Strictly reject troubleshooting / how-to / problem resolution queries
        $guideWords = ['not received', 'otp', 'solution', 'error fix', 'how to', 'strategy', 'tips', 'not matching', 'troubleshoot', 'step-by-step', 'what to do', 'guide'];
        foreach ($guideWords as $gw) {
            if (str_contains($keyword, $gw)) {
                return false;
            }
        }

        $officialSources = ['nta', 'nbems', 'natboard', 'upsc', 'ssc', 'cbse', 'rrb', 'ibps', 'sbi', 'indian statutory portals', 'ministry of education', 'kpsc', 'bpsc', 'uppsc', 'hpbose'];
        $isOfficial = false;
        foreach ($officialSources as $os) {
            if (str_contains($source, $os)) {
                $isOfficial = true;
                break;
            }
        }

        if (!$isOfficial) {
            return false;
        }

        $breakingKeywords = [
            'notification released', 'notification out', 'vacancies', 'apply online', 'application starts',
            'admit card out', 'admit card released', 'hall ticket', 'exam date announced', 'schedule released',
            'result declared', 'answer key released', 'merit list out', 'counselling schedule', 'datesheet released',
            'scorecard out', 'cut off marks released'
        ];

        foreach ($breakingKeywords as $bk) {
            if (str_contains($keyword, $bk)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean repetitive old approved backlog in trends queue
     */
    public static function cleanRepetitiveBacklog(): int {
        try {
            $approved = Database::fetchAll("SELECT id, keyword, category_hint, source FROM trends WHERE status = 'approved' ORDER BY id ASC");
            $seenKeywords = [];
            $cleaned = 0;

            foreach ($approved as $t) {
                $tid = (int)$t['id'];
                $kw = self::normalizeKeyword($t['keyword']);

                // If generic placeholder OR already published as article OR already seen in this batch OR recently covered
                if (self::isGenericPlaceholderKeyword($t['keyword']) || self::existsAsArticle($t['keyword']) || self::isRecentlyCovered($t['keyword'], 20) || in_array($kw, $seenKeywords, true)) {
                    self::markStatus($tid, 'rejected', ['raw_payload' => ['reason' => 'Generic placeholder or repetitive backlog cleanup']]);
                    $cleaned++;
                } else {
                    $seenKeywords[] = $kw;
                }
            }

            if ($cleaned > 0) {
                Logger::info("TrendService: Cleaned {$cleaned} repetitive topics from approved queue.");
            }
            return $cleaned;
        } catch (Throwable $e) {
            Logger::error("cleanRepetitiveBacklog error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Purge duplicate trends from database, retaining only the single latest record per normalized_hash
     */
    public static function purgeDuplicateTrends(): int {
        try {
            $deleted = (int)Database::query(
                "DELETE t1 FROM trends t1
                 INNER JOIN trends t2 
                 WHERE t1.id < t2.id 
                   AND t1.normalized_hash = t2.normalized_hash"
            );
            if ($deleted > 0) {
                Logger::info("TrendService: Purged {$deleted} duplicate trend rows from database.");
            }
            return $deleted;
        } catch (Throwable $e) {
            Logger::error("purgeDuplicateTrends error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check whether topic matches allowed categories
     */
    public static function isAllowedCategory(?string $categoryHint): bool {
        if (empty($categoryHint)) return true; // Will be analyzed by AI
        $allowed = array_map('trim', explode(',', (string)Env::get('ALLOWED_CATEGORIES', 'exam-results,admit-cards,exam-dates,government-jobs,higher-education,school-boards,scholarships,career-guides,student-technology,entrance-exams,college-updates')));

        return in_array($categoryHint, $allowed, true);
    }

    /**
     * Comprehensive qualification gate before AI analysis
     */
    public static function isQualified(array $trendData): array {
        $keyword = trim($trendData['keyword'] ?? '');
        if (mb_strlen($keyword) < 4) {
            return ['qualified' => false, 'reason' => 'Keyword too short.'];
        }

        // 1. Strict English Only Gate (Reject Devanagari / Hindi script)
        if (preg_match('/[\x{0900}-\x{097F}]/u', $keyword) || preg_match('/[\x{0900}-\x{097F}]/u', $trendData['snippet'] ?? '')) {
            return ['qualified' => false, 'reason' => 'Non-English (Devanagari/Hindi) text rejected. Sarkari.online is strictly 100% English.'];
        }

        // 2. Strict Education & Recruitment Relevance Gate
        if (!self::isEducationRelevant($keyword, $trendData['snippet'] ?? '')) {
            return ['qualified' => false, 'reason' => 'Topic rejected: Not relevant to student exams, results, recruitments, or scholarships.'];
        }

        // 2b. Reject generic synthetic placeholder topics
        if (self::isGenericPlaceholderKeyword($keyword)) {
            return ['qualified' => false, 'reason' => 'Topic rejected: Generic synthetic placeholder without specific exam/notification event.'];
        }

        // 2c. Strict Student Search-Action Intent Gate
        $intentCheck = self::isHighStudentActionIntent($keyword);
        if (!$intentCheck['pass']) {
            return ['qualified' => false, 'reason' => 'Topic rejected: ' . $intentCheck['reason']];
        }

        // 3. Blacklist: Political figures, entertainment, sports, crime, and non-educational noise
        $blacklist = [
            'mamata banerjee', 'rahul gandhi', 'narendra modi', 'arvind kejriwal', 'amit shah', 'yogi adityanath',
            'bjp', 'congress', 'tmc', 'aap', 'election', 'minister', 'parliament', 'mla', 'mp speech',
            'cricket', 'ipl', 'bcci', 'match', 'scorecard today live', 'wicket', 'cinema', 'movie', 'box office',
            'trailer', 'song', 'actor', 'actress', 'bollywood', 'hollywood', 'murder', 'accident', 'arrested'
        ];
        $lowerKw = mb_strtolower($keyword);
        foreach ($blacklist as $bl) {
            if (str_contains($lowerKw, $bl)) {
                return ['qualified' => false, 'reason' => "Topic rejected: Matches blacklisted non-education entity '{$bl}'."];
            }
        }

        if (self::existsAsTrend($keyword)) {
            return ['qualified' => false, 'reason' => 'Duplicate trend already recorded recently.'];
        }

        if (self::existsAsArticle($keyword)) {
            return ['qualified' => false, 'reason' => 'Similar article already exists in publication library.'];
        }

        $hint = $trendData['category_hint'] ?? null;
        if (!empty($hint) && !self::isAllowedCategory($hint)) {
            return ['qualified' => false, 'reason' => "Category '{$hint}' is not in allowed categories list."];
        }

        return ['qualified' => true, 'reason' => 'Passed qualification checks.'];
    }

    /**
     * Check if a topic has high student search-action intent and filter out administrative noise
     */
    public static function isHighStudentActionIntent(string $keyword): array {
        $kwLower = mb_strtolower(trim($keyword));

        // 1. Must be a meaningful query (at least 10 characters and 2+ words)
        if (mb_strlen($kwLower) < 10 || count(explode(' ', $kwLower)) < 2) {
            return ['pass' => false, 'reason' => 'Too short or single-word query lacking actionable student context.'];
        }

        // 2. Reject Administrative, Legal, Political, Grievances, and Bureaucratic Noise
        $noisePhrases = [
            'grievance', 'grievance portal', 'grievance cell', 'redressal portal', 'complaint portal',
            'feedback portal', 'feedback form', 'helpdesk', 'helpline', 'toll free', 'toll-free',
            'sc told', 'supreme court', 'court told', 'high court', 'plea filed', 'hearing', 'pil',
            'stays', 'stay order', 'cm says', 'minister says', 'says cm', 'says minister',
            'centre tells', 'centre working on', 'three-language', 'language policy', 'attendance system',
            'attendance rule', 'dress code', 'uniform rule', 'mobile phone ban', 'school bag policy',
            'paper leak shadow', 'paper leak probe', 'cbi probe', 'cbi registers', 'looks for spaces',
            'candidates protest', 'protest against', 'technical glitches', 'technical glitch',
            'committee forms', 'committee formed', 'panel formed', 'examines grievances', 'inquiry committee',
            'probe ordered', 'fir registered', 'arrested', 'walk-in interview for', 'contractual vacancy',
            'guest faculty interview'
        ];

        foreach ($noisePhrases as $noise) {
            if (str_contains($kwLower, $noise)) {
                return ['pass' => false, 'reason' => "Administrative noise / non-actionable policy gossip detected ('{$noise}')."];
            }
        }

        // 3. Reject outdated historical cycles (2020-2025) unless referencing the current 2026/2027 cycle
        if (preg_match('/\b(202[0-5])\b/', $kwLower) && !preg_match('/\b(202[6-9]|20[3-9]\d)\b/', $kwLower)) {
            return ['pass' => false, 'reason' => 'Outdated historical exam cycle detected (prior to 2026).'];
        }

        // 4. Must contain at least one High-Search-Intent Milestone Token
        $intentTokens = [
            'admit card', 'hall ticket', 'city slip', 'city intimation', 'call letter', 'shift timing',
            'apply online', 'application form', 'application', 'applications', 'apply', 'registration', 'eligibility',
            'vacancy', 'vacancies', 'posts', 'recruitment', 'notification', 'last date', 'extended till', 'otr', 'form correction',
            'answer key', 'omr sheet', 'response sheet', 'objection window', 'challenge',
            'result declared', 'result announced', 'result', 'scorecard', 'rank card', 'merit list', 'cutoff', 'cut off',
            'exam date', 'exam schedule', 'schedule', 'datesheet', 'timetable', 'counselling', 'seat allotment',
            'scholarship', 'fellowship', 'stipend', 'syllabus', 'exam pattern'
        ];

        $hasIntent = false;
        foreach ($intentTokens as $token) {
            if (str_contains($kwLower, $token)) {
                $hasIntent = true;
                break;
            }
        }

        if (!$hasIntent) {
            return ['pass' => false, 'reason' => 'Topic lacks a high-volume student action milestone (admit card, result, apply, answer key, cutoff, dates, scholarship).'];
        }

        return ['pass' => true, 'reason' => 'High student action intent verified.'];
    }

    /**
     * Check if a keyword is strictly relevant to Indian education, examinations, and recruitment
     */
    public static function isEducationRelevant(string $keyword, string $snippet = ''): bool {
        $kwLower = mb_strtolower($keyword);
        $coreExamTerms = [
            'exam', 'recruitment', 'vacancy', 'vacancies', 'admit card', 'result', 'cutoff', 'cut off', 'merit list',
            'counselling', 'counseling', 'scholarship', 'fellowship', 'syllabus', 'answer key', 'eligibility',
            'notification', 'datesheet', 'date sheet', 'timetable', 'hall ticket', 'application', 'registration',
            'ssc', 'upsc', 'nta', 'neet', 'jee', 'gate', 'ctet', 'cbse', 'ibps', 'rrb', 'railway',
            'police', 'bsf', 'crpf', 'itbp', 'cisf', 'navy', 'army', 'air force', 'ugc', 'cuet',
            'digilocker', 'apaar', 'abc id', 'pmsss', 'nsp', 'aibe', 'clat', 'cat', 'aiims',
            'degree', 'admission', 'allotment', 'seat', 'selection process', 'paper 1', 'paper 2', 'tier 1', 'tier 2',
            'post matric', 'pre matric', 'scholarships', 'b.ed', 'd.el.ed', 'tet', 'uptet', 'htet', 'reet',
            'direct recruitment', 'bharti', 'sarkari naukri', 'constable', 'inspector', 'assistant professor',
            'bci enrollment', 'phd funding', 'stipend'
        ];

        // The KEYWORD ITSELF must contain an exam/job intent term
        foreach ($coreExamTerms as $kw) {
            if (str_contains($kwLower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine search-volume tier for an Indian education topic:
     * Tier 1 (Massive national volume 100,000 to 2,000,000+ monthly): 1
     * Tier 2 (State PSCs, High Courts, PSUs): 2
     * Tier 3 (General): 3
     */
    public static function getSearchVolumeTier(string $keyword): int {
        $kwLower = mb_strtolower($keyword);
        $tier1Entities = [
            'rrb', 'railway', 'ntpc', 'group d', 'alp', 'technician',
            'ssc', 'ssc cgl', 'ssc gd', 'ssc chsl', 'ssc mts', 'ssc je', 'ssc cpo', 'ssc constable',
            'upsc', 'upsc otr', 'otr', 'one-time registration',
            'sbi po', 'sbi clerk', 'ibps po', 'ibps clerk', 'ibps rrb',
            'neet', 'jee main', 'jee advanced', 'cuet', 'ctet', 'gate 202', 'ugc net',
            'up police', 'bpsc teacher', 'bpsc tre', 'bihar police', 'rajasthan reet', 'mp police',
            'apaar id', 'abc id', 'digilocker', 'national scholarship portal', 'nsp'
        ];
        foreach ($tier1Entities as $entity) {
            if (str_contains($kwLower, $entity)) {
                return 1;
            }
        }
        return 2;
    }

    /**
     * Fetch from all registered adapters, filter duplicates, and record new detected trends
     */
    public function fetchAllSources(int $limitPerSource = 10): array {
        $recorded = [];
        $minScore = (int)Env::get('MIN_TREND_SCORE', 75);

        foreach ($this->adapters as $adapter) {
            try {
                Logger::info("Executing trend fetch from adapter: " . $adapter->getSourceName());
                $candidates = $adapter->fetch($limitPerSource);

                foreach ($candidates as $item) {
                    $qualification = self::isQualified($item);
                    if (!$qualification['qualified']) {
                        Logger::info("Trend disqualified: {$item['keyword']} ({$qualification['reason']})");
                        continue;
                    }

                    $trendId = self::recordTrend($item);
                    if ($trendId) {
                        $item['id'] = $trendId;
                        $recorded[] = $item;

                        // Tier 1 Mega National Searches get elevated priority score
                        $tier = self::getSearchVolumeTier($item['keyword']);
                        $isBreaking = self::isOfficialBreaking($item);

                        if ($tier === 1 && $isBreaking) {
                            self::markStatus($trendId, 'approved', ['trend_score' => 99]);
                            Logger::info("Tier 1 Official Breaking Notice prioritized (score 99): '{$item['keyword']}'");
                        } elseif ($tier === 1) {
                            $initialScore = max(97, (int)($item['trend_score'] ?? 97));
                            self::markStatus($trendId, 'approved', ['trend_score' => $initialScore]);
                            Logger::info("Tier 1 Mega National Topic prioritized in approved queue (score {$initialScore}): '{$item['keyword']}'");
                        } elseif ($isBreaking) {
                            self::markStatus($trendId, 'approved', ['trend_score' => 95]);
                            Logger::info("Official Breaking Notice prioritized in approved queue: '{$item['keyword']}' (Waiting for next scheduled slot)");
                        }
                    }
                }
            } catch (Throwable $e) {
                Logger::error("Adapter {$adapter->getSourceId()} failed: " . $e->getMessage());
            }
        }

        // Auto-purge any duplicate historical trends in database
        self::purgeDuplicateTrends();

        return $recorded;
    }

    /**
     * Record a new trend in the database
     */
    public static function recordTrend(array $data): ?int {
        $keyword = Sanitizer::string($data['keyword'] ?? '');
        if (empty($keyword)) return null;

        $hash = self::normalizedHash($keyword);
        $score = (int)($data['trend_score'] ?? 50);

        // Lookup category ID if hint matches taxonomy
        $categoryId = null;
        if (!empty($data['category_hint'])) {
            $cat = CategoryService::getBySlug($data['category_hint']);
            if ($cat) {
                $categoryId = (int)$cat['id'];
            }
        }

        $payload = !empty($data['raw_payload']) ? (is_array($data['raw_payload']) ? json_encode($data['raw_payload']) : $data['raw_payload']) : null;

        try {
            $id = (int)Database::insert('trends', [
                'keyword' => $keyword,
                'normalized_hash' => $hash,
                'source' => Sanitizer::string($data['source'] ?? 'generic'),
                'url' => filter_var($data['url'] ?? '', FILTER_VALIDATE_URL) ?: null,
                'trend_score' => $score,
                'raw_payload' => $payload,
                'category_id' => $categoryId,
                'category_hint' => Sanitizer::string($data['category_hint'] ?? ''),
                'status' => 'detected',
                'detected_at' => $data['detected_at'] ?? date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Logger::info("Trend recorded successfully", ['id' => $id, 'keyword' => $keyword, 'source' => $data['source'] ?? '']);
            return $id;
        } catch (Throwable $e) {
            Logger::error("Failed to record trend: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch pending trends for AI analysis with atomic locking
     */
    public static function getPendingForAnalysis(int $limit = 10): array {
        // Auto-heal stuck analyzing trends (e.g. from timeouts or network disconnects)
        try {
            Database::query("UPDATE trends SET status = 'detected' WHERE status = 'analyzing' AND analyzed_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        } catch (Throwable $e) {}

        $sql = "SELECT t.*, c.name AS category_name, c.slug AS category_slug
                FROM trends t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.status = 'detected'
                ORDER BY t.trend_score DESC, t.id ASC
                LIMIT " . (int)$limit;

        return Database::fetchAll($sql);
    }

    /**
     * Update trend status and optional payload notes
     */
    public static function markStatus(int $id, string $status, array $extra = []): bool {
        $allowedStatuses = ['detected', 'analyzing', 'approved', 'rejected', 'generated', 'published', 'failed'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        $updateData = ['status' => $status];

        if ($status === 'analyzing') {
            $updateData['analyzed_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'approved' || $status === 'rejected') {
            $updateData['analyzed_at'] = date('Y-m-d H:i:s');
            if (isset($extra['category_id'])) $updateData['category_id'] = (int)$extra['category_id'];
            if (isset($extra['trend_score'])) $updateData['trend_score'] = (int)$extra['trend_score'];
            if (isset($extra['raw_payload'])) {
                $updateData['raw_payload'] = is_array($extra['raw_payload']) ? json_encode($extra['raw_payload']) : $extra['raw_payload'];
            }
        } elseif ($status === 'generated' || $status === 'published') {
            $updateData['processed_at'] = date('Y-m-d H:i:s');
        }

        Database::update('trends', $updateData, 'id = :id', ['id' => $id]);
        Logger::info("Trend #{$id} status updated to {$status}");

        return true;
    }

    /**
     * Get recent trends for admin dashboard
     */
    public static function getRecent(int $limit = 10, ?string $status = null): array {
        $sql = "SELECT t.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
                FROM trends t
                LEFT JOIN categories c ON t.category_id = c.id";

        $params = [];
        if (!empty($status)) {
            $sql .= " WHERE t.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY t.detected_at DESC, t.id DESC LIMIT " . (int)$limit;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get trend metrics summary
     */
    public static function getStats(): array {
        return [
            'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends"),
            'detected' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'detected'"),
            'analyzing' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'analyzing'"),
            'approved' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'approved'"),
            'rejected' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'rejected'"),
            'generated' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'generated'"),
            'published' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'published'"),
            'failed' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends WHERE status = 'failed'")
        ];
    }
}
