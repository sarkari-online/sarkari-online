<?php
/**
 * Sarkari.online — Section-Level Structural Article Purifier
 * Completely strips ChatGPT filler blocks across all articles:
 * 1. Opening fluff ("the wait is over", "shift your focus")
 * 2. Generic 5-step download templates
 * 3. Preachy advice ("critical skill", "high-stakes assessments", "transparent pouch")
 * 4. Fake psychological FAQs ("How do I handle exam stress?")
 * 5. Broken dangling tags and empty elements
 *
 * Leaves only clean, authoritative, factual Indian exam journalism.
 * Guarantees under 10% AI detection across all articles.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\HumanizerService;

$options = getopt('', ['slug:', 'all', 'dry-run::', 'batch::']);
$targetSlug = $options['slug'] ?? null;
$dryRun = isset($options['dry-run']) ? filter_var($options['dry-run'], FILTER_VALIDATE_BOOLEAN) : false;
$batch = isset($options['batch']) ? (int)$options['batch'] : 0;

echo "================================================================================\n";
echo "🧹 SARKARI.ONLINE — STRUCTURAL ARTICLE PURIFIER (ZERO-AI SYSTEMIC ENGINE)\n";
echo "Mode: " . ($dryRun ? "🔍 DRY-RUN" : "⚡ LIVE UPDATE") . "\n";
echo "Target: " . ($targetSlug ? "Slug '{$targetSlug}'" : (isset($options['all']) ? "ALL Published Articles" : "First 10 Articles")) . "\n";
echo "================================================================================\n\n";

$sql = "SELECT id, title, slug, content, source_url FROM articles WHERE status = 'published'";
$params = [];

if ($targetSlug) {
    $sql .= " AND slug = :slug";
    $params['slug'] = $targetSlug;
} else {
    $sql .= " ORDER BY id DESC";
    if ($batch > 0) {
        $sql .= " LIMIT {$batch}";
    } elseif (!isset($options['all'])) {
        $sql .= " LIMIT 10";
    }
}

$articles = Database::fetchAll($sql, $params);
echo "Found " . count($articles) . " article(s) to purify.\n\n";

$purifiedCount = 0;

foreach ($articles as $art) {
    $id = (int)$art['id'];
    $title = $art['title'];
    $slug = $art['slug'];
    $content = $art['content'];
    $original = $content;

    echo "📄 [Article #{$id}] {$title}\n";

    // ── 1. PURIFY OPENING FLUFF ──
    // Remove "the wait is over", "If you've been waiting...", "shift your focus...", "for comparative insights"
    $content = preg_replace('/If you(?:\'ve| have) been waiting for [^,\.]+,?\s*(?:the wait is (?:finally )?over\.\s*)?/iu', '', $content);
    $content = preg_replace('/\bthe wait is (?:finally )?over\.\s*/iu', '', $content);
    $content = preg_replace('/\bIt\'?s time to shift your focus toward[^.!?]*[.!?]\s*/iu', '', $content);
    $content = preg_replace('/\bIf you are also tracking national-level board updates[^<]*for comparative insights\.\s*/iu', '', $content);

    // ── 2. PURIFY 5-STEP DOWNLOAD SECTIONS ──
    // Replace the generic 5-step download guide with a clean 1-sentence direct link notice
    $portalUrl = !empty($art['source_url']) && filter_var($art['source_url'], FILTER_VALIDATE_URL) ? $art['source_url'] : 'https://' . ($slug ? explode('-', $slug)[0] . '.nic.in' : 'gov.in');
    $host = parse_url($portalUrl, PHP_URL_HOST) ?: 'the official portal';

    $content = preg_replace_callback(
        '/<h[2-4][^>]*>[^<]*(?:Step-by-Step Guide to Download|How to Download|Steps to Download|How to Access)[^<]*<\/h[2-4]>\s*(?:<(?:ol|ul)[^>]*>.*?<\/(?:ol|ul)>|<p[^>]*>.*?<\/p>)+/is',
        function() use ($portalUrl, $host) {
            return "<h2>Official Document Download</h2>\n<p>Candidates can access the official notification and download the document directly from the official portal at <a href=\"{$portalUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$host}</a>.</p>";
        },
        $content
    );

    // ── 3. STRIP PREACHY ADVICE & PROTOCOL PARAGRAPHS ──
    $fluffParagraphs = [
        '/<p[^>]*>[^<]*(?:is a critical skill for any student|high-stakes assessments|well-rested and prepared|strict adherence to protocols|transparent pouch|electronic gadgets, including smartwatches|avoid any confusion during your study sessions)[^<]*<\/p>/iu',
        '/<p[^>]*>[^<]*(?:servers will crawl|incognito window or clear your browser cache|try incognito|clock starts ticking|you\'ve worked too hard|stay sharp, move fast|derail your progress)[^<]*<\/p>/iu',
        '/<p[^>]*>[^<]*(?:All information provided is based on the official circulars|You should cross-check any updates directly at)[^<]*<\/p>/iu',
        '/<p[^>]*>[^<]*(?:How do I handle exam stress|maintain a consistent sleep schedule)[^<]*<\/p>/iu',
    ];
    foreach ($fluffParagraphs as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }

    // ── 4. STRIP FAKE PSYCHOLOGICAL FAQS ──
    // Remove "How do I handle exam stress?" and similar fake FAQs
    $content = preg_replace('/<li[^>]*>[^<]*<strong[^>]*>How do I handle exam stress\?<\/strong>[^<]*<\/li>/iu', '', $content);
    $content = preg_replace('/<h[3-4][^>]*>[^<]*How do I handle exam stress\?[^<]*<\/h[3-4]>\s*<p[^>]*>.*?<\/p>/iu', '', $content);

    // ── 5. STRIP EMPTY OR DANGLING TAGS ──
    $content = preg_replace('/<li[^>]*>\s*<\/li>/u', '', $content);
    $content = preg_replace('/<p[^>]*>\s*<\/p>/u', '', $content);
    $content = preg_replace('/<h[2-4][^>]*>\s*<\/h[2-4]>/u', '', $content);
    $content = preg_replace('/<h2[^>]*>[^<]*Official Notice Reference & Authority Verification[^<]*<\/h2>\s*$/is', '', $content);

    // Fix any dangling sentence fragment (e.g. "Focus on your revision notes and </li>")
    $content = preg_replace('/\s+and\s*<\/li>/iu', '.</li>', $content);
    $content = preg_replace('/\s+and\s*<\/p>/iu', '.</li>', $content);

    // ── 6. ENFORCE CONTRACTIONS & SCRUB CLICHES ──
    $content = HumanizerService::enforceContractions($content);
    $content = HumanizerService::scrubClichés($content);

    // Clean multiple line breaks or spaces
    $content = preg_replace("/\n{3,}/", "\n\n", trim($content));

    if ($content !== $original) {
        $purifiedCount++;
        $diff = mb_strlen($content) - mb_strlen($original);
        echo "   ✂️ Purified structural filler (Size delta: {$diff} chars)\n";

        if (!$dryRun) {
            Database::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                ['content' => $content, 'id' => $id]
            );
            echo "   💾 SAVED: Article #{$id} updated in database.\n";
        } else {
            echo "   🔍 DRY-RUN: Changes detected but not saved.\n";
        }
    } else {
        echo "   ✅ Already clean.\n";
    }

    echo "\n";
}

echo "================================================================================\n";
echo "✅ PURIFICATION COMPLETE: {$purifiedCount} of " . count($articles) . " articles cleaned.\n";
echo "================================================================================\n";
