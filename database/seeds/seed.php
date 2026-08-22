<?php
/**
 * EduPulse - Database Initial Seeder
 * Populates default admin user, categories, initial articles, sources, and settings.
 */

use App\Database\Database;

echo "[*] Seeding default records...\n";

// 1. Seed Admin User
$adminUsername = 'admin';
$adminEmail = 'admin@edupulse.in';
$adminPass = 'admin12345';
$passwordHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

$existingAdmin = Database::fetchOne("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1", [
    'u' => $adminUsername,
    'e' => $adminEmail
]);

if (!$existingAdmin) {
    $adminId = Database::insert('users', [
        'username' => $adminUsername,
        'email' => $adminEmail,
        'password_hash' => $passwordHash,
        'role' => 'admin',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "    -> Created Admin User: {$adminUsername} (Pass: {$adminPass})\n";
} else {
    $adminId = $existingAdmin['id'];
    Database::update('users', ['password_hash' => $passwordHash], 'id = :id', ['id' => $adminId]);
    echo "    -> Admin user updated (ID: {$adminId})\n";
}

// 2. Seed Categories
$categories = [
    [
        'name' => 'Exam Results',
        'slug' => 'exam-results',
        'description' => 'Real-time announcements and direct link updates for national, state, and board exam results.',
        'color' => '#1d4ed8',
        'bg_light' => '#eff6ff',
        'icon' => 'award',
        'sort_order' => 1
    ],
    [
        'name' => 'Admit Cards',
        'slug' => 'admit-cards',
        'description' => 'Official hall tickets, exam city intimation slips, and admit card download notifications.',
        'color' => '#0f766e',
        'bg_light' => '#f0fdfa',
        'icon' => 'id-card',
        'sort_order' => 2
    ],
    [
        'name' => 'Exam Dates',
        'slug' => 'exam-dates',
        'description' => 'Schedules, shift timings, notification dates, and application deadlines across India.',
        'color' => '#4338ca',
        'bg_light' => '#eef2ff',
        'icon' => 'calendar',
        'sort_order' => 3
    ],
    [
        'name' => 'Answer Keys',
        'slug' => 'answer-keys',
        'description' => 'Provisional and final answer keys, response sheets, and question paper challenge updates.',
        'color' => '#7c3aed',
        'bg_light' => '#f5f3ff',
        'icon' => 'check-circle',
        'sort_order' => 4
    ],
    [
        'name' => 'Entrance Exams',
        'slug' => 'entrance-exams',
        'description' => 'Detailed coverage of JEE, NEET, CUET, GATE, CAT, UPSC, and state-level entrance tests.',
        'color' => '#b91c1c',
        'bg_light' => '#fef2f2',
        'icon' => 'compass',
        'sort_order' => 5
    ],
    [
        'name' => 'Government Jobs',
        'slug' => 'government-jobs',
        'description' => 'Verified Central and State Government recruitment notifications, eligibility, and vacancies.',
        'color' => '#c2410c',
        'bg_light' => '#fff7ed',
        'icon' => 'briefcase',
        'sort_order' => 6
    ],
    [
        'name' => 'Scholarships',
        'slug' => 'scholarships',
        'description' => 'Government, private, merit-based, and means-tested scholarships for school and higher education.',
        'color' => '#047857',
        'bg_light' => '#ecfdf5',
        'icon' => 'graduation-cap',
        'sort_order' => 7
    ],
    [
        'name' => 'College Updates',
        'slug' => 'college-updates',
        'description' => 'University admissions, NIRF rankings, cutoffs, seat matrices, and counselling schedules.',
        'color' => '#6d28d9',
        'bg_light' => '#f5f3ff',
        'icon' => 'building',
        'sort_order' => 8
    ],
    [
        'name' => 'Career Guides',
        'slug' => 'career-guides',
        'description' => 'Actionable career roadmaps, salary insights, eligibility, and emerging industry profiles in India.',
        'color' => '#9a3412',
        'bg_light' => '#fff7ed',
        'icon' => 'trending-up',
        'sort_order' => 9
    ],
    [
        'name' => 'Student Tech & AI',
        'slug' => 'student-technology',
        'description' => 'Curated AI study tools, productivity apps, learning software, and digital tools for students.',
        'color' => '#334155',
        'bg_light' => '#f8fafc',
        'icon' => 'cpu',
        'sort_order' => 10
    ]
];

$categoryMap = [];
foreach ($categories as $cat) {
    $existing = Database::fetchOne("SELECT id FROM categories WHERE slug = :s LIMIT 1", ['s' => $cat['slug']]);
    if (!$existing) {
        $catId = Database::insert('categories', [
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'description' => $cat['description'],
            'color' => $cat['color'],
            'bg_light' => $cat['bg_light'],
            'icon' => $cat['icon'],
            'sort_order' => $cat['sort_order'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $categoryMap[$cat['slug']] = $catId;
    } else {
        $categoryMap[$cat['slug']] = $existing['id'];
    }
}
echo "    -> Categories seeded: " . count($categories) . "\n";

// 3. Seed Official Sources
$sources = [
    ['name' => 'National Testing Agency (NTA)', 'base_url' => 'https://exams.nta.ac.in', 'adapter_class' => 'NTAAdapter'],
    ['name' => 'Central Board of Secondary Education (CBSE)', 'base_url' => 'https://cbse.gov.in', 'adapter_class' => 'CBSEAdapter'],
    ['name' => 'Union Public Service Commission (UPSC)', 'base_url' => 'https://upsc.gov.in', 'adapter_class' => 'UPSCAdapter'],
    ['name' => 'Staff Selection Commission (SSC)', 'base_url' => 'https://ssc.gov.in', 'adapter_class' => 'SSCAdapter'],
    ['name' => 'Medical Counselling Committee (MCC)', 'base_url' => 'https://mcc.nic.in', 'adapter_class' => 'MCCAdapter'],
    ['name' => 'University Grants Commission (UGC)', 'base_url' => 'https://ugc.gov.in', 'adapter_class' => 'UGCAdapter']
];

foreach ($sources as $src) {
    $existing = Database::fetchOne("SELECT id FROM sources WHERE name = :n LIMIT 1", ['n' => $src['name']]);
    if (!$existing) {
        Database::insert('sources', [
            'name' => $src['name'],
            'base_url' => $src['base_url'],
            'adapter_class' => $src['adapter_class'],
            'is_active' => 1,
            'robots_checked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
echo "    -> Sources seeded: " . count($sources) . "\n";

// 4. Seed Initial Articles
$articles = [
    [
        'title' => 'NEET UG 2026 All India Quota (AIQ) Counselling Schedule Released by MCC: Check Round 1 Choice Filling Dates',
        'slug' => 'neet-ug-2026-counselling-schedule-released-mcc-nic-in',
        'excerpt' => 'The Medical Counselling Committee (MCC) has officially published the 15% AIQ counselling calendar for MBBS/BDS admissions across government and deemed universities in India.',
        'content' => '<p class="lead">The Medical Counselling Committee (MCC), Directorate General of Health Services (DGHS), has officially published the information bulletin and calendar for <strong>NEET UG 2026 All India Quota (AIQ) Counselling</strong>.</p><h2>Overview of NEET UG 2026 AIQ Counselling Stages</h2><ul><li><strong>15% All India Quota (AIQ) Seats:</strong> Across all state government medical and dental colleges without domicile restrictions.</li><li><strong>100% MBBS/BDS Seats:</strong> In AIIMS institutions across India, JIPMER (Puducherry &amp; Karaikal), and Central Universities.</li></ul>',
        'category_id' => $categoryMap['exam-results'] ?? 1,
        'author_id' => $adminId,
        'status' => 'published',
        'quality_score' => 96,
        'ai_generated' => 0,
        'source_verified' => 1,
        'meta_title' => 'NEET UG 2026 AIQ Counselling Schedule Released: Round 1 Dates',
        'meta_description' => 'Medical Counselling Committee (MCC) announces NEET UG 2026 Round 1 AIQ 15% counselling schedule, registration links, and eligibility guidelines.',
        'source_name' => 'Medical Counselling Committee (mcc.nic.in)',
        'source_url' => 'https://mcc.nic.in',
        'source_ref' => 'Notification No. F.No.U-12021/01/2026-MEC',
        'published_at' => '2026-08-18 16:30:00',
        'original_published_at' => '2026-08-18 16:30:00'
    ],
    [
        'title' => 'JEE Advanced 2026 Category-wise Qualifying Cutoff Marks Declared by IIT Delhi',
        'slug' => 'jee-advanced-2026-qualifying-cutoff-marks-iit-delhi',
        'excerpt' => 'Detailed breakdown of minimum percentage of aggregate marks and subject-wise cutoffs required for Common Rank List (CRL) and reserved categories.',
        'content' => '<p class="lead">IIT Delhi has officially released the category-wise qualifying cutoff marks and minimum percentage criteria for JEE Advanced 2026.</p>',
        'category_id' => $categoryMap['entrance-exams'] ?? 5,
        'author_id' => $adminId,
        'status' => 'published',
        'quality_score' => 94,
        'ai_generated' => 0,
        'source_verified' => 1,
        'meta_title' => 'JEE Advanced 2026 Category-wise Qualifying Cutoff Marks Declared',
        'meta_description' => 'Check official JEE Advanced 2026 CRL, EWS, OBC-NCL, SC, ST cutoff marks and minimum aggregate percentages.',
        'source_name' => 'IIT Delhi JEE Office',
        'source_url' => 'https://jeeadv.ac.in',
        'published_at' => '2026-08-18 14:00:00',
        'original_published_at' => '2026-08-18 14:00:00'
    ],
    [
        'title' => 'SSC CGL 2026 Notification for 14,582 Group B & C Vacancies: Apply by 30th Sept',
        'slug' => 'ssc-cgl-2026-recruitment-notification-vacancies-eligibility',
        'excerpt' => 'Staff Selection Commission announces comprehensive Combined Graduate Level recruitment for Assistant Section Officer, Income Tax Inspector, and Central Excise Inspector.',
        'content' => '<p class="lead">Staff Selection Commission (SSC) has officially published the CGL 2026 notice inviting eligible graduates for 14,582 Group B &amp; C posts across Central Ministries.</p>',
        'category_id' => $categoryMap['government-jobs'] ?? 6,
        'author_id' => $adminId,
        'status' => 'published',
        'quality_score' => 95,
        'ai_generated' => 0,
        'source_verified' => 1,
        'meta_title' => 'SSC CGL 2026 Notification PDF: 14,582 Vacancies, Eligibility, Syllabus',
        'meta_description' => 'Staff Selection Commission releases SSC CGL 2026 recruitment notification. Check tier 1 exam pattern, syllabus, eligibility, and direct apply link.',
        'source_name' => 'Staff Selection Commission (ssc.gov.in)',
        'source_url' => 'https://ssc.gov.in',
        'published_at' => '2026-08-18 12:00:00',
        'original_published_at' => '2026-08-18 12:00:00'
    ],
    [
        'title' => 'IBPS PO Prelims 2026 Admit Card Out: Download CRP PO/MT XIV Call Letter',
        'slug' => 'ibps-po-2026-prelims-admit-card-download-active',
        'excerpt' => 'Institute of Banking Personnel Selection has released the online preliminary exam hall ticket for Probationary Officer recruitment across participating public sector banks.',
        'content' => '<p class="lead">Candidates appearing for IBPS CRP PO/MT XIV examination can download their preliminary call letters till the exam date from the official portal at ibps.in.</p>',
        'category_id' => $categoryMap['admit-cards'] ?? 2,
        'author_id' => $adminId,
        'status' => 'published',
        'quality_score' => 92,
        'ai_generated' => 0,
        'source_verified' => 1,
        'meta_title' => 'IBPS PO Prelims 2026 Admit Card Download Direct Link at ibps.in',
        'meta_description' => 'Download IBPS PO Prelims 2026 call letter with registration number and date of birth. Check shift timings and exam day guidelines.',
        'source_name' => 'Institute of Banking Personnel Selection',
        'source_url' => 'https://ibps.in',
        'published_at' => '2026-08-18 11:20:00',
        'original_published_at' => '2026-08-18 11:20:00'
    ],
    [
        'title' => 'National Scholarship Portal 2026: Central Sector Scheme for College and University Students',
        'slug' => 'nsp-central-sector-scholarship-scheme-college-university',
        'excerpt' => 'Ministry of Education invites online fresh and renewal applications for merit-cum-means scholarship providing ₹12,000 to ₹20,000 per annum for higher studies.',
        'content' => '<p class="lead">The Department of Higher Education, Ministry of Education, has opened the National Scholarship Portal (NSP) for Central Sector Scheme 2026.</p>',
        'category_id' => $categoryMap['scholarships'] ?? 7,
        'author_id' => $adminId,
        'status' => 'published',
        'quality_score' => 93,
        'ai_generated' => 0,
        'source_verified' => 1,
        'meta_title' => 'NSP Central Sector Scholarship 2026: Eligibility, Direct Apply Link',
        'meta_description' => 'Apply online for Central Sector Scholarship on scholarships.gov.in. Check eligibility, family income cap, and document checklist.',
        'source_name' => 'Ministry of Education (scholarships.gov.in)',
        'source_url' => 'https://scholarships.gov.in',
        'published_at' => '2026-08-17 18:00:00',
        'original_published_at' => '2026-08-17 18:00:00'
    ],
    [
        'title' => 'Draft Proposal: UGC Revised Norms for Dual Degrees and Credit Transfers 2027',
        'slug' => 'draft-proposal-ugc-revised-norms-dual-degrees-2027',
        'excerpt' => 'Internal draft reviewing UGC framework for simultaneous degree programs and Academic Bank of Credits (ABC) credit transfers.',
        'content' => '<p>This is an internal editorial review draft covering proposed changes to national university credit transfers.</p>',
        'category_id' => $categoryMap['college-updates'] ?? 8,
        'author_id' => $adminId,
        'status' => 'draft',
        'quality_score' => 74,
        'ai_generated' => 0,
        'source_verified' => 0,
        'published_at' => null
    ],
    [
        'title' => 'Review Required: RRB ALP Tier 2 Revised CBT Exam Center Allotment',
        'slug' => 'review-rrb-alp-tier-2-exam-center-allotment',
        'excerpt' => 'Pending review of regional RRB notices regarding additional CBT centers across Eastern and Southern railway divisions.',
        'content' => '<p>Awaiting secondary verification of notification reference with RRB regional portals.</p>',
        'category_id' => $categoryMap['exam-dates'] ?? 3,
        'author_id' => $adminId,
        'status' => 'review',
        'quality_score' => 84,
        'ai_generated' => 1,
        'source_verified' => 0,
        'published_at' => null
    ]
];

foreach ($articles as $art) {
    $existing = Database::fetchOne("SELECT id FROM articles WHERE slug = :s LIMIT 1", ['s' => $art['slug']]);
    if (!$existing) {
        Database::insert('articles', [
            'title' => $art['title'],
            'slug' => $art['slug'],
            'excerpt' => $art['excerpt'],
            'content' => $art['content'],
            'category_id' => $art['category_id'],
            'author_id' => $art['author_id'],
            'status' => $art['status'],
            'quality_score' => $art['quality_score'],
            'ai_generated' => $art['ai_generated'],
            'source_verified' => $art['source_verified'],
            'meta_title' => $art['meta_title'] ?? null,
            'meta_description' => $art['meta_description'] ?? null,
            'source_name' => $art['source_name'] ?? null,
            'source_url' => $art['source_url'] ?? null,
            'source_ref' => $art['source_ref'] ?? null,
            'published_at' => $art['published_at'] ?? null,
            'original_published_at' => $art['original_published_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
echo "    -> Articles seeded: " . count($articles) . "\n";

// 5. Seed Default Trends
$trends = [
    ['keyword' => 'NEET UG Counselling 2026', 'source' => 'google_trends', 'trend_score' => 95, 'category_id' => $categoryMap['exam-results'] ?? 1, 'status' => 'processed'],
    ['keyword' => 'SSC CGL 2026 Notification PDF', 'source' => 'google_trends', 'trend_score' => 88, 'category_id' => $categoryMap['government-jobs'] ?? 6, 'status' => 'processed'],
    ['keyword' => 'JEE Advanced Cutoff 2026', 'source' => 'google_trends', 'trend_score' => 82, 'category_id' => $categoryMap['entrance-exams'] ?? 5, 'status' => 'approved'],
    ['keyword' => 'CUET PG 2027 Syllabus Revision', 'source' => 'rss_feed', 'trend_score' => 65, 'category_id' => $categoryMap['exam-dates'] ?? 3, 'status' => 'new']
];

foreach ($trends as $tr) {
    $existing = Database::fetchOne("SELECT id FROM trends WHERE keyword = :k LIMIT 1", ['k' => $tr['keyword']]);
    if (!$existing) {
        Database::insert('trends', [
            'keyword' => $tr['keyword'],
            'source' => $tr['source'],
            'trend_score' => $tr['trend_score'],
            'category_id' => $tr['category_id'],
            'status' => $tr['status'],
            'detected_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
echo "    -> Trends seeded: " . count($trends) . "\n";

// 6. Seed Default Settings
$settings = [
    'site_name' => 'EduPulse',
    'site_tagline' => 'Authentic Indian Education, Exams & Career Intelligence',
    'site_description' => 'Real-time updates, authentic alerts, and comprehensive guides on Indian entrance exams, results, admit cards, government jobs, and scholarships.',
    'auto_publish_daily_limit' => '5',
    'min_quality_score' => '90',
    'contact_email' => 'editorial@edupulse.in',
    'gemini_model' => 'gemini-1.5-flash',
    'maintenance_mode' => '0'
];

foreach ($settings as $k => $v) {
    $existing = Database::fetchOne("SELECT id FROM settings WHERE `key` = :k LIMIT 1", ['k' => $k]);
    if (!$existing) {
        Database::insert('settings', [
            'key' => $k,
            'value' => $v,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
echo "    -> Settings seeded: " . count($settings) . "\n";

echo "[✓] All seed data populated successfully.\n";
