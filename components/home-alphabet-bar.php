<?php
/**
 * Sarkari.online - A-Z Full Forms Alphabet Bar
 * Matches exact executive redesign mockup (homepage_redesign_1790864147830.jpg)
 */
$letters = range('A', 'Z');
?>

<section class="home-az-fullforms-section" style="margin-bottom: 2.25rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.01em;">
            A-Z Full Forms
        </h3>
        <a href="<?= url('full-forms/') ?>" style="color: #1a237e; font-weight: 700; font-size: 0.875rem; text-decoration: none;">
            Browse All Full Forms &rarr;
        </a>
    </div>

    <!-- Rounded Navy Letter Buttons -->
    <div class="az-pills-row" style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
        <?php foreach ($letters as $char): ?>
            <a href="<?= url('full-forms/?letter=' . $char) ?>" 
               class="az-pill-btn"
               style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 8px; background: #1a237e; color: #ffffff; font-weight: 800; font-size: 0.95rem; text-decoration: none; transition: all 0.15s ease;"
               onmouseover="this.style.background='#f57c00'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#1a237e'; this.style.transform='none';">
                <?= $char ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
