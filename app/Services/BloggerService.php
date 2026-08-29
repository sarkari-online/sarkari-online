<?php
/**
 * Sarkari.online - Autonomous Google Blogger API v3 Engine
 * Fully isolated companion publishing service.
 * Syndicates high-DA Tier-1 articles to Blogspot with contextual DoFollow backlinks.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\AI\Gemini;
use Exception;
use Throwable;

class BloggerService {

    private string $blogId;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $historyFile;

    public function __construct() {
        $this->blogId       = Env::get('BLOGGER_BLOG_ID', '6238274167720956144');
        $this->clientId     = Env::get('BLOGGER_CLIENT_ID', '');
        $this->clientSecret = Env::get('BLOGGER_CLIENT_SECRET', '');
        $this->refreshToken = Env::get('BLOGGER_REFRESH_TOKEN', '');

        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $this->historyFile = $cacheDir . '/blogger_syndicated.json';
    }

    /**
     * Get fresh Google OAuth2 Access Token using Refresh Token
     */
    public function getAccessToken(): string {
        if (empty($this->refreshToken)) {
            throw new Exception("Blogger Refresh Token is missing. Run cron/blogger-auth-setup.php first.");
        }

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type'    => 'refresh_token'
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $err = $data['error_description'] ?? ($data['error'] ?? 'OAuth token refresh failed');
            Logger::error("Blogger OAuth Error: {$err}");
            throw new Exception("Blogger OAuth Token Error: {$err}");
        }

        return $data['access_token'];
    }

    /**
     * Publish a new post to Blogger via API v3
     */
    public function publishPost(string $title, string $contentHtml, array $labels = []): array {
        $accessToken = $this->getAccessToken();
        $url = "https://www.googleapis.com/blogger/v3/blogs/{$this->blogId}/posts/";

        $payload = [
            'kind'    => 'blogger#post',
            'title'   => $title,
            'content' => $contentHtml,
            'labels'  => !empty($labels) ? array_values(array_unique($labels)) : ['Education', 'Sarkari Alerts']
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['id'])) {
            $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}: Failed to insert Blogger post";
            Logger::error("Blogger API Post Error: {$errMsg}");
            throw new Exception("Blogger API Error: {$errMsg}");
        }

        return [
            'success'   => true,
            'post_id'   => $data['id'],
            'url'       => $data['url'] ?? '',
            'title'     => $data['title'] ?? $title,
            'published' => $data['published'] ?? date('c')
        ];
    }

    /**
     * Syndicate the latest published Sarkari.online article to Blogger
     */
    public function syndicateLatest(int $limit = 1): array {
        $history = $this->loadHistory();
        $syndicatedIds = array_column($history, 'article_id');

        $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, c.name AS category_name
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                WHERE a.status = 'published'
                ORDER BY a.published_at DESC LIMIT 15";

        $candidates = Database::fetchAll($sql);
        $results = [];
        $count = 0;

        foreach ($candidates as $article) {
            $artId = (int)$article['id'];
            if (in_array($artId, $syndicatedIds, true)) {
                continue; // Already syndicated
            }

            try {
                Logger::info("Blogger: Generating companion post for Article #{$artId} '{$article['title']}'");
                $companion = $this->generateCompanionContent($article);

                $postResult = $this->publishPost(
                    $companion['title'],
                    $companion['content'],
                    [$article['category_name'], 'Sarkari Result', 'Official Update']
                );

                $record = [
                    'article_id'    => $artId,
                    'original_slug' => $article['slug'],
                    'blogger_id'    => $postResult['post_id'],
                    'blogger_url'   => $postResult['url'],
                    'title'         => $postResult['title'],
                    'syndicated_at' => date('Y-m-d H:i:s')
                ];

                $history[] = $record;
                $this->saveHistory($history);

                Logger::info("Blogger: Successfully syndicated Article #{$artId} -> {$postResult['url']}");
                $results[] = $record;
                $count++;

                if ($count >= $limit) {
                    break;
                }

                sleep(2); // Rate limit pacing
            } catch (Throwable $e) {
                Logger::error("Blogger syndication failed for #{$artId}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Generate unique, non-duplicate companion content with contextual backlink
     */
    private function generateCompanionContent(array $article): array {
        $canonicalUrl = "https://sarkari.online/article/{$article['slug']}/";
        $articleTitle = $article['title'];
        $cleanSnippet = strip_tags(mb_substr($article['content'], 0, 1000));

        $prompt = <<<PROMPT
You are a senior education journalist writing an authoritative, concise news summary for a high-DA education blog.
Based on the following verified article details:

ORIGINAL TITLE: {$articleTitle}
CATEGORY: {$article['category_name']}
SOURCE CONTEXT: {$cleanSnippet}
TARGET CANONICAL URL: {$canonicalUrl}

REQUIREMENTS:
1. Write a completely ORIGINAL, non-duplicate 350-450 word companion briefing in clean HTML format.
2. Structure with clear <h3> subheadings, concise bullet points, and key takeaways.
3. Natural Contextual Backlink: Naturally integrate an anchor link pointing directly to {$canonicalUrl} using relevant anchor keywords (such as "detailed official notification & direct application guide on Sarkari.online" or "complete eligibility criteria at Sarkari.online").
4. Never copy text verbatim from the source context.
5. Tone: Highly informative, factual, professional Indian English.
6. Use HTML tags: <p>, <h3>, <ul>, <li>, <strong>, <a href="{$canonicalUrl}">.

Output strictly valid JSON:
{
  "title": "A fresh, compelling blog headline (different from original title)",
  "content": "Full HTML content with embedded contextual link to Sarkari.online"
}
PROMPT;

        try {
            $aiResponse = Gemini::generateJson($prompt);
            if (!empty($aiResponse['title']) && !empty($aiResponse['content'])) {
                return $aiResponse;
            }
        } catch (Throwable $e) {
            Logger::warning("Gemini companion generation fallback: " . $e->getMessage());
        }

        // Safe Fallback Content
        $fallbackTitle = "Latest Update: " . $articleTitle;
        $fallbackHtml  = "<p>" . htmlspecialchars($article['excerpt']) . "</p>";
        $fallbackHtml .= "<p>Candidates preparing for this examination or recruitment drive can check the comprehensive step-by-step instructions, eligibility requirements, and direct apply link on <a href=\"{$canonicalUrl}\" target=\"_blank\">Sarkari.online</a>.</p>";

        return ['title' => $fallbackTitle, 'content' => $fallbackHtml];
    }

    private function loadHistory(): array {
        if (file_exists($this->historyFile)) {
            $data = json_decode(file_get_contents($this->historyFile), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private function saveHistory(array $history): void {
        @file_put_contents($this->historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
