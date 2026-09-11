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
                <a href="<?= url($crumb['url']) ?>" title="<?= e($crumb['label']) ?>"><?= e($crumb['label']) ?></a>
            </span>
        <?php else: ?>
            <span class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php if (!empty($renderBreadcrumbSchema)): ?>
<!-- BreadcrumbList Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    <?php 
    $totalCrumbs = count($crumbs);
    foreach ($crumbs as $i => $c): 
        $pos = $i + 1;
        if ($i === 0) {
            $crumbUrl = rtrim(SITE_URL, '/') . '/';
        } elseif (!empty($c['url'])) {
            $crumbUrl = url($c['url']);
        } else {
            $crumbUrl = !empty($canonicalUrl) ? $canonicalUrl : (SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/'));
        }
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
<?php endif; ?>
