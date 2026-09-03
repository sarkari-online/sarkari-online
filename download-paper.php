<?php
/**
 * Sarkari.online - High-Speed Streamlined PDF Download Handler
 * Streams authentic question papers and answer keys with Cloudflare edge caching headers.
 */

require_once __DIR__ . '/config.php';

use App\Services\QuestionPaperService;
use App\Helpers\Logger;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$paper = QuestionPaperService::getPaperById($id);
if (!$paper) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$filePath = __DIR__ . '/' . ltrim($paper['file_path'], '/');
if (!file_exists($filePath)) {
    Logger::error("Requested question paper file missing on disk: {$filePath} (ID #{$id})");
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Increment real download counter for social proof
QuestionPaperService::incrementDownloadCount($id);

// Generate clean, safe download filename
$cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($paper['paper_title']));
$cleanName = preg_replace('/-+/', '-', $cleanName) . '.pdf';

// Send high-performance streaming & Cloudflare edge-caching headers
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $cleanName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, follow');

// Clean output buffer to avoid corruption
if (ob_get_level()) {
    ob_end_clean();
}

readfile($filePath);
exit;
