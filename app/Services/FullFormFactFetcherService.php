<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Helpers\Logger;
use Throwable;

/**
 * FullFormFactFetcherService
 *
 * Uses Grounded Gemini with Google Search to fetch verified salary structures (7th CPC / PSU),
 * career growth hierarchy, and high-intent FAQs for Indian govt & exam full forms.
 *
 * Rules:
 * 1. Zero Hallucination: Unknown facts must be returned as null.
 * 2. Absolute ban on filler text: 'varies', 'check official site', 'approx', 'TBA', 'awaited'.
 * 3. Confidence is 'VERIFIED' only with official evidence URL (.gov.in, .nic.in, PIB, etc.).
 * 4. Resilient 429 rate limit retry handling with 45s backoff.
 */
class FullFormFactFetcherService
{
    private Gemini $gemini;

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
            . "Using Google Search Grounding, find the OFFICIAL salary structure, pay level (under 7th CPC or PSU wage board), "
            . "career progression ladder, and 4 to 6 authentic high-intent FAQs for this post / cadre in India.\n\n"
            . "DATA EXTRACTION RULES:\n"
            . "1. pay_level_7cpc: e.g. 'Level 7' (or 'Level 10', 'Scale I (JMGS-I)', 'Executive Grade E-2'). If this entity is an organization/exam with no single salary scale, set to null.\n"
            . "2. basic_pay_min & basic_pay_max: Integer basic pay values in INR (e.g. 44900 and 142400 for Level 7). If only a single starting basic pay is verified, provide that as min & max. Numbers only, no symbols.\n"
            . "3. gross_salary_min & gross_salary_max: Approximate gross/in-hand monthly salary range in INR (e.g. 70000 and 85000) based on prevailing DA (approx 50-53%), HRA (X/Y/Z cities), and allowances.\n"
            . "4. allowances_summary: Short string mentioning official allowance types (e.g. 'DA, HRA, Transport Allowance, Medical Allowance, Children Education Allowance'). Names only.\n"
            . "5. career_growth_summary: 1-2 factual sentences outlining the promotion ladder (e.g. 'Appointed as Assistant Section Officer (Level 7) with promotional avenues to Section Officer (Level 8/10), Under Secretary (Level 11), and Deputy Secretary (Level 12).').\n"
            . "6. faqs: Exactly 4 to 6 informative Q&A objects answering key queries like 'What is the salary of {$acronym}?', 'What is the age limit for {$acronym}?', 'What is the selection process?', 'What does {$acronym} stand for?'.\n"
            . "7. evidence_url: Official .gov.in, .nic.in, PIB, or statutory body circular/notification URL from which the pay scale or norms are verified.\n\n"
            . "ABSOLUTE ANTI-HALLUCINATION RULES:\n"
            . "- If a fact is unverified or not applicable, return null. NEVER write placeholder strings like 'Varies', 'Check official site', 'Approx', 'TBA', or 'Not specified'.\n"
            . "- Output must be strictly valid JSON matching this schema:\n"
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
            Logger::warning("FullFormFactFetcherService: Grounded search failed for '{$acronym}' (#{$termId}). Setting UNAVAILABLE.");
            return $this->buildUnavailablePayload($termId);
        }

        return $this->normalizeAndValidate($rawResponse, $termId, $acronym);
    }

    /**
     * Executes grounded Gemini query with 429 rate limit backoff.
     */
    private function callGeminiWithRetry(string $prompt, int $termId, string $acronym): ?array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $this->gemini->generateGrounded($prompt, ['googleSearch'], [
                    'stage'       => 'full_form_fact_fetch',
                    'temperature' => 0.1,
                ]);

                $rawText = $response['text'] ?? '';
                if (empty($rawText)) {
                    Logger::warning("FullFormFactFetcherService: Empty response for '{$acronym}' (#{$termId}) on attempt {$attempt}");
                    return null;
                }

                $parsed = Gemini::extractAndRepairJson($rawText);
                if (is_array($parsed)) {
                    // Try to attach top grounding source url if evidence_url is missing from json
                    if (empty($parsed['evidence_url']) && !empty($response['grounding_metadata']['groundingChunks'])) {
                        foreach ($response['grounding_metadata']['groundingChunks'] as $chunk) {
                            $webUrl = $chunk['web']['uri'] ?? null;
                            if ($webUrl && $this->isGovOrOfficialDomain($webUrl)) {
                                $parsed['evidence_url'] = $webUrl;
                                break;
                            }
                        }
                    }
                    return $parsed;
                }

                Logger::warning("FullFormFactFetcherService: Could not parse JSON for '{$acronym}' (#{$termId}) on attempt {$attempt}");
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if ((str_contains($msg, '429') || str_contains(strtolower($msg), 'quota')) && $attempt === 1) {
                    Logger::warning("FullFormFactFetcherService: Rate limit (429) on '{$acronym}'. Sleeping 45s before retry...");
                    sleep(45);
                    continue;
                }
                Logger::error("FullFormFactFetcherService error attempt {$attempt} for '{$acronym}': " . $msg);
            }
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
        // VERIFIED requires valid evidence URL from official/reputed domain AND at least basic pay / pay level
        $hasSalary = ($payLevel !== null || $basicPayMin !== null || $grossMin !== null);
        $isOfficial = $evidenceUrl && $this->isGovOrOfficialDomain($evidenceUrl);

        if ($hasSalary && $isOfficial) {
            $confidence = 'VERIFIED';
        } elseif ($hasSalary) {
            $confidence = 'INFERRED';
        } else {
            // No salary info found — entity is likely an exam, scheme, or non-employment term
            $confidence = 'UNAVAILABLE';
            // Clear partial salary figures to avoid rendering an incomplete table
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
            // Remove currency symbols, commas, spaces
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

        // Realistic sanity bounds for Indian government & public sector compensation
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
