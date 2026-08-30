<?php
/**
 * EduPulse - RSS Feed Adapter
 * Consumes public RSS/Atom feeds from verified Indian statutory authorities and news wires.
 */

namespace App\Services\TrendSources;

use App\Helpers\Env;
use App\Helpers\Logger;
use Throwable;

class RssFeedAdapter implements TrendSourceInterface {

    private string $sourceId;
    private string $sourceName;
    private array $feedUrls;
    private int $timeout;

    public function __construct(string $sourceId = 'education_rss', string $sourceName = 'Education RSS Feeds', array $feedUrls = []) {
        $this->sourceId = $sourceId;
        $this->sourceName = $sourceName;
        $this->feedUrls = !empty($feedUrls) ? $feedUrls : [
            'https://feeds.feedburner.com/ndtvnews-education',
            'https://timesofindia.indiatimes.com/rssfeeds/913168846.cms',
            'https://indianexpress.com/section/education/feed/',
            'https://pib.gov.in/RssMain.aspx?ModId=6',
        ];
        $this->timeout = (int)Env::get('TRENDS_FETCH_TIMEOUT', 15);
    }

    public function getSourceId(): string {
        return $this->sourceId;
    }

    public function getSourceName(): string {
        return $this->sourceName;
    }

    public function fetch(int $limit = 10): array {
        $results = [];
        $collected = 0;

        foreach ($this->feedUrls as $url) {
            if ($collected >= $limit) break;

            try {
                $content = $this->fetchUrl($url);
                if (empty($content)) continue;

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                libxml_clear_errors();

                if (!$xml) continue;

                // Handle RSS 2.0 or Atom
                $items = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : []);

                foreach ($items as $item) {
                    if ($collected >= $limit) break;

                    $title = trim((string)$item->title);
                    $link = trim((string)($item->link['href'] ?? $item->link ?? ''));
                    $pubDate = !empty($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$item->pubDate)) : (!empty($item->updated) ? date('Y-m-d H:i:s', strtotime((string)$item->updated)) : date('Y-m-d H:i:s'));
                    $snippet = trim((string)($item->description ?? $item->summary ?? ''));

                    // Strict English Only (Skip any Devanagari / Hindi items)
                    if (preg_match('/[\x{0900}-\x{097F}]/u', $title) || preg_match('/[\x{0900}-\x{097F}]/u', $snippet)) {
                        continue;
                    }

                    // Strict Education & Recruitment Whitelist (Discard generic ministry PR, awards, speeches)
                    if (!\App\Services\TrendService::isEducationRelevant($title, $snippet)) {
                        continue;
                    }

                    $resolvedCat = \App\Services\CategoryService::autoResolveCategory($title, $snippet);
                    $categoryHint = $resolvedCat['slug'] ?? 'career-guides';

                    $results[] = [
                        'keyword' => $title,
                        'source' => $this->getSourceId(),
                        'url' => $link ?: null,
                        'trend_score' => 80,
                        'category_hint' => $categoryHint,
                        'snippet' => strip_tags($snippet),
                        'detected_at' => $pubDate,
                        'raw_payload' => [
                            'feed' => $url,
                            'pub_date' => $pubDate
                        ]
                    ];

                    $collected++;
                }
            } catch (Throwable $e) {
                Logger::warning("RssFeedAdapter failed for {$url}: " . $e->getMessage());
            }
        }

        return $results;
    }

    private function inferCategoryHint(string $text): ?string {
        $lower = mb_strtolower($text);
        if (str_contains($lower, 'result') || str_contains($lower, 'merit') || str_contains($lower, 'cutoff')) return 'exam-results';
        if (str_contains($lower, 'admit card') || str_contains($lower, 'hall ticket')) return 'admit-cards';
        if (str_contains($lower, 'exam date') || str_contains($lower, 'schedule') || str_contains($lower, 'timetable')) return 'exam-dates';
        if (str_contains($lower, 'recruitment') || str_contains($lower, 'vacancy') || str_contains($lower, 'postings') || str_contains($lower, 'job')) return 'government-jobs';
        if (str_contains($lower, 'admission') || str_contains($lower, 'counselling') || str_contains($lower, 'university')) return 'higher-education';
        if (str_contains($lower, 'board') || str_contains($lower, 'cbse') || str_contains($lower, 'class 10') || str_contains($lower, 'class 12')) return 'school-boards';
        if (str_contains($lower, 'scholarship') || str_contains($lower, 'fellowship') || str_contains($lower, 'grant')) return 'scholarships';
        return null;
    }

    private function fetchUrl(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Sarkari.online/2.0 (Indian Education & Career Intelligence Platform)',
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        } else {
            unset($ch);
        }

        return ($httpCode === 200 && is_string($data)) ? $data : null;
    }
}
