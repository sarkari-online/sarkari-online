<?php
/**
 * Sarkari.online - High-Density 3-Column Fast Action Feed
 * Replaces heavy image box cards with a lightning-fast, readable 3-pillar portal feed:
 * [ Results Declared | Admit Cards Released | Latest Jobs & Apply ]
 *
 * 100% SVG-driven (zero emojis), mobile-responsive, high CTR.
 */

use App\Database\Database;
use App\Services\ArticleService;

// Column 1: Results & Scorecards
$resultsFeed = Database::fetchAll("
    SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug, c.name as category_name
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' 
      AND (c.slug IN ('exam-results', 'answer-keys') OR a.title LIKE '%Result%' OR a.title LIKE '%Scorecard%' OR a.title LIKE '%Answer Key%' OR a.title LIKE '%Merit List%')
    ORDER BY a.published_at DESC, a.id DESC 
    LIMIT 8
");
if (empty($resultsFeed)) {
    $resultsFeed = ArticleService::getLatestPublished(8, 1);
}

// Column 2: Admit Cards & Exam Dates
$admitFeed = Database::fetchAll("
    SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug, c.name as category_name
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' 
      AND (c.slug IN ('admit-cards', 'exam-dates') OR a.title LIKE '%Admit Card%' OR a.title LIKE '%Hall Ticket%' OR a.title LIKE '%City Slip%' OR a.title LIKE '%Exam Date%')
    ORDER BY a.published_at DESC, a.id DESC 
    LIMIT 8
");
if (empty($admitFeed)) {
    $admitFeed = ArticleService::getLatestPublished(8, 2);
}

// Column 3: Latest Government Jobs & Application Forms
$jobsFeed = Database::fetchAll("
    SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug, c.name as category_name
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    WHERE a.status = 'published' 
      AND (c.slug IN ('government-jobs', 'scholarships', 'entrance-exams') OR a.title LIKE '%Apply%' OR a.title LIKE '%Recruitment%' OR a.title LIKE '%Posts%' OR a.title LIKE '%Fellowship%' OR a.title LIKE '%Vacancy%')
    ORDER BY a.published_at DESC, a.id DESC 
    LIMIT 8
");
if (empty($jobsFeed)) {
    $jobsFeed = ArticleService::getLatestPublished(8, 6);
}

// Helper to extract a short clean badge from title or source_name
function get_feed_badge(array $item): string {
    $title = $item['title'] ?? '';
    if (preg_match('/\b(UPSC|SSC|NTA|NEET|JEE|RRB|IBPS|CBSE|AICTE|UPSSSC|DSSSB|CISF|BSF|CRPF|ITBP|SSB|BPSC|RPSC|UPPSC|MPPSC|HSSC|WBPSC|IGNOU|SBI)\b/i', $title, $m)) {
        return strtoupper($m[1]);
    }
    $source = $item['source_name'] ?? '';
    if (!empty($source) && preg_match('/\(([A-Z]{2,8})\)/', $source, $m)) {
        return $m[1];
    }
    return $item['category_name'] ?? 'Notice';
}
?>

<section class="fast-feed-section" aria-label="Quick Action Announcements">
    
    <!-- Section Header -->
    <div class="fast-feed-main-header">
        <div class="fast-feed-title-wrap">
            <span class="fast-feed-pulse-indicator"></span>
            <h2 class="fast-feed-main-title">Live Candidate Hub</h2>
            <span class="fast-feed-subtitle">Real-time official notices, scorecards &amp; application portals</span>
        </div>
        <div class="fast-feed-refresh-time">
            <?= icon('clock', 'fast-feed-clock-icon') ?>
            <span>Updated <?= date('d M Y, h:i A') ?> IST</span>
        </div>
    </div>

    <!-- 3-Pillar Clean Grid -->
    <div class="fast-feed-grid">
        
        <!-- Column 1: Results & Scorecards -->
        <div class="fast-feed-col col-results">
            <div class="fast-feed-col-header header-results">
                <div class="col-header-left">
                    <span class="col-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                    <h3 class="col-title">Results &amp; Scorecards</h3>
                </div>
                <span class="col-count-badge"><?= count($resultsFeed) ?> Live</span>
            </div>

            <ul class="fast-feed-list">
                <?php foreach ($resultsFeed as $item): ?>
                    <li class="fast-feed-item">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="fast-feed-item-link">
                            <div class="item-headline-row">
                                <span class="item-agency-tag tag-results"><?= e(get_feed_badge($item)) ?></span>
                                <span class="item-title"><?= e($item['title']) ?></span>
                            </div>
                            <div class="item-meta-row">
                                <span class="item-date"><?= date('d M Y', strtotime($item['published_at'])) ?></span>
                                <span class="item-action-link">
                                    <span>Check Result</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="fast-feed-col-footer">
                <a href="<?= url('category/exam-results/') ?>" class="col-footer-link">
                    <span>View All Results</span>
                    <?= icon('arrow-right', 'footer-arrow-icon') ?>
                </a>
            </div>
        </div>

        <!-- Column 2: Admit Cards & Hall Tickets -->
        <div class="fast-feed-col col-admit">
            <div class="fast-feed-col-header header-admit">
                <div class="col-header-left">
                    <span class="col-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                            <circle cx="9" cy="10" r="2"></circle>
                            <line x1="15" y1="8" x2="17" y2="8"></line>
                            <line x1="15" y1="12" x2="17" y2="12"></line>
                            <line x1="7" y1="16" x2="17" y2="16"></line>
                        </svg>
                    </span>
                    <h3 class="col-title">Admit Cards &amp; Slips</h3>
                </div>
                <span class="col-count-badge"><?= count($admitFeed) ?> Active</span>
            </div>

            <ul class="fast-feed-list">
                <?php foreach ($admitFeed as $item): ?>
                    <li class="fast-feed-item">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="fast-feed-item-link">
                            <div class="item-headline-row">
                                <span class="item-agency-tag tag-admit"><?= e(get_feed_badge($item)) ?></span>
                                <span class="item-title"><?= e($item['title']) ?></span>
                            </div>
                            <div class="item-meta-row">
                                <span class="item-date"><?= date('d M Y', strtotime($item['published_at'])) ?></span>
                                <span class="item-action-link">
                                    <span>Download</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="fast-feed-col-footer">
                <a href="<?= url('category/admit-cards/') ?>" class="col-footer-link">
                    <span>View All Admit Cards</span>
                    <?= icon('arrow-right', 'footer-arrow-icon') ?>
                </a>
            </div>
        </div>

        <!-- Column 3: Latest Jobs & Apply Online -->
        <div class="fast-feed-col col-jobs">
            <div class="fast-feed-col-header header-jobs">
                <div class="col-header-left">
                    <span class="col-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </span>
                    <h3 class="col-title">Latest Jobs &amp; Apply</h3>
                </div>
                <span class="col-count-badge"><?= count($jobsFeed) ?> Open</span>
            </div>

            <ul class="fast-feed-list">
                <?php foreach ($jobsFeed as $item): ?>
                    <li class="fast-feed-item">
                        <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="fast-feed-item-link">
                            <div class="item-headline-row">
                                <span class="item-agency-tag tag-jobs"><?= e(get_feed_badge($item)) ?></span>
                                <span class="item-title"><?= e($item['title']) ?></span>
                            </div>
                            <div class="item-meta-row">
                                <span class="item-date"><?= date('d M Y', strtotime($item['published_at'])) ?></span>
                                <span class="item-action-link">
                                    <span>Apply Online</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="fast-feed-col-footer">
                <a href="<?= url('category/government-jobs/') ?>" class="col-footer-link">
                    <span>View All Jobs</span>
                    <?= icon('arrow-right', 'footer-arrow-icon') ?>
                </a>
            </div>
        </div>

    </div>
</section>
