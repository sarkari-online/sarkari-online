<?php
/**
 * EduPulse - Admin Login Page
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Helpers\Env;

Auth::startSession();

// Secret access key
$secretKey = Env::get('ADMIN_ACCESS_KEY', 'Ajay-bytecode-cyber-security');

// Check if secret key was provided in URL
if (isset($_GET['access']) && trim($_GET['access']) === $secretKey) {
    $_SESSION['admin_unlocked'] = true;
}

// If already logged in, redirect to dashboard
if (Auth::check()) {
    header("Location: " . url('admin/index.php'));
    exit;
}

// Stealth Gatekeeper: If gate is locked, render 404 (pretend login page does not exist)
if (empty($_SESSION['admin_unlocked'])) {
    http_response_code(404);
    $notFoundFile = dirname(__DIR__) . '/404.php';
    if (file_exists($notFoundFile)) {
        include $notFoundFile;
    } else {
        echo "404 — Page Not Found";
    }
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateRequest()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $identifier = Sanitizer::string($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (Auth::attempt($identifier, $password)) {
            header("Location: " . url('admin/index.php'));
            exit;
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e(SITE_NAME) ?> CMS</title>
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>?v=<?= APP_VERSION ?>">
    <style>
        body {
            background-color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.25rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--color-primary);
            margin-top: 0.5rem;
        }
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="<?= asset('sarkari-logo-transparent.png') ?>" alt="Sarkari.online" style="height: 42px; width: auto; margin: 0 auto 0.75rem auto; display: block;">
            <h1 class="login-title" style="font-size: 1.35rem;">Sarkari.online CMS</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Authorized Staff Sign In</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-danger" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('admin/login.php') ?>" method="POST">
            <?= CSRF::field() ?>

            <div class="form-group">
                <label for="identifier" class="form-label">Username or Email</label>
                <input type="text" id="identifier" name="identifier" class="form-input" placeholder="admin" required autofocus value="<?= e($_POST['identifier'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
                    Sign In to Dashboard
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 1.75rem; font-size: 0.8125rem; color: var(--text-muted);">
            <a href="<?= url() ?>">← Return to Public Website</a>
        </div>
    </div>
</body>
</html>
