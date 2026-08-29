<?php
/**
 * Sarkari.online — WordPress.com Syndication Engine
 *
 * Publishes SEO-optimized companion articles to WordPress.com (DA 95)
 * with canonical URLs, proper meta, categories, tags, and contextual
 * DoFollow backlinks pointing back to Sarkari.online.
 *
 * SEO Features:
 *  - canonical_url → prevents duplicate content penalty
 *  - excerpt → used as meta description
 *  - categories + tags → topical relevance signals
 *  - featured_media via upload → rich snippet thumbnail
 *  - slug → keyword-rich URL
 *  - yoast_head / jetpack_seo support via post meta
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use App\AI\Gemini;
use Throwable;

class WordPressService
{
    private string $accessToken;
    private string $blogId;
    private string $blogUrl;
    private string $stateFile;
    private string $apiBase;

    public function __construct()
    {
        $this->accessToken = (string) Env::get('WORDPRESS_ACCESS_TOKEN', '');
        $this->blogId      = (string) Env::get('WORDPRESS_BLOG_ID', '257038678');
        $this->blogUrl     = rtrim((string) Env::get('WORDPRESS_BLOG_URL', 'https://sarkarionlinealert.wordpress.com'), '/');

        // Fallback: read from storage/cache/wp_credentials.json if .env token missing
        if (empty($this->accessToken)) {
            $cacheFile = dirname(__DIR__, 2) . '/storage/cache/wp_credentials.json';
            if (file_exists($cacheFile)) {
                $creds = json_decode(file_get_contents($cacheFile), true) ?? [];
                $this->accessToken = $creds['WORDPRESS_ACCESS_TOKEN'] ?? '';
                $this->blogId      = $creds['WORDPRESS_BLOG_ID']      ?? $this->blogId;
                $this->blogUrl     = rtrim($creds['WORDPRESS_BLOG_URL'] ?? $this->blogUrl, '/');
            }
        }

        $this->stateFile   = dirname(__DIR__, 2) . '/storage/cache/wordpress_syndicated.json';
        $this->apiBase     = "https://public-api.wordpress.com/rest/v1.1/sites/{$this->blogId}";
    }

    /* ---------------------------------------------------------------
     * PUBLIC: Syndicate latest N unsyndicated articles
     * ------------------------------------------------------------- */
    public function syndicateLatest(int $limit = 2): array
    {
        if (empty($this->accessToken)) {
            throw new \RuntimeException('WORDPRESS_ACCESS_TOKEN is not set in .env');
        }

        $history       = $this->loadHistory();
        $today         = date('Y-m-d');
        $todayCount    = 0;

        foreach ($history as $h) {
            if (str_starts_with($h['syndicated_at'] ?? '', $today)) {
                $todayCount++;
            }
        }

        if ($todayCount >= $limit) {
            Logger::info("WordPressService: Daily quota ({$limit}/day) already reached.");
            return ['status' => 'quota_reached', 'items' => []];
        }

        $allowedSlots   = $limit - $todayCount;
        $syndicatedIds  = array_column($history, 'article_id');

        $articles = Database::fetchAll(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.content,
                    a.featured_image, a.published_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM articles a
             JOIN categories c ON a.category_id = c.id
             WHERE a.status = 'published'
             ORDER BY a.published_at DESC
             LIMIT 20"
        );

        $syndicated = [];

        foreach ($articles as $art) {
            if (count($syndicated) >= $allowedSlots) break;

            $artId = (int) $art['id'];
            if (in_array($artId, $syndicatedIds, true)) continue;

            try {
                $result = $this->publishPost($art);
                if (!empty($result['url'])) {
                    $record = [
                        'article_id'    => $artId,
                        'title'         => $art['title'],
                        'sarkari_url'   => url('article/' . $art['slug'] . '/'),
                        'wordpress_url' => $result['url'],
                        'wp_post_id'    => $result['post_id'],
                        'syndicated_at' => date('Y-m-d H:i:s'),
                    ];
                    $history[]    = $record;
                    $syndicated[] = $record;
                    Logger::info("WordPressService: Published Article #{$artId} → {$result['url']}");
                }
            } catch (Throwable $e) {
                Logger::error("WordPressService failed on Article #{$artId}: " . $e->getMessage());
            }

            sleep(3);
        }

        $this->saveHistory($history);

        return [
            'status'           => 'success',
            'syndicated_count' => count($syndicated),
            'items'            => $syndicated,
        ];
    }

    /* ---------------------------------------------------------------
     * PRIVATE: Generate content with Gemini + publish to WordPress.com
     * ------------------------------------------------------------- */
    private function publishPost(array $art): array
    {
        $canonicalUrl  = url('article/' . $art['slug'] . '/');
        $categoryUrl   = url('category/' . $art['category_slug'] . '/');
        $siteUrl       = SITE_URL;
        $cleanSnippet  = strip_tags(mb_substr($art['content'], 0, 2500));

        // ── Step 1: Generate 800-word SEO article via Gemini ──────────
        $gemini = new Gemini();
        $prompt = <<<PROMPT
You are a senior Indian education journalist writing for WordPress.com.

Write a 100% ORIGINAL 800–1000 word companion article in MARKDOWN for this topic:

TITLE: "{$art['title']}"
CATEGORY: {$art['category_name']}
CANONICAL SOURCE: {$canonicalUrl}
CONTEXT: {$cleanSnippet}

STRICT SEO RULES:
1. Original content — no verbatim copy.
2. Structure: H2 intro, H3 subheadings, bullet lists, one comparison table.
3. Naturally embed 3 contextual DoFollow backlinks:
   - Primary: [{$art['title']}]({$canonicalUrl}) — use exact title or long-tail keyword anchor
   - Secondary: [Sarkari.online]({$siteUrl}) — use branded or category anchor
   - Tertiary: [latest {$art['category_name']} updates]({$categoryUrl})
4. Meta description (150–160 chars): compelling summary with primary keyword.
5. Focus keyword in: first 100 words, at least 2 H3s, and conclusion.
6. No markdown code fences — clean markdown only.
7. End with a strong CTA paragraph linking back to {$canonicalUrl}.

OUTPUT FORMAT (strict JSON, no extra text):
{
  "title": "SEO headline under 65 chars with primary keyword",
  "slug": "keyword-rich-url-slug-here",
  "meta_description": "150-160 char meta description with focus keyword",
  "focus_keyword": "primary focus keyword",
  "body_markdown": "full markdown article...",
  "tags": ["education", "india", "tag3", "tag4", "tag5"],
  "category": "{$art['category_name']}"
}
PROMPT;

        $res      = $gemini->generateJson($prompt, ['stage' => 'wordpress_backlink', 'temperature' => 0.2]);
        $data     = $res['data'] ?? [];

        $postTitle    = $data['title']           ?? $art['title'];
        $slug         = $data['slug']             ?? $art['slug'] . '-guide';
        $metaDesc     = $data['meta_description'] ?? ($art['excerpt'] ?: substr(strip_tags($art['content']), 0, 155));
        $focusKw      = $data['focus_keyword']    ?? $art['title'];
        $bodyMarkdown = $data['body_markdown']    ?? '';
        $tags         = array_slice($data['tags'] ?? ['education', 'india', 'sarkari', 'career', 'exams'], 0, 10);

        if (empty($bodyMarkdown)) {
            throw new \RuntimeException("Gemini returned empty body for WordPress post.");
        }

        // ── Step 2: Convert Markdown → HTML for WordPress ────────────
        $bodyHtml = $this->markdownToHtml($bodyMarkdown);

        // ── Step 3: Resolve thumbnail image URL ──────────────────────
        $featImg = $art['featured_image'] ?? '';
        if (empty($featImg)) {
            $featImg = 'uploads/thumbnails/' . $art['category_slug'] . '/' . $art['slug'] . '.png';
        } else {
            $featImg = preg_replace('/\.webp$/i', '.png', $featImg);
        }
        $imageUrl = str_starts_with($featImg, 'http')
            ? $featImg
            : 'https://sarkari.online/' . ltrim($featImg, '/');

        // Add thumbnail at top of content
        $featuredHtml = '<figure class="wp-block-image size-large">'
            . '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($postTitle) . '" '
            . 'style="max-width:100%;height:auto;border-radius:8px;" />'
            . '<figcaption>' . htmlspecialchars($postTitle) . ' — Sarkari.online</figcaption>'
            . '</figure>' . "\n\n";

        $fullContent = $featuredHtml . $bodyHtml;

        // ── Step 4: Build SEO-optimised Yoast/Jetpack meta ───────────
        $postMeta = [
            // Yoast SEO (if plugin active)
            '_yoast_wpseo_title'       => $postTitle . ' | Sarkari.online',
            '_yoast_wpseo_metadesc'    => $metaDesc,
            '_yoast_wpseo_focuskw'     => $focusKw,
            '_yoast_wpseo_canonical'   => $canonicalUrl,
            // Jetpack / Genesis
            'advanced_seo_description' => $metaDesc,
        ];

        // ── Step 5: Publish via WordPress.com REST API v1.1 ──────────
        $payload = [
            'title'          => $postTitle,
            'content'        => $fullContent,
            'excerpt'        => $metaDesc,
            'slug'           => $slug,
            'status'         => 'publish',
            'format'         => 'standard',
            'categories'     => $art['category_name'],
            'tags'           => implode(',', $tags),
            'metadata'       => array_map(
                fn($k, $v) => ['key' => $k, 'value' => $v, 'operation' => 'update'],
                array_keys($postMeta), $postMeta
            ),
        ];

        $ch = curl_init("{$this->apiBase}/posts/new");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'User-Agent: SarkariOnline-WP-Syndication/2.0',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new \RuntimeException("WordPress API Error (HTTP {$httpCode}): " . substr($response, 0, 400));
        }

        $json = json_decode($response, true);
        $wpUrl    = $json['URL']   ?? $json['url']  ?? '';
        $wpPostId = $json['ID']    ?? $json['id']   ?? 0;

        if (empty($wpUrl)) {
            throw new \RuntimeException("WordPress API returned no post URL. Response: " . substr($response, 0, 300));
        }

        return [
            'post_id' => $wpPostId,
            'url'     => $wpUrl,
            'title'   => $json['title'] ?? $postTitle,
        ];
    }

    /* ---------------------------------------------------------------
     * PRIVATE: Lightweight Markdown → HTML converter
     * ------------------------------------------------------------- */
    private function markdownToHtml(string $md): string
    {
        // Headers
        $md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
        $md = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $md);
        $md = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $md);

        // Bold / Italic
        $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
        $md = preg_replace('/\*(.+?)\*/',     '<em>$1</em>',         $md);

        // Links
        $md = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="dofollow">$1</a>', $md);

        // Bullet lists
        $md = preg_replace_callback('/^(\- .+\n?)+/m', function ($m) {
            $items = preg_replace('/^- (.+)/m', '<li>$1</li>', trim($m[0]));
            return "<ul>\n{$items}\n</ul>\n";
        }, $md);

        // Ordered lists
        $md = preg_replace_callback('/^(\d+\. .+\n?)+/m', function ($m) {
            $items = preg_replace('/^\d+\. (.+)/m', '<li>$1</li>', trim($m[0]));
            return "<ol>\n{$items}\n</ol>\n";
        }, $md);

        // Tables (GFM)
        $md = preg_replace_callback('/(\|.+\|\n)+/m', function ($m) {
            $rows  = array_filter(explode("\n", trim($m[0])));
            $html  = '<table style="width:100%;border-collapse:collapse;">';
            $first = true;
            foreach ($rows as $row) {
                if (preg_match('/^\|[-| ]+\|$/', trim($row))) continue;
                $cells = array_map('trim', explode('|', trim($row, '| ')));
                if ($first) {
                    $html .= '<thead><tr>' . implode('', array_map(fn($c) => "<th style=\"border:1px solid #ddd;padding:8px;background:#f5f5f5;\">$c</th>", $cells)) . '</tr></thead><tbody>';
                    $first = false;
                } else {
                    $html .= '<tr>' . implode('', array_map(fn($c) => "<td style=\"border:1px solid #ddd;padding:8px;\">$c</td>", $cells)) . '</tr>';
                }
            }
            return $html . '</tbody></table>' . "\n";
        }, $md);

        // Paragraphs
        $blocks = preg_split('/\n{2,}/', $md);
        $html   = '';
        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;
            if (preg_match('/^<(h[1-6]|ul|ol|table|figure)/', $block)) {
                $html .= $block . "\n";
            } else {
                $html .= '<p>' . nl2br($block) . "</p>\n";
            }
        }

        return $html;
    }

    /* ---------------------------------------------------------------
     * PRIVATE: History helpers
     * ------------------------------------------------------------- */
    private function loadHistory(): array
    {
        if (!file_exists($this->stateFile)) return [];
        $data = json_decode(file_get_contents($this->stateFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveHistory(array $history): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents(
            $this->stateFile,
            json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
