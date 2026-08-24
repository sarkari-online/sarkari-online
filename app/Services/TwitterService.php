<?php
/**
 * EduPulse - Twitter (X) Real-Time Broadcast Service
 * Automatically tweets breaking exam notices, admit cards, and job alerts using X API v2.
 * Native PHP HMAC-SHA1 OAuth 1.0a implementation (zero third-party dependencies).
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;

class TwitterService {

    /**
     * Check if Twitter API keys are configured
     */
    public static function isConfigured(): bool {
        $cKey = Env::get('TWITTER_CONSUMER_KEY', '');
        $cSec = Env::get('TWITTER_CONSUMER_SECRET', '');
        $aTok = Env::get('TWITTER_ACCESS_TOKEN', '');
        $aSec = Env::get('TWITTER_ACCESS_TOKEN_SECRET', '');

        return !empty($cKey) && !empty($cSec) && !empty($aTok) && !empty($aSec);
    }

    /**
     * Post a tweet using X API v2 (POST /2/tweets)
     * @param string $text
     * @return array
     */
    public static function tweet(string $text): array {
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'message' => 'Twitter credentials not configured in environment.'
            ];
        }

        $consumerKey = Env::get('TWITTER_CONSUMER_KEY');
        $consumerSecret = Env::get('TWITTER_CONSUMER_SECRET');
        $accessToken = Env::get('TWITTER_ACCESS_TOKEN');
        $accessTokenSecret = Env::get('TWITTER_ACCESS_TOKEN_SECRET');

        $url = 'https://api.twitter.com/2/tweets';
        $method = 'POST';
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));

        // OAuth 1.0a Parameters
        $oauthParams = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0'
        ];

        // Generate OAuth Signature Base String
        ksort($oauthParams);
        $paramPairs = [];
        foreach ($oauthParams as $k => $v) {
            $paramPairs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $paramString = implode('&', $paramPairs);
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        $oauthParams['oauth_signature'] = $signature;

        // Build Authorization Header
        $authHeaderParts = [];
        foreach ($oauthParams as $k => $v) {
            $authHeaderParts[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }
        $authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

        $payload = json_encode(['text' => $text], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $authHeader
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        unset($ch);

        if ($curlErr) {
            Logger::error("Twitter API cURL error: {$curlErr}");
            return ['success' => false, 'message' => "cURL error: {$curlErr}"];
        }

        $data = json_decode($response, true);

        if ($httpCode === 201 || ($httpCode >= 200 && $httpCode < 300)) {
            $tweetId = $data['data']['id'] ?? '';
            Logger::info("Tweet successfully posted [ID: {$tweetId}]", ['text' => $text]);
            return [
                'success' => true,
                'tweet_id' => $tweetId,
                'response' => $data
            ];
        }

        Logger::warning("Twitter API returned HTTP {$httpCode}: {$response}");
        return [
            'success' => false,
            'http_code' => $httpCode,
            'message' => $data['detail'] ?? ($data['errors'][0]['message'] ?? 'Failed to post tweet'),
            'raw' => $data
        ];
    }

    /**
     * Compose and broadcast an automated high-CTR tweet for an article
     */
    public static function broadcastArticle(int $articleId): array {
        $article = Database::fetchOne(
            "SELECT a.*, c.name as category_name, c.slug as category_slug 
             FROM articles a 
             JOIN categories c ON a.category_id = c.id 
             WHERE a.id = :id AND a.status = 'published' LIMIT 1",
            ['id' => $articleId]
        );

        if (!$article) {
            return ['success' => false, 'message' => "Article #{$articleId} not found or not published."];
        }

        $title = $article['title'];
        $category = $article['category_name'];
        $url = url('article/' . $article['slug'] . '/');

        // Dynamic Hashtags based on Category
        $hashtags = "#SarkariOnline #ExamAlerts #EducationIndia";
        $slug = $article['category_slug'] ?? '';
        if (str_contains($slug, 'job') || str_contains($slug, 'recruitment')) {
            $hashtags .= " #GovtJobs2026 #SarkariResult #JobAlert";
        } elseif (str_contains($slug, 'entrance') || str_contains($slug, 'neet') || str_contains($slug, 'jee') || str_contains($slug, 'gate')) {
            $hashtags .= " #EntranceExam #AdmitCard #ExamDates";
        } elseif (str_contains($slug, 'scholarship')) {
            $hashtags .= " #Scholarships #NSP #StudentAid";
        } else {
            $hashtags .= " #LatestNews #ExamNotice";
        }

        // Compose High-CTR 280-char compliant Tweet
        $tweetText = "🚨 LATEST UPDATE: {$title}\n\n";
        $tweetText .= "📌 Category: {$category}\n";
        $tweetText .= "✅ Verified details & direct link:\n";
        $tweetText .= "👉 {$url}\n\n";
        $tweetText .= $hashtags;

        // Truncate if exceeds 280 chars
        if (mb_strlen($tweetText) > 280) {
            $maxTitleLen = 280 - mb_strlen("\n\n📌 Category: {$category}\n👉 {$url}\n\n{$hashtags}") - 25;
            $truncatedTitle = mb_substr($title, 0, max(40, $maxTitleLen)) . '...';
            $tweetText = "🚨 UPDATE: {$truncatedTitle}\n\n";
            $tweetText .= "👉 {$url}\n\n";
            $tweetText .= $hashtags;
        }

        return self::tweet($tweetText);
    }
}
