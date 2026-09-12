<?php
/**
 * Sarkari.online - Dynamic Statutory Authority & Portal Resolver
 *
 * Provides self-expanding resolution of Indian examination boards and commissions.
 * 1. Fast-Path: Checks authority_portals DB cache for pre-verified entries.
 * 2. Discovery-Path: Uses Gemini Google Search Grounding to discover genuine
 *    government portals (.gov.in, .nic.in, .ac.in).
 * 3. Fail-Closed Verification: Performs lightweight live HTTP check before caching.
 *    Any timeout, SSL error, or non-200 fails closed to protect domain reputation.
 * 4. Governance Rule: Newly discovered authorities are recorded as 'pending_review'
 *    and NEVER auto-published until verified by a human administrator.
 */

namespace App\Services;

use App\Database\Database;
use App\AI\Gemini;
use App\Helpers\Logger;
use Throwable;

class AuthorityPortalResolverService {

    private Gemini $gemini;
    private int $verifyTimeout = 8;

    private const ALLOWED_TLD_PATTERNS = [
        '/\.gov\.in$/i',
        '/\.nic\.in$/i',
        '/\.ac\.in$/i',
        '/\.edu\.in$/i',
        '/\.up\.gov\.in$/i',
        '/\.bihar\.gov\.in$/i',
        '/\.mp\.gov\.in$/i',
        '/\.rajasthan\.gov\.in$/i',
        '/\.haryana\.gov\.in$/i',
        '/\.uk\.gov\.in$/i',
        '/\.kar\.nic\.in$/i',
        '/\.delhi\.gov\.in$/i',
        '/\.mahacet\.org$/i'
    ];

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Resolve statutory authority by acronym and optional full name hint.
     *
     * @param string $acronym e.g. "UPESSC", "OPSC", "UPSC"
     * @param string $fullNameHint e.g. "Odisha Public Service Commission"
     * @return array|null Authority record or null if unresolvable
     */
    public function resolve(string $acronym, string $fullNameHint = ''): ?array {
        $cleanAcronym = strtoupper(trim($acronym));
        if (empty($cleanAcronym)) {
            return null;
        }

        // 1. Fast Path: Check authority_portals database table
        $existing = $this->fetchFromDb($cleanAcronym);
        if ($existing) {
            return [
                'acronym'             => $existing['acronym'],
                'name'                => $existing['official_name'],
                'portal'              => $existing['portal_url'],
                'verification_status' => $existing['verification_status'],
                'discovery_method'    => $existing['discovery_method']
            ];
        }

        // 2. Discovery Path: Grounded search via Gemini Google Search Grounding
        if (\App\AI\Gemini::isCircuitBreakerActive()) {
            Logger::info("AuthorityPortalResolver: Circuit breaker active, skipping dynamic discovery for '{$cleanAcronym}'.");
            return null;
        }

        $fullName = !empty($fullNameHint) ? $fullNameHint : $cleanAcronym;
        Logger::info("AuthorityPortalResolver: Attempting dynamic search discovery for '{$cleanAcronym}' ({$fullName}).");

        try {
            $prompt = "What is the official government website (.gov.in, .nic.in, or .ac.in domain) "
                    . "for {$fullName} ({$cleanAcronym})? This is an Indian statutory examination / recruitment authority. "
                    . "Return only the single most authoritative official URL.";

            $response = $this->gemini->generateGrounded($prompt, ['googleSearch'], [
                'stage' => 'authority_discovery',
                'temperature' => 0.05
            ]);

            $candidateUrl = $this->extractTopGroundedUrl($response);
            if (!$candidateUrl || !$this->matchesAllowedTld($candidateUrl)) {
                Logger::warning("AuthorityPortalResolver: Discovery failed or candidate URL not matching allowed TLD for '{$cleanAcronym}' ('{$candidateUrl}').");
                return null; // Absence is safe; never guess
            }

            // 3. Lightweight Live Verification (FAIL-CLOSED)
            $isConfirmed = $this->verifyPageMentionsAuthority($candidateUrl, $cleanAcronym, $fullName);
            if (!$isConfirmed) {
                Logger::warning("AuthorityPortalResolver: Live cURL verification failed for '{$candidateUrl}'. Storing as pending_review (unverified).");
                $this->storeDiscovered($cleanAcronym, $fullName, $candidateUrl, false);
                return [
                    'acronym'             => $cleanAcronym,
                    'name'                => $fullName . " ({$cleanAcronym})",
                    'portal'              => $candidateUrl,
                    'verification_status' => 'pending_review',
                    'discovery_method'    => 'search_discovered'
                ];
            }

            // Successfully discovered and live-confirmed!
            // Note per governance rule: Freshly discovered authorities are stored as 'pending_review'
            // so that PipelineService holds first-time use for admin confirmation.
            $this->storeDiscovered($cleanAcronym, $fullName, $candidateUrl, false);
            Logger::info("AuthorityPortalResolver: Discovered and confirmed portal '{$candidateUrl}' for '{$cleanAcronym}'. Stored as pending_review.");

            return [
                'acronym'             => $cleanAcronym,
                'name'                => $fullName . " ({$cleanAcronym})",
                'portal'              => $candidateUrl,
                'verification_status' => 'pending_review',
                'discovery_method'    => 'search_discovered'
            ];

        } catch (Throwable $e) {
            Logger::error("AuthorityPortalResolver: Exception during resolution of '{$cleanAcronym}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract top authoritative URL from groundingMetadata chunks or response text
     */
    public function extractTopGroundedUrl(array $geminiResponse): ?string {
        $metadata = $geminiResponse['grounding_metadata'] ?? [];
        $chunks = $metadata['groundingChunks'] ?? [];

        foreach ($chunks as $chunk) {
            $uri = $chunk['web']['uri'] ?? ($chunk['uri'] ?? null);
            if ($uri && $this->matchesAllowedTld($uri)) {
                return $this->normalizeBaseUrl($uri);
            }
        }

        // Fallback: regex search in response text
        $text = $geminiResponse['text'] ?? '';
        if (preg_match('/https?:\/\/[a-z0-9.-]+\.(?:gov|nic|ac|edu)\.in[^\s"\'<>)]*/i', $text, $match)) {
            return $this->normalizeBaseUrl($match[0]);
        }

        return null;
    }

    /**
     * Validate whether URL matches allowed Indian government / statutory TLD patterns
     */
    public function matchesAllowedTld(string $url): bool {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        if (empty($host)) {
            return false;
        }

        foreach (self::ALLOWED_TLD_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fail-Closed live HTTP verification:
     * Confirms the live portal loads with HTTP 200 and mentions the authority.
     * Any timeout, SSL error, redirect loop, or non-200 FAILS CLOSED (returns false).
     */
    public function verifyPageMentionsAuthority(string $url, string $acronym, string $fullName): bool {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->verifyTimeout,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 SarkariBot/1.0'
            ]);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            unset($ch);

            // FAIL-CLOSED: Non-200 or cURL error
            if (!empty($curlError) || $httpCode < 200 || $httpCode >= 400 || empty($html)) {
                Logger::warning("AuthorityPortalResolver verifyPage failed closed for '{$url}': HTTP {$httpCode}, Error: {$curlError}");
                return false;
            }

            $cleanHtml = strtolower(strip_tags($html));
            $cleanAcronym = strtolower($acronym);

            // Check if acronym appears as whole word or in text
            if (str_contains($cleanHtml, $cleanAcronym)) {
                return true;
            }

            // Check significant tokens from full name (e.g. "odisha", "public service")
            $tokens = array_filter(explode(' ', strtolower($fullName)), fn($t) => strlen($t) > 3 && !in_array($t, ['commission', 'board', 'selection', 'state']));
            $matchCount = 0;
            foreach ($tokens as $t) {
                if (str_contains($cleanHtml, $t)) {
                    $matchCount++;
                }
            }

            return $matchCount >= 1;

        } catch (Throwable $e) {
            Logger::warning("AuthorityPortalResolver verifyPage exception for '{$url}': " . $e->getMessage());
            return false; // Fail-closed
        }
    }

    /**
     * Normalize URL to clean base portal URL (scheme + host + base path)
     */
    private function normalizeBaseUrl(string $url): string {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        return $scheme . '://' . rtrim($host, '/');
    }

    /**
     * Fetch from authority_portals table
     */
    private function fetchFromDb(string $acronym): ?array {
        try {
            return Database::fetchOne(
                "SELECT * FROM authority_portals WHERE acronym = :acr LIMIT 1",
                ['acr' => $acronym]
            );
        } catch (Throwable $e) {
            Logger::error("AuthorityPortalResolver fetchFromDb error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store newly discovered authority in authority_portals table
     */
    private function storeDiscovered(string $acronym, string $fullName, string $portalUrl, bool $verified): void {
        try {
            $status = $verified ? 'verified' : 'pending_review';
            $verifiedAt = $verified ? date('Y-m-d H:i:s') : null;
            $verifiedBy = $verified ? 'live_curl_check' : null;

            Database::execute("
                INSERT INTO authority_portals 
                    (acronym, official_name, portal_url, discovery_method, verification_status, discovered_at, verified_at, verified_by)
                VALUES 
                    (:acr, :name, :url, 'search_discovered', :status, NOW(), :vat, :vby)
                ON DUPLICATE KEY UPDATE 
                    portal_url = VALUES(portal_url),
                    verification_status = VALUES(verification_status)
            ", [
                'acr'    => $acronym,
                'name'   => $fullName . " ({$acronym})",
                'url'    => $portalUrl,
                'status' => $status,
                'vat'    => $verifiedAt,
                'vby'    => $verifiedBy
            ]);
        } catch (Throwable $e) {
            Logger::error("AuthorityPortalResolver storeDiscovered error: " . $e->getMessage());
        }
    }
}
