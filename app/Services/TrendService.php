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
            // Default built-in source adapters
            $this->adapters = [
                new GoogleTrendsAdapter(),
                new RssFeedAdapter(),
                new OfficialSourcesAdapter(),
                new EvergreenTopicsAdapter()
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
     * Check if trend already exists in trends table
     */
    public static function existsAsTrend(string $keyword, int $days = 7): bool {
        $hash = self::normalizedHash($keyword);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $existing = Database::fetchOne(
            "SELECT id FROM trends WHERE normalized_hash = :hash AND detected_at >= :since LIMIT 1",
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
     * Check whether topic matches allowed categories
     */
    public static function isAllowedCategory(?string $categoryHint): bool {
        if (empty($categoryHint)) return true; // Will be analyzed by AI
        $allowed = array_map('trim', explode(',', (string)Env::get('ALLOWED_CATEGORIES', 'exam-results,admit-cards,exam-dates,government-jobs,higher-education,school-boards,scholarships,career-guides,student-technology')));
        return in_array($categoryHint, $allowed, true);
    }

    /**
     * Comprehensive qualification gate before AI analysis
     */
    public static function isQualified(array $trendData): array {
        $keyword = $trendData['keyword'] ?? '';
        if (mb_strlen(trim($keyword)) < 4) {
            return ['qualified' => false, 'reason' => 'Keyword too short.'];
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
