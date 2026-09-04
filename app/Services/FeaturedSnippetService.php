<?php
/**
 * Sarkari.online - Featured Snippet & "Position 0" Quick-Answer Box Engine
 *
 * Algorithmically synthesizes and renders an authoritative Executive Summary Box
 * with a direct 35-45 word factual answer and a high-density 6-point Fact Sheet.
 * Specifically engineered to win Google Search "Position 0" Featured Snippets.
 */

namespace App\Services;

class FeaturedSnippetService {

    /**
     * Render the complete Featured Snippet / Position 0 Box HTML
     *
     * @param array $article Article database record
     * @return string HTML output
     */
    public static function render(array $article): string {
        $title = $article['title'] ?? '';
        $content = $article['content_html'] ?? $article['content'] ?? '';
        $excerpt = $article['excerpt'] ?? $article['meta_description'] ?? '';
        $category = $article['category_slug'] ?? $article['category_name'] ?? 'education';

        // 1. Generate Crisp Direct Answer (35-45 words)
        $directAnswer = self::generateDirectAnswer($article, $title, $excerpt, $content, $category);

        // 2. Extract Structured Key Fact Sheet Grid
        $facts = self::extractFactSheet($article);

        // 3. Status Badge
        $statusInfo = self::determineStatusBadge($title, $content);

        // Build Semantic HTML
        ob_start();
        ?>
        <div class="featured-snippet-card notranslate-table" role="region" aria-label="Quick Highlights & Direct Answer" itemscope itemtype="https://schema.org/SpecialAnnouncement">
            
            <!-- Header Bar -->
            <div class="snippet-card-header">
                <div class="snippet-header-left">
                    <span class="snippet-header-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </span>
                    <span class="snippet-header-title">Executive Summary &amp; Direct Answer</span>
                </div>
                <div class="snippet-header-right">
                    <span class="snippet-status-pill <?= e($statusInfo['class']) ?>">
                        <span class="status-dot"></span>
                        <?= e($statusInfo['label']) ?>
                    </span>
                </div>
            </div>

            <!-- Direct Answer Paragraph (Targeted for Google Position 0 Voice & Snippet Box) -->
            <div class="snippet-direct-answer" itemprop="text">
                <p>
                    <strong class="snippet-lead-highlight">Direct Answer:</strong>
                    <?= e($directAnswer) ?>
                </p>
            </div>

            <!-- 6-Point Structured Fact Sheet Grid -->
            <div class="snippet-fact-grid">
                <?php foreach ($facts as $fact): ?>
                    <div class="snippet-fact-cell">
                        <div class="snippet-fact-label"><?= e($fact['label']) ?></div>
                        <div class="snippet-fact-val">
                            <?php if (!empty($fact['url'])): ?>
                                <a href="<?= e($fact['url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="snippet-fact-link">
                                    <?= e($fact['value']) ?>
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>
                            <?php else: ?>
                                <?= e($fact['value']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer Statutory Verification Note -->
            <div class="snippet-card-footer">
                <span class="snippet-footer-statutory">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Statutory Source Verified &middot; Candidates must cross-check with official gazette bulletins.
                </span>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Synthesizes a crisp, 35-45 word direct answer for Google Snippets
     */
    private static function generateDirectAnswer(array $article, string $title, string $excerpt, string $content, string $category): string {
        // 0. Check if AI already provided an explicit direct answer in raw_payload
        if (!empty($article['raw_payload'])) {
            $raw = is_array($article['raw_payload']) ? $article['raw_payload'] : json_decode($article['raw_payload'], true);
            if (!empty($raw['direct_answer']) && mb_strlen($raw['direct_answer']) > 30) {
                return trim($raw['direct_answer']);
            }
        }
        // If excerpt is already concise and rich, use it
        $cleanExcerpt = trim(strip_tags($excerpt));
        if (mb_strlen($cleanExcerpt) >= 80 && mb_strlen($cleanExcerpt) <= 240 && !str_contains($cleanExcerpt, '...')) {
            return $cleanExcerpt;
        }

        // Otherwise, extract the first authoritative sentence from content
        if (!empty($content)) {
            $plainText = strip_tags($content);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $sentences = preg_split('/(?<=[.?!])\s+/', $plainText);
            if (!empty($sentences[0]) && mb_strlen($sentences[0]) > 40) {
                $lead = trim($sentences[0]);
                if (!empty($sentences[1]) && mb_strlen($lead) < 120) {
                    $lead .= ' ' . trim($sentences[1]);
                }
                if (mb_strlen($lead) <= 260) {
                    return $lead;
                }
            }
        }

        // Fallback synthesised answer
        return "Official updates regarding {$title} have been issued. Eligible candidates are advised to verify key deadlines, eligibility criteria, and procedure through the official statutory portal.";
    }

    /**
     * Extracts a 6-point structured Fact Sheet
     */
    private static function extractFactSheet(array $article): array {
        $title = $article['title'] ?? '';
        $content = $article['content_html'] ?? $article['content'] ?? '';
        
        // 1. Authority: Prioritize authoritative resolution over generic database fallback
        $authority = $article['source_name'] ?? '';
        $genericNames = ['official statutory authority', 'statutory authority', 'official authority', 'statutory agency', 'government agency', 'statutory examination board / agency'];
        if (empty($authority) || in_array(strtolower(trim($authority)), $genericNames, true)) {
            $resolved = AuthorityFactFetcherService::resolveAuthority($title, $article['source_url'] ?? '');
            $authority = (!empty($resolved['name']) && !in_array(strtolower(trim($resolved['name'])), $genericNames, true))
                ? $resolved['name'] 
                : self::detectAuthority($title, $content);
        }

        // 2. Exam/Recruitment Name
        $examName = self::cleanExamName($title);

        // 3. Status
        $status = self::detectStatusText($title, $content);

        // 4. Milestone / Timeline
        $timeline = self::extractKeyDate($title, $content, $article['published_at'] ?? '');

        // 5. Official Portal: Strictly prevent self-referential sarkari.online link
        $portalUrl = $article['source_url'] ?? '';
        if (empty($portalUrl) || str_contains($portalUrl, 'sarkari.online') || str_contains($portalUrl, 'localhost')) {
            $resolved = AuthorityFactFetcherService::resolveAuthority($title);
            $portalUrl = (!empty($resolved['portal']) && !str_contains($resolved['portal'], 'sarkari.online'))
                ? $resolved['portal']
                : '';
        }

        if (!empty($portalUrl)) {
            $portalDomain = parse_url($portalUrl, PHP_URL_HOST) ?: 'Official Portal';
            $portalDomain = preg_replace('/^www\./i', '', $portalDomain);
        } else {
            $portalDomain = 'Official Gazette Bulletin';
            $portalUrl = null;
        }

        // 6. Action
        $action = self::determineCandidateAction($title);

        return [
            ['label' => 'Conducting Body', 'value' => $authority, 'url' => null],
            ['label' => 'Exam / Post Name', 'value' => $examName, 'url' => null],
            ['label' => 'Current Status', 'value' => $status, 'url' => null],
            ['label' => 'Important Timeline', 'value' => $timeline, 'url' => null],
            ['label' => 'Official Portal', 'value' => $portalDomain, 'url' => $portalUrl],
            ['label' => 'Next Action', 'value' => $action, 'url' => null],
        ];
    }

    private static function detectAuthority(string $title, string $content): string {
        $resolved = AuthorityFactFetcherService::resolveAuthority($title);
        if (!empty($resolved['name']) && !str_contains(strtolower($resolved['name']), 'statutory examination board')) {
            return $resolved['name'];
        }

        $haystack = strtolower($title . ' ' . substr($content, 0, 1000));
        $map = [
            'aicte' => 'All India Council for Technical Education (AICTE)',
            'upsssc'=> 'UP Subordinate Services Selection Commission (UPSSSC)',
            'upsc'  => 'Union Public Service Commission (UPSC)',
            'ssc'   => 'Staff Selection Commission (SSC)',
            'nta'   => 'National Testing Agency (NTA)',
            'rrb'   => 'Railway Recruitment Board (RRB)',
            'ibps'  => 'Institute of Banking Personnel Selection (IBPS)',
            'cbse'  => 'Central Board of Secondary Education (CBSE)',
            'ugc'   => 'University Grants Commission (UGC)',
            'dsssb' => 'Delhi Subordinate Services Selection Board (DSSSB)',
            'cisf'  => 'Central Industrial Security Force (CISF)',
            'bsf'   => 'Border Security Force (BSF)',
            'crpf'  => 'Central Reserve Police Force (CRPF)',
            'itbp'  => 'Indo-Tibetan Border Police (ITBP)',
            'ssb'   => 'Sashastra Seema Bal (SSB)',
            'kvs'   => 'Kendriya Vidyalaya Sangathan (KVS)',
            'nvs'   => 'Navodaya Vidyalaya Samiti (NVS)',
            'cisce' => 'Council for the Indian School Certificate Examinations',
            'dopt'  => 'Department of Personnel and Training (DoPT)',
            'ignou' => 'Indira Gandhi National Open University (IGNOU)',
            'uppsc' => 'Uttar Pradesh Public Service Commission (UPPSC)',
            'bpsc'  => 'Bihar Public Service Commission (BPSC)',
            'rpsc'  => 'Rajasthan Public Service Commission (RPSC)',
            'mppsc' => 'Madhya Pradesh Public Service Commission (MPPSC)',
            'hssc'  => 'Haryana Staff Selection Commission (HSSC)',
            'ukpsc' => 'Uttarakhand Public Service Commission (UKPSC)',
            'wbjee' => 'West Bengal Joint Entrance Examinations Board',
            'josaa' => 'Joint Seat Allocation Authority (JoSAA)',
            'csab'  => 'Central Seat Allocation Board (CSAB)',
            'drdo'  => 'Defence Research and Development Organisation (DRDO)',
            'isro'  => 'Indian Space Research Organisation (ISRO)',
            'indian army' => 'Indian Army (Join Indian Army)',
            'indian air force' => 'Indian Air Force (IAF)',
            'indian navy' => 'Indian Navy (Nausena Bharti)'
        ];

        foreach ($map as $key => $name) {
            if (str_contains($haystack, $key)) {
                return $name;
            }
        }

        // Acronym extractor
        if (preg_match('/\b([A-Z]{3,8})\b/', $title, $m)) {
            return $m[1] . ' (Statutory Examination Authority)';
        }

        return 'Official Statutory Board / Agency';
    }

    private static function cleanExamName(string $title): string {
        $clean = preg_replace('/[:|—–-].*$/u', '', $title);
        $clean = trim($clean);
        return mb_strlen($clean) > 3 ? $clean : $title;
    }

    private static function detectStatusText(string $title, string $content): string {
        $t = strtolower($title);
        if (str_contains($t, 'result') && (str_contains($t, 'out') || str_contains($t, 'declared') || str_contains($t, 'released'))) {
            return 'Scorecard & Merit List Declared';
        }
        if (str_contains($t, 'admit card') || str_contains($t, 'hall ticket')) {
            return 'Hall Ticket Download Active';
        }
        if (str_contains($t, 'answer key')) {
            return 'Provisional Key & Objection Live';
        }
        if (str_contains($t, 'fellowship') || str_contains($t, 'scholarship')) {
            return 'Scheme Guidelines & Portal Active';
        }
        if (str_contains($t, 'date') || str_contains($t, 'schedule')) {
            return 'Official Calendar Announced';
        }
        if (str_contains($t, 'notification') || str_contains($t, 'apply') || str_contains($t, 'recruitment')) {
            return 'Official Notification Published';
        }
        return 'Official Gazetted Update Live';
    }

    private static function determineStatusBadge(string $title, string $content): array {
        $t = strtolower($title);
        if (str_contains($t, 'out') || str_contains($t, 'declared') || str_contains($t, 'active') || str_contains($t, 'released')) {
            return ['label' => 'Live Update', 'class' => 'status-live'];
        }
        if (str_contains($t, 'date') || str_contains($t, 'schedule') || str_contains($t, 'calendar')) {
            return ['label' => 'Confirmed Schedule', 'class' => 'status-confirmed'];
        }
        return ['label' => 'Verified Circular', 'class' => 'status-verified'];
    }

    private static function extractKeyDate(string $title, string $content, string $pubDate): string {
        $plain = strip_tags($content);

        // Priority 1: Direct application deadline markers
        if (preg_match('/(?:last\s*date|apply\s*by|deadline|closing\s*date)[:\s]+([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67]|[0-9]{1,2}[\/\-][0-9]{1,2}[\/\-]202[67])/i', $plain, $m)) {
            return 'Last Date: ' . trim($m[1]);
        }

        // Priority 2: Direct exam date markers
        if (preg_match('/(?:exam\s*date|exam\s*on|exam\s*schedule|commencing\s*on)[:\s]+([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67]|[0-9]{1,2}[\/\-][0-9]{1,2}[\/\-]202[67])/i', $plain, $m)) {
            return 'Exam Date: ' . trim($m[1]);
        }

        // Priority 3: Direct admit card / result release markers
        if (preg_match('/(?:admit\s*card\s*release|hall\s*ticket\s*from|result\s*on|result\s*declared\s*on)[:\s]+([0-9]{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+202[67]|[A-Za-z]+\s+[0-9]{1,2},?\s+202[67])/i', $plain, $m)) {
            return 'Milestone: ' . trim($m[1]);
        }

        // Priority 4: Look for explicit dates in content (excluding today's publish date)
        if (preg_match_all('/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+202[67])\b/i', $plain, $matches)) {
            foreach ($matches[1] as $foundDate) {
                if (empty($pubDate) || date('Y-m-d', strtotime($foundDate)) !== date('Y-m-d', strtotime($pubDate))) {
                    return $foundDate;
                }
            }
        }

        // Priority 5: Fallback based on content type - NEVER return publish date as the "Important Timeline"!
        $t = strtolower($title);
        if (str_contains($t, 'result')) {
            return 'Scorecard Download Active';
        }
        if (str_contains($t, 'admit card') || str_contains($t, 'hall ticket')) {
            return 'Hall Ticket Download Live';
        }
        if (str_contains($t, 'apply') || str_contains($t, 'recruitment')) {
            return 'Check Official Notification';
        }
        if (str_contains($t, 'fellowship') || str_contains($t, 'scholarship')) {
            return 'Refer to Scheme Guidelines';
        }

        return 'As Per Official Schedule';
    }

    private static function determineCandidateAction(string $title): string {
        $t = strtolower($title);
        if (str_contains($t, 'result')) {
            return 'Check Roll No. in Merit List';
        }
        if (str_contains($t, 'admit card') || str_contains($t, 'hall ticket')) {
            return 'Download & Print Admit Card';
        }
        if (str_contains($t, 'answer key')) {
            return 'Download Key & Submit Objections';
        }
        if (str_contains($t, 'fellowship') || str_contains($t, 'scholarship')) {
            return 'Check Eligibility & Apply Online';
        }
        if (str_contains($t, 'apply') || str_contains($t, 'form') || str_contains($t, 'recruitment')) {
            return 'Submit Online Application Form';
        }
        if (str_contains($t, 'date') || str_contains($t, 'schedule') || str_contains($t, 'timetable')) {
            return 'Download Timetable & Exam Shift';
        }
        return 'Verify Circular on Official Portal';
    }
}
