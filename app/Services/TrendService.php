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
use Exception;
use Throwable;

class TrendService {

    /** @var TrendSourceInterface[] */
    private array $adapters = [];

    public function __construct(array $adapters = []) {
        if (!empty($adapters)) {
            $this->adapters = $adapters;
        } else {
            // Default built-in source adapters: Live Official Portals, Exam RSS Feeds, and Google Trends
            $this->adapters = [
                new OfficialSourcesAdapter(),
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
     * Check if trend already exists in trends table
     */
    public static function existsAsTrend(string $keyword, int $days = 7): bool {
        $hash = self::normalizedHash($keyword);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $existing = Database::fetchOne(
            "SELECT id FROM trends WHERE normalized_hash = :hash AND status IN ('detected', 'analyzing', 'approved', 'generated', 'published') AND detected_at >= :since LIMIT 1",
            ['hash' => $hash, 'since' => $since]
        );

        return $existing !== null;
    }

    /**
     * Check if similar article already exists in published articles
     */
    public static function existsAsArticle(string $keyword): bool {
        $normalized = self::normalizeKeyword($keyword);
        $words = explode(' ', $normalized);
        $keyWords = array_filter($words, fn($w) => strlen($w) > 3);

        if (empty($keyWords)) {
            return false;
        }

        // Check if top 2 keywords match existing article title
        $searchTerms = array_slice($keyWords, 0, 3);
        $likePattern = '%' . implode('%', $searchTerms) . '%';

        $existing = Database::fetchOne(
            "SELECT id FROM articles WHERE title LIKE :pattern LIMIT 1",
            ['pattern' => $likePattern]
        );

        return $existing !== null;
    }

    /**
     * Check if a topic was published recently (e.g. within 30 days) to prevent spam repetition
     */
    public static function isRecentlyCovered(string $keyword, int $days = 30): bool {
        $normalized = self::normalizeKeyword($keyword);
        $words = explode(' ', $normalized);
        $keyWords = array_values(array_filter($words, fn($w) => strlen($w) > 3));

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

                        // 🔴 INSTANT AUTO-PUBLISH FOR OFFICIAL BREAKING NOTICES:
                        // Real breaking news never sits in the 'detected' queue — it generates and publishes immediately!
                        if (self::isOfficialBreaking($item)) {
                            Logger::info("🔴 Official Breaking Notice Ingested: '{$item['keyword']}'. Triggering INSTANT fast-track publishing!");
                            try {
                                self::markStatus($trendId, 'approved', ['trend_score' => 99]);
                                $pipeline = new PipelineService();
                                $gen = $pipeline->generateFromTrend($trendId);
                                if (!empty($gen['success']) && !empty($gen['article_id'])) {
                                    $pubService = new PublishingService();
                                    $pubService->publish((int)$gen['article_id']);
                                    Logger::info("🔴 Official Breaking Notice published live immediately as Article #{$gen['article_id']}");
                                }
                            } catch (Throwable $e) {
                                Logger::error("Failed to instant publish breaking trend #{$trendId}: " . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                Logger::error("Adapter {$adapter->getSourceId()} failed: " . $e->getMessage());
            }
        }

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
