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
     * Google Indexing API disabled by administrator.
     */
    public static function isConfigured(): bool {
        return false;
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
     * Check if an article is eligible for Google Indexing API (Disabled)
     */
    public static function checkEligibility(array $article): array {
        return [
            'eligible' => false,
            'reason' => 'Google Indexing API disabled by administrator.',
            'job_schema' => null
        ];
    }

    /**
     * Submit a URL to Google Real-Time Indexing API (Disabled)
     */
    public static function pingUrl(string $url, string $type = 'URL_UPDATED'): array {
        return [
            'success' => false,
            'message' => 'Google Indexing API disabled by administrator.',
            'status_code' => 200
        ];
    }

    /**
     * Submit an Article by ID (Disabled)
     */
    public static function pingArticle(int $articleId, bool $force = false): array {
        return [
            'success' => false,
            'message' => 'Google Indexing API disabled by administrator.',
            'status_code' => 200
        ];
    }
}
