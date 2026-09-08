<?php
/**
 * Sarkari.online - Candidate Help & Center Update Submission API
 * 
 * Multi-Layer Anti-Spam & Security Architecture:
 * 1. Double Honeypot Trap (website_hp_guard & user_email_hp)
 * 2. Time-Lock Bot Defense (Min 2.5s human fill threshold)
 * 3. IP-Hash Rate Limiter (Max 4 submissions per 30 minutes)
 * 4. Regex Spam & Blacklist Filter (Telegram links, WhatsApp invites, Betting, Casino)
 * 5. Strict Editorial Review Queue (Status = 'pending_review', never directly modifies live text)
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    // 1. Double Honeypot Anti-Bot Traps
    if (!empty($_POST['website_hp_guard']) || !empty($_POST['user_email_hp'])) {
        // Silently succeed for bots to waste their cycles without DB writes
        echo json_encode(['success' => true, 'message' => 'Aapka update receive ho gaya hai!']);
        exit;
    }

    // 2. Time-Lock Defense (Human form-fill takes at least 2.5 seconds; bots submit in < 500ms)
    $formTime  = isset($_POST['form_time']) ? (int)$_POST['form_time'] : 0;
    $formToken = trim($_POST['form_token'] ?? '');
    $now = time();

    if ($formTime > 0 && !empty($formToken)) {
        $expectedToken = hash('sha256', $formTime . '_salt_sarkari_signal_security');
        if (!hash_equals($expectedToken, $formToken)) {
            echo json_encode(['success' => false, 'message' => 'Security token invalid. Kripya page refresh karke try karein.']);
            exit;
        }

        $elapsedSeconds = $now - $formTime;
        if ($elapsedSeconds < 2) {
            // Submitted suspiciously fast — silent bot drop
            echo json_encode(['success' => true, 'message' => 'Aapka update receive ho gaya hai!']);
            exit;
        }
    }

    // 3. Rate Limiting via Salted IP Hash (Max 4 submissions per 30 mins)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ipHash = hash('sha256', $ip . '_sarkari_rate_guard_' . date('Y-m-d-H'));

    $recentCount = (int)Database::fetchValue(
        "SELECT COUNT(*) FROM reader_signals WHERE ip_hash = :ip_hash AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
        ['ip_hash' => $ipHash]
    );

    if ($recentCount >= 4) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Aapne haal hi me update submit kiya hai. Kripya 30 minute baad dobara try karein.']);
        exit;
    }

    // 4. Input Validation & Reference Verification
    $articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
    if ($articleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid article reference.']);
        exit;
    }

    $articleExists = (bool)Database::fetchValue("SELECT 1 FROM articles WHERE id = :id LIMIT 1", ['id' => $articleId]);
    if (!$articleExists) {
        echo json_encode(['success' => false, 'message' => 'Article not found.']);
        exit;
    }

    $signalType = trim($_POST['signal_type'] ?? 'center_experience');
    if (!in_array($signalType, ['correction', 'center_experience', 'official_circular'], true)) {
        $signalType = 'center_experience';
    }

    $message = trim($_POST['message'] ?? '');
    if (mb_strlen($message) < 8) {
        echo json_encode(['success' => false, 'message' => 'Kripya kam se kam 8-10 akshar me apna experience ya notice ki details likhein.']);
        exit;
    }
    if (mb_strlen($message) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    // 5. Anti-Spam Blacklist & Regex Filter
    $spamKeywords = [
        't.me/', 'telegram.me/', 'chat.whatsapp.com', 'wa.me/',
        'bit.ly/', 'tinyurl.com', 'is.gd/', 'cutt.ly/',
        'satta', 'matka', 'kalyan matka', 'casino', 'betting', 'rummy',
        'crypto', 'forex trading', 'viagra', 'porn', 'xxx', 'escort'
    ];

    $lowerMessage = mb_strtolower($message);
    foreach ($spamKeywords as $kw) {
        if (str_contains($lowerMessage, $kw)) {
            // Silently drop spam without showing server error
            echo json_encode(['success' => true, 'message' => 'Aapka update receive ho gaya hai!']);
            exit;
        }
    }

    // Check for excessive repetitive character spam (e.g. aaaaaaaaaaa)
    if (preg_match('/(.)\1{9,}/', $message)) {
        echo json_encode(['success' => false, 'message' => 'Kripya sahi text likhein.']);
        exit;
    }

    // Sanitize Clean Fields
    $candidateName  = !empty($_POST['candidate_name']) ? mb_substr(trim(strip_tags($_POST['candidate_name'])), 0, 80) : 'Aspirant';
    $examCenterCity = !empty($_POST['exam_center_city']) ? mb_substr(trim(strip_tags($_POST['exam_center_city'])), 0, 100) : null;
    $reportingTime  = !empty($_POST['reporting_time_observed']) ? mb_substr(trim(strip_tags($_POST['reporting_time_observed'])), 0, 100) : null;
    $biometricStatus= !empty($_POST['biometric_status']) ? mb_substr(trim(strip_tags($_POST['biometric_status'])), 0, 100) : null;

    // Validate Official Circular URL if provided
    $sourceUrl = null;
    if (!empty($_POST['source_circular_url'])) {
        $rawUrl = trim(filter_var($_POST['source_circular_url'], FILTER_SANITIZE_URL));
        if (filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            // Ensure no spam domains in circular URL
            $urlHost = strtolower(parse_url($rawUrl, PHP_URL_HOST) ?: '');
            if (!str_contains($urlHost, 'bit.ly') && !str_contains($urlHost, 't.me') && !str_contains($urlHost, 'whatsapp')) {
                $sourceUrl = mb_substr($rawUrl, 0, 500);
            }
        }
    }

    // 6. Secure Database Insert strictly in 'pending_review' state
    Database::insert('reader_signals', [
        'article_id'              => $articleId,
        'signal_type'             => $signalType,
        'candidate_name'          => htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8'),
        'exam_center_city'        => $examCenterCity ? htmlspecialchars($examCenterCity, ENT_QUOTES, 'UTF-8') : null,
        'reporting_time_observed' => $reportingTime ? htmlspecialchars($reportingTime, ENT_QUOTES, 'UTF-8') : null,
        'biometric_status'        => $biometricStatus ? htmlspecialchars($biometricStatus, ENT_QUOTES, 'UTF-8') : null,
        'source_circular_url'     => $sourceUrl,
        'message'                 => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'status'                  => 'pending_review',
        'ip_hash'                 => $ipHash,
        'created_at'              => date('Y-m-d H:i:s')
    ]);

    Logger::info("ReaderSignal: Verified submission stored for Article #{$articleId} from {$candidateName} (City: {$examCenterCity})");

    echo json_encode([
        'success' => true,
        'message' => 'Aapka update receive ho gaya hai! Editorial desk verify karne ke baad ise yahan live show karegi taaki baaki students ki help ho sake.'
    ]);

} catch (\Throwable $e) {
    Logger::error("ReaderSignal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Kripya thodi der baad try karein.']);
}
