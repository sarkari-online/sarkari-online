<?php
/**
 * Sarkari.online - State Government Jobs Service
 * Manages official state government recruitment directories, regional commissions,
 * automated article matching, and localized search-engine optimization.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class StateJobService {

    /**
     * Master configuration of Indian States & Union Territories
     */
    private static array $states = [
        'uttar-pradesh' => [
            'slug' => 'uttar-pradesh',
            'name' => 'Uttar Pradesh',
            'name_hi' => 'उत्तर प्रदेश',
            'code' => 'UP',
            'region' => 'North',
            'capital' => 'Lucknow',
            'tagline' => 'UP Police, UPSSSC PET, UPPSC, Lekhpal & Teaching Recruitment 2026',
            'meta_title' => 'UP Sarkari Naukri 2026 — Latest Uttar Pradesh Govt Jobs, Results & Notifications',
            'meta_desc' => 'Verified Uttar Pradesh (UP) government jobs 2026, UP Police Constable & SI vacancy, UPSSSC, UPPSC civil services, teacher recruitment, and official direct application portals.',
            'top_keywords' => [
                'UP Sarkari Naukri 2026', 'UP Police Vacancy 2026', 'UPSSSC PET Notification',
                'UPPSC Civil Services', 'UP Lekhpal Recruitment', 'UP TGT PGT Vacancy'
            ],
            'match_keywords' => ['UPSSSC', 'UPPSC', 'UP Police', 'Uttar Pradesh', 'Lekhpal', 'UPTET', 'UP TET', 'Allahabad High Court', 'UPPCL', 'UP Metro', 'UP BEd'],
            'conducting_bodies' => [
                ['name' => 'UP Subordinate Services Selection Commission', 'abbr' => 'UPSSSC', 'url' => 'http://upsssc.gov.in', 'desc' => 'Group C, Lekhpal, Junior Assistant & PET'],
                ['name' => 'UP Public Service Commission', 'abbr' => 'UPPSC', 'url' => 'https://uppsc.up.nic.in', 'desc' => 'PCS, RO/ARO, Medical & Judicial Services'],
                ['name' => 'UP Police Recruitment & Promotion Board', 'abbr' => 'UPPRPB', 'url' => 'https://uppbpb.gov.in', 'desc' => 'Constable, Sub-Inspector (SI), Jail Warder'],
                ['name' => 'High Court of Judicature at Allahabad', 'abbr' => 'Allahabad HC', 'url' => 'https://www.allahabadhighcourt.in', 'desc' => 'RO, ARO, Stenographer, Group C & D']
            ],
            'color' => '#1e3a8a',
            'bg' => '#eff6ff'
        ],
        'bihar' => [
            'slug' => 'bihar',
            'name' => 'Bihar',
            'name_hi' => 'बिहार',
            'code' => 'BR',
            'region' => 'East',
            'capital' => 'Patna',
            'tagline' => 'BPSC Teacher TRE, BSSC Inter Level, Bihar Police & CSBC Alerts 2026',
            'meta_title' => 'Bihar Sarkari Naukri 2026 — BPSC, BSSC, Bihar Police Vacancy & Results',
            'meta_desc' => 'Latest Bihar government job alerts 2026. Official notifications for BPSC TRE Teacher recruitment, BSSC 10+2 Inter Level, Bihar Police Constable, CSBC, and Patna High Court.',
            'top_keywords' => [
                'Bihar Sarkari Naukri 2026', 'BPSC TRE Teacher Vacancy', 'BSSC Inter Level 2026',
                'Bihar Police Constable Alert', 'BPSSC SI Recruitment', 'Patna High Court Jobs'
            ],
            'match_keywords' => ['BPSC', 'BSSC', 'Bihar Police', 'CSBC', 'BPSSC', 'Bihar', 'Patna High Court', 'BTESC', 'Bihar SSC', 'BPSC TRE'],
            'conducting_bodies' => [
                ['name' => 'Bihar Public Service Commission', 'abbr' => 'BPSC', 'url' => 'https://www.bpsc.bih.nic.in', 'desc' => 'Civil Services, Teacher Recruitment (TRE), Assistant Professor'],
                ['name' => 'Bihar Staff Selection Commission', 'abbr' => 'BSSC', 'url' => 'https://bssc.bihar.gov.in', 'desc' => 'Inter Level (10+2), CGL Graduate Level, Steno'],
                ['name' => 'Central Selection Board of Constable', 'abbr' => 'CSBC', 'url' => 'https://csbc.bih.nic.in', 'desc' => 'Bihar Police Constable, Fireman, Prohibition Constable'],
                ['name' => 'Bihar Police Sub-ordinate Services Commission', 'abbr' => 'BPSSC', 'url' => 'https://bpssc.bih.nic.in', 'desc' => 'Police Sub-Inspector (SI), Sergeant']
            ],
            'color' => '#0f766e',
            'bg' => '#f0fdfa'
        ],
        'rajasthan' => [
            'slug' => 'rajasthan',
            'name' => 'Rajasthan',
            'name_hi' => 'राजस्थान',
            'code' => 'RJ',
            'region' => 'West',
            'capital' => 'Jaipur',
            'tagline' => 'RSMSSB CET, RPSC RAS, Rajasthan Police & REET Teacher Updates 2026',
            'meta_title' => 'Rajasthan Govt Jobs 2026 — RSMSSB, RPSC RAS, REET & Police Vacancies',
            'meta_desc' => 'Verified Rajasthan government recruitment 2026. RSMSSB CET Graduation & 12th Level, RPSC RAS/RTS, REET Teacher Grade 3, Rajasthan Police Constable, and Patwari vacancies.',
            'top_keywords' => [
                'Rajasthan Govt Jobs 2026', 'RSMSSB CET Notification', 'RPSC RAS 2026',
                'REET Exam Date 2026', 'Rajasthan Police Vacancy', 'Rajasthan Patwari Bharti'
            ],
            'match_keywords' => ['RSMSSB', 'RPSC', 'Rajasthan', 'REET', 'RAS', 'Rajasthan Police', 'Patwari', 'RSMSSB CET', 'High Court Jodhpur'],
            'conducting_bodies' => [
                ['name' => 'Rajasthan Staff Selection Board', 'abbr' => 'RSMSSB', 'url' => 'https://rsmssb.rajasthan.gov.in', 'desc' => 'CET, Patwari, VDO, Junior Accountant, Informatics Assistant'],
                ['name' => 'Rajasthan Public Service Commission', 'abbr' => 'RPSC', 'url' => 'https://rpsc.rajasthan.gov.in', 'desc' => 'RAS/RTS, 1st & 2nd Grade Teachers, College Lecturer'],
                ['name' => 'Rajasthan Police Department', 'abbr' => 'Raj Police', 'url' => 'https://police.rajasthan.gov.in', 'desc' => 'Constable, Sub-Inspector (SI), Telecommunications']
            ],
            'color' => '#b45309',
            'bg' => '#fffbeb'
        ],
        'madhya-pradesh' => [
            'slug' => 'madhya-pradesh',
            'name' => 'Madhya Pradesh',
            'name_hi' => 'मध्य प्रदेश',
            'code' => 'MP',
            'region' => 'Central',
            'capital' => 'Bhopal',
            'tagline' => 'MPESB Vyapam, MPPSC State Services, MP Police & Patwari Recruitment 2026',
            'meta_title' => 'MP Sarkari Naukri 2026 — MPESB Vyapam, MPPSC, MP Police Vacancies',
            'meta_desc' => 'Official Madhya Pradesh government job updates 2026. Direct links to MPESB (Vyapam) notifications, MPPSC State Service Examination, MP Police Constable, Patwari, and Samvida Shikshak.',
            'top_keywords' => [
                'MP Sarkari Naukri 2026', 'MPESB Vyapam Vacancy', 'MPPSC State Service 2026',
                'MP Police Constable Alert', 'MP Patwari Bharti', 'MP TET Samvida Shikshak'
            ],
            'match_keywords' => ['MPESB', 'MPPSC', 'MP Police', 'Madhya Pradesh', 'Vyapam', 'PEB', 'MP Patwari', 'MP TET', 'MP High Court'],
            'conducting_bodies' => [
                ['name' => 'MP Employees Selection Board (Vyapam)', 'abbr' => 'MPESB', 'url' => 'https://esb.mp.gov.in', 'desc' => 'Group 2, Group 4, Patwari, Constable, Jail Prahari'],
                ['name' => 'Madhya Pradesh Public Service Commission', 'abbr' => 'MPPSC', 'url' => 'https://mppsc.mp.gov.in', 'desc' => 'State Services (Civil), State Forest, Medical Officer'],
                ['name' => 'Madhya Pradesh High Court', 'abbr' => 'MPHC', 'url' => 'https://mphc.gov.in', 'desc' => 'Civil Judge, Stenographer, Assistant Grade 3']
            ],
            'color' => '#6d28d9',
            'bg' => '#f5f3ff'
        ],
        'delhi' => [
            'slug' => 'delhi',
            'name' => 'Delhi (NCT)',
            'name_hi' => 'दिल्ली',
            'code' => 'DL',
            'region' => 'North',
            'capital' => 'New Delhi',
            'tagline' => 'DSSSB Teaching & Non-Teaching, Delhi Police & High Court Jobs 2026',
            'meta_title' => 'Delhi Govt Jobs 2026 — DSSSB Teacher, Delhi Police, High Court Vacancies',
            'meta_desc' => 'Latest Delhi government job vacancies 2026. Official DSSSB PRT/TGT/PGT teacher recruitment, Delhi Police Head Constable & Executive, and Delhi High Court recruitments.',
            'top_keywords' => [
                'Delhi Govt Jobs 2026', 'DSSSB Recruitment 2026', 'Delhi Police Vacancy',
                'DSSSB TGT PGT Vacancy', 'Delhi High Court Jobs', 'DDA Recruitment 2026'
            ],
            'match_keywords' => ['DSSSB', 'Delhi Police', 'Delhi', 'DDA', 'Delhi High Court', 'DTC', 'DMRC'],
            'conducting_bodies' => [
                ['name' => 'Delhi Subordinate Services Selection Board', 'abbr' => 'DSSSB', 'url' => 'https://dsssb.delhi.gov.in', 'desc' => 'TGT, PGT, PRT Teachers, DASS Cadre, Junior Assistant'],
                ['name' => 'Delhi Police (SSC / DP)', 'abbr' => 'Delhi Police', 'url' => 'https://delhipolice.gov.in', 'desc' => 'Constable Executive, Head Constable (Ministerial/AWO/TPO)'],
                ['name' => 'Delhi Development Authority', 'abbr' => 'DDA', 'url' => 'https://dda.gov.in', 'desc' => 'Assistant Accounts Officer, Patwari, Junior Engineer']
            ],
            'color' => '#15803d',
            'bg' => '#f0fdf4'
        ],
        'haryana' => [
            'slug' => 'haryana',
            'name' => 'Haryana',
            'name_hi' => 'हरियाणा',
            'code' => 'HR',
            'region' => 'North',
            'capital' => 'Chandigarh',
            'tagline' => 'HSSC CET Group C & D, HPSC HCS, Haryana Police Alerts 2026',
            'meta_title' => 'Haryana Govt Jobs 2026 — HSSC CET Group C/D, HPSC HCS, Police Bharti',
            'meta_desc' => 'Authentic Haryana government job notifications 2026. Direct portals for HSSC Common Eligibility Test (CET) Group C & D, HPSC Civil Services (HCS), and Haryana Police Constable.',
            'top_keywords' => [
                'Haryana Govt Jobs 2026', 'HSSC CET Group C 2026', 'HPSC HCS Notification',
                'Haryana Police Constable Bharti', 'HTET Exam Date', 'HSSC Group D Result'
            ],
            'match_keywords' => ['HSSC', 'HPSC', 'Haryana', 'HCS', 'HTET', 'Haryana Police', 'HKRN'],
            'conducting_bodies' => [
                ['name' => 'Haryana Staff Selection Commission', 'abbr' => 'HSSC', 'url' => 'https://hssc.gov.in', 'desc' => 'CET Group C & D, Police Constable, Canal Patwari'],
                ['name' => 'Haryana Public Service Commission', 'abbr' => 'HPSC', 'url' => 'https://hpsc.gov.in', 'desc' => 'HCS Executive, Assistant Professor, Sub-Divisional Officer'],
                ['name' => 'Haryana Kaushal Rozgar Nigam', 'abbr' => 'HKRN', 'url' => 'https://hkrnl.itiharyana.gov.in', 'desc' => 'Contractual government postings across Haryana departments']
            ],
            'color' => '#b91c1c',
            'bg' => '#fef2f2'
        ],
        'maharashtra' => [
            'slug' => 'maharashtra',
            'name' => 'Maharashtra',
            'name_hi' => 'महाराष्ट्र',
            'code' => 'MH',
            'region' => 'West',
            'capital' => 'Mumbai',
            'tagline' => 'MPSC State Services, Talathi Bharti, Maharashtra Police & ZP Bharti 2026',
            'meta_title' => 'Maharashtra Govt Jobs 2026 (महासर्कारी नोकरी) — MPSC, Talathi, Police Bharti',
            'meta_desc' => 'Maharashtra government jobs 2026 (MahaPariksha). MPSC Rajyaseva, Talathi Bharti, Maharashtra Police Constable, Zilla Parishad (ZP), and Arogya Vibhag official alerts.',
            'top_keywords' => [
                'Maharashtra Govt Jobs 2026', 'MPSC Rajyaseva 2026', 'Maharashtra Police Bharti',
                'Talathi Bharti Result', 'Zilla Parishad Recruitment', 'MahaPariksha Portal'
            ],
            'match_keywords' => ['MPSC', 'Maharashtra', 'Talathi', 'MahaPariksha', 'Maharashtra Police', 'ZP Bharti', 'Arogya Vibhag', 'MahaTransco'],
            'conducting_bodies' => [
                ['name' => 'Maharashtra Public Service Commission', 'abbr' => 'MPSC', 'url' => 'https://mpsc.gov.in', 'desc' => 'Rajyaseva, Combined Subordinate Services Group B & C'],
                ['name' => 'Maharashtra Police Bharti', 'abbr' => 'Maha Police', 'url' => 'https://mahapolice.gov.in', 'desc' => 'Police Sipahi, Driver Constable, SRPF Armed Police'],
                ['name' => 'Revenue & Forest Department (Talathi)', 'abbr' => 'MahaBhumi', 'url' => 'https://mahabhumi.gov.in', 'desc' => 'Talathi, Mandal Adhikari, Revenue Clerk']
            ],
            'color' => '#0369a1',
            'bg' => '#f0f9ff'
        ],
        'west-bengal' => [
            'slug' => 'west-bengal',
            'name' => 'West Bengal',
            'name_hi' => 'पश्चिम बंगाल',
            'code' => 'WB',
            'region' => 'East',
            'capital' => 'Kolkata',
            'tagline' => 'WBPSC WBCS, WB Police PRB, WB TET & Health Recruitment 2026',
            'meta_title' => 'West Bengal Govt Jobs 2026 — WBPSC WBCS, WB Police PRB, WB TET Alerts',
            'meta_desc' => 'Latest West Bengal government job updates 2026. WBPSC West Bengal Civil Service (WBCS), West Bengal Police PRB Constable & SI, WB Primary TET, and WBHRB alerts.',
            'top_keywords' => [
                'West Bengal Govt Jobs 2026', 'WBPSC WBCS 2026', 'WB Police Constable Bharti',
                'WB TET Notification 2026', 'Calcutta High Court Jobs', 'WBHRB Staff Nurse'
            ],
            'match_keywords' => ['WBPSC', 'WBCS', 'WB Police', 'WBP PRB', 'West Bengal', 'WB TET', 'Calcutta High Court', 'WBHRB'],
            'conducting_bodies' => [
                ['name' => 'West Bengal Public Service Commission', 'abbr' => 'WBPSC', 'url' => 'https://psc.wb.gov.in', 'desc' => 'WBCS Executive, Miscellaneous Services, Audit & Accounts'],
                ['name' => 'West Bengal Police Recruitment Board', 'abbr' => 'WBPRB', 'url' => 'https://prb.wb.gov.in', 'desc' => 'Constable, Sub-Inspector (UB/AB), Kolkata Police'],
                ['name' => 'West Bengal Board of Primary Education', 'abbr' => 'WBBPE', 'url' => 'https://wbbpe.org', 'desc' => 'Primary Teacher Eligibility Test (WB TET)']
            ],
            'color' => '#4338ca',
            'bg' => '#eef2ff'
        ],
        'gujarat' => [
            'slug' => 'gujarat',
            'name' => 'Gujarat',
            'name_hi' => 'गुजरात',
            'code' => 'GJ',
            'region' => 'West',
            'capital' => 'Gandhinagar',
            'tagline' => 'GPSC Class 1 & 2, GSSSB CCE, Gujarat Police & Talati Recruitment 2026',
            'meta_title' => 'Gujarat Govt Jobs 2026 (OJAS Gujarat) — GPSC, GSSSB CCE, Police Bharti',
            'meta_desc' => 'Authentic Gujarat government job updates 2026 on OJAS portal. GPSC Class 1-2 Civil Services, GSSSB Combined Competitive Exam (CCE), Gujarat Police Lokrakshak & PSI recruitments.',
            'top_keywords' => [
                'Gujarat Govt Jobs 2026', 'OJAS Gujarat Bharti', 'GPSC Class 1 2 2026',
                'GSSSB CCE Notification', 'Gujarat Police Constable PSI', 'Talati Mantri Bharti'
            ],
            'match_keywords' => ['GPSC', 'GSSSB', 'OJAS', 'Gujarat', 'Gujarat Police', 'LRD', 'Talati', 'GPSSB'],
            'conducting_bodies' => [
                ['name' => 'Gujarat Public Service Commission', 'abbr' => 'GPSC', 'url' => 'https://gpsc.gujarat.gov.in', 'desc' => 'Class 1 & 2 Administrative, Police & Accounts Cadres'],
                ['name' => 'Gujarat Subordinate Service Selection Board', 'abbr' => 'GSSSB', 'url' => 'https://gsssb.gujarat.gov.in', 'desc' => 'Combined Competitive Exam (CCE) Group A & B, Clerk'],
                ['name' => 'OJAS Gujarat Portal', 'abbr' => 'OJAS', 'url' => 'https://ojas.gujarat.gov.in', 'desc' => 'Central application repository for all Gujarat state vacancies']
            ],
            'color' => '#0d9488',
            'bg' => '#ccfbf1'
        ],
        'jharkhand' => [
            'slug' => 'jharkhand',
            'name' => 'Jharkhand',
            'name_hi' => 'झारखंड',
            'code' => 'JH',
            'region' => 'East',
            'capital' => 'Ranchi',
            'tagline' => 'JSSC CGL, JPSC Civil Services, Jharkhand Police & JTET Alerts 2026',
            'meta_title' => 'Jharkhand Govt Jobs 2026 — JSSC CGL, JPSC Civil Services, Police Bharti',
            'meta_desc' => 'Verified Jharkhand government job notifications 2026. JSSC CGL Graduate Level, JPSC Combined Civil Services, Jharkhand Police Constable, and JTET Teacher recruitment.',
            'top_keywords' => [
                'Jharkhand Govt Jobs 2026', 'JSSC CGL 2026', 'JPSC Civil Services Exam',
                'Jharkhand Police Constable', 'JTET Notification 2026', 'JSSC Inter Level'
            ],
            'match_keywords' => ['JSSC', 'JPSC', 'Jharkhand', 'JTET', 'Jharkhand Police', 'JSSC CGL'],
            'conducting_bodies' => [
                ['name' => 'Jharkhand Staff Selection Commission', 'abbr' => 'JSSC', 'url' => 'https://jssc.nic.in', 'desc' => 'CGL, Excise Constable, Inter Level, Teacher Recruitment'],
                ['name' => 'Jharkhand Public Service Commission', 'abbr' => 'JPSC', 'url' => 'https://jpsc.gov.in', 'desc' => 'Combined Civil Services, Medical Officer, Assistant Engineer']
            ],
            'color' => '#c2410c',
            'bg' => '#ffedd5'
        ],
        'uttarakhand' => [
            'slug' => 'uttarakhand',
            'name' => 'Uttarakhand',
            'name_hi' => 'उत्तराखंड',
            'code' => 'UK',
            'region' => 'North',
            'capital' => 'Dehradun',
            'tagline' => 'UKSSSC Group C, UKPSC Upper & Lower Subordinate, UK Police 2026',
            'meta_title' => 'Uttarakhand Govt Jobs 2026 — UKPSC, UKSSSC Group C, Police Vacancies',
            'meta_desc' => 'Latest Uttarakhand government vacancies 2026. UKPSC Combined State Civil Services, UKSSSC Group C Patwari, Lekhpal, Forest Guard, and Uttarakhand Police Constable alerts.',
            'top_keywords' => [
                'Uttarakhand Govt Jobs 2026', 'UKPSC Civil Services 2026', 'UKSSSC Group C Bharti',
                'Uttarakhand Police Vacancy', 'UK Patwari Lekhpal', 'UTET Exam 2026'
            ],
            'match_keywords' => ['UKSSSC', 'UKPSC', 'Uttarakhand', 'UK Police', 'UTET', 'Uttarakhand High Court'],
            'conducting_bodies' => [
                ['name' => 'Uttarakhand Public Service Commission', 'abbr' => 'UKPSC', 'url' => 'https://psc.uk.gov.in', 'desc' => 'Upper/Lower PCS, RO/ARO, Forest Guard, Patwari/Lekhpal'],
                ['name' => 'Uttarakhand Subordinate Service Selection Commission', 'abbr' => 'UKSSSC', 'url' => 'https://sssc.uk.gov.in', 'desc' => 'Group C clerical, technical, and executive posts']
            ],
            'color' => '#1e3a8a',
            'bg' => '#eff6ff'
        ],
        'chhattisgarh' => [
            'slug' => 'chhattisgarh',
            'name' => 'Chhattisgarh',
            'name_hi' => 'छत्तीसगढ़',
            'code' => 'CG',
            'region' => 'Central',
            'capital' => 'Raipur',
            'tagline' => 'CG Vyapam, CGPSC State Service, CG Police Constable Alerts 2026',
            'meta_title' => 'Chhattisgarh Govt Jobs 2026 — CG Vyapam, CGPSC State Service, CG Police',
            'meta_desc' => 'Official Chhattisgarh government job notifications 2026. CGPSC Civil Services examination, CGPEB Vyapam recruitment tests, CG Police Constable, and Shikshak bharti.',
            'top_keywords' => [
                'Chhattisgarh Govt Jobs 2026', 'CG Vyapam Vacancy 2026', 'CGPSC State Service',
                'CG Police Constable Bharti', 'CG TET Exam Date', 'CG High Court Bilaspur'
            ],
            'match_keywords' => ['CG Vyapam', 'CGPSC', 'CGPEB', 'Chhattisgarh', 'CG Police', 'CG TET'],
            'conducting_bodies' => [
                ['name' => 'Chhattisgarh Professional Examination Board', 'abbr' => 'CG Vyapam', 'url' => 'https://vyapam.cgstate.gov.in', 'desc' => 'Recruitment & Entrance Tests for Group C & Teacher Posts'],
                ['name' => 'Chhattisgarh Public Service Commission', 'abbr' => 'CGPSC', 'url' => 'https://psc.cg.gov.in', 'desc' => 'State Services (PCS), Assistant Professor, Engineering Services']
            ],
            'color' => '#4d7c0f',
            'bg' => '#ecfccb'
        ]
    ];

    /**
     * Get all states sorted alphabetically or by prominent priority
     */
    public static function getAllStates(): array {
        return self::$states;
    }

    /**
     * Get single state configuration by slug
     */
    public static function getStateBySlug(string $slug): ?array {
        $slug = trim(strtolower($slug));
        return self::$states[$slug] ?? null;
    }

    /**
     * Get states organized by geographical zones for the main directory hub
     */
    public static function getStatesByRegion(): array {
        $grouped = [];
        foreach (self::$states as $slug => $state) {
            $region = $state['region'] . ' Zone';
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            $grouped[$region][$slug] = $state;
        }
        return $grouped;
    }

    /**
     * Automatically find published articles matching a specific state
     * Uses state names, recruiting body acronyms, and localized search keywords
     */
    public static function getArticlesByState(array $state, int $limit = 12, int $page = 1): array {
        try {
            $keywords = $state['match_keywords'] ?? [];
            if (empty($keywords)) {
                return ['items' => [], 'total' => 0, 'pages' => 1];
            }

            // Build LIKE clauses for title, slug, and content
            $conditions = [];
            $params = [];
            foreach ($keywords as $i => $kw) {
                $paramKey = "kw_{$i}";
                $conditions[] = "(a.title LIKE :{$paramKey} OR a.slug LIKE :{$paramKey})";
                $params[$paramKey] = '%' . $kw . '%';
            }
            $whereClause = '(' . implode(' OR ', $conditions) . ')';

            $offset = max(0, ($page - 1) * $limit);
            $limitInt = (int)$limit;
            $offsetInt = (int)$offset;

            // Count total
            $countSql = "SELECT COUNT(*) as total FROM articles a WHERE a.status = 'published' AND {$whereClause}";
            $totalRow = Database::fetchOne($countSql, $params);
            $total = (int)($totalRow['total'] ?? 0);

            if ($total === 0) {
                // Fallback: If no direct state matches, return latest published government & exam alerts
                $fallbackSql = "
                    SELECT a.id, a.title, a.slug, a.summary, a.featured_image, a.reading_time, a.published_at,
                           c.name as category_name, c.slug as category_slug, c.color as category_color
                    FROM articles a
                    LEFT JOIN categories c ON a.category_id = c.id
                    WHERE a.status = 'published'
                    ORDER BY a.published_at DESC
                    LIMIT {$limitInt} OFFSET {$offsetInt}
                ";
                $items = Database::fetchAll($fallbackSql);
                return [
                    'items' => $items,
                    'total' => count($items),
                    'pages' => 1,
                    'is_fallback' => true
                ];
            }

            // Query items with category info
            $dataSql = "
                SELECT a.id, a.title, a.slug, a.summary, a.featured_image, a.reading_time, a.published_at,
                       c.name as category_name, c.slug as category_slug, c.color as category_color
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.status = 'published' AND {$whereClause}
                ORDER BY a.published_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $items = Database::fetchAll($dataSql, $params);

            return [
                'items' => $items,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $limit)),
                'is_fallback' => false
            ];

        } catch (Throwable $e) {
            Logger::error("Failed to query articles for state '{$state['slug']}': " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'pages' => 1, 'is_fallback' => false];
        }
    }

    /**
     * Generate Schema.org JSON-LD structured data for State Landing Pages
     */
    public static function generateStateSchema(array $state, array $articles): array {
        $canonical = url('jobs/' . $state['slug'] . '/');
        
        $itemList = [];
        foreach (array_slice($articles, 0, 10) as $idx => $art) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $idx + 1,
                'item' => [
                    '@type' => 'Article',
                    'name' => $art['title'],
                    'url' => url('article/' . $art['slug'] . '/'),
                    'datePublished' => date('c', strtotime($art['published_at']))
                ]
            ];
        }

        $faqs = [
            [
                '@type' => 'Question',
                'name' => "Where can I check latest {$state['name']} Government Jobs 2026?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "You can check all verified {$state['name']} government job notifications on Sarkari.online's official state hub. We provide direct verified links to official conducting portals including " . implode(', ', array_column($state['conducting_bodies'], 'abbr')) . "."
                ]
            ],
            [
                '@type' => 'Question',
                'name' => "What are the primary recruiting commissions in {$state['name']}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "The primary recruitment bodies in {$state['name']} are " . implode(', ', array_map(function($b) { return "{$b['name']} ({$b['abbr']})"; }, $state['conducting_bodies'])) . "."
                ]
            ],
            [
                '@type' => 'Question',
                'name' => "How to apply online for {$state['name']} state vacancies?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "Candidates can apply online directly through official government portals like " . ($state['conducting_bodies'][0]['url'] ?? 'official recruitment sites') . " during the specified application window. Ensure you have your educational certificates, photograph, and signature ready."
                ]
            ]
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => $canonical,
                    'name' => $state['meta_title'],
                    'description' => $state['meta_desc'],
                    'url' => $canonical,
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => SITE_NAME,
                        'url' => SITE_URL
                    ],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListElement' => $itemList
                    ]
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Home',
                            'item' => SITE_URL . '/'
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'State Govt Jobs',
                            'item' => url('state-jobs/')
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $state['name'],
                            'item' => $canonical
                        ]
                    ]
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqs
                ]
            ]
        ];
    }
}
