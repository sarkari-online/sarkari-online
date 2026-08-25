<?php
/**
 * Sarkari.online - IndexNow Real-Time Protocol Service
 * Programmatically notifies Microsoft Bing, Yahoo, Yandex, Naver, and Seznam
 * whenever an article is published or updated.
 * Zero third-party dependencies; uses native cURL HTTP/2 with fallback endpoints.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use Exception;
use Throwable;

class IndexNowService {

    public const INDEXNOW_KEY = 'd8f4b23a9e714652a831e509cbf27a14';
    public const PRIMARY_ENDPOINT = 'https://api.indexnow.org/indexnow';
    public const BING_ENDPOINT = 'https://www.bing.com/indexnow';
    public const YANDEX_ENDPOINT = 'https://yandex.com/indexnow';

    /**
     * Check if IndexNow key file exists and is accessible
     */
    public static function isConfigured(): bool {
        $keyFile = dirname(__DIR__, 2) . '/' . self::INDEXNOW_KEY . '.txt';
        return file_exists($keyFile) && trim(file_get_contents($keyFile)) === self::INDEXNOW_KEY;
    }

    /**
     * Get the host name (e.g., sarkari.online)
     */
    public static function getHost(): string {
        $parsed = parse_url(SITE_URL, PHP_URL_HOST);
        if (!$parsed || $parsed === 'localhost' || $parsed === '127.0.0.1') {
            return 'sarkari.online';
        }
        return $parsed;
    }

    /**
     * Get the key location URL
     */
    public static function getKeyLocation(): string {
        $host = self::getHost();
        return 'https://' . $host . '/' . self::INDEXNOW_KEY . '.txt';
    }

    /**
     * Submit a single URL to IndexNow (Bing, Yahoo, Yandex)
     */
    public static function pingUrl(string $url): array {
        return self::pingBatch([$url]);
    }

    /**
     * Submit a published article by Article ID
     */
    public static function pingArticle(int $articleId): array {
        $article = Database::fetchOne("SELECT id, slug, status FROM articles WHERE id = :id LIMIT 1", ['id' => $articleId]);
        if (!$article) {
            return ['success' => false, 'error' => "Article #{$articleId} not found in database."];
        }

        if ($article['status'] !== 'published') {
            return ['success' => false, 'error' => "Article #{$articleId} is in '{$article['status']}' status (must be published)."];
        }

        $fullUrl = url('article/' . $article['slug'] . '/');
        // Ensure production domain for external search engine notification
        if (str_contains($fullUrl, 'localhost')) {
            $fullUrl = 'https://sarkari.online/article/' . $article['slug'] . '/';
        }
        return self::pingUrl($fullUrl);
    }

    /**
     * Submit a batch of URLs to IndexNow
     */
    public static function pingBatch(array $urls): array {
        if (empty($urls)) {
            return ['success' => false, 'error' => 'URL list cannot be empty.'];
        }

        $host = self::getHost();
        $key = self::INDEXNOW_KEY;
        $keyLocation = self::getKeyLocation();

        // Ensure all URLs are absolute canonical production URLs
        $normalizedUrls = [];
        foreach ($urls as $u) {
            if (str_contains($u, 'localhost')) {
                $u = str_replace(['http://localhost:8888/automation/', 'http://localhost/automation/'], 'https://sarkari.online/', $u);
            }
            $normalizedUrls[] = $u;
        }

        // Prepare JSON payload according to IndexNow RFC
        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => array_values(array_unique($normalizedUrls))
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Try Primary IndexNow Endpoint (Broadcasts to all participating search engines)
        $result = self::sendRequest(self::PRIMARY_ENDPOINT, $jsonPayload);

        // If primary fails, fallback to direct Bing endpoint
        if (!$result['success']) {
            Logger::warning("Primary IndexNow endpoint failed. Retrying via Bing endpoint: " . ($result['error'] ?? ''));
            $result = self::sendRequest(self::BING_ENDPOINT, $jsonPayload);
        }

        if ($result['success']) {
            Logger::info("IndexNow: Successfully notified Bing/Yahoo/Yandex for " . count($normalizedUrls) . " URL(s).", [
                'urls' => $normalizedUrls,
                'status_code' => $result['status_code']
            ]);
        } else {
            Logger::error("IndexNow submission failed: " . ($result['error'] ?? 'Unknown error'), [
                'urls' => $normalizedUrls
            ]);
        }

        return $result;
    }

    /**
     * Send HTTP POST request via cURL
     */
    private static function sendRequest(string $endpoint, string $jsonPayload): array {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonPayload),
                'User-Agent: Sarkari.online-IndexNow-Bot/1.0'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if (!empty($error)) {
            return [
                'success' => false,
                'status_code' => $httpCode,
                'error' => "cURL Error: {$error}"
            ];
        }

        // IndexNow returns 200 OK or 202 Accepted on success
        if ($httpCode === 200 || $httpCode === 202) {
            return [
                'success' => true,
                'status_code' => $httpCode,
                'message' => 'IndexNow accepted URL notification successfully (Bing, Yahoo, Yandex notified).',
                'raw_response' => $response
            ];
        }

        return [
            'success' => false,
            'status_code' => $httpCode,
            'error' => "IndexNow returned HTTP {$httpCode}: " . ($response ?: 'No response body')
        ];
    }
}
