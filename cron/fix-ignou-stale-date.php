<?php
/**
 * Fix IGNOU July 2026 Article - Update stale "Apply by Sept 15" deadline
 * Deadline has passed (today: Sep 17, 2026) — update article to reflect closed status
 * 
 * Also improves the deep_audit.php policy scan to avoid false positives
 * (skill != kill, weapon/metaphor context)
 * 
 * Run on VPS: docker exec -it sarkari_app php cron/fix-ignou-stale-date.php
 */

if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once __DIR__ . '/../config.php';
use App\Database\Database;

echo "=== IGNOU Stale Date Fix + Article Update ===\n\n";

// 1. Fetch the IGNOU article
$article = Database::fetchOne(
    "SELECT id, title, content, slug FROM articles WHERE slug = 'ignou-july-2026-admissions-extended' LIMIT 1"
);

if (!$article) {
    echo "❌ Article not found!\n";
    exit(1);
}

echo "Found: #{$article['id']} — {$article['title']}\n\n";

$content = $article['content'];
$original = $content;

// 2. Fix the title reference in article body (Apply by Sept 15 → Admission Closed)
$fixes = [
    // Title and heading mentions
    '/Apply by Sept 15/i'                                     => 'Admission Closed — September 15, 2026',
    '/Apply by September 15/i'                                => 'Admission Closed — September 15, 2026',
    
    // "You have until September 15" type urgency phrases
    '/you now have until September 15,?\s*2026[,.]?\s*to get your application/i'
        => 'the deadline was September 15, 2026. The application window is now closed.',
    '/you[\'"]ve got a second chance/i'                       => 'the extended deadline has now passed',
    
    // Current status box — already says "Application Window Closed" so likely fine
    // but update any "Don't let this slip" urgency
    '/Don[\'"]t let this slip by again\./i'                   => 'The admission window has now closed.',
    '/get your application sorted/i'                          => 'watch for the next admission cycle',
    
    // Future-tense "apply" references near the date
    '/to apply before the extended deadline/i'                => 'the extended deadline has passed',
    
    // Article meta/excerpt updates handled via status check below
];

foreach ($fixes as $pattern => $replacement) {
    $new = preg_replace($pattern, $replacement, $content);
    if ($new !== $content) {
        $count = preg_match_all($pattern, $content);
        echo "  ✅ Fixed ({$count}x): " . trim($replacement) . "\n";
        $content = $new;
    }
}

// 3. Add/update a "Status: Closed" notice at the top of the article body if not already present
$closedNotice = '<div class="alert-box status-closed" style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin-bottom:16px;border-radius:4px;">
<strong>⚠️ Admission Closed:</strong> The IGNOU July 2026 extended admission deadline (September 15, 2026) has passed. 
Please visit <a href="https://ignouadmission.samarth.edu.in" rel="nofollow" target="_blank">ignouadmission.samarth.edu.in</a> 
for updates on the next admission cycle (January 2027 session).
</div>';

// Only add if not already present
if (strpos($content, 'Admission Closed') === false || strpos($content, 'alert-box') === false) {
    // Add after the first <p> or <h2> tag
    $content = preg_replace('/(<(?:p|h2)[^>]*>)/i', $closedNotice . '$1', $content, 1);
    echo "  ✅ Added 'Admission Closed' notice at top of article\n";
}

// 4. Save if changed
if ($content !== $original) {
    Database::execute(
        "UPDATE articles SET content = ?, updated_at = NOW() WHERE id = ?",
        [$content, $article['id']]
    );
    echo "\n💾 SAVED — Article #{$article['id']} updated successfully\n";
} else {
    echo "\n⚠️  No changes needed — article may already be updated\n";
}

echo "\n=== Done ===\n";
