<?php
/**
 * Migration: Create authority_portals table and seed established statutory bodies
 */

require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;

echo "=== Running Migration: create_authority_portals ===\n";

Database::execute("
    CREATE TABLE IF NOT EXISTS authority_portals (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        acronym VARCHAR(30) NOT NULL,
        official_name VARCHAR(255) NOT NULL,
        portal_url VARCHAR(500) NOT NULL,
        discovery_method ENUM('static_seed','search_discovered') NOT NULL,
        verification_status ENUM('verified','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
        discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        verified_at DATETIME NULL,
        verified_by VARCHAR(100) NULL,
        UNIQUE KEY uk_acronym (acronym),
        INDEX idx_verification (verification_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo "✅ Table 'authority_portals' created / verified.\n";

$seedAuthorities = [
    'UPESSC'     => ['name' => 'Uttar Pradesh Education Service Selection Commission (UPESSC)', 'portal' => 'https://upessc.up.gov.in'],
    'NBEMS'      => ['name' => 'NBEMS (National Board of Examinations in Medical Sciences)', 'portal' => 'https://natboard.edu.in'],
    'NTA'        => ['name' => 'NTA (National Testing Agency)', 'portal' => 'https://nta.ac.in'],
    'UPSC'       => ['name' => 'UPSC (Union Public Service Commission)', 'portal' => 'https://upsc.gov.in'],
    'SSC'        => ['name' => 'SSC (Staff Selection Commission)', 'portal' => 'https://ssc.gov.in'],
    'AICTE'      => ['name' => 'All India Council for Technical Education (AICTE)', 'portal' => 'https://www.aicte-india.org'],
    'CBSE'       => ['name' => 'CBSE (Central Board of Secondary Education)', 'portal' => 'https://cbse.gov.in'],
    'CTET'       => ['name' => 'CTET Unit, CBSE', 'portal' => 'https://ctet.nic.in'],
    'UGC'        => ['name' => 'UGC (University Grants Commission)', 'portal' => 'https://www.ugc.gov.in'],
    'JOSAA'      => ['name' => 'JoSAA (Joint Seat Allocation Authority)', 'portal' => 'https://josaa.nic.in'],
    'MCC'        => ['name' => 'MCC (Medical Counselling Committee)', 'portal' => 'https://mcc.nic.in'],
    'NSP'        => ['name' => 'NSP (National Scholarship Portal)', 'portal' => 'https://scholarships.gov.in'],
    'RRB'        => ['name' => 'Railway Recruitment Boards (RRB)', 'portal' => 'https://indianrailways.gov.in'],
    'IBPS'       => ['name' => 'IBPS (Institute of Banking Personnel Selection)', 'portal' => 'https://ibps.in'],
    'SBI'        => ['name' => 'State Bank of India (SBI)', 'portal' => 'https://sbi.co.in/web/careers'],
    'IAF'        => ['name' => 'Indian Air Force (IAF / Agnipath Vayu)', 'portal' => 'https://agnipathvayu.cdac.in'],
    'ARMY'       => ['name' => 'Indian Army (Join Indian Army)', 'portal' => 'https://joinindianarmy.nic.in'],
    'NAVY'       => ['name' => 'Indian Navy (Join Indian Navy)', 'portal' => 'https://joinindiannavy.gov.in'],
    'COASTGUARD' => ['name' => 'Indian Coast Guard (ICG)', 'portal' => 'https://joinindiancoastguard.cdac.in'],
    'BSF'        => ['name' => 'Border Security Force (BSF)', 'portal' => 'https://rectt.bsf.gov.in'],
    'CISF'       => ['name' => 'Central Industrial Security Force (CISF)', 'portal' => 'https://cisfrectt.cisf.gov.in'],
    'CRPF'       => ['name' => 'Central Reserve Police Force (CRPF)', 'portal' => 'https://rect.crpf.gov.in'],
    'ITBP'       => ['name' => 'Indo-Tibetan Border Police (ITBP)', 'portal' => 'https://recruitment.itbpolice.nic.in'],
    'SSB'        => ['name' => 'Sashastra Seema Bal (SSB)', 'portal' => 'https://ssbrectt.gov.in'],
    'DRDO'       => ['name' => 'DRDO (Defence Research and Development Organisation)', 'portal' => 'https://drdo.gov.in'],
    'ISRO'       => ['name' => 'ISRO (Indian Space Research Organisation)', 'portal' => 'https://isro.gov.in'],
    'KVS'        => ['name' => 'Kendriya Vidyalaya Sangathan (KVS)', 'portal' => 'https://kvsangathan.nic.in'],
    'NVS'        => ['name' => 'Navodaya Vidyalaya Samiti (NVS)', 'portal' => 'https://navodaya.gov.in'],
    'DSSSB'      => ['name' => 'Delhi Subordinate Services Selection Board (DSSSB)', 'portal' => 'https://dsssb.delhi.gov.in'],
    'UPSSSC'     => ['name' => 'UP Subordinate Services Selection Commission (UPSSSC)', 'portal' => 'https://upsssc.gov.in'],
    'UPPRPB'     => ['name' => 'Uttar Pradesh Police Recruitment & Promotion Board (UPPRPB)', 'portal' => 'https://uppbpb.gov.in'],
    'HPBOSE'     => ['name' => 'HPBOSE (Himachal Pradesh Board of School Education)', 'portal' => 'https://hpbose.org'],
    'IGNOU'      => ['name' => 'Indira Gandhi National Open University (IGNOU)', 'portal' => 'https://ignouadmission.samarth.edu.in'],
    'COALINDIA'  => ['name' => 'Coal India Limited (CIL)', 'portal' => 'https://coalindia.in'],
    'KPSC'       => ['name' => 'KPSC (Karnataka Public Service Commission)', 'portal' => 'https://kpsc.kar.nic.in'],
    'BPSC'       => ['name' => 'Bihar Public Service Commission (BPSC)', 'portal' => 'https://www.bpsc.bih.nic.in'],
    'BSSC'       => ['name' => 'Bihar Staff Selection Commission (BSSC)', 'portal' => 'https://bssc.bihar.gov.in'],
    'CSBC'       => ['name' => 'Central Selection Board of Constable, Bihar (CSBC)', 'portal' => 'https://csbc.bih.nic.in'],
    'UPPSC'      => ['name' => 'UP Public Service Commission (UPPSC)', 'portal' => 'https://uppsc.up.nic.in'],
    'MPPSC'      => ['name' => 'MP Public Service Commission (MPPSC)', 'portal' => 'https://mppsc.mp.gov.in'],
    'RPSC'       => ['name' => 'Rajasthan Public Service Commission (RPSC)', 'portal' => 'https://rpsc.rajasthan.gov.in'],
    'RSMSSB'     => ['name' => 'Rajasthan Staff Selection Board (RSMSSB)', 'portal' => 'https://rsmssb.rajasthan.gov.in'],
    'HSSC'       => ['name' => 'Haryana Staff Selection Commission (HSSC)', 'portal' => 'https://hssc.gov.in'],
    'HPSC'       => ['name' => 'Haryana Public Service Commission (HPSC)', 'portal' => 'https://hpsc.gov.in'],
    'BSEH'       => ['name' => 'Board of School Education Haryana (BSEH)', 'portal' => 'https://bseh.org.in'],
    'UKPSC'      => ['name' => 'Uttarakhand Public Service Commission (UKPSC)', 'portal' => 'https://psc.uk.gov.in'],
    'BSEB'       => ['name' => 'Bihar School Examination Board (BSEB)', 'portal' => 'https://biharboardonline.bihar.gov.in'],
    'UPMSP'      => ['name' => 'UPMSP (Uttar Pradesh Madhyamik Shiksha Parishad)', 'portal' => 'https://upmsp.edu.in'],
    'WBJEE'      => ['name' => 'WBJEEB (West Bengal Joint Entrance Examinations Board)', 'portal' => 'https://wbjeeb.nic.in'],
    'WBSSC'      => ['name' => 'West Bengal Central School Service Commission (WBSSC)', 'portal' => 'https://westbengalssc.com'],
    'AIIMS'      => ['name' => 'All India Institute of Medical Sciences (AIIMS)', 'portal' => 'https://aiimsexams.ac.in']
];

$inserted = 0;
foreach ($seedAuthorities as $acronym => $info) {
    Database::execute("
        INSERT INTO authority_portals 
            (acronym, official_name, portal_url, discovery_method, verification_status, discovered_at, verified_at, verified_by)
        VALUES 
            (:acronym, :name, :portal, 'static_seed', 'verified', NOW(), NOW(), 'system_seed')
        ON DUPLICATE KEY UPDATE 
            official_name = VALUES(official_name),
            portal_url = VALUES(portal_url)
    ", [
        'acronym' => $acronym,
        'name'    => $info['name'],
        'portal'  => $info['portal']
    ]);
    $inserted++;
}

echo "✅ Pre-seeded {$inserted} verified authorities into authority_portals.\n";
