<?php
/**
 * Category Overview Card Component
 * Parameters passed via $catItem:
 * - name, slug, description, color, bg_light, icon
 */
if (!isset($catItem)) return;
?>
<a href="<?= url('category/' . $catItem['slug'] . '/') ?>" class="category-overview-card">
    <div class="cat-icon-box" style="background-color: <?= e($catItem['bg_light']) ?>; color: <?= e($catItem['color']) ?>;">
        <?= icon($catItem['icon'] ?? 'award', 'icon-lg') ?>
    </div>
    <div class="cat-card-info">
        <h4 style="color: <?= e($catItem['color']) ?>;"><?= e($catItem['name']) ?></h4>
        <p><?= e($catItem['description']) ?></p>
    </div>
</a>
