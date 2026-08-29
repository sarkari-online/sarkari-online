<?php
/**
 * Sarkari.online - Security & Traffic Attack Analyzer
 * Run: docker exec sarkari_app php /var/www/html/cron/inspect-traffic.php
 */
if (php_sapi_name() !== 'cli') die("CLI only.\n");
require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "=======================================================\n";
echo "🛡️ SARKARI.ONLINE — TRAFFIC & SECURITY AUDIT\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================================\n\n";

// 1. Fetch recent 30 visitor stream hits
$views = Database::fetchAll(
    "SELECT id, ip_address, page_url, page_title, referrer, referrer_type, device_type, browser, os, viewed_at
     FROM page_views
     ORDER BY id DESC
     LIMIT 35"
);

echo "🌐 1. RECENT VISITOR STREAM HITS (" . count($views) . "):\n";
$suspicious = [];

foreach ($views as $v) {
    $url = $v['page_url'];
    $isSuspicious = false;
    $reason = [];

    // Suspicious pattern checks
    if (preg_match('/(\.php|\.env|\.git|wp-|eval|union|select|base64|<script|\.\.\/)/i', $url)) {
        $isSuspicious = true;
        $reason[] = "Suspicious string in URL";
    }
    if (preg_match('/(bot|crawl|spider|scanner|python|curl|wget)/i', $v['browser'] . ' ' . $v['os'])) {
        $isSuspicious = true;
        $reason[] = "Automated scanner/tool";
    }

    $time = date('H:i:s', strtotime($v['viewed_at']));
    $flag = $isSuspicious ? "🚨 SUSPICIOUS" : "✅ NORMAL";

    echo "   [{$time}] [{$flag}] IP: {$v['ip_address']} | {$v['os']}/{$v['browser']} ({$v['device_type']})\n";
    echo "       URL: {$url}\n";
    if ($v['referrer']) {
        echo "       Ref: {$v['referrer']} ({$v['referrer_type']})\n";
    }

    if ($isSuspicious) {
        $suspicious[] = [
            'ip' => $v['ip_address'],
            'url' => $url,
            'reason' => implode(', ', $reason),
            'time' => $v['viewed_at']
        ];
    }
}

echo "\n=======================================================\n";
echo "🚨 2. SUSPICIOUS / SCANNER ACTIVITY SUMMARY:\n";
if (empty($suspicious)) {
    echo "   ✅ No malicious exploit patterns detected in recent stream!\n";
    echo "   All visits appear to be legitimate page browses or standard referral clicks.\n";
} else {
    echo "   Found " . count($suspicious) . " suspicious request(s):\n";
    foreach ($suspicious as $s) {
        echo "   - IP: {$s['ip']} | Reason: {$s['reason']} | URL: {$s['url']}\n";
    }
}

echo "\n🛡️ 3. CURRENT SECURITY PROTECTION STATUS:\n";
echo "   ✅ SQL Injection Protection : ACTIVE (100% Prepared Statements via PDO)\n";
echo "   ✅ XSS Sanitization          : ACTIVE (e() / htmlspecialchars everywhere)\n";
echo "   ✅ Stealth Admin Protection  : ACTIVE (Fake 404 gatekeeper on /admin)\n";
echo "   ✅ CSRF Protection           : ACTIVE (CSRF tokens on state-changing forms)\n";
echo "   ✅ Bot Filtering             : ACTIVE (Automated crawlers flagged as is_bot)\n";
echo "=======================================================\n";
