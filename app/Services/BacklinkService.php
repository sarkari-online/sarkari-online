<?php
/**
 * Sarkari.online - High-DA Backlink Syndication Engine
 * Automatically generates unique companion articles with contextual backlinks & canonical URLs,
 * and publishes them to high-authority platforms (Dev.to DA 90+).
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\AI\Gemini;
use Throwable;

class BacklinkService {

    private string $devtoApiKey;
    private string $stateFile;

    public function __construct() {
        $this->devtoApiKey = (string)Env::get('DEVTO_API_KEY', 'wTJq1ihZ6DGv98k2yTVYHK3s');
        $this->stateFile = dirname(__DIR__, 2) . '/storage/cache/backlinks_syndicated.json';
    }

    /**
     * Syndicate up to $limit recent published articles to Dev.to
     */
    public function syndicateLatest(int $limit = 2): array {
        $lockFile = dirname(__DIR__, 2) . '/storage/cache/backlink_syndication.lock';
        $fp = @fopen($lockFile, 'c+');
        if (!$fp || !@flock($fp, LOCK_EX | LOCK_NB)) {
            Logger::info("BacklinkService: Another syndication process is already running. Exiting to prevent duplicates.");
            return ['status' => 'locked', 'items' => []];
        }

        try {
            $history = $this->loadHistory();
            $today = date('Y-m-d');
            $todayCount = 0;

            foreach ($history as $item) {
                if (str_starts_with($item['published_at'] ?? '', $today)) {
                    $todayCount++;
                }
            }

            if ($todayCount >= 2) {
                Logger::info("BacklinkService: Daily syndication quota (strictly 2/day) already reached.");
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return ['status' => 'quota_reached', 'today_published' => $todayCount, 'items' => []];
            }

            $allowedSlots = 2 - $todayCount;
            $maxToProcess = min($limit, $allowedSlots);

        // Fetch recent published articles that have not yet been syndicated
        $articles = Database::fetchAll(
            "SELECT a.id, a.title, a.slug, a.content, a.excerpt, c.name AS category_name, c.slug AS category_slug, a.published_at 
             FROM articles a 
             JOIN categories c ON a.category_id = c.id 
             WHERE a.status = 'published' 
             ORDER BY a.published_at DESC 
             LIMIT 15"
        );

        $syndicated = [];

        foreach ($articles as $art) {
            if (count($syndicated) >= $maxToProcess) {
                break;
            }

            $artId = (int)$art['id'];
            if (isset($history[$artId])) {
                continue; // already syndicated
            }

            try {
                $res = $this->createDevToPost($art);
                if (!empty($res['url'])) {
                    $history[$artId] = [
                        'article_id' => $artId,
                        'title' => $art['title'],
                        'sarkari_url' => url('article/' . $art['slug'] . '/'),
                        'devto_url' => $res['url'],
                        'published_at' => date('Y-m-d H:i:s')
                    ];
                    $syndicated[] = $history[$artId];
                    Logger::info("BacklinkService: Successfully syndicated Article #{$artId} to Dev.to: {$res['url']}");
                }
            } catch (Throwable $e) {
                Logger::error("BacklinkService failed on Article #{$artId}: " . $e->getMessage());
            }

            sleep(3); // pacing
        }

        $this->saveHistory($history);

        return [
            'status' => 'success',
            'syndicated_count' => count($syndicated),
            'items' => $syndicated
        ];
        } finally {
            if ($fp) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
        }
    }

    /**
     * Generate unique companion article and publish to Dev.to
     */
    private function createDevToPost(array $art): array {
        $gemini = new Gemini();
        $targetUrl = url('article/' . $art['slug'] . '/');
        $categoryUrl = url('category/' . $art['category_slug'] . '/');
        $siteUrl = SITE_URL;

        $prompt = <<<PROMPT
You are a senior education journalist and editorial writer for high-authority tech and knowledge platforms.
Write a 100% original, in-depth companion article in Markdown format for publication on Dev.to.

TARGET SARKARI.ONLINE TOPIC:
Title: "{$art['title']}"
Category: "{$art['category_name']}"
Target Link: {$targetUrl}
Category Link: {$categoryUrl}
Homepage Link: {$siteUrl}

RULES FOR HIGH-QUALITY CONTEXTUAL BACKLINKING:
1. Write a complete, comprehensive 700 to 950 word educational guide/analysis in Markdown.
2. DO NOT simply copy the original article; write a fresh, authoritative perspective addressing student preparation, official rules, guidelines, and key actionable advice.
3. Naturally embed 2 to 3 organic contextual hyperlinks back to Sarkari.online:
   - Primary Anchor: Link to {$targetUrl} using natural contextual anchor text (e.g. "[verified notification & guidelines on Sarkari.online]({$targetUrl})" or "[official breakdown on Sarkari.online]({$targetUrl})").
   - Category / Resource Anchor: Link to {$categoryUrl} or {$siteUrl} using natural anchor text (e.g. "[comprehensive examination updates]({$categoryUrl})" or "[Sarkari.online information network]({$siteUrl})").
4. Include clean Markdown subheadings (##, ###), bullet points, and actionable takeaways.
5. Choose up to 4 tags (lowercase, alphanumeric only, e.g. ["education", "career", "admissions", "india"]).

Return strictly as JSON with this schema:
{
  "title": "Engaging Headline under 90 characters",
  "body_markdown": "Full Markdown article content...",
  "tags": ["education", "career", "india", "exams"]
}
PROMPT;

        $res = $gemini->generateJson($prompt, [
            'stage' => 'backlink_generation',
            'temperature' => 0.2
        ]);

        $postData = $res['data'] ?? [];
        $title = $postData['title'] ?? $art['title'];
        $markdown = $postData['body_markdown'] ?? '';
        $tags = array_slice($postData['tags'] ?? ['education', 'india', 'career', 'exams'], 0, 4);

        if (empty($markdown)) {
            throw new \Exception("Gemini returned empty markdown body for backlink article.");
        }

        // Send to Dev.to API
        $payload = [
            'article' => [
                'title' => $title,
                'body_markdown' => $markdown,
                'published' => true,
                'tags' => $tags,
                'canonical_url' => $targetUrl,
                'description' => $art['excerpt'] ?: substr(strip_tags($art['content']), 0, 150)
            ]
        ];

        $ch = curl_init('https://dev.to/api/articles');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'api-key: ' . $this->devtoApiKey,
                'User-Agent: SarkariOnline-Backlink-Engine/1.0'
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new \Exception("Dev.to API error (HTTP {$httpCode}): " . substr($response, 0, 300));
        }

        $json = json_decode($response, true);
        return [
            'id' => $json['id'] ?? 0,
            'url' => $json['url'] ?? '',
            'title' => $json['title'] ?? $title
        ];
    }

    private function loadHistory(): array {
        if (!file_exists($this->stateFile)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->stateFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveHistory(array $history): void {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($this->stateFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
