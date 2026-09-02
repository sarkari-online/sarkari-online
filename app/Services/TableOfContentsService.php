<?php
/**
 * Sarkari.online - Automatic Table of Contents & Sitelinks Navigation Engine
 * Automatically parses article headings (H2, H3), generates clean semantic anchor IDs,
 * injects a responsive collapsible Table of Contents box, and powers Google SERP Sitelinks.
 */

namespace App\Services;

class TableOfContentsService {

    /**
     * Process article HTML: inject IDs into H2/H3 tags and generate Table of Contents
     * 
     * @param string $html Article raw or processed HTML
     * @return array ['content' => string, 'toc_html' => string, 'headings' => array]
     */
    public static function process(string $html): array {
        if (empty($html)) {
            return ['content' => '', 'toc_html' => '', 'headings' => []];
        }

        // Match H2 and H3 tags
        $pattern = '/<h([23])\b([^>]*)>(.*?)<\/h\1>/is';
        $headings = [];
        $slugCounts = [];

        // First pass: extract headings and build navigation list
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        // Require at least 2 headings for a Table of Contents
        if (count($matches) < 2) {
            return [
                'content' => $html,
                'toc_html' => '',
                'headings' => []
            ];
        }

        foreach ($matches as $m) {
            $level = (int)$m[1];
            $rawText = trim(strip_tags($m[3]));

            // Filter out empty or whitespace-only headings
            if (empty($rawText)) {
                continue;
            }

            // Generate clean URL-safe anchor slug
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/u', '-', $rawText));
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = 'section-' . (count($headings) + 1);
            }

            // Deduplicate slugs
            if (isset($slugCounts[$slug])) {
                $slugCounts[$slug]++;
                $anchorId = $slug . '-' . $slugCounts[$slug];
            } else {
                $slugCounts[$slug] = 1;
                $anchorId = $slug;
            }

            $headings[] = [
                'level'  => $level,
                'title'  => $rawText,
                'anchor' => $anchorId
            ];
        }

        // If after filtering we have fewer than 2 valid headings
        if (count($headings) < 2) {
            return [
                'content' => $html,
                'toc_html' => '',
                'headings' => []
            ];
        }

        // Second pass: inject id into HTML headings
        $index = 0;
        $modifiedHtml = preg_replace_callback($pattern, function ($m) use (&$headings, &$index) {
            if (!isset($headings[$index])) {
                return $m[0];
            }
            $h = $headings[$index];
            $index++;
            $level = $h['level'];
            $anchor = htmlspecialchars($h['anchor'], ENT_QUOTES, 'UTF-8');
            
            // Check if heading already has an id attribute
            if (preg_match('/\bid=["\'][^"\']*["\']/i', $m[2])) {
                $newAttrs = preg_replace('/\bid=["\'][^"\']*["\']/i', 'id="' . $anchor . '"', $m[2]);
            } else {
                $newAttrs = ' id="' . $anchor . '"' . $m[2];
            }

            return "<h{$level}{$newAttrs}>{$m[3]}</h{$level}>";
        }, $html);

        // Build HTML Table of Contents component
        $tocHtml = self::renderTocHtml($headings);

        // Inject TOC right before the first <h2>
        $firstH2Pos = stripos($modifiedHtml, '<h2');
        if ($firstH2Pos !== false) {
            $modifiedHtml = substr_replace($modifiedHtml, $tocHtml . "\n", $firstH2Pos, 0);
        } else {
            // Fallback: prepend
            $modifiedHtml = $tocHtml . "\n" . $modifiedHtml;
        }

        return [
            'content'  => $modifiedHtml,
            'toc_html' => $tocHtml,
            'headings' => $headings
        ];
    }

    /**
     * Render the accessible, responsive Table of Contents component
     */
    private static function renderTocHtml(array $headings): string {
        $itemsHtml = '';
        $h2Count = 0;

        foreach ($headings as $h) {
            $anchor = htmlspecialchars($h['anchor'], ENT_QUOTES, 'UTF-8');
            // Clean redundant leading numbers from heading text (e.g. "1. Overview" -> "Overview")
            $cleanTitle = preg_replace('/^\d+[\.\)\s\-]+\s*/u', '', $h['title']);
            $title = htmlspecialchars($cleanTitle, ENT_QUOTES, 'UTF-8');
            $level = $h['level'];

            if ($level === 2) {
                $h2Count++;
                $itemsHtml .= "<li class=\"toc-item toc-h2\">";
                $itemsHtml .= "<span class=\"toc-num\">{$h2Count}.</span> ";
                $itemsHtml .= "<a href=\"#{$anchor}\" class=\"toc-link\">{$title}</a>";
                $itemsHtml .= "</li>\n";
            } else {
                // H3 sub-item with clean SVG arrow
                $itemsHtml .= "<li class=\"toc-item toc-h3\">";
                $itemsHtml .= "<svg class=\"toc-sub-arrow\" width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#94a3b8\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"9 18 15 12 9 6\"></polyline></svg> ";
                $itemsHtml .= "<a href=\"#{$anchor}\" class=\"toc-link\">{$title}</a>";
                $itemsHtml .= "</li>\n";
            }
        }

        return <<<HTML
<nav class="article-toc-box" aria-label="Table of Contents">
    <div class="toc-header" onclick="toggleArticleToc()" role="button" tabindex="0" aria-expanded="true">
        <div class="toc-title-group">
            <svg class="toc-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span class="toc-heading">Table of Contents</span>
        </div>
        <button type="button" class="toc-toggle-btn" aria-label="Toggle Table of Contents" id="tocToggleBtn">
            <span id="tocToggleText">[ Hide ]</span>
        </button>
    </div>
    <div class="toc-content" id="articleTocContent">
        <ul class="toc-list" style="list-style: none !important; padding-left: 0 !important; margin: 0 !important;">
            {$itemsHtml}
        </ul>
    </div>
</nav>
HTML;
    }
}
