<?php
/**
 * Sarkari.online - Tiered Primary-Source Authority Verification Service
 * Validates ground-truth authority for factual claims (fees, dates, vacancies, eligibility)
 * using a tiered primary-source model including statutory domains and official autonomous bodies.
 */

namespace App\Services;

use App\Helpers\Logger;

class AuthorityVerificationService {

    /**
     * Tier 1A: Statutory Government & Commission Portals
     */
    private const TIER_1A_DOMAINS = [
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
        'pib.gov.in'
    ];

    /**
     * Tier 1B: Recognized Autonomous National Bodies & Testing Agencies
     * (Mandatory for Banking, National Entrance, and Board Exams)
     */
    private const TIER_1B_DOMAINS = [
        'ibps.in',               // Institute of Banking Personnel Selection
        'nta.ac.in',              // National Testing Agency (NEET, JEE, CUET)
        'cbse.gov.in',            // Central Board of Secondary Education
        'cbse.nic.in',
        'cisce.org',              // ICSE / ISC Council
        'aiims.edu',              // AIIMS Exams & Medical Admissions
        'aiimsexams.ac.in',
        'jeemain.nta.nic.in',
        'neet.nta.nic.in',
        'cuet.nta.nic.in',
        'gate2026.iitkgp.ac.in',  // Dynamic IIT GATE host portals
        'ignou.ac.in',
        'iimcat.ac.in'
    ];

    /**
     * Tier 2: Recognized Statutory Regulators & Professional Councils
     */
    private const TIER_2_DOMAINS = [
        'ugc.gov.in',
        'ugc.ac.in',
        'aicte-india.org',
        'barcouncilofindia.org',
        'pci.nic.in',
        'nbe.edu.in',
        'natboard.edu.in'
    ];

    /**
     * Verify authority level of a given source URL or domain
     * 
     * @param string $sourceUrl Official URL or domain string
     * @return array ['is_valid' => bool, 'tier' => string, 'confidence' => string, 'authority_name' => string]
     */
    public static function verify(string $sourceUrl): array {
        if (empty($sourceUrl)) {
            return [
                'is_valid' => false,
                'tier' => 'unverified',
                'confidence' => 'none',
                'authority_name' => 'Unknown Source'
            ];
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST) ?: $sourceUrl;
        $host = strtolower(trim($host));

        // Strip leading 'www.'
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Check Tier 1A: Government & Commission Portals
        foreach (self::TIER_1A_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return [
                    'is_valid' => true,
                    'tier' => 'tier_1a_government',
                    'confidence' => 'high',
                    'authority_name' => self::resolveAuthorityName($host)
                ];
            }
        }

        // Check Tier 1B: Autonomous National Testing Bodies & Banking (ibps.in, nta.ac.in, etc.)
        foreach (self::TIER_1B_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return [
                    'is_valid' => true,
                    'tier' => 'tier_1b_autonomous_body',
                    'confidence' => 'high',
                    'authority_name' => self::resolveAuthorityName($host)
                ];
            }
        }

        // Check Tier 2: Statutory Regulators (ugc, aicte, etc.)
        foreach (self::TIER_2_DOMAINS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return [
                    'is_valid' => true,
                    'tier' => 'tier_2_regulator',
                    'confidence' => 'medium',
                    'authority_name' => self::resolveAuthorityName($host)
                ];
            }
        }

        // Generic academic/government TLD fallback (.ac.in, .edu.in)
        if (str_ends_with($host, '.ac.in') || str_ends_with($host, '.edu.in')) {
            return [
                'is_valid' => true,
                'tier' => 'tier_2_academic_body',
                'confidence' => 'medium',
                'authority_name' => 'Recognized University / Institute'
            ];
        }

        return [
            'is_valid' => false,
            'tier' => 'third_party_or_unverified',
            'confidence' => 'low',
            'authority_name' => 'Third-Party Source'
        ];
    }

    /**
     * Resolve human-readable authority name from domain
     */
    private static function resolveAuthorityName(string $host): string {
        $map = [
            'ssc.gov.in' => 'Staff Selection Commission (SSC)',
            'upsc.gov.in' => 'Union Public Service Commission (UPSC)',
            'indianrailways.gov.in' => 'Railway Recruitment Boards (RRB)',
            'ibps.in' => 'Institute of Banking Personnel Selection (IBPS)',
            'nta.ac.in' => 'National Testing Agency (NTA)',
            'cbse.gov.in' => 'Central Board of Secondary Education (CBSE)',
            'cisce.org' => 'Council for the Indian School Certificate Examinations (CISCE)',
            'scholarships.gov.in' => 'National Scholarship Portal (NSP / MoE)',
            'digilocker.gov.in' => 'DigiLocker / National Academic Depository (MeitY)',
            'uppbpb.gov.in' => 'Uttar Pradesh Police Recruitment Board (UPPRPB)',
            'bpsc.bih.nic.in' => 'Bihar Public Service Commission (BPSC)',
            'rpsc.rajasthan.gov.in' => 'Rajasthan Public Service Commission (RPSC)',
            'ugc.gov.in' => 'University Grants Commission (UGC)',
            'aicte-india.org' => 'All India Council for Technical Education (AICTE)'
        ];

        foreach ($map as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $name;
            }
        }

        return 'Official Statutory Authority (' . $host . ')';
    }
}
