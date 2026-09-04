<?php
/**
 * Sarkari.online - Official National Gazette & Commission Desk
 * High-craft editorial grid featuring distinctive agency crest medallions
 * (Ashoka Lion, Indian Railways Locomotive, Police Shield, Stethoscope, Bank Pillars, AICTE Gear, CBSE Book).
 * Zero rainbow top-borders, 100% recognizable vector crests, senior-designer aesthetic.
 */

if (!isset($hotArticles)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(8);
    if (!empty($dbHero)) {
        $hotArticles = $dbHero;
    } else {
        $heroData = MockData::getHeroArticles();
        $hotArticles = MockData::getLatestArticles(8);
    }
}

if (!function_exists('render_crest_svg')) {
    function render_crest_svg(string $type): string {
        switch ($type) {
            case 'railway':
                // Aerodynamic Locomotive & Tracks
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M5 4C5 2.9 5.9 2 7 2H17C18.1 2 19 2.9 19 4V14C19 16.2 17.2 18 15 18H9C6.8 18 5 16.2 5 14V4ZM7 5V9H17V5H7ZM8.5 15C9.3 15 10 14.3 10 13.5C10 12.7 9.3 12 8.5 12C7.7 12 7 12.7 7 13.5C7 14.3 7.7 15 8.5 15ZM15.5 15C16.3 15 17 14.3 17 13.5C17 12.7 16.3 12 15.5 12C14.7 12 14 12.7 14 13.5C14 14.3 14.7 15 15.5 15ZM4 19L2 22H5L6 20H18L19 22H22L20 19H4Z"/></svg>';

            case 'police':
                // Police Star on Heraldic Shield
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 1L3 5V11C3 16.5 6.8 21.7 12 23C17.2 21.7 21 16.5 21 11V5L12 1ZM12 4.2L18.5 7.1V11C18.5 15.2 15.7 19.3 12 20.5C8.3 19.3 5.5 15.2 5.5 11V7.1L12 4.2ZM12 7.5L13.3 10.7H16.8L14 12.7L15 15.9L12 14L9 15.9L10 12.7L7.2 10.7H10.7L12 7.5Z"/></svg>';

            case 'medical':
                // Stethoscope & Healing Cross
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M10 2V5H8V7H10V10H12V7H14V5H12V2H10ZM6 3C5.4 3 5 3.4 5 4V10C5 13.9 8.1 17 12 17C15.9 17 19 13.9 19 10V4C19 3.4 18.6 3 18 3C17.4 3 17 3.4 17 4V10C17 12.8 14.8 15 12 15C9.2 15 7 12.8 7 10V4C7 3.4 6.6 3 6 3ZM12 17V19C12 20.1 12.9 21 14 21H16C17.1 21 18 20.1 18 19V17.8C18.9 17.4 19.5 16.5 19.5 15.5C19.5 14.1 18.4 13 17 13C15.6 13 14.5 14.1 14.5 15.5C14.5 16.5 15.1 17.4 16 17.8V19H14V17H12Z"/></svg>';

            case 'bank':
                // Classical Bank Pillars & Pediment
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 2L2 7V9H22V7L12 2ZM4 10V18H6V10H4ZM8.5 10V18H10.5V10H8.5ZM13.5 10V18H15.5V10H13.5ZM18 10V18H20V10H18ZM2 19V22H22V19H2Z"/></svg>';

            case 'aicte':
            case 'technical':
                // Technical Gear & Knowledge Flame
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 8C9.8 8 8 9.8 8 12C8 14.2 9.8 16 12 16C14.2 16 16 14.2 16 12C16 9.8 14.2 8 12 8ZM12 10C13.1 10 14 10.9 14 12C14 13.1 13.1 14 12 14C10.9 14 10 13.1 10 12C10 10.9 10.9 10 12 10ZM20.5 11.2L18.8 10.9C18.7 10.4 18.4 9.9 18.1 9.4L19.2 8.1L17.9 6.8L16.6 7.9C16.1 7.6 15.6 7.3 15.1 7.2L14.8 5.5H13.2L12.9 7.2C12.4 7.3 11.9 7.6 11.4 7.9L10.1 6.8L8.8 8.1L9.9 9.4C9.6 9.9 9.3 10.4 9.2 10.9L7.5 11.2V12.8L9.2 13.1C9.3 13.6 9.6 14.1 9.9 14.6L8.8 15.9L10.1 17.2L11.4 16.1C11.9 16.4 12.4 16.7 12.9 16.8L13.2 18.5H14.8L15.1 16.8C15.6 16.7 16.1 16.4 16.6 16.1L17.9 17.2L19.2 15.9L18.1 14.6C18.4 14.1 18.7 13.6 18.8 13.1L20.5 12.8V11.2ZM12 2C11 3.5 10.5 5 11 6.5C11.3 6 11.8 5.7 12.2 5.5C12.6 6.5 13.5 7.5 14 7.5C14.5 7.5 15 6 14 4.5C13.2 3.3 12.5 2.5 12 2Z"/></svg>';

            case 'cbse':
            case 'education':
                // Open Book & Radiating Knowledge
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 6C10 4 7 3.5 3 3.5V17C7 17 10 17.5 12 19.5C14 17.5 17 17 21 17V3.5C17 3.5 14 4 12 6ZM11 16.8C9.4 15.4 6.8 15 4.8 15V5.2C6.8 5.2 9.4 5.6 11 6.9V16.8ZM19.2 15C17.2 15 14.6 15.4 13 16.8V6.9C14.6 5.6 17.2 5.2 19.2 5.2V15ZM12 1L11.2 2.8H12.8L12 1ZM7.5 2.5L6.5 4L8 4.5L7.5 2.5ZM16.5 2.5L16 4.5L17.5 4L16.5 2.5Z"/></svg>';

            case 'scholarship':
            case 'degree':
                // Graduation Cap & Ribbon
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 3L1 8L12 13L21 9V15H23V8L12 3ZM12 15L5 11.8V16C5 18.8 8.1 21 12 21C15.9 21 19 18.8 19 16V11.8L12 15Z"/></svg>';

            case 'ashoka':
            default:
                // Ashoka Lion Capital (3 Lions & Base)
                return '<svg viewBox="0 0 24 24" fill="currentColor" class="crest-svg"><path d="M12 2C10.6 2 9.5 3.1 9.5 4.5C9.5 5.5 10.1 6.3 11 6.7C10 7.8 8.5 9.5 8.5 12C8.5 14 10 15.5 11.5 16V18H6V20H18V18H12.5V16C14 15.5 15.5 14 15.5 12C15.5 9.5 14 7.8 13 6.7C13.9 6.3 14.5 5.5 14.5 4.5C14.5 3.1 13.4 2 12 2ZM5.5 8C4.4 8 3.5 8.9 3.5 10C3.5 11.5 4.8 13 6.5 13.5V16H8V12.5C7 12 6 11 6 10C6 9 5.8 8 5.5 8ZM18.5 8C18.2 8 18 9 18 10C18 11 17 12 16 12.5V16H17.5V13.5C19.2 13 20.5 11.5 20.5 10C20.5 8.9 19.6 8 18.5 8ZM12 13C12.6 13 13 13.4 13 14C13 14.6 12.6 15 12 15C11.4 15 11 14.6 11 14C11 13.4 11.4 13 12 13ZM4 21H20V22.5H4V21Z"/></svg>';
        }
    }
}

if (!function_exists('resolve_commission_crest')) {
    function resolve_commission_crest(string $title, string $categorySlug, string $agencyName = ''): array {
        $haystack = strtolower($title . ' ' . $categorySlug . ' ' . $agencyName);

        if (str_contains($haystack, 'rrb') || str_contains($haystack, 'railway') || str_contains($haystack, 'rrc') || str_contains($haystack, 'ntpc')) {
            return [
                'type' => 'railway',
                'name' => 'Railway Recruitment Boards (RRB)',
                'theme_class' => 'theme-railway'
            ];
        }

        if (str_contains($haystack, 'police') || str_contains($haystack, 'uppbpb') || str_contains($haystack, 'constable') || str_contains($haystack, 'cisf') || str_contains($haystack, 'bsf') || str_contains($haystack, 'crpf') || str_contains($haystack, 'itbp') || str_contains($haystack, 'ssb') || str_contains($haystack, 'army') || str_contains($haystack, 'defence')) {
            return [
                'type' => 'police',
                'name' => 'Police Recruitment Board',
                'theme_class' => 'theme-police'
            ];
        }

        if (str_contains($haystack, 'neet') || str_contains($haystack, 'medical') || str_contains($haystack, 'aiims') || str_contains($haystack, 'mcc') || str_contains($haystack, 'nbems') || str_contains($haystack, 'nursing')) {
            return [
                'type' => 'medical',
                'name' => 'NTA / Medical Counselling (MCC)',
                'theme_class' => 'theme-medical'
            ];
        }

        if (str_contains($haystack, 'ibps') || str_contains($haystack, 'sbi') || str_contains($haystack, 'bank') || str_contains($haystack, 'rbi') || str_contains($haystack, 'po / mt')) {
            return [
                'type' => 'bank',
                'name' => 'IBPS / Public Sector Banking',
                'theme_class' => 'theme-bank'
            ];
        }

        if (str_contains($haystack, 'aicte') || str_contains($haystack, 'fellowship') || str_contains($haystack, 'technical') || str_contains($haystack, 'diploma') || str_contains($haystack, 'polytechnic')) {
            return [
                'type' => 'aicte',
                'name' => 'All India Council for Technical Education',
                'theme_class' => 'theme-aicte'
            ];
        }

        if (str_contains($haystack, 'cbse') || str_contains($haystack, 'icse') || str_contains($haystack, 'board') || str_contains($haystack, '10th') || str_contains($haystack, '12th') || str_contains($haystack, 'revaluation') || str_contains($haystack, 'bseb') || str_contains($haystack, 'upmsp')) {
            return [
                'type' => 'cbse',
                'name' => 'Central Board of Secondary Education',
                'theme_class' => 'theme-cbse'
            ];
        }

        if (str_contains($haystack, 'upsc') || str_contains($haystack, 'nda') || str_contains($haystack, 'cds') || str_contains($haystack, 'civil services') || str_contains($haystack, 'ias')) {
            return [
                'type' => 'ashoka',
                'name' => 'Union Public Service Commission (UPSC)',
                'theme_class' => 'theme-upsc'
            ];
        }

        if (str_contains($haystack, 'ssc') || str_contains($haystack, 'cgl') || str_contains($haystack, 'chsl') || str_contains($haystack, 'mts') || str_contains($haystack, 'cpo') || str_contains($haystack, 'je ')) {
            return [
                'type' => 'ashoka',
                'name' => 'Staff Selection Commission (SSC)',
                'theme_class' => 'theme-ssc'
            ];
        }

        if (str_contains($haystack, 'scholarship') || str_contains($haystack, 'nsp') || str_contains($haystack, 'scheme')) {
            return [
                'type' => 'scholarship',
                'name' => 'National Scholarship Portal (NSP)',
                'theme_class' => 'theme-scholarship'
            ];
        }

        return [
            'type' => 'ashoka',
            'name' => !empty($agencyName) ? $agencyName : 'Official Examination Authority',
            'theme_class' => 'theme-statutory'
        ];
    }
}
?>
<section class="gazette-desk-section" aria-label="Official Commission Gazettes and Live Examination Intimations">
    <div class="gazette-desk-header">
        <div class="gazette-header-left">
            <span class="gazette-live-pulse" aria-hidden="true"></span>
            <h2 class="gazette-header-title">National Examination Gazettes</h2>
            <span class="gazette-header-rule"></span>
            <span class="gazette-header-subtitle">Verified Direct Notifications</span>
        </div>
        <div class="gazette-header-right">
            <span class="gazette-time-badge">
                <?= icon('clock', 'gazette-header-clock') ?>
                <span>Updated <?= date('d M Y') ?></span>
            </span>
        </div>
    </div>

    <div class="gazette-grid">
        <?php foreach ($hotArticles as $item): 
            $itemAuth = App\Services\AuthorityFactFetcherService::resolveAuthority($item['title'] ?? '', $item['source_url'] ?? '');
            $agencyName = $itemAuth['name'] ?? '';
            $catSlug = $item['category_slug'] ?? $item['category'] ?? 'exam-results';
            $catName = $item['category_name'] ?? 'Notice';

            $crest = resolve_commission_crest($item['title'] ?? '', $catSlug, $agencyName);
            $crestSvg = render_crest_svg($crest['type']);

            // Milestone pill & Action CTA
            $tLower = strtolower($item['title'] ?? '');
            if (str_contains($tLower, 'result')) {
                $statusPill = 'Scorecard Declared';
                $pillClass = 'pill-result';
                $actionLabel = 'Check Result';
            } elseif (str_contains($tLower, 'admit card') || str_contains($tLower, 'hall ticket') || str_contains($tLower, 'slip')) {
                $statusPill = 'Hall Ticket Live';
                $pillClass = 'pill-admit';
                $actionLabel = 'Download Slip';
            } elseif (str_contains($tLower, 'answer key')) {
                $statusPill = 'Answer Key Out';
                $pillClass = 'pill-key';
                $actionLabel = 'Check Key';
            } elseif (str_contains($tLower, 'apply') || str_contains($tLower, 'form') || str_contains($tLower, 'recruitment') || str_contains($tLower, 'vacancy')) {
                $statusPill = 'Online Form Open';
                $pillClass = 'pill-apply';
                $actionLabel = 'Apply Online';
            } else {
                $statusPill = 'Verified Notice';
                $pillClass = 'pill-default';
                $actionLabel = 'View Notice';
            }
        ?>
            <article class="gazette-card">
                <div class="gazette-card-top">
                    <div class="gazette-crest-box <?= e($crest['theme_class']) ?>" title="<?= e($crest['name']) ?>">
                        <?= $crestSvg ?>
                    </div>
                    <div class="gazette-card-meta">
                        <span class="gazette-commission-name" title="<?= e($crest['name']) ?>">
                            <?= e(truncate_text($crest['name'], 28)) ?>
                        </span>
                        <div class="gazette-category-line">
                            <span class="gazette-cat-tag"><?= e($catName) ?></span>
                            <span class="gazette-dot" aria-hidden="true">•</span>
                            <time datetime="<?= e($item['published_at'] ?? '') ?>" class="gazette-pubtime notranslate">
                                <?= time_ago($item['published_at'] ?? 'now') ?>
                            </time>
                        </div>
                    </div>
                </div>

                <h3 class="gazette-card-heading">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>" title="<?= e($item['title']) ?>">
                        <?= e($item['title']) ?>
                    </a>
                </h3>

                <div class="gazette-card-bottom">
                    <span class="gazette-status-badge <?= e($pillClass) ?>">
                        <?= e($statusPill) ?>
                    </span>
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="gazette-action-btn">
                        <span><?= e($actionLabel) ?></span>
                        <svg class="gazette-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
