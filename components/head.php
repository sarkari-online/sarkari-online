<?php
/**
 * Head Component (HTML <head> with SEO & Meta Tokens)
 * Supports full SEO metadata: Canonical, Open Graph, Twitter/X cards, and JSON-LD schemas.
 */
use App\Helpers\SEOHelper;

// Optimized Title Formatting (Google 55-60 Character Best Practice)
if (!empty($pageTitle)) {
    if (str_contains($pageTitle, SITE_NAME)) {
        $metaTitle = $pageTitle;
    } elseif (mb_strlen($pageTitle) <= 42) {
        $metaTitle = $pageTitle . ' | ' . SITE_NAME;
    } else {
        $metaTitle = $pageTitle;
    }
} else {
    $metaTitle = SITE_NAME . ' — ' . SITE_TAGLINE;
}

$metaDesc = !empty($pageDesc) ? $pageDesc : SITE_DESCRIPTION;
$metaCanonical = !empty($canonicalUrl) ? $canonicalUrl : SITE_URL . $_SERVER['REQUEST_URI'];
$metaOgType = !empty($ogType) ? $ogType : 'website';
$metaKeywords = !empty($pageKeywords) ? $pageKeywords : 'Sarkari result, Sarkari online, government jobs 2026, entrance exams 2026, admit cards, answer keys, admission cutoffs, scholarships in India';
$metaAuthorVal = !empty($metaAuthor) ? $metaAuthor : 'Sarkari.online Editorial Desk';

$ogTitleVal = !empty($ogTitle) ? $ogTitle : $metaTitle;
$ogDescVal = !empty($ogDescription) ? $ogDescription : $metaDesc;
$ogImageVal = !empty($ogImage) ? (str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage)) : url('assets/images/default-share.jpg');
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php 
$isAdminSession = \App\Helpers\Auth::check();
if (!$isAdminSession): 
?>
    <!-- High-Performance Asynchronous Google Tag Manager & GA4 -->
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-XW0PTK22ZW', { 'send_page_view': true, 'cookie_domain': 'auto' });

    function initAnalytics() {
        if (window._analyticsLoaded) return;
        window._analyticsLoaded = true;
        
        // GTM
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-TBKXJRWX');

        // GA4
        var ga = document.createElement('script');
        ga.async = true;
        ga.src = 'https://www.googletagmanager.com/gtag/js?id=G-XW0PTK22ZW';
        document.head.appendChild(ga);
    }

    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() { setTimeout(initAnalytics, 1500); });
    } else {
        window.addEventListener('load', function() { setTimeout(initAnalytics, 1500); });
    }
    ['scroll', 'touchstart', 'mousemove', 'click'].forEach(function(e) {
        window.addEventListener(e, initAnalytics, { once: true, passive: true });
    });
    </script>
<?php endif; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Primary Meta Tags -->
    <title><?= e($metaTitle) ?></title>
    <meta name="title" content="<?= e($metaTitle) ?>">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords" content="<?= e($metaKeywords) ?>">
    <link rel="canonical" href="<?= e($metaCanonical) ?>">

    <!-- Crawling & Indexation Directives -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <!-- Google Search Console Ownership Verification -->
    <meta name="google-site-verification" content="tbEauc4I8_zvPJ8zJOf_YA3-40UtKAZNRxgo881ZLiY">

    <!-- Authorship & Publisher Directives -->
    <meta name="author" content="<?= e($metaAuthorVal) ?>">
    <meta name="publisher" content="<?= e(SITE_NAME) ?>">
    <meta name="copyright" content="<?= e(SITE_NAME) ?>">
    <meta name="rating" content="General">
    <meta name="geo.region" content="IN">
    <meta name="geo.placename" content="India">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?= e($metaOgType) ?>">
    <meta property="og:url" content="<?= e($metaCanonical) ?>">
    <meta property="og:title" content="<?= e($ogTitleVal) ?>">
    <meta property="og:description" content="<?= e($ogDescVal) ?>">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:locale" content="en_IN">
    <meta property="og:image" content="<?= e($ogImageVal) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php if (!empty($articlePublishedTime)): ?>
        <meta property="article:published_time" content="<?= e($articlePublishedTime) ?>">
    <?php endif; ?>
    <?php if (!empty($articleModifiedTime)): ?>
        <meta property="article:modified_time" content="<?= e($articleModifiedTime) ?>">
    <?php endif; ?>
    <?php if (!empty($articleSection)): ?>
        <meta property="article:section" content="<?= e($articleSection) ?>">
    <?php endif; ?>

    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($metaCanonical) ?>">
    <meta name="twitter:title" content="<?= e($ogTitleVal) ?>">
    <meta name="twitter:description" content="<?= e($ogDescVal) ?>">
    <meta name="twitter:image" content="<?= e($ogImageVal) ?>">
    <meta name="twitter:site" content="@SarkariOnline">
    <meta name="twitter:creator" content="@SarkariOnline">
    <!-- Multi-Device Favicons & PWA Icons (Google Search Standard Compliant) -->
    <link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon-16x16.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= asset('favicon-48x48.png') ?>">
    <link rel="icon" type="image/png" sizes="96x96" href="<?= asset('favicon-96x96.png') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= asset('favicon-192x192.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="manifest" href="<?= asset('site.webmanifest') ?>">
    <meta name="theme-color" content="#1e3a8a">
    <!-- Core Web Vitals Resource Hints & Preconnects -->
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://www.google-analytics.com">

    <?php if (!empty($lcpImagePreload)): ?>
        <!-- Preload Largest Contentful Paint (LCP) Candidate Image -->
        <link rel="preload" as="image" href="<?= e($lcpImagePreload) ?>" fetchpriority="high">
    <?php endif; ?>

    <!-- Critical CSS Reset: Prevent Giant Icon Flash & Layout Shifts (CLS = 0) -->
    <style>
    svg.icon{width:1em;height:1em;display:inline-block;vertical-align:middle;max-width:24px;max-height:24px}
    svg.icon-xs{width:12px;height:12px}
    svg.icon-sm{width:16px;height:16px}
    svg.icon-md{width:20px;height:20px}
    svg.icon-lg{width:24px;height:24px}
    svg.icon-xl{width:32px;height:32px}
    *,*::before,*::after{box-sizing:border-box}
    body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a}
    img{max-width:100%;height:auto;display:block}
    .goog-te-banner-frame,iframe.skiptranslate,.VIpgJd-ZVi9od-aZ2wEe-wOHMyf,.VIpgJd-ZVi9od-ORHb-OEVmcb{display:none!important;visibility:hidden!important;height:0!important;width:0!important}
    body{top:0!important}
    </style>

    <!-- Master Minified CSS Design System -->
    <?php $cssFile = file_exists(dirname(__DIR__) . '/assets/css/main.min.css') ? 'css/main.min.css' : 'css/main.css'; ?>
    <link rel="preload" href="<?= asset($cssFile) ?>" as="style">
    <link rel="stylesheet" href="<?= asset($cssFile) ?>">

    <!-- Schema.org Organization Structured Data -->
    <script type="application/ld+json">
    <?= SEOHelper::organizationSchema() ?>
    </script>

    <?php 
    $reqUri = $_SERVER['REQUEST_URI'] ?? '/';
    if (($ogType ?? '') === 'website' && ($reqUri === '/' || $reqUri === BASE_PATH || $reqUri === BASE_PATH . '/')): 
    ?>
        <!-- Schema.org WebSite Structured Data -->
        <script type="application/ld+json">
        <?= SEOHelper::websiteSchema() ?>
        </script>
    <?php endif; ?>
</head>
<body>
    <?php if (!$isAdminSession): ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TBKXJRWX"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    <?php endif; ?>
