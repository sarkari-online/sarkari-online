<?php
/**
 * Sarkari.online - Official Statutory Authority RSS Adapter
 * Fetches LIVE notifications from official Indian statutory portals
 * (NTA, UPSC, SSC, CBSE, UGC, MCC, PIB Education) via their RSS feeds.
 * Falls back to database sources table if no feeds are available.
 */

namespace App\Services\TrendSources;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Env;
use Throwable;

class OfficialSourcesAdapter implements TrendSourceInterface {

    private int $timeout;

    // Official statutory RSS feeds for Indian education authorities
    private array $officialFeeds = [
        'https://pib.gov.in/RssMain.aspx?ModId=6' => ['name' => 'PIB Education', 'category' => 'career-guides', 'score' => 90],
    ];

    public function __construct() {
        $this->timeout = min(6, (int)Env::get('TRENDS_FETCH_TIMEOUT', 15));
    }

    public function getSourceId(): string {
        return 'official_sources';
    }

    public function getSourceName(): string {
        return 'Indian Statutory Portals';
    }

    public function fetch(int $limit = 10): array {
        $results = [];

        // 1. Try fetching from official RSS feeds first
        foreach ($this->officialFeeds as $feedUrl => $meta) {
            if (count($results) >= $limit) break;

            try {
                $items = $this->fetchRssFeed($feedUrl, $meta, ceil($limit / count($this->officialFeeds)));
                $results = array_merge($results, $items);
            } catch (Throwable $e) {
                Logger::warning("OfficialSourcesAdapter feed failed [{$meta['name']}]: " . $e->getMessage());
            }
        }

        // 2. Return collected items from official RSS feeds (do NOT generate synthetic placeholders)
        return array_slice($results, 0, $limit);
    }

    private function fetchRssFeed(string $url, array $meta, int $limit): array {
        $results = [];

        $content = $this->fetchUrl($url);
        if (empty($content)) {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if (!$xml) {
            return [];
        }

        $items = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : []);

        $count = 0;
        foreach ($items as $item) {
            if ($count >= $limit) break;

            $title   = trim((string)$item->title);
            $link    = trim((string)($item->link['href'] ?? $item->link ?? ''));
            $pubDate = !empty($item->pubDate)
                ? date('Y-m-d H:i:s', strtotime((string)$item->pubDate))
                : (!empty($item->updated) ? date('Y-m-d H:i:s', strtotime((string)$item->updated)) : date('Y-m-d H:i:s'));
            $snippet = strip_tags(trim((string)($item->description ?? $item->summary ?? '')));

            if (empty($title) || mb_strlen($title) < 10) continue;

            // Reject Hindi/Devanagari
            if (preg_match('/[\x{0900}-\x{097F}]/u', $title)) continue;

            // Must be education relevant
            if (!\App\Services\TrendService::isEducationRelevant($title, $snippet)) continue;

            $results[] = [
                'keyword'     => $title,
                'source'      => $meta['name'],
                'url'         => $link ?: $url,
                'trend_score' => $meta['score'],
                'category_hint' => $meta['category'],
                'snippet'     => $snippet,
                'detected_at' => $pubDate,
                'raw_payload' => [
                    'feed'      => $url,
                    'authority' => $meta['name'],
                    'pub_date'  => $pubDate,
                    'snippet'   => $snippet
                ]
            ];

            $count++;
        }

        return $results;
    }



    private function fetchUrl(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Sarkari.online/2.0 (Indian Education Platform; RSS Reader)',
            CURLOPT_SSL_VERIFYPEER => false, // Some govt portals have cert issues
        ]);
        $data     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && is_string($data) && strlen($data) > 100) ? $data : null;
    }
}
