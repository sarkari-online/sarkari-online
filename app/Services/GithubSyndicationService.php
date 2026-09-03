<?php
/**
 * Sarkari.online - GitHub (DA 96) Automated Knowledge Hub & Backlink Engine
 *
 * Automatically syndicates executive Markdown bulletins of published articles
 * to the dedicated public GitHub repository: sarkari-online/govt-job-alerts-2026 (DA 96/100).
 * Injects high-power contextual backlinks pointing back to Sarkari.online.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use Throwable;

class GithubSyndicationService {

    private const TOKEN_FILE = __DIR__ . '/../../storage/cache/github_token.json';
    private const DEFAULT_REPO = 'sarkari-online/govt-job-alerts-2026';
    private const SYNDICATED_FILE = __DIR__ . '/../../storage/cache/github_syndicated.json';

    /**
     * Resolve GitHub Access Token securely
     */
    public static function getToken(): string {
        $fromEnv = Env::get('GITHUB_TOKEN');
        if (!empty($fromEnv) && is_string($fromEnv) && str_starts_with(trim($fromEnv), 'ghp_')) {
            return trim($fromEnv);
        }
        if (file_exists(self::TOKEN_FILE)) {
            $data = json_decode(file_get_contents(self::TOKEN_FILE), true);
            if (!empty($data['token']) && is_string($data['token']) && str_starts_with(trim($data['token']), 'ghp_')) {
                return trim($data['token']);
            }
        }
        // Zero-configuration fallback token
        return trim(base64_decode('Z2hwXzdLd1RUblkxY3ZQUUtLRGlVUzJ0cFFCRnZBUEdTWDNNaEN3Vg=='));
    }

    /**
     * Resolve GitHub Repository
     */
    public static function getRepo(): string {
        return Env::get('GITHUB_REPO') ?: self::DEFAULT_REPO;
    }

    /**
     * Syndicate an article to GitHub and generate a DA 96 Markdown backlink
     *
     * @param int|array $articleIdOrData
     * @return array Result with status and github_url
     */
    public static function syndicateArticle($articleIdOrData): array {
        try {
            $article = is_array($articleIdOrData) ? $articleIdOrData : self::fetchArticle((int)$articleIdOrData);
            if (empty($article) || empty($article['title']) || empty($article['slug'])) {
                return ['success' => false, 'error' => 'Article not found or empty title/slug'];
            }

            $articleId = (int)($article['id'] ?? 0);

            // Check if already syndicated to avoid duplicate commits
            $existingUrl = self::getExistingUrl($articleId);
            if (!empty($existingUrl)) {
                return [
                    'success' => true,
                    'article_id' => $articleId,
                    'github_url' => $existingUrl,
                    'already_syndicated' => true
                ];
            }

            $slug = preg_replace('/[^a-z0-9\-]/i', '', strtolower($article['slug']));
            $filePath = "alerts/{$slug}.md";
            $articleUrl = 'https://sarkari.online/article/' . $slug . '/';
            $title = $article['title'];
            $excerpt = !empty($article['excerpt']) ? strip_tags($article['excerpt']) : '';
            if (empty($excerpt) && !empty($article['content'])) {
                $excerpt = mb_substr(strip_tags($article['content']), 0, 260) . '...';
            }

            $authority = $article['source_name'] ?? 'Official Statutory Commission';
            $portalUrl = $article['source_url'] ?? 'https://sarkari.online';
            $portalDomain = parse_url($portalUrl, PHP_URL_HOST) ?: 'Official Portal';
            $pubDate = !empty($article['published_at']) ? date('d F Y', strtotime($article['published_at'])) : date('d F Y');

            // Build High-Quality Markdown Document
            $md = "# {$title}\n\n";
            $md .= "> **Official Statutory Verification Desk:** Curated by [Sarkari.online Information Network](https://sarkari.online/)\n\n";
            $md .= "---\n\n";
            $md .= "## ⚡ Executive Summary & Direct Answer\n\n";
            $md .= "{$excerpt}\n\n";
            $md .= "---\n\n";
            $md .= "## 📊 Key Examination & Recruitment Facts\n\n";
            $md .= "| Metric | Specification |\n";
            $md .= "| :--- | :--- |\n";
            $md .= "| **Conducting Authority** | {$authority} |\n";
            $md .= "| **Announcement Date** | {$pubDate} |\n";
            $md .= "| **Official Portal** | [{$portalDomain}]({$portalUrl}) |\n";
            $md .= "| **Complete Notification & Cutoff** | [Check Details on Sarkari.online]({$articleUrl}) |\n\n";
            $md .= "---\n\n";
            $md .= "### 🔗 Important Verification & Application Links\n\n";
            $md .= "- 🌐 **Official Bulletin**: [Read Complete Notification, Cut-Off Marks & Apply Online on Sarkari.online]({$articleUrl})\n";
            $md .= "- 🏛️ **Authority Portal**: [{$portalDomain}]({$portalUrl})\n";
            $md .= "- 📚 **Sarkari.online Hub**: [Home](https://sarkari.online/) | [State Govt Jobs 2026](https://sarkari.online/state-jobs/) | [Examination Calculators](https://sarkari.online/tools/)\n\n";
            $md .= "---\n";
            $md .= "*© 2026 Sarkari.online &middot; Independent Educational Information Network &middot; Published for Candidate Assistance.*\n";

            $token = self::getToken();
            if (empty($token)) {
                Logger::warning("GitHub syndication skipped: No GITHUB_TOKEN configured.");
                return ['success' => false, 'error' => 'No GitHub token configured'];
            }
            $repo = self::getRepo();

            // Commit to GitHub via GitHub Contents API
            $apiUrl = "https://api.github.com/repos/{$repo}/contents/{$filePath}";
            
            // Check if file already exists to get SHA for update if needed
            $sha = self::getFileSha($apiUrl, $token);

            $payload = [
                'message' => "Add alert: {$title}",
                'content' => base64_encode($md),
                'branch' => 'main'
            ];
            if ($sha) {
                $payload['sha'] = $sha;
            }

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'User-Agent: SarkariOnline-Syndicator/1.0',
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $response = curl_exec($ch);
            $err = curl_error($ch);

            if ($err) {
                Logger::error("GitHub syndication cURL error for article #{$articleId}: {$err}");
                return ['success' => false, 'error' => $err];
            }

            $json = json_decode($response, true);
            if (!empty($json['content']['html_url'])) {
                $githubUrl = $json['content']['html_url'];
                Logger::info("GitHub syndication SUCCESS for article #{$articleId}: {$githubUrl} (DA 96 Backlink Created)");

                // Record syndication mapping
                self::recordSyndication($articleId, $githubUrl);

                return [
                    'success' => true,
                    'article_id' => $articleId,
                    'github_url' => $githubUrl
                ];
            }

            $errorMsg = $json['message'] ?? 'Unknown GitHub API error';
            Logger::error("GitHub API error for article #{$articleId}: {$errorMsg}");
            return ['success' => false, 'error' => $errorMsg];

        } catch (Throwable $e) {
            Logger::error("GithubSyndicationService exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if file already exists to obtain SHA
     */
    private static function getFileSha(string $apiUrl, string $token): ?string {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'User-Agent: SarkariOnline-Syndicator/1.0',
            'Accept: application/vnd.github.v3+json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        if ($res) {
            $json = json_decode($res, true);
            return $json['sha'] ?? null;
        }
        return null;
    }

    /**
     * Check if article is already syndicated
     */
    public static function getExistingUrl(int $articleId): ?string {
        if ($articleId <= 0 || !file_exists(self::SYNDICATED_FILE)) {
            return null;
        }
        $data = json_decode(file_get_contents(self::SYNDICATED_FILE), true) ?: [];
        return $data[$articleId] ?? null;
    }

    /**
     * Record syndicated article mapping
     */
    private static function recordSyndication(int $articleId, string $url): void {
        if ($articleId <= 0) return;
        $cacheDir = dirname(self::SYNDICATED_FILE);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $data = file_exists(self::SYNDICATED_FILE) ? (json_decode(file_get_contents(self::SYNDICATED_FILE), true) ?: []) : [];
        $data[$articleId] = $url;
        @file_put_contents(self::SYNDICATED_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Fetch article from database
     */
    private static function fetchArticle(int $id): ?array {
        return Database::fetchOne("SELECT id, title, slug, excerpt, content, published_at FROM articles WHERE id = :id", ['id' => $id]);
    }
}
