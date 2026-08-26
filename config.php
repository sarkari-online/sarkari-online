<?php
/**
 * EduPulse - Master Configuration & Bootstrap (Phase 1)
 * Environment loader, database connection, exception/error handling, and view helpers.
 */

// 1. PSR-4 Style Lightweight Class Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 2. Load Environment Variables (.env)
App\Helpers\Env::load(__DIR__ . '/.env');

// 3. Application Constants
define('APP_ENV', App\Helpers\Env::get('APP_ENV', 'development'));
define('APP_DEBUG', (bool)App\Helpers\Env::get('APP_DEBUG', true));
define('APP_VERSION', '1.3.7');

define('SITE_NAME', App\Helpers\Env::get('APP_NAME', 'Sarkari.online'));
define('SITE_TAGLINE', 'Independent Information Platform for Exams, Results & Jobs');
define('SITE_DESCRIPTION', 'Sarkari.online is an independent educational and recruitment information portal providing authentic alerts on exam results, admit cards, notifications, and scholarships across India. Not affiliated with any Government department.');

// 4. Base URL Auto-Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

// Base URL definition
define('SITE_URL', App\Helpers\Env::get('APP_URL', $protocol . $host . $basePath));
define('BASE_PATH', $basePath);

// 5. Global Error & Exception Handlers
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    App\Helpers\Logger::error("PHP Error: {$errstr}", ['file' => $errfile, 'line' => $errline, 'code' => $errno]);
    if (APP_DEBUG) {
        // In debug mode, throw ErrorException for full stack trace
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return true;
});

set_exception_handler(function (Throwable $e) {
    App\Helpers\Logger::critical("Uncaught Exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (file_exists(__DIR__ . '/500.php')) {
        $exception = $e;
        include __DIR__ . '/500.php';
    } else {
        echo "<h1>500 - Internal Server Error</h1>";
        if (APP_DEBUG) {
            echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong> in " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
    }
    exit(1);
});

// 6. Categories Configuration Map (National Repository Taxonomy)
const CATEGORIES = [
    'exam-results' => [
        'name' => 'Exam Results',
        'hindi_name' => 'परीक्षा परिणाम',
        'slug' => 'exam-results',
        'description' => 'Real-time scorecards, merit lists, rank cards, and pass percentage archives.',
        'color' => '#1d4ed8',
        'bg_light' => '#eff6ff',
        'icon' => 'award',
        'pillar' => 'exams',
        'statutory_tags' => ['NTA', 'CBSE', 'SSC', 'State Boards']
    ],
    'admit-cards' => [
        'name' => 'Admit Cards',
        'hindi_name' => 'प्रवेश पत्र / हॉल टिकट',
        'slug' => 'admit-cards',
        'description' => 'Official hall tickets, exam city intimation slips, and center guidelines.',
        'color' => '#0f766e',
        'bg_light' => '#f0fdfa',
        'icon' => 'id-card',
        'pillar' => 'exams',
        'statutory_tags' => ['UPSC', 'NTA', 'SSC', 'IBPS']
    ],
    'exam-dates' => [
        'name' => 'Exam Dates',
        'hindi_name' => 'परीक्षा तिथियां व शेड्यूल',
        'slug' => 'exam-dates',
        'description' => 'Official exam timetables, shift timings, notification releases, and calendar advisories.',
        'color' => '#4338ca',
        'bg_light' => '#eef2ff',
        'icon' => 'calendar',
        'pillar' => 'exams',
        'statutory_tags' => ['Shift Timings', 'Advisories', 'Schedules']
    ],
    'answer-keys' => [
        'name' => 'Answer Keys',
        'hindi_name' => 'उत्तर कुंजी / आंसर की',
        'slug' => 'answer-keys',
        'description' => 'Provisional & final keys, candidate response sheets, and objection submission portals.',
        'color' => '#7c3aed',
        'bg_light' => '#f5f3ff',
        'icon' => 'check-circle',
        'pillar' => 'exams',
        'statutory_tags' => ['Response Sheets', 'Objections', 'Final Keys']
    ],
    'entrance-exams' => [
        'name' => 'Entrance Exams',
        'hindi_name' => 'प्रवेश परीक्षाएं (JEE / NEET)',
        'slug' => 'entrance-exams',
        'description' => 'National and state entrance notifications for engineering, medical, law, and management.',
        'color' => '#b91c1c',
        'bg_light' => '#fef2f2',
        'icon' => 'compass',
        'pillar' => 'exams',
        'statutory_tags' => ['JEE Main', 'NEET UG', 'CUET', 'GATE']
    ],
    'government-jobs' => [
        'name' => 'Government Jobs',
        'hindi_name' => 'सरकारी नौकरियां व भर्ती',
        'slug' => 'government-jobs',
        'description' => 'Verified Central and State Government recruitment notifications, eligibility, and vacancies.',
        'color' => '#c2410c',
        'bg_light' => '#fff7ed',
        'icon' => 'briefcase',
        'pillar' => 'jobs',
        'statutory_tags' => ['UPSC', 'SSC CGL/CHSL', 'Railways', 'Defence']
    ],
    'career-guides' => [
        'name' => 'Career Guides',
        'hindi_name' => 'करियर गाइड व स्कोप',
        'slug' => 'career-guides',
        'description' => 'Actionable career roadmaps, salary insights, eligibility, and sector opportunities.',
        'color' => '#9a3412',
        'bg_light' => '#fff7ed',
        'icon' => 'trending-up',
        'pillar' => 'jobs',
        'statutory_tags' => ['Salary Insights', 'Govt vs Pvt', 'Roadmaps']
    ],
    'scholarships' => [
        'name' => 'Scholarships',
        'hindi_name' => 'छात्रवृत्ति व मेरिट योजनाएं',
        'slug' => 'scholarships',
        'description' => 'National scholarship portal schemes, state funding, and merit-cum-means assistance.',
        'color' => '#047857',
        'bg_light' => '#ecfdf5',
        'icon' => 'graduation-cap',
        'pillar' => 'higher_ed',
        'statutory_tags' => ['NSP Portal', 'State Schemes', 'Merit Assistance']
    ],
    'college-updates' => [
        'name' => 'College Updates',
        'hindi_name' => 'कॉलेज व यूनिवर्सिटी एडमिशन',
        'slug' => 'college-updates',
        'description' => 'University cutoffs, NIRF rankings, JoSAA/MCC seat matrices, and counselling rounds.',
        'color' => '#6d28d9',
        'bg_light' => '#f5f3ff',
        'icon' => 'building',
        'pillar' => 'higher_ed',
        'statutory_tags' => ['JoSAA', 'MCC Counselling', 'NIRF Cutoffs']
    ],
    'student-technology' => [
        'name' => 'Student Tech & AI',
        'hindi_name' => 'एआई व छात्र डिजिटल टूल्स',
        'slug' => 'student-technology',
        'description' => 'Curated AI study tools, productivity apps, learning software, and digital utilities.',
        'color' => '#334155',
        'bg_light' => '#f8fafc',
        'icon' => 'cpu',
        'pillar' => 'higher_ed',
        'statutory_tags' => ['AI Tools', 'Exam Calculators', 'Productivity']
    ]
];

// Navigation Menu
const NAV_LINKS = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Results', 'url' => 'category/exam-results/'],
    ['label' => 'Admit Cards', 'url' => 'category/admit-cards/'],
    ['label' => 'Exam Dates', 'url' => 'category/exam-dates/'],
    ['label' => 'Govt Jobs', 'url' => 'category/government-jobs/'],
    ['label' => 'Scholarships', 'url' => 'category/scholarships/'],
    ['label' => 'College Updates', 'url' => 'category/college-updates/'],
    ['label' => 'Entrance', 'url' => 'category/entrance-exams/'],
    ['label' => 'Career Guides', 'url' => 'category/career-guides/'],
    ['label' => 'Tech & AI', 'url' => 'category/student-technology/']
];

// ==========================================
// Essential Helpers
// ==========================================

/**
 * Escape HTML output safely
 */
function e(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate full URL
 */
function url(string $path = ''): string {
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return rtrim(SITE_URL, '/') . '/';
    }
    return rtrim(SITE_URL, '/') . '/' . $cleanPath;
}

/**
 * Generate asset URL with automatic version cache busting
 */
function asset(string $path): string {
    $filePath = __DIR__ . '/assets/' . ltrim($path, '/');
    $version = file_exists($filePath) ? filemtime($filePath) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

/**
 * Get Category info by slug
 */
function get_category(string $slug): ?array {
    return CATEGORIES[$slug] ?? null;
}

/**
 * Format date for Indian context
 */
function format_date(?string $datetime, bool $includeTime = false): string {
    if (empty($datetime)) return 'Recently';
    $time = strtotime($datetime);
    if (!$time) return e($datetime);
    if ($includeTime) {
        return date('d M Y, h:i A', $time) . ' IST';
    }
    return date('d M Y', $time);
}

/**
 * Time ago helper
 */
function time_ago(?string $datetime): string {
    if (empty($datetime)) return 'Recently';
    $time = strtotime($datetime);
    if (!$time) return (string)$datetime;
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Truncate text cleanly to words
 */
function truncate_text(?string $text, int $limit = 120): string {
    $clean = strip_tags($text ?? '');
    if (mb_strlen($clean) <= $limit) return $clean;
    $truncated = mb_substr($clean, 0, $limit);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return rtrim($truncated, '.,;') . '...';
}

/**
 * Render an SVG icon inline
 */
function icon(string $name, string $class = 'icon'): string {
    $icons = [
        'search' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
        'home' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'bell' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
        'menu' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>',
        'close' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        'clock' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        'calendar' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        'award' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
        'briefcase' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
        'check-circle' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        'chevron-right' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>',
        'chevron-left' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>',
        'arrow-right' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
        'trending-up' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
        'id-card' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><line x1="15" y1="8" x2="17" y2="8"></line><line x1="15" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="17" y2="16"></line></svg>',
        'compass' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>',
        'graduation-cap' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>',
        'building' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line><line x1="9" y1="6" x2="9" y2="6.01"></line><line x1="15" y1="6" x2="15" y2="6.01"></line><line x1="9" y1="10" x2="9" y2="10.01"></line><line x1="15" y1="10" x2="15" y2="10.01"></line><line x1="9" y1="14" x2="9" y2="14.01"></line><line x1="15" y1="14" x2="15" y2="14.01"></line><line x1="9" y1="18" x2="9" y2="18.01"></line><line x1="15" y1="18" x2="15" y2="18.01"></line></svg>',
        'book-open' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
        'cpu' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>',
        'shield-check' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',
        'bolt' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
        'share' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>',
        'external-link' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>',
        'file-text' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        'info' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
        'alert-triangle' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'user' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'trash' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',
        'edit' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
        'plus' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
        'logout' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        'layers' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>',
        'globe' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>'
    ];
    return $icons[$name] ?? '';
}

/**
 * Render a high-quality branded SVG thumbnail placeholder
 */
function render_thumbnail_svg(string $categorySlug, string $title = '', int $width = 800, int $height = 450): string {
    $cat = CATEGORIES[$categorySlug] ?? [
        'name' => 'Education Alert',
        'color' => '#1e3a8a',
        'bg_light' => '#eff6ff'
    ];

    $color = $cat['color'];
    $catName = htmlspecialchars(strtoupper($cat['name']), ENT_QUOTES, 'UTF-8');

    // Handle Compact Mode for small card thumbnails (width <= 360)
    if ($width <= 360) {
        $words = explode(' ', $title ?: 'Education Update');
        $shortTitle = htmlspecialchars(truncate_text($title, 38), ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $width $height" width="100%" height="100%" preserveAspectRatio="xMidYMid slice">
    <defs>
        <linearGradient id="cgrad_{$categorySlug}" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0f172a" />
            <stop offset="100%" stop-color="{$color}" stop-opacity="0.9" />
        </linearGradient>
    </defs>
    <rect width="$width" height="$height" fill="url(#cgrad_{$categorySlug})" />
    <rect x="0" y="0" width="6" height="$height" fill="{$color}" />
    
    <rect x="18" y="16" width="30" height="30" rx="4" fill="{$color}" />
    <text x="27" y="37" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="16" font-weight="900">S</text>
    
    <rect x="58" y="16" width="120" height="26" rx="4" fill="{$color}" fill-opacity="0.35" />
    <text x="68" y="34" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="10" font-weight="800" letter-spacing="0.5">$catName</text>

    <!-- Center Emblem Text -->
    <rect x="18" y="58" width="284" height="150" rx="6" fill="#0f172a" fill-opacity="0.75" stroke="#334155" stroke-width="1" />
    <rect x="18" y="58" width="5" height="150" rx="3" fill="{$color}" />
    <text x="32" y="105" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="16" font-weight="800" width="250">$shortTitle</text>
    <text x="32" y="175" fill="{$color}" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="11" font-weight="700">★ SARKARI.ONLINE UPDATE</text>
</svg>
SVG;
    }

    // Full Size Mode (800x450 / 1200x675)
    // Intelligent word wrap with max 28 characters per line
    $words = explode(' ', $title ?: 'Education & Examination Update');
    $lines = [];
    $currentLine = '';
    foreach ($words as $w) {
        if (mb_strlen($currentLine . ' ' . $w) <= 28) {
            $currentLine = trim($currentLine . ' ' . $w);
        } else {
            if ($currentLine !== '') $lines[] = $currentLine;
            $currentLine = $w;
            if (count($lines) >= 3) break;
        }
    }
    if ($currentLine !== '' && count($lines) < 3) {
        $lines[] = $currentLine;
    }

    // If 3rd line exceeds length, truncate with ellipsis
    if (count($lines) === 3 && mb_strlen($lines[2]) > 26) {
        $lines[2] = mb_substr($lines[2], 0, 23) . '...';
    }

    $fontSize = count($lines) >= 3 ? 28 : 34;
    $lineSpacing = count($lines) >= 3 ? 46 : 54;
    $startY = count($lines) >= 3 ? 165 : 185;

    $textSvg = '';
    foreach ($lines as $i => $lineText) {
        $y = $startY + ($i * $lineSpacing);
        $escaped = htmlspecialchars($lineText, ENT_QUOTES, 'UTF-8');
        $textSvg .= "<text x=\"70\" y=\"{$y}\" fill=\"#ffffff\" font-family=\"-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif\" font-size=\"{$fontSize}\" font-weight=\"800\">{$escaped}</text>\n";
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $width $height" width="100%" height="100%" preserveAspectRatio="xMidYMid slice">
    <defs>
        <linearGradient id="grad_{$categorySlug}" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0f172a" />
            <stop offset="100%" stop-color="{$color}" stop-opacity="0.85" />
        </linearGradient>
        <pattern id="pattern_{$categorySlug}" width="30" height="30" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.5" fill="#ffffff" fill-opacity="0.06" />
        </pattern>
    </defs>
    <!-- Background -->
    <rect width="$width" height="$height" fill="url(#grad_{$categorySlug})" />
    <rect width="$width" height="$height" fill="url(#pattern_{$categorySlug})" />
    
    <!-- Accent Line -->
    <rect x="0" y="0" width="10" height="$height" fill="{$color}" />
    
    <!-- Header Brand & Category -->
    <rect x="40" y="35" width="40" height="40" rx="6" fill="{$color}" />
    <text x="53" y="62" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="20" font-weight="900">S</text>
    
    <text x="92" y="52" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="16" font-weight="800" letter-spacing="1">SARKARI.ONLINE</text>
    <text x="92" y="68" fill="#94a3b8" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="10" font-weight="700" letter-spacing="1">INDEPENDENT INFORMATION DESK</text>
    
    <rect x="580" y="35" width="180" height="32" rx="4" fill="{$color}" fill-opacity="0.45" />
    <text x="595" y="56" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="12" font-weight="800" letter-spacing="1">$catName</text>

    <!-- Center Card Box -->
    <rect x="40" y="95" width="720" height="280" rx="8" fill="#0f172a" fill-opacity="0.75" stroke="#334155" stroke-width="1.5" />
    <rect x="40" y="95" width="8" height="280" rx="4" fill="{$color}" />

    <!-- Headline Lines -->
    {$textSvg}
    
    <!-- Verified Badge -->
    <text x="70" y="335" fill="{$color}" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="14" font-weight="700">VERIFIED PUBLIC NOTICE &amp; PROCEDURE</text>

    <!-- Footer Watermark -->
    <text x="40" y="418" fill="#ffffff" fill-opacity="0.4" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="12" font-weight="700" letter-spacing="1.5">SARKARI.ONLINE • INDEPENDENT INFORMATION PORTAL</text>
</svg>
SVG;
}
