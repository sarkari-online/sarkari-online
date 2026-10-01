<?php
/**
 * Sarkari.online - 4-Column Candidate Action Feed
 * Matches exact executive redesign mockup (homepage_redesign_1790864147830.jpg):
 * [ Important Dates Timeline | Results | Admit Cards | Jobs ]
 */

use App\Database\Database;

$feedLimit = 5;

// Column 1: Important Dates & Timeline Events
try {
    $datesFeed = Database::fetchAll("
        SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.name as category_name
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        ORDER BY a.published_at DESC, a.id DESC 
        LIMIT " . (int)$feedLimit . "
    ");
} catch (\Throwable $e) {
    $datesFeed = [];
}

// Column 2: Results & Scorecards
try {
    $resultsFeed = Database::fetchAll("
        SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' 
          AND (c.slug IN ('exam-results', 'answer-keys') OR a.title LIKE '%Result%' OR a.title LIKE '%Scorecard%' OR a.title LIKE '%Cutoff%' OR a.title LIKE '%Merit%')
        ORDER BY a.published_at DESC, a.id DESC 
        LIMIT " . (int)$feedLimit . "
    ");
} catch (\Throwable $e) {
    $resultsFeed = [];
}

// Seamless backfill if needed
if (count($resultsFeed) < $feedLimit) {
    try {
        $existingIds = array_column($resultsFeed, 'id');
        $fillers = Database::fetchAll("SELECT a.id, a.title, a.slug, a.published_at, a.source_name FROM articles a WHERE a.status = 'published' ORDER BY a.published_at DESC LIMIT 10");
        foreach ($fillers as $f) {
            if (count($resultsFeed) >= $feedLimit) break;
            if (!in_array($f['id'], $existingIds, true)) {
                $resultsFeed[] = $f;
                $existingIds[] = $f['id'];
            }
        }
    } catch (\Throwable $e) {}
}

// Column 3: Admit Cards & Hall Tickets
try {
    $admitFeed = Database::fetchAll("
        SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' 
          AND (c.slug IN ('admit-cards', 'exam-dates') OR a.title LIKE '%Admit Card%' OR a.title LIKE '%Hall Ticket%' OR a.title LIKE '%City Slip%' OR a.title LIKE '%Exam Date%')
        ORDER BY a.published_at DESC, a.id DESC 
        LIMIT " . (int)$feedLimit . "
    ");
} catch (\Throwable $e) {
    $admitFeed = [];
}

if (count($admitFeed) < $feedLimit) {
    try {
        $existingIds = array_column($admitFeed, 'id');
        $fillers = Database::fetchAll("SELECT a.id, a.title, a.slug, a.published_at, a.source_name FROM articles a WHERE a.status = 'published' ORDER BY a.id ASC LIMIT 10");
        foreach ($fillers as $f) {
            if (count($admitFeed) >= $feedLimit) break;
            if (!in_array($f['id'], $existingIds, true)) {
                $admitFeed[] = $f;
                $existingIds[] = $f['id'];
            }
        }
    } catch (\Throwable $e) {}
}

// Column 4: Latest Government Jobs
try {
    $jobsFeed = Database::fetchAll("
        SELECT a.id, a.title, a.slug, a.published_at, a.source_name, c.slug as category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' 
          AND (c.slug IN ('government-jobs', 'scholarships', 'entrance-exams') OR a.title LIKE '%Apply%' OR a.title LIKE '%Recruitment%' OR a.title LIKE '%Posts%' OR a.title LIKE '%Vacancy%')
        ORDER BY a.published_at DESC, a.id DESC 
        LIMIT " . (int)$feedLimit . "
    ");
} catch (\Throwable $e) {
    $jobsFeed = [];
}

if (count($jobsFeed) < $feedLimit) {
    try {
        $existingIds = array_column($jobsFeed, 'id');
        $fillers = Database::fetchAll("SELECT a.id, a.title, a.slug, a.published_at, a.source_name FROM articles a WHERE a.status = 'published' ORDER BY a.id DESC LIMIT 10");
        foreach ($fillers as $f) {
            if (count($jobsFeed) >= $feedLimit) break;
            if (!in_array($f['id'], $existingIds, true)) {
                $jobsFeed[] = $f;
                $existingIds[] = $f['id'];
            }
        }
    } catch (\Throwable $e) {}
}
?>

<section class="portal-candidate-section" style="margin-bottom: 2.5rem;">
    <div class="portal-candidate-grid">
        
        <!-- ==========================================
             COLUMN 1: IMPORTANT DATES (Timeline Style)
             ========================================== -->
        <div class="candidate-feed-col col-timeline">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 1.35rem 0; letter-spacing: -0.01em;">
                Important Dates
            </h3>

            <div class="timeline-container" style="position: relative; padding-left: 20px;">
                <!-- Vertical Line -->
                <div style="position: absolute; left: 6px; top: 10px; bottom: 20px; width: 2px; background: #1a237e;"></div>

                <?php foreach ($datesFeed as $idx => $dateItem): 
                    $dateStr = !empty($dateItem['published_at']) ? date('M d, Y', strtotime($dateItem['published_at'])) : date('M d, Y');
                    $monthYear = !empty($dateItem['published_at']) ? date('F Y', strtotime($dateItem['published_at'])) : date('F Y');
                ?>
                    <div style="position: relative; margin-bottom: 1.5rem;">
                        <!-- Timeline Node Dot -->
                        <div style="position: absolute; left: -19px; top: 3px; width: 12px; height: 12px; border-radius: 50%; background: #1a237e; border: 2px solid #ffffff; box-shadow: 0 0 0 2px #1a237e;"></div>
                        
                        <span style="font-size: 0.72rem; color: #78909c; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">
                            <?= e($dateItem['category_name'] ?? 'Notice') ?>
                        </span>
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 3px 0; line-height: 1.3;">
                            <a href="<?= url('article/' . $dateItem['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';">
                                <?= e(truncate_text($dateItem['title'], 48)) ?>
                            </a>
                        </h4>
                        <span style="font-size: 0.8rem; color: #546e7a; font-weight: 500;">
                            <?= e($dateStr) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==========================================
             COLUMN 2: RESULTS (Mockup Style)
             ========================================== -->
        <div class="candidate-feed-col col-results">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 1.35rem 0; letter-spacing: -0.01em;">
                Results
            </h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($resultsFeed as $item): 
                    $dateStr = !empty($item['published_at']) ? date('M d, Y', strtotime($item['published_at'])) : date('M d, Y');
                    $authName = $item['source_name'] ?? 'Official Authority';
                ?>
                    <div class="candidate-card" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.15rem 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 14px rgba(26,35,126,0.08)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.03)';">
                        <div style="margin-bottom: 0.65rem;">
                            <span style="background: #fff3e0; color: #e65100; font-weight: 700; font-size: 0.72rem; padding: 3px 10px; border-radius: 6px; display: inline-block;">
                                Result
                            </span>
                        </div>
                        <h4 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 0.85rem 0; line-height: 1.35;">
                            <a href="<?= url('article/' . $item['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';">
                                <?= e($item['title']) ?>
                            </a>
                        </h4>
                        <div style="font-size: 0.8rem; color: #546e7a; font-weight: 600; margin-bottom: 3px;">
                            <?= e($dateStr) ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #78909c;">
                            Authority: <?= e(truncate_text($authName, 24)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==========================================
             COLUMN 3: ADMIT CARD (Mockup Style)
             ========================================== -->
        <div class="candidate-feed-col col-admit">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 1.35rem 0; letter-spacing: -0.01em;">
                Admit Cards
            </h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($admitFeed as $item): 
                    $dateStr = !empty($item['published_at']) ? date('M d, Y', strtotime($item['published_at'])) : date('M d, Y');
                    $authName = $item['source_name'] ?? 'Official Authority';
                ?>
                    <div class="candidate-card" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.15rem 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 14px rgba(26,35,126,0.08)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.03)';">
                        <div style="margin-bottom: 0.65rem;">
                            <span style="background: #e8f5e9; color: #1b5e20; font-weight: 700; font-size: 0.72rem; padding: 3px 10px; border-radius: 6px; display: inline-block;">
                                Admit Card
                            </span>
                        </div>
                        <h4 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 0.85rem 0; line-height: 1.35;">
                            <a href="<?= url('article/' . $item['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';">
                                <?= e($item['title']) ?>
                            </a>
                        </h4>
                        <div style="font-size: 0.8rem; color: #546e7a; font-weight: 600; margin-bottom: 3px;">
                            <?= e($dateStr) ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #78909c;">
                            Authority: <?= e(truncate_text($authName, 24)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==========================================
             COLUMN 4: JOBS (Mockup Style)
             ========================================== -->
        <div class="candidate-feed-col col-jobs">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 1.35rem 0; letter-spacing: -0.01em;">
                Latest Jobs
            </h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($jobsFeed as $item): 
                    $dateStr = !empty($item['published_at']) ? date('M d, Y', strtotime($item['published_at'])) : date('M d, Y');
                    $authName = $item['source_name'] ?? 'Official Authority';
                ?>
                    <div class="candidate-card" style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.15rem 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 14px rgba(26,35,126,0.08)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.03)';">
                        <div style="margin-bottom: 0.65rem;">
                            <span style="background: #e3f2fd; color: #0d47a1; font-weight: 700; font-size: 0.72rem; padding: 3px 10px; border-radius: 6px; display: inline-block;">
                                Jobs
                            </span>
                        </div>
                        <h4 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 0.85rem 0; line-height: 1.35;">
                            <a href="<?= url('article/' . $item['slug'] . '/') ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#1a237e';" onmouseout="this.style.color='#0f172a';">
                                <?= e($item['title']) ?>
                            </a>
                        </h4>
                        <div style="font-size: 0.8rem; color: #546e7a; font-weight: 600; margin-bottom: 3px;">
                            <?= e($dateStr) ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #78909c;">
                            Authority: <?= e(truncate_text($authName, 24)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>

<style>
.portal-candidate-grid {
    display: grid;
    grid-template-columns: 240px 1fr 1fr 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 1100px) {
    .portal-candidate-grid {
        grid-template-columns: 1fr 1fr !important;
    }
}
@media (max-width: 640px) {
    .portal-candidate-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
