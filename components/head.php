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
    <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

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
    <!-- Multi-Device Favicons & PWA Icons -->
    <link rel="icon" type="image/x-icon" href="<?= asset('Sarkari-online-favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon-16x16.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= asset('favicon-48x48.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="manifest" href="<?= asset('site.webmanifest') ?>">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="msapplication-TileColor" content="#1e3a8a">

    <!-- Master CSS Design System -->
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">

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
