<?php
/**
 * Sarkari.online - Dynamic Editorial Split Examination Grid
 * Asymmetric Layout: 2 Prominent Lead Cards (Left) + 4 Quick Alert Cards (Right).
 * Matches user's exact architectural sketch (media_1788537413403.png).
 * 
 * Fully Autonomous & Dynamic:
 * - As new articles are published, they automatically take slot 01, 02, etc.
 * - Logos, commission names, and theme colors automatically detect from article content!
 * - Real official board logo images (/assets/images/boards/{ssc, railways, upsc, uppolice, aicte, nta, ibps, cbse, default}.png)
 */

if (!isset($hotArticles)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(10);
    if (!empty($dbHero)) {
        $hotArticles = $dbHero;
    } else {
        $hotArticles = MockData::getLatestArticles(6);
    }
}

// Fallback presets if database has fewer than 6 articles
$fallbackPresets = [
    0 => [
        'title' => 'SSC CGL Tier 1 2026: Official Admit Card Download Link Live',
        'slug' => 'ssc-cgl-admit-card-2026',
        'board_key' => 'ssc',
        'category_name' => 'Admit Cards',
        'published_at' => 'now'
    ],
    1 => [
        'title' => 'RRB NTPC 2026: Apply Online for 11,558 Vacancies, Check Syllabus',
        'slug' => 'rrb-ntpc-recruitment-2026',
        'board_key' => 'railways',
        'category_name' => 'Government Jobs',
        'published_at' => 'now'
    ],
    2 => [
        'title' => 'UPSC NDA 2 2026 Admit Card Released: Download Exam City Slip',
        'slug' => 'upsc-nda-admit-card-2026',
        'board_key' => 'upsc',
        'category_name' => 'Admit Cards',
        'published_at' => 'now'
    ],
    3 => [
        'title' => 'UP Police Constable Re-Exam 2026: Official Answer Key & OMR Sheet',
        'slug' => 'up-police-constable-answer-key-2026',
        'board_key' => 'uppolice',
        'category_name' => 'Exam Results',
        'published_at' => 'now'
    ],
    4 => [
        'title' => 'AICTE Doctoral Fellowship 2026: Eligibility, Stipend & Application',
        'slug' => 'aicte-doctoral-fellowship-2026',
        'board_key' => 'aicte',
        'category_name' => 'Scholarships',
        'published_at' => 'now'
    ],
    5 => [
        'title' => 'NEET UG Round 2 Counselling 2026: Registration Link & Seat Matrix',
        'slug' => 'neet-ug-counselling-2026',
        'board_key' => 'nta',
        'category_name' => 'Exam Results',
        'published_at' => 'now'
    ]
];

// Ensure we have at least 6 articles
$displayArticles = [];
for ($i = 0; $i < 6; $i++) {
    $displayArticles[$i] = $hotArticles[$i] ?? $fallbackPresets[$i];
}

// Helper: Automatically detect board profile and logo from article metadata
function resolve_board_profile(array $article): array {
    $text = strtolower(($article['title'] ?? '') . ' ' . ($article['category_name'] ?? '') . ' ' . ($article['source_name'] ?? '') . ' ' . ($article['slug'] ?? ''));

    // 1. SSC
    if (preg_match('/\b(ssc|cgl|chsl|mts|ssc je|cpo|gd constable|stenographer)\b/i', $text)) {
        return [
            'board_key' => 'ssc',
            'board_title' => 'SSC',
            'board_name' => 'Staff Selection Commission',
            'logo' => 'ssc.png',
            'color' => '#DC2626',
            'bg_light' => '#FEF2F2',
            'border' => '#FECACA',
            'watermark' => 'parliament'
        ];
    }

    // 2. IBPS / SBI / Banking
    if (preg_match('/\b(ibps|sbi|bank|banking|clerk|po|rbi|nabard|specialist officer)\b/i', $text)) {
        $isSBI = strpos($text, 'sbi') !== false;
        return [
            'board_key' => 'ibps',
            'board_title' => $isSBI ? 'SBI Banking' : 'IBPS',
            'board_name' => $isSBI ? 'State Bank of India' : 'Institute of Banking Personnel Selection',
            'logo' => 'ibps.png',
            'color' => '#0284C7',
            'bg_light' => '#F0F9FF',
            'border' => '#BAE6FD',
            'watermark' => 'bank'
        ];
    }

    // 3. Indian Railways
    if (preg_match('/\b(railway|railways|rrb|ntpc|alp|loco pilot|rrc|group d|irctc)\b/i', $text)) {
        return [
            'board_key' => 'railways',
            'board_title' => 'Indian Railways',
            'board_name' => 'Railway Recruitment Board',
            'logo' => 'railways.png',
            'color' => '#16A34A',
            'bg_light' => '#F0FDF4',
            'border' => '#BBF7D0',
            'watermark' => 'train'
        ];
    }

    // 4. UPSC
    if (preg_match('/\b(upsc|civil services|nda|cds|ias|ips|ifs|capf|epfo|geoscientist)\b/i', $text)) {
        return [
            'board_key' => 'upsc',
            'board_title' => 'UPSC',
            'board_name' => 'Union Public Service Commission',
            'logo' => 'upsc.png',
            'color' => '#2563EB',
            'bg_light' => '#EFF6FF',
            'border' => '#BFDBFE',
            'watermark' => 'rashtrapati'
        ];
    }

    // 5. Police Recruitment
    if (preg_match('/\b(police|constable|sub inspector|uppbpb|daroga|cisf|crpf|bsf|itbp)\b/i', $text)) {
        $isUP = strpos($text, 'up ') !== false || strpos($text, 'uttar pradesh') !== false;
        return [
            'board_key' => 'uppolice',
            'board_title' => $isUP ? 'UP Police' : 'Police Recruitment',
            'board_name' => $isUP ? 'Uttar Pradesh Police' : 'Police Recruitment Board',
            'logo' => 'uppolice.png',
            'color' => '#EA580C',
            'bg_light' => '#FFF7ED',
            'border' => '#FED7AA',
            'watermark' => 'police'
        ];
    }

    // 6. NTA / NEET / JEE / CUET
    if (preg_match('/\b(nta|neet|jee|cuet|ugc net|counselling|mbbs|iit|medical|seat allotment)\b/i', $text)) {
        return [
            'board_key' => 'nta',
            'board_title' => 'NTA',
            'board_name' => 'National Testing Agency',
            'logo' => 'nta.png',
            'color' => '#0D9488',
            'bg_light' => '#F0FDFA',
            'border' => '#99F6E4',
            'watermark' => 'medical'
        ];
    }

    // 7. AICTE / Higher Technical
    if (preg_match('/\b(aicte|technical education|doctoral fellowship|phd|fellowship|scholarship)\b/i', $text)) {
        return [
            'board_key' => 'aicte',
            'board_title' => 'AICTE',
            'board_name' => 'All India Council for Technical Education',
            'logo' => 'aicte.png',
            'color' => '#7C3AED',
            'bg_light' => '#FAF5FF',
            'border' => '#E9D5FF',
            'watermark' => 'aicte'
        ];
    }

    // 8. CBSE / School Boards
    if (preg_match('/\b(cbse|class 10|class 12|board exam|board result|term 1|term 2|ctet)\b/i', $text)) {
        return [
            'board_key' => 'cbse',
            'board_title' => 'CBSE',
            'board_name' => 'Central Board of Secondary Education',
            'logo' => 'cbse.png',
            'color' => '#059669',
            'bg_light' => '#F0FDF4',
            'border' => '#A7F3D0',
            'watermark' => 'cbse'
        ];
    }

    // 9. BPSC / State PSC
    if (preg_match('/\b(bpsc|bihar|tre|bssc)\b/i', $text)) {
        return [
            'board_key' => 'bpsc',
            'board_title' => 'BPSC',
            'board_name' => 'Bihar Public Service Commission',
            'logo' => 'default.png',
            'color' => '#0F766E',
            'bg_light' => '#F0FDFA',
            'border' => '#99F6E4',
            'watermark' => 'parliament'
        ];
    }

    // 10. Default Government Authority
    return [
        'board_key' => 'gov',
        'board_title' => 'Govt Authority',
        'board_name' => 'Official Statutory Commission',
        'logo' => 'default.png',
        'color' => '#1E3A8A',
        'bg_light' => '#EFF6FF',
        'border' => '#BFDBFE',
        'watermark' => 'parliament'
    ];
}

// Helper: Detect candidate milestone action
function resolve_article_milestone(string $title): array {
    $t = strtolower($title);
    if (strpos($t, 'admit card') !== false || strpos($t, 'hall ticket') !== false || strpos($t, 'city intimation') !== false) {
        return [
            'status_text' => 'Admit Card Live',
            'status_icon' => 'file-text',
            'btn1_text'   => 'Download Slip',
            'btn1_icon'   => 'download',
            'badge'       => '★ Live',
            'status_bg'   => '#DCFCE7',
            'status_color'=> '#166534',
            'badge_bg'    => '#DC2626',
            'date1_label' => 'Exam Date',
            'date1_val'   => 'Sep 2026',
            'date2_label' => 'Download',
            'date2_val'   => 'Active'
        ];
    }
    if (strpos($t, 'result') !== false || strpos($t, 'scorecard') !== false || strpos($t, 'cutoff') !== false || strpos($t, 'merit') !== false) {
        return [
            'status_text' => 'Result Declared',
            'status_icon' => 'bar-chart',
            'btn1_text'   => 'Check Result',
            'btn1_icon'   => 'external-link',
            'badge'       => '★ Hot',
            'status_bg'   => '#DBEAFE',
            'status_color'=> '#1E40AF',
            'badge_bg'    => '#2563EB',
            'date1_label' => 'Result Date',
            'date1_val'   => 'Declared',
            'date2_label' => 'Scorecard',
            'date2_val'   => 'Available'
        ];
    }
    if (strpos($t, 'answer key') !== false || strpos($t, 'response sheet') !== false || strpos($t, 'omr') !== false) {
        return [
            'status_text' => 'Answer Key Out',
            'status_icon' => 'key',
            'btn1_text'   => 'Check Key',
            'btn1_icon'   => 'key',
            'badge'       => '★ Updated',
            'status_bg'   => '#FFEDD5',
            'status_color'=> '#9A3412',
            'badge_bg'    => '#EA580C',
            'date1_label' => 'Key Status',
            'date1_val'   => 'Released',
            'date2_label' => 'Objections',
            'date2_val'   => 'Open'
        ];
    }
    if (strpos($t, 'apply') !== false || strpos($t, 'recruitment') !== false || strpos($t, 'vacancy') !== false || strpos($t, 'registration') !== false || strpos($t, 'form') !== false) {
        return [
            'status_text' => 'Online Form Live',
            'status_icon' => 'briefcase',
            'btn1_text'   => 'Apply Online',
            'btn1_icon'   => 'external-link',
            'badge'       => '★ Popular',
            'status_bg'   => '#DCFCE7',
            'status_color'=> '#166534',
            'badge_bg'    => '#16A34A',
            'date1_label' => 'Last Date',
            'date1_val'   => 'Sep / Oct 2026',
            'date2_label' => 'Apply Mode',
            'date2_val'   => 'Online'
        ];
    }
    if (strpos($t, 'counselling') !== false || strpos($t, 'allotment') !== false || strpos($t, 'seat') !== false) {
        return [
            'status_text' => 'Counselling Active',
            'status_icon' => 'user',
            'btn1_text'   => 'Check Status',
            'btn1_icon'   => 'refresh-cw',
            'badge'       => '★ Live',
            'status_bg'   => '#CFFAFE',
            'status_color'=> '#0E7490',
            'badge_bg'    => '#0D9488',
            'date1_label' => 'Round',
            'date1_val'   => 'Round 1 & 2',
            'date2_label' => 'Allotment',
            'date2_val'   => 'Sep 2026'
        ];
    }
    return [
        'status_text' => 'Official Notice',
        'status_icon' => 'file-text',
        'btn1_text'   => 'View Notice',
        'btn1_icon'   => 'file-text',
        'badge'       => 'New',
        'status_bg'   => '#F1F5F9',
        'status_color'=> '#334155',
        'badge_bg'    => '#64748B',
        'date1_label' => 'Notice Date',
        'date1_val'   => 'Sep 2026',
        'date2_label' => 'Status',
        'date2_val'   => 'Verified'
    ];
}

// Background Architectural Watermark Helper
function render_split_watermark(string $type, string $color): string {
    switch ($type) {
        case 'train':
            return '<svg viewBox="0 0 160 120" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M15 85L35 30C40 22 52 18 70 18H130C145 18 155 28 155 42V85H15ZM45 35L33 70H80V35H45ZM90 35V70H140V45C140 38 135 35 128 35H90ZM42 78C45 78 48 75 48 72C48 69 45 66 42 66C39 66 36 69 36 72C36 75 39 78 42 78ZM5 98H155V106H5V98ZM0 112H160V118H0V112Z"/></svg>';
        case 'rashtrapati':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 10C78 10 76 12 76 15V25C65 26 55 35 55 48C55 58 62 67 72 70V125H30V135H130V125H88V70C98 67 105 58 105 48C105 35 95 26 84 25V15C84 12 82 10 80 10ZM65 48C65 39 72 32 80 32C88 32 95 39 95 48H65ZM75 80H85V120H75V80Z"/></svg>';
        case 'police':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 10L30 30V75C30 110 52 135 80 140C108 135 130 110 130 75V30L80 10ZM80 25L115 39V75C115 102 98 123 80 128C62 123 45 102 45 75V39L80 25ZM80 45L86 62H104L90 73L95 90L80 79L65 90L70 73L56 62H74L80 45Z"/></svg>';
        case 'medical':
            return '<svg viewBox="0 0 140 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M40 20C40 16 43 13 47 13H53V21H47V45C47 57 57 67 70 67C83 67 93 57 93 45V21H87V13H93C97 13 100 16 100 20V45C100 61 87 74 71 75V95C71 103 64 110 56 110H52C44 110 38 116 38 124C38 132 44 138 52 138H60C68 138 74 132 74 124V75C75 75 76 75 77 75C103 75 124 54 124 28V20H116V28C116 49 99 67 77 67V45C77 41 74 38 70 38C66 38 63 41 63 45V67C41 67 24 49 24 28V20H16V28C16 54 37 75 63 75V124C63 126 62 128 60 128H52C50 128 48 126 48 124C48 122 50 120 52 120H56C60 120 63 117 63 113V75C47 74 34 61 34 45V20H40Z"/></svg>';
        case 'bank':
            return '<svg viewBox="0 0 160 120" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 12L15 42V52H145V42L80 12ZM28 60V105H42V60H28ZM58 60V105H72V60H58ZM88 60V105H102V60H88ZM118 60V105H132V60H118ZM12 110V120H148V110H12Z"/></svg>';
        case 'cbse':
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 45C68 32 48 30 20 30V105C48 105 68 107 80 122C92 107 112 105 140 105V30C112 30 92 32 80 45ZM74 102C63 92 48 88 34 88V42C48 42 63 45 74 55V102ZM126 88C112 88 97 92 86 102V55C97 45 112 42 126 42V88ZM80 12L76 24H84L80 12ZM50 20L44 30L52 33L50 20ZM110 20L108 33L116 30L110 20Z"/></svg>';
        case 'aicte':
            return '<svg viewBox="0 0 140 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M70 45C56.2 45 45 56.2 45 70C45 83.8 56.2 95 70 95C83.8 95 95 83.8 95 70C95 56.2 83.8 45 70 45ZM70 58C76.6 58 82 63.4 82 70C82 76.6 76.6 82 70 82C63.4 82 58 76.6 58 70C58 63.4 63.4 58 70 58ZM125 62V51L109 48C108 44 106 40 104 37L113 24L103 14L90 23C87 21 83 19 79 18L76 2H64L61 18C57 19 53 21 50 23L37 14L27 24L36 37C34 40 32 44 31 48L15 51V62L31 65C32 69 34 73 36 76L27 89L37 99L50 90C53 92 57 94 61 95L64 111H76L79 95C83 94 87 92 90 90L103 99L113 89L104 76C106 73 108 69 109 65L125 62Z"/></svg>';
        case 'parliament':
        default:
            return '<svg viewBox="0 0 160 140" fill="' . $color . '" xmlns="http://www.w3.org/2000/svg"><path d="M80 15C77 15 75 17 75 20V28H68C66 28 65 30 65 32V45H40C38 45 36 47 36 49V130H124V49C124 47 122 45 120 45H95V32C95 30 94 28 92 28H85V20C85 17 83 15 80 15ZM70 52C70 42 75 35 80 35C85 35 90 42 90 52V130H70V52ZM44 55H62V130H44V55ZM98 55H116V130H98V55ZM20 130H140V140H20V130Z"/></svg>';
    }
}
?>

<section class="editorial-split-section" aria-label="Official Commission Highlights and Notifications">
    <div class="editorial-split-grid">
        
        <!-- ========================================================
             LEFT COLUMN: 2 PROMINENT LEAD CARDS (Top 2 Articles)
             ======================================================== -->
        <div class="split-col-lead">
            <?php 
            for ($i = 0; $i < 2; $i++): 
                $item = $displayArticles[$i];
                $board = resolve_board_profile($item);
                $milestone = resolve_article_milestone($item['title'] ?? '');
                $articleTitle = $item['title'] ?? 'Official Government Examination Update';
                $articleSlug = $item['slug'] ?? 'latest-updates';
                $numStr = sprintf('%02d', $i + 1);
                $watermarkSvg = render_split_watermark($board['watermark'], $board['color']);
            ?>
                <article class="lead-card">
                    <!-- Subtle Architectural Watermark Accent (Top-Right) -->
                    <div class="lead-watermark" aria-hidden="true">
                        <?= $watermarkSvg ?>
                    </div>

                    <div class="lead-card-top">
                        <!-- Top Bar: Number Badge + Status Badge -->
                        <div class="lead-topbar">
                            <span class="lead-num-pill" style="background-color: <?= $board['bg_light'] ?>; color: <?= $board['color'] ?>; border: 1px solid <?= $board['border'] ?>;">
                                <?= $numStr ?>
                            </span>
                            <span class="lead-badge-status" style="background-color: <?= $milestone['badge_bg'] ?>;">
                                <?= $milestone['badge'] ?>
                            </span>
                        </div>

                        <!-- Board Header: Real Official Logo Image + Short & Full Title -->
                        <div class="lead-board-header">
                            <div class="lead-board-logo">
                                <img src="<?= url('assets/images/boards/' . $board['logo']) ?>" 
                                     alt="<?= e($board['board_title']) ?>" 
                                     width="44" 
                                     height="44" 
                                     loading="lazy">
                            </div>
                            <div class="lead-board-info">
                                <h4 class="lead-board-title"><?= e($board['board_title']) ?></h4>
                                <span class="lead-board-sub" title="<?= e($board['board_name']) ?>"><?= e($board['board_name']) ?></span>
                            </div>
                        </div>

                        <!-- Prominent Notice Title -->
                        <h3 class="lead-notice-title">
                            <a href="<?= url('article/' . $articleSlug . '/') ?>" title="<?= e($articleTitle) ?>">
                                <?= e(truncate_text($articleTitle, 68)) ?>
                            </a>
                        </h3>

                        <!-- Status Pill -->
                        <div class="lead-status-pill" style="background-color: <?= $milestone['status_bg'] ?>; color: <?= $milestone['status_color'] ?>;">
                            <?= icon($milestone['status_icon'], 'lead-status-icon') ?>
                            <span><?= e($milestone['status_text']) ?></span>
                        </div>

                        <!-- 2-Column Key Dates Strip -->
                        <div class="lead-dates-grid">
                            <div class="lead-date-item">
                                <?= icon('calendar', 'lead-date-icon') ?>
                                <div class="lead-date-text">
                                    <span class="lead-date-label"><?= e($milestone['date1_label']) ?></span>
                                    <span class="lead-date-val"><?= e($milestone['date1_val']) ?></span>
                                </div>
                            </div>
                            <div class="lead-date-item">
                                <?= icon('clock', 'lead-date-icon') ?>
                                <div class="lead-date-text">
                                    <span class="lead-date-label"><?= e($milestone['date2_label']) ?></span>
                                    <span class="lead-date-val"><?= e($milestone['date2_val']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dual Action Buttons Row -->
                    <div class="lead-actions-row">
                        <a href="<?= url('article/' . $articleSlug . '/') ?>" class="lead-btn-outline" style="color: <?= $board['color'] ?>; background-color: <?= $board['bg_light'] ?>; border-color: <?= $board['border'] ?>;">
                            <?= icon($milestone['btn1_icon'], 'lead-btn-icon') ?>
                            <span><?= e($milestone['btn1_text']) ?></span>
                        </a>
                        <a href="<?= url('article/' . $articleSlug . '/') ?>" class="lead-btn-solid" style="background-color: <?= $board['color'] ?>;">
                            <span>View Details</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endfor; ?>
        </div>

        <!-- ========================================================
             RIGHT COLUMN: 4 COMPACT NOTIFICATION CARDS (Articles 3 to 6)
             ======================================================== -->
        <div class="split-col-feed">
            <?php 
            for ($i = 2; $i < 6; $i++): 
                $item = $displayArticles[$i];
                $board = resolve_board_profile($item);
                $milestone = resolve_article_milestone($item['title'] ?? '');
                $articleTitle = $item['title'] ?? 'Official Recruitment Notification';
                $articleSlug = $item['slug'] ?? 'latest-updates';
                $numStr = sprintf('%02d', $i + 1);
            ?>
                <article class="feed-compact-card">
                    <!-- Board Logo -->
                    <div class="feed-logo-box">
                        <img src="<?= url('assets/images/boards/' . $board['logo']) ?>" 
                             alt="<?= e($board['board_title']) ?>" 
                             width="38" 
                             height="38" 
                             loading="lazy">
                    </div>

                    <!-- Content Details -->
                    <div class="feed-card-body">
                        <div class="feed-meta-row">
                            <span class="feed-num-pill" style="color: <?= $board['color'] ?>; background: <?= $board['bg_light'] ?>;">
                                <?= $numStr ?>
                            </span>
                            <span class="feed-board-name"><?= e($board['board_title']) ?></span>
                            <span class="feed-status-tag" style="background: <?= $milestone['status_bg'] ?>; color: <?= $milestone['status_color'] ?>;">
                                <?= e($milestone['status_text']) ?>
                            </span>
                        </div>

                        <h4 class="feed-card-title">
                            <a href="<?= url('article/' . $articleSlug . '/') ?>" title="<?= e($articleTitle) ?>">
                                <?= e(truncate_text($articleTitle, 58)) ?>
                            </a>
                        </h4>

                        <div class="feed-footer-row">
                            <span class="feed-cat-label"><?= e($item['category_name'] ?? 'Notice') ?></span>
                            <span class="feed-action-link" style="color: <?= $board['color'] ?>;">
                                <?= e($milestone['btn1_text']) ?> →
                            </span>
                        </div>
                    </div>
                </article>
            <?php endfor; ?>
        </div>

    </div>
</section>
