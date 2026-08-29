<?php
/**
 * EduPulse - Admin Logout
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\Auth;

Auth::logout();
if (session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['admin_unlocked']);
}
header("Location: " . url());
exit;
