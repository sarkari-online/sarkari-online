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
    public function syndicateLatest(int $limit = 1, bool $force = false): array {
        $history = $force ? [] : $this->loadHistory();

        if (!$force) {
            // STRICT SAFETY: Max 1 article per day to Blogger (Zero Duplicates)
            $today = date('Y-m-d');
            foreach ($history as $h) {
                if (!empty($h['syndicated_at']) && str_starts_with($h['syndicated_at'], $today)) {
                    Logger::info("Blogger: Daily limit of 1 post already reached for today ({$today}).");
                    return [];
                }
            }
        }

        $syndicatedIds = array_column($history, 'article_id');

        $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.featured_image, c.name AS category_name, c.slug AS category_slug
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
     * Generate unique, non-duplicate 700-1000 word companion content with embedded PNG image & rich backlinks
     */
    private function generateCompanionContent(array $article): array {
        $canonicalUrl = "https://sarkari.online/article/{$article['slug']}/";
        $articleTitle = $article['title'];
        $cleanSnippet = strip_tags(mb_substr($article['content'], 0, 2000));
        
        // Resolve featured image URL — convert to PNG for Blogger compatibility
        $featuredImage = $article['featured_image'] ?? null;
        if (empty($featuredImage)) {
            $catPrefix = !empty($article['category_slug']) ? $article['category_slug'] . '/' : '';
            $featuredImage = 'uploads/thumbnails/' . $catPrefix . $article['slug'] . '.png';
        } else {
            $featuredImage = preg_replace('/\.webp$/i', '.png', $featuredImage);
        }
        $imageUrl = str_starts_with($featuredImage, 'http') ? $featuredImage : "https://sarkari.online/" . ltrim($featuredImage, '/');

        $imageHtml = "<div style=\"text-align: center; margin-bottom: 25px;\">" .
                     "<img src=\"{$imageUrl}\" alt=\"{$articleTitle}\" style=\"max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: block; margin: 0 auto;\" />" .
                     "</div>";

        $prompt = <<<PROMPT
You are a senior education journalist writing an authoritative, comprehensive 700 to 1,000-word feature article for a high-DA educational publication on Blogger.

SOURCE ARTICLE DETAILS:
Title: {$articleTitle}
Category: {$article['category_name']}
Target Canonical URL: {$canonicalUrl}
Detailed Background & Context:
{$cleanSnippet}

EDITORIAL REQUIREMENTS:
1. DEPTH & WORD COUNT: Write an in-depth, original 700 to 1,000-word article in clean, semantic HTML.
2. ZERO DUPLICATION: Completely rephrase all explanations, analysis, and roadmaps with original editorial language. Never copy text verbatim.
3. RICH FORMATTING:
   - Engaging opening overview explaining student intent and key takeaways.
   - Clean <table> structure for Key Dates / Examination Milestones.
   - <h3> subheadings for Eligibility Criteria, Educational Qualification, and Selection Process.
   - Clear ordered steps (<ol>) for the Step-by-Step Online Application Procedure.
   - Important instructions / documents checklist.
4. CONTEXTUAL DO-FOLLOW BACKLINKS:
   - Seamlessly embed 2 to 3 natural contextual anchor text links pointing directly to {$canonicalUrl}.
   - Example anchor keywords:
     * "<a href=\"{$canonicalUrl}\" target=\"_blank\">complete official notification PDF and direct application link on Sarkari.online</a>"
     * "<a href=\"{$canonicalUrl}\" target=\"_blank\">detailed subject-wise syllabus and cutoff matrix at Sarkari.online</a>"
5. STRICT HTML TAGS ONLY: Use <h2>, <h3>, <p>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <strong>, <em>, <a>. Do NOT output markdown code blocks (no ```html).

OUTPUT FORMAT:
On the very first line, write:
TITLE: [Your compelling blog headline here]

Then on the lines below it, write the full HTML article body directly.
PROMPT;

        try {
            $gemini = new Gemini();
            $aiResponse = $gemini->generate($prompt, [
                'stage' => 'blogger_syndication',
                'temperature' => 0.2
            ]);

            $rawText = trim($aiResponse['text'] ?? '');
            if (!empty($rawText)) {
                $lines = explode("\n", $rawText);
                $title = "Complete Guide: " . $articleTitle;
                $bodyLines = [];

                foreach ($lines as $i => $line) {
                    if ($i === 0 && stripos($line, 'TITLE:') === 0) {
                        $title = trim(substr($line, 6));
                    } else {
                        $bodyLines[] = $line;
                    }
                }

                $bodyHtml = trim(implode("\n", $bodyLines));
                // Remove any accidental markdown backticks
                $bodyHtml = preg_replace('/^```(?:html)?\s*/i', '', $bodyHtml);
                $bodyHtml = preg_replace('/\s*```$/', '', $bodyHtml);

                if (strlen($bodyHtml) > 500) {
                    $fullContent = $imageHtml . "\n" . $bodyHtml;
                    return [
                        'title'   => $title,
                        'content' => $fullContent
                    ];
                }
            }
        } catch (Throwable $e) {
            Logger::error("Gemini Blogger generation error: " . $e->getMessage());
        }

        // High-Quality Structured Fallback
        $fallbackTitle = "Complete Guide: " . $articleTitle;
        $fallbackHtml  = $imageHtml;
        $fallbackHtml .= "<p class=\"lead\"><strong>{$articleTitle}</strong> has officially been announced with key guidelines, examination dates, and recruitment milestones for aspiring candidates across India.</p>";
        $fallbackHtml .= "<h3>Key Information & Overview</h3>";
        $fallbackHtml .= "<p>" . htmlspecialchars($article['excerpt']) . "</p>";
        $fallbackHtml .= "<h3>Application Guidelines & Direct Verification</h3>";
        $fallbackHtml .= "<p>Candidates can access the <a href=\"{$canonicalUrl}\" target=\"_blank\">complete official notification PDF, eligibility requirements, and direct apply link on Sarkari.online</a> to ensure all eligibility criteria, fee submission deadlines, and document upload parameters are satisfied.</p>";

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
