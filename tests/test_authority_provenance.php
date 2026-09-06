<?php
/**
 * Test Suite: Authority Identity + Claim Evidence + Provenance Model
 * 
 * Verifies all 12 specified scenarios deterministically:
 * 1. .gov.in / .nic.in
 * 2. AICTE (aicte-india.org)
 * 3. NBEMS (natboard.edu.in)
 * 4. CERT-In (cert-in.org.in)
 * 5. Maharashtra CET Cell (cetcell.mahacet.org)
 * 6. Samarth (samarth.edu.in)
 * 7. PSU domains (CIL coalindia.in, SBI sbi.co.in)
 * 8. Google Trends
 * 9. RSS
 * 10. Unknown .com
 * 11. Official authority + unsupported claim -> NOT verified (Stage B pending)
 * 12. Official authority + supporting evidence -> verified (Stage B verified)
 */

require_once dirname(__DIR__) . '/app/Services/AuthorityVerificationService.php';

use App\Services\AuthorityVerificationService;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $scenario, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$scenario}" . ($detail ? " - {$detail}" : "") . "\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$scenario}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "RUNNING DETERMINISTIC AUTHORITY & PROVENANCE SUITE (12 SCENARIOS)\n";
echo "====================================================================\n\n";

// 1. .gov.in / .nic.in
echo "Scenario 1: .gov.in / .nic.in domains\n";
$gov = AuthorityVerificationService::verifyIdentity('https://upsc.gov.in/examinations/active');
$nic = AuthorityVerificationService::verifyIdentity('https://bpsc.bih.nic.in/Notice.pdf');
assertTest('Scenario 1.1: upsc.gov.in Tier 1A', 
    $gov['is_authoritative'] === true && 
    $gov['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $gov['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$gov['authority_tier']}, Role: {$gov['source_role']}"
);
assertTest('Scenario 1.2: bpsc.bih.nic.in Tier 1A', 
    $nic['is_authoritative'] === true && 
    $nic['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $nic['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$nic['authority_tier']}, Role: {$nic['source_role']}"
);

// 2. AICTE (aicte-india.org)
echo "\nScenario 2: AICTE (aicte-india.org)\n";
$aicte = AuthorityVerificationService::verifyIdentity('https://www.aicte-india.org/bulletins/schemes');
assertTest('Scenario 2: aicte-india.org Tier 1A statutory',
    $aicte['is_authoritative'] === true &&
    $aicte['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $aicte['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$aicte['authority_tier']}, Name: {$aicte['authority_name']}"
);

// 3. NBEMS (natboard.edu.in)
echo "\nScenario 3: NBEMS (natboard.edu.in)\n";
$nbems = AuthorityVerificationService::verifyIdentity('https://natboard.edu.in/viewNotice.php?NID=1234');
assertTest('Scenario 3: natboard.edu.in Tier 1A statutory board',
    $nbems['is_authoritative'] === true &&
    $nbems['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $nbems['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$nbems['authority_tier']}, Name: {$nbems['authority_name']}"
);

// 4. CERT-In (cert-in.org.in)
echo "\nScenario 4: CERT-In (cert-in.org.in)\n";
$certin = AuthorityVerificationService::verifyIdentity('https://www.cert-in.org.in/advisories/CIAD-2026-0012');
assertTest('Scenario 4: cert-in.org.in Tier 1A statutory cyber authority',
    $certin['is_authoritative'] === true &&
    $certin['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $certin['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$certin['authority_tier']}, Name: {$certin['authority_name']}"
);

// 5. Maharashtra CET Cell (cetcell.mahacet.org)
echo "\nScenario 5: Maharashtra CET Cell (cetcell.mahacet.org)\n";
$mahacet = AuthorityVerificationService::verifyIdentity('https://cetcell.mahacet.org/CAP-2026/notifications');
assertTest('Scenario 5: cetcell.mahacet.org Tier 1B state statutory authority',
    $mahacet['is_authoritative'] === true &&
    $mahacet['authority_tier'] === AuthorityVerificationService::TIER_1B &&
    $mahacet['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$mahacet['authority_tier']}, Name: {$mahacet['authority_name']}"
);

// 6. Samarth (samarth.edu.in)
echo "\nScenario 6: Samarth e-Gov Central Portal (samarth.edu.in)\n";
$samarth = AuthorityVerificationService::verifyIdentity('https://cuetug.samarth.edu.in/index.php');
assertTest('Scenario 6: samarth.edu.in Tier 1A national platform',
    $samarth['is_authoritative'] === true &&
    $samarth['authority_tier'] === AuthorityVerificationService::TIER_1A &&
    $samarth['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$samarth['authority_tier']}, Name: {$samarth['authority_name']}"
);

// 7. PSU domains (CIL coalindia.in, SBI sbi.co.in)
echo "\nScenario 7: PSU / CPSE domains (Coal India, State Bank of India)\n";
$cil = AuthorityVerificationService::verifyIdentity('https://www.coalindia.in/campaigns/recruitment-mt-2026/');
$sbi = AuthorityVerificationService::verifyIdentity('https://sbi.co.in/web/careers/current-openings');
assertTest('Scenario 7.1: coalindia.in Tier 1B CPSE',
    $cil['is_authoritative'] === true &&
    $cil['authority_tier'] === AuthorityVerificationService::TIER_1B &&
    $cil['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$cil['authority_tier']}, Name: {$cil['authority_name']}"
);
assertTest('Scenario 7.2: sbi.co.in Tier 1B statutory bank',
    $sbi['is_authoritative'] === true &&
    $sbi['authority_tier'] === AuthorityVerificationService::TIER_1B &&
    $sbi['source_role'] === AuthorityVerificationService::ROLE_AUTHORITY_PRIMARY,
    "Tier: {$sbi['authority_tier']}, Name: {$sbi['authority_name']}"
);

// 8. Google Trends
echo "\nScenario 8: Google Trends (Discovery only)\n";
$trends = AuthorityVerificationService::verifyIdentity('https://trends.google.com/trending/rss?geo=IN');
assertTest('Scenario 8: Google Trends is discovery only and tier none',
    $trends['is_authoritative'] === false &&
    $trends['authority_tier'] === AuthorityVerificationService::TIER_NONE &&
    $trends['source_role'] === AuthorityVerificationService::ROLE_DISCOVERY,
    "Tier: {$trends['authority_tier']}, Role: {$trends['source_role']}"
);

// 9. RSS Feeds
echo "\nScenario 9: RSS / Feed sources\n";
$rss = AuthorityVerificationService::verifyIdentity('https://feeds.feedburner.com/sarkari-updates/rss');
assertTest('Scenario 9: RSS feed is discovery only and tier none',
    $rss['is_authoritative'] === false &&
    $rss['authority_tier'] === AuthorityVerificationService::TIER_NONE &&
    $rss['source_role'] === AuthorityVerificationService::ROLE_DISCOVERY,
    "Tier: {$rss['authority_tier']}, Role: {$rss['source_role']}"
);

// 10. Unknown .com domain
echo "\nScenario 10: Unknown .com domain\n";
$unknown = AuthorityVerificationService::verifyIdentity('https://sarkariprepnews24.com/latest-admit-card');
assertTest('Scenario 10: Unknown domain is unverified with tier none',
    $unknown['is_authoritative'] === false &&
    $unknown['authority_tier'] === AuthorityVerificationService::TIER_NONE &&
    $unknown['source_role'] === AuthorityVerificationService::ROLE_UNVERIFIED,
    "Tier: {$unknown['authority_tier']}, Role: {$unknown['source_role']}"
);

// 11. Official authority + unsupported claim -> NOT verified (Stage B pending)
echo "\nScenario 11: Official authority + unsupported claim (Decoupled Stage A vs Stage B)\n";
$sbiAuth = AuthorityVerificationService::verifyIdentity('https://sbi.co.in/careers');
$fakeExamDate = 'November 30, 2026';
$sampleContent = '<p>SBI PO recruitment notification is out. Candidates can register online starting September 1, 2026.</p>';
$unsupportedResult = AuthorityVerificationService::verifyClaimEvidence(
    $sbiAuth,
    'exam_date',
    $fakeExamDate,
    $sampleContent,
    null
);
assertTest('Scenario 11: Official SBI authority but unmentioned exam date must NOT verify',
    $unsupportedResult['is_verified'] === false &&
    $unsupportedResult['status'] === 'pending',
    "Status: {$unsupportedResult['status']}, Reason: {$unsupportedResult['reason']}"
);

// 12. Official authority + supporting evidence -> VERIFIED
echo "\nScenario 12: Official authority + supporting evidence (Stage A + Stage B PASS)\n";
$upscAuth = AuthorityVerificationService::verifyIdentity('https://upsc.gov.in');
$realDeadline = 'October 15, 2026';
$circularContent = '<p>The Union Public Service Commission announces the last date for submission of online applications is October 15, 2026 till 18:00 hrs.</p>';
$supportedResult = AuthorityVerificationService::verifyClaimEvidence(
    $upscAuth,
    'application_end',
    $realDeadline,
    $circularContent,
    'Last date for submission of online applications is October 15, 2026'
);
assertTest('Scenario 12: Official UPSC authority with supporting circular text must verify',
    $supportedResult['is_verified'] === true &&
    $supportedResult['status'] === 'verified',
    "Status: {$supportedResult['status']}, Reason: {$supportedResult['reason']}"
);

echo "\n====================================================================\n";
echo "SUMMARY: Total: {$totalTests} | Passed: {$passedTests} | Failed: {$failedTests}\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
