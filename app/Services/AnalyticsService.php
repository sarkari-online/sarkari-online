<?php
/**
 * Sarkari.online - Real-Time Autonomous Analytics & Traffic Service
 * 100% In-House, Privacy-Friendly, Zero Third-Party Tracker.
 * Accurately tracks unique daily visitors, pageviews, referrers, and device breakdown.
 * Automatically filters out owner IP (38.254.176.x) and authenticated admin sessions.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\Logger;
use Throwable;

class AnalyticsService {

    // Configured excluded IP ranges (Owner Wi-Fi & Mobile networks, Docker subnets, Server loopbacks)
    public const EXCLUDED_IP_PREFIXES = [
        '38.254.176.',  // Owner's Wi-Fi network subnet
        '152.58.87.',   // Owner's Mobile network subnet
        '152.58.',      // Owner's Mobile network IP pool
        '127.0.0.1',
        '::1',
        '192.168.',
        '172.16.',
        '172.17.',
        '172.18.',
        '172.19.',
        '172.20.',
        '10.'
    ];

    /**
     * Get Client Real IP Address with Cloudflare/Proxy Support
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', $_SERVER[$header]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if given IP address is excluded from analytics
     */
    public static function isExcludedIp(string $ip): bool {
        foreach (self::EXCLUDED_IP_PREFIXES as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Track a public page view
     */
    public static function track(?int $articleId = null, ?string $pageTitle = null, ?string $categorySlug = null): void {
        try {
            // 1. Skip if authenticated as Admin
            if (Auth::check()) {
                return;
            }

            $ip = self::getClientIp();

            // 2. Skip if from owner's Wi-Fi network
            if (self::isExcludedIp($ip)) {
                return;
            }

            // 3. Skip internal admin / cron / asset requests
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if (str_starts_with($uri, '/admin') || str_starts_with($uri, '/assets') || str_starts_with($uri, '/uploads')) {
                return;
            }

            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $isBot = self::detectBot($userAgent);

            // 4. Generate daily unique session hash per visitor (IP + UA + Date)
            $today = date('Y-m-d');
            $sessionHash = hash('sha256', $ip . '|' . $userAgent . '|' . $today);

            // 5. Parse Referrer
            $rawReferrer = $_SERVER['HTTP_REFERER'] ?? '';
            $refType = self::classifyReferrer($rawReferrer);

            // 6. Detect Device & Browser
            $deviceType = self::detectDevice($userAgent);
            $browser = self::detectBrowser($userAgent);
            $os = self::detectOS($userAgent);

            $pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sarkari.online') . $uri;

            Database::insert('page_views', [
                'session_hash'   => $sessionHash,
                'page_url'       => mb_substr($pageUrl, 0, 255),
                'page_title'     => mb_substr($pageTitle ?? 'Sarkari.online', 0, 255),
                'article_id'     => $articleId,
                'category_slug'  => $categorySlug,
                'referrer'       => mb_substr($rawReferrer, 0, 500),
                'referrer_type'  => $refType,
                'device_type'    => $deviceType,
                'browser'        => $browser,
                'os'             => $os,
                'ip_address'     => $ip,
                'is_bot'         => $isBot ? 1 : 0,
                'viewed_at'      => date('Y-m-d H:i:s')
            ]);

        } catch (Throwable $e) {
            // Silently log error without interrupting user page load
            Logger::warning("Analytics tracking error: " . $e->getMessage());
        }
    }

    /**
     * Get KPI Summary Dashboard Data
     */
    public static function getDashboardSummary(): array {
        self::ensureTableExists();

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Today's Unique Human Visitors
        $todayUnique = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE DATE(viewed_at) = :today AND is_bot = 0",
            ['today' => $today]
        );

        // Yesterday's Unique Human Visitors
        $yesterdayUnique = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE DATE(viewed_at) = :yesterday AND is_bot = 0",
            ['yesterday' => $yesterday]
        );

        // Today's Total Page Views
        $todayViews = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = :today AND is_bot = 0",
            ['today' => $today]
        );

        // Yesterday's Total Page Views
        $yesterdayViews = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = :yesterday AND is_bot = 0",
            ['yesterday' => $yesterday]
        );

        // Live Online Users (Active in last 15 minutes)
        $fifteenMinsAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $liveUsers = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE viewed_at >= :since AND is_bot = 0",
            ['since' => $fifteenMinsAgo]
        );

        // Total All-Time Unique Visitors
        $totalUnique = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE is_bot = 0"
        );

        return [
            'today_unique'       => $todayUnique,
            'yesterday_unique'   => $yesterdayUnique,
            'today_views'        => $todayViews,
            'yesterday_views'    => $yesterdayViews,
            'live_now'           => $liveUsers,
            'total_unique'       => $totalUnique,
            'growth_pct'         => $yesterdayUnique > 0 ? round((($todayUnique - $yesterdayUnique) / $yesterdayUnique) * 100, 1) : 0
        ];
    }

    /**
     * Get Daily Traffic Trend for the past N days
     */
    public static function getDailyTrend(int $days = 14): array {
        self::ensureTableExists();

        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $sql = "SELECT DATE(viewed_at) as date, 
                       COUNT(DISTINCT session_hash) as unique_visitors, 
                       COUNT(*) as page_views 
                FROM page_views 
                WHERE viewed_at >= :start AND is_bot = 0 
                GROUP BY DATE(viewed_at) 
                ORDER BY date ASC";

        $rows = Database::fetchAll($sql, ['start' => $startDate . ' 00:00:00']);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['date']] = $r;
        }

        // Fill missing days with 0
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = [
                'date'            => $d,
                'label'           => date('d M', strtotime($d)),
                'unique_visitors' => (int)($map[$d]['unique_visitors'] ?? 0),
                'page_views'      => (int)($map[$d]['page_views'] ?? 0)
            ];
        }

        return $result;
    }

    /**
     * Get Top Visited Articles
     */
    public static function getTopArticles(int $limit = 10, string $range = 'today'): array {
        self::ensureTableExists();

        $where = "is_bot = 0 AND article_id IS NOT NULL";
        $params = [];

        if ($range === 'today') {
            $where .= " AND DATE(viewed_at) = :d";
            $params['d'] = date('Y-m-d');
        } elseif ($range === '7days') {
            $where .= " AND viewed_at >= :d";
            $params['d'] = date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        $sql = "SELECT p.article_id, p.page_title, a.slug, a.category_id, c.name as category_name,
                       COUNT(DISTINCT p.session_hash) as unique_readers,
                       COUNT(*) as total_views
                FROM page_views p
                LEFT JOIN articles a ON p.article_id = a.id
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE {$where}
                GROUP BY p.article_id, p.page_title, a.slug, a.category_id, c.name
                ORDER BY total_views DESC
                LIMIT " . (int)$limit;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get Real-Time Recent Visitor Activity Logs (with IP Address, Device, Page, Time)
     */
    public static function getRecentVisitorLogs(int $limit = 30): array {
        self::ensureTableExists();

        $sql = "SELECT p.id, p.ip_address, p.page_url, p.page_title, p.referrer_type, p.referrer,
                       p.device_type, p.browser, p.os, p.viewed_at, a.slug
                FROM page_views p
                LEFT JOIN articles a ON p.article_id = a.id
                WHERE p.is_bot = 0
                ORDER BY p.viewed_at DESC
                LIMIT " . (int)$limit;

        return Database::fetchAll($sql);
    }

    /**
     * Get Traffic Sources Breakdown
     */
    public static function getTrafficSources(string $range = '7days'): array {
        self::ensureTableExists();

        $where = "is_bot = 0";
        $params = [];

        if ($range === 'today') {
            $where .= " AND DATE(viewed_at) = :d";
            $params['d'] = date('Y-m-d');
        } else {
            $where .= " AND viewed_at >= :d";
            $params['d'] = date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        $sql = "SELECT referrer_type, COUNT(*) as count 
                FROM page_views 
                WHERE {$where} 
                GROUP BY referrer_type 
                ORDER BY count DESC";

        $rows = Database::fetchAll($sql, $params);
        $total = array_sum(array_column($rows, 'count')) ?: 1;

        $results = [];
        foreach ($rows as $r) {
            $results[] = [
                'type'       => ucfirst($r['referrer_type']),
                'count'      => (int)$r['count'],
                'percentage' => round(($r['count'] / $total) * 100, 1)
            ];
        }

        return $results;
    }

    /**
     * Get Device Breakdown (Mobile vs Desktop)
     */
    public static function getDeviceBreakdown(string $range = '7days'): array {
        self::ensureTableExists();

        $where = "is_bot = 0";
        $params = [];

        if ($range === 'today') {
            $where .= " AND DATE(viewed_at) = :d";
            $params['d'] = date('Y-m-d');
        } else {
            $where .= " AND viewed_at >= :d";
            $params['d'] = date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        $sql = "SELECT device_type, COUNT(*) as count 
                FROM page_views 
                WHERE {$where} 
                GROUP BY device_type 
                ORDER BY count DESC";

        $rows = Database::fetchAll($sql, $params);
        $total = array_sum(array_column($rows, 'count')) ?: 1;

        $results = [];
        foreach ($rows as $r) {
            $results[] = [
                'device'     => ucfirst($r['device_type']),
                'count'      => (int)$r['count'],
                'percentage' => round(($r['count'] / $total) * 100, 1)
            ];
        }

        return $results;
    }

    /**
     * Helper: Classify Referrer Type
     */
    private static function classifyReferrer(string $ref): string {
        if (empty($ref)) return 'direct';
        $host = strtolower(parse_url($ref, PHP_URL_HOST) ?? '');

        if (str_contains($host, 'google.')) return 'google';
        if (str_contains($host, 'bing.')) return 'bing';
        if (str_contains($host, 'yahoo.')) return 'yahoo';
        if (str_contains($host, 't.co') || str_contains($host, 'twitter.') || str_contains($host, 'x.com')) return 'twitter';
        if (str_contains($host, 'facebook.') || str_contains($host, 'fb.me')) return 'facebook';
        if (str_contains($host, 'pinterest.')) return 'pinterest';
        if (str_contains($host, 't.me') || str_contains($host, 'telegram.')) return 'telegram';
        if (str_contains($host, 'quora.')) return 'quora';
        if (str_contains($host, 'whatsapp.')) return 'whatsapp';
        if (str_contains($host, 'linkedin.')) return 'linkedin';
        if (str_contains($host, 'sarkari.online') || str_contains($host, 'localhost')) return 'internal';

        return 'other';
    }

    /**
     * Helper: Detect Device Type
     */
    private static function detectDevice(string $ua): string {
        $ua = strtolower($ua);
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'mobile';
        return 'desktop';
    }

    /**
     * Helper: Detect Browser
     */
    private static function detectBrowser(string $ua): string {
        $ua = strtolower($ua);
        if (str_contains($ua, 'edg')) return 'Edge';
        if (str_contains($ua, 'chrome')) return 'Chrome';
        if (str_contains($ua, 'safari')) return 'Safari';
        if (str_contains($ua, 'firefox')) return 'Firefox';
        if (str_contains($ua, 'opera') || str_contains($ua, 'opr')) return 'Opera';
        return 'Other';
    }

    /**
     * Helper: Detect OS
     */
    private static function detectOS(string $ua): string {
        $ua = strtolower($ua);
        if (str_contains($ua, 'android')) return 'Android';
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) return 'iOS';
        if (str_contains($ua, 'windows')) return 'Windows';
        if (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) return 'macOS';
        if (str_contains($ua, 'linux')) return 'Linux';
        return 'Other';
    }

    /**
     * Helper: Detect Bot / Crawler
     */
    private static function detectBot(string $ua): bool {
        $ua = strtolower($ua);
        $botSignatures = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'googlebot', 'bingbot',
            'yandex', 'baidu', 'duckduckbot', 'curl', 'wget', 'python', 'headlesschrome',
            'lighthouse', 'semrush', 'ahrefs', 'petalbot'
        ];

        foreach ($botSignatures as $sig) {
            if (str_contains($ua, $sig)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure Table Exists
     */
    public static function ensureTableExists(): void {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `page_views` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `session_hash` VARCHAR(64) NOT NULL,
              `page_url` VARCHAR(255) NOT NULL,
              `page_title` VARCHAR(255) NULL,
              `article_id` INT NULL,
              `category_slug` VARCHAR(100) NULL,
              `referrer` VARCHAR(500) NULL,
              `referrer_type` VARCHAR(50) NOT NULL DEFAULT 'direct',
              `device_type` VARCHAR(30) NOT NULL DEFAULT 'desktop',
              `browser` VARCHAR(50) NULL,
              `os` VARCHAR(50) NULL,
              `ip_address` VARCHAR(45) NOT NULL,
              `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
              `viewed_at` DATETIME NOT NULL,
              INDEX `idx_session_date` (`session_hash`, `viewed_at`),
              INDEX `idx_viewed_at` (`viewed_at`),
              INDEX `idx_article_id` (`article_id`),
              INDEX `idx_ip` (`ip_address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            Database::query($sql);
        } catch (Throwable $e) {
            // Table already exists
        }
    }
}
