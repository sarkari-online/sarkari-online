<?php
/**
 * Sarkari.online - Aspirant Experience & Verification Signal Submission Handler
 * 
 * Safety & E-E-A-T Compliance:
 * - Submissions land STRICTLY in 'reader_signals' with status 'pending_review'.
 * - NEVER alters live article text or overrides grounded facts directly.
 * - Honeypot & IP-hash rate limiting prevents automated spam.
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    // 1. Honeypot Anti-Spam Check (hidden field in UI)
    if (!empty($_POST['website_hp_guard'])) {
        // Silently succeed for bots
        echo json_encode(['success' => true, 'message' => 'Thank you for your submission.']);
        exit;
    }

    // 2. Rate Limiting via IP Hash (Max 5 submissions per IP per hour)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ipHash = hash('sha256', $ip . '_sarkari_signal_salt_' . date('Y-m-d-H'));

    $recentCount = (int)Database::fetchValue(
        "SELECT COUNT(*) FROM reader_signals WHERE ip_hash = :ip_hash AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        ['ip_hash' => $ipHash]
    );

    if ($recentCount >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Submission rate limit reached. Please wait before submitting another report.']);
        exit;
    }

    // 3. Input Validation
    $articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
    if ($articleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid article reference.']);
        exit;
    }

    // Verify article exists
    $articleExists = (bool)Database::fetchValue("SELECT 1 FROM articles WHERE id = :id LIMIT 1", ['id' => $articleId]);
    if (!$articleExists) {
        echo json_encode(['success' => false, 'message' => 'Referenced article not found.']);
        exit;
    }

    $signalType = trim($_POST['signal_type'] ?? 'center_experience');
    $allowedTypes = ['correction', 'center_experience', 'official_circular'];
    if (!in_array($signalType, $allowedTypes, true)) {
        $signalType = 'center_experience';
    }

    $message = trim($_POST['message'] ?? '');
    if (mb_strlen($message) < 10) {
        echo json_encode(['success' => false, 'message' => 'Please provide at least 10 characters detailing your observation or correction.']);
        exit;
    }
    if (mb_strlen($message) > 3000) {
        $message = mb_substr($message, 0, 3000);
    }

    $candidateName = !empty($_POST['candidate_name']) ? mb_substr(trim(strip_tags($_POST['candidate_name'])), 0, 100) : 'Aspirant';
    $examCenterCity = !empty($_POST['exam_center_city']) ? mb_substr(trim(strip_tags($_POST['exam_center_city'])), 0, 100) : null;
    $reportingTime = !empty($_POST['reporting_time_observed']) ? mb_substr(trim(strip_tags($_POST['reporting_time_observed'])), 0, 100) : null;
    $biometricStatus = !empty($_POST['biometric_status']) ? mb_substr(trim(strip_tags($_POST['biometric_status'])), 0, 100) : null;
    $sourceUrl = !empty($_POST['source_circular_url']) ? mb_substr(trim(filter_var($_POST['source_circular_url'], FILTER_SANITIZE_URL)), 0, 500) : null;

    // 4. Secure Insert into reader_signals strictly in 'pending_review' state
    Database::insert('reader_signals', [
        'article_id'              => $articleId,
        'signal_type'             => $signalType,
        'candidate_name'          => $candidateName,
        'exam_center_city'        => $examCenterCity,
        'reporting_time_observed' => $reportingTime,
        'biometric_status'        => $biometricStatus,
        'source_circular_url'     => $sourceUrl,
        'message'                 => strip_tags($message),
        'status'                  => 'pending_review',
        'ip_hash'                 => $ipHash,
        'created_at'              => date('Y-m-d H:i:s')
    ]);

    Logger::info("ReaderSignal: New {$signalType} signal recorded for Article #{$articleId} from {$candidateName} (Pending Review)");

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your report! Your submission has been received by our editorial verification desk for review.'
    ]);

} catch (Throwable $e) {
    Logger::error("ReaderSignal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your submission.']);
}
