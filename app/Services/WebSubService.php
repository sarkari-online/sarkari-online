<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use Throwable;

/**
 * WebSubService (PubSubHubbub Protocol Service)
 * Implements the W3C WebSub standard to notify Google and secondary hubs
 * in real time whenever an RSS/Atom feed is updated with a newly published article.
 * 
 * Target Hubs:
 * - Google Primary Hub: https://pubsubhubbub.appspot.com/
 * - Superfeedr Hub:     https://pubsubhubbub.superfeedr.com/
 */
class WebSubService
{
    public const GOOGLE_HUB = 'https://pubsubhubbub.appspot.com/';
    public const SUPERFEEDR_HUB = 'https://pubsubhubbub.superfeedr.com/';

    public const DEFAULT_HUBS = [
        self::GOOGLE_HUB,
        self::SUPERFEEDR_HUB
    ];

    /**
     * Get the authoritative public feed URLs for Sarkari.online
     */
    public static function getFeedUrls(): array
    {
        $parsed = parse_url(defined('SITE_URL') ? SITE_URL : '', PHP_URL_HOST);
        $host   = (!$parsed || in_array($parsed, ['localhost', '127.0.0.1'], true)) ? 'sarkari.online' : $parsed;

        return [
            "https://{$host}/feed/",
            "https://{$host}/rss.xml"
        ];
    }

    /**
     * Publish/Ping a single feed URL to a specific WebSub hub
     * 
     * @param string $hubUrl WebSub hub endpoint
     * @param string $feedUrl Feed URL that was updated
     * @return array Status report ['success' => bool, 'code' => int, 'hub' => string, 'feed' => string]
     */
    public static function pingHub(string $hubUrl, string $feedUrl): array
    {
        $ch = curl_init();
        $postFields = http_build_query([
            'hub.mode' => 'publish',
            'hub.url'  => $feedUrl
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $hubUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: SarkariOnline-WebSub/1.0 (+https://sarkari.online)'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        // Note: curl_close() omitted for clean PHP 8.0-8.5+ lifecycle compatibility

        // HTTP 204 No Content is standard W3C WebSub success; HTTP 200/202 are also valid
        $isSuccess = in_array($httpCode, [200, 202, 204], true);

        if ($isSuccess) {
            Logger::info("WebSub: Successfully pinged hub [{$hubUrl}] for feed [{$feedUrl}] (HTTP {$httpCode})");
        } else {
            Logger::warning("WebSub: Hub ping warning [{$hubUrl}] for feed [{$feedUrl}] (HTTP {$httpCode}): {$error}");
        }

        return [
            'success'   => $isSuccess,
            'http_code' => $httpCode,
            'hub'       => $hubUrl,
            'feed'      => $feedUrl,
            'error'     => $error ?: null
        ];
    }

    /**
     * Notify all configured WebSub hubs for all public feeds
     * 
     * @param array|null $feedUrls Optional explicit feed URLs list
     * @param array|null $hubs Optional explicit hub endpoints list
     * @return array Results summary
     */
    public static function pingHubs(?array $feedUrls = null, ?array $hubs = null): array
    {
        $targetFeeds = $feedUrls ?: self::getFeedUrls();
        $targetHubs  = $hubs ?: self::DEFAULT_HUBS;

        $results = [];

        foreach ($targetFeeds as $feedUrl) {
            foreach ($targetHubs as $hubUrl) {
                try {
                    $results[] = self::pingHub($hubUrl, $feedUrl);
                } catch (Throwable $e) {
                    Logger::error("WebSub: Ping exception for hub [{$hubUrl}] on feed [{$feedUrl}]: " . $e->getMessage());
                    $results[] = [
                        'success'   => false,
                        'http_code' => 0,
                        'hub'       => $hubUrl,
                        'feed'      => $feedUrl,
                        'error'     => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }
}
