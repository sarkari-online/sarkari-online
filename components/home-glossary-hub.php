<?php
/**
 * Sarkari.online - Homepage Full Forms A-to-Z Directory Hub
 * Interactive Alphabet Jump Bar + Instant Search + 12 High-Value National Abbreviations
 * Seamlessly matches Sarkari.online design system.
 */

use App\Database\Database;

// Fetch top high-intent national abbreviations
$featuredAcronyms = ['UPSC', 'SSC', 'RRB', 'NDA', 'CTET', 'NEET', 'GATE', 'IBPS', 'SBI', 'BPSC', 'UPSSSC', 'RPF'];
$inClause = "'" . implode("','", $featuredAcronyms) . "'";

try {
    $featuredTerms = Database::fetchAll("
        SELECT id, acronym, slug, letter, full_form_en, full_form_hi, category, conducting_body
        FROM glossary_terms
        WHERE acronym IN ($inClause)
        ORDER BY FIELD(acronym, $inClause)
    ");
} catch (\Throwable $e) {
    $featuredTerms = [];
}

// Fallback if needed
if (count($featuredTerms) < 8) {
    try {
        $featuredTerms = Database::fetchAll("
            SELECT id, acronym, slug, letter, full_form_en, full_form_hi, category, conducting_body
            FROM glossary_terms
            ORDER BY acronym ASC
            LIMIT 12
        ");
    } catch (\Throwable $e) {}
}

$totalTermsCount = 195;
try {
    $countDb = (int)Database::fetchColumn("SELECT COUNT(*) FROM glossary_terms");
    if ($countDb > 0) $totalTermsCount = $countDb;
} catch (\Throwable $e) {}

$letters = range('A', 'Z');
?>

<section class="content-section home-glossary-section" aria-labelledby="sec-glossary-hub" style="margin: 2.25rem 0;">
    
    <!-- Section Header -->
    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; background: var(--color-primary-light); color: var(--color-primary); flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
            </span>
            <div>
                <h2 class="section-title" id="sec-glossary-hub" style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2;">
                    Government &amp; Examination Full Forms (A-Z)
                </h2>
                <span style="font-size: 0.8rem; color: var(--text-light); font-weight: 500;">
                    Official expansions, Hindi meanings, eligibility, and pay scales for <?= $totalTermsCount ?>+ recruitment entities
                </span>
            </div>
        </div>
        <a href="<?= url('full-forms/') ?>" class="section-link-more" title="View Complete A-to-Z Full Forms Directory" style="font-weight: 700; color: var(--color-primary); display: inline-flex; align-items: center; gap: 4px; font-size: 0.875rem;">
            <span>Browse All <?= $totalTermsCount ?>+ Terms</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- Interactive Search Bar + A-Z Alphabet Quick Bar -->
    <div style="background: var(--bg-page); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        
        <!-- Search Input -->
        <form action="<?= url('full-forms/') ?>" method="GET" style="display: flex; gap: 0.5rem; margin-bottom: 0.85rem; max-width: 650px;">
            <div style="position: relative; flex: 1;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); display: flex;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" name="q" placeholder="Search full form (e.g. UPSC, SSC, RRB, NDA, CTET, NEET)..." style="width: 100%; padding: 0.6rem 0.75rem 0.6rem 2.25rem; font-size: 0.875rem; border: 1px solid var(--border-strong); border-radius: 8px; outline: none; background: var(--bg-surface);">
            </div>
            <button type="submit" style="padding: 0.6rem 1.25rem; background: var(--color-primary); color: var(--bg-surface); font-weight: 700; font-size: 0.875rem; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span>Search</span>
            </button>
        </form>

        <!-- Alphabet Quick Jump Strip -->
        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-right: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em;">A-Z Jump:</span>
            <a href="<?= url('full-forms/') ?>" style="padding: 3px 8px; font-size: 0.75rem; font-weight: 700; border-radius: 6px; background: var(--color-primary); color: var(--bg-surface); text-decoration: none;">ALL</a>
            <?php foreach ($letters as $l): ?>
                <a href="<?= url('full-forms/?letter=' . $l) ?>" style="padding: 3px 7px; font-size: 0.75rem; font-weight: 600; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-strong); color: var(--text-body); text-decoration: none; transition: all 0.15s ease;" onmouseover="this.style.background='var(--color-primary-light)';this.style.borderColor='var(--color-primary-light)';this.style.color='var(--color-primary)';" onmouseout="this.style.background='var(--bg-surface)';this.style.borderColor='var(--border-strong)';this.style.color='var(--text-body)';">
                    <?= $l ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 12 High-Value National Abbreviations Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
        <?php foreach ($featuredTerms as $term): 
            $catLabel = ucfirst(str_replace('_', ' ', $term['category']));
            $termUrl = url('full-forms/' . $term['slug'] . '/');
        ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.04);" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.07)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)';">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.15rem; font-weight: 800; color: var(--color-primary); letter-spacing: -0.01em;">
                            <?= htmlspecialchars($term['acronym']) ?>
                        </span>
                        <span style="font-size: 0.675rem; font-weight: 700; padding: 2px 7px; border-radius: 9999px; background: var(--bg-page); color: var(--text-muted); text-transform: uppercase;">
                            <?= htmlspecialchars($catLabel) ?>
                        </span>
                    </div>
                    <h3 style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.35rem 0; line-height: 1.35;">
                        <a href="<?= $termUrl ?>" style="color: inherit; text-decoration: none;" title="<?= htmlspecialchars($term['acronym']) ?> Full Form">
                            <?= htmlspecialchars($term['full_form_en']) ?>
                        </a>
                    </h3>
                    <?php if (!empty($term['full_form_hi'])): ?>
                        <div style="font-size: 0.8rem; color: var(--text-light); font-weight: 500; margin-bottom: 0.75rem;">
                            हिंदी: <?= htmlspecialchars($term['full_form_hi']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="border-top: 1px solid var(--bg-page); padding-top: 0.6rem; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                        <?= htmlspecialchars($term['conducting_body'] ?? 'Govt Authority') ?>
                    </span>
                    <a href="<?= $termUrl ?>" style="font-size: 0.75rem; font-weight: 700; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 3px;" title="<?= htmlspecialchars($term['acronym']) ?> Meaning &amp; Details">
                        <span>Details</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bottom Explore CTA -->
    <div style="margin-top: 1.25rem; text-align: center; padding: 0.85rem; background: var(--color-primary-light); border: 1px solid var(--border-color); border-radius: 8px;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-primary);">
            Looking for state PSCs, police ranks, or commission acronyms?
        </span>
        <a href="<?= url('full-forms/') ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--color-primary); text-decoration: underline; margin-left: 0.5rem;" title="Explore Full Directory">
            Explore All <?= $totalTermsCount ?>+ Full Forms Directory &rarr;
        </a>
    </div>

</section>
