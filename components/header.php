<?php
/**
 * Header Component
 * Contains Top Breaking bar, Brand Logo, Desktop Nav, Search & Mobile Menu Triggers.
 */
?>
<!-- Top Breaking Updates Bar -->
<?php include __DIR__ . '/top-update-bar.php'; ?>

<!-- Sticky Header -->
<header class="site-header" id="siteHeader">
    <div class="container">
        <div class="header-inner">
            
            <!-- Brand / Logo -->
            <a href="<?= url() ?>" class="site-brand" aria-label="<?= e(SITE_NAME) ?> - Back to homepage">
                <picture>
                    <source srcset="<?= asset('sarkari-logo-transparent.webp') ?>" type="image/webp">
                    <img src="<?= asset('sarkari-logo-transparent.png') ?>" alt="<?= e(SITE_NAME) ?> - Sarkari Result &amp; Latest Govt Jobs 2026" title="<?= e(SITE_NAME) ?> - Official Education &amp; Recruitment Portal" class="site-logo-img" width="185" height="48" fetchpriority="high" decoding="async">
                </picture>
            </a>

            <!-- Desktop Navigation -->
            <?php include __DIR__ . '/navigation.php'; ?>

            <!-- Header Action Controls -->
            <div class="header-actions">
                <!-- Language Switcher Dropdown -->
                <div class="lang-dropdown-container" id="langDropdownContainer">
                    <button type="button" class="lang-select-btn" id="langToggleBtn" aria-label="Select Language / भाषा चुनें" aria-expanded="false">
                        <?= icon('globe', 'icon-sm') ?>
                        <span class="current-lang-text" id="currentLangLabel">English</span>
                        <span class="lang-chevron-icon"><?= icon('chevron-right', 'icon-xs') ?></span>
                    </button>
                    
                    <div class="lang-dropdown-menu" id="langDropdownMenu" aria-hidden="true">
                        <div class="lang-menu-header">Select Language / भाषा चुनें</div>
                        <div class="lang-menu-grid">
                            <button type="button" class="lang-option-btn active" data-lang="en">
                                <span class="lang-native">English</span>
                                <span class="lang-en">Default</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="hi">
                                <span class="lang-native">हिंदी</span>
                                <span class="lang-en">Hindi</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="ta">
                                <span class="lang-native">தமிழ்</span>
                                <span class="lang-en">Tamil</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="te">
                                <span class="lang-native">తెలుగు</span>
                                <span class="lang-en">Telugu</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="ur">
                                <span class="lang-native">اردو</span>
                                <span class="lang-en">Urdu</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="bn">
                                <span class="lang-native">বাংলা</span>
                                <span class="lang-en">Bengali</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="mr">
                                <span class="lang-native">मराठी</span>
                                <span class="lang-en">Marathi</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="gu">
                                <span class="lang-native">ગુજરાતી</span>
                                <span class="lang-en">Gujarati</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="pa">
                                <span class="lang-native">ਪੰਜਾਬੀ</span>
                                <span class="lang-en">Punjabi</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="kn">
                                <span class="lang-native">ಕನ್ನಡ</span>
                                <span class="lang-en">Kannada</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="ml">
                                <span class="lang-native">മലയാളം</span>
                                <span class="lang-en">Malayalam</span>
                            </button>
                            <button type="button" class="lang-option-btn" data-lang="or">
                                <span class="lang-native">ଓଡ଼ିଆ</span>
                                <span class="lang-en">Odia</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" class="header-btn trigger-search-modal" aria-label="Search articles (Press Ctrl+K)">
                    <?= icon('search') ?>
                </button>

                <button type="button" class="header-btn mobile-menu-toggle" aria-label="Open mobile navigation menu">
                    <?= icon('menu') ?>
                </button>
            </div>

        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="mobile-nav-drawer" id="mobileNavDrawer" aria-hidden="true">
    <div class="mobile-nav-panel">
        <div class="mobile-nav-header">
            <div class="site-brand">
                <img src="<?= asset('sarkari-logo-transparent.png') ?>" alt="<?= e(SITE_NAME) ?> - Sarkari Result &amp; Latest Govt Jobs 2026" title="<?= e(SITE_NAME) ?> - Official Education &amp; Recruitment Portal" class="site-logo-img" width="185" height="48" style="height: 38px; width: auto;">
            </div>
            <button type="button" class="header-btn mobile-nav-close" aria-label="Close navigation menu">
                <?= icon('close') ?>
            </button>
        </div>

        <!-- Mobile Language Selector Section -->
        <div class="mobile-lang-box">
            <div class="mobile-lang-title">
                <?= icon('globe', 'icon-xs') ?>
                <span>Choose Language / भाषा चुनें</span>
            </div>
            <select class="form-select mobile-lang-select" id="mobileLangSelect" aria-label="Choose Language">
                <option value="en">English (Default)</option>
                <option value="hi">हिंदी (Hindi)</option>
                <option value="ta">தமிழ் (Tamil)</option>
                <option value="te">తెలుగు (Telugu)</option>
                <option value="ur">اردو (Urdu)</option>
                <option value="bn">বাংলা (Bengali)</option>
                <option value="mr">मराठी (Marathi)</option>
                <option value="gu">ગુજરાતી (Gujarati)</option>
                <option value="pa">ਪੰਜਾਬੀ (Punjabi)</option>
                <option value="kn">ಕನ್ನಡ (Kannada)</option>
                <option value="ml">മലയാളം (Malayalam)</option>
                <option value="or">ଓଡ଼ିଆ (Odia)</option>
            </select>
        </div>

        <nav class="mobile-nav-list" aria-label="Mobile Navigation">
            <?php 
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            foreach ($primaryNavLinks as $link): 
                $isActive = ($link['url'] === '' && ($currentUrl === '/' || $currentUrl === BASE_PATH || $currentUrl === BASE_PATH . '/'))
                            || ($link['url'] !== '' && str_contains($currentUrl, trim($link['url'], '/')));
            ?>
                <a href="<?= url($link['url']) ?>" class="mobile-nav-link <?= $isActive ? 'active' : '' ?>">
                    <span><?= e($link['label']) ?></span>
                    <?= icon('chevron-right', 'icon-sm') ?>
                </a>
            <?php endforeach; ?>

            <!-- Featured Portals & Tools Cards Section -->
            <div class="mobile-nav-featured-section">
                
                <!-- State Govt Jobs Card -->
                <a href="<?= url('state-jobs/') ?>" class="mobile-feature-card <?= str_contains($currentUrl, 'state-jobs') ? 'active' : '' ?>">
                    <div class="mobile-feature-icon state-icon">
                        <?= icon('award', 'icon-sm') ?>
                    </div>
                    <div class="mobile-feature-body">
                        <div class="mobile-feature-top">
                            <span class="mobile-feature-title">State Govt Jobs</span>
                            <span class="mobile-feature-badge state-badge">2026</span>
                        </div>
                        <span class="mobile-feature-sub">28 States &amp; UTs Employment Hub</span>
                    </div>
                    <span class="mobile-feature-arrow"><?= icon('chevron-right', 'icon-xs') ?></span>
                </a>

                <!-- More: Examination Tools & Portals Accordion Card -->
                <div class="mobile-accordion-container <?= $isMoreActive ? 'open' : '' ?>">
                    <button type="button" class="mobile-feature-card mobile-accordion-trigger <?= $isMoreActive ? 'open' : '' ?>" id="mobileMegaToggle" aria-expanded="<?= $isMoreActive ? 'true' : 'false' ?>">
                        <div class="mobile-feature-icon tools-icon">
                            <?= icon('layers', 'icon-sm') ?>
                        </div>
                        <div class="mobile-feature-body">
                            <div class="mobile-feature-top">
                                <span class="mobile-feature-title">More Portals &amp; Tools</span>
                                <span class="mobile-feature-badge tools-badge">Interactive</span>
                            </div>
                            <span class="mobile-feature-sub">Salary, Age, Portals &amp; Guides</span>
                        </div>
                        <span class="mobile-accordion-chevron"><?= icon('chevron-right', 'icon-xs') ?></span>
                    </button>

                    <div class="mobile-accordion-drawer <?= $isMoreActive ? 'open' : '' ?>" id="mobileMegaCollapse">
                        
                        <!-- Section 1: Examination & Student Tools -->
                        <div class="mobile-sub-group">
                            <div class="mobile-sub-header">
                                <span class="mobile-sub-header-title">Examination Tools</span>
                                <span class="mobile-sub-header-badge">Interactive</span>
                            </div>
                            <div class="mobile-sub-list">
                                <?php foreach ($toolsLinks as $tl): 
                                    $isTlActive = str_contains($currentUrl, trim($tl['url'], '/'));
                                ?>
                                    <a href="<?= url($tl['url']) ?>" class="mobile-sub-item <?= $isTlActive ? 'active' : '' ?>">
                                        <span class="mobile-sub-icon"><?= icon($tl['icon'], 'icon-xs') ?></span>
                                        <div class="mobile-sub-text">
                                            <span class="mobile-sub-title"><?= e($tl['label']) ?></span>
                                            <span class="mobile-sub-desc"><?= e($tl['desc']) ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Section 2: Academic & Career Portals -->
                        <div class="mobile-sub-group">
                            <div class="mobile-sub-header">
                                <span class="mobile-sub-header-title">Academic &amp; Career Portals</span>
                                <span class="mobile-sub-header-badge">Live Updates</span>
                            </div>
                            <div class="mobile-sub-list">
                                <?php foreach ($portalLinks as $pl): 
                                    $isPlActive = str_contains($currentUrl, trim($pl['url'], '/'));
                                ?>
                                    <a href="<?= url($pl['url']) ?>" class="mobile-sub-item <?= $isPlActive ? 'active' : '' ?>">
                                        <span class="mobile-sub-icon"><?= icon($pl['icon'], 'icon-xs') ?></span>
                                        <div class="mobile-sub-text">
                                            <span class="mobile-sub-title"><?= e($pl['label']) ?></span>
                                            <span class="mobile-sub-desc"><?= e($pl['desc']) ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </nav>

        <div style="padding: 1.25rem; margin-top: auto; border-top: 1px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted);">
            <p><strong>Sarkari.online Information Network</strong></p>
            <p>Independent alerts on Indian entrance exams, results, admit cards, notifications, and scholarships.</p>
        </div>
    </div>
</div>

<!-- Quick Search Modal Dialog -->
<div class="search-modal" id="searchModal" role="dialog" aria-modal="true" aria-label="Search Articles">
    <div class="search-modal-box">
        <form action="<?= url('search/') ?>" method="GET" class="search-modal-form">
            <?= icon('search', 'icon-lg', ['style' => 'color: var(--text-muted); margin-right: 0.5rem;']) ?>
            <input type="search" name="q" class="search-modal-input" placeholder="Search exams, results, admit cards, jobs..." autocomplete="off">
            <button type="button" class="header-btn search-modal-close" aria-label="Close search modal">
                <?= icon('close') ?>
            </button>
        </form>
        <div class="search-quick-tags">
            <div class="search-tags-title">Popular Searches</div>
            <div class="search-tag-list">
                <a href="<?= url('search/?q=NEET+UG') ?>" rel="nofollow" class="search-tag-chip">NEET UG 2026</a>
                <a href="<?= url('search/?q=JEE+Advanced') ?>" rel="nofollow" class="search-tag-chip">JEE Advanced Cutoff</a>
                <a href="<?= url('search/?q=SSC+CGL') ?>" rel="nofollow" class="search-tag-chip">SSC CGL 2026</a>
                <a href="<?= url('search/?q=CUET+Admit+Card') ?>" rel="nofollow" class="search-tag-chip">CUET Admit Card</a>
                <a href="<?= url('search/?q=UPSC+Answer+Key') ?>" rel="nofollow" class="search-tag-chip">UPSC Answer Key</a>
                <a href="<?= url('search/?q=Scholarships') ?>" rel="nofollow" class="search-tag-chip">NSP Scholarship</a>
            </div>
        </div>
    </div>
</div>
