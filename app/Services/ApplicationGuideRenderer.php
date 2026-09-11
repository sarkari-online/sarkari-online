<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

/**
 * ApplicationGuideRenderer — Task 2
 * 
 * Generates keyword-accurate application guides (/how-to-apply/{exam-slug}-{year}/)
 * optimized for real student search queries (Hinglish/Hindi/English).
 * 
 * STRICT CONFIDENCE GATES:
 * 1. Only renders for APPLICATION_OPEN or APPLICATION_CORRECTION phases.
 * 2. Requires phase_confidence = 'VERIFIED'.
 * 3. Enforces grounded keyword research for student search phrasing.
 * 4. Step-by-step procedural flow with statutory disclaimer.
 */
class ApplicationGuideRenderer
{
    private Gemini $gemini;

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Conduct grounded keyword research for actual candidate search queries.
     */
    public function researchKeywords(string $examName, int $year, string $authorityCode): array
    {
        $prompt = <<<PROMPT
Search for how Indian job and exam aspirants actually phrase search queries for filling the {$examName} {$year} ({$authorityCode}) application form or correction window.
Check Google autocomplete-style patterns, People Also Ask, and common Hinglish phrasings used on Sarkari forums and YouTube tutorials for this exam.

Return 5 to 8 most likely literal search phrases (authentic mix of Hindi, English, and Hinglish).
Return ONLY valid JSON:
{
  "primary_h1_phrase": "Most popular natural query phrase (e.g. {$examName} 2026 form kaise bhare)",
  "primary_meta_phrase": "Short high-intent query (e.g. {$examName} Online Apply Kaise Kare)",
  "search_phrases": [
    "{$examName} form kaise bhare",
    "{$examName} online application form {$year} step by step",
    "how to fill {$examName} form in hindi",
    "{$examName} correction window online process"
  ]
}
PROMPT;

        try {
            $response = $this->gemini->generateJson($prompt, [
                'stage'       => 'keyword_research_application_guide',
                'temperature' => 0.1,
            ]);

            $data = $response['data'] ?? [];
            if (!empty($data['search_phrases']) && is_array($data['search_phrases'])) {
                return [
                    'primary_h1'   => $data['primary_h1_phrase'] ?? "{$examName} {$year} Form Kaise Bhare",
                    'primary_meta' => $data['primary_meta_phrase'] ?? "{$examName} {$year} Online Apply",
                    'phrases'      => array_slice($data['search_phrases'], 0, 8),
                ];
            }
        } catch (Throwable $e) {
            Logger::warning("ApplicationGuideRenderer: Keyword research fallback used: " . $e->getMessage());
        }

        // Natural, unhedged statutory fallback
        $shortName = mb_strtolower($examName);
        return [
            'primary_h1'   => "{$examName} {$year} Form Kaise Bhare",
            'primary_meta' => "{$examName} {$year} Online Apply Kaise Kare",
            'phrases'      => [
                "{$shortName} {$year} form kaise bhare",
                "{$shortName} online apply kaise kare step by step",
                "{$shortName} form fill up in hindi",
                "how to apply for {$shortName} {$year} online",
                "{$shortName} application form correction kaise kare",
            ],
        ];
    }

    /**
     * Render the application guide page
     * 
     * @param array $cycle Row from exam_cycles
     * @param bool  $forceAllowDryRun Bypass phase gate strictly for test/dry-run review
     * @return array|null Returns ['html' => string, 'meta_title' => string, 'meta_description' => string, 'keywords' => array] or null
     */
    public function render(array $cycle, bool $forceAllowDryRun = false): ?array
    {
        $phase = $cycle['current_phase'] ?? '';
        $confidence = $cycle['phase_confidence'] ?? 'INFERRED';

        // Gate: Only APPLICATION_OPEN or APPLICATION_CORRECTION
        $allowedPhases = ['APPLICATION_OPEN', 'APPLICATION_CORRECTION'];
        $isArchived = !in_array($phase, $allowedPhases, true);

        if ($isArchived && !$forceAllowDryRun) {
            Logger::info("ApplicationGuideRenderer: Cycle #{$cycle['id']} is in phase '{$phase}' — skipping or archived");
            return null;
        }

        if ($confidence !== 'VERIFIED' && !$forceAllowDryRun) {
            Logger::warning("ApplicationGuideRenderer: Cycle #{$cycle['id']} confidence is '{$confidence}' — BLOCKED by gate");
            return null;
        }

        $examName = trim((string)$cycle['exam_name']);
        $year     = (int)$cycle['cycle_year'];
        $authCode = strtoupper(trim((string)$cycle['authority_code']));
        $portal   = $cycle['phase_evidence_url'] ?: 'https://' . strtolower($authCode) . '.nic.in';

        // 1. Research real candidate keywords
        $kw = $this->researchKeywords($examName, $year, $authCode);

        // 2. Decode facts
        $facts = [];
        if (!empty($cycle['facts_json'])) {
            $decoded = json_decode($cycle['facts_json'], true);
            if (is_array($decoded)) {
                $facts = $decoded;
            }
        }

        // 3. Assemble Meta Information
        $metaTitle = "{$kw['primary_meta']} ({$year}): Step-by-Step Guide & Portal Link";
        $metaDesc  = "{$kw['primary_h1']} — Complete step-by-step online registration, application correction process, document upload, and fee guidelines for {$examName} {$year}.";

        // 4. Generate Content HTML
        $html = $this->buildHtml($cycle, $kw, $facts, $portal, $isArchived);

        return [
            'html'             => $html,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'keywords'         => $kw,
            'is_archived'      => $isArchived,
        ];
    }

    private function buildHtml(array $cycle, array $kw, array $facts, string $portal, bool $isArchived): string
    {
        $examName   = htmlspecialchars((string)$cycle['exam_name'], ENT_QUOTES, 'UTF-8');
        $year       = (int)$cycle['cycle_year'];
        $authCode   = htmlspecialchars((string)$cycle['authority_code'], ENT_QUOTES, 'UTF-8');
        $portalUrl  = htmlspecialchars($portal, ENT_QUOTES, 'UTF-8');
        $phase      = (string)$cycle['current_phase'];
        $primaryH1  = htmlspecialchars($kw['primary_h1'], ENT_QUOTES, 'UTF-8');

        // Status Banner
        $statusBannerHtml = '';
        if ($isArchived) {
            $statusBannerHtml = <<<HTML
            <div class="alert alert-secondary mb-4 p-3" role="alert" style="border-left: 5px solid #6c757d; background-color: #f8f9fa;">
                <h6 class="alert-heading font-weight-bold text-muted mb-1">📁 ARCHIVED APPLICATION NOTICE</h6>
                <p class="mb-0 text-dark">Online applications for {$examName} {$year} have officially closed. This step-by-step procedural reference is retained for candidates checking previous submission records or preparing for upcoming cycles.</p>
            </div>
HTML;
        } elseif ($phase === 'APPLICATION_CORRECTION') {
            $statusBannerHtml = <<<HTML
            <div class="alert alert-info mb-4 p-3" role="alert" style="border-left: 5px solid #0dcaf0; background-color: #f0fdf4;">
                <div class="d-flex align-items-center">
                    <span class="me-2" style="font-size: 1.5rem;">✏️</span>
                    <div>
                        <h6 class="alert-heading font-weight-bold text-primary mb-1">APPLICATION CORRECTION WINDOW CURRENTLY ACTIVE</h6>
                        <p class="mb-0 text-dark">The statutory application correction facility is currently live on the official portal. Registered candidates can log in to edit allowed particulars before the correction deadline.</p>
                    </div>
                </div>
            </div>
HTML;
        } else {
            $statusBannerHtml = <<<HTML
            <div class="alert alert-success mb-4 p-3" role="alert" style="border-left: 5px solid #198754; background-color: #f0fdf4;">
                <div class="d-flex align-items-center">
                    <span class="me-2" style="font-size: 1.5rem;">🟢</span>
                    <div>
                        <h6 class="alert-heading font-weight-bold text-success mb-1">ONLINE APPLICATION WINDOW IS LIVE</h6>
                        <p class="mb-0 text-dark">Eligible candidates can submit their online recruitment/entrance application form directly on the official commission portal.</p>
                    </div>
                </div>
            </div>
HTML;
        }

        // Facts Table
        $appStart = !empty($facts['application_start']) ? date('d F Y', strtotime($facts['application_start'])) : 'See Official Circular';
        $appEnd   = !empty($facts['application_end']) ? date('d F Y', strtotime($facts['application_end'])) : 'See Official Circular';
        $feeGen   = isset($facts['application_fee_general']) && is_numeric($facts['application_fee_general']) ? '₹' . number_format((int)$facts['application_fee_general']) : 'As per category rules';

        $leadPhrase1 = htmlspecialchars($kw['phrases'][0] ?? "{$examName} form kaise bhare", ENT_QUOTES, 'UTF-8');
        $leadPhrase2 = htmlspecialchars($kw['phrases'][1] ?? "online apply kaise kare step by step", ENT_QUOTES, 'UTF-8');

        return <<<HTML
<article class="application-guide-module my-4">
    <header class="guide-header mb-4">
        <h1 class="h2 font-weight-bold text-dark">{$primaryH1}: Online Apply & Step-by-Step Registration Guide</h1>
        <p class="lead text-secondary mt-2">
            Agar aap jaan-na chahte hain ki <strong>{$leadPhrase1}</strong> aur <strong>{$leadPhrase2}</strong>, to yeh comprehensive statutory guide aapko official portal ke pure process ko asaan steps mein batati hai.
        </p>
    </header>

    {$statusBannerHtml}

    <section class="quick-facts-table mb-4">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr><th colspan="2" class="text-primary font-weight-bold">📌 {$examName} {$year} Application Summary</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Conducting Authority</strong></td><td>{$authCode}</td></tr>
                    <tr><td><strong>Application Window</strong></td><td>{$appStart} to {$appEnd}</td></tr>
                    <tr><td><strong>General / OBC Application Fee</strong></td><td>{$feeGen}</td></tr>
                    <tr><td><strong>Official Application Portal</strong></td><td><a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" class="text-primary font-weight-bold">{$portalUrl} ↗</a></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="alert alert-warning mb-4" role="alert" style="border-left: 5px solid #ffc107; background-color: #fffbeb;">
        <h6 class="alert-heading font-weight-bold text-dark mb-1">⚠️ IMPORTANT INSTRUCTIONS</h6>
        <p class="mb-0 text-dark" style="font-size: 0.95rem;">
            Exact portal interfaces may vary slightly between cycles. Always verify your candidate details against your Matriculation (Class 10) Certificate before submitting, and complete final fee payment before the statutory deadline.
        </p>
    </div>

    <section class="step-by-step-guide mb-4">
        <h3 class="h4 font-weight-bold text-dark mb-3">📋 Step-by-Step Online Application Process</h3>
        
        <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <h5 class="card-title text-primary font-weight-bold">Step 1: Official Portal Registration</h5>
                <p class="card-text text-dark">
                    Sabse pehle official portal (<a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" class="text-primary">{$portalUrl}</a>) par jayein aur "New Candidate Registration" link par click karein. Apna active mobile number aur valid email ID enter karein. System se OTP verify hone ke baad aapka Application Number / Login ID generate ho jayega.
                </p>
            </div>
        </div>

        <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <h5 class="card-title text-primary font-weight-bold">Step 2: Candidate Details & Educational Qualifications</h5>
                <p class="card-text text-dark">
                    Apne Application Number aur password se login karein. Apna Name, Father's Name, Mother's Name, Date of Birth, aur Category (General/OBC/SC/ST/EWS) bilkul Class 10 Marksheet ke according fill karein. Uske baad educational qualifications aur exam centre preferences select karein.
                </p>
            </div>
        </div>

        <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <h5 class="card-title text-primary font-weight-bold">Step 3: Upload Scanned Photo & Signature</h5>
                <p class="card-text text-dark">
                    Official bulletin ke prescribed specifications (JPG/JPEG format, standard file size e.g. 10KB to 100KB) ke anusar apna recent clear colour photograph aur black/blue ink signature upload karein. Agar category reservation ya PwBD certificate maanga gaya ho to use prescribed format mein attach karein.
                </p>
            </div>
        </div>

        <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <h5 class="card-title text-primary font-weight-bold">Step 4: Application Fee Payment</h5>
                <p class="card-text text-dark">
                    Net Banking, Debit Card, Credit Card, ya UPI ke through statutory examination fee pay karein. Fee payment successful hone par transaction receipt aur payment reference number save karein.
                </p>
            </div>
        </div>

        <div class="card mb-3 border-0 shadow-sm" style="border-left: 4px solid #198754 !important;">
            <div class="card-body">
                <h5 class="card-title text-success font-weight-bold">Step 5: Final Submission & Confirmation Page Download</h5>
                <p class="card-text text-dark">
                    Form ka complete preview check karein. Final submit button press karne ke baad <strong>Confirmation Page (Printout)</strong> zaroor download aur print karke rakhein. Future admit card download aur verification ke liye Confirmation Page mandatory hota hai.
                </p>
            </div>
        </div>
    </section>

    <section class="portal-cta my-4 text-center p-4 bg-light rounded">
        <h4 class="h5 font-weight-bold text-dark mb-2">Ready to Apply or Correct Form?</h4>
        <p class="text-muted mb-3">Visit the statutory {$authCode} portal directly for live submissions and notices.</p>
        <a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg px-4 font-weight-bold">
            Open Official {$authCode} Portal ↗
        </a>
    </section>
</article>
HTML;
    }
}
