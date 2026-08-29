<?php
/**
 * Sarkari.online - One-Time Google Blogger OAuth2 Token Generator
 */
require_once dirname(__DIR__) . '/config.php';
use App\Helpers\Env;

$clientId = Env::get('BLOGGER_CLIENT_ID', '');
$clientSecret = Env::get('BLOGGER_CLIENT_SECRET', '');
$redirectUri = 'https://developers.google.com/oauthplayground';
$scope = 'https://www.googleapis.com/auth/blogger';

echo "\n=======================================================\n";
echo "🔐 GOOGLE BLOGGER OAUTH2 TOKEN GENERATOR\n";
echo "=======================================================\n\n";

if ($argc > 1 && !empty($argv[1])) {
    $authCode = trim($argv[1]);
    echo "Exchanging authorization code for permanent Refresh Token...\n";

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $authCode,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (!empty($data['refresh_token'])) {
        echo "\n🎉 SUCCESS! Permanent Refresh Token Generated:\n\n";
        echo "BLOGGER_REFRESH_TOKEN=" . $data['refresh_token'] . "\n\n";
        echo "Copy this token and add it to your .env file!\n";
    } else {
        echo "\n❌ Failed to exchange code:\n";
        print_r($data);
    }
    exit;
}

$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => $scope,
    'access_type'   => 'offline',
    'prompt'        => 'consent'
]);

echo "STEP 1: Open this URL in your browser to authorize:\n\n";
echo $authUrl . "\n\n";
echo "STEP 2: After allowing access, copy the authorization code from the redirect URL.\n";
echo "STEP 3: Run this script with the code:\n";
echo "   php cron/blogger-auth-setup.php <YOUR_AUTH_CODE>\n\n";
