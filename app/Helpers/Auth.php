<?php
/**
 * EduPulse - Secure Authentication & Session Guard
 * Hardened session management, password_verify(), session hijacking prevention, and admin guards.
 */

namespace App\Helpers;

use App\Database\Database;
use PDOException;

class Auth {
    private const SESSION_USER_KEY = 'auth_user_id';
    private const SESSION_LAST_ACTIVITY = 'auth_last_activity';
    private const SESSION_FINGERPRINT = 'auth_fingerprint';
    private static ?array $currentUser = null;

    /**
     * Start secure session with hardened cookie parameters
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = (int)Env::get('SESSION_LIFETIME', 7200);
            $isSecure = (bool)Env::get('SESSION_SECURE_COOKIE', false) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                @ini_set('session.use_strict_mode', '1');
                @ini_set('session.use_only_cookies', '1');
            }

            @session_start();
        }
    }

    /**
     * Generate security fingerprint for session hijacking protection
     */
    private static function generateFingerprint(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        return hash('sha256', $ip . '|' . $ua . '|' . Env::get('APP_KEY', 'secret'));
    }

    /**
     * Attempt login with username or email and password
     */
    public static function attempt(string $identifier, string $password): bool {
        self::startSession();

        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return false;
        }

        try {
            $user = Database::fetchOne(
                "SELECT id, username, email, password_hash, role, status FROM users WHERE (email = :e OR username = :u) LIMIT 1",
                ['e' => $identifier, 'u' => $identifier]
            );

            if (!$user) {
                Logger::warning('Failed login attempt: User not found', ['identifier' => $identifier]);
                return false;
            }

            if (($user['status'] ?? 'active') !== 'active') {
                Logger::warning('Failed login attempt: Account suspended', ['identifier' => $identifier]);
                return false;
            }

            if (password_verify($password, $user['password_hash'])) {
                // Prevent session fixation
                if (php_sapi_name() !== 'cli' && !headers_sent()) {
                    @session_regenerate_id(true);
                }

                $_SESSION[self::SESSION_USER_KEY] = $user['id'];
                $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
                $_SESSION[self::SESSION_FINGERPRINT] = self::generateFingerprint();
                self::$currentUser = $user;

                // Update last login timestamp
                Database::update('users', ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

                Logger::info('Admin user logged in successfully', ['username' => $user['username'], 'user_id' => $user['id']]);
                return true;
            }

            Logger::warning('Failed login attempt: Invalid password', ['identifier' => $identifier]);
            return false;
        } catch (PDOException $e) {
            Logger::critical('Authentication database error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if current session is authenticated and valid
     */
    public static function check(): bool {
        self::startSession();

        if (empty($_SESSION[self::SESSION_USER_KEY])) {
            return false;
        }

        // Verify session timeout
        $maxLifetime = (int)Env::get('SESSION_LIFETIME', 7200);
        $lastActivity = $_SESSION[self::SESSION_LAST_ACTIVITY] ?? 0;
        if ((time() - $lastActivity) > $maxLifetime) {
            self::logout();
            return false;
        }

        // Verify fingerprint
        $expectedFingerprint = self::generateFingerprint();
        $actualFingerprint = $_SESSION[self::SESSION_FINGERPRINT] ?? '';
        if (!hash_equals($expectedFingerprint, $actualFingerprint)) {
            Logger::warning('Session hijacking detected: Fingerprint mismatch', [
                'user_id' => $_SESSION[self::SESSION_USER_KEY]
            ]);
            self::logout();
            return false;
        }

        // Refresh activity timestamp
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Get authenticated user record
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        if (self::$currentUser === null) {
            $userId = $_SESSION[self::SESSION_USER_KEY];
            self::$currentUser = Database::fetchOne(
                "SELECT id, username, email, role, created_at, updated_at FROM users WHERE id = :id LIMIT 1",
                ['id' => $userId]
            );
        }

        return self::$currentUser;
    }

    /**
     * Require authentication guard for admin routes
     */
    public static function requireAuth(string $redirectUrl = ''): void {
        self::startSession();

        // Secret key bypass check on any admin request
        $secretKey = Env::get('ADMIN_ACCESS_KEY', 'Ajay-bytecode-cyber-security');
        if (isset($_GET['access']) && trim($_GET['access']) === $secretKey) {
            $_SESSION['admin_unlocked'] = true;
        }

        if (!self::check()) {
            // Stealth Gatekeeper: If gate is locked, render 404 (pretend admin panel does not exist)
            if (empty($_SESSION['admin_unlocked'])) {
                http_response_code(404);
                $notFoundFile = dirname(__DIR__, 2) . '/404.php';
                if (file_exists($notFoundFile)) {
                    include $notFoundFile;
                } else {
                    echo "404 — Page Not Found";
                }
                exit;
            }

            if ($redirectUrl === '') {
                $redirectUrl = url('admin/login.php');
            }
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Logout and destroy session
     */
    public static function logout(): void {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$currentUser = null;
    }
}
