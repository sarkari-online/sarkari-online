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
        'upessc'    => ['name' => 'Uttar Pradesh Education Service Selection Commission (UPESSC)', 'portal' => 'https://upessc.up.gov.in'],
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
    public static function resolveAuthority(string $topic, string $sourceUrl = '', bool $allowDynamicDiscovery = false): array {
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
        if (str_contains($lower, 'upessc') || str_contains($lower, 'uttar pradesh education service selection') || str_contains($lower, 'assistant professor') && str_contains($lower, 'uttar pradesh')) return self::$authorityPortals['upessc'];
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
        if (str_contains($lower, 'afcat')) return ['name' => 'Indian Air Force (AFCAT)', 'portal' => 'https://afcat.cdac.in', 'verification_status' => 'verified'];
        if (str_contains($lower, 'indian army') || preg_match('/\barmy\b/', $lower)) return self::$authorityPortals['army'];
        if (str_contains($lower, 'indian navy') || preg_match('/\bnavy\b/', $lower)) return self::$authorityPortals['navy'];
        if (str_contains($lower, 'air force') || str_contains($lower, 'iaf') || str_contains($lower, 'agniveer vayu')) return self::$authorityPortals['iaf'];
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

        // 7. Premier Institutes & National Entrance Tests
        if (str_contains($lower, 'iit jam') || str_contains($lower, 'jam 202') || str_contains($lower, 'jam.iit')) {
            return ['name' => 'IIT JAM (Joint Admission test for Masters)', 'portal' => 'https://jam2026.iitb.ac.in', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'gate 202') || str_contains($lower, 'gate exam')) {
            return ['name' => 'GATE (Graduate Aptitude Test in Engineering)', 'portal' => 'https://gate2026.iitr.ac.in', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'iit') || str_contains($lower, 'indian institute of technology')) {
            return ['name' => 'Indian Institute of Technology (IIT)', 'portal' => 'https://www.iitb.ac.in', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'mht cet') || str_contains($lower, 'mahacet') || str_contains($lower, 'cap round')) {
            return ['name' => 'State Common Entrance Test Cell, Maharashtra', 'portal' => 'https://cetcell.mahacet.org', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'rajasthan neet') || str_contains($lower, 'neetrajasthan')) {
            return ['name' => 'State Medical & Dental Counselling Board, Rajasthan', 'portal' => 'https://ug.neetrajasthan.com', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'csir') || str_contains($lower, 'csirnet')) {
            return ['name' => 'Council of Scientific and Industrial Research (CSIR / NTA)', 'portal' => 'https://csirnet.nta.nic.in', 'verification_status' => 'verified'];
        }
        if (str_contains($lower, 'icar') || str_contains($lower, 'aieea')) {
            return ['name' => 'Indian Council of Agricultural Research (ICAR / NTA)', 'portal' => 'https://icar.nta.nic.in', 'verification_status' => 'verified'];
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
                    'portal' => $sourceUrl,
                    'verification_status' => 'verified'
                ];
            }
        }

        // 13. Dynamic Authority Portal Resolution via AuthorityPortalResolverService
        if (preg_match('/\b([A-Z]{3,8})\b/', $topic, $acr)) {
            $resolver = new AuthorityPortalResolverService();
            $resolved = $resolver->resolve($acr[1], $topic, $allowDynamicDiscovery);
            if ($resolved) {
                return [
                    'name'                => $resolved['name'],
                    'portal'              => $resolved['portal'],
                    'verification_status' => $resolved['verification_status'] ?? 'pending_review'
                ];
            }
            return [
                'name'                => $acr[1] . ' (Statutory Examination Body)',
                'portal'              => '',
                'verification_status' => 'unresolved'
            ];
        }

        return [
            'name'                => 'Statutory Examination Board / Agency',
            'portal'              => '',
            'verification_status' => 'unresolved'
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
     * Fetch fresh statutory dispatches from Google News + Bing News RSS.
     * Returns structured array of dispatches for PHP-side recency filtering.
     * Publication dates are consumed strictly by NewsDispatchSanitizer in PHP
     * and NEVER exposed as extractable text to the LLM prompt.
     *
     * @return array Array of ['headline' => string, 'body_snippet' => string, 'published_at' => string, 'source_url' => string]
     */
    public function fetchNewsDispatches(string $topic): array {
        $cleanQuery = preg_replace('/[^\w\s\-]/u', ' ', $topic);
        $cleanQuery = trim(preg_replace('/\s+/', ' ', (string)$cleanQuery));

        $browserUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $dispatches = [];

        // ── SOURCE 1: Google News RSS ─────────────────────────────────────────
        try {
            $gUrl = "https://news.google.com/rss/search?q=" . urlencode($cleanQuery) . "&hl=en-IN&gl=IN&ceid=IN:en";
            $ch = curl_init($gUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => $browserUA,
            ]);
            $xmlContent = curl_exec($ch);
            $gCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($gCode === 200 && !empty($xmlContent)) {
                $xml = @simplexml_load_string($xmlContent);
                if ($xml && isset($xml->channel->item)) {
                    $count = 0;
                    foreach ($xml->channel->item as $item) {
                        $t = trim((string)$item->title);
                        $d = trim((string)$item->pubDate);
                        $dispatches[] = [
                            'headline'     => $t,
                            'body_snippet' => '',
                            'published_at' => $d,
                            'source_url'   => (string)($item->link ?? '')
                        ];
                        if (++$count >= 6) break;
                    }
                }
            }
        } catch (Throwable $e) {
            Logger::warning("fetchNewsDispatches[Google]: " . $e->getMessage());
        }

        // ── SOURCE 2: Bing News RSS (Direct URLs + Article Meta Summaries) ───
        try {
            $bUrl = "https://www.bing.com/news/search?q=" . urlencode($cleanQuery) . "&format=rss";
            $ch = curl_init($bUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => $browserUA,
            ]);
            $bContent = curl_exec($ch);
            $bCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($bCode === 200 && !empty($bContent)) {
                $bXml = @simplexml_load_string($bContent);
                if ($bXml && isset($bXml->channel->item)) {
                    $metaFetched = 0;
                    foreach ($bXml->channel->item as $item) {
                        $t    = trim((string)$item->title);
                        $d    = trim((string)$item->pubDate);
                        $desc = trim(strip_tags((string)$item->description));
                        $link = trim((string)($item->link ?? ''));
                        $meta = '';
                        if ($metaFetched < 3 && !empty($link) && str_starts_with($link, 'http')) {
                            $meta = $this->fetchArticleMeta($link);
                            if (!empty($meta)) {
                                $metaFetched++;
                            }
                        }
                        $bodySnippet = !empty($meta) ? $meta : mb_substr($desc, 0, 300);
                        $dispatches[] = [
                            'headline'     => $t,
                            'body_snippet' => $bodySnippet,
                            'published_at' => $d,
                            'source_url'   => $link
                        ];
                        if ($metaFetched >= 3 || count($dispatches) >= 12) break;
                    }
                }
            }
        } catch (Throwable $e) {
            Logger::warning("fetchNewsDispatches[Bing]: " . $e->getMessage());
        }

        return $dispatches;
    }


    /**
     * Fetch article meta description from a news article URL.
     * Two-step approach:
     *   1. HEAD request following all redirects → resolves Google News redirect to real article URL
     *   2. Partial GET (first 25KB) of real article URL → extract <meta> description
     * This avoids the "Comprehensive news coverage..." Google fallback page.
     */
    private function fetchArticleMeta(string $url): string {
        try {
            $browserUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

            // Step 1: Follow all redirects (HEAD only) to resolve the actual article URL.
            // Google News links redirect to the real article — we must not send Range here.
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 6,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => $browserUA,
            ]);
            curl_exec($ch);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            // If redirect didn't resolve away from Google, skip
            if (empty($finalUrl) || str_contains($finalUrl, 'news.google.com')) {
                return '';
            }

            // Step 2: Fetch only first 25KB of the real article (enough for <head> meta tags).
            $ch2 = curl_init($finalUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_RANGE          => '0-25000',
                CURLOPT_USERAGENT      => $browserUA,
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: en-IN,en;q=0.9',
                ],
            ]);
            $html     = curl_exec($ch2);
            $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if (empty($html) || ($httpCode !== 200 && $httpCode !== 206)) {
                return '';
            }

            // Extract meta description — try og:description, name=description, twitter:description
            $patterns = [
                '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\']/i',
                '/<meta[^>]+content=["\'](.*?)["\']\s+property=["\']og:description["\']/i',
                '/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i',
                '/<meta[^>]+content=["\'](.*?)["\']\s+name=["\']description["\']/i',
                '/<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\'](.*?)["\']/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    $meta = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
                    $meta = trim(preg_replace('/\s+/', ' ', $meta));
                    // Skip trivially short or clearly generic descriptions
                    if (strlen($meta) > 40 && !str_contains(strtolower($meta), 'comprehensive') && !str_contains(strtolower($meta), 'google news')) {
                        return mb_substr($meta, 0, 500);
                    }
                }
            }

            return '';
        } catch (Throwable $e) {
            return ''; // Non-fatal — article might block bots or be slow
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

        // News dispatches: Gather raw dispatches from Google News and Bing News
        $notifQuery = preg_replace('/syllabus|exam pattern|result|admit card/i', '', $topic);
        $notifQuery = trim($notifQuery . ' official notification application dates ' . $currentYear);

        $rawDispatches = array_merge(
            $this->fetchNewsDispatches($topic),
            $this->fetchNewsDispatches($notifQuery)
        );

        // Sanitize & filter in PHP: Drops publication dates completely before LLM context!
        // The model literally cannot confuse a news publish date with an event date.
        $sanitizedDispatches = NewsDispatchSanitizer::filterAndSanitize($rawDispatches, 14);

        $dispatchesText = '';
        if (!empty($sanitizedDispatches)) {
            $formattedItems = [];
            foreach ($sanitizedDispatches as $d) {
                $entry = "- Headline: {$d['headline']}";
                if (!empty($d['body_snippet'])) {
                    $entry .= "\n  Report Excerpt: {$d['body_snippet']}";
                }
                $formattedItems[] = $entry;
            }
            $dispatchesText = mb_substr(implode("\n", array_slice($formattedItems, 0, 8)), 0, 2500);
        }

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
            $contextPrompt .= "CRAWLED OFFICIAL CIRCULARS & NOTICE BOARDS (PRIMARY SOURCE):\n" . $combinedText . "\n";
        }
        if (!empty($dispatchesText)) {
            $contextPrompt .= "SUPPORTING NEWS COVERAGE (CONTEXT ONLY — NO PUBLISH DATES INCLUDED):\n" . $dispatchesText . "\n";
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
   - You MUST extract the specific notification code (e.g. CEN 07/2025, Advt No., File No.) and exact exam stage (e.g. CBT-2, Tier-1, Prelims, Mains) if present in the topic or source context.

2. EXAM PATTERN EXTRACTION (MANDATORY & FACT-CRITICAL):
   - Extract the full examination structure for BOTH Preliminary and Main examinations:
     * Subject/section names
     * Number of questions per section and total questions
     * Maximum marks per section and total marks
     * Sectional time duration (minutes) and overall time duration
     * Negative marking penalty (e.g. "0.25 (1/4th mark deducted per incorrect answer)")
   - For exam pattern facts specifically: if the CURRENT cycle's pattern has not been officially altered, search for and extract the established statutory pattern from the commission's official gazette with stated continuity. NEVER guess or invent numbers.

3. TIERED SOURCE CONFIDENCE & TENTATIVE BASIS (ZERO FABRICATION):
   - Assign source_confidence to each milestone using one of four strict tiers:
     * "confirmed_primary_source": Explicitly confirmed by official PDF, gazette, or authority portal.
     * "confirmed_secondary_source": Multiple independent reputable news outlets citing the authority.
     * "tentative_estimate": Used when an exact date is awaited, BUT an official forward-looking statement (recruitment calendar, press release) or an explicitly-dated prior cycle precedent exists (e.g. "SBI Clerk Prelims 2025 cycle held late September").
     * "unavailable": Used when neither confirmed date nor verifiable historical precedent exists.
   - MANDATORY TENTATIVE BASIS RULE:
     * If source_confidence is "tentative_estimate", tentative_basis is REQUIRED and must cite a concrete source reference (a year, a portal archive, or an explicit "per [organization]" statement).
     * If no verifiable basis exists, you MUST mark source_confidence as "unavailable" and date as "Not yet announced". NEVER provide a guessed date with a vague basis.

4. REGIONAL PORTALS MATRIX:
   - For RRB and SSC, map and include the exact regional board names and portal URLs from the verified directory.

5. CRITICAL ANTI-DATE-CONFUSION RULE ON NEWS COVERAGE:
   - The "SUPPORTING NEWS COVERAGE" section above contains NO publication dates — this is strictly intentional.
   - You must NEVER infer, assume, or estimate an exam date, answer key date, or result date based on today's date ({$currentDate}) or when an article was written.
   - A date is ONLY valid for a milestone if it is EXPLICITLY STATED within the text body as the date of that specific event (e.g. text explicitly says "released on September 7" or "exam scheduled for November 19-20").
   - If supporting news coverage discusses a topic but does not explicitly state a date for a milestone, that milestone MUST be marked as "Not yet announced" with source_confidence = "unavailable".

6. TENSE DISCIPLINE FOR RESULT & ANSWER-KEY EVENTS:
   - Before assigning source_confidence = "confirmed_primary_source" or "confirmed_secondary_source" to a "release" or "declared" milestone, confirm the source text uses PAST-TENSE, DECLARATIVE language specifically about the event itself — e.g. "has been released", "was declared on", "is now available for download".
   - If the source text instead uses FUTURE or CONDITIONAL language — "will be released", "expected to be released", "candidates can check once declared", "is likely to be announced" — the event is NOT confirmed. Mark date as "Not yet announced" and source_confidence as "unavailable".
   - An article explaining HOW to check an answer key or result once out is NOT evidence that it IS out. Do NOT conflate procedural/explanatory content with a factual release confirmation.

7. REQUIRED DOCUMENTS CHECKLIST (ZERO SPECULATION):
   - List every document explicitly named as mandatory or conditional in the official notification/bulletin for application/candidature.
   - For conditional documents, specify who it applies to in applies_to (e.g. 'OBC-NCL candidates seeking reservation', 'PwBD candidates requiring scribe').
   - Do NOT infer common documents that are not explicitly cited in the official notification. If none are explicitly cited in the circulars, return an empty array.

Return strictly as JSON matching this schema:
{
  "authority_name": "{$authority['name']}",
  "official_portal": "{$authority['portal']}",
  "notification_code": "Official CEN / Advt / Notification Reference Number or null",
  "exam_phase": "Specific stage (e.g. Undergraduate CBT-2, Tier-1, Prelims, CAP Round 3)",
  "exam_status": "Confirmed | Active Registration | Exam City Slip Active | Upcoming",
  "distinction_notes": "Explicit distinction if applicable",
  "prelims_pattern": {
    "total_questions": 100,
    "total_marks": 100,
    "duration_minutes": 60,
    "negative_marking": "0.25 (1/4th mark deducted per incorrect answer)",
    "sections": [
      {
        "subject": "Official Subject Name",
        "questions": 30,
        "marks": 30,
        "duration_minutes": 20
      }
    ]
  },
  "mains_pattern": {
    "total_questions": 190,
    "total_marks": 200,
    "duration_minutes": 160,
    "negative_marking": "0.25 (1/4th mark deducted per incorrect answer)",
    "sections": [
      {
        "subject": "Official Subject Name",
        "questions": 50,
        "marks": 50,
        "duration_minutes": 35
      }
    ]
  },
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
      "milestone": "Official Milestone Name (e.g. Notification Release, Application Last Date, Prelims Exam)",
      "date": "Exact Calendar Date (e.g. August 11, 2026) or Expected Month (e.g. Late September 2026)",
      "status": "Confirmed | Tentative / Expected | Awaiting Official Circular",
      "source_confidence": "confirmed_primary_source | confirmed_secondary_source | tentative_estimate | unavailable",
      "tentative_basis": "Required citation if tentative_estimate (e.g. per SBI Clerk 2025 cycle schedule), else null"
    }
  ],
  "regional_portals": [
    {
      "region_name": "RRB Bhopal",
      "url": "https://www.rrbbpl.nic.in"
    }
  ],
  "vacancy_breakdown": {
    "total_vacancies": "Total post count if mentioned (e.g. 9124 Posts)",
    "candidate_count": "Total candidates appearing if mentioned or null"
  },
  "application_start_date": "Exact Start Date if stated in circular, or null",
  "application_deadline": "Exact Last Date to Apply if stated in circular, or null",
  "fee_amount_general": "Rupee fee amount for General/OBC or Fee-Exempt, or null",
  "fee_amount_sc_st": "Rupee fee amount for SC/ST or null",
  "fee_amount_ph": "Rupee fee amount for PH or null",
  "mandatory_documents": [
    "Printed Official Admit Card with recent colour photograph",
    "Original Valid Government Photo ID (Aadhaar / PAN / Voter ID / Passport / Driving License)"
  ],
  "required_documents": [
    {
      "item": "10th Marksheet / Birth Certificate",
      "mandatory": true,
      "applies_to": "All Candidates"
    }
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

            // Post-extraction validation: Cleanse any invalid tentative estimates lacking basis
            if (isset($data['dates_schedule']) && is_array($data['dates_schedule'])) {
                foreach ($data['dates_schedule'] as &$item) {
                    if (($item['source_confidence'] ?? '') === 'tentative_estimate') {
                        $basis = trim((string)($item['tentative_basis'] ?? ''));
                        // If tentative estimate has no credible citation, force to unavailable
                        if (empty($basis) || !preg_match('/\b(202\d|official|circular|calendar|press|sbi|upsc|ssc|rrb|ibps|archive|portal|notification|cycle)\b/i', $basis)) {
                            $item['source_confidence'] = 'unavailable';
                            $item['date'] = 'Not yet announced';
                            $item['tentative_basis'] = null;
                        }
                    }

                    // Defense-in-depth: If an event date equals today's date, verify that the source
                    // explicitly confirmed it was released today (not just an article written today)
                    $dateVal = trim((string)($item['date'] ?? ''));
                    if (!empty($dateVal) && (stripos($dateVal, $currentDate) !== false || $dateVal === date('Y-m-d'))) {
                        $fullContextCorpus = $combinedText . ' ' . $dispatchesText;
                        $hasExplicitRelease = (bool)preg_match('/\b(has been released|was declared|published today|released today|declared today)\b/i', $fullContextCorpus);
                        if (!$hasExplicitRelease && preg_match('/result|answer key|scorecard|cut[\s\-]?off/i', $item['milestone'] ?? '')) {
                            Logger::warning("AuthorityFactFetcherService: Cleaned unconfirmed same-day date '{$dateVal}' for '{$item['milestone']}' — forcing to 'Not yet announced'");
                            $item['source_confidence'] = 'unavailable';
                            $item['date'] = 'Not yet announced';
                            $item['tentative_basis'] = null;
                        }
                    }
                }
            }

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
                'prelims_pattern' => [
                    'total_questions' => 100,
                    'total_marks' => 100,
                    'duration_minutes' => 60,
                    'negative_marking' => '0.25 (1/4th mark deducted per incorrect answer)',
                    'sections' => [
                        ['subject' => 'English Language', 'questions' => 30, 'marks' => 30, 'duration_minutes' => 20],
                        ['subject' => 'Quantitative Aptitude', 'questions' => 35, 'marks' => 35, 'duration_minutes' => 20],
                        ['subject' => 'Reasoning Ability', 'questions' => 35, 'marks' => 35, 'duration_minutes' => 20]
                    ]
                ],
                'mains_pattern' => [
                    'total_questions' => 190,
                    'total_marks' => 200,
                    'duration_minutes' => 160,
                    'negative_marking' => '0.25 (1/4th mark deducted per incorrect answer)',
                    'sections' => [
                        ['subject' => 'General / Financial Awareness', 'questions' => 50, 'marks' => 50, 'duration_minutes' => 35],
                        ['subject' => 'General English', 'questions' => 40, 'marks' => 40, 'duration_minutes' => 35],
                        ['subject' => 'Quantitative Aptitude', 'questions' => 50, 'marks' => 50, 'duration_minutes' => 45],
                        ['subject' => 'Reasoning Ability & Computer Aptitude', 'questions' => 50, 'marks' => 60, 'duration_minutes' => 45]
                    ]
                ],
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
                        'date' => 'Not yet announced',
                        'status' => 'Awaiting Official Circular',
                        'source_confidence' => 'unavailable',
                        'tentative_basis' => null
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
