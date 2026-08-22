<?php
/**
 * EduPulse - Environment Loader Helper
 * Safely loads and parses .env file variables without external dependencies.
 */

namespace App\Helpers;

class Env {
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load(string $path): void {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Cast types
                $value = match (strtolower($value)) {
                    'true', '(true)' => true,
                    'false', '(false)' => false,
                    'null', '(null)' => null,
                    'empty', '(empty)' => '',
                    default => is_numeric($value) ? (str_contains($value, '.') ? (float)$value : (int)$value) : $value
                };

                self::$variables[$key] = $value;
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default value
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$loaded) {
            $envPath = dirname(__DIR__, 2) . '/.env';
            self::load($envPath);
        }

        return self::$variables[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
