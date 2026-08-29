<?php
/**
 * Sarkari.online - Autonomous Blogger Syndication Cron
 */
if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Services\BloggerService;
use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Starting Blogger Syndication Pipeline...\n";

// Step 1: DB check
try {
    $count = Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'");
    echo "✅ DB connected. Published articles: {$count}\n";
} catch (\Throwable $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Fetch latest article directly
try {
    $article = Database::fetchOne(
        "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.featured_image,
                c.name AS category_name, c.slug AS category_slug
         FROM articles a
         JOIN categories c ON a.category_id = c.id
         WHERE a.status = 'published'
         ORDER BY a.published_at DESC LIMIT 1"
    );

    if (!$article) {
        echo "❌ No published articles found in DB.\n";
        exit(1);
    }

    echo "✅ Latest article: [{$article['id']}] {$article['title']}\n";

    // Step 3: Resolve thumbnail URL
    $catSlug = $article['category_slug'] ?? '';
    $featImg = $article['featured_image'];
    if (empty($featImg)) {
        $featImg = 'uploads/thumbnails/' . ($catSlug ? $catSlug . '/' : '') . $article['slug'] . '.png';
    } else {
        $featImg = preg_replace('/\.webp$/i', '.png', $featImg);
    }
    $imageUrl = str_starts_with($featImg, 'http') ? $featImg : "https://sarkari.online/" . ltrim($featImg, '/');
    echo "🖼️  Thumbnail URL: {$imageUrl}\n";

    // Step 4: Check token
    $service = new BloggerService();
    $token = $service->getAccessToken();
    echo "✅ OAuth token obtained (" . strlen($token) . " chars)\n";

    // Step 5: Generate Gemini content
    echo "🤖 Generating 800-word companion content with Gemini...\n";
    $gemini = new \App\AI\Gemini();
    $canonicalUrl = "https://sarkari.online/article/{$article['slug']}/";
    $cleanSnippet = strip_tags(mb_substr($article['content'], 0, 2000));

    $prompt = <<<PROMPT
You are a senior education journalist. Write a comprehensive 700-900 word feature article in clean HTML for the Google Blogger platform.

SOURCE:
Title: {$article['title']}
Category: {$article['category_name']}
Canonical URL: {$canonicalUrl}
Context: {$cleanSnippet}

RULES:
1. Original content only - do NOT copy text verbatim.
2. Rich structure: opening <p>, <h3> subheadings, <table> for key dates, <ol> for steps, <ul> for eligibility.
3. Embed 2-3 natural contextual backlinks to {$canonicalUrl}.
4. Professional Indian English tone.
5. No markdown. Only HTML tags.

OUTPUT FORMAT:
Line 1 exactly: TITLE: [Your compelling headline here]
Lines 2+: Full HTML body content.
PROMPT;

    $res = $gemini->generate($prompt, ['stage' => 'blogger', 'temperature' => 0.2]);
    $rawText = trim($res['text'] ?? '');

    if (empty($rawText)) {
        echo "❌ Gemini returned empty response.\n";
        exit(1);
    }

    $lines = explode("\n", $rawText);
    $postTitle = trim(substr($lines[0], 6));
    $bodyHtml = implode("\n", array_slice($lines, 1));
    $bodyHtml = preg_replace(['/^```(?:html)?\s*/i', '/\s*```$/'], '', trim($bodyHtml));

    echo "✅ Gemini content ready: " . str_word_count(strip_tags($bodyHtml)) . " words\n";
    echo "📝 Post title: {$postTitle}\n";

    // Step 6: Build full content with thumbnail image
    $imageHtml = "<div style=\"text-align:center;margin-bottom:25px;\"><img src=\"{$imageUrl}\" alt=\"{$article['title']}\" style=\"max-width:100%;height:auto;border-radius:8px;\" /></div>\n";
    $fullContent = $imageHtml . $bodyHtml;

    // Step 7: Publish to Blogger
    echo "📤 Publishing to Blogger API...\n";
    $result = $service->publishPost($postTitle, $fullContent, [$article['category_name'], 'Sarkari Result', 'Education']);

    echo "🎉 SUCCESS!\n";
    echo "   Title : {$result['title']}\n";
    echo "   URL   : {$result['url']}\n";
    echo "   ID    : {$result['post_id']}\n";

    // Save to history to prevent re-publish
    $historyFile = dirname(__DIR__) . '/storage/cache/blogger_syndicated.json';
    $history = file_exists($historyFile) ? (json_decode(file_get_contents($historyFile), true) ?: []) : [];
    $history[] = [
        'article_id'    => (int)$article['id'],
        'original_slug' => $article['slug'],
        'blogger_id'    => $result['post_id'],
        'blogger_url'   => $result['url'],
        'title'         => $result['title'],
        'syndicated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    Logger::error("Blogger sync failed: " . $e->getMessage());
    exit(1);
}

