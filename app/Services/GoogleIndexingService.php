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
        curl_close($ch);

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
     * Submit a URL to Google Real-Time Indexing API
     * @param string $url Full canonical URL
     * @param string $type URL_UPDATED or URL_DELETED
     * @return array
     */
    public static function pingUrl(string $url, string $type = 'URL_UPDATED'): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'message' => 'Google Indexing key file not installed at storage/google-indexing-key.json',
                'status_code' => 0
            ];
        }

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
        curl_close($ch);

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
     * Submit an Article by ID
     */
    public static function pingArticle(int $articleId): array {
        $article = Database::fetchOne("SELECT id, slug, status FROM articles WHERE id = :id LIMIT 1", ['id' => $articleId]);
        if (!$article || $article['status'] !== 'published') {
            return [
                'success' => false,
                'message' => "Article #{$articleId} is not published.",
                'status_code' => 400
            ];
        }

        $canonical = url('article/' . $article['slug'] . '/');
        return self::pingUrl($canonical, 'URL_UPDATED');
    }
}
