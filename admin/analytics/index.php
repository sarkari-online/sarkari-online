<?php
/**
 * Sarkari.online - Traffic & Audience feature decommissioned
 * Redirecting immediately to main admin dashboard
 */
require_once dirname(__DIR__, 2) . '/config.php';
use App\Helpers\Auth;

Auth::requireAuth();
header('Location: ' . url('admin/'));
exit;
