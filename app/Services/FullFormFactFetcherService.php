<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use Throwable;

/**
 * FullFormFactFetcherService
 *
 * Resilient multi-tier fact fetcher:
 * Tier 1: Grounded Google Search via Gemini for live statutory discovery & evidence URLs.
 * Tier 2: Direct Structured JSON fallback via Gemini knowledge base (avoids quota exhaustion).
 *
 * Rules:
 * 1. Zero Hallucination: Unknown facts must be returned as null.
 * 2. Absolute ban on filler text: 'varies', 'check official site', 'approx', 'TBA', 'awaited'.
 * 3. Confidence is 'VERIFIED' with official evidence URL (.gov.in, .nic.in, PIB, etc.).
 * 4. Captures exact diagnostic errors in $lastError for transparent CLI visibility.
 */
class FullFormFactFetcherService
{
    private Gemini $gemini;
    public ?string $lastError = null;

    private const FORBIDDEN_PLACEHOLDERS = [
        '/\bvaries\b/i',
        '/\bcheck official site\b/i',
        '/\bapprox\b/i',
        '/\bapproximate\b/i',
        '/\bnot specified\b/i',
        '/\bnot available\b/i',
        '/\btba\b/i',
        '/\bto be announced\b/i',
        '/\bawaited\b/i',
        '/\bcoming soon\b/i',
        '/\byet to be\b/i',
        '/\bdepending on\b/i',
        '/\bas per norms\b/i',
    ];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Fetch grounded facts for a glossary term entry.
     *
     * @param array $fullFormRow Row from glossary_terms
     * @return array Normalized payload ready for full_form_entity_facts
     */
    public function fetch(array $fullFormRow): array
    {
        $termId   = (int)($fullFormRow['id'] ?? 0);
        $acronym  = trim((string)($fullFormRow['acronym'] ?? ''));
        $fullEn   = trim((string)($fullFormRow['full_form_en'] ?? ''));
        $category = trim((string)($fullFormRow['category'] ?? ''));
        $body     = trim((string)($fullFormRow['conducting_body'] ?? ''));

        $today = date('d F Y');

        $prompt = "You are an expert Indian Government Compensation & Administrative Analyst for Sarkari.online. Today: {$today}.\n\n"
            . "TARGET ENTITY / POST:\n"
            . "Acronym: {$acronym}\n"
            . "Full Name: {$fullEn}\n"
            . "Conducting Authority / Ministry: {$body}\n"
            . "Category: {$category}\n\n"
            . "TASK:\n"
            . "Provide the official salary structure, pay level (under 7th CPC or PSU wage board), "
            . "career progression ladder, and 4 to 6 authentic high-intent FAQs for this post / cadre in India.\n\n"
            . "DATA EXTRACTION RULES:\n"
            . "1. pay_level_7cpc: e.g. 'Level 7' (or 'Level 10', 'Scale I (JMGS-I)', 'Executive Grade E-2'). If this entity is an organization/exam with no single salary scale, set to null.\n"
            . "2. basic_pay_min & basic_pay_max: Integer basic pay values in INR (e.g. 56100 and 177500 for Level 10; 44900 and 142400 for Level 7; 36000 and 63840 for Bank PO). Numbers only, no symbols.\n"
            . "3. gross_salary_min & gross_salary_max: Approximate monthly gross/in-hand salary range in INR (e.g. 70000 and 95000) based on prevailing DA (~50%), HRA, and allowances.\n"
            . "4. allowances_summary: Short string listing official allowance types (e.g. 'DA, HRA, Transport Allowance, Medical Allowance, Children Education Allowance'). Names only.\n"
            . "5. career_growth_summary: 1-2 factual sentences outlining the promotion ladder (e.g. 'Appointed as Assistant Section Officer (Level 7) with promotional avenues to Section Officer (Level 8/10), Under Secretary (Level 11), and Deputy Secretary (Level 12).').\n"
            . "6. faqs: Exactly 4 to 6 informative Q&A objects answering key queries like 'What is the salary of {$acronym}?', 'What is the age limit for {$acronym}?', 'What is the selection process?', 'What does {$acronym} stand for?'.\n"
            . "7. evidence_url: Official .gov.in, .nic.in, PIB, or statutory portal URL (e.g. upsc.gov.in, ssc.gov.in, ibps.in) confirming the pay scale or norms.\n\n"
            . "ABSOLUTE ANTI-HALLUCINATION RULES:\n"
            . "- If a fact is unverified or not applicable, return null. NEVER write placeholder strings like 'Varies', 'Check official site', 'Approx', 'TBA', or 'Not specified'.\n"
            . "- Return strictly valid JSON:\n"
            . "{\n"
            . "  \"pay_level_7cpc\": string or null,\n"
            . "  \"basic_pay_min\": integer or null,\n"
            . "  \"basic_pay_max\": integer or null,\n"
            . "  \"gross_salary_min\": integer or null,\n"
            . "  \"gross_salary_max\": integer or null,\n"
            . "  \"allowances_summary\": string or null,\n"
            . "  \"career_growth_summary\": string or null,\n"
            . "  \"faqs\": [\n"
            . "    {\"q\": \"Question 1?\", \"a\": \"Detailed factual answer.\"}\n"
            . "  ],\n"
            . "  \"evidence_url\": string or null\n"
            . "}";

        $rawResponse = $this->callGeminiWithRetry($prompt, $termId, $acronym);

        if ($rawResponse === null) {
            Logger::warning("FullFormFactFetcherService: Both grounded search and fallback failed for '{$acronym}' (#{$termId}). Setting UNAVAILABLE.");
            $payload = $this->buildUnavailablePayload($termId);
            $payload['reason'] = $this->lastError ?: 'AI call returned null';
            return $payload;
        }

        $normalized = $this->normalizeAndValidate($rawResponse, $termId, $acronym);
        if ($normalized['confidence'] === 'UNAVAILABLE') {
            $normalized['reason'] = $this->lastError ?: 'Entity has no salary scale or verifiable facts';
        }

        return $normalized;
    }

    /**
     * Executes grounded Gemini query with graceful fallback to generateJson.
     */
    private function callGeminiWithRetry(string $prompt, int $termId, string $acronym): ?array
    {
        $this->lastError = null;

        // --- TIER 1: Grounded Google Search ---
        try {
            $response = $this->gemini->generateGrounded($prompt, ['googleSearch'], [
                'stage'       => 'full_form_fact_fetch',
                'temperature' => 0.1,
            ]);

            $rawText = $response['text'] ?? '';
            if (!empty($rawText)) {
                $parsed = Gemini::extractAndRepairJson($rawText);
                if (is_array($parsed)) {
                    if (empty($parsed['evidence_url']) && !empty($response['grounding_metadata']['groundingChunks'])) {
                        foreach ($response['grounding_metadata']['groundingChunks'] as $chunk) {
                            $webUrl = $chunk['web']['uri'] ?? null;
                            if ($webUrl && $this->isGovOrOfficialDomain($webUrl)) {
                                $parsed['evidence_url'] = $webUrl;
                                break;
                            }
                        }
                    }
                    $this->lastError = null;
                    return $parsed;
                } else {
                    $this->lastError = "Grounded text could not be parsed as JSON: " . substr($rawText, 0, 150);
                }
            } else {
                $this->lastError = "Grounded search returned empty text";
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $this->lastError = "Grounded error: " . $msg;
            Logger::warning("FullFormFactFetcherService: Grounded attempt failed for '{$acronym}': " . $msg);
        }

        // --- TIER 2: Fast Structured JSON Fallback (uses Gemini's deep knowledge base, avoids search tool quota) ---
        try {
            $jsonPrompt = $prompt . "\n\nCRITICAL: Return ONLY the JSON object. Do not include markdown or conversational commentary.";
            $response = $this->gemini->generateJson($jsonPrompt, [
                'stage'       => 'full_form_fact_fallback',
                'temperature' => 0.05,
            ]);

            if (!empty($response['data']) && is_array($response['data'])) {
                Logger::info("FullFormFactFetcherService: Successfully resolved '{$acronym}' via JSON generation.");
                $this->lastError = null;
                return $response['data'];
            }
        } catch (Throwable $e2) {
            $msg2 = $e2->getMessage();
            $this->lastError = "Fallback error: " . $msg2 . " | Prev: " . ($this->lastError ?: 'none');
            Logger::error("FullFormFactFetcherService: Both grounded and fallback failed for '{$acronym}': " . $msg2);
        }

        return null;
    }

    /**
     * Normalizes and validates facts according to strict governance criteria.
     */
    private function normalizeAndValidate(array $data, int $termId, string $acronym): array
    {
        $payLevel     = $this->cleanString($data['pay_level_7cpc'] ?? null);
        $basicPayMin  = $this->cleanInteger($data['basic_pay_min'] ?? null);
        $basicPayMax  = $this->cleanInteger($data['basic_pay_max'] ?? null);
        $grossMin     = $this->cleanInteger($data['gross_salary_min'] ?? null);
        $grossMax     = $this->cleanInteger($data['gross_salary_max'] ?? null);
        $allowances   = $this->cleanString($data['allowances_summary'] ?? null);
        $careerGrowth = $this->cleanString($data['career_growth_summary'] ?? null);
        $evidenceUrl  = $this->cleanUrl($data['evidence_url'] ?? null);

        // Sanitize FAQs
        $faqs = [];
        if (!empty($data['faqs']) && is_array($data['faqs'])) {
            foreach ($data['faqs'] as $faq) {
                $q = trim((string)($faq['q'] ?? ''));
                $a = trim((string)($faq['a'] ?? ''));
                if (!empty($q) && !empty($a) && !$this->hasForbiddenPlaceholder($q) && !$this->hasForbiddenPlaceholder($a)) {
                    $faqs[] = [
                        'q' => $q,
                        'a' => $a,
                    ];
                }
            }
        }
        $faqsJson = !empty($faqs) ? $faqs : null;

        // Ensure ranges are logical
        if ($basicPayMin !== null && $basicPayMax !== null && $basicPayMin > $basicPayMax) {
            $temp = $basicPayMin;
            $basicPayMin = $basicPayMax;
            $basicPayMax = $temp;
        }
        if ($grossMin !== null && $grossMax !== null && $grossMin > $grossMax) {
            $temp = $grossMin;
            $grossMin = $grossMax;
            $grossMax = $temp;
        }

        // Determine confidence:
        $hasSalary = ($payLevel !== null || $basicPayMin !== null || $grossMin !== null);
        $hasFaqs   = !empty($faqsJson);
        $isOfficial = $evidenceUrl && $this->isGovOrOfficialDomain($evidenceUrl);

        if ($hasSalary && $isOfficial) {
            $confidence = 'VERIFIED';
        } elseif ($hasSalary) {
            $confidence = 'INFERRED';
        } elseif ($hasFaqs) {
            // Organization/institution (e.g. RRB, SSC, SBI, UGC, NTA) where post salary is not single,
            // but valid FAQs & career progression are present.
            $confidence = $isOfficial ? 'VERIFIED' : 'INFERRED';
            $payLevel = null;
            $basicPayMin = null;
            $basicPayMax = null;
            $grossMin = null;
            $grossMax = null;
            $allowances = null;
        } else {
            $confidence = 'UNAVAILABLE';
            $payLevel = null;
            $basicPayMin = null;
            $basicPayMax = null;
            $grossMin = null;
            $grossMax = null;
            $allowances = null;
        }

        return [
            'full_form_id'          => $termId,
            'pay_level_7cpc'        => $payLevel,
            'basic_pay_min'         => $basicPayMin,
            'basic_pay_max'         => $basicPayMax,
            'gross_salary_min'      => $grossMin,
            'gross_salary_max'      => $grossMax,
            'allowances_summary'    => $allowances,
            'career_growth_summary' => $careerGrowth,
            'faqs_json'             => $faqsJson,
            'evidence_url'          => $evidenceUrl,
            'confidence'            => $confidence,
        ];
    }

    private function cleanString(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $trimmed = trim($val);
        if ($trimmed === '' || $this->hasForbiddenPlaceholder($trimmed)) {
            return null;
        }
        return $trimmed;
    }

    private function cleanInteger(mixed $val): ?int
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_string($val)) {
            $cleaned = preg_replace('/[^\d]/', '', $val);
            if ($cleaned === '') {
                return null;
            }
            $int = (int)$cleaned;
        } elseif (is_numeric($val)) {
            $int = (int)$val;
        } else {
            return null;
        }

        return ($int >= 5000 && $int <= 500000) ? $int : null;
    }

    private function cleanUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $trimmed = trim($url);
        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }
        return $trimmed;
    }

    private function hasForbiddenPlaceholder(string $text): bool
    {
        foreach (self::FORBIDDEN_PLACEHOLDERS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    private function isGovOrOfficialDomain(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (empty($host)) {
            return false;
        }
        return str_ends_with($host, '.gov.in')
            || str_ends_with($host, '.nic.in')
            || str_ends_with($host, '.ac.in')
            || str_ends_with($host, 'pib.gov.in')
            || str_ends_with($host, 'ssc.gov.in')
            || str_ends_with($host, 'upsc.gov.in')
            || str_ends_with($host, 'rrbcdg.gov.in')
            || str_ends_with($host, 'ibps.in')
            || str_ends_with($host, 'sbi.co.in')
            || str_ends_with($host, 'rbi.org.in');
    }

    private function buildUnavailablePayload(int $termId): array
    {
        return [
            'full_form_id'          => $termId,
            'pay_level_7cpc'        => null,
            'basic_pay_min'         => null,
            'basic_pay_max'         => null,
            'gross_salary_min'      => null,
            'gross_salary_max'      => null,
            'allowances_summary'    => null,
            'career_growth_summary' => null,
            'faqs_json'             => null,
            'evidence_url'          => null,
            'confidence'            => 'UNAVAILABLE',
        ];
    }
}
