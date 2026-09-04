<?php
/**
 * Sarkari.online - 8-Card Official Examination & Recruitment Grid
 * Exact pixel-perfect implementation of the ChatGPT high-craft design mockup.
 * Features:
 * - Official logos (SSC, Indian Railways, UPSC, UP Police, AICTE, NTA, IBPS, CBSE)
 * - Faint architectural watermark illustrations in the background
 * - Top number pill (01-08) + status badge (★ Trending, ★ Popular, ★ Hot, etc.)
 * - Board title, full official name, and motto tagline
 * - Notice title & subtitle
 * - Dedicated status pill
 * - 2-column key dates strip
 * - Dual action buttons (Outline action + Solid primary "View Details →")
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

// Preset metadata mapping matching the 8 cards
$cardPresets = [
    0 => [
        'num' => '01',
        'badge' => '★ Trending',
        'badge_bg' => '#DC2626',
        'num_bg' => '#FEE2E2',
        'num_color' => '#DC2626',
        'board_title' => 'SSC',
        'board_name' => 'Staff Selection Commission',
        'board_motto' => 'People • Progress • Opportunities',
        'default_title' => 'SSC CGL Tier 1 2026',
        'default_sub' => 'Admit Card Released for Tier 1 Exam',
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
        'logo' => 'ssc'
    ],
    1 => [
        'num' => '02',
        'badge' => '★ Popular',
        'badge_bg' => '#16A34A',
        'num_bg' => '#DCFCE7',
        'num_color' => '#16A34A',
        'board_title' => 'Indian Railways',
        'board_name' => 'Railway Recruitment Board',
        'board_motto' => 'Nation on Track',
        'default_title' => 'RRB NTPC 2026',
        'default_sub' => 'Apply Online for 11,558 Posts',
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
        'logo' => 'railway'
    ],
    2 => [
        'num' => '03',
        'badge' => '★ Hot',
        'badge_bg' => '#2563EB',
        'num_bg' => '#DBEAFE',
        'num_color' => '#2563EB',
        'board_title' => 'UPSC',
        'board_name' => 'Union Public Service Commission',
        'board_motto' => 'Excellence in Service',
        'default_title' => 'Civil Services Exam 2026',
        'default_sub' => 'Final Result Declared',
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
        'logo' => 'upsc'
    ],
    3 => [
        'num' => '04',
        'badge' => '★ Updated',
        'badge_bg' => '#EA580C',
        'num_bg' => '#FEF3C7',
        'num_color' => '#D97706',
        'board_title' => 'UP Police',
        'board_name' => 'Uttar Pradesh Police',
        'board_motto' => 'Safety • Security • Service',
        'default_title' => 'Constable Re-Exam 2026',
        'default_sub' => 'Official Answer Key Released',
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
        'logo' => 'uppolice'
    ],
    4 => [
        'num' => '05',
        'badge' => 'New',
        'badge_bg' => '#7C3AED',
        'num_bg' => '#F3E8FF',
        'num_color' => '#9333EA',
        'board_title' => 'AICTE',
        'board_name' => 'All India Council for Technical Education',
        'board_motto' => 'Education for a Better Tomorrow',
        'default_title' => 'AICTE Doctoral Fellowship 2026',
        'default_sub' => 'Eligibility, Stipend & Application Steps',
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
        'logo' => 'aicte'
    ],
    5 => [
        'num' => '06',
        'badge' => '★ Live',
        'badge_bg' => '#059669',
        'num_bg' => '#CFFAFE',
        'num_color' => '#0891B2',
        'board_title' => 'NTA',
        'board_name' => 'National Testing Agency',
        'board_motto' => 'Fair Exams, Bright Futures',
        'default_title' => 'NEET UG Round 2 Seat 2026',
        'default_sub' => 'Counselling Registration Link',
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
        'logo' => 'nta'
    ],
    6 => [
        'num' => '07',
        'badge' => '★ Popular',
        'badge_bg' => '#2563EB',
        'num_bg' => '#DBEAFE',
        'num_color' => '#2563EB',
        'board_title' => 'IBPS',
        'board_name' => 'Institute of Banking Personnel Selection',
        'board_motto' => 'People for Progress',
        'default_title' => 'SBI PO 2026',
        'default_sub' => '3,955 Vacancy Notice Out',
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
        'logo' => 'ibps'
    ],
    7 => [
        'num' => '08',
        'badge' => '★ Updated',
        'badge_bg' => '#E11D48',
        'num_bg' => '#FFE4E6',
        'num_color' => '#E11D48',
        'board_title' => 'CBSE',
        'board_name' => 'Central Board of Secondary Education',
        'board_motto' => 'Education for All',
        'default_title' => 'Class 10/12 Result 2026',
        'default_sub' => 'Scorecard Link Available',
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
        'logo' => 'cbse'
    ]
];

// Helper: Render Official Board Emblem SVG
function render_official_board_logo(string $type): string {
    switch ($type) {
        case 'ssc':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="23" fill="#8B0000" stroke="#D4AF37" stroke-width="2"/><circle cx="24" cy="24" r="19.5" fill="#7A0000" stroke="#FFD700" stroke-dasharray="2 2"/><path d="M24 8C22 8 20.5 9.5 20.5 11.5C20.5 12.5 21 13.2 21.8 13.6C21 14.5 19.5 16 19.5 18C19.5 19.8 21 21 22.5 21.5V23.5H18V25H30V23.5H25.5V21.5C27 21 28.5 19.8 28.5 18C28.5 16 27 14.5 26.2 13.6C27 13.2 27.5 12.5 27.5 11.5C27.5 9.5 26 8 24 8ZM15 28C15 33 19 37 24 37C29 37 33 33 33 28H30C30 31.3 27.3 34 24 34C20.7 34 18 31.3 18 28H15Z" fill="#FFD700"/><rect x="18" y="25.5" width="12" height="1.8" rx="0.5" fill="#FFFFFF"/><text x="24" y="31.5" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-size="3.5" font-weight="900" letter-spacing="0.5">SSC</text></svg>';

        case 'railway':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="23" fill="#C51D24" stroke="#FFFFFF" stroke-width="1.5"/><circle cx="24" cy="24" r="19" stroke="#FFFFFF" stroke-width="1" stroke-dasharray="2 1.5"/><circle cx="24" cy="24" r="14" fill="#A8151B" stroke="#FFFFFF" stroke-width="1"/><path d="M17 17C17 14.5 19 13 24 13C29 13 31 14.5 31 17V26C31 28 29.5 29 28 29H20C18.5 29 17 28 17 26V17ZM19 16V20H29V16H19ZM20.5 25C21.3 25 22 24.3 22 23.5C22 22.7 21.3 22 20.5 22C19.7 22 19 22.7 19 23.5C19 24.3 19.7 25 20.5 25ZM27.5 25C28.3 25 29 24.3 29 23.5C29 22.7 28.3 22 27.5 22C26.7 22 26 22.7 26 23.5C26 24.3 26.7 25 27.5 25ZM16 32L14 35H17L18 33H30L31 35H34L32 32H16Z" fill="#FFFFFF"/></svg>';

        case 'upsc':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" rx="8" fill="#F8FAFC"/><path d="M24 5C21.5 5 19.8 6.8 19.8 9C19.8 10.2 20.4 11.2 21.2 11.8C20 13 18.5 15 18.5 18C18.5 20.5 20.2 22 22 22.5V24H16V26H32V24H26V22.5C27.8 22 29.5 20.5 29.5 18C29.5 15 28 13 26.8 11.8C27.6 11.2 28.2 10.2 28.2 9C28.2 6.8 26.5 5 24 5ZM14 12C12.8 12 11.8 13 11.8 14.5C11.8 16 13 17.5 14.5 18V21H16V17C15.2 16.5 14.5 15.5 14.5 14.5C14.5 13.5 15.2 12.8 16 12.8C15.5 12.3 14.8 12 14 12ZM34 12C33.2 12 32.5 12.3 32 12.8C32.8 12.8 33.5 13.5 33.5 14.5C33.5 15.5 32.8 16.5 32 17V21H33.5V18C35 17.5 36.2 16 36.2 14.5C36.2 13 35.2 12 34 12ZM15 28C15 28.5 15.5 29 16 29H32C32.5 29 33 28.5 33 28V27H15V28ZM19 33C19 35.8 21.2 38 24 38C26.8 38 29 35.8 29 33H19ZM14 41H34V43H14V41Z" fill="#1E3A8A"/><circle cx="24" cy="33" r="3.5" stroke="#1E3A8A" stroke-width="1"/><line x1="24" y1="30" x2="24" y2="36" stroke="#1E3A8A" stroke-width="0.8"/><line x1="21" y1="33" x2="27" y2="33" stroke="#1E3A8A" stroke-width="0.8"/></svg>';

        case 'uppolice':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" rx="8" fill="#FFFBEB"/><path d="M24 4L22 7H26L24 4ZM20 8H28V10H20V8Z" fill="#D97706"/><path d="M14 12H24V34C24 37 14 35 14 30V12Z" fill="#B91C1C"/><path d="M24 12H34V30C34 35 24 37 24 34V12Z" fill="#1E3A8A"/><path d="M18 20C18 24 21 26 24 26C27 26 30 24 30 20C30 17 28 15 24 15C20 15 18 17 18 20Z" fill="#F59E0B"/><path d="M12 36C12 36 17 40 24 40C31 40 36 36 36 36L34 38C34 38 29 42 24 42C19 42 14 38 14 38L12 36Z" fill="#D97706"/><text x="24" y="22" text-anchor="middle" fill="#78350F" font-family="sans-serif" font-size="4" font-weight="900">UPP</text></svg>';

        case 'aicte':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="23" fill="#F59E0B" stroke="#D97706" stroke-width="1.5"/><circle cx="24" cy="24" r="18" fill="#FBBF24"/><circle cx="24" cy="24" r="14" fill="#1E3A8A"/><path d="M24 14C23 17 21 19 21 22C21 24.5 22.5 26 24 26C25.5 26 27 24.5 27 22C27 19 25 17 24 14Z" fill="#EF4444"/><circle cx="24" cy="23" r="1.5" fill="#FDE047"/><text x="24" y="32" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-size="3.5" font-weight="900" letter-spacing="0.5">AICTE</text></svg>';

        case 'nta':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="23" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.5"/><path d="M12 24C12 17.37 17.37 12 24 12C30.63 12 36 17.37 36 24H30C30 20.69 27.31 18 24 18C20.69 18 18 20.69 18 24H12Z" fill="#F97316"/><path d="M12 26C12 32.63 17.37 38 24 38C28.5 38 32.4 35.5 34.5 31.8L29.5 29C28.2 31 26.2 32 24 32C20.69 32 18 29.31 18 26H12Z" fill="#16A34A"/><circle cx="24" cy="25" r="3.5" fill="#1E40AF"/></svg>';

        case 'ibps':
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" rx="8" fill="#F0F9FF"/><path d="M13 12H21C24.5 12 27 14 27 17C27 19 25.5 20.5 23.5 21C26 21.5 28 23.5 28 26.5C28 30 25 32 21 32H13V12ZM18 19H20.5C21.8 19 22.5 18.2 22.5 17C22.5 15.8 21.8 15 20.5 15H18V19ZM18 29H21C22.5 29 23.5 28 23.5 26.5C23.5 25 22.5 24 21 24H18V29Z" fill="#0284C7"/><path d="M30 12H35V32H30V12Z" fill="#0369A1"/><text x="24" y="41" text-anchor="middle" fill="#0369A1" font-family="sans-serif" font-size="5" font-weight="900">IBPS</text></svg>';

        case 'cbse':
        default:
            return '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="23" fill="#047857" stroke="#F59E0B" stroke-width="1.5"/><circle cx="24" cy="24" r="19" fill="#065F46" stroke="#FFFFFF" stroke-dasharray="1.5 1.5"/><path d="M16 19C16 19 20 20 24 21C28 20 32 19 32 19V30C32 30 28 31 24 32C20 31 16 30 16 30V19Z" fill="#FFFFFF"/><path d="M24 15V20" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/><path d="M24 12C23 13.5 23 14.5 24 15.5C25 14.5 25 13.5 24 12Z" fill="#F59E0B"/><text x="24" y="37" text-anchor="middle" fill="#FDE68A" font-family="sans-serif" font-size="3.8" font-weight="900" letter-spacing="0.5">CBSE</text></svg>';
    }
}

// Helper: Render Background Architectural Watermark
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
            $item = $hotArticles[$i] ?? null;

            // Notice Title & Subtitle (Use database article if present, or pristine preset)
            $articleTitle = $item['title'] ?? $preset['default_title'];
            $articleSlug = $item['slug'] ?? 'latest-updates';
            $articleSub = !empty($item['excerpt']) ? truncate_text($item['excerpt'], 48) : $preset['default_sub'];

            // Watermark SVG
            $watermarkSvg = render_background_watermark($preset['watermark'], $preset['watermark_color']);
            $logoSvg = render_official_board_logo($preset['logo']);
        ?>
            <article class="cg-card">
                <!-- Background Architectural Watermark -->
                <div class="cg-card-watermark" aria-hidden="true">
                    <?= $watermarkSvg ?>
                </div>

                <div>
                    <!-- Top Row: Number Pill + Status Badge -->
                    <div class="cg-card-topbar">
                        <span class="cg-num-badge" style="background-color: <?= $preset['num_bg'] ?>; color: <?= $preset['num_color'] ?>;">
                            <?= $preset['num'] ?>
                        </span>
                        <span class="cg-badge-status" style="background-color: <?= $preset['badge_bg'] ?>;">
                            <?= $preset['badge'] ?>
                        </span>
                    </div>

                    <!-- Board Header: Official Logo + Title + Motto -->
                    <div class="cg-board-header">
                        <div class="cg-board-logo">
                            <?= $logoSvg ?>
                        </div>
                        <div class="cg-board-info">
                            <h4 class="cg-board-title"><?= e($preset['board_title']) ?></h4>
                            <span class="cg-board-sub" title="<?= e($preset['board_name']) ?>"><?= e($preset['board_name']) ?></span>
                            <span class="cg-board-motto"><?= e($preset['board_motto']) ?></span>
                        </div>
                    </div>

                    <!-- Notice Title & Subtitle -->
                    <h3 class="cg-notice-title">
                        <a href="<?= url('article/' . $articleSlug . '/') ?>" title="<?= e($articleTitle) ?>">
                            <?= e(truncate_text($articleTitle, 46)) ?>
                        </a>
                    </h3>
                    <p class="cg-notice-sub"><?= e($articleSub) ?></p>

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
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </article>
        <?php endfor; ?>
    </div>
</section>

