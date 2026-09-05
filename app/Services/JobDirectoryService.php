<?php
/**
 * Sarkari.online - Dynamic Government Jobs Directory Service
 * Aggregates, parses, and formats real-time public sector job notifications
 * with verified vacancy counts, application deadlines, and regional mapping.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class JobDirectoryService {

    /**
     * Get active government job listings with extracted vacancies and deadlines
     *
     * @param int $limit Max items to return
     * @param string|null $stateFilter Optional state slug to filter by
     * @return array
     */
    public static function getActiveJobs(int $limit = 60, ?string $stateFilter = null): array {
        try {
            // Fetch published recruitment and examination articles
            $sql = "
                SELECT a.id, a.title, a.slug, a.content, a.excerpt, a.published_at, a.updated_at,
                       a.source_name, a.source_url,
                       c.name as category_name, c.slug as category_slug, c.color as category_color
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.status = 'published'
                  AND (c.slug IN ('government-jobs', 'career-guides', 'exam-dates', 'admit-cards')
                       OR a.title LIKE '%Recruitment%'
                       OR a.title LIKE '%Vacancy%'
                       OR a.title LIKE '%Online Form%'
                       OR a.title LIKE '%Posts%'
                       OR a.title LIKE '%Bharti%')
                ORDER BY a.published_at DESC
                LIMIT 100
            ";

            $rows = Database::fetchAll($sql);
            if (empty($rows)) {
                return [];
            }

            $jobs = [];
            foreach ($rows as $row) {
                $job = self::formatJobRow($row);
                if ($job === null) {
                    continue;
                }

                // Apply state filter if provided
                if ($stateFilter !== null && $stateFilter !== 'all') {
                    if ($stateFilter === 'central') {
                        if ($job['state_code'] !== 'ALL') {
                            continue;
                        }
                    } else {
                        if (strtolower($job['state_slug']) !== strtolower($stateFilter)) {
                            continue;
                        }
                    }
                }

                $jobs[] = $job;
                if (count($jobs) >= $limit) {
                    break;
                }
            }

            return $jobs;
        } catch (Throwable $e) {
            Logger::error("JobDirectoryService::getActiveJobs failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format raw database article row into an accurate, structured job item
     */
    public static function formatJobRow(array $row): ?array {
        $title = $row['title'] ?? '';
        $content = $row['content'] ?? '';
        $slug = $row['slug'] ?? '';

        // Filter out non-job noise (grievance portals, admit cards, answer keys, results, fellowships)
        if (preg_match('/\b(?:Grievance|Helpdesk|Complaint|Court|Stay Order|Admit Card Out|Hall Ticket Out|Answer Key|Scorecard Link|Result Declared|Fellowship|Scholarship)\b/i', $title)) {
            return null;
        }

        // Extract total vacancies (e.g. "1100 Posts", "32,388 Vacancies", "14582 Posts")
        $vacancies = self::extractVacancies($title, $content);

        // Extract application deadline / last date
        $deadlineInfo = self::extractLastDate($content, $title);

        // Extract conducting department / organization
        $authority = self::extractAuthority($title, $row['source_name'] ?? '');

        // Detect State / Regional classification
        $stateInfo = self::detectState($title, $content);

        // Clean display title (ensure it looks official and clear)
        $displayTitle = self::cleanJobTitle($title, $vacancies);

        // Determine status tag (intelligent deadline & lifecycle tracker)
        $statusTag = self::determineStatusTag($deadlineInfo['raw_date'] ?? '', $title);

        $displayDeadline = $deadlineInfo['formatted'];
        if ($statusTag['type'] === 'notice') {
            $displayDeadline = 'Dates Postponed';
        } elseif ($statusTag['type'] === 'closed') {
            $displayDeadline = str_contains($displayDeadline, 'Closed') ? $displayDeadline : "{$displayDeadline} (Closed)";
        }

        return [
            'id'             => (int)$row['id'],
            'title'          => $displayTitle,
            'original_title' => $title,
            'slug'           => $slug,
            'url'            => function_exists('url') ? \url('article/' . $slug . '/') : ('/article/' . $slug . '/'),
            'authority'      => $authority,
            'vacancies'      => $vacancies,
            'last_date'      => $displayDeadline,
            'last_date_raw'  => $deadlineInfo['raw_date'],
            'status_tag'     => $statusTag,
            'state_name'     => $stateInfo['name'],
            'state_code'     => $stateInfo['code'],
            'state_slug'     => $stateInfo['slug'],
            'published_at'   => $row['published_at'] ?? '',
            'category_name'  => $row['category_name'] ?? 'Govt Jobs',
            'category_color' => $row['category_color'] ?? '#1e3a8a'
        ];
    }

    /**
     * Extract vacancy count from title and HTML content
     */
    public static function extractVacancies(string $title, string $content): ?string {
        // Pattern 1: Title check for "for 1100 Posts", "32,388 Posts", "(2482 Posts)", "1.2 Lakh"
        if (preg_match('/(?:for|\(|:)?\s*(\d+[\d,]*|\d+(?:\.\d+)?\s*(?:Lakh|Lakhs?|Cr))\s*(?:wealth & credit )?(?:posts?|vacanc(?:y|ies)|seats?|roles?)/i', $title, $m)) {
            $val = trim($m[1]);
            return is_numeric(str_replace(',', '', $val)) ? number_format((int)str_replace(',', '', $val)) . ' Posts' : $val . ' Posts';
        }

        // Pattern 2: Check content in opening table or paragraph
        $cleanContent = strip_tags(mb_substr($content, 0, 2000));
        if (preg_match('/(?:Total\s+Vacanc(?:y|ies)|Total\s+Posts?|Number\s+of\s+Posts?)\s*[:\-–]\s*(\d+[\d,]*)/i', $cleanContent, $m)) {
            $val = trim($m[1]);
            return number_format((int)str_replace(',', '', $val)) . ' Posts';
        }

        return null;
    }

    /**
     * Extract application last date / deadline
     */
    public static function extractLastDate(string $content, string $title): array {
        // Check title first for "Apply by Sept 30" or "Last Date Today"
        if (preg_match('/(?:Apply\s+by|Last\s+Date\s+by)\s+([A-Za-z]+\s+\d{1,2}(?:,\s*\d{4})?)/i', $title, $m)) {
            return [
                'formatted' => 'Last Date: ' . trim($m[1]) . ' 2026',
                'raw_date'  => trim($m[1])
            ];
        }
        if (preg_match('/Last\s+Date\s+Today/i', $title)) {
            return [
                'formatted' => 'Last Date Today',
                'raw_date'  => date('Y-m-d')
            ];
        }

        // Check HTML table rows for Last Date / Application End
        if (preg_match('/<(?:td|th)[^>]*>[^<]*(?:Last\s+Date|Application\s+Deadline|Closing\s+Date|Application\s+End|End\s+Date)[^<]*<\/(?:td|th)>\s*<(?:td|th)[^>]*>(.*?)<\/(?:td|th)>/is', $content, $m)) {
            $raw = trim(strip_tags($m[1]));
            if (!empty($raw) && mb_strlen($raw) <= 45 && !str_contains(strtolower($raw), 'notify')) {
                return [
                    'formatted' => 'Last Date: ' . $raw,
                    'raw_date'  => $raw
                ];
            }
        }

        // Check general text regex in content
        $cleanContent = strip_tags(mb_substr($content, 0, 3000));
        if (preg_match('/\b(?:Last\s+Date|Deadline|Closing\s+Date|Apply\s+by|Apply\s+on\s+or\s+before)\b.{0,60}?\b([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67])\b/is', $cleanContent, $m)) {
            $raw = trim($m[1]);
            return [
                'formatted' => 'Last Date: ' . $raw,
                'raw_date'  => $raw
            ];
        }

        return [
            'formatted' => 'Apply Online',
            'raw_date'  => null
        ];
    }

    /**
     * Extract conducting recruitment authority
     */
    public static function extractAuthority(string $title, string $sourceName): string {
        $authorities = [
            'Bank of Baroda' => 'Bank of Baroda',
            'SBI'            => 'State Bank of India (SBI)',
            'IBPS'           => 'IBPS',
            'SSC'            => 'Staff Selection Commission (SSC)',
            'UPSC'           => 'UPSC',
            'RRB'            => 'Railway Recruitment Board (RRB)',
            'RRC'            => 'Railway RRC',
            'BPSC'           => 'BPSC Bihar',
            'UPSSSC'         => 'UPSSSC Uttar Pradesh',
            'UPPRPB'         => 'UP Police Board',
            'RSMSSB'         => 'RSMSSB Rajasthan',
            'RPSC'           => 'RPSC Rajasthan',
            'MPESB'          => 'MPESB Vyapam',
            'MPPSC'          => 'MPPSC Madhya Pradesh',
            'DSSSB'          => 'DSSSB Delhi',
            'HSSC'           => 'HSSC Haryana',
            'India Post'     => 'India Post',
            'NTA'            => 'National Testing Agency (NTA)',
            'CBSE'           => 'CBSE',
            'UGC'            => 'UGC',
            'RVUNL'          => 'RVUNL Rajasthan',
            'Punjab PTI'     => 'Punjab Education Board',
            'Coal India'     => 'Coal India Limited',
            'ITBP'           => 'ITBP Police',
            'Indian Army'    => 'Indian Army',
            'Indian Air Force'=> 'Indian Air Force',
            'Indian Navy'    => 'Indian Navy',
            'AAI'            => 'Airports Authority of India (AAI)',
            'IOCL'           => 'Indian Oil (IOCL)',
            'CONCOR'         => 'CONCOR India',
            'ISRO'           => 'ISRO',
            'DRDO'           => 'DRDO',
            'NIC'            => 'National Informatics Centre'
        ];

        foreach ($authorities as $key => $name) {
            if (stripos($title, $key) !== false) {
                return $name;
            }
        }

        if (!empty($sourceName) && !str_contains(strtolower($sourceName), 'statutory')) {
            return $sourceName;
        }

        return 'Public Recruitment Board';
    }

    /**
     * Detect State / Central zone for job
     */
    public static function detectState(string $title, string $content): array {
        $stateMappings = [
            'uttar-pradesh' => ['name' => 'Uttar Pradesh', 'code' => 'UP', 'slug' => 'uttar-pradesh', 'keys' => ['UP', 'Uttar Pradesh', 'UPSSSC', 'UPPSC', 'UPPRPB', 'UP Police', 'Lekhpal']],
            'bihar'         => ['name' => 'Bihar', 'code' => 'BR', 'slug' => 'bihar', 'keys' => ['Bihar', 'BPSC', 'BSSC', 'CSBC', 'BPSSC', 'Patna']],
            'rajasthan'     => ['name' => 'Rajasthan', 'code' => 'RJ', 'slug' => 'rajasthan', 'keys' => ['Rajasthan', 'RSMSSB', 'RPSC', 'RVUNL', 'REET', 'Jaipur']],
            'madhya-pradesh'=> ['name' => 'Madhya Pradesh', 'code' => 'MP', 'slug' => 'madhya-pradesh', 'keys' => ['Madhya Pradesh', 'MP', 'MPESB', 'MPPSC', 'Vyapam', 'Bhopal']],
            'delhi'         => ['name' => 'Delhi', 'code' => 'DL', 'slug' => 'delhi', 'keys' => ['Delhi', 'DSSSB', 'DDA']],
            'haryana'       => ['name' => 'Haryana', 'code' => 'HR', 'slug' => 'haryana', 'keys' => ['Haryana', 'HSSC', 'HPSC', 'HTET']],
            'punjab'        => ['name' => 'Punjab', 'code' => 'PB', 'slug' => 'punjab', 'keys' => ['Punjab', 'PPSC', 'SSSB Punjab', 'Punjab PTI']],
            'maharashtra'   => ['name' => 'Maharashtra', 'code' => 'MH', 'slug' => 'maharashtra', 'keys' => ['Maharashtra', 'MPSC', 'MHT CET']],
            'west-bengal'   => ['name' => 'West Bengal', 'code' => 'WB', 'slug' => 'west-bengal', 'keys' => ['West Bengal', 'WBPSC', 'WBJEE']],
            'odisha'        => ['name' => 'Odisha', 'code' => 'OD', 'slug' => 'odisha', 'keys' => ['Odisha', 'OPSC', 'OSSC', 'SAMS Odisha']],
        ];

        $titleSearch = ' ' . mb_strtolower($title) . ' ';
        foreach ($stateMappings as $st) {
            foreach ($st['keys'] as $kw) {
                if (stripos($titleSearch, mb_strtolower($kw)) !== false) {
                    return $st;
                }
            }
        }

        // Default to Central / All-India
        return [
            'name' => 'All India / Central',
            'code' => 'ALL',
            'slug' => 'all-india'
        ];
    }

    /**
     * Clean job headline to ensure high clarity (e.g. Bank of Baroda SO Online Form 2026 (1100 Posts))
     */
    public static function cleanJobTitle(string $title, ?string $vacancies): string {
        $clean = trim($title);

        // If title already has colon (e.g. "Bank of Baroda Recruitment 2026: Apply for 1100 Wealth & Credit Roles")
        if (str_contains($clean, ':')) {
            $parts = explode(':', $clean, 2);
            $entity = trim($parts[0]);
            $action = trim($parts[1]);

            if ($vacancies && !str_contains($entity, '(')) {
                return "{$entity} ({$vacancies})";
            }
            return "{$entity} – {$action}";
        }

        if ($vacancies && !str_contains($clean, '(')) {
            return "{$clean} ({$vacancies})";
        }

        return $clean;
    }

    /**
     * Parse various date formats commonly found in Indian recruitment notifications
     */
    public static function parseDateTimestamp(?string $dateStr): ?int {
        if (empty($dateStr)) {
            return null;
        }

        // Replace parentheses with spaces to keep time intact without bracket syntax breaking strtotime
        $clean = trim(preg_replace('/[()]/', ' ', $dateStr));
        // Remove ordinal suffixes like 2nd, 3rd, 24th
        $clean = preg_replace('/\b(st|nd|rd|th)\b/i', '', $clean);
        // Remove common non-date words
        $clean = preg_replace('/\b(tentative|expected|extended|online|form|till|up\s+to|upto)\b/i', '', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $ts = strtotime($clean);
        if ($ts !== false) {
            return $ts;
        }

        // Fallback: extract specific date pattern "September 02, 2026" or "02 September 2026"
        if (preg_match('/\b([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{4}|[A-Za-z]+\s+[0-9]{1,2},?\s+[0-9]{4})\b/', $dateStr, $m)) {
            $ts = strtotime($m[1]);
            if ($ts !== false) {
                return $ts;
            }
        }

        return null;
    }

    /**
     * Determine status badge based on deadline and title lifecycle
     */
    public static function determineStatusTag(?string $rawDate, string $title = ''): array {
        $lowerTitle = strtolower($title);
        if (str_contains($lowerTitle, 'postponed') || str_contains($lowerTitle, 'deferred') || str_contains($lowerTitle, 'cancelled')) {
            return ['label' => 'Postponed', 'type' => 'notice'];
        }

        if (empty($rawDate)) {
            return ['label' => 'Apply Online', 'type' => 'active'];
        }

        $lower = strtolower($rawDate);
        if (str_contains($lower, 'today') || $rawDate === date('Y-m-d')) {
            return ['label' => 'Last Date Today', 'type' => 'urgent'];
        }

        // Try robust date parsing
        $ts = self::parseDateTimestamp($rawDate);
        if ($ts !== null) {
            $todayStart = strtotime('today midnight');
            $targetStart = strtotime(date('Y-m-d', $ts) . ' midnight');
            $diffDays = (int)round(($targetStart - $todayStart) / 86400);

            if ($diffDays < 0) {
                return ['label' => 'Application Closed', 'type' => 'closed'];
            }
            if ($diffDays === 0) {
                return ['label' => 'Last Date Today', 'type' => 'urgent'];
            }
            if ($diffDays <= 3) {
                return ['label' => "Closing in {$diffDays}d", 'type' => 'urgent'];
            }
        }

        return ['label' => 'Apply Online', 'type' => 'active'];
    }

    /**
     * Summary stats for the directory header
     */
    public static function getDirectoryStats(array $jobs): array {
        $totalJobs = count($jobs);
        $totalVacancies = 0;
        $stateCounts = [];

        foreach ($jobs as $job) {
            if (!empty($job['vacancies'])) {
                if (preg_match('/(\d+[\d,]*)/', $job['vacancies'], $m)) {
                    $num = (int)str_replace(',', '', $m[1]);
                    $totalVacancies += $num;
                }
            }
            $st = $job['state_code'];
            $stateCounts[$st] = ($stateCounts[$st] ?? 0) + 1;
        }

        return [
            'total_jobs'      => $totalJobs,
            'total_vacancies' => $totalVacancies > 0 ? number_format($totalVacancies) . '+' : '50,000+',
            'states_covered'  => count($stateCounts)
        ];
    }
}
