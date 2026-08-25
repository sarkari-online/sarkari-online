<?php
/**
 * Sarkari.online - Core Web Vitals Pre-Checker & Auto-Fixer
 * Validates and auto-fixes article HTML before publishing:
 * - Missing/empty image alt tags
 * - Image lazy loading injection
 * - External link security (rel=noopener)
 * - Heading hierarchy validation
 * - Word count enforcement
 */

namespace App\Services;

use App\Helpers\Logger;

class WebVitalsService {

    private const MIN_WORD_COUNT = 400;

    /**
     * Run full Core Web Vitals check and auto-fix on article content
     * Returns fixed content and list of remaining issues
     */
    public static function check(array $article): array {
        $content = $article['content'] ?? '';
        $title   = $article['title'] ?? 'Sarkari.online Article';
        $issues  = [];
        $fixed   = 0;

        if (empty($content)) {
            return ['pass' => false, 'issues' => ['Content is empty'], 'fixed_content' => $content];
        }

        // Fix 1: Add missing alt attributes to <img> tags
        $content = preg_replace_callback(
            '/<img\b([^>]*?)>/i',
            function($m) use ($title, &$fixed) {
                $tag = $m[0];
                // Already has non-empty alt
                if (preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $tag)) {
                    return $tag;
                }
                // Has empty alt — fill it
                if (preg_match('/\balt\s*=\s*["\']\s*["\']/i', $tag)) {
                    $tag = preg_replace('/\balt\s*=\s*["\']\s*["\']/i', 'alt="' . htmlspecialchars($title, ENT_QUOTES) . '"', $tag);
                    $fixed++;
                    return $tag;
                }
                // No alt at all — inject before closing >
                $fixed++;
                return str_replace('>', ' alt="' . htmlspecialchars($title, ENT_QUOTES) . '">', $tag);
            },
            $content
        );

        // Fix 2: Add loading="lazy" to images that don't have it
        $content = preg_replace_callback(
            '/<img\b([^>]*?)>/i',
            function($m) use (&$fixed) {
                $tag = $m[0];
                if (!preg_match('/\bloading\s*=/i', $tag)) {
                    $fixed++;
                    return str_replace('>', ' loading="lazy">', $tag);
                }
                return $tag;
            },
            $content
        );

        // Fix 3: Add rel="noopener noreferrer" to external links
        $content = preg_replace_callback(
            '/<a\s([^>]*href=["\']https?:\/\/(?!' . preg_quote(parse_url(SITE_URL, PHP_URL_HOST), '/') . ')[^"\']*["\'][^>]*)>/i',
            function($m) use (&$fixed) {
                $tag = $m[0];
                if (!preg_match('/\brel\s*=/i', $tag)) {
                    $tag = str_replace('>', ' rel="noopener noreferrer" target="_blank">', $tag);
                    $fixed++;
                } elseif (!preg_match('/noopener/i', $tag)) {
                    $tag = preg_replace('/rel\s*=\s*["\']([^"\']*)["\']/', 'rel="$1 noopener noreferrer"', $tag);
                    $fixed++;
                }
                return $tag;
            },
            $content
        );

        // Check 4: Heading hierarchy — warn if H1 found in body
        if (preg_match('/<h1\b/i', $content)) {
            $issues[] = 'H1 tag found in article body — H1 should only be the page title, not inside content.';
        }

        // Check 5: Word count
        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount < self::MIN_WORD_COUNT) {
            $issues[] = "Article has only {$wordCount} words. Minimum recommended: " . self::MIN_WORD_COUNT . " words for strong ranking.";
        }

        $pass = empty($issues);

        if ($fixed > 0) {
            Logger::info("WebVitalsService: Auto-fixed {$fixed} HTML issues in article '{$article['title']}'.");
        }

        return [
            'pass'          => $pass,
            'issues'        => $issues,
            'fixes_applied' => $fixed,
            'word_count'    => $wordCount,
            'fixed_content' => $content
        ];
    }

    /**
     * Check if an image file is optimized (under 150KB)
     */
    public static function isImageOptimized(string $imagePath): bool {
        if (!file_exists($imagePath)) {
            return true; // Can't check, don't block
        }
        return filesize($imagePath) <= 150 * 1024;
    }
}
