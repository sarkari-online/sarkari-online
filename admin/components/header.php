<?php
/**
 * EduPulse - Admin Header Component
 */
require_once dirname(__DIR__, 2) . '/config.php';
App\Helpers\Auth::requireAuth();

$user = App\Helpers\Auth::user();
$adminPageTitle = $adminPageTitle ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminPageTitle) ?> — <?= e(SITE_NAME) ?> Control Panel</title>
    <link rel="icon" type="image/x-icon" href="<?= asset('Sarkari-online-favicon.ico') ?>?v=<?= APP_VERSION ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32x32.png') ?>?v=<?= APP_VERSION ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>?v=<?= APP_VERSION ?>">
    <style>
        svg.icon, .icon {
            width: 18px;
            height: 18px;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        svg.icon-xs, .icon-xs {
            width: 12px !important;
            height: 12px !important;
            max-width: 12px;
            max-height: 12px;
        }
        svg.icon-sm, .icon-sm {
            width: 15px !important;
            height: 15px !important;
        }
        svg.icon-md, .icon-md {
            width: 20px !important;
            height: 20px !important;
        }
        svg.icon-lg, .icon-lg {
            width: 28px !important;
            height: 28px !important;
        }
        svg.icon-xl, .icon-xl {
            width: 48px !important;
            height: 48px !important;
        }
        svg.icon-2xl, .icon-2xl {
            width: 64px !important;
            height: 64px !important;
        }
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 260px;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .admin-brand {
            padding: 1.25rem 1.5rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }
        .admin-brand span { color: #f59e0b; }
        .admin-nav {
            padding: 1rem 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.5rem;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .admin-nav-item:hover, .admin-nav-item.active {
            color: #ffffff;
            background: rgba(255,255,255,0.06);
            border-left-color: #38bdf8;
        }
        .admin-main-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            overflow-x: hidden;
        }
        .admin-topbar {
            height: 60px;
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
        }
        .admin-content-body {
            padding: 1.75rem;
            flex: 1;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            box-shadow: var(--shadow-xs);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .stat-card-num {
            font-size: 1.85rem;
            font-weight: 900;
            color: var(--text-main);
            line-height: 1;
        }
        .stat-card-label {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .admin-table-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xs);
            overflow: hidden;
        }
        .admin-table-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        @media (max-width: 860px) {
            .admin-layout { flex-direction: column; }
            .admin-sidebar { width: 100%; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <a href="<?= url('admin/') ?>" class="admin-brand" style="padding: 1.15rem 1.25rem;">
            <img src="<?= asset('sarkari-logo-white.png') ?>" alt="Sarkari.online CMS" style="height: 32px; width: auto; max-width: 170px; object-fit: contain; display: block;">
        </a>
        <nav class="admin-nav">
            <a href="<?= url('admin/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'dashboard' ? 'active' : '' ?>">
                <?= icon('layers') ?> Dashboard
            </a>
            <a href="<?= url('admin/articles/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'articles' ? 'active' : '' ?>">
                <?= icon('file-text') ?> Articles
            </a>
            <a href="<?= url('admin/trends/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'trends' ? 'active' : '' ?>">
                <?= icon('trending-up') ?> Trends Engine
            </a>
            <a href="<?= url('admin/review/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'review' ? 'active' : '' ?>">
                <?= icon('shield-check') ?> Review Queue
            </a>
            <a href="<?= url('admin/messages/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'messages' ? 'active' : '' ?>">
                <?= icon('mail') ?> Inquiries &amp; Leads
                <?php 
                $unreadLeadCount = (int)\App\Database\Database::fetchColumn("SELECT count(*) FROM contact_messages WHERE status = 'unread'");
                if ($unreadLeadCount > 0): 
                ?>
                    <span class="badge" style="background: #f97316; color: #fff; margin-left: auto; font-size: 0.685rem; padding: 0.15rem 0.45rem; border-radius: 9999px; font-weight: 700;"><?= $unreadLeadCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('admin/sources/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'sources' ? 'active' : '' ?>">
                <?= icon('book-open') ?> Authority Sources
            </a>
            <a href="<?= url('admin/categories/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'categories' ? 'active' : '' ?>">
                <?= icon('compass') ?> Categories
            </a>
            <a href="<?= url('admin/ai-logs/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'ai_logs' ? 'active' : '' ?>">
                <?= icon('cpu') ?> AI Logs & Audits
            </a>
            <a href="<?= url('admin/health/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'health' ? 'active' : '' ?>">
                <?= icon('activity') ?> System Health
            </a>
            <a href="<?= url('admin/system-test.php') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'system-test' ? 'active' : '' ?>" style="color: #38bdf8;">
                <?= icon('shield-check') ?> Route &amp; SEO Test
            </a>
            <a href="<?= url('admin/settings/') ?>" class="admin-nav-item <?= ($adminPageKey ?? '') === 'settings' ? 'active' : '' ?>">
                <?= icon('sliders') ?> System Settings
            </a>
            <div style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.5rem;">
                <a href="<?= url() ?>" target="_blank" class="admin-nav-item">
                    <?= icon('external-link') ?> View Website
                </a>
                <a href="<?= url('admin/logout.php') ?>" class="admin-nav-item" style="color: #f87171;">
                    <?= icon('logout') ?> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main-wrap">
        <header class="admin-topbar">
            <div style="font-weight: 700; color: var(--text-main); font-size: 1.1rem;">
                <?= e($adminPageTitle) ?>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.875rem;">
                <span>Logged in as <strong><?= e($user['username'] ?? 'Admin') ?></strong></span>
                <a href="<?= url('admin/logout.php') ?>" class="btn btn-sm btn-outline" style="color: var(--color-danger);">Logout</a>
            </div>
        </header>

        <div class="admin-content-body">
