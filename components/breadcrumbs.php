<?php
/**
 * Breadcrumbs Component
 * Accepts array $crumbs = [ ['label' => 'Home', 'url' => ''], ['label' => 'Exam Results', 'url' => 'category/exam-results/'], ['label' => 'Current Article', 'url' => null] ]
 */
if (!isset($crumbs) || empty($crumbs)) {
    $crumbs = [
        ['label' => 'Home', 'url' => '']
    ];
}
?>
<nav class="breadcrumbs-nav" aria-label="Breadcrumbs">
    <?php foreach ($crumbs as $index => $crumb): ?>
        <?php if ($index > 0): ?>
            <span class="breadcrumb-separator"><?= icon('chevron-right', 'icon-sm') ?></span>
        <?php endif; ?>

        <?php if (!empty($crumb['url'])): ?>
            <span class="breadcrumb-item">
                <a href="<?= url($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            </span>
        <?php else: ?>
            <span class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<!-- BreadcrumbList Structured Data Placeholder -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    <?php 
    $totalCrumbs = count($crumbs);
    foreach ($crumbs as $i => $c): 
        $pos = $i + 1;
        $crumbUrl = !empty($c['url']) ? url($c['url']) : SITE_URL;
    ?>
    {
      "@type": "ListItem",
      "position": <?= $pos ?>,
      "name": "<?= e($c['label']) ?>",
      "item": "<?= e($crumbUrl) ?>"
    }<?= $pos < $totalCrumbs ? ',' : '' ?>
    <?php endforeach; ?>
  ]
}
</script>
