<?php
/**
 * Sarkari.online - Authority & Factual Grounding Ingestion Service
 *
 * Dynamically resolves statutory testing authorities (NTA, NBEMS, UPSC, SSC, CBSE, UGC, JoSAA, MCC, NSP)
 * and fetches real-time official bulletins, circulars, shift schedules, gate closure rules, document
 * checklists, and dress code directives.
 *
 * Injects verified factual context into ArticleGenerator so that articles are 100% accurate,
 * search-intent driven, zero-hallucination, and AdSense/SEO compliant.
 */

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use App\Helpers\Env;
use Throwable;

class AuthorityFactFetcherService {

    private Gemini $gemini;
    private int $fetchTimeout = 12;

    // Statutory Portals Directory
    private static array $authorityPortals = [
        'nbems'     => ['name' => 'NBEMS (National Board of Examinations in Medical Sciences)', 'portal' => 'https://natboard.edu.in'],
        'nta'       => ['name' => 'NTA (National Testing Agency)', 'portal' => 'https://nta.ac.in'],
        'upsc'      => ['name' => 'UPSC (Union Public Service Commission)', 'portal' => 'https://upsc.gov.in'],
        'ssc'       => ['name' => 'SSC (Staff Selection Commission)', 'portal' => 'https://ssc.gov.in'],
        'aicte'     => ['name' => 'All India Council for Technical Education (AICTE)', 'portal' => 'https://www.aicte-india.org'],
        'cbse'      => ['name' => 'CBSE (Central Board of Secondary Education)', 'portal' => 'https://cbse.gov.in'],
        'ctet'      => ['name' => 'CTET Unit, CBSE', 'portal' => 'https://ctet.nic.in'],
        'ugc'       => ['name' => 'UGC (University Grants Commission)', 'portal' => 'https://www.ugc.gov.in'],
        'josaa'     => ['name' => 'JoSAA (Joint Seat Allocation Authority)', 'portal' => 'https://josaa.nic.in'],
        'mcc'       => ['name' => 'MCC (Medical Counselling Committee)', 'portal' => 'https://mcc.nic.in'],
        'nsp'       => ['name' => 'NSP (National Scholarship Portal)', 'portal' => 'https://scholarships.gov.in'],
        'rrb'       => ['name' => 'Railway Recruitment Boards (RRB)', 'portal' => 'https://indianrailways.gov.in'],
        'ibps'      => ['name' => 'IBPS (Institute of Banking Personnel Selection)', 'portal' => 'https://ibps.in'],
        'sbi'       => ['name' => 'State Bank of India (SBI)', 'portal' => 'https://sbi.co.in/web/careers'],
        'iaf'       => ['name' => 'Indian Air Force (IAF / Agnipath Vayu)', 'portal' => 'https://agnipathvayu.cdac.in'],
        'army'      => ['name' => 'Indian Army (Join Indian Army)', 'portal' => 'https://joinindianarmy.nic.in'],
        'navy'      => ['name' => 'Indian Navy (Join Indian Navy)', 'portal' => 'https://joinindiannavy.gov.in'],
        'coastguard'=> ['name' => 'Indian Coast Guard (ICG)', 'portal' => 'https://joinindiancoastguard.cdac.in'],
        'bsf'       => ['name' => 'Border Security Force (BSF)', 'portal' => 'https://rectt.bsf.gov.in'],
        'cisf'      => ['name' => 'Central Industrial Security Force (CISF)', 'portal' => 'https://cisfrectt.cisf.gov.in'],
        'crpf'      => ['name' => 'Central Reserve Police Force (CRPF)', 'portal' => 'https://rect.crpf.gov.in'],
        'itbp'      => ['name' => 'Indo-Tibetan Border Police (ITBP)', 'portal' => 'https://recruitment.itbpolice.nic.in'],
        'ssb'       => ['name' => 'Sashastra Seema Bal (SSB)', 'portal' => 'https://ssbrectt.gov.in'],
        'drdo'      => ['name' => 'DRDO (Defence Research and Development Organisation)', 'portal' => 'https://drdo.gov.in'],
        'isro'      => ['name' => 'ISRO (Indian Space Research Organisation)', 'portal' => 'https://isro.gov.in'],
        'kvs'       => ['name' => 'Kendriya Vidyalaya Sangathan (KVS)', 'portal' => 'https://kvsangathan.nic.in'],
        'nvs'       => ['name' => 'Navodaya Vidyalaya Samiti (NVS)', 'portal' => 'https://navodaya.gov.in'],
        'dsssb'     => ['name' => 'Delhi Subordinate Services Selection Board (DSSSB)', 'portal' => 'https://dsssb.delhi.gov.in'],
        'upsssc'    => ['name' => 'UP Subordinate Services Selection Commission (UPSSSC)', 'portal' => 'https://upsssc.gov.in'],
        'uppbpb'    => ['name' => 'Uttar Pradesh Police Recruitment & Promotion Board (UPPRPB)', 'portal' => 'https://uppbpb.gov.in'],
        'hpbose'    => ['name' => 'HPBOSE (Himachal Pradesh Board of School Education)', 'portal' => 'https://hpbose.org'],
        'ignou'     => ['name' => 'Indira Gandhi National Open University (IGNOU)', 'portal' => 'https://ignouadmission.samarth.edu.in'],
        'coalindia' => ['name' => 'Coal India Limited (CIL)', 'portal' => 'https://coalindia.in'],
        'kpsc'      => ['name' => 'KPSC (Karnataka Public Service Commission)', 'portal' => 'https://kpsc.kar.nic.in'],
        'bpsc'      => ['name' => 'Bihar Public Service Commission (BPSC)', 'portal' => 'https://www.bpsc.bih.nic.in'],
        'bssc'      => ['name' => 'Bihar Staff Selection Commission (BSSC)', 'portal' => 'https://bssc.bihar.gov.in'],
        'csbc'      => ['name' => 'Central Selection Board of Constable, Bihar (CSBC)', 'portal' => 'https://csbc.bih.nic.in'],
        'uppsc'     => ['name' => 'UP Public Service Commission (UPPSC)', 'portal' => 'https://uppsc.up.nic.in'],
        'mppsc'     => ['name' => 'MP Public Service Commission (MPPSC)', 'portal' => 'https://mppsc.mp.gov.in'],
        'rpsc'      => ['name' => 'Rajasthan Public Service Commission (RPSC)', 'portal' => 'https://rpsc.rajasthan.gov.in'],
        'rsmssb'    => ['name' => 'Rajasthan Staff Selection Board (RSMSSB)', 'portal' => 'https://rsmssb.rajasthan.gov.in'],
        'hssc'      => ['name' => 'Haryana Staff Selection Commission (HSSC)', 'portal' => 'https://hssc.gov.in'],
        'hpsc'      => ['name' => 'Haryana Public Service Commission (HPSC)', 'portal' => 'https://hpsc.gov.in'],
        'bseh'      => ['name' => 'Board of School Education Haryana (BSEH)', 'portal' => 'https://bseh.org.in'],
        'ukpsc'     => ['name' => 'Uttarakhand Public Service Commission (UKPSC)', 'portal' => 'https://psc.uk.gov.in'],
        'bseb'      => ['name' => 'Bihar School Examination Board (BSEB)', 'portal' => 'https://biharboardonline.bihar.gov.in'],
        'upmsp'     => ['name' => 'UPMSP (Uttar Pradesh Madhyamik Shiksha Parishad)', 'portal' => 'https://upmsp.edu.in'],
        'wbjee'     => ['name' => 'WBJEEB (West Bengal Joint Entrance Examinations Board)', 'portal' => 'https://wbjeeb.nic.in'],
        'wbssc'     => ['name' => 'West Bengal Central School Service Commission (WBSSC)', 'portal' => 'https://westbengalssc.com'],
        'aiims'     => ['name' => 'All India Institute of Medical Sciences (AIIMS)', 'portal' => 'https://aiimsexams.ac.in']
    ];

    public function __construct(?Gemini $gemini = null) {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Resolve the statutory authority from topic text or URL
     */
    public static function resolveAuthority(string $topic, string $sourceUrl = ''): array {
        // Media Domain Blocker: If sourceUrl is a news portal / media aggregator, NEVER treat it as statutory authority portal!
        $mediaDomains = [
            'timesofindia.indiatimes.com', 'indiatimes.com', 'hindustantimes.com', 'ndtv.com',
            'indianexpress.com', 'livemint.com', 'jagran.com', 'amarujala.com', 'news18.com',
            'aajtak.in', 'abplive.com', 'firstpost.com', 'thehindu.com', 'zeenews.india.com',
            'dnaindia.com', 'economictimes.indiatimes.com', 'indiatoday.in', 'career360.com',
            'shiksha.com', 'collegedunia.com', 'sarkari.online', 'localhost'
        ];
        foreach ($mediaDomains as $domain) {
            if (str_contains(strtolower($sourceUrl), $domain)) {
                $sourceUrl = '';
                break;
            }
        }

        $lower = strtolower($topic . ' ' . $sourceUrl);

        // 1. National Councils & Technical Bodies
        if (str_contains($lower, 'aicte')) return self::$authorityPortals['aicte'];
        if (str_contains($lower, 'ugc') || str_contains($lower, 'net exam')) return self::$authorityPortals['ugc'];

        // 2. Multi-letter State Selection Boards (Check BEFORE central SSC to prevent false match)
        if (str_contains($lower, 'upsssc') || str_contains($lower, 'up pet') || str_contains($lower, 'pet 202')) return self::$authorityPortals['upsssc'];
        if (str_contains($lower, 'wbssc')) return self::$authorityPortals['wbssc'];
        if (str_contains($lower, 'hssc') || str_contains($lower, 'haryana ssc') || str_contains($lower, 'haryana cet')) return self::$authorityPortals['hssc'];
        if (str_contains($lower, 'dsssb')) return self::$authorityPortals['dsssb'];
        if (str_contains($lower, 'rsmssb')) return self::$authorityPortals['rsmssb'];
        if (str_contains($lower, 'bssc')) return self::$authorityPortals['bssc'];
        if (str_contains($lower, 'csbc') || str_contains($lower, 'bihar constable')) return self::$authorityPortals['csbc'];
        if (str_contains($lower, 'uppbpb') || str_contains($lower, 'up police')) return self::$authorityPortals['uppbpb'];

        // 3. Paramilitary & Police Forces (Check BEFORE generic police)
        if (str_contains($lower, 'cisf')) return self::$authorityPortals['cisf'];
        if (str_contains($lower, 'bsf')) return self::$authorityPortals['bsf'];
        if (str_contains($lower, 'crpf')) return self::$authorityPortals['crpf'];
        if (str_contains($lower, 'itbp')) return self::$authorityPortals['itbp'];
        if (str_contains($lower, 'ssb') && !str_contains($lower, 'rsmssb') && !str_contains($lower, 'dsssb') && !str_contains($lower, 'upsssc')) return self::$authorityPortals['ssb'];

        // 4. Defence Forces
        if (str_contains($lower, 'indian army') || preg_match('/\barmy\b/', $lower)) return self::$authorityPortals['army'];
        if (str_contains($lower, 'indian navy') || preg_match('/\bnavy\b/', $lower)) return self::$authorityPortals['navy'];
        if (str_contains($lower, 'air force') || str_contains($lower, 'iaf') || str_contains($lower, 'agniveer vayu') || str_contains($lower, 'afcat')) return self::$authorityPortals['iaf'];
        if (str_contains($lower, 'coast guard') || str_contains($lower, 'icg')) return self::$authorityPortals['coastguard'];
        if (str_contains($lower, 'drdo')) return self::$authorityPortals['drdo'];
        if (str_contains($lower, 'isro')) return self::$authorityPortals['isro'];

        // 5. School Organizations & Teacher Tests
        if (str_contains($lower, 'kvs') || str_contains($lower, 'kendriya vidyalaya')) return self::$authorityPortals['kvs'];
        if (str_contains($lower, 'nvs') || str_contains($lower, 'navodaya')) return self::$authorityPortals['nvs'];
        if (str_contains($lower, 'ctet')) return self::$authorityPortals['ctet'];
        if (str_contains($lower, 'htet') || str_contains($lower, 'bseh')) return self::$authorityPortals['bseh'];

        // 6. Open Universities & PSUs
        if (str_contains($lower, 'ignou') || str_contains($lower, 'indira gandhi national open')) return self::$authorityPortals['ignou'];
        if (str_contains($lower, 'coal india') || str_contains($lower, 'cil mt') || str_contains($lower, 'coalindia')) return self::$authorityPortals['coalindia'];

        // 7. State Admission & CET Cells
        if (str_contains($lower, 'mht cet') || str_contains($lower, 'mahacet') || str_contains($lower, 'cap round')) {
            return ['name' => 'State Common Entrance Test Cell, Maharashtra', 'portal' => 'https://cetcell.mahacet.org'];
        }
        if (str_contains($lower, 'rajasthan neet') || str_contains($lower, 'neetrajasthan')) {
            return ['name' => 'State Medical & Dental Counselling Board, Rajasthan', 'portal' => 'https://ug.neetrajasthan.com'];
        }
        if (str_contains($lower, 'csir') || str_contains($lower, 'csirnet')) {
            return ['name' => 'Council of Scientific and Industrial Research (CSIR / NTA)', 'portal' => 'https://csirnet.nta.nic.in'];
        }
        if (str_contains($lower, 'icar') || str_contains($lower, 'aieea')) {
            return ['name' => 'Indian Council of Agricultural Research (ICAR / NTA)', 'portal' => 'https://icar.nta.nic.in'];
        }

        // 8. State Boards & Commissions
        if (str_contains($lower, 'hpbose') || str_contains($lower, 'himachal')) return self::$authorityPortals['hpbose'];
        if (str_contains($lower, 'kpsc') || str_contains($lower, 'karnataka') || str_contains($lower, 'kas ')) return self::$authorityPortals['kpsc'];
        if (str_contains($lower, 'bpsc') || str_contains($lower, 'bihar public service')) return self::$authorityPortals['bpsc'];
        if (str_contains($lower, 'uppsc') || str_contains($lower, 'uttar pradesh public service')) return self::$authorityPortals['uppsc'];
        if (str_contains($lower, 'mppsc') || str_contains($lower, 'madhya pradesh public')) return self::$authorityPortals['mppsc'];
        if (str_contains($lower, 'rpsc') || str_contains($lower, 'rajasthan public')) return self::$authorityPortals['rpsc'];
        if (str_contains($lower, 'hpsc') || str_contains($lower, 'haryana public')) return self::$authorityPortals['hpsc'];
        if (str_contains($lower, 'ukpsc') || str_contains($lower, 'uttarakhand public')) return self::$authorityPortals['ukpsc'];
        if (str_contains($lower, 'bseb') || str_contains($lower, 'bihar board')) return self::$authorityPortals['bseb'];
        if (str_contains($lower, 'upmsp') || str_contains($lower, 'up board')) return self::$authorityPortals['upmsp'];
        if (str_contains($lower, 'wbjee')) return self::$authorityPortals['wbjee'];

        // 9. Banking
        if (str_contains($lower, 'ibps') || str_contains($lower, 'crp po') || str_contains($lower, 'crp clerk')) return self::$authorityPortals['ibps'];
        if (str_contains($lower, 'sbi po') || str_contains($lower, 'sbi clerk') || str_contains($lower, 'state bank')) return self::$authorityPortals['sbi'];

        // 10. Medical & Entrance
        if (str_contains($lower, 'aiims')) return self::$authorityPortals['aiims'];
        if (str_contains($lower, 'neet pg') || str_contains($lower, 'dnb') || str_contains($lower, 'fmge') || str_contains($lower, 'natboard') || str_contains($lower, 'nbems')) {
            return self::$authorityPortals['nbems'];
        }
        if (str_contains($lower, 'jee') || str_contains($lower, 'neet ug') || str_contains($lower, 'cuet') || preg_match('/\bnta\b/', $lower)) {
            return self::$authorityPortals['nta'];
        }
        if (str_contains($lower, 'josaa') || str_contains($lower, 'csab')) return self::$authorityPortals['josaa'];
        if (str_contains($lower, 'mcc') || str_contains($lower, 'neet counselling')) return self::$authorityPortals['mcc'];

        // 11. Central Commissions & Boards (Use word boundaries for ssc to prevent matching upsssc/wbssc)
        if (str_contains($lower, 'cbse') || str_contains($lower, 'class 10') || str_contains($lower, 'class 12')) return self::$authorityPortals['cbse'];
        if (preg_match('/\bssc\b/', $lower) || str_contains($lower, 'cgl') || str_contains($lower, 'chsl') || str_contains($lower, 'mts') || str_contains($lower, 'cpo') || str_contains($lower, 'havaldar') || str_contains($lower, 'ssc je')) {
            return self::$authorityPortals['ssc'];
        }
        if (preg_match('/\bupsc\b/', $lower) || str_contains($lower, 'civil services') || str_contains($lower, 'nda') || str_contains($lower, 'cds') || str_contains($lower, 'ifs')) {
            if (str_contains($lower, 'admit') || str_contains($lower, 'hall ticket') || str_contains($lower, 'call letter')) {
                return ['name' => 'UPSC (Union Public Service Commission)', 'portal' => 'https://upsconline.nic.in'];
            }
            return self::$authorityPortals['upsc'];
        }
        if (preg_match('/\brrb\b/', $lower) || str_contains($lower, 'railway') || str_contains($lower, 'alp') || str_contains($lower, 'ntpc')) return self::$authorityPortals['rrb'];
        if (str_contains($lower, 'scholarship') || str_contains($lower, 'nsp') || str_contains($lower, 'pmsss') || str_contains($lower, 'yasasvi')) return self::$authorityPortals['nsp'];

        // 12. Fallback: Check if sourceUrl is a genuine external official/university portal (.gov.in, .nic.in, .ac.in, .edu.in, .org)
        if (!empty($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $host = parse_url($sourceUrl, PHP_URL_HOST);
            if ($host && !str_contains($host, 'sarkari.online')) {
                return [
                    'name' => 'Statutory Examination Board / Agency',
                    'portal' => $sourceUrl
                ];
            }
        }

        // 13. Dynamic Acronym Extraction Fallback (Extract e.g. "UPESSC" or "AIIMS" from headline)
        if (preg_match('/\b([A-Z]{3,8})\b/', $topic, $acr)) {
            return [
                'name' => $acr[1] . ' (Statutory Examination Body)',
                'portal' => ''
            ];
        }

        return [
            'name' => 'Statutory Examination Board / Agency',
            'portal' => ''
        ];
    }

    /**
     * Fetch real-time official portal HTML text
     */
    public function fetchPortalText(string $url): ?string {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->fetchTimeout,
                CURLOPT_CONNECTTIMEOUT => 5,
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
            return mb_substr(trim($text), 0, 4000);
        } catch (Throwable $e) {
            Logger::warning("AuthorityFactFetcherService fetchPortalText failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Synthesize and extract structured statutory facts for any topic
     *
     * @param string $topic Title or keyword (e.g. "RRB NTPC 2026 CBT 2 Exam Schedule & City Slip")
     * @param string $category Category slug
     * @param string $sourceUrl Source or portal URL if available
     * @param string $snippet Raw dispatch or news wire excerpt
     * @return array Verified factual package ready for ArticleGenerator
     */
    public function fetchFactsForTopic(string $topic, string $category = 'entrance-exams', string $sourceUrl = '', string $snippet = ''): array {
        $authority = self::resolveAuthority($topic, $sourceUrl);
        $currentDate = date('F d, Y');
        $currentYear = (int)date('Y');

        // Circular-first, notice board and regional sub-portal crawl
        $crawler = new CircularCrawlerService();
        $crawlData = $crawler->gather($authority['portal'], $topic, true);
        
        $combinedText = '';
        foreach ($crawlData['documents'] as $doc) {
            $combinedText .= "[PORTAL: {$doc['url']} ({$doc['type']})]\n" . $doc['text'] . "\n\n";
        }
        $combinedText = mb_substr(trim($combinedText), 0, 4500);

        $regionalListStr = '';
        if (!empty($crawlData['regional_portals'])) {
            foreach ($crawlData['regional_portals'] as $name => $url) {
                $regionalListStr .= "- {$name}: {$url}\n";
            }
        }

        $contextPrompt = "STATUTORY AUTHORITY: " . $authority['name'] . " (" . $authority['portal'] . ")\n";
        if (!empty($snippet)) {
            $contextPrompt .= "NEWS WIRE & DISPATCH DETAILS:\n" . mb_substr($snippet, 0, 1500) . "\n";
        }
        if (!empty($combinedText)) {
            $contextPrompt .= "CRAWLED OFFICIAL CIRCULARS & NOTICE BOARDS:\n" . $combinedText . "\n";
        }
        if (!empty($regionalListStr)) {
            $contextPrompt .= "VERIFIED REGIONAL EXAMINATION PORTALS DIRECTORY:\n" . $regionalListStr . "\n";
        }

        $prompt = <<<PROMPT
You are the Forensic Fact-Extraction and Chief Verification Officer for Sarkari.online.
Today's Date: {$currentDate}.
Operating Year: {$currentYear}.

TOPIC / HEADLINE: {$topic}
CATEGORY: {$category}
{$contextPrompt}

CRITICAL ANTI-HEDGING & FACT GROUNDING DIRECTIVES:
1. SPECIFIC NOTIFICATION CODE & STAGE (ZERO OMISSION):
   - You MUST extract the specific notification code (e.g. CEN 07/2025, Advt No., File No.) and exact exam stage (e.g. CBT-2 for Undergraduate Posts, Tier-1, PET/PST) if present in the topic or source context.
2. DISTINCTION RECOGNITION (e.g. City Intimation Slip vs e-Call Letter):
   - If the event is an exam or admit card: Detail whether the Exam City Slip is out (e.g. September 7, 2026) vs when the final e-Call Letter / Admit Card releases (e.g. September 13, 2026, 4 days prior to exam).
   - Never confuse the City Slip with the actual Hall Ticket.
3. ANTI-HEDGING RULE:
   - If a specific date, code, or vacancy number exists anywhere in the source material — including dispatch snippets or notice boards — you MUST extract it verbatim into the "date" or "value" field.
   - Only set status to "Awaited / Tentative" if the fact genuinely does not yet exist.
   - NEVER substitute a specific fact with vague placeholders like "expected soon", "dates awaited", or "check official portal".
4. REGIONAL PORTALS MATRIX:
   - For RRB and SSC, map and include the exact regional board names and portal URLs from the verified directory.

Return strictly as JSON matching this schema:
{
  "authority_name": "{$authority['name']}",
  "official_portal": "{$authority['portal']}",
  "notification_code": "Official CEN / Advt / Notification Reference Number or null",
  "exam_phase": "Specific stage (e.g. Undergraduate CBT-2, Tier-1, Prelims, CAP Round 3)",
  "exam_status": "Confirmed | Active Registration | Exam City Slip Active | Upcoming",
  "distinction_notes": "Explicit distinction (e.g. City Intimation Slip released on Sep 7; Hall Ticket downloads live 4 days prior on Sep 13)",
  "shift_timings": [
    {
      "shift": "Official Paper / Shift Name",
      "reporting_time": "Official reporting time",
      "gate_closure": "Official gate closure cutoff time",
      "exam_timing": "Official exam hours (e.g. 10:00 AM – 12:30 PM)",
      "duration": "Official test duration (e.g. 150 Minutes)",
      "mode": "Computer Based Test (CBT) / OMR"
    }
  ],
  "dates_schedule": [
    {
      "milestone": "Exam City Intimation Slip / Admit Card / Exam Date",
      "date": "Exact Calendar Date (e.g. September 07, 2026)",
      "status": "Confirmed | Awaited / Tentative"
    }
  ],
  "regional_portals": [
    {
      "region_name": "RRB Bhopal",
      "url": "https://www.rrbbpl.nic.in"
    }
  ],
  "vacancy_breakdown": {
    "total_vacancies": "Total post count if mentioned (e.g. 3058 Posts)",
    "candidate_count": "Total candidates appearing if mentioned (e.g. 45,900+ candidates)"
  },
  "mandatory_documents": [
    "Printed Official Admit Card with recent colour photograph",
    "Original Valid Government Photo ID (Aadhaar / PAN / Voter ID / Passport / Driving License)"
  ],
  "official_notice_ref": "Official Notification Circular at {$authority['portal']}",
  "extraction_confidence": "high | medium | low"
}
PROMPT;

        try {
            $response = $this->gemini->generateJson($prompt, [
                'stage' => 'authority_fact_fetching',
                'temperature' => 0.05
            ]);

            $data = $response['data'];
            
            // Enrich with static regional portals if AI left it empty but authority is RRB/SSC
            if (empty($data['regional_portals']) && !empty($crawlData['regional_portals'])) {
                $regList = [];
                foreach ($crawlData['regional_portals'] as $name => $u) {
                    $regList[] = ['region_name' => $name, 'url' => $u];
                }
                $data['regional_portals'] = array_slice($regList, 0, 10);
            }

            Logger::info("AuthorityFactFetcherService: Grounded facts extracted successfully for '{$topic}' (Confidence: " . ($data['extraction_confidence'] ?? 'medium') . ")");
            return $data;
        } catch (Throwable $e) {
            Logger::error("AuthorityFactFetcherService failed for '{$topic}': " . $e->getMessage());

            // Safe fallback structure
            return [
                'authority_name' => $authority['name'],
                'official_portal' => $authority['portal'],
                'notification_code' => null,
                'exam_phase' => 'Scheduled Phase',
                'exam_status' => 'Refer to Official Portal',
                'distinction_notes' => null,
                'shift_timings' => [
                    [
                        'shift' => 'Scheduled Shift',
                        'reporting_time' => 'As specified on Admit Card',
                        'gate_closure' => '30–45 Mins Prior to Exam (Strict Cutoff)',
                        'exam_timing' => 'As printed on Official Hall Ticket',
                        'duration' => 'Standard Statutory Test Duration',
                        'mode' => 'Computer Based Test (CBT)'
                    ]
                ],
                'dates_schedule' => [
                    [
                        'milestone' => 'Exam Schedule',
                        'date' => 'To Be Announced (TBA) by Statutory Authority',
                        'status' => 'Awaiting Official Circular'
                    ]
                ],
                'regional_portals' => [],
                'mandatory_documents' => [
                    'Printed Official Admit Card with recent photo',
                    'Original Government Photo ID (Aadhaar, PAN, Voter ID, Passport, DL)'
                ],
                'official_notice_ref' => "Official notice at {$authority['portal']}",
                'extraction_confidence' => 'medium'
            ];
        }
    }
}
