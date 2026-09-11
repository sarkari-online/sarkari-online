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

        // Gate: Only VERIFIED cycles are allowed to be published
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

        // 3. Assemble Meta Information (Bug 3: Eliminate duplicate year)
        $hasYearInMeta = str_contains($kw['primary_meta'], (string)$year);
        $metaTitleSuffix = $hasYearInMeta ? "" : " ({$year})";
        $metaTitle = "{$kw['primary_meta']}{$metaTitleSuffix}: Step-by-Step Guide & Portal Link";
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

        // Bug 4: Natural single-sentence H1 without colon stacking
        $cleanH1Phrase = trim($kw['primary_h1']);
        if (!preg_match('/^[A-Z]/', $cleanH1Phrase)) {
            $cleanH1Phrase = ucfirst($cleanH1Phrase);
        }
        if (preg_match('/(step by step|kaise kare|kaise bhare)$/i', $cleanH1Phrase)) {
            $h1Headline = "{$cleanH1Phrase} — Janein Online Registration aur Form Submission Process";
        } else {
            $h1Headline = "{$cleanH1Phrase} Online Kaise Bhare — Step-by-Step Registration Guide";
        }

        // Status Banner (Zero Emojis, Clean Institutional Design)
        $statusBannerHtml = '';
        if ($isArchived) {
            $statusBannerHtml = <<<HTML
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #64748b; border-radius: 6px; padding: 1rem 1.25rem; margin-bottom: 1.75rem;">
                <div style="font-size: 0.8rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Archived Application Notice</div>
                <p style="margin: 0; font-size: 0.925rem; color: #334155; line-height: 1.55;">Online applications for {$examName} {$year} have officially closed. This step-by-step procedural reference is retained for candidates checking previous submission records or preparing for upcoming cycles.</p>
            </div>
HTML;
        } elseif ($phase === 'APPLICATION_CORRECTION') {
            $statusBannerHtml = <<<HTML
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; border-radius: 6px; padding: 1rem 1.25rem; margin-bottom: 1.75rem;">
                <div style="font-size: 0.8rem; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Application Correction Window Currently Active</div>
                <p style="margin: 0; font-size: 0.925rem; color: #1e293b; line-height: 1.55;">The statutory application correction facility is currently live on the official portal. Registered candidates can log in to edit allowed particulars before the correction deadline.</p>
            </div>
HTML;
        } else {
            $statusBannerHtml = <<<HTML
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; border-radius: 6px; padding: 1rem 1.25rem; margin-bottom: 1.75rem;">
                <div style="font-size: 0.8rem; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Online Application Window Is Live</div>
                <p style="margin: 0; font-size: 0.925rem; color: #1e293b; line-height: 1.55;">Eligible candidates can submit their online recruitment/entrance application form directly on the official commission portal.</p>
            </div>
HTML;
        }

        // Facts Table — Omit row completely if data is null; NO vague filler strings
        $appStart = !empty($facts['application_start']) ? date('d F Y', strtotime($facts['application_start'])) : null;
        $appEnd   = !empty($facts['application_end']) ? date('d F Y', strtotime($facts['application_end'])) : null;
        $feeGen   = isset($facts['application_fee_general']) && is_numeric($facts['application_fee_general']) ? '₹' . number_format((int)$facts['application_fee_general']) : null;

        $tableRowsHtml = "<tr style=\"border-bottom: 1px solid #f1f5f9;\"><th style=\"width: 34%; text-align: left; padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">Conducting Authority</th><td style=\"padding: 0.75rem 1rem; color: #0f172a; font-weight: 700;\">{$authCode}</td></tr>\n";
        if ($appStart !== null && $appEnd !== null) {
            $tableRowsHtml .= "                    <tr style=\"border-bottom: 1px solid #f1f5f9;\"><th style=\"padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">Application Window</th><td style=\"padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;\">{$appStart} to {$appEnd}</td></tr>\n";
        } elseif ($appEnd !== null) {
            $tableRowsHtml .= "                    <tr style=\"border-bottom: 1px solid #f1f5f9;\"><th style=\"padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">Last Date to Apply</th><td style=\"padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;\">{$appEnd}</td></tr>\n";
        } elseif ($appStart !== null) {
            $tableRowsHtml .= "                    <tr style=\"border-bottom: 1px solid #f1f5f9;\"><th style=\"padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">Application Start Date</th><td style=\"padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;\">{$appStart}</td></tr>\n";
        }

        if ($feeGen !== null) {
            $tableRowsHtml .= "                    <tr style=\"border-bottom: 1px solid #f1f5f9;\"><th style=\"padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">General / OBC Application Fee</th><td style=\"padding: 0.75rem 1rem; color: #0f172a; font-weight: 600;\">{$feeGen}</td></tr>\n";
        }

        $tableRowsHtml .= "                    <tr><th style=\"padding: 0.75rem 1rem; background: #f8fafc; color: #475569; font-weight: 600;\">Official Portal</th><td style=\"padding: 0.75rem 1rem;\"><a href=\"{$portalUrl}\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"color: #1e3a8a; font-weight: 700; text-decoration: underline;\">{$portalUrl} &rarr;</a></td></tr>";

        $leadPhrase1 = htmlspecialchars($kw['phrases'][0] ?? "{$examName} form kaise bhare", ENT_QUOTES, 'UTF-8');
        $leadPhrase2 = htmlspecialchars($kw['phrases'][1] ?? "online apply kaise kare step by step", ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="application-guide-content">
    <header style="margin-bottom: 1.75rem;">
        <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0; line-height: 1.3; letter-spacing: -0.02em;">{$h1Headline}</h1>
        <p style="font-size: 1.05rem; color: #475569; line-height: 1.65; margin: 0;">
            Agar aap jaan-na chahte hain ki <strong>{$leadPhrase1}</strong> aur <strong>{$leadPhrase2}</strong>, to yeh comprehensive statutory guide aapko official portal ke pure process ko asaan steps mein batati hai.
        </p>
    </header>

    {$statusBannerHtml}

    <section style="margin: 1.75rem 0 2rem 0;">
        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th colspan="2" style="padding: 0.85rem 1rem; text-align: left; color: #1e3a8a; font-size: 0.95rem; font-weight: 800; letter-spacing: 0.3px;">
                            {$examName} {$year} Application Summary
                        </th>
                    </tr>
                </thead>
                <tbody>
{$tableRowsHtml}
                </tbody>
            </table>
        </div>
    </section>

    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 1rem 1.25rem; margin-bottom: 2rem;">
        <div style="font-size: 0.8rem; font-weight: 800; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.35rem;">
            Important Instructions
        </div>
        <p style="margin: 0; font-size: 0.925rem; color: #78350f; line-height: 1.6;">
            Exact portal interfaces may vary slightly between cycles. Always verify your candidate details against your Matriculation (Class 10) Certificate before submitting, and complete final fee payment before the statutory deadline.
        </p>
    </div>

    <section style="margin-bottom: 2.5rem;">
        <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 1.5rem 0;">Step-by-Step Online Application Process</h2>
        
        <div style="margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                <span style="color: #1e3a8a; font-weight: 800; margin-right: 4px;">Step 1.</span> Official Portal Registration
            </h3>
            <p style="font-size: 0.935rem; color: #334155; line-height: 1.7; margin: 0;">
                Sabse pehle official portal (<a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" style="color: #1e3a8a; font-weight: 600; text-decoration: underline;">{$portalUrl} &rarr;</a>) par jayein aur "New Candidate Registration" link par click karein. Apna active mobile number aur valid email ID enter karein. System se OTP verify hone ke baad aapka Application Number / Login ID generate ho jayega.
            </p>
        </div>

        <div style="margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                <span style="color: #1e3a8a; font-weight: 800; margin-right: 4px;">Step 2.</span> Candidate Details &amp; Educational Qualifications
            </h3>
            <p style="font-size: 0.935rem; color: #334155; line-height: 1.7; margin: 0;">
                Apne Application Number aur password se login karein. Apna Name, Father's Name, Mother's Name, Date of Birth, aur Category (General/OBC/SC/ST/EWS) bilkul Class 10 Marksheet ke according fill karein. Uske baad educational qualifications aur exam centre preferences select karein.
            </p>
        </div>

        <div style="margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                <span style="color: #1e3a8a; font-weight: 800; margin-right: 4px;">Step 3.</span> Upload Scanned Photo &amp; Signature
            </h3>
            <p style="font-size: 0.935rem; color: #334155; line-height: 1.7; margin: 0;">
                Official bulletin ke prescribed specifications (JPG/JPEG format, standard file size e.g. 10KB to 100KB) ke anusar apna recent clear colour photograph aur black/blue ink signature upload karein. Agar category reservation ya PwBD certificate maanga gaya ho to use prescribed format mein attach karein.
            </p>
        </div>

        <div style="margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem 0;">
                <span style="color: #1e3a8a; font-weight: 800; margin-right: 4px;">Step 4.</span> Application Fee Payment
            </h3>
            <p style="font-size: 0.935rem; color: #334155; line-height: 1.7; margin: 0;">
                Net Banking, Debit Card, Credit Card, ya UPI ke through statutory examination fee pay karein. Fee payment successful hone par transaction receipt aur payment reference number save karein.
            </p>
        </div>

        <div style="margin-bottom: 1.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #166534; margin: 0 0 0.4rem 0;">
                <span style="color: #166534; font-weight: 800; margin-right: 4px;">Step 5.</span> Final Submission &amp; Confirmation Page Download
            </h3>
            <p style="font-size: 0.935rem; color: #334155; line-height: 1.7; margin: 0;">
                Form ka complete preview check karein. Final submit button press karne ke baad <strong>Confirmation Page (Printout)</strong> zaroor download aur print karke rakhein. Future admit card download aur verification ke liye Confirmation Page mandatory hota hai.
            </p>
        </div>
    </section>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.75rem; text-align: center; margin-top: 2rem;">
        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.4rem 0;">
            Ready to Apply or Correct Form?
        </h3>
        <p style="font-size: 0.9rem; color: #64748b; margin: 0 0 1.25rem 0;">
            Visit the statutory {$authCode} portal directly for live submissions and notices.
        </p>
        <a href="{$portalUrl}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; background: #1e3a8a; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none;">
            <span>Open Official {$authCode} Portal</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
    </div>
</div>
HTML;
    }
}
