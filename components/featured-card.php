<?php
/**
 * Official Statutory Bulletin Hub (Option 1: Gateway / Command Center Style)
 * Renders high-authority Lead Statutory Bulletin (left) + Fast Urgent Notice Desk (right).
 * Strictly zero blog aesthetics: no author avatars, no reading-time fluff, pure SVGs, 100% mobile-responsive.
 */

if (!isset($featured) || !isset($secondary)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(5);
    if (!empty($dbHero)) {
        $featured = $dbHero[0];
        $secondary = array_slice($dbHero, 1, 4);
    } else {
        $heroData = MockData::getHeroArticles();
        $featured = $heroData['featured'];
        $secondary = $heroData['secondary'];
    }
}

// 1. Resolve Conducting Authority & Portal for Lead Bulletin
$featAuth = App\Services\AuthorityFactFetcherService::resolveAuthority($featured['title'] ?? '', $featured['source_url'] ?? '');
$leadAgency = !empty($featAuth['name']) ? $featAuth['name'] : 'Official Statutory Authority';
$leadPortal = (!empty($featAuth['portal']) && !str_contains($featAuth['portal'], 'sarkari.online')) ? $featAuth['portal'] : null;

// Short agency acronym for tag (e.g., UPSC, SSC, AICTE, NTA, RRB)
$leadAgencyTag = 'OFFICIAL NOTICE';
if (preg_match('/^([A-Z0-9]{2,8})\b/', $leadAgency, $m)) {
    $leadAgencyTag = $m[1];
} elseif (preg_match('/\(([A-Z0-9]{2,8})\)/', $leadAgency, $m)) {
    $leadAgencyTag = $m[1];
}

// 2. Status Badge & Timeline for Lead Bulletin
$featStatus = App\Services\FeaturedSnippetService::determineStatusBadge($featured['title'] ?? '', $featured['content'] ?? '');
$featContentPlain = strip_tags($featured['content'] ?? '');

// Timeline extraction
$featTimeline = 'Refer Official Notice';
if (preg_match('/(?:last\s*date|apply\s*by|deadline|closing\s*date)[:\s]+([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67]|[0-9]{1,2}[\/\-][0-9]{1,2}[\/\-]202[67])/i', $featContentPlain, $m)) {
    $featTimeline = 'Last Date: ' . trim($m[1]);
} elseif (preg_match('/(?:exam\s*date|exam\s*on|exam\s*schedule)[:\s]+([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67])/i', $featContentPlain, $m)) {
    $featTimeline = 'Exam Date: ' . trim($m[1]);
} else {
    $tLower = strtolower($featured['title'] ?? '');
    if (str_contains($tLower, 'result')) {
        $featTimeline = 'Scorecard Download Live';
    } elseif (str_contains($tLower, 'admit card') || str_contains($tLower, 'hall ticket')) {
        $featTimeline = 'Admit Card Link Active';
    } elseif (str_contains($tLower, 'scholarship') || str_contains($tLower, 'scheme')) {
        $featTimeline = 'Registration Window Open';
    } elseif (str_contains($tLower, 'recruitment') || str_contains($tLower, 'apply') || str_contains($tLower, 'vacancy')) {
        $featTimeline = 'Online Form Active';
    } else {
        $featTimeline = 'Active Notification 2026';
    }
}

// Eligibility detection
$featCategoryName = $featured['category_name'] ?? 'Statutory Notice';
$featCategoryColor = $featured['category_color'] ?? '#1e40af';
$featEligibility = $featCategoryName;
$tHaystack = strtolower(($featured['title'] ?? '') . ' ' . substr($featContentPlain, 0, 600));
if (str_contains($tHaystack, '10th') || str_contains($tHaystack, 'matric')) {
    $featEligibility = '10th Pass / Matric';
} elseif (str_contains($tHaystack, '12th') || str_contains($tHaystack, 'intermediate') || str_contains($tHaystack, '10+2')) {
    $featEligibility = '12th Pass (10+2)';
} elseif (str_contains($tHaystack, 'graduate') || str_contains($tHaystack, 'degree') || str_contains($tHaystack, 'b.tech') || str_contains($tHaystack, 'b.e')) {
    $featEligibility = 'Graduate / Degree';
} elseif (str_contains($tHaystack, 'diploma')) {
    $featEligibility = 'Polytechnic / Diploma';
} elseif (str_contains($tHaystack, 'post graduate') || str_contains($tHaystack, 'pg ') || str_contains($tHaystack, 'masters')) {
    $featEligibility = 'Post Graduate (PG)';
}

// Synopsis
$featSynopsis = !empty($featured['excerpt']) ? truncate_text($featured['excerpt'], 140) : truncate_text($featContentPlain, 140);
?>
<section class="portal-hub-grid" aria-label="National Statutory Exam &amp; Recruitment Hub">
    
    <!-- Left: Lead Statutory Bulletin / Spotlight -->
    <article class="portal-lead-card">
        <div>
            <div class="portal-lead-topstrip">
                <div class="portal-agency-pill">
                    <?= icon('shield-check', 'portal-icon-sm') ?>
                    <span><?= e($leadAgencyTag) ?></span>
                </div>
                <div class="portal-status-pill <?= e($featStatus['class'] ?? 'status-live') ?>">
                    <span class="portal-live-dot"></span>
                    <span><?= e($featStatus['label'] ?? 'Live Update') ?></span>
                </div>
            </div>

            <h1 class="portal-lead-title">
                <a href="<?= url('article/' . $featured['slug'] . '/') ?>">
                    <?= e($featured['title']) ?>
                </a>
            </h1>

            <!-- 4-Point Verified Fact Strip -->
            <div class="portal-fact-strip">
                <div class="portal-fact-item">
                    <span class="portal-fact-icon"><?= icon('building', 'portal-icon-sm') ?></span>
                    <div class="portal-fact-data">
                        <span class="portal-fact-label">Conducting Body</span>
                        <strong class="portal-fact-val" title="<?= e($leadAgency) ?>"><?= e(truncate_text($leadAgency, 32)) ?></strong>
                    </div>
                </div>
                <div class="portal-fact-item">
                    <span class="portal-fact-icon"><?= icon('calendar', 'portal-icon-sm') ?></span>
                    <div class="portal-fact-data">
                        <span class="portal-fact-label">Timeline / Deadline</span>
                        <strong class="portal-fact-val"><?= e($featTimeline) ?></strong>
                    </div>
                </div>
                <div class="portal-fact-item">
                    <span class="portal-fact-icon"><?= icon('graduation-cap', 'portal-icon-sm') ?></span>
                    <div class="portal-fact-data">
                        <span class="portal-fact-label">Category / Level</span>
                        <strong class="portal-fact-val"><?= e($featEligibility) ?></strong>
                    </div>
                </div>
                <div class="portal-fact-item">
                    <span class="portal-fact-icon"><?= icon('globe', 'portal-icon-sm') ?></span>
                    <div class="portal-fact-data">
                        <span class="portal-fact-label">Official Portal</span>
                        <strong class="portal-fact-val">
                            <?php if ($leadPortal): ?>
                                <?= e(preg_replace('/^www\./i', '', parse_url($leadPortal, PHP_URL_HOST) ?: 'gov.in')) ?>
                            <?php else: ?>
                                Official Gazette
                            <?php endif; ?>
                        </strong>
                    </div>
                </div>
            </div>

            <?php if (!empty($featSynopsis)): ?>
                <p class="portal-lead-synopsis">
                    <?= e($featSynopsis) ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Bottom Action Bar -->
        <div class="portal-lead-actions">
            <div class="portal-btn-group">
                <a href="<?= url('article/' . $featured['slug'] . '/') ?>" class="portal-btn portal-btn-primary">
                    <?= icon('file-text', 'portal-icon-sm') ?>
                    <span>Detailed Notice &amp; Guidelines</span>
                    <?= icon('arrow-right', 'portal-icon-xs') ?>
                </a>
                <?php if (!empty($leadPortal)): ?>
                    <a href="<?= e($leadPortal) ?>" target="_blank" rel="noopener noreferrer" class="portal-btn portal-btn-secondary" title="Visit <?= e($leadAgency) ?> Official Portal">
                        <?= icon('external-link', 'portal-icon-sm') ?>
                        <span>Official Portal (<?= e(preg_replace('/^www\./i', '', parse_url($leadPortal, PHP_URL_HOST) ?: 'Portal')) ?>)</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="portal-lead-timestamp">
                <?= icon('clock', 'portal-icon-xs') ?>
                <time datetime="<?= e($featured['published_at'] ?? '') ?>" class="notranslate">
                    Updated <?= time_ago($featured['updated_at'] ?? $featured['published_at'] ?? 'now') ?>
                </time>
            </div>
        </div>
    </article>

    <!-- Right: Urgent Notice Desk / Verified Gazettes -->
    <aside class="portal-desk" aria-label="Urgent Examination &amp; Recruitment Gazettes">
        <div class="portal-desk-header">
            <div class="portal-desk-title">
                <?= icon('bolt', 'portal-icon-sm') ?>
                <span>Urgent Notice Desk</span>
            </div>
            <span class="portal-desk-pill">
                <span class="portal-live-dot"></span>
                <span>Verified Circulars</span>
            </span>
        </div>

        <div class="portal-desk-list">
            <?php foreach ($secondary as $item): 
                $itemAuth = App\Services\AuthorityFactFetcherService::resolveAuthority($item['title'] ?? '', $item['source_url'] ?? '');
                $itemAgency = !empty($itemAuth['name']) ? $itemAuth['name'] : 'Official Agency';
                $itemAgencyTag = 'GOVT';
                if (preg_match('/^([A-Z0-9]{2,8})\b/', $itemAgency, $m)) {
                    $itemAgencyTag = $m[1];
                } elseif (preg_match('/\(([A-Z0-9]{2,8})\)/', $itemAgency, $m)) {
                    $itemAgencyTag = $m[1];
                }

                $itemCatSlug = $item['category_slug'] ?? $item['category'] ?? 'exam-results';
                $itemCatName = $item['category_name'] ?? 'Notice';
                $itemCatColor = $item['category_color'] ?? '#1e40af';

                // Candidate Action
                $itemLower = strtolower($item['title'] ?? '');
                if (str_contains($itemLower, 'result')) {
                    $actionText = 'Check Result';
                    $actionClass = 'action-result';
                    $actionIcon = 'award';
                } elseif (str_contains($itemLower, 'admit card') || str_contains($itemLower, 'hall ticket') || str_contains($itemLower, 'intimation')) {
                    $actionText = 'Download Slip';
                    $actionClass = 'action-admit';
                    $actionIcon = 'id-card';
                } elseif (str_contains($itemLower, 'apply') || str_contains($itemLower, 'form') || str_contains($itemLower, 'recruitment')) {
                    $actionText = 'Apply Online';
                    $actionClass = 'action-apply';
                    $actionIcon = 'briefcase';
                } else {
                    $actionText = 'View Circular';
                    $actionClass = 'action-circular';
                    $actionIcon = 'file-text';
                }
            ?>
                <article class="portal-desk-item">
                    <div class="portal-desk-item-meta">
                        <span class="portal-agency-tag" style="background-color: <?= e($itemCatColor) ?>15; color: <?= e($itemCatColor) ?>;">
                            <?= e($itemAgencyTag) ?>
                        </span>
                        <span class="portal-cat-tag"><?= e($itemCatName) ?></span>
                        <time datetime="<?= e($item['published_at'] ?? '') ?>" class="portal-desk-time notranslate">
                            <?= time_ago($item['published_at'] ?? 'now') ?>
                        </time>
                    </div>

                    <h2 class="portal-desk-item-title">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>">
                            <?= e($item['title']) ?>
                        </a>
                    </h2>

                    <div class="portal-desk-item-footer">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="portal-desk-action-link <?= e($actionClass) ?>">
                            <?= icon($actionIcon, 'portal-icon-xs') ?>
                            <span><?= e($actionText) ?></span>
                            <?= icon('chevron-right', 'portal-icon-xs') ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </aside>

</section>
