<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\Gemini;
use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use Throwable;

/**
 * GlossaryPipelineService
 * High-authority, autonomous pipeline for Indian Government Full Forms.
 * Handles AI generation, schema locking, facts insertion, candidate management,
 * and 5-slot daily autonomous publishing.
 */
class GlossaryPipelineService
{
    private Gemini $gemini;
    private const STATE_FILE_RELATIVE = '/storage/cache/glossary_scheduler_state.json';
    public const DEFAULT_SLOTS = ['09:30', '13:00', '16:30', '19:30', '22:00'];

    public function __construct(?Gemini $gemini = null)
    {
        $this->gemini = $gemini ?: new Gemini();
    }

    /**
     * Generate full detail and publish a term to glossary_terms & full_form_entity_facts
     */
    public function generateAndPublish(string $acronym, ?string $hintFullForm = null, ?string $hintCategory = null): array
    {
        $acronym = trim($acronym);
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $acronym));
        $slug = trim($slug, '-');

        // 1. Check if term already exists in published glossary
        $existing = Database::fetchOne(
            "SELECT id, slug, full_form_en FROM glossary_terms WHERE slug = :slug OR acronym = :acr LIMIT 1",
            ['slug' => $slug, 'acr' => $acronym]
        );
        if ($existing) {
            return [
                'success' => true,
                'already_exists' => true,
                'id' => (int)$existing['id'],
                'slug' => $existing['slug'],
                'url' => url('full-forms/' . $existing['slug'] . '/'),
                'full_form_en' => $existing['full_form_en'],
                'message' => "Term '{$acronym}' already exists in live glossary."
            ];
        }

        Logger::info("GlossaryPipelineService: Initiating AI generation for '{$acronym}'");

        // 2. Fetch authoritative expansion and details via Gemini AI
        $generated = $this->callGeminiForFullForm($acronym, $hintFullForm, $hintCategory);

        if (!$generated || empty($generated['full_form_en'])) {
            Logger::error("GlossaryPipelineService: Failed to generate valid content for '{$acronym}'");
            return [
                'success' => false,
                'error' => "Could not generate verified government details for '{$acronym}'. Please verify spelling."
            ];
        }

        // 3. Prepare Term Data
        $fullFormEn = Sanitizer::string($generated['full_form_en']);
        $fullFormHi = !empty($generated['full_form_hi']) ? Sanitizer::string($generated['full_form_hi']) : null;
        $category = in_array($generated['category'] ?? '', ['civil_services', 'defence', 'banking', 'railway', 'police', 'teaching', 'engineering', 'medical', 'entrance'], true)
            ? $generated['category']
            : ($hintCategory ?: 'civil_services');
        $conductingBody = !empty($generated['conducting_body']) ? Sanitizer::string($generated['conducting_body']) : 'Government Statutory Authority';
        $officialPortal = !empty($generated['official_portal']) && filter_var($generated['official_portal'], FILTER_VALIDATE_URL) ? $generated['official_portal'] : 'https://india.gov.in';
        $overview = Sanitizer::html($generated['overview'] ?? "Official statutory recruitment entity for {$fullFormEn}.");
        $eligibility = !empty($generated['eligibility_criteria']) ? Sanitizer::html($generated['eligibility_criteria']) : null;
        $selection = !empty($generated['selection_process']) ? Sanitizer::html($generated['selection_process']) : null;
        $syllabus = !empty($generated['syllabus_snapshot']) ? Sanitizer::html($generated['syllabus_snapshot']) : null;
        $letter = strtoupper(substr($acronym, 0, 1));
        if (!preg_match('/^[A-Z]$/', $letter)) {
            $letter = 'A';
        }

        // Find potential matching article for internal link
        $matchedArticleSlug = null;
        try {
            $matchedArt = Database::fetchOne(
                "SELECT slug FROM articles WHERE status = 'published' AND (title LIKE :acr OR title LIKE :fn) ORDER BY published_at DESC LIMIT 1",
                ['acr' => '%' . $acronym . '%', 'fn' => '%' . $fullFormEn . '%']
            );
            if ($matchedArt) {
                $matchedArticleSlug = $matchedArt['slug'];
            }
        } catch (Throwable $e) {}

        // 4. Insert into glossary_terms
        $termInsert = [
            'acronym' => $acronym,
            'slug' => $slug,
            'letter' => $letter,
            'full_form_en' => $fullFormEn,
            'full_form_hi' => $fullFormHi,
            'category' => $category,
            'conducting_body' => $conductingBody,
            'official_portal' => $officialPortal,
            'overview' => $overview,
            'eligibility_criteria' => $eligibility,
            'selection_process' => $selection,
            'syllabus_snapshot' => $syllabus,
            'related_article_slug' => $matchedArticleSlug,
            'last_reviewed_at' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $termId = (int)Database::insert('glossary_terms', $termInsert);
        if ($termId <= 0) {
            return ['success' => false, 'error' => "Database insertion failed for glossary_terms."];
        }

        // 5. Insert into full_form_entity_facts
        $payLevel = !empty($generated['pay_level_7cpc']) ? Sanitizer::string($generated['pay_level_7cpc']) : null;
        $basicMin = !empty($generated['basic_pay_min']) && is_numeric($generated['basic_pay_min']) ? (int)$generated['basic_pay_min'] : null;
        $basicMax = !empty($generated['basic_pay_max']) && is_numeric($generated['basic_pay_max']) ? (int)$generated['basic_pay_max'] : null;
        $grossMin = !empty($generated['gross_salary_min']) && is_numeric($generated['gross_salary_min']) ? (int)$generated['gross_salary_min'] : null;
        $grossMax = !empty($generated['gross_salary_max']) && is_numeric($generated['gross_salary_max']) ? (int)$generated['gross_salary_max'] : null;
        $allowances = !empty($generated['allowances_summary']) ? Sanitizer::string($generated['allowances_summary']) : null;
        $growth = !empty($generated['career_growth_summary']) ? Sanitizer::string($generated['career_growth_summary']) : null;
        
        $faqsJson = null;
        if (!empty($generated['faqs']) && is_array($generated['faqs'])) {
            $cleanedFaqs = [];
            foreach ($generated['faqs'] as $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    $cleanedFaqs[] = [
                        'question' => Sanitizer::string($faq['question']),
                        'answer' => Sanitizer::string($faq['answer'])
                    ];
                }
            }
            if (!empty($cleanedFaqs)) {
                $faqsJson = json_encode($cleanedFaqs, JSON_UNESCAPED_UNICODE);
            }
        }

        try {
            Database::insert('full_form_entity_facts', [
                'full_form_id' => $termId,
                'pay_level_7cpc' => $payLevel,
                'basic_pay_min' => $basicMin,
                'basic_pay_max' => $basicMax,
                'gross_salary_min' => $grossMin,
                'gross_salary_max' => $grossMax,
                'allowances_summary' => $allowances,
                'career_growth_summary' => $growth,
                'faqs_json' => $faqsJson,
                'evidence_url' => $officialPortal,
                'confidence' => 'VERIFIED',
                'last_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            Logger::error("GlossaryPipelineService: facts insert warning: " . $e->getMessage());
        }

        // 6. Update candidate status if was in queue
        try {
            Database::execute(
                "UPDATE glossary_candidate_terms SET status = 'verified', reviewed_at = NOW() WHERE acronym = :acr",
                ['acr' => $acronym]
            );
        } catch (Throwable $e) {}

        // 7. Update scheduler state log
        $this->recordPublicationInState($acronym);

        Logger::info("GlossaryPipelineService: Successfully published term #{$termId} '{$acronym}' ({$fullFormEn})");

        return [
            'success' => true,
            'id' => $termId,
            'acronym' => $acronym,
            'slug' => $slug,
            'url' => url('full-forms/' . $slug . '/'),
            'full_form_en' => $fullFormEn,
            'full_form_hi' => $fullFormHi,
            'category' => $category,
            'conducting_body' => $conductingBody
        ];
    }

    /**
     * AI Generation with strict prompt constraints
     */
    private function callGeminiForFullForm(string $acronym, ?string $hintFullForm, ?string $hintCategory): ?array
    {
        $hintText = '';
        if (!empty($hintFullForm)) {
            $hintText .= " Hint full form: \"{$hintFullForm}\".";
        }
        if (!empty($hintCategory)) {
            $hintText .= " Category hint: \"{$hintCategory}\".";
        }

        $prompt = <<<PROMPT
You are the Chief Statutory Lexicographer for Sarkari.online, an authoritative public portal for Indian government examinations, commission gazettes, and public sector employment.

TARGET ACRONYM: "{$acronym}"{$hintText}

Generate 100% FACTUAL, accurate, gazette-style data for this Indian government examination, public commission, administrative post, or PSU abbreviation.

STRICT HUMAN-EDITORIAL CONSTRAINTS:
1. OBJECTIVE 3RD-PERSON JOURNALISTIC TONE:
   - Write strictly in neutral, authoritative 3rd-person voice (like The Hindu, Indian Express, or Jagran Josh).
   - NEVER use first-person coaching claims: "I've seen many candidates get rejected", "In my experience", "Don't underestimate", "It's a classic mistake", "The interview panel isn't looking for bookish knowledge", "Don't take the Group Task lightly", "Every graduate dreams of joining".
   - State official criteria directly and factually. No dramatic warnings or motivational fluff.

2. HIGH BURSTINESS & NATURAL CADENCE:
   - Mix concise sentences with informative factual sentences.
   - Use natural contractions where appropriate (don't, it's, haven't).

3. STRICT CLICHÉ BLACKLIST (ZERO TOLERANCE):
   - NEVER use: "digital governance initiative", "streamline the recruitment process", "centralized repository", "eliminate redundancy", "reflecting the government's commitment to", "fosters transparency", "crucial step", "pivotal role", "serves as a testament to", "in today's digital era", "without further ado", "traffic is insane", "server traffic".

STRICT FIELD SPECIFICATIONS:
1. "full_form_en": The exact official expansion in English.
2. "full_form_hi": The exact authentic Hindi translation and meaning (शुद्ध हिंदी अनुवाद).
3. "category": EXACTLY ONE of: ["civil_services", "defence", "banking", "railway", "police", "teaching", "engineering", "medical", "entrance"].
4. "conducting_body": The exact Ministry, Commission, or Exam Board (e.g. "Ministry of Petroleum and Natural Gas", "UPSC", "SSC", "NTA", "RRB").
5. "official_portal": Official .gov.in, .nic.in, or statutory agency website URL.
6. "overview": 100-140 words in authoritative, neutral gazette voice. Detail the organisation/post's establishment, headquarters, parent ministry, statutory mandate, and operational role. NO personal coaching advice.
7. "eligibility_criteria": 60-90 words stating official educational qualifications (degree/discipline), minimum marks (General vs reserved categories), and official age limits with standard statutory relaxations. Strictly factual.
8. "selection_process": 60-90 words detailing the official gazetted recruitment stages (e.g. CBT/GATE shortlisting, Group Discussion/Interview, Document Verification, and Medical Examination). Strictly procedural.
9. "syllabus_snapshot": 50-80 words listing the official examination subjects and key technical/general aptitude domains. Strictly factual.
10. Salary Details (if a job post or cadre):
    - "pay_level_7cpc": Pay Matrix Level e.g. "Level 3 (7th CPC)" or "Executive Scale E-2" (or null if statutory board)
    - "basic_pay_min": Integer e.g. 21700 (or null)
    - "basic_pay_max": Integer e.g. 69100 (or null)
    - "gross_salary_min": Integer e.g. 35000 (or null)
    - "gross_salary_max": Integer e.g. 42000 (or null)
    - "allowances_summary": "DA, HRA, Transport Allowance, Medical Benefits"
    - "career_growth_summary": 40-70 words on official promotion hierarchy.
11. "faqs": Exactly 3 factual administrative FAQs with concise answers:
    - FAQ 1: What is the full form of {$acronym} in Hindi?
    - FAQ 2: What is the minimum qualification and age limit for {$acronym}?
    - FAQ 3: What is the selection process and salary scale for {$acronym}?

Return ONLY valid JSON matching this exact structure:
{
  "acronym": "{$acronym}",
  "full_form_en": "string",
  "full_form_hi": "string",
  "category": "police",
  "conducting_body": "string",
  "official_portal": "https://...",
  "overview": "string",
  "eligibility_criteria": "string",
  "selection_process": "string",
  "syllabus_snapshot": "string",
  "pay_level_7cpc": "string or null",
  "basic_pay_min": 21700,
  "basic_pay_max": 69100,
  "gross_salary_min": 35000,
  "gross_salary_max": 42000,
  "allowances_summary": "string",
  "career_growth_summary": "string",
  "faqs": [
    {"question": "string", "answer": "string"}
  ]
}
PROMPT;

        try {
            $response = $this->gemini->generateJson($prompt, [
                'stage' => 'glossary_generation',
                'temperature' => 0.1,
            ]);
            return $response['data'] ?? null;
        } catch (Throwable $e) {
            Logger::error("GlossaryPipelineService: Gemini generation error for '{$acronym}': " . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CANDIDATE QUEUE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPendingCandidates(int $limit = 50): array
    {
        try {
            return Database::fetchAll(
                "SELECT c.* 
                 FROM glossary_candidate_terms c
                 WHERE c.status IN ('pending', 'approved')
                 ORDER BY c.priority_score DESC, c.occurrence_count DESC, c.id ASC
                 LIMIT " . (int)$limit
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function dismissCandidate(int $id): bool
    {
        try {
            return Database::execute("UPDATE glossary_candidate_terms SET status = 'rejected', reviewed_at = NOW() WHERE id = :id", ['id' => $id]) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function addCandidate(string $acronym, ?string $hintFullForm = null, ?string $category = null, int $priority = 60): bool
    {
        $acronym = strtoupper(trim($acronym));
        try {
            $existing = Database::fetchOne("SELECT id FROM glossary_candidate_terms WHERE acronym = :acr LIMIT 1", ['acr' => $acronym]);
            if ($existing) {
                Database::execute(
                    "UPDATE glossary_candidate_terms SET status = 'pending', priority_score = :p WHERE id = :id",
                    ['p' => $priority, 'id' => $existing['id']]
                );
                return true;
            }

            return Database::insert('glossary_candidate_terms', [
                'acronym' => $acronym,
                'proposed_full_form_en' => $hintFullForm,
                'category' => $category,
                'priority_score' => $priority,
                'source_article_ids' => '[]',
                'status' => 'pending',
                'occurrence_count' => 1,
                'first_seen_at' => date('Y-m-d H:i:s')
            ]) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SCHEDULER & SLOTS ENGINE (5 SLOTS PER DAY)
    // ─────────────────────────────────────────────────────────────────────────

    public static function getStateFilePath(): string
    {
        return dirname(__DIR__, 2) . self::STATE_FILE_RELATIVE;
    }

    public static function getSchedulerState(): array
    {
        $filePath = self::getStateFilePath();
        $defaultState = [
            'enabled' => true,
            'daily_limit' => 5,
            'slots' => self::DEFAULT_SLOTS,
            'today_date' => date('Y-m-d'),
            'executed_slots' => [],
            'published_today' => []
        ];

        if (!file_exists($filePath)) {
            @file_put_contents($filePath, json_encode($defaultState, JSON_PRETTY_PRINT));
            return $defaultState;
        }

        $content = @file_get_contents($filePath);
        $state = json_decode($content ?: '{}', true) ?: [];
        $merged = array_merge($defaultState, $state);

        // Daily reset if date changed
        $today = date('Y-m-d');
        if (($merged['today_date'] ?? '') !== $today) {
            $merged['today_date'] = $today;
            $merged['executed_slots'] = [];
            $merged['published_today'] = [];
            @file_put_contents($filePath, json_encode($merged, JSON_PRETTY_PRINT));
        }

        return $merged;
    }

    public static function updateSchedulerSettings(bool $enabled, ?int $dailyLimit = null, ?array $slots = null): void
    {
        $state = self::getSchedulerState();
        $state['enabled'] = $enabled;
        if ($dailyLimit !== null && $dailyLimit > 0) {
            $state['daily_limit'] = $dailyLimit;
        }
        if ($slots !== null && !empty($slots)) {
            $state['slots'] = $slots;
        }

        @file_put_contents(self::getStateFilePath(), json_encode($state, JSON_PRETTY_PRINT));
        Logger::info("GlossaryPipelineService: Scheduler settings updated. Enabled=" . ($enabled ? 'true' : 'false'));
    }

    /**
     * Check if a daily publishing slot is currently due (within 20 mins of slot time)
     */
    public static function getDueSlot(array $state): ?string
    {
        if (empty($state['enabled'])) {
            return null;
        }

        if (count($state['published_today'] ?? []) >= ($state['daily_limit'] ?? 5)) {
            return null;
        }

        $nowMinutes = (int)date('H') * 60 + (int)date('i');
        $executedSlots = $state['executed_slots'] ?? [];

        foreach ($state['slots'] as $slot) {
            if (in_array($slot, $executedSlots, true)) {
                continue;
            }

            [$slotHour, $slotMin] = explode(':', $slot);
            $slotMinutes = (int)$slotHour * 60 + (int)$slotMin;

            // Trigger window: from slot time up to 35 minutes after
            if ($nowMinutes >= $slotMinutes && ($nowMinutes - $slotMinutes) <= 35) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Autonomous slot execution
     */
    public function runScheduledSlotIfDue(): ?array
    {
        $state = self::getSchedulerState();
        $dueSlot = self::getDueSlot($state);

        if (!$dueSlot) {
            return null;
        }

        Logger::info("GlossaryPipelineService: Slot {$dueSlot} is DUE! Fetching top candidate...");

        // Fetch top pending candidate
        $candidates = self::getPendingCandidates(1);
        if (empty($candidates)) {
            Logger::warning("GlossaryPipelineService: Slot {$dueSlot} triggered but candidate queue is empty.");
            return null;
        }

        $candidate = $candidates[0];
        $result = $this->generateAndPublish(
            $candidate['acronym'],
            $candidate['proposed_full_form_en'] ?? null,
            $candidate['category'] ?? null
        );

        // Mark slot as executed
        $state = self::getSchedulerState();
        $state['executed_slots'][] = $dueSlot;
        @file_put_contents(self::getStateFilePath(), json_encode($state, JSON_PRETTY_PRINT));

        Logger::info("GlossaryPipelineService: Slot {$dueSlot} executed with result: " . json_encode($result));
        return $result;
    }

    private function recordPublicationInState(string $acronym): void
    {
        $state = self::getSchedulerState();
        $state['published_today'][] = [
            'acronym' => $acronym,
            'time' => date('H:i:s'),
            'ts' => time()
        ];
        @file_put_contents(self::getStateFilePath(), json_encode($state, JSON_PRETTY_PRINT));
    }
}
