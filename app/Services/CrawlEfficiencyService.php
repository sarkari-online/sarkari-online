<?php
declare(strict_types=1);

namespace App\Services;

/**
 * CrawlEfficiencyService
 * Implements HTTP/1.1 Conditional GET Validation (RFC 7232) & Crawler Directives.
 * Sends Last-Modified, ETag, and X-Robots-Tag headers across all public pages.
 * When Googlebot sends If-Modified-Since or If-None-Match headers for unchanged content,
 * immediately responds with HTTP/1.1 304 Not Modified (0 bytes body), conserving Google's
 * daily crawl budget so it prioritizes newly published articles.
 */
class CrawlEfficiencyService
{
    /**
     * Send caching and robots headers, and terminate with 304 Not Modified if content is unchanged.
     * 
     * @param string $resourceKey Unique identifier (e.g. article slug, full-form slug, category page)
     * @param int|string $updatedAt Timestamp or date string of last content modification
     * @param bool $skipCheck If true, headers are sent but 304 exit is skipped (e.g. for preview mode)
     */
    public static function handleConditionalGet(string $resourceKey, int|string $updatedAt, bool $skipCheck = false): void
    {
        $timestamp = is_numeric($updatedAt) ? (int)$updatedAt : strtotime((string)$updatedAt);
        if ($timestamp === false || $timestamp <= 0) {
            $timestamp = time();
        }

        $gmtLastMod = gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
        $etag = '"' . md5($resourceKey . '-' . $timestamp) . '"';

        // Set authoritative validation & crawling headers (if headers not already dispatched)
        if (!headers_sent()) {
            header("Last-Modified: {$gmtLastMod}");
            header("ETag: {$etag}");
            header("X-Robots-Tag: max-snippet:-1, max-image-preview:large, max-video-preview:-1");
        }

        // If preview mode or requested bypass, do not terminate with 304
        if ($skipCheck) {
            return;
        }

        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime(trim($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : false;
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;

        // Strip quotes/weak validators if present
        if ($ifNoneMatch !== false) {
            $cleanNoneMatch = trim($ifNoneMatch, 'W/ ');
            $cleanEtag = trim($etag, '"');
            $matchEtag = ($ifNoneMatch === $etag || $cleanNoneMatch === $cleanEtag);
        } else {
            $matchEtag = false;
        }

        $matchTimestamp = ($ifModifiedSince !== false && $ifModifiedSince >= $timestamp);

        if ($matchEtag || $matchTimestamp) {
            if (!headers_sent()) {
                header("HTTP/1.1 304 Not Modified");
            }
            exit;
        }
    }
}
