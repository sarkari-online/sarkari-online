<?php
declare(strict_types=1);

namespace App\Services;

/**
 * FaqSchemaRenderer
 *
 * Renders both visible accordion HTML and matching JSON-LD FAQPage structured data
 * from a single verified facts_json source of truth.
 */
class FaqSchemaRenderer
{
    /**
     * Render visible FAQ items and JSON-LD schema block.
     *
     * @param mixed $faqs Array of ['q' => ..., 'a' => ...] or JSON string
     * @return string|null Combined HTML & script block or null if empty
     */
    public static function render(mixed $faqs): ?string
    {
        if (is_string($faqs)) {
            $decoded = json_decode($faqs, true);
            if (is_array($decoded)) {
                $faqs = $decoded;
            } else {
                return null;
            }
        }

        if (!is_array($faqs) || empty($faqs)) {
            return null;
        }

        $validItems = [];
        foreach ($faqs as $item) {
            $q = trim((string)($item['q'] ?? ''));
            $a = trim((string)($item['a'] ?? ''));
            if (!empty($q) && !empty($a)) {
                $validItems[] = [
                    'q' => $q,
                    'a' => $a,
                ];
            }
        }

        if (empty($validItems)) {
            return null;
        }

        // 1. Build Visible HTML
        $itemsHtml = '';
        foreach ($validItems as $idx => $item) {
            $qSafe = htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8');
            $aSafe = nl2br(htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8'));

            $itemsHtml .= "<details class=\"glossary-faq-item\" style=\"margin-bottom: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; overflow: hidden; transition: all 0.15s ease;\">\n"
                        . "  <summary style=\"padding: 1rem 1.25rem; font-weight: 700; color: #0f172a; cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; font-size: 0.95rem; user-select: none;\">\n"
                        . "    <span>{$qSafe}</span>\n"
                        . "    <span style=\"font-size: 1.1rem; color: #1e3a8a; font-weight: 800; margin-left: 10px;\">&plus;</span>\n"
                        . "  </summary>\n"
                        . "  <div style=\"padding: 0 1.25rem 1.15rem 1.25rem; color: #334155; font-size: 0.925rem; line-height: 1.65; border-top: 1px solid #f1f5f9; background: #f8fafc;\">\n"
                        . "    <p style=\"margin: 0.75rem 0 0 0;\">{$aSafe}</p>\n"
                        . "  </div>\n"
                        . "</details>\n";
        }

        // 2. Build JSON-LD Schema
        $mainEntities = [];
        foreach ($validItems as $item) {
            $mainEntities[] = [
                '@type' => 'Question',
                'name'  => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['a'],
                ],
            ];
        }

        $schemaArray = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $mainEntities,
        ];

        $schemaJson = json_encode($schemaArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $scriptTag = "<script type=\"application/ld+json\">\n{$schemaJson}\n</script>";

        return "<div class=\"glossary-faq-wrapper\" style=\"margin-top: 1.5rem;\">\n"
             . $itemsHtml
             . "</div>\n"
             . $scriptTag . "\n";
    }
}
