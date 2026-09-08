<?php
/**
 * Sarkari.online - Real-Time Search Engine Indexing Service
 * 
 * Integrates:
 * 1. Google Indexing API (https://indexing.googleapis.com/v3/urlNotifications:publish)
 *    - HARD-GATED: Only invoked for verified active 'recruitment' articles with JobPosting schema.
 *    - Strictly blocks generic blog posts, admit cards, or result updates per Google Terms of Service.
 * 2. IndexNow API (Bing, Yandex, Seznam, Naver)
 *    - Dispatches instant crawl requests across modern multi-engine search indexes.
 * 
 * Features automatic quota guarding and service-account key auto-discovery.
 */

namespace App\Services;

use App\Helpers\Logger;
use App\Helpers\Env;
use Throwable;

class IndexingService {

    private const GOOGLE_INDEXING_ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    private const INDEXNOW_ENDPOINT        = 'https://api.indexnow.org/indexnow';
    private const MAX_GOOGLE_DAILY_QUOTA   = 180; // Cap safely below Google's 200/day limit

    /**
     * Dispatch indexing notification upon article publication or major update
     *
     * @param array $article Article database record
     * @param array|null $jobPostingSchema Validated JobPosting schema (if generated)
     * @return array Results summary of ping attempts
     */
    public static function notifyPublished(array $article, ?array $jobPostingSchema = null): array {
        $results = [
            'google'   => ['attempted' => false, 'success' => false, 'message' => ''],
            'indexnow' => ['attempted' => false, 'success' => false, 'message' => '']
        ];

        $url = !empty($article['canonical_url']) 
            ? $article['canonical_url'] 
            : (SITE_URL . '/article/' . ($article['slug'] ?? '') . '/');

        // 1. Evaluate Google Indexing API Eligibility
        // Google explicitly limits Indexing API to JobPosting & BroadcastEvent URLs.
        // Pinging it for generic articles risks service account suspension.
        $isGoogleEligible = !empty($jobPostingSchema) && ($jobPostingSchema['@type'] ?? '') === 'JobPosting';

        if ($isGoogleEligible) {
            $results['google'] = self::pingGoogleIndexingApi($url, 'URL_UPDATED');
        } else {
            $results['google']['message'] = 'Skipped: Not a JobPosting URL (Google Indexing API ToS requires JobPosting schema).';
            Logger::info("IndexingService: Skipped Google Indexing API for '{$url}' (Not JobPosting schema)");
        }

        // 2. Dispatch to IndexNow (Bing / Yandex)
        $results['indexnow'] = self::pingIndexNow($url);

        return $results;
    }

    /**
     * Dispatch removal notification when a job recruitment closes
     *
     * @param string $url Canonical URL of the closed article
     * @return array Result of Google Indexing API call
     */
    public static function notifyExpired(string $url): array {
        return self::pingGoogleIndexingApi($url, 'URL_DELETED');
    }

    /**
     * Send URL notification to Google Indexing API via Service Account OAuth2 JWT
     */
    private static function pingGoogleIndexingApi(string $url, string $type = 'URL_UPDATED'): array {
        $result = ['attempted' => true, 'success' => false, 'message' => ''];

        try {
            // Check daily quota limit
            if (!self::checkAndIncrementQuota('google', self::MAX_GOOGLE_DAILY_QUOTA)) {
                $result['message'] = 'Daily quota limit reached for Google Indexing API';
                Logger::warning("IndexingService: Google daily quota reached (" . self::MAX_GOOGLE_DAILY_QUOTA . ")");
                return $result;
            }

            // Locate Service Account JSON
            $keyPath = Env::get('GOOGLE_INDEXING_KEY_PATH');
            if (empty($keyPath) || !file_exists($keyPath)) {
                $defaultPath = dirname(__DIR__, 2) . '/storage/keys/google-indexing-key.json';
                if (file_exists($defaultPath)) {
                    $keyPath = $defaultPath;
                }
            }

            if (empty($keyPath) || !file_exists($keyPath)) {
                $result['message'] = 'Google Service Account credentials not configured (storage/keys/google-indexing-key.json).';
                Logger::info("IndexingService: Google Service Account key not found. Skipping Google ping.");
                return $result;
            }

            $keyData = json_decode(file_get_contents($keyPath), true);
            if (empty($keyData['client_email']) || empty($keyData['private_key'])) {
                $result['message'] = 'Invalid Google Service Account JSON structure.';
                return $result;
            }

            $accessToken = self::getGoogleAccessToken($keyData);
            if (!$accessToken) {
                $result['message'] = 'Failed to generate Google OAuth2 bearer token.';
                return $result;
            }

            // POST to Google Indexing endpoint
            $payload = json_encode([
                'url'  => $url,
                'type' => $type
            ]);

            $ch = curl_init(self::GOOGLE_INDEXING_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result['success'] = true;
                $result['message'] = "Successfully pinged Google Indexing API ({$type})";
                Logger::info("IndexingService: Google Indexing API ping success for {$url} (HTTP {$httpCode})");
            } else {
                $result['message'] = "Google Indexing API returned HTTP {$httpCode}: {$response}";
                Logger::warning("IndexingService: Google Indexing API returned HTTP {$httpCode} for {$url}");
            }

        } catch (Throwable $e) {
            $result['message'] = 'Google Indexing API Exception: ' . $e->getMessage();
            Logger::error("IndexingService: " . $result['message']);
        }

        return $result;
    }

    /**
     * Generate Google OAuth2 access token from service account private key via JWT
     */
    private static function getGoogleAccessToken(array $keyData): ?string {
        $now = time();
        $jwtHeader = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = self::base64UrlEncode(json_encode([
            'iss'   => $keyData['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now
        ]));

        $signatureInput = $jwtHeader . '.' . $jwtClaim;
        $binarySignature = '';

        if (!openssl_sign($signatureInput, $binarySignature, $keyData['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $signatureInput . '.' . self::base64UrlEncode($binarySignature);

        // Exchange JWT for Bearer token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 200 && $res) {
            $data = json_decode($res, true);
            return $data['access_token'] ?? null;
        }

        return null;
    }

    /**
     * Dispatch URL update to IndexNow (Bing / Yandex)
     */
    private static function pingIndexNow(string $url): array {
        $result = ['attempted' => true, 'success' => false, 'message' => ''];

        try {
            $host = parse_url(SITE_URL, PHP_URL_HOST) ?: 'sarkari.online';
            $key = Env::get('INDEXNOW_KEY', 'e8e3d1a0f9b44a2c918349275bce34fa');

            $payload = json_encode([
                'host'        => $host,
                'key'         => $key,
                'keyLocation' => SITE_URL . '/' . $key . '.txt',
                'urlList'     => [$url]
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init(self::INDEXNOW_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8']
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // IndexNow returns 200 (OK) or 202 (Accepted)
            if ($httpCode === 200 || $httpCode === 202) {
                $result['success'] = true;
                $result['message'] = "IndexNow ping successful (HTTP {$httpCode})";
                Logger::info("IndexingService: IndexNow ping successful for {$url}");
            } else {
                $result['message'] = "IndexNow returned HTTP {$httpCode}: {$response}";
                Logger::info("IndexingService: IndexNow HTTP {$httpCode} for {$url}");
            }

        } catch (Throwable $e) {
            $result['message'] = 'IndexNow Exception: ' . $e->getMessage();
            Logger::error("IndexingService: " . $result['message']);
        }

        return $result;
    }

    /**
     * Check and increment daily rate limits stored in storage/cache
     */
    private static function checkAndIncrementQuota(string $provider, int $maxDaily): bool {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $quotaFile = $cacheDir . "/indexing_quota_{$provider}.json";
        $today = date('Y-m-d');
        $quota = ['date' => $today, 'count' => 0];

        if (file_exists($quotaFile)) {
            $existing = json_decode(file_get_contents($quotaFile), true);
            if (!empty($existing) && ($existing['date'] ?? '') === $today) {
                $quota = $existing;
            }
        }

        if ($quota['count'] >= $maxDaily) {
            return false;
        }

        $quota['count']++;
        @file_put_contents($quotaFile, json_encode($quota));
        return true;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
