<?php
/**
 * EduPulse - Automated Branded Thumbnail Generation Service (Phase 6)
 * Generates crisp, high-CTR 1200x675 WebP editorial cards with category color palettes,
 * typographic hierarchy, verification seals, and brand emblems using PHP GD.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use Exception;
use Throwable;

class ThumbnailService {

    private int $width = 1200;
    private int $height = 675;
    private string $uploadBaseDir;
    private ?string $fontRegular = null;
    private ?string $fontBold = null;

    public function __construct() {
        $this->uploadBaseDir = dirname(__DIR__, 2) . '/uploads/thumbnails';
        $this->detectFonts();
    }

    /**
     * Detect available system TrueType fonts
     */
    private function detectFonts(): void {
        $boldCandidates = [
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'
        ];

        $regCandidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
        ];

        foreach ($boldCandidates as $f) {
            if (file_exists($f)) {
                $this->fontBold = $f;
                break;
            }
        }

        foreach ($regCandidates as $f) {
            if (file_exists($f)) {
                $this->fontRegular = $f;
                break;
            }
        }
    }

    /**
     * Generate branded thumbnail for an article and update its database record
     */
    public function generateForArticle(int $articleId): array {
        $article = Database::fetchOne(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM articles a
             JOIN categories c ON a.category_id = c.id
             WHERE a.id = :id LIMIT 1",
            ['id' => $articleId]
        );

        if (!$article) {
            throw new Exception("Article #{$articleId} not found.");
        }

        $title = $article['title'];
        $categorySlug = $article['category_slug'] ?? 'exam-results';
        $categoryName = $article['category_name'] ?? 'Education Update';
        $slug = $article['slug'];
        $dateStr = !empty($article['published_at']) ? date('M d, Y', strtotime($article['published_at'])) : date('M d, Y');
        $sourceName = $article['source_name'] ?? 'Official Statutory Portal';

        $result = $this->generate([
            'title' => $title,
            'category_slug' => $categorySlug,
            'category_name' => $categoryName,
            'slug' => $slug,
            'date_string' => $dateStr,
            'source_name' => $sourceName
        ]);

        if (!empty($result['success'])) {
            // Update article record
            Database::update('articles', [
                'featured_image' => $result['relative_path'],
                'featured_image_alt' => $result['alt_text'],
                'og_image' => $result['relative_path'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Generated thumbnail for Article #{$articleId}", ['path' => $result['relative_path']]);
        }

        return $result;
    }

    /**
     * Generate WebP image from input parameters
     */
    public function generate(array $params): array {
        $title = trim($params['title'] ?? 'Education & Career Update');
        $categorySlug = $params['category_slug'] ?? 'exam-results';
        $categoryName = strtoupper($params['category_name'] ?? 'EDUCATION NOTICE');
        $slug = Sanitizer::slug($params['slug'] ?? $title);
        $dateStr = $params['date_string'] ?? date('M d, Y');
        $sourceName = $params['source_name'] ?? 'Official Release';

        // Ensure category subfolder exists
        $targetDir = $this->uploadBaseDir . '/' . $categorySlug;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = $slug . '.webp';
        $fullPath = $targetDir . '/' . $filename;
        $relativePath = 'uploads/thumbnails/' . $categorySlug . '/' . $filename;
        $altText = "Official update notice: " . $title . " — Sarkari.online";

        // Create GD TrueColor canvas
        $img = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // Get Theme Palette
        $theme = $this->getCategoryTheme($categorySlug);

        // 1. Draw Background Gradient / Texture
        $this->drawGradient($img, $theme['bg_top'], $theme['bg_bottom']);

        // 2. Draw Subtle Editorial Grid Lines & Top Accent Strip
        $accentColor = imagecolorallocate($img, $theme['accent'][0], $theme['accent'][1], $theme['accent'][2]);
        $cardBgColor = imagecolorallocatealpha($img, 15, 23, 42, 40); // Dark translucent card
        $cardBorderColor = imagecolorallocatealpha($img, 255, 255, 255, 100);
        $whiteColor = imagecolorallocate($img, 255, 255, 255);
        $mutedTextColor = imagecolorallocate($img, 203, 213, 225); // #cbd5e1

        // Top Accent Bar (8px)
        imagefilledrectangle($img, 0, 0, $this->width, 10, $accentColor);

        // 3. Header Section: Brand Logo & Category Pill
        $logoPath = dirname(__DIR__, 2) . '/assets/sarkari-logo-white.png';
        if (file_exists($logoPath)) {
            $logoImg = @imagecreatefrompng($logoPath);
            if ($logoImg) {
                $lw = imagesx($logoImg);
                $lh = imagesy($logoImg);
                $targetH = 50;
                $targetW = (int)($lw * ($targetH / $lh));
                imagecopyresampled($img, $logoImg, 60, 45, 0, 0, $targetW, $targetH, $lw, $lh);
                unset($logoImg);
            }
        } else {
            // Brand Emblem Box [S]
            imagefilledrectangle($img, 60, 50, 105, 95, $accentColor);
            $emblemTextColor = imagecolorallocate($img, 15, 23, 42);
            $this->drawText($img, 30, 0, 72, 85, $emblemTextColor, "S", true);

            // Brand Name Text
            $this->drawText($img, 24, 0, 120, 76, $whiteColor, "SARKARI.ONLINE", true);
            $this->drawText($img, 13, 0, 120, 93, $mutedTextColor, "INDEPENDENT EDUCATION & EXAMS DESK", false);
        }

        // Category Badge Pill (Right aligned)
        $badgeBg = imagecolorallocatealpha($img, $theme['accent'][0], $theme['accent'][1], $theme['accent'][2], 85);
        imagefilledrectangle($img, 820, 50, 1140, 95, $badgeBg);
        $this->drawText($img, 15, 0, 840, 78, $accentColor, $categoryName, true);

        // 4. Central Headline Container Card
        imagefilledrectangle($img, 60, 130, 1140, 560, $cardBgColor);
        imagerectangle($img, 60, 130, 1140, 560, $cardBorderColor);

        // Left Decorative Category Color Strip inside card
        imagefilledrectangle($img, 60, 130, 72, 560, $accentColor);

        // 5. Render Main Headline Text (Large, Bold & High-Contrast)
        $wrappedLines = $this->wrapText($title, 26);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 42 : 48;
        $lineSpacing = $lineCount >= 3 ? 66 : 74;
        $startY = $lineCount >= 3 ? 235 : 255;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 28) {
                $line = mb_substr($line, 0, 25) . '...';
            }
            $this->drawText($img, $fontSize, 0, 105, $startY + ($i * $lineSpacing), $whiteColor, $line, true);
        }

        // Subtitle / Highlight Strip with crisp drawn verified badge
        $subY = $startY + ($lineCount * $lineSpacing) + 25;
        $this->drawVerifiedBadge($img, 105, $subY - 14, $accentColor);
        $this->drawText($img, 20, 0, 138, $subY, $accentColor, "VERIFIED PUBLIC NOTICE & DIRECT DETAILS", true);

        // 6. Bottom Information Footer Bar
        // Source Reference Pill
        imagefilledrectangle($img, 105, 480, 620, 535, imagecolorallocatealpha($img, 255, 255, 255, 110));
        $this->drawText($img, 18, 0, 125, 514, $whiteColor, "Source: " . $this->truncate($sourceName, 36), false);

        // Date Pill
        imagefilledrectangle($img, 860, 480, 1100, 535, imagecolorallocatealpha($img, 255, 255, 255, 110));
        $this->drawText($img, 18, 0, 885, 514, $mutedTextColor, "Date: " . $dateStr, false);

        // 7. Save as WebP
        $saved = imagewebp($img, $fullPath, 85);
        unset($img);

        if (!$saved || !file_exists($fullPath)) {
            Logger::error("Failed to write thumbnail image to {$fullPath}");
            return [
                'success' => false,
                'error' => "Could not save WebP file."
            ];
        }

        $filesize = filesize($fullPath);

        return [
            'success' => true,
            'relative_path' => $relativePath,
            'absolute_path' => $fullPath,
            'alt_text' => $altText,
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $filesize
        ];
    }

    /**
     * Draw a crisp verified checkmark badge icon with GD primitives (never missing in any font)
     */
    private function drawVerifiedBadge($img, int $x, int $y, int $color): void {
        // Crisp Badge Circle
        imagefilledellipse($img, $x + 9, $y + 9, 18, 18, $color);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagesetthickness($img, 2);
        // Draw Checkmark
        imageline($img, $x + 5, $y + 9, $x + 8, $y + 13, $white);
        imageline($img, $x + 8, $y + 13, $x + 14, $y + 5, $white);
        imagesetthickness($img, 1);
    }

    /**
     * Draw text with TrueType font or GD internal fallback
     */
    private function drawText($img, int $size, int $angle, int $x, int $y, int $color, string $text, bool $bold = false): void {
        $font = ($bold && $this->fontBold) ? $this->fontBold : ($this->fontRegular ?: $this->fontBold);

        if ($font && function_exists('imagettftext')) {
            imagettftext($img, $size, $angle, $x, $y, $color, $font, $text);
        } else {
            // GD Standard fallback
            $gdFont = $size > 20 ? 5 : ($size > 14 ? 4 : 3);
            imagestring($img, $gdFont, $x, $y - 12, $text, $color);
        }
    }

    /**
     * Draw background gradient
     */
    private function drawGradient($img, array $topRgb, array $bottomRgb): void {
        for ($y = 0; $y < $this->height; $y++) {
            $ratio = $y / $this->height;
            $r = (int)($topRgb[0] + ($bottomRgb[0] - $topRgb[0]) * $ratio);
            $g = (int)($topRgb[1] + ($bottomRgb[1] - $topRgb[1]) * $ratio);
            $b = (int)($topRgb[2] + ($bottomRgb[2] - $topRgb[2]) * $ratio);

            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $this->width, $y, $color);
        }
    }

    /**
     * Wrap text into array of lines respecting max character width
     */
    private function wrapText(string $text, int $maxChars = 40): array {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen($current . ' ' . $word) <= $maxChars) {
                $current = trim($current . ' ' . $word);
            } else {
                if ($current !== '') $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') $lines[] = $current;

        return $lines;
    }

    private function truncate(string $text, int $max = 35): string {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }

    /**
     * Get tailored visual theme palette per category
     */
    private function getCategoryTheme(string $category): array {
        return match($category) {
            'exam-results' => [
                'bg_top' => [15, 23, 42],       // Slate 900
                'bg_bottom' => [30, 58, 138],    // Blue 900
                'accent' => [245, 158, 11]       // Amber Gold #f59e0b
            ],
            'admit-cards' => [
                'bg_top' => [4, 47, 46],         // Teal 950
                'bg_bottom' => [13, 148, 136],   // Teal 600
                'accent' => [6, 182, 212]        // Cyan #06b6d4
            ],
            'exam-dates' => [
                'bg_top' => [30, 27, 75],        // Indigo 950
                'bg_bottom' => [67, 56, 202],    // Indigo 700
                'accent' => [251, 191, 36]       // Amber #fbbf24
            ],
            'government-jobs' => [
                'bg_top' => [6, 78, 59],         // Emerald 900
                'bg_bottom' => [5, 150, 105],    // Emerald 600
                'accent' => [132, 204, 22]       // Lime #84cc16
            ],
            'scholarships' => [
                'bg_top' => [46, 16, 101],       // Purple 950
                'bg_bottom' => [124, 58, 237],   // Purple 600
                'accent' => [250, 204, 21]       // Yellow #facc15
            ],
            'higher-education' => [
                'bg_top' => [23, 37, 84],        // Blue 950
                'bg_bottom' => [37, 99, 235],    // Blue 600
                'accent' => [249, 115, 22]       // Orange #f97316
            ],
            'school-boards' => [
                'bg_top' => [69, 10, 10],        // Red 950
                'bg_bottom' => [220, 38, 38],    // Red 600
                'accent' => [254, 240, 138]      // Light Yellow
            ],
            'career-guides' => [
                'bg_top' => [69, 26, 3],         // Amber 950
                'bg_bottom' => [217, 119, 6],    // Amber 600
                'accent' => [253, 224, 71]       // Yellow
            ],
            'student-technology' => [
                'bg_top' => [15, 23, 42],        // Slate 900
                'bg_bottom' => [51, 65, 85],     // Slate 700
                'accent' => [56, 189, 248]       // Sky Blue #38bdf8
            ],
            default => [
                'bg_top' => [15, 23, 42],
                'bg_bottom' => [30, 58, 138],
                'accent' => [245, 158, 11]
            ]
        };
    }
}
