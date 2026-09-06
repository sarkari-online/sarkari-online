<?php
/**
 * Sarkari.online - Tiered Primary-Source Authority Verification Service
 *
 * Implements decoupled Source Role vs Authority Tier architecture:
 * 1. source_role: authority_primary, discovery, secondary, unverified
 * 2. authority_tier: tier_1a, tier_1b, none
 * 3. Two-stage verification: Authority Identity (Stage A) vs Claim Evidence (Stage B)
 *
 * Uses EXPLICIT verified institutional registries only. No blanket .org / .edu.in / .com trust.
 */

namespace App\Services;

use App\Helpers\Logger;

class AuthorityVerificationService {

    public const ROLE_AUTHORITY_PRIMARY = 'authority_primary';
    public const ROLE_DISCOVERY         = 'discovery';
    public const ROLE_SECONDARY         = 'secondary';
    public const ROLE_UNVERIFIED        = 'unverified';

    public const TIER_1A   = 'tier_1a';
    public const TIER_1B   = 'tier_1b';
    public const TIER_NONE = 'none';

    /**
     * Tier 1A Explicit Registry: Statutory Government, Ministries, Commissions,
     * and Autonomous Government Authorities established by statute/cabinet.
     */
    private const TIER_1A_EXPLICIT_DOMAINS = [
        // Systemic Government & Commission Namespaces
        'gov.in',
        'nic.in',
        'upsc.gov.in',
        'ssc.gov.in',
        'indianrailways.gov.in',
        'education.gov.in',
        'scholarships.gov.in',
        'digilocker.gov.in',
        'uppbpb.gov.in',
        'bpsc.bih.nic.in',
        'rpsc.rajasthan.gov.in',
        'mppsc.mp.gov.in',
        'dsssb.delhi.gov.in',
        'hssc.gov.in',
        'tspsc.gov.in',
        'appsc.gov.in',
        'kpsc.kar.nic.in',
        'wbpsc.gov.in',
        'pib.gov.in',
        'samsodisha.gov.in',
        'updeled.gov.in',
        'cetonline.karnataka.gov.in',
        'energy.rajasthan.gov.in',
        'joinindianarmy.nic.in',
        'recruitment.itbpolice.nic.in',
        'cbse.gov.in',
        'cbse.nic.in',
        'ugc.gov.in',
        'ugc.ac.in',
        'abc.gov.in',
        'uidai.gov.in',
        'mcc.nic.in',
        'upsconline.nic.in',

        // Explicit Non-gov TLD Statutory Authorities & Autonomous Government Boards
        'natboard.edu.in',        // NBEMS (National Board of Examinations in Medical Sciences)
        'nbe.edu.in',             // Legacy NBEMS portal
        'aicte-india.org',        // AICTE (All India Council for Technical Education, AICTE Act 1987)
        'cert-in.org.in',         // CERT-In (Statutory Cyber Authority under IT Act Sec 70B)
        'samarth.edu.in',         // Samarth e-Gov Central Ministry of Education Platform
        'samarth.ac.in',
        'nta.ac.in',              // National Testing Agency (Ministry of Education)
        'agnipathvayu.cdac.in'    // CDAC / Indian Air Force Official Portal
    ];

    /**
     * Tier 1B Explicit Registry: Recognized Statutory / Regulatory / Professional /
     * PSU / Official Institutional Authorities.
     */
    private const TIER_1B_EXPLICIT_DOMAINS = [
        'coalindia.in',               // Coal India Limited (Maharatna CPSE, Ministry of Coal)
        'sbi.co.in',                  // State Bank of India (Statutory Banking Corporation)
        'bankofbaroda.in',            // Bank of Baroda (Public Sector Bank)
        'ibps.in',                    // Institute of Banking Personnel Selection
        'cetcell.mahacet.org',        // State Common Entrance Test Cell, Maharashtra
        'mahacet.org',                // Parent domain of Maharashtra CET Cell
        'barcouncilofindia.org',      // Bar Council of India
        'allindiabarexamination.com', // Official AIBE Portal sanctioned by BCI
        'cisce.org',                  // Council for the Indian School Certificate Examinations
        'aiims.edu',                  // All India Institute of Medical Sciences
        'aiimsexams.ac.in',
        'ignou.ac.in',                // Central University
        'iimcat.ac.in',               // Common Admission Test Official Portal
        'azimpremjifoundation.org'    // Recognized Philanthropic Foundation Portal
    ];

    /**
     * Known Discovery and Aggregator Sources (Must NEVER authorize facts or lifecycles)
     */
    private const DISCOVERY_PATTERNS = [
        'trends.google.',
        'google.com/trends',
        'news.google.',
        '/rss',
        'rss?geo=',
        'feedburner.com'
    ];

    /**
     * Known Secondary Media Domains
     */
    private const SECONDARY_MEDIA_DOMAINS = [
        'timesofindia.indiatimes.com',
        'indiatimes.com',
        'indianexpress.com',
        'thehindu.com',
        'hindustantimes.com',
        'ndtv.com',
        'livemint.com',
        'news18.com',
        'zeenews.india.com',
        'indiatoday.in',
        'amarujala.com',
        'jagran.com'
    ];

    /**
     * Stage A: Authority Identity Verification
     * Determines whether the domain/URL belongs to an official statutory authority,
     * a discovery source, or secondary/unverified.
     *
     * @param string $sourceUrl
     * @return array
     */
    public static function verifyIdentity(string $sourceUrl): array {
        if (empty(trim($sourceUrl))) {
            return [
                'is_valid'          => false,
                'is_authoritative'  => false,
                'source_role'       => self::ROLE_UNVERIFIED,
                'authority_tier'    => self::TIER_NONE,
                'legacy_tier'       => 'unverified',
                'confidence'        => 'none',
                'authority_name'    => 'Unknown Source',
                'canonical_domain'  => ''
            ];
        }

        $sourceUrl = trim($sourceUrl);
        $parsedHost = parse_url($sourceUrl, PHP_URL_HOST);
        $host = strtolower(trim($parsedHost ?: $sourceUrl));

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // 1. Check Discovery Sources (Google Trends, RSS feeds)
        foreach (self::DISCOVERY_PATTERNS as $dPat) {
            if (str_contains(strtolower($sourceUrl), $dPat)) {
                return [
                    'is_valid'          => false,
                    'is_authoritative'  => false,
                    'source_role'       => self::ROLE_DISCOVERY,
                    'authority_tier'    => self::TIER_NONE,
                    'legacy_tier'       => 'third_party_or_unverified',
                    'confidence'        => 'none',
                    'authority_name'    => 'Discovery Feed (' . $host . ')',
                    'canonical_domain'  => $host
                ];
            }
        }

        // 2. Check Secondary Media Sources
        foreach (self::SECONDARY_MEDIA_DOMAINS as $mDomain) {
            if ($host === $mDomain || str_ends_with($host, '.' . $mDomain)) {
                return [
                    'is_valid'          => false,
                    'is_authoritative'  => false,
                    'source_role'       => self::ROLE_SECONDARY,
                    'authority_tier'    => self::TIER_NONE,
                    'legacy_tier'       => 'third_party_or_unverified',
                    'confidence'        => 'low',
                    'authority_name'    => 'Secondary Media Source (' . $host . ')',
                    'canonical_domain'  => $host
                ];
            }
        }

        // 3. Check Tier 1A Explicit Registry
        foreach (self::TIER_1A_EXPLICIT_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                $name = self::resolveAuthorityName($host);
                return [
                    'is_valid'          => true,
                    'is_authoritative'  => true,
                    'source_role'       => self::ROLE_AUTHORITY_PRIMARY,
                    'authority_tier'    => self::TIER_1A,
                    'legacy_tier'       => 'tier_1a_government',
                    'confidence'        => 'high',
                    'authority_name'    => $name,
                    'canonical_domain'  => $d
                ];
            }
        }

        // 4. Check Tier 1B Explicit Registry
        foreach (self::TIER_1B_EXPLICIT_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                $name = self::resolveAuthorityName($host);
                return [
                    'is_valid'          => true,
                    'is_authoritative'  => true,
                    'source_role'       => self::ROLE_AUTHORITY_PRIMARY,
                    'authority_tier'    => self::TIER_1B,
                    'legacy_tier'       => 'tier_1b_autonomous_body',
                    'confidence'        => 'high',
                    'authority_name'    => $name,
                    'canonical_domain'  => $d
                ];
            }
        }

        // 5. Dynamic IIT GATE Portals pattern (e.g. gate2027.iitd.ac.in)
        if (preg_match('/^gate\d{4}\.[a-z0-9-]+\.(?:ac|ernet)\.in$/i', $host)) {
            return [
                'is_valid'          => true,
                'is_authoritative'  => true,
                'source_role'       => self::ROLE_AUTHORITY_PRIMARY,
                'authority_tier'    => self::TIER_1B,
                'legacy_tier'       => 'tier_1b_autonomous_body',
                'confidence'        => 'high',
                'authority_name'    => 'Organizing IIT GATE Portal (' . $host . ')',
                'canonical_domain'  => $host
            ];
        }

        // 6. Unknown / Unverified Source
        return [
            'is_valid'          => false,
            'is_authoritative'  => false,
            'source_role'       => self::ROLE_UNVERIFIED,
            'authority_tier'    => self::TIER_NONE,
            'legacy_tier'       => 'third_party_or_unverified',
            'confidence'        => 'none',
            'authority_name'    => 'Third-Party / Unverified (' . $host . ')',
            'canonical_domain'  => $host
        ];
    }

    /**
     * Stage B: Specific Claim Evidence Verification
     * Official domain identity alone MUST NOT mark article facts as verified.
     * This method evaluates whether specific supporting evidence exists.
     *
     * @param array $authorityIdentity Result of verifyIdentity()
     * @param string $claimType E.g. 'application_end', 'exam_date', 'vacancy'
     * @param mixed $claimValue E.g. 'September 25, 2026', 1100
     * @param string $content Full article text or circular extract
     * @param string|null $evidenceText Specific circular extract or gazette text
     * @return array ['is_verified' => bool, 'status' => string, 'reason' => string]
     */
    public static function verifyClaimEvidence(
        array $authorityIdentity,
        string $claimType,
        mixed $claimValue,
        string $content,
        ?string $evidenceText = null
    ): array {
        // Stage A gate: Must be primary authority Tier 1A or Tier 1B
        if (
            empty($authorityIdentity['is_authoritative']) ||
            !in_array($authorityIdentity['authority_tier'], [self::TIER_1A, self::TIER_1B], true) ||
            $authorityIdentity['source_role'] !== self::ROLE_AUTHORITY_PRIMARY
        ) {
            return [
                'is_verified' => false,
                'status'      => 'unverified',
                'reason'      => 'Stage A Failed: Source is not recognized as a primary Tier 1A/1B authority.'
            ];
        }

        // If claim value is empty, TBA, or unannounced
        if (empty($claimValue) || preg_match('/^(tba|to be announced|awaited|pending)/i', trim((string)$claimValue))) {
            return [
                'is_verified' => false,
                'status'      => 'unannounced',
                'reason'      => "Claim '{$claimType}' is marked unannounced/TBA; cannot be verified as an active statutory milestone."
            ];
        }

        // Stage B check: Corroborating evidence in official notice / content
        $searchCorpus = ($evidenceText ? $evidenceText . ' ' : '') . $content;
        $claimStr = trim((string)$claimValue);

        // Substring or normalized date search
        $found = (stripos($searchCorpus, $claimStr) !== false);

        if (!$found) {
            // Attempt date normalization match if applicable
            $cleanDate = preg_replace('/(\d+)(st|nd|rd|th)/i', '$1', $claimStr);
            $found = (stripos($searchCorpus, $cleanDate) !== false);
        }

        if ($found) {
            return [
                'is_verified' => true,
                'status'      => 'verified',
                'reason'      => "Stage B Passed: Official authority '{$authorityIdentity['authority_name']}' supports claim '{$claimType}' ({$claimStr})."
            ];
        }

        return [
            'is_verified' => false,
            'status'      => 'pending',
            'reason'      => "Stage B Failed: Authority identity '{$authorityIdentity['authority_name']}' is valid, but specific claim '{$claimType}' ({$claimStr}) lacks supporting circular evidence."
        ];
    }

    /**
     * Backward-compatible verify() method
     * Calls verifyIdentity() and maps return fields to preserve existing callers.
     *
     * @param string $sourceUrl
     * @return array
     */
    public static function verify(string $sourceUrl): array {
        $ident = self::verifyIdentity($sourceUrl);

        return [
            'is_valid'          => $ident['is_valid'],
            'tier'              => $ident['legacy_tier'],
            'authority_tier'    => $ident['authority_tier'],
            'source_role'       => $ident['source_role'],
            'confidence'        => $ident['confidence'],
            'authority_name'    => $ident['authority_name'],
            'canonical_domain'  => $ident['canonical_domain']
        ];
    }

    /**
     * Resolve human-readable authority name from domain
     */
    private static function resolveAuthorityName(string $host): string {
        $map = [
            'ssc.gov.in'                => 'Staff Selection Commission (SSC)',
            'upsc.gov.in'               => 'Union Public Service Commission (UPSC)',
            'upsconline.nic.in'         => 'Union Public Service Commission (UPSC)',
            'indianrailways.gov.in'     => 'Railway Recruitment Boards (RRB)',
            'ibps.in'                   => 'Institute of Banking Personnel Selection (IBPS)',
            'nta.ac.in'                 => 'National Testing Agency (NTA)',
            'cbse.gov.in'               => 'Central Board of Secondary Education (CBSE)',
            'cbse.nic.in'               => 'Central Board of Secondary Education (CBSE)',
            'cisce.org'                 => 'Council for the Indian School Certificate Examinations (CISCE)',
            'scholarships.gov.in'       => 'National Scholarship Portal (NSP / MoE)',
            'digilocker.gov.in'         => 'DigiLocker / National Academic Depository (MeitY)',
            'uppbpb.gov.in'             => 'Uttar Pradesh Police Recruitment Board (UPPRPB)',
            'bpsc.bih.nic.in'           => 'Bihar Public Service Commission (BPSC)',
            'rpsc.rajasthan.gov.in'     => 'Rajasthan Public Service Commission (RPSC)',
            'ugc.gov.in'                => 'University Grants Commission (UGC)',
            'aicte-india.org'           => 'All India Council for Technical Education (AICTE)',
            'natboard.edu.in'           => 'National Board of Examinations in Medical Sciences (NBEMS)',
            'nbe.edu.in'                => 'National Board of Examinations in Medical Sciences (NBEMS)',
            'cert-in.org.in'            => 'Indian Computer Emergency Response Team (CERT-In)',
            'samarth.edu.in'            => 'Samarth e-Gov Central University Admission Portal',
            'coalindia.in'              => 'Coal India Limited (CIL)',
            'sbi.co.in'                 => 'State Bank of India (SBI)',
            'bankofbaroda.in'           => 'Bank of Baroda (BOB)',
            'cetcell.mahacet.org'       => 'State Common Entrance Test Cell, Maharashtra',
            'mahacet.org'               => 'State Common Entrance Test Cell, Maharashtra',
            'allindiabarexamination.com'=> 'All India Bar Examination (Bar Council of India)',
            'barcouncilofindia.org'     => 'Bar Council of India',
            'joinindianarmy.nic.in'     => 'Join Indian Army Recruiting Directorate',
            'agnipathvayu.cdac.in'      => 'Indian Air Force (Agnipath Vayu CDAC Portal)',
            'cetonline.karnataka.gov.in'=> 'Karnataka Examinations Authority (KEA)',
            'energy.rajasthan.gov.in'   => 'Rajasthan State Power Generation Companies (RVUNL)',
            'samsodisha.gov.in'         => 'Student Academic Management System (SAMS Odisha)',
            'updeled.gov.in'            => 'Uttar Pradesh Examination Regulatory Authority',
            'hssc.gov.in'               => 'Haryana Staff Selection Commission (HSSC)'
        ];

        foreach ($map as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $name;
            }
        }

        return 'Official Statutory Authority (' . $host . ')';
    }
}
