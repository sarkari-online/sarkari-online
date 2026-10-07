<?php
use App\Database\Database;

$totalTermsCount = 212;
try {
    $countDb = (int)Database::fetchValue("SELECT COUNT(*) FROM glossary_terms");
    if ($countDb > 0) $totalTermsCount = $countDb;
} catch (\Throwable $e) {}
?>

<section class="hero-announcement-section" style="margin-bottom: 2rem;">
    <div class="hero-announcement-card" style="background: #ffffff; border: 4px solid #f57c00; border-radius: 16px; padding: 2.25rem 2.5rem; box-shadow: 0 4px 20px rgba(245, 124, 0, 0.08); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        
        <!-- Top Badge -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.15rem;">
            <span style="background: #f57c00; color: #ffffff; padding: 6px 18px; border-radius: 9999px; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.3px; display: inline-block;">
                Official Master Directory
            </span>
            <span style="font-size: 0.825rem; font-weight: 700; color: #1a237e; background: #e8eaf6; padding: 4px 12px; border-radius: 6px;">
                <?= $totalTermsCount ?>+ Verified Acronyms
            </span>
        </div>

        <!-- Headline -->
        <h1 style="font-size: clamp(1.75rem, 4vw, 2.35rem); font-weight: 800; color: #1a237e; margin: 0 0 0.85rem 0; line-height: 1.25; letter-spacing: -0.02em;">
            <a href="<?= url('full-forms/') ?>" style="color: #1a237e; text-decoration: none;" onmouseover="this.style.color='#f57c00';" onmouseout="this.style.color='#1a237e';">
                Government &amp; Examination Full Forms (A-Z Directory)
            </a>
        </h1>

        <p style="font-size: 1.05rem; color: #475569; margin: 0 0 1.5rem 0; line-height: 1.6; max-width: 820px;">
            Comprehensive dictionary of all Indian government bodies, competitive exam acronyms, civil services ranks, defense posts, and education boards with authentic Hindi translations, eligibility rules, and conducting authority portals.
        </p>

        <!-- Search Form inside Hero -->
        <form action="<?= url('full-forms/') ?>" method="GET" style="display: flex; gap: 0.5rem; max-width: 620px; margin-bottom: 1.25rem;">
            <input type="text" name="q" placeholder="Type acronym e.g. UPSC, SSC, RRB, NEET, NDA, CTET..." style="flex: 1; padding: 0.75rem 1rem; font-size: 0.95rem; border: 2px solid #cbd5e1; border-radius: 8px; outline: none; background: #f8fafc;" onfocus="this.style.borderColor='#1a237e';" onblur="this.style.borderColor='#cbd5e1';">
            <button type="submit" style="padding: 0.75rem 1.5rem; background: #1a237e; color: #ffffff; font-weight: 700; font-size: 0.95rem; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s ease;" onmouseover="this.style.background='#f57c00';" onmouseout="this.style.background='#1a237e';">
                <span>Search</span>
            </button>
        </form>

        <!-- Meta Line -->
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.875rem; color: #78909c; font-weight: 500;">
            <span>Curated by Sarkari.online Editorial Desk</span>
            <span>&bull;</span>
            <span style="color: #16a34a; font-weight: 700;">100% Verified Statutory Definitions</span>
            <span>&bull;</span>
            <a href="<?= url('full-forms/') ?>" style="color: #f57c00; font-weight: 700; text-decoration: none;">Browse Full Directory &rarr;</a>
        </div>

    </div>
</section>
