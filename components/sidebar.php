<?php
/**
 * Sidebar Component
 * Executive design matching brand design system:
 * - Trending Stories
 * - Latest Verified Updates
 * - Student Tools & Utilities
 */
use App\Services\ArticleService;

$sidebarTrending = ArticleService::getLatestPublished(5);
$sidebarLatest = ArticleService::getLatestPublished(5);
?>
<aside class="article-sidebar" aria-label="Secondary Sidebar" style="position: sticky; top: 85px;">
    
    <!-- Widget 1: Trending Right Now -->
    <div class="sidebar-widget" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); margin-bottom: 1.5rem;">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #1a237e; margin: 0 0 1rem 0; padding-bottom: 0.65rem; border-bottom: 2px solid #f57c00; display: flex; align-items: center; justify-content: space-between;">
            <span>Trending Right Now</span>
            <?= icon('trending-up', 'icon-sm', ['style' => 'color: #f57c00;']) ?>
        </h3>
        <div style="display: flex; flex-direction: column; gap: 0.85rem;">
            <?php 
            $rank = 1;
            foreach ($sidebarTrending as $item): 
            ?>
                <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: #e8eaf6; color: #1a237e; font-weight: 800; font-size: 0.8rem; flex-shrink: 0;">
                        <?= $rank++ ?>
                    </span>
                    <div style="flex: 1; min-width: 0;">
                        <h4 style="font-size: 0.875rem; font-weight: 700; line-height: 1.35; margin: 0 0 3px 0;">
                            <a href="<?= url('article/' . $item['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';" title="<?= e($item['title']) ?>">
                                <?= e(truncate_text($item['title'], 55)) ?>
                            </a>
                        </h4>
                        <span style="font-size: 0.75rem; color: #78909c;"><?= format_date($item['published_at'] ?? 'now') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Widget 2: Student Utilities & Calculators -->
    <div class="sidebar-widget" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); margin-bottom: 1.5rem;">
        <div style="font-size: 0.72rem; font-weight: 800; color: #f57c00; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.35rem;">
            STUDENT UTILITIES
        </div>
        <h4 style="font-size: 0.95rem; font-weight: 800; color: #1a237e; margin: 0 0 0.5rem 0;">
            Salary &amp; Eligibility Calculators
        </h4>
        <p style="font-size: 0.8rem; color: #546e7a; line-height: 1.45; margin: 0 0 1rem 0;">
            Calculate in-hand salary as per 7th CPC (50% DA), exact age eligibility, or CGPA to percentage.
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="<?= url('tools/7th-pay-commission-salary-calculator/') ?>" style="display: block; padding: 8px 12px; background: #1a237e; color: #ffffff; font-size: 0.8rem; font-weight: 700; text-decoration: none; border-radius: 6px; text-align: center; transition: background 0.15s ease;" onmouseover="this.style.background='#f57c00';" onmouseout="this.style.background='#1a237e';">
                7th Pay Salary Calculator &rarr;
            </a>
            <a href="<?= url('tools/age-calculator/') ?>" style="display: block; padding: 8px 12px; background: #fafafa; border: 1px solid #e0e0e0; color: #1a237e; font-size: 0.8rem; font-weight: 700; text-decoration: none; border-radius: 6px; text-align: center; transition: all 0.15s ease;" onmouseover="this.style.background='#ffffff'; this.style.borderColor='#1a237e';" onmouseout="this.style.background='#fafafa'; this.style.borderColor='#e0e0e0';">
                Govt Job Age Calculator &rarr;
            </a>
        </div>
    </div>

    <!-- Widget 3: Key Portals -->
    <div class="sidebar-widget" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: #1a237e; margin: 0 0 1rem 0; padding-bottom: 0.65rem; border-bottom: 2px solid #f57c00;">
            Key Exam Portals
        </h3>
        <div style="display: flex; flex-direction: column; gap: 0.45rem;">
            <?php foreach (array_slice(CATEGORIES, 0, 6) as $cat): ?>
                <a href="<?= url('category/' . $cat['slug'] . '/') ?>" title="<?= e($cat['name']) ?> Portal" style="display: flex; justify-content: space-between; align-items: center; padding: 0.45rem 0.65rem; border-radius: 6px; color: #0f172a; font-weight: 600; font-size: 0.85rem; text-decoration: none; background: #fafafa; border: 1px solid #f0f0f0; transition: all 0.15s ease;" onmouseover="this.style.background='#e8eaf6'; this.style.color='#1a237e'; this.style.borderColor='#c5cae9';" onmouseout="this.style.background='#fafafa'; this.style.color='#0f172a'; this.style.borderColor='#f0f0f0';">
                    <span><?= e($cat['name']) ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</aside>
