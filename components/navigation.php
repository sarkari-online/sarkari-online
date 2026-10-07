<?php
/**
 * Navigation Component (Desktop)
 * Features primary direct category links + clean 2-column institutional Mega Menu for Utilities & Portals.
 */
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';

$primaryNavLinks = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Full Forms (A-Z)', 'url' => 'full-forms/'],
    ['label' => 'About Us', 'url' => 'about/'],
    ['label' => 'Contact', 'url' => 'contact/'],
];
?>
<nav class="desktop-nav" aria-label="Main Navigation">
    <?php foreach ($primaryNavLinks as $link): 
        $isActive = ($link['url'] === '' && ($currentUrl === '/' || $currentUrl === BASE_PATH || $currentUrl === BASE_PATH . '/'))
                    || ($link['url'] !== '' && str_contains($currentUrl, trim($link['url'], '/')));
    ?>
        <a href="<?= url($link['url']) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>" title="<?= e($link['label']) ?> — Sarkari.online">
            <?= e($link['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
