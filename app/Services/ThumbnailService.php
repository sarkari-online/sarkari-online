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
        $bundledBold = dirname(__DIR__, 2) . '/assets/fonts/Bold.ttf';
        $bundledReg = dirname(__DIR__, 2) . '/assets/fonts/Regular.ttf';

        $boldCandidates = [
            $bundledBold,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf'
        ];

        $regCandidates = [
            $bundledReg,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf'
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
            // Update article image paths without modifying content revision timestamp
            Database::update('articles', [
                'featured_image' => $result['relative_path'],
                'featured_image_alt' => $result['alt_text'],
                'og_image' => $result['relative_path']
            ], 'id = :id', ['id' => $articleId]);

            Logger::info("Generated thumbnail for Article #{$articleId}", ['path' => $result['relative_path']]);
        }

        return $result;
    }

    /**
     * Generate WebP image from input parameters using 5 specialized editorial designs
     */
    public function generate(array $params): array {
        $title = trim($params['title'] ?? 'Education & Career Update');
        $categorySlug = $params['category_slug'] ?? 'government-jobs';
        $categoryName = strtoupper($params['category_name'] ?? 'OFFICIAL NOTICE');
        $slug = Sanitizer::slug($params['slug'] ?? $title);
        $dateStr = $params['date_string'] ?? date('d M Y');
        $sourceName = $params['source_name'] ?? 'Government Portal';

        // Ensure category subfolder exists
        $targetDir = $this->uploadBaseDir . '/' . $categorySlug;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = $slug . '.webp';
        $fullPath = $targetDir . '/' . $filename;
        $relativePath = 'uploads/thumbnails/' . $categorySlug . '/' . $filename;
        $altText = "Official update notice: " . $title . " — Sarkari.online";

        // Create GD TrueColor canvas 1200x675
        $img = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // Classify intent to select the matching visual layout from mockup
        $intent = $this->classifyIntent($categorySlug, $title);

        switch ($intent) {
            case 'results':
                $this->drawResultLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
            case 'admit_cards':
                $this->drawAdmitCardLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
            case 'answer_keys':
                $this->drawAnswerKeyLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
            case 'exam_dates':
                $this->drawExamDateLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
            case 'scholarships':
                $this->drawScholarshipLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
            default: // recruitment / government-jobs / default
                $this->drawRecruitmentLayout($img, $title, $categoryName, $sourceName, $dateStr);
                break;
        }

        // Save as WebP (for fast web loading) and PNG (for Google Blogger & social crawlers)
        $saved = imagewebp($img, $fullPath, 88);
        $pngPath = preg_replace('/\.webp$/i', '.png', $fullPath);
        imagepng($img, $pngPath, 6);
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
     * Classify content into 5 visual template categories
     */
    private function classifyIntent(string $categorySlug, string $title): string {
        $slug = strtolower($categorySlug);
        $titleLower = strtolower($title);

        if (str_contains($slug, 'result') || str_contains($titleLower, 'result') || str_contains($titleLower, 'scorecard') || str_contains($titleLower, 'merit list') || str_contains($titleLower, 'cutoff')) {
            return 'results';
        }
        if (str_contains($slug, 'admit') || str_contains($titleLower, 'admit card') || str_contains($titleLower, 'hall ticket') || str_contains($titleLower, 'city intimation') || str_contains($titleLower, 'call letter')) {
            return 'admit_cards';
        }
        if (str_contains($slug, 'answer-key') || str_contains($titleLower, 'answer key') || str_contains($titleLower, 'response sheet') || str_contains($titleLower, 'key objection')) {
            return 'answer_keys';
        }
        if (str_contains($slug, 'exam-date') || str_contains($titleLower, 'exam date') || str_contains($titleLower, 'timetable') || str_contains($titleLower, 'datesheet') || str_contains($titleLower, 'schedule')) {
            return 'exam_dates';
        }
        if (str_contains($slug, 'scholarship') || str_contains($titleLower, 'scholarship') || str_contains($titleLower, 'fellowship')) {
            return 'scholarships';
        }
        return 'recruitment';
    }

    /**
     * DESIGN 1: RECRUITMENT & JOBS (Navy Background + Orange Pill Badge)
     * Matches Top-Left thumbnail in mockup
     */
    private function drawRecruitmentLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        // Deep Navy Gradient: #1a237e -> #0d1642
        $this->drawGradient($img, [26, 35, 126], [13, 22, 66]);

        // Top Orange Accent Bar (8px)
        $orange = imagecolorallocate($img, 245, 124, 0); // #f57c00
        $white = imagecolorallocate($img, 255, 255, 255);
        $lightBlue = imagecolorallocate($img, 180, 205, 240);
        $cardBg = imagecolorallocatealpha($img, 255, 255, 255, 115);

        imagefilledrectangle($img, 0, 0, $this->width, 8, $orange);

        // Top Left Brand
        $this->drawBrandHeader($img, 60, 50, true);

        // Top Right Pill: "RECRUITMENT" (Orange background, white text)
        imagefilledrectangle($img, 880, 48, 1140, 92, $orange);
        $this->drawText($img, 16, 0, 915, 76, $white, "RECRUITMENT", true);

        // Main Headline (White, Bold, Large)
        $wrappedLines = $this->wrapText($title, 26);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 42 : 48;
        $lineSpacing = $lineCount >= 3 ? 66 : 74;
        $startY = $lineCount >= 3 ? 220 : 240;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 28) {
                $line = mb_substr($line, 0, 25) . '...';
            }
            $this->drawText($img, $fontSize, 0, 60, $startY + ($i * $lineSpacing), $white, $line, true);
        }

        // Subtitle Highlight Bar
        $subY = $startY + ($lineCount * $lineSpacing) + 25;
        $this->drawVerifiedBadge($img, 60, $subY - 14, $orange);
        $this->drawText($img, 19, 0, 92, $subY, $orange, "OFFICIAL NOTIFICATION & ELIGIBILITY DETAILS", true);

        // Bottom Left Authority Card
        imagefilledrectangle($img, 60, 510, 500, 565, $cardBg);
        imagerectangle($img, 60, 510, 500, 565, imagecolorallocatealpha($img, 255, 255, 255, 80));
        $this->drawText($img, 16, 0, 80, 545, $white, "Authority: " . $this->truncate($sourceName, 32), true);

        // Bottom Right Date
        $this->drawText($img, 16, 0, 920, 545, $lightBlue, "Date: " . $dateStr, false);
    }

    /**
     * DESIGN 2: RESULTS (Two-Tone Split: Vibrant Orange + Deep Navy)
     * Matches Top-Middle thumbnail in mockup
     */
    private function drawResultLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        $orange = imagecolorallocate($img, 245, 124, 0); // #f57c00
        $deepNavy = imagecolorallocate($img, 13, 22, 66); // #0d1642
        $white = imagecolorallocate($img, 255, 255, 255);
        $gold = imagecolorallocate($img, 255, 215, 0);
        $lightBlue = imagecolorallocate($img, 180, 205, 240);

        // Left Panel (x: 0 to 400) - Solid Orange
        imagefilledrectangle($img, 0, 0, 400, $this->height, $orange);

        // Right Panel (x: 400 to 1200) - Deep Navy
        imagefilledrectangle($img, 400, 0, $this->width, $this->height, $deepNavy);

        // Left Panel Content: Huge "RESULT DECLARED"
        $this->drawText($img, 20, 0, 45, 75, $white, "Sarkari.online", true);
        $this->drawText($img, 62, 0, 45, 330, $white, "RESULT", true);
        $this->drawText($img, 28, 0, 48, 390, $white, "DECLARED", true);
        imagefilledrectangle($img, 48, 420, 280, 425, $white);
        $this->drawText($img, 15, 0, 48, 460, $white, "SCORECARD & MERIT LIST", true);

        // Right Panel Content: Title
        $this->drawText($img, 15, 0, 460, 75, $lightBlue, "OFFICIAL EXAMINATION RESULT", true);

        $wrappedLines = $this->wrapText($title, 24);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 38 : 44;
        $lineSpacing = $lineCount >= 3 ? 60 : 68;
        $startY = 200;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 26) {
                $line = mb_substr($line, 0, 23) . '...';
            }
            $this->drawText($img, $fontSize, 0, 460, $startY + ($i * $lineSpacing), $white, $line, true);
        }

        // Subtitle / Checklist
        $subY = $startY + ($lineCount * $lineSpacing) + 30;
        $this->drawText($img, 19, 0, 460, $subY, $gold, "Check Roll Number, Cutoff Marks & Scorecard Link", true);

        // Authority & Date
        $this->drawText($img, 16, 0, 460, 545, $lightBlue, "Authority: " . $this->truncate($sourceName, 30) . "  |  " . $dateStr, false);
    }

    /**
     * DESIGN 3: ADMIT CARDS (Clean White Canvas + Green Pill + Navy Stripe)
     * Matches Top-Right thumbnail in mockup
     */
    private function drawAdmitCardLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        $white = imagecolorallocate($img, 255, 255, 255);
        $navy = imagecolorallocate($img, 26, 35, 126); // #1a237e
        $green = imagecolorallocate($img, 19, 136, 8);   // #138808 India Green
        $orange = imagecolorallocate($img, 245, 124, 0); // #f57c00
        $slate = imagecolorallocate($img, 84, 110, 122); // #546e7a

        // White Canvas
        imagefilledrectangle($img, 0, 0, $this->width, $this->height, $white);

        // Left Thick Navy Stripe (36px)
        imagefilledrectangle($img, 0, 0, 36, $this->height, $navy);

        // Top Left Brand: "Sarkari.online" in Navy
        $this->drawText($img, 24, 0, 75, 75, $navy, "Sarkari.online", true);

        // Top Right Pill: "ADMIT CARD" (Green pill, white text)
        imagefilledrectangle($img, 880, 48, 1140, 92, $green);
        $this->drawText($img, 16, 0, 915, 76, $white, "ADMIT CARD", true);

        // Center Title (Navy, Bold, Large)
        $wrappedLines = $this->wrapText($title, 26);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 42 : 48;
        $lineSpacing = $lineCount >= 3 ? 66 : 74;
        $startY = 230;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 28) {
                $line = mb_substr($line, 0, 25) . '...';
            }
            $this->drawText($img, $fontSize, 0, 75, $startY + ($i * $lineSpacing), $navy, $line, true);
        }

        // Orange Underline Accent Bar under title
        $barY = $startY + ($lineCount * $lineSpacing) + 15;
        imagefilledrectangle($img, 75, $barY, 550, $barY + 6, $orange);

        // Subtitle
        $this->drawText($img, 19, 0, 75, $barY + 45, $slate, "Download Hall Ticket, Exam Center City Slip & Shift Timings", true);

        // Authority & Date
        $this->drawText($img, 16, 0, 75, 555, $slate, "Authority: " . $this->truncate($sourceName, 35) . "  |  " . $dateStr, false);
    }

    /**
     * DESIGN 4: ANSWER KEYS (Charcoal Background + Gold Borders & Typography)
     * Matches Middle-Left thumbnail in mockup
     */
    private function drawAnswerKeyLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        $bg = imagecolorallocate($img, 18, 18, 18); // #121212
        $gold = imagecolorallocate($img, 245, 197, 66); // #f5c542
        $goldBorder = imagecolorallocate($img, 212, 175, 55); // #d4af37
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 180, 180, 180);

        // Charcoal Canvas
        imagefilledrectangle($img, 0, 0, $this->width, $this->height, $bg);

        // Double Gold Editorial Rules at Top & Bottom
        imagefilledrectangle($img, 60, 40, 1140, 43, $goldBorder);
        imagefilledrectangle($img, 60, 48, 1140, 49, $goldBorder);

        imagefilledrectangle($img, 60, 580, 1140, 581, $goldBorder);
        imagefilledrectangle($img, 60, 586, 1140, 589, $goldBorder);

        // Brand Center Top
        $this->drawText($img, 24, 0, 490, 85, $gold, "Sarkari.online", true);

        // Headline in Large Gold Serif/Bold
        $wrappedLines = $this->wrapText($title, 26);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 42 : 48;
        $lineSpacing = $lineCount >= 3 ? 66 : 74;
        $startY = 230;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 28) {
                $line = mb_substr($line, 0, 25) . '...';
            }
            $this->drawText($img, $fontSize, 0, 80, $startY + ($i * $lineSpacing), $gold, $line, true);
        }

        // Subtitle
        $subY = $startY + ($lineCount * $lineSpacing) + 25;
        $this->drawText($img, 19, 0, 80, $subY, $white, "Official Provisional Answer Key & Response Sheet Released", true);

        // Objection Window Pill
        $pillY = $subY + 45;
        imagefilledrectangle($img, 80, $pillY - 20, 540, $pillY + 16, $goldBorder);
        $this->drawText($img, 14, 0, 95, $pillY + 5, $bg, "SUBMIT OBJECTIONS ONLINE BEFORE DEADLINE", true);

        // Date Right
        $this->drawText($img, 15, 0, 900, $pillY + 5, $gray, "Updated: " . $dateStr, false);
    }

    /**
     * DESIGN 5: EXAM DATES & SCHEDULE (Clean White Canvas + Orange Underline)
     * Matches Middle-Right thumbnail in mockup
     */
    private function drawExamDateLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        $white = imagecolorallocate($img, 255, 255, 255);
        $navy = imagecolorallocate($img, 26, 35, 126); // #1a237e
        $orange = imagecolorallocate($img, 245, 124, 0); // #f57c00
        $slate = imagecolorallocate($img, 84, 110, 122);

        // White Canvas
        imagefilledrectangle($img, 0, 0, $this->width, $this->height, $white);

        // Left Navy Stripe (45px)
        imagefilledrectangle($img, 0, 0, 45, $this->height, $navy);

        // Top Brand
        $this->drawText($img, 24, 0, 85, 75, $navy, "Sarkari.online", true);

        // Top Right Pill: "EXAM SCHEDULE"
        imagefilledrectangle($img, 860, 48, 1140, 92, $navy);
        $this->drawText($img, 15, 0, 890, 76, $white, "EXAM SCHEDULE", true);

        // Center Title (Navy, Bold, Huge)
        $wrappedLines = $this->wrapText($title, 24);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 44 : 52;
        $lineSpacing = $lineCount >= 3 ? 68 : 78;
        $startY = 240;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 26) {
                $line = mb_substr($line, 0, 23) . '...';
            }
            $this->drawText($img, $fontSize, 0, 85, $startY + ($i * $lineSpacing), $navy, $line, true);
        }

        // Thick Orange Underline directly beneath the title (8px)
        $lineY = $startY + ($lineCount * $lineSpacing) + 15;
        imagefilledrectangle($img, 85, $lineY, 650, $lineY + 8, $orange);

        // Subtitle
        $this->drawText($img, 19, 0, 85, $lineY + 45, $slate, "Official Timetable, Shift Timings & Center Guidelines", true);

        // Bottom Info
        $this->drawText($img, 16, 0, 85, 555, $slate, "Notice by: " . $this->truncate($sourceName, 35) . "  |  " . $dateStr, false);
    }

    /**
     * DESIGN 6: SCHOLARSHIPS & FELLOWSHIPS (Deep Navy + Cyan/Teal Geometric Theme)
     * Matches Center-Middle thumbnail in mockup
     */
    private function drawScholarshipLayout($img, string $title, string $categoryName, string $sourceName, string $dateStr): void {
        $deepBlue = imagecolorallocate($img, 13, 37, 85);    // #0d2555
        $cyan = imagecolorallocate($img, 0, 188, 212);        // #00bcd4
        $white = imagecolorallocate($img, 255, 255, 255);
        $lightBlue = imagecolorallocate($img, 180, 215, 250);

        // Gradient from Deep Navy to Dark Teal
        $this->drawGradient($img, [13, 37, 85], [10, 60, 95]);

        // Brand Header Top Left
        $this->drawBrandHeader($img, 60, 50, true);

        // Top Right Pill: "SCHOLARSHIP"
        imagefilledrectangle($img, 860, 48, 1140, 92, $cyan);
        $this->drawText($img, 16, 0, 885, 76, $deepBlue, "SCHOLARSHIP", true);

        // Center Title (White, Bold, Large)
        $wrappedLines = $this->wrapText($title, 24);
        $lineCount = min(count($wrappedLines), 3);
        $fontSize = $lineCount >= 3 ? 42 : 48;
        $lineSpacing = $lineCount >= 3 ? 66 : 74;
        $startY = 220;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $wrappedLines[$i];
            if ($i === 2 && mb_strlen($line) > 26) {
                $line = mb_substr($line, 0, 23) . '...';
            }
            $this->drawText($img, $fontSize, 0, 60, $startY + ($i * $lineSpacing), $white, $line, true);
        }

        // Subtitle
        $subY = $startY + ($lineCount * $lineSpacing) + 25;
        $this->drawVerifiedBadge($img, 60, $subY - 14, $cyan);
        $this->drawText($img, 19, 0, 92, $subY, $cyan, "Financial Aid, Eligibility Criteria & Online Application", true);

        // Bottom Info
        $this->drawText($img, 16, 0, 60, 545, $lightBlue, "Scheme Portal: " . $this->truncate($sourceName, 32) . "  |  " . $dateStr, false);
    }

    /**
     * Draw brand header (Logo image if present, or styled text)
     */
    private function drawBrandHeader($img, int $x, int $y, bool $isDarkBg = true): void {
        $logoPath = dirname(__DIR__, 2) . ($isDarkBg ? '/assets/sarkari-logo-white.png' : '/assets/sarkari-logo-transparent.png');
        if (file_exists($logoPath)) {
            $logoImg = @imagecreatefrompng($logoPath);
            if ($logoImg) {
                $lw = imagesx($logoImg);
                $lh = imagesy($logoImg);
                $targetH = 46;
                $targetW = (int)($lw * ($targetH / $lh));
                imagecopyresampled($img, $logoImg, $x, $y - 6, 0, 0, $targetW, $targetH, $lw, $lh);
                unset($logoImg);
                return;
            }
        }
        $color = $isDarkBg ? imagecolorallocate($img, 255, 255, 255) : imagecolorallocate($img, 26, 35, 126);
        $this->drawText($img, 24, 0, $x, $y + 24, $color, "Sarkari.online", true);
    }

    /**
     * Draw a crisp verified checkmark badge icon with GD primitives
     */
    private function drawVerifiedBadge($img, int $x, int $y, int $color): void {
        imagefilledellipse($img, $x + 9, $y + 9, 18, 18, $color);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagesetthickness($img, 2);
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
}
