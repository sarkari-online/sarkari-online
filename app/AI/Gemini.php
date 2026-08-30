<?php
/**
 * EduPulse - Centralized Gemini API Client
 * Enterprise-grade LLM client with timeout, exponential backoff retries,
 * token tracking, database audit logging, and resilient JSON extraction & repair.
 */

namespace App\AI;

use App\Database\Database;
use App\Helpers\Env;
use App\Helpers\Logger;
use Exception;
use Throwable;

class Gemini {

    private string $apiKey;
    private string $model;
    private int $timeout;
    private int $maxRetries;
    private static mixed $mockHandler = null;

    public function __construct(?string $model = null) {
        $this->apiKey = (string)Env::get('GEMINI_API_KEY', '');

        $dbModel = null;
        try {
            $dbModel = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'gemini_model' LIMIT 1");
        } catch (Throwable $e) {}

        $this->model = $model ?: ($dbModel ?: (string)Env::get('GEMINI_MODEL', 'gemini-3.5-flash-lite'));
        $this->timeout = (int)Env::get('GEMINI_TIMEOUT', 90);
        $this->maxRetries = (int)Env::get('GEMINI_MAX_RETRIES', 3);
    }

    /**
     * Check if Gemini Circuit Breaker is active (rate limit cooldown)
     */
    public static function isCircuitBreakerActive(): bool {
        try {
            $val = Database::fetchValue("SELECT value FROM settings WHERE `key` = 'gemini_circuit_breaker_until' LIMIT 1");
            if ($val && (int)$val > time()) {
                return true;
            }
        } catch (Throwable $e) {}
        return false;
    }

    /**
     * Trip circuit breaker for a given number of seconds
     */
    public static function setCircuitBreaker(int $seconds = 60, string $reason = ''): void {
        $until = time() + $seconds;
        try {
            Database::query(
                "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES ('gemini_circuit_breaker_until', :val, NOW())
                 ON DUPLICATE KEY UPDATE `value` = :val, `updated_at` = NOW()",
                ['val' => (string)$until]
            );
            Logger::warning("Gemini Circuit Breaker ACTIVATED for {$seconds}s until " . date('Y-m-d H:i:s', $until) . ". Reason: {$reason}");
        } catch (Throwable $e) {}
    }

    /**
     * Allow registering a mock handler for testing without external API calls
     */
    public static function setMockHandler(?callable $handler): void {
        self::$mockHandler = $handler;
    }

    /**
     * Check if Gemini API key is configured
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey) || self::$mockHandler !== null;
    }

    /**
     * Generate raw text or structured response
     */
    public function generate(string $prompt, array $options = []): array {
        $stage = $options['stage'] ?? 'general';
        $articleId = $options['article_id'] ?? null;
        $trendId = $options['trend_id'] ?? null;
        $jsonMode = $options['json_mode'] ?? false;
        $systemInstruction = $options['system_instruction'] ?? null;
        $temperature = $options['temperature'] ?? 0.2;

        // Use mock handler if registered (for unit testing)
        if (self::$mockHandler !== null) {
            $mockResponse = call_user_func(self::$mockHandler, $prompt, $options);
            $this->logOperation($stage, $articleId, $trendId, $prompt, $mockResponse['text'] ?? '', 100, true, null);
            return $mockResponse;
        }

        if (empty($this->apiKey)) {
            $err = 'GEMINI_API_KEY is not configured in .env file.';
            Logger::error('Gemini API call aborted: API key missing', ['stage' => $stage]);
            $this->logOperation($stage, $articleId, $trendId, $prompt, '', 0, false, $err);
            throw new Exception($err);
        }

        // Circuit Breaker check: don't hammer Google if we are in rate limit cooldown
        if (self::isCircuitBreakerActive()) {
            $val = (int)Database::fetchValue("SELECT value FROM settings WHERE `key` = 'gemini_circuit_breaker_until' LIMIT 1");
            $rem = max(1, $val - time());
            $err = "Gemini API circuit breaker is active (cooldown). Please wait {$rem}s before retrying.";
            $this->logOperation($stage, $articleId, $trendId, $prompt, '', 0, false, $err);
            throw new Exception($err);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'topP' => 0.95,
                'topK' => 40,
                'maxOutputTokens' => 8192
            ]
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'role' => 'system',
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        // Active Google Gemini models in 2026 (gemini-3.5-flash-lite has the highest free tier quota)
        $modelsToTry = array_unique(array_filter([$this->model, 'gemini-3.5-flash-lite', 'gemini-3.6-flash']));
        $attempt = 0;
        $lastError = '';
        $tokensUsed = 0;

        foreach ($modelsToTry as $currentModel) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key=" . urlencode($this->apiKey);
            
            for ($try = 1; $try <= 2; $try++) {
                $attempt++;
                try {
                    $response = $this->executeCurl($url, $payload);
                    $httpCode = $response['http_code'];
                    $rawBody = $response['body'];

                    if ($httpCode === 200) {
                        $json = json_decode($rawBody, true);
                        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        $tokensUsed = $json['usageMetadata']['totalTokenCount'] ?? 0;

                        $this->logOperation($stage, $articleId, $trendId, $prompt, $text, $tokensUsed, true, null);

                        return [
                            'text' => $text,
                            'tokens_used' => $tokensUsed,
                            'model' => $currentModel,
                            'status' => 'success'
                        ];
                    }

                    $lastError = "Gemini API HTTP {$httpCode} on model {$currentModel}: " . substr($rawBody ?: 'Server issue', 0, 200);

                    // If model not found (404), skip retries on this model and try next model
                    if ($httpCode === 404) {
                        Logger::warning("Gemini model {$currentModel} returned 404; switching to next model.");
                        break;
                    }

                    // Handle rate limit (429) -> Activate Circuit Breaker and STOP trying further models
                    if ($httpCode === 429) {
                        $retrySeconds = 60;
                        if (preg_match('/retry in ([0-9.]+)s/i', $rawBody, $m)) {
                            $retrySeconds = (int)ceil((float)$m[1]) + 5;
                        } elseif (str_contains(strtolower($rawBody), 'quota exceeded') || str_contains(strtolower($rawBody), 'check your plan')) {
                            $retrySeconds = 900; // 15 mins for daily quota
                        }
                        self::setCircuitBreaker($retrySeconds, "HTTP 429 Rate Limit on {$currentModel}");
                        $lastError = "Gemini API HTTP 429: Rate limit cooldown active for {$retrySeconds}s.";
                        break 2; // Exit BOTH loops immediately — all models share the same quota pool!
                    }

                    // Server error (500/503) -> brief wait
                    sleep(2);

                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                    Logger::warning("Gemini API connection error on attempt {$attempt}: {$lastError}");
                    sleep(2);
                }
            }
        }

        $this->logOperation($stage, $articleId, $trendId, $prompt, '', $tokensUsed, false, $lastError);
        Logger::error("Gemini API operation failed after all model fallbacks", ['stage' => $stage, 'error' => $lastError]);

        throw new Exception("Gemini API failure: {$lastError}");
    }

    /**
     * Generate and parse strict JSON output with repair capability
     */
    public function generateJson(string $prompt, array $options = []): array {
        $options['json_mode'] = true;
        $response = $this->generate($prompt, $options);
        $rawText = $response['text'] ?? '';

        $parsed = self::extractAndRepairJson($rawText);

        if ($parsed === null) {
            $err = "Failed to parse or repair valid JSON from Gemini output.";
            Logger::error($err, ['raw_sample' => substr($rawText, 0, 300)]);
            throw new Exception($err);
        }

        return [
            'data' => $parsed,
            'tokens_used' => $response['tokens_used'] ?? 0,
            'raw_text' => $rawText
        ];
    }

    /**
     * Resilient JSON extractor and repair engine
     */
    public static function extractAndRepairJson(string $rawText): ?array {
        $clean = trim($rawText);

        // 1. Direct JSON decode
        $direct = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($direct)) {
            return $direct;
        }

        // 2. Strip Markdown code blocks (```json ... ``` or ``` ...)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $matches)) {
            $extracted = trim($matches[1]);
            $decoded = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            $clean = $extracted;
        }

        // 3. Find outermost JSON object or array bounds
        $firstBrace = strpos($clean, '{');
        $firstBracket = strpos($clean, '[');

        $startPos = false;
        $isObject = false;

        if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
            $startPos = $firstBrace;
            $isObject = true;
            $endPos = strrpos($clean, '}');
        } elseif ($firstBracket !== false) {
            $startPos = $firstBracket;
            $isObject = false;
            $endPos = strrpos($clean, ']');
        }

        if ($startPos !== false && isset($endPos) && $endPos !== false && $endPos > $startPos) {
            $substring = substr($clean, $startPos, $endPos - $startPos + 1);
            $decoded = json_decode($substring, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // 4. Attempt light syntax repair (remove trailing commas before closing braces/brackets)
            $repaired = preg_replace('/,\s*([\}\]])/', '$1', $substring);
            $decodedRepaired = json_decode($repaired, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRepaired)) {
                return $decodedRepaired;
            }

            // 5. Attempt control character / unescaped newline repair inside strings
            $sanitized = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function ($matches) {
                return '"' . str_replace(["\r\n", "\r", "\n", "\t"], ['\n', '\n', '\n', '\t'], $matches[1]) . '"';
            }, $repaired);
            $decodedSanitized = json_decode($sanitized, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSanitized)) {
                return $decodedSanitized;
            }
        }

        return null;
    }

    /**
     * Execute cURL request with timeout controls
     */
    private function executeCurl(string $url, array $payload): array {
        $ch = curl_init($url);
        $jsonPayload = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        } else {
            unset($ch);
        }

        if ($body === false && !empty($curlError)) {
            throw new Exception("cURL connection error: {$curlError}");
        }

        return [
            'http_code' => $httpCode,
            'body'      => (string)$body
        ];
    }

    /**
     * Log operation safely to database ai_logs table and system logger
     */
    private function logOperation(
        string $stage,
        ?int $articleId,
        ?int $trendId,
        string $prompt,
        string $response,
        int $tokens,
        bool $success,
        ?string $error
    ): void {
        try {
            Database::insert('ai_logs', [
                'article_id'       => $articleId,
                'trend_id'         => $trendId,
                'pipeline_stage'   => $stage,
                'prompt_summary'   => substr($prompt, 0, 500),
                'response_summary' => substr($response, 0, 2000),
                'tokens_used'      => $tokens,
                'success'          => $success ? 1 : 0,
                'error_message'    => $error ? substr($error, 0, 500) : null,
                'created_at'       => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to write ai_logs record: ' . $e->getMessage());
        }
    }
}
