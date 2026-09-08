<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use Throwable;

final class CircularCrawlerService
{
    private int $fetchTimeout = 10;

    /**
     * Known Notice Board / What's New sub-paths for major Indian testing commissions
     */
    private const NOTICE_BOARD_MAP = [
        'ssc.gov.in' => ['https://ssc.gov.in/notices'],
        'upsc.gov.in' => ['https://upsc.gov.in/whats-new', 'https://upsc.gov.in/examinations/active-exams'],
        'nta.ac.in' => ['https://nta.ac.in/NoticeBoardArchive'],
        'indianrailways.gov.in' => [
            'https://indianrailways.gov.in/railwayboard/view_section.jsp?lang=0&id=0,1,304,366,544,2282',
            'https://rrbcdg.gov.in',
            'https://www.rrbmumbai.gov.in'
        ],
        'natboard.edu.in' => ['https://natboard.edu.in/viewNoticeBoard.php'],
        'upsssc.gov.in' => ['https://upsssc.gov.in/AllNotices.aspx'],
        'bpsc.bih.nic.in' => ['https://www.bpsc.bih.nic.in'],
        'ibps.in' => ['https://ibps.in/index.php/all-notifications/'],
        'mcc.nic.in' => ['https://mcc.nic.in/ug-medical-counselling/']
    ];

    /**
     * Regional Examination Boards directory (e.g. 21 RRBs, SSC regional setups)
     */
    private const REGIONAL_PORTALS_MAP = [
        'rrb' => [
            'RRB Ahmedabad' => 'https://www.rrbahmedabad.gov.in',
            'RRB Ajmer' => 'https://www.rrbajmer.gov.in',
            'RRB Allahabad (Prayagraj)' => 'https://www.rrbald.gov.in',
            'RRB Bangalore' => 'https://www.rrbbnc.gov.in',
            'RRB Bhopal' => 'https://www.rrbbpl.nic.in',
            'RRB Bhubaneswar' => 'https://www.rrbbbs.gov.in',
            'RRB Bilaspur' => 'https://www.rrbbilaspur.gov.in',
            'RRB Chandigarh' => 'https://www.rrbcdg.gov.in',
            'RRB Chennai' => 'https://www.rrbchennai.gov.in',
            'RRB Gorakhpur' => 'https://www.rrbgkp.gov.in',
            'RRB Guwahati' => 'https://www.rrbguwahati.gov.in',
            'RRB Jammu' => 'https://www.rrbjammu.nic.in',
            'RRB Kolkata' => 'https://www.rrbkolkata.gov.in',
            'RRB Malda' => 'https://www.rrbmalda.gov.in',
            'RRB Mumbai' => 'https://www.rrbmumbai.gov.in',
            'RRB Muzaffarpur' => 'https://www.rrbmuzaffarpur.gov.in',
            'RRB Patna' => 'https://www.rrbpatna.gov.in',
            'RRB Ranchi' => 'https://www.rrbranchi.gov.in',
            'RRB Secunderabad' => 'https://www.rrbsecunderabad.gov.in',
            'RRB Siliguri' => 'https://www.rrbsiliguri.gov.in',
            'RRB Thiruvananthapuram' => 'https://www.rrbthiruvananthapuram.gov.in'
        ],
        'ssc' => [
            'SSC Northern Region (NR)' => 'https://sscnr.nic.in',
            'SSC Central Region (CR)' => 'https://www.ssc-cr.org',
            'SSC Eastern Region (ER)' => 'https://www.sscer.org',
            'SSC Western Region (WR)' => 'https://www.sscwr.net',
            'SSC Southern Region (SR)' => 'https://www.sscsr.gov.in',
            'SSC North Western Region (NWR)' => 'https://www.sscnwr.org',
            'SSC Madhya Pradesh Region (MPR)' => 'https://www.sscmpr.org',
            'SSC North Eastern Region (NER)' => 'https://www.sscner.org.in',
            'SSC KKR Region' => 'https://www.ssckkr.kar.nic.in'
        ]
    ];

    /**
     * Gather circular documents and regional sub-portals
     */
    public function gather(string $homepageUrl, string $authorityCode = '', bool $includeRegional = true): array
    {
        $documents = [];

        // 1. Fetch main portal text
        if (!empty($homepageUrl) && filter_var($homepageUrl, FILTER_VALIDATE_URL)) {
            $mainText = $this->fetchHtml($homepageUrl);
            if (!empty($mainText)) {
                $documents[] = ['url' => $homepageUrl, 'text' => $mainText, 'type' => 'homepage'];
            }
        }

        // 2. Resolve notice board sub-paths
        $host = parse_url($homepageUrl, PHP_URL_HOST) ?? '';
        foreach (self::NOTICE_BOARD_MAP as $knownHost => $noticeUrls) {
            if (str_contains($host, $knownHost)) {
                foreach ($noticeUrls as $noticeUrl) {
                    if ($noticeUrl === $homepageUrl) continue;
                    $noticeText = $this->fetchHtml($noticeUrl);
                    if (!empty($noticeText)) {
                        $documents[] = ['url' => $noticeUrl, 'text' => $noticeText, 'type' => 'notice_board'];
                    }
                }
                break;
            }
        }

        // 3. Resolve regional sub-portals if applicable
        $authLower = strtolower($authorityCode . ' ' . $homepageUrl);
        $regionalPortals = [];

        if (str_contains($authLower, 'rrb') || str_contains($authLower, 'railway') || str_contains($authLower, 'ntpc')) {
            $regionalPortals = self::REGIONAL_PORTALS_MAP['rrb'];
        } elseif (str_contains($authLower, 'ssc') && !str_contains($authLower, 'upsssc') && !str_contains($authLower, 'wbssc')) {
            $regionalPortals = self::REGIONAL_PORTALS_MAP['ssc'];
        }

        return [
            'documents' => $documents,
            'regional_portals' => $regionalPortals
        ];
    }

    /**
     * Get static regional portals list for prompt injection
     */
    public static function getRegionalPortalsForAuthority(string $authorityCode): array
    {
        $lower = strtolower($authorityCode);
        if (str_contains($lower, 'rrb') || str_contains($lower, 'railway') || str_contains($lower, 'ntpc')) {
            return self::REGIONAL_PORTALS_MAP['rrb'];
        }
        if (str_contains($lower, 'ssc') && !str_contains($lower, 'upsssc')) {
            return self::REGIONAL_PORTALS_MAP['ssc'];
        }
        return [];
    }

    /**
     * Fetch HTML and convert to clean text
     */
    public function fetchHtml(string $url): ?string
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->fetchTimeout,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept-Language: en-IN,en;q=0.9,hi;q=0.8']
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode < 200 || $httpCode >= 400 || empty($html)) {
                return null;
            }

            $text = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
            $text = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $text);
            $text = strip_tags($text);
            $text = preg_replace('/\s+/', ' ', $text);
            return mb_substr(trim($text), 0, 3000);
        } catch (Throwable $e) {
            Logger::warning("CircularCrawlerService failed for {$url}: " . $e->getMessage());
            return null;
        }
    }
}
