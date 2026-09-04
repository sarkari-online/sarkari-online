<?php
/**
 * Sarkari.online - 8-Card Official Examination & Recruitment Grid
 * Decluttered Senior Creative Front-End Edition.
 * Features:
 * - Real official high-resolution board logos (PNG assets in /assets/images/boards/)
 * - Intelligent Board-to-Article matching (SSC gets SSC, Railways gets RRB, etc.)
 * - Zero visual noise: removed duplicate slogans and repetitive subtitles
 * - Subdued, elegant background watermarks that never interfere with text
 * - High-clarity typography, clean status pills, and crisp action buttons
 */

if (!isset($hotArticles)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(16);
    if (!empty($dbHero)) {
        $hotArticles = $dbHero;
    } else {
        $hotArticles = MockData::getLatestArticles(8);
    }
}

// Preset metadata mapping matching the 8 official commission cards
$cardPresets = [
    0 => [
        'board_key' => 'ssc',
        'keywords' => ['ssc', 'cgl', 'chsl', 'mts', 'je', 'cpo', 'gd constable'],
        'num' => '01',
        'badge' => '★ Trending',
        'badge_bg' => '#DC2626',
        'num_bg' => '#FEE2E2',
        'num_color' => '#DC2626',
        'board_title' => 'SSC',
        'board_name' => 'Staff Selection Commission',
        'default_title' => 'SSC CGL Tier 1 2026',
        'watermark' => 'parliament',
        'watermark_color' => '#DC2626',
        'status_text' => 'Admit Card Released',
        'status_bg' => '#DCFCE7',
        'status_color' => '#166534',
        'status_icon' => 'file-text',
        'date1_label' => 'Exam Date',
        'date1_val' => '12 Sep 2026',
        'date2_label' => 'Last Date',
        'date2_val' => '—',
        'btn1_text' => 'Download Slip',
        'btn1_icon' => 'download',
        'btn1_color' => '#DC2626',
        'btn1_bg' => '#FEF2F2',
        'btn1_border' => '#FECACA',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#B91C1C',
        'logo_img' => 'ssc.png'
    ],
    1 => [
        'board_key' => 'railways',
        'keywords' => ['railway', 'rrb', 'ntpc', 'alp', 'rrc', 'loco pilot'],
        'num' => '02',
        'badge' => '★ Popular',
        'badge_bg' => '#16A34A',
        'num_bg' => '#DCFCE7',
        'num_color' => '#16A34A',
        'board_title' => 'Indian Railways',
        'board_name' => 'Railway Recruitment Board',
        'default_title' => 'RRB NTPC 2026',
        'watermark' => 'train',
        'watermark_color' => '#16A34A',
        'status_text' => 'Online Form Live',
        'status_bg' => '#DCFCE7',
        'status_color' => '#166534',
        'status_icon' => 'briefcase',
        'date1_label' => 'Last Date',
        'date1_val' => '20 Sep 2026',
        'date2_label' => 'Exam Date',
        'date2_val' => 'Nov 2026',
        'btn1_text' => 'Apply Online',
        'btn1_icon' => 'external-link',
        'btn1_color' => '#16A34A',
        'btn1_bg' => '#F0FDF4',
        'btn1_border' => '#BBF7D0',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#065F46',
        'logo_img' => 'railways.png'
    ],
    2 => [
        'board_key' => 'upsc',
        'keywords' => ['upsc', 'civil services', 'nda', 'cds', 'ias', 'ifs', 'ips', 'capf', 'epfo'],
        'num' => '03',
        'badge' => '★ Hot',
        'badge_bg' => '#2563EB',
        'num_bg' => '#DBEAFE',
        'num_color' => '#2563EB',
        'board_title' => 'UPSC',
        'board_name' => 'Union Public Service Commission',
        'default_title' => 'Civil Services Exam 2026',
        'watermark' => 'rashtrapati',
        'watermark_color' => '#2563EB',
        'status_text' => 'Final Result Out',
        'status_bg' => '#DBEAFE',
        'status_color' => '#1E40AF',
        'status_icon' => 'bar-chart',
        'date1_label' => 'Result Date',
        'date1_val' => '02 Sep 2026',
        'date2_label' => 'Interview',
        'date2_val' => 'Apr - May 2026',
        'btn1_text' => 'Check Result',
        'btn1_icon' => 'external-link',
        'btn1_color' => '#2563EB',
        'btn1_bg' => '#EFF6FF',
        'btn1_border' => '#BFDBFE',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#1D4ED8',
        'logo_img' => 'upsc.png'
    ],
    3 => [
        'board_key' => 'uppolice',
        'keywords' => ['police', 'constable', 'sub inspector', 'uppbpb', 'daroga', 'cisf', 'crpf', 'bsf'],
        'num' => '04',
        'badge' => '★ Updated',
        'badge_bg' => '#EA580C',
        'num_bg' => '#FEF3C7',
        'num_color' => '#D97706',
        'board_title' => 'UP Police',
        'board_name' => 'Uttar Pradesh Police',
        'default_title' => 'Constable Re-Exam 2026',
        'watermark' => 'police',
        'watermark_color' => '#EA580C',
        'status_text' => 'Answer Key Live',
        'status_bg' => '#FFEDD5',
        'status_color' => '#9A3412',
        'status_icon' => 'key',
        'date1_label' => 'Exam Date',
        'date1_val' => '27 Aug 2026',
        'date2_label' => 'Result Date',
        'date2_val' => 'Oct 2026',
        'btn1_text' => 'Check Key',
        'btn1_icon' => 'key',
        'btn1_color' => '#C2410C',
        'btn1_bg' => '#FFF7ED',
        'btn1_border' => '#FED7AA',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#C2410C',
        'logo_img' => 'uppolice.png'
    ],
    4 => [
        'board_key' => 'aicte',
        'keywords' => ['aicte', 'technical education', 'doctoral fellowship', 'phd', 'fellowship'],
        'num' => '05',
        'badge' => 'New',
        'badge_bg' => '#7C3AED',
        'num_bg' => '#F3E8FF',
        'num_color' => '#9333EA',
        'board_title' => 'AICTE',
        'board_name' => 'All India Council for Technical Education',
        'default_title' => 'AICTE Doctoral Fellowship 2026',
        'watermark' => 'aicte',
        'watermark_color' => '#7C3AED',
        'status_text' => 'Official Notice',
        'status_bg' => '#F3E8FF',
        'status_color' => '#6B21A8',
        'status_icon' => 'file-text',
        'date1_label' => 'Last Date',
        'date1_val' => '15 Oct 2026',
        'date2_label' => 'Mode',
        'date2_val' => 'Online',
        'btn1_text' => 'View Notice',
        'btn1_icon' => 'file-text',
        'btn1_color' => '#7C3AED',
        'btn1_bg' => '#FAF5FF',
        'btn1_border' => '#E9D5FF',
        'btn2_text' => 'Apply Online',
        'btn2_bg' => '#6D28D9',
        'logo_img' => 'aicte.png'
    ],
    5 => [
        'board_key' => 'nta',
        'keywords' => ['nta', 'neet', 'jee', 'cuet', 'ugc net', 'counselling', 'mbbs', 'iit'],
        'num' => '06',
        'badge' => '★ Live',
        'badge_bg' => '#059669',
        'num_bg' => '#CFFAFE',
        'num_color' => '#0891B2',
        'board_title' => 'NTA',
        'board_name' => 'National Testing Agency',
        'default_title' => 'NEET UG Round 2 Seat 2026',
        'watermark' => 'medical',
        'watermark_color' => '#0891B2',
        'status_text' => 'Registration Active',
        'status_bg' => '#D1FAE5',
        'status_color' => '#065F46',
        'status_icon' => 'user',
        'date1_label' => 'Last Date',
        'date1_val' => '10 Sep 2026',
        'date2_label' => 'Allotment',
        'date2_val' => 'Sep 2026',
        'btn1_text' => 'Check Status',
        'btn1_icon' => 'refresh-cw',
        'btn1_color' => '#0891B2',
        'btn1_bg' => '#ECFEFF',
        'btn1_border' => '#A5F3FC',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#0F766E',
        'logo_img' => 'nta.png'
    ],
    6 => [
        'board_key' => 'ibps',
        'keywords' => ['ibps', 'sbi', 'bank', 'po', 'clerk', 'rbi', 'nabard', 'specialist officer'],
        'num' => '07',
        'badge' => '★ Popular',
        'badge_bg' => '#2563EB',
        'num_bg' => '#DBEAFE',
        'num_color' => '#2563EB',
        'board_title' => 'IBPS',
        'board_name' => 'Institute of Banking Personnel Selection',
        'default_title' => 'SBI PO 2026',
        'watermark' => 'bank',
        'watermark_color' => '#2563EB',
        'status_text' => 'Notification Live',
        'status_bg' => '#DBEAFE',
        'status_color' => '#1E40AF',
        'status_icon' => 'bell',
        'date1_label' => 'Last Date',
        'date1_val' => '21 Sep 2026',
        'date2_label' => 'Exam Date',
        'date2_val' => 'Oct/Nov 2026',
        'btn1_text' => 'View Notice',
        'btn1_icon' => 'file-text',
        'btn1_color' => '#2563EB',
        'btn1_bg' => '#EFF6FF',
        'btn1_border' => '#BFDBFE',
        'btn2_text' => 'Apply Online',
        'btn2_bg' => '#1D4ED8',
        'logo_img' => 'ibps.png'
    ],
    7 => [
        'board_key' => 'cbse',
        'keywords' => ['cbse', 'class 10', 'class 12', 'board result', 'scorecard', 'bpsc', 'tre'],
        'num' => '08',
        'badge' => '★ Updated',
        'badge_bg' => '#E11D48',
        'num_bg' => '#FFE4E6',
        'num_color' => '#E11D48',
        'board_title' => 'CBSE',
        'board_name' => 'Central Board of Secondary Education',
        'default_title' => 'Class 10/12 Result 2026',
        'watermark' => 'cbse',
        'watermark_color' => '#E11D48',
        'status_text' => 'Result Declared',
        'status_bg' => '#FFE4E6',
        'status_color' => '#9F1239',
        'status_icon' => 'bar-chart',
        'date1_label' => 'Result Date',
        'date1_val' => '13 May 2026',
        'date2_label' => 'Re-evaluation',
        'date2_val' => 'Started',
        'btn1_text' => 'View Result',
        'btn1_icon' => 'external-link',
        'btn1_color' => '#E11D48',
        'btn1_bg' => '#FFF1F2',
        'btn1_border' => '#FECDD3',
        'btn2_text' => 'View Details',
        'btn2_bg' => '#BE123C',
        'logo_img' => 'cbse.png'
    ]
];

// Helper: Match article pool to appropriate board preset
$poolArticles = !empty($hotArticles) ? $hotArticles : [];
$usedArticleIds = [];

$matchArticleForPreset = function(array $keywords) use (&$poolArticles, &$usedArticleIds): ?array {
    foreach ($poolArticles as $art) {
        $id = $art['id'] ?? null;
        if ($id && in_array($id, $usedArticleIds, true)) {
            continue;
        }
        $title = strtolower($art['title'] ?? '');
        foreach ($keywords as $kw) {
            if (strpos($title, strtolower($kw)) !== false) {
                if ($id) {
                    $usedArticleIds[] = $id;
                }
                return $art;
            }
        }
    }
    return null;
};

// Helper: Render Background Architectural Watermark SVG
function render_background_watermark(string $type, string $color): string {
    switch ($type) {
        case 'train':
            return '<svg viewBox="0 0 160 120" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M15 85L35 30C40 22 52 18 70 18H130C145 18 155 28 155 42V85H15ZM45 35L33 70H80V35H45ZM90 35V70H140V45C140 38 135 35 128 35H90ZM42 78C45 78 48 75 48 72C48 69 45 66 42 66C39 66 36 69 36 72C36 75 39 78 42 78ZM5 98H155V106H5V98ZM0 112H160V118H0V112Z"/></svg>';

        case 'rashtrapati':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 10C78 10 76 12 76 15V25C65 26 55 35 55 48C55 58 62 67 72 70V125H30V135H130V125H88V70C98 67 105 58 105 48C105 35 95 26 84 25V15C84 12 82 10 80 10ZM65 48C65 39 72 32 80 32C88 32 95 39 95 48H65ZM75 80H85V120H75V80Z"/></svg>';

        case 'police':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 10L30 30V75C30 110 52 135 80 140C108 135 130 110 130 75V30L80 10ZM80 25L115 39V75C115 102 98 123 80 128C62 123 45 102 45 75V39L80 25ZM80 45L86 62H104L90 73L95 90L80 79L65 90L70 73L56 62H74L80 45Z"/></svg>';

        case 'aicte':
            return '<svg viewBox="0 0 140 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M70 45C56.2 45 45 56.2 45 70C45 83.8 56.2 95 70 95C83.8 95 95 83.8 95 70C95 56.2 83.8 45 70 45ZM70 58C76.6 58 82 63.4 82 70C82 76.6 76.6 82 70 82C63.4 82 58 76.6 58 70C58 63.4 63.4 58 70 58ZM125 62V51L109 48C108 44 106 40 104 37L113 24L103 14L90 23C87 21 83 19 79 18L76 2H64L61 18C57 19 53 21 50 23L37 14L27 24L36 37C34 40 32 44 31 48L15 51V62L31 65C32 69 34 73 36 76L27 89L37 99L50 90C53 92 57 94 61 95L64 111H76L79 95C83 94 87 92 90 90L103 99L113 89L104 76C106 73 108 69 109 65L125 62Z"/></svg>';

        case 'medical':
            return '<svg viewBox="0 0 140 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M40 20C40 16 43 13 47 13H53V21H47V45C47 57 57 67 70 67C83 67 93 57 93 45V21H87V13H93C97 13 100 16 100 20V45C100 61 87 74 71 75V95C71 103 64 110 56 110H52C44 110 38 116 38 124C38 132 44 138 52 138H60C68 138 74 132 74 124V75C75 75 76 75 77 75C103 75 124 54 124 28V20H116V28C116 49 99 67 77 67V45C77 41 74 38 70 38C66 38 63 41 63 45V67C41 67 24 49 24 28V20H16V28C16 54 37 75 63 75V124C63 126 62 128 60 128H52C50 128 48 126 48 124C48 122 50 120 52 120H56C60 120 63 117 63 113V75C47 74 34 61 34 45V20H40Z"/></svg>';

        case 'bank':
            return '<svg viewBox="0 0 160 120" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 12L15 42V52H145V42L80 12ZM28 60V105H42V60H28ZM58 60V105H72V60H58ZM88 60V105H102V60H88ZM118 60V105H132V60H118ZM12 110V120H148V110H12Z"/></svg>';

        case 'cbse':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 45C68 32 48 30 20 30V105C48 105 68 107 80 122C92 107 112 105 140 105V30C112 30 92 32 80 45ZM74 102C63 92 48 88 34 88V42C48 42 63 45 74 55V102ZM126 88C112 88 97 92 86 102V55C97 45 112 42 126 42V88ZM80 12L76 24H84L80 12ZM50 20L44 30L52 33L50 20ZM110 20L108 33L116 30L110 20Z"/></svg>';

        case 'parliament':
        default:
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 15C77 15 75 17 75 20V28H68C66 28 65 30 65 32V45H40C38 45 36 47 36 49V130H124V49C124 47 122 45 120 45H95V32C95 30 94 28 92 28H85V20C85 17 83 15 80 15ZM70 52C70 42 75 35 80 35C85 35 90 42 90 52V130H70V52ZM44 55H62V130H44V55ZM98 55H116V130H98V55ZM20 130H140V140H20V130Z"/></svg>';
    }
}
?>
<section class="chatgpt-grid-section" aria-label="Official Commission Highlights and Notifications">
    <div class="chatgpt-hot-grid">
        <?php 
        for ($i = 0; $i < 8; $i++): 
            $preset = $cardPresets[$i];
            
            // Intelligently find matching database article for this commission
            $matchedArticle = $matchArticleForPreset($preset['keywords']);

            $articleTitle = $matchedArticle['title'] ?? $preset['default_title'];
            $articleSlug = $matchedArticle['slug'] ?? 'latest-updates';

            // Watermark SVG
            $watermarkSvg = render_background_watermark($preset['watermark'], $preset['watermark_color']);
        ?>
            <article class="cg-card">
                <!-- Background Architectural Watermark (Top Right Corner Accent) -->
                <div class="cg-card-watermark" aria-hidden="true">
                    <?= $watermarkSvg ?>
                </div>

                <div class="cg-card-body-top">
                    <!-- Top Row: Number Pill + Status Badge -->
                    <div class="cg-card-topbar">
                        <span class="cg-num-badge" style="background-color: <?= $preset['num_bg'] ?>; color: <?= $preset['num_color'] ?>;">
                            <?= $preset['num'] ?>
                        </span>
                        <span class="cg-badge-status" style="background-color: <?= $preset['badge_bg'] ?>;">
                            <?= $preset['badge'] ?>
                        </span>
                    </div>

                    <!-- Board Header: Real Official Logo Image + Short & Full Title -->
                    <div class="cg-board-header">
                        <div class="cg-board-logo">
                            <img src="<?= url('assets/images/boards/' . $preset['logo_img']) ?>" 
                                 alt="<?= e($preset['board_title']) ?>" 
                                 width="42" 
                                 height="42" 
                                 loading="lazy" 
                                 class="cg-board-logo-img">
                        </div>
                        <div class="cg-board-info">
                            <h4 class="cg-board-title"><?= e($preset['board_title']) ?></h4>
                            <span class="cg-board-sub" title="<?= e($preset['board_name']) ?>"><?= e($preset['board_name']) ?></span>
                        </div>
                    </div>

                    <!-- Notice Title (Clean & High-Contrast) -->
                    <h3 class="cg-notice-title">
                        <a href="<?= url('article/' . $articleSlug . '/') ?>" title="<?= e($articleTitle) ?>">
                            <?= e(truncate_text($articleTitle, 52)) ?>
                        </a>
                    </h3>

                    <!-- Status Pill -->
                    <div class="cg-status-pill" style="background-color: <?= $preset['status_bg'] ?>; color: <?= $preset['status_color'] ?>;">
                        <?= icon($preset['status_icon'], 'cg-status-icon') ?>
                        <span><?= e($preset['status_text']) ?></span>
                    </div>

                    <!-- 2-Column Key Dates Strip -->
                    <div class="cg-dates-grid">
                        <div class="cg-date-item">
                            <?= icon('calendar', 'cg-date-icon') ?>
                            <div class="cg-date-text">
                                <span class="cg-date-label"><?= e($preset['date1_label']) ?></span>
                                <span class="cg-date-val"><?= e($preset['date1_val']) ?></span>
                            </div>
                        </div>
                        <div class="cg-date-item">
                            <?= icon('clock', 'cg-date-icon') ?>
                            <div class="cg-date-text">
                                <span class="cg-date-label"><?= e($preset['date2_label']) ?></span>
                                <span class="cg-date-val"><?= e($preset['date2_val']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dual Action Buttons -->
                <div class="cg-actions-row">
                    <a href="<?= url('article/' . $articleSlug . '/') ?>" class="cg-btn-outline" style="color: <?= $preset['btn1_color'] ?>; background-color: <?= $preset['btn1_bg'] ?>; border-color: <?= $preset['btn1_border'] ?>;">
                        <?= icon($preset['btn1_icon'], 'cg-btn-icon') ?>
                        <span><?= e($preset['btn1_text']) ?></span>
                    </a>
                    <a href="<?= url('article/' . $articleSlug . '/') ?>" class="cg-btn-solid" style="background-color: <?= $preset['btn2_bg'] ?>;">
                        <span><?= e($preset['btn2_text']) ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </article>
        <?php endfor; ?>
    </div>
</section>
