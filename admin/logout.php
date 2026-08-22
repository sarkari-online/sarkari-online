<?php
/**
 * EduPulse - Admin Logout
 */
require_once dirname(__DIR__) . '/config.php';

use App\Helpers\Auth;

Auth::logout();
header("Location: " . url('admin/login.php'));
exit;
