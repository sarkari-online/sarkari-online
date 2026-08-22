<?php
/**
 * EduPulse - Google Trends RSS Adapter
 * Discovers emerging search surges in India via Google Trends Daily RSS.
 */

namespace App\Services\TrendSources;

use App\Helpers\Env;
use App\Helpers\Logger;
use Throwable;

class GoogleTrendsAdapter implements TrendSourceInterface {

    private string $feedUrl;
    private int $timeout;

    public function __construct(?string $feedUrl = null) {
        $this->feedUrl = $feedUrl ?: 'https://trends.google.com/trending/rss?geo=IN';
        $this->timeout = (int)Env::get('TRENDS_FETCH_TIMEOUT', 15);
    }

    public function getSourceId(): string {
        return 'google_trends';
    }

    public function getSourceName(): string {
        return 'Google Trends India';
    }

    public function fetch(int $limit = 10): array {
        $results = [];

        try {
            $xmlContent = $this->fetchUrl($this->feedUrl);
            if (empty($xmlContent)) {
                return [];
            }

            // Suppress XML errors during parsing
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            libxml_clear_errors();

            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) break;

                $title = trim((string)$item->title);
                $link = trim((string)$item->link);
                $pubDate = !empty($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$item->pubDate)) : date('Y-m-d H:i:s');
                $snippet = trim((string)$item->description);

                // Google Trends custom namespaces
                $namespaces = $item->getNamespaces(true);
                $traffic = 50;
                if (isset($namespaces['ht'])) {
                    $ht = $item->children($namespaces['ht']);
                    $approxTraffic = (string)$ht->approx_traffic;
                    if (preg_match('/(\d+)/', str_replace(['+', ','], '', $approxTraffic), $m)) {
                        $rawNum = (int)$m[1];
                        $traffic = min(100, max(50, (int)($rawNum / 1000)));
                    }
                }

                $categoryHint = $this->inferCategoryHint($title . ' ' . $snippet);

                $results[] = [
                    'keyword' => $title,
                    'source' => $this->getSourceId(),
                    'url' => $link ?: null,
                    'trend_score' => $traffic,
                    'category_hint' => $categoryHint,
                    'snippet' => strip_tags($snippet),
                    'detected_at' => $pubDate,
                    'raw_payload' => [
                        'feed' => $this->feedUrl,
                        'pub_date' => $pubDate,
                        'approx_traffic' => $approxTraffic ?? null
                    ]
                ];

                $count++;
            }
        } catch (Throwable $e) {
            Logger::warning('GoogleTrendsAdapter fetch error: ' . $e->getMessage());
        }

        return $results;
    }

    private function inferCategoryHint(string $text): ?string {
        $lower = mb_strtolower($text);
        if (str_contains($lower, 'result') || str_contains($lower, 'scorecard') || str_contains($lower, 'merit list')) return 'exam-results';
        if (str_contains($lower, 'admit card') || str_contains($lower, 'hall ticket') || str_contains($lower, 'city slip')) return 'admit-cards';
        if (str_contains($lower, 'exam date') || str_contains($lower, 'schedule') || str_contains($lower, 'timetable')) return 'exam-dates';
        if (str_contains($lower, 'recruitment') || str_contains($lower, 'vacancy') || str_contains($lower, 'govt job') || str_contains($lower, 'upsc') || str_contains($lower, 'ssc')) return 'government-jobs';
        if (str_contains($lower, 'counselling') || str_contains($lower, 'admission') || str_contains($lower, 'neet') || str_contains($lower, 'jee') || str_contains($lower, 'cuet')) return 'higher-education';
        if (str_contains($lower, 'cbse') || str_contains($lower, 'icse') || str_contains($lower, '10th') || str_contains($lower, '12th') || str_contains($lower, 'board')) return 'school-boards';
        if (str_contains($lower, 'scholarship') || str_contains($lower, 'fellowship')) return 'scholarships';
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
