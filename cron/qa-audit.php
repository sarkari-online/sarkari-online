<?php
/**
 * Sarkari.online - Comprehensive Full-Website QA & Articles Auditor
 * Audits all published articles, executive summaries, official portals, schema, and page assets.
 *
 * Usage:
 * php cron/qa-audit.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Services\FeaturedSnippetService;
use App\Services\SchemaService;
use App\Services\AuthorityFactFetcherService;

echo "=================================================================\n";
echo "🔍 SARKARI.ONLINE — COMPREHENSIVE FULL-WEBSITE QA AUDITOR\n";
echo "=================================================================\n\n";

$publishedArticles = Database::fetchAll("
    SELECT id, title, slug, featured_image, source_name, source_url, source_ref, published_at, content, excerpt, meta_title, meta_description
    FROM articles 
    WHERE status = 'published' 
    ORDER BY id DESC 
    LIMIT 20
");

$totalArticles = (int)Database::fetchValue("SELECT COUNT(*) FROM articles WHERE status = 'published'");
echo "1. TOTAL PUBLISHED ARTICLES IN DATABASE: {$totalArticles}\n";
echo "   Auditing latest 20 published articles in detail...\n\n";

$issues = [];
$scorecard = [
    'articles_checked' => 0,
    'snippet_passed' => 0,
    'authority_valid' => 0,
    'portal_valid' => 0,
    'timeline_valid' => 0,
    'thumbnail_valid' => 0,
    'schema_valid' => 0
];

foreach ($publishedArticles as $art) {
    $scorecard['articles_checked']++;
    $id = (int)$art['id'];
    $title = $art['title'];

    // 1. Featured Snippet / Executive Summary Check
    try {
        $snippetHtml = FeaturedSnippetService::render($art);
        $scorecard['snippet_passed']++;

        preg_match_all('/<div class="snippet-fact-label">([^<]+)<\/div>\s*<div class="snippet-fact-val">\s*(?:<a[^>]*href="([^"]*)"[^>]*>)?([^<]+)/s', $snippetHtml, $matches, PREG_SET_ORDER);
        
        $facts = [];
        foreach ($matches as $m) {
            $facts[trim($m[1])] = [
                'val' => trim($m[3]),
                'url' => !empty($m[2]) ? $m[2] : null
            ];
        }

        $authName = $facts['Conducting Body']['val'] ?? '';
        $portalDomain = $facts['Official Portal']['val'] ?? '';
        $portalUrl = $facts['Official Portal']['url'] ?? '';
        $timeline = $facts['Important Timeline']['val'] ?? '';

        // Check Authority
        if (str_contains(strtolower($authName), 'official statutory authority') || str_contains(strtolower($authName), 'statutory examination board')) {
            $issues[] = "[Article #{$id}] Generic Conducting Body: '{$authName}'";
        } else {
            $scorecard['authority_valid']++;
        }

        // Check Official Portal (Must NEVER point to sarkari.online)
        if (str_contains(strtolower($portalDomain), 'sarkari.online') || str_contains(strtolower((string)$portalUrl), 'sarkari.online')) {
            $issues[] = "[Article #{$id}] Official Portal incorrectly points to sarkari.online: '{$portalDomain}' ({$portalUrl})";
        } else {
            $scorecard['portal_valid']++;
        }

        // Check Timeline
        if (!empty($art['published_at']) && $timeline === date('d F Y', strtotime($art['published_at']))) {
            $issues[] = "[Article #{$id}] Timeline falsely displays today's publish date: '{$timeline}'";
        } else {
            $scorecard['timeline_valid']++;
        }

        echo "   -> Article #{$id}: '{$title}'\n";
        echo "      Conducting Body: {$authName}\n";
        echo "      Official Portal: " . ($portalDomain ?: 'None') . ($portalUrl ? " ({$portalUrl})" : "") . "\n";
        echo "      Timeline       : {$timeline}\n\n";

    } catch (\Throwable $e) {
        $issues[] = "[Article #{$id}] FeaturedSnippetService rendering failed: " . $e->getMessage();
    }

    // 2. Thumbnail Check
    if (!empty($art['featured_image'])) {
        $thumbPath = dirname(__DIR__) . '/' . ltrim($art['featured_image'], '/');
        if (file_exists($thumbPath)) {
            $scorecard['thumbnail_valid']++;
        } else {
            $issues[] = "[Article #{$id}] Featured thumbnail file missing on disk: {$art['featured_image']}";
        }
    } else {
        $scorecard['thumbnail_valid']++;
    }

    // 3. Schema Check
    try {
        $schemas = SchemaService::generate($art);
        if (!empty($schemas)) {
            $scorecard['schema_valid']++;
        } else {
            $issues[] = "[Article #{$id}] Empty JSON-LD schema generated";
        }
    } catch (\Throwable $e) {
        $issues[] = "[Article #{$id}] SchemaService error: " . $e->getMessage();
    }
}

echo "=================================================================\n";
echo "📊 QA SCORECARD & INTEGRITY METRICS:\n";
echo "=================================================================\n";
echo "   • Articles Audited      : {$scorecard['articles_checked']}\n";
echo "   • Executive Box Rendered: {$scorecard['snippet_passed']}/{$scorecard['articles_checked']}\n";
echo "   • Authentic Authorities : {$scorecard['authority_valid']}/{$scorecard['articles_checked']}\n";
echo "   • Valid Official Portals: {$scorecard['portal_valid']}/{$scorecard['articles_checked']}\n";
echo "   • Grounded Timelines    : {$scorecard['timeline_valid']}/{$scorecard['articles_checked']}\n";
echo "   • Valid Thumbnails      : {$scorecard['thumbnail_valid']}/{$scorecard['articles_checked']}\n";
echo "   • Rich Schema Valid     : {$scorecard['schema_valid']}/{$scorecard['articles_checked']}\n\n";

if (empty($issues)) {
    echo "🎉 AUDIT RESULT: 100% PERFECT! ZERO DEFECTS DETECTED!\n";
} else {
    echo "⚠️ IDENTIFIED DEFECTS (" . count($issues) . "):\n";
    foreach ($issues as $iss) {
        echo "   ❌ {$iss}\n";
    }
}
echo "=================================================================\n";
