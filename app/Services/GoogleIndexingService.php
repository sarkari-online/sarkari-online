<?php
/**
 * EduPulse - Google Real-Time Indexing API Service
 * Programmatically notifies Googlebot of new and updated article URLs within seconds.
 * Uses native PHP OpenSSL JWT signing (zero third-party dependencies).
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use Exception;

class GoogleIndexingService {

    private static string $keyFilePath = '';
    private static ?string $cachedToken = null;
    private static int $tokenExpiry = 0;

    private static function init(): void {
        if (empty(self::$keyFilePath)) {
            self::$keyFilePath = dirname(__DIR__, 2) . '/storage/google-indexing-key.json';
        }
    }

    /**
     * Check if Google Indexing API service account key is installed
     */
    public static function isConfigured(): bool {
        self::init();
        return file_exists(self::$keyFilePath) && filesize(self::$keyFilePath) > 50;
    }

    /**
     * Get Service Account Credentials Array
     */
    private static function getCredentials(): ?array {
        self::init();
        if (!self::isConfigured()) {
            return null;
        }

        $content = file_get_contents(self::$keyFilePath);
        $json = json_decode($content, true);
        if (!is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
            Logger::error("Invalid Google Indexing Service Account JSON file structure.");
            return null;
        }

        return $json;
    }

    /**
     * Generate an OAuth2 Bearer Access Token via RSA JWT Signing
     */
    private static function getAccessToken(): ?string {
        if (self::$cachedToken && time() < (self::$tokenExpiry - 60)) {
            return self::$cachedToken;
        }

        $creds = self::getCredentials();
        if (!$creds) {
            return null;
        }

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        
        $claimSet = [
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];
        $claims = base64_encode(json_encode($claimSet));

        $signatureInput = rtrim(strtr($header, '+/', '-_'), '=') . '.' . rtrim(strtr($claims, '+/', '-_'), '=');
        
        $privateKey = openssl_pkey_get_private($creds['private_key']);
        if (!$privateKey) {
            Logger::error("Failed to parse Google Service Account private key via OpenSSL.");
            return null;
        }

        $signature = '';
        $signed = openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            Logger::error("OpenSSL failed to sign Google Indexing JWT.");
            return null;
        }

        $jwt = $signatureInput . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        // Exchange JWT for Google Access Token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($httpCode !== 200) {
            Logger::error("Google OAuth2 Token Exchange Failed [HTTP {$httpCode}]: {$response}");
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            Logger::error("Access token missing in Google OAuth response: {$response}");
            return null;
        }

        self::$cachedToken = $data['access_token'];
        self::$tokenExpiry = $now + (int)($data['expires_in'] ?? 3600);

        return self::$cachedToken;
    }

    /**
     * Check if an article is 100% strictly eligible for Google Indexing API.
     * Google restricts Indexing API to JobPosting and BroadcastEvent only.
     * Pinging for generic articles, admit cards, results, or expired dates risks Google penalty.
     *
     * @param array $article Article database record
     * @return array ['eligible' => bool, 'reason' => string, 'job_schema' => ?array]
     */
    public static function checkEligibility(array $article): array {
        // 1. Category check - must be government-jobs or recruitment
        $catSlug = $article['category_slug'] ?? '';
        if ($catSlug !== 'government-jobs') {
            return [
                'eligible' => false,
                'reason' => "Category '{$catSlug}' is not government-jobs. Google Indexing API is strictly restricted to JobPosting notices.",
                'job_schema' => null
            ];
        }

        // 2. Lifecycle check - must not be closed, archived, historical
        $lifecycle = $article['lifecycle_status'] ?? 'draft';
        if (in_array($lifecycle, ['closed', 'exam_completed', 'admit_card_released', 'result_released', 'historical', 'archived'], true)) {
            return [
                'eligible' => false,
                'reason' => "Article lifecycle is '{$lifecycle}'. Only active recruitment notifications can be pinged.",
                'job_schema' => null
            ];
        }

        // 3. Negative keyword check on Title - admit card, result, answer key, etc.
        $title = $article['title'] ?? '';
        $lowerTitle = mb_strtolower($title);
        $negativeKeywords = ['admit card', 'hall ticket', 'result', 'answer key', 'syllabus', 'exam date', 'exam city', 'cut off', 'merit list'];
        foreach ($negativeKeywords as $neg) {
            if (str_contains($lowerTitle, $neg)) {
                return [
                    'eligible' => false,
                    'reason' => "Title contains non-job keyword '{$neg}'. Indexing API disallowed.",
                    'job_schema' => null
                ];
            }
        }

        // 4. Decode raw_payload if present
        $rawPayload = [];
        if (!empty($article['raw_payload'])) {
            $rawPayload = is_array($article['raw_payload']) ? $article['raw_payload'] : (json_decode($article['raw_payload'], true) ?? []);
        }

        // 5. Generate and validate Schema.org JobPosting
        $jobSchema = SchemaService::generateJobPosting($article, $rawPayload);
        if (!$jobSchema || ($jobSchema['@type'] ?? '') !== 'JobPosting') {
            return [
                'eligible' => false,
                'reason' => "Article does not have a valid JobPosting schema with a future application deadline (validThrough).",
                'job_schema' => null
            ];
        }

        // 6. Verify validThrough deadline is strictly in the future
        $validThrough = $jobSchema['validThrough'] ?? null;
        if (!$validThrough || strtotime($validThrough) <= time()) {
            return [
                'eligible' => false,
                'reason' => "Application deadline ({$validThrough}) has already passed or is invalid.",
                'job_schema' => null
            ];
        }

        return [
            'eligible' => true,
            'reason' => "Eligible JobPosting with deadline {$validThrough}",
            'job_schema' => $jobSchema
        ];
    }

    /**
     * Submit a URL to Google Real-Time Indexing API
     * @param string $url Full canonical URL
     * @param string $type URL_UPDATED or URL_DELETED
     * @return array
     */
    public static function pingUrl(string $url, string $type = 'URL_UPDATED'): array {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to obtain Google OAuth2 access token.',
                'status_code' => 500
            ];
        }

        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $payload = json_encode([
            'url' => $url,
            'type' => $type
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        unset($ch);

        if ($curlError) {
            Logger::error("Google Indexing API cURL error: {$curlError}");
            return [
                'success' => false,
                'message' => "cURL error: {$curlError}",
                'status_code' => 0
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200) {
            Logger::info("Google Indexing API successfully notified for {$url}", ['type' => $type, 'response' => $data]);
            return [
                'success' => true,
                'message' => 'Googlebot successfully notified for instant indexing.',
                'status_code' => 200,
                'response' => $data
            ];
        }

        Logger::warning("Google Indexing API notification returned HTTP {$httpCode}", ['url' => $url, 'response' => $response]);
        return [
            'success' => false,
            'message' => $data['error']['message'] ?? "HTTP {$httpCode} error",
            'status_code' => $httpCode,
            'response' => $data
        ];
    }

    /**
     * Submit an Article by ID (Strictly gated to verified JobPosting articles per Google ToS)
     *
     * @param int $articleId
     * @param bool $force Bypass eligibility gate only if explicitly requested (e.g. manual admin override)
     * @return array
     */
    public static function pingArticle(int $articleId, bool $force = false): array {
        $article = Database::fetchOne(
            "SELECT a.*, c.slug AS category_slug, t.raw_payload 
             FROM articles a 
             JOIN categories c ON a.category_id = c.id 
             LEFT JOIN trends t ON a.trend_id = t.id 
             WHERE a.id = :id LIMIT 1", 
            ['id' => $articleId]
        );

        if (!$article || $article['status'] !== 'published') {
            return [
                'success' => false,
                'message' => "Article #{$articleId} is not published.",
                'status_code' => 400
            ];
        }

        // Safety Gate: Check eligibility strictly
        if (!$force) {
            $check = self::checkEligibility($article);
            if (!$check['eligible']) {
                Logger::info("Google Indexing API skipped for Article #{$articleId}: " . $check['reason']);
                return [
                    'success' => false,
                    'message' => 'Skipped: ' . $check['reason'],
                    'status_code' => 422,
                    'ineligible' => true
                ];
            }
        }

        // Real-Time Google Indexing Notification for verified recruitment notices
        $canonical = !empty($article['canonical_url']) ? $article['canonical_url'] : url('article/' . $article['slug'] . '/');
        return self::pingUrl($canonical, 'URL_UPDATED');
    }
}
