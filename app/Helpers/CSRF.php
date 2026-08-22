<?php
/**
 * EduPulse - CSRF Protection Helper
 * Generates, verifies, and outputs cryptographic CSRF tokens for form submissions.
 */

namespace App\Helpers;

class CSRF {
    private const SESSION_KEY = '_csrf_token';

    public static function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            Auth::startSession();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function generate(): string {
        return self::getToken();
    }

    public static function validate(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            Auth::startSession();
        }

        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    public static function verify(?string $token): bool {
        return self::validate($token);
    }

    public static function validateRequest(): bool {
        $token = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::validate($token);
    }

    public static function field(): string {
        $token = self::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function input(): string {
        return self::field();
    }

    public static function regenerate(): string {
        if (session_status() === PHP_SESSION_NONE) {
            Auth::startSession();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }
}
