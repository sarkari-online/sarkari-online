<?php
/**
 * Sarkari.online - Telegra.ph (DA 92) Automated Backlink Syndication Engine
 *
 * Automatically syndicates executive editorial summaries of published articles
 * to Telegram's Telegra.ph platform (Domain Authority 92/100).
 * Injects natural contextual backlinks pointing back to Sarkari.online.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class TelegraphSyndicationService {

    private const TOKEN_FILE = __DIR__ . '/../../storage/cache/telegraph_token.json';
    private const SYNDICATED_FILE = __DIR__ . '/../../storage/cache/telegraph_syndicated.json';
    private const DEFAULT_TOKEN = 'ab7c60099f5004f3f5ef63eeb6367f369cf5faff19ceb0445a3b74b48f60';

    /**
     * Syndicate an article to Telegra.ph and generate a high-DA backlink
     *
     * @param int|array $articleIdOrData
     * @return array Result with status and telegraph_url
     */
    public static function syndicateArticle($articleIdOrData): array {
        try {
            $article = is_array($articleIdOrData) ? $articleIdOrData : self::fetchArticle((int)$articleIdOrData);
            if (empty($article) || empty($article['title'])) {
                return ['success' => false, 'error' => 'Article not found or empty title'];
            }

            $articleId = (int)($article['id'] ?? 0);

            // Check if already syndicated to avoid duplicate spam
            $existingUrl = self::getExistingUrl($articleId);
            if (!empty($existingUrl)) {
                return [
                    'success' => true,
                    'article_id' => $articleId,
                    'telegraph_url' => $existingUrl,
                    'already_syndicated' => true
                ];
            }

            $token = self::getAccessToken();
            if (empty($token)) {
                return ['success' => false, 'error' => 'Failed to obtain Telegraph access token'];
            }

            $articleUrl = 'https://sarkari.online/article/' . ($article['slug'] ?? '') . '/';
            $siteUrl = 'https://sarkari.online';
            $title = mb_substr($article['title'], 0, 120);
            $excerpt = !empty($article['excerpt']) ? strip_tags($article['excerpt']) : '';
            if (empty($excerpt) && !empty($article['content'])) {
                $excerpt = mb_substr(strip_tags($article['content']), 0, 240) . '...';
            }

            // Build clean Telegraph AST Content Nodes
            $nodes = [
                [
                    'tag' => 'p',
                    'children' => [
                        ['tag' => 'strong', 'children' => ['Official Gazette & Examination Bulletin: ']],
                        $excerpt
                    ]
                ],
                [
                    'tag' => 'h3',
                    'children' => ['Executive Summary & Statutory Highlights']
                ],
                [
                    'tag' => 'p',
                    'children' => [
                        "Candidate recruitment schedules, cut-off marks, admit card windows, and direct verification procedures for this notification have been verified by the editorial desk at Sarkari.online."
                    ]
                ],
                [
                    'tag' => 'p',
                    'children' => [
                        ['tag' => 'strong', 'children' => ['👉 Official Verification & Direct Access: ']],
                        [
                            'tag' => 'a',
                            'attrs' => ['href' => $articleUrl],
                            'children' => ["Read Full Notification, Cut-Off Marks & Direct Portal Link on Sarkari.online"]
                        ]
                    ]
                ],
                [
                    'tag' => 'hr',
                    'children' => []
                ],
                [
                    'tag' => 'p',
                    'children' => [
                        ['tag' => 'em', 'children' => [
                            "Published independently by ",
                            [
                                'tag' => 'a',
                                'attrs' => ['href' => $siteUrl],
                                'children' => ["Sarkari.online"]
                            ],
                            " — India's premier educational updates and statutory examination network."
                        ]]
                    ]
                ]
            ];

            $postData = [
                'access_token' => $token,
                'title' => $title,
                'author_name' => 'Sarkari.online Editorial Desk',
                'author_url' => $siteUrl,
                'content' => json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'return_content' => false
            ];

            $ch = curl_init('https://api.telegra.ph/createPage');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $response = curl_exec($ch);
            $err = curl_error($ch);

            if ($err) {
                Logger::error("Telegraph syndication cURL error for article #{$articleId}: {$err}");
                return ['success' => false, 'error' => $err];
            }

            $json = json_decode($response, true);
            if (!empty($json['ok']) && !empty($json['result']['url'])) {
                $telegraphUrl = $json['result']['url'];
                Logger::info("Telegraph syndication SUCCESS for article #{$articleId}: {$telegraphUrl} (DA 92 Backlink Created)");

                // Record syndication mapping
                self::recordSyndication($articleId, $telegraphUrl);

                return [
                    'success' => true,
                    'article_id' => $articleId,
                    'telegraph_url' => $telegraphUrl,
                    'path' => $json['result']['path'] ?? ''
                ];
            }

            $errorMsg = $json['error'] ?? 'Unknown error';
            Logger::error("Telegraph API error for article #{$articleId}: {$errorMsg}");
            return ['success' => false, 'error' => $errorMsg];

        } catch (Throwable $e) {
            Logger::error("TelegraphSyndicationService exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get or create valid Telegraph access token
     */
    public static function getAccessToken(): string {
        $cacheDir = dirname(self::TOKEN_FILE);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        if (file_exists(self::TOKEN_FILE)) {
            $data = json_decode(file_get_contents(self::TOKEN_FILE), true);
            if (!empty($data['access_token'])) {
                return $data['access_token'];
            }
        }

        // Return default valid token and save it
        $token = self::DEFAULT_TOKEN;
        @file_put_contents(self::TOKEN_FILE, json_encode(['access_token' => $token, 'created_at' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT));
        return $token;
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
