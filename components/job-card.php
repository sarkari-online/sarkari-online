<?php
/**
 * Government Job Card Component
 * Parameters passed via $job:
 * - title, organization, vacancies, qualification, salary, last_date, slug, source
 */
if (!isset($job)) return;
?>
<div class="job-card">
    <div class="flex justify-between items-center">
        <span class="job-org-tag"><?= e($job['organization']) ?></span>
        <span class="badge badge-verified"><?= icon('check-circle', 'icon-sm') ?> Verified</span>
    </div>

    <h3 class="job-title">
        <a href="<?= url('article/' . $job['slug'] . '/') ?>">
            <?= e($job['title']) ?>
        </a>
    </h3>

    <div class="job-details-grid">
        <div class="job-detail-item">
            <span class="job-detail-label">Total Vacancies</span>
            <span class="job-detail-val"><?= e($job['vacancies']) ?></span>
        </div>
        <div class="job-detail-item">
            <span class="job-detail-label">Eligibility</span>
            <span class="job-detail-val"><?= e($job['qualification']) ?></span>
        </div>
        <div class="job-detail-item">
            <span class="job-detail-label">Pay Scale</span>
            <span class="job-detail-val"><?= e($job['salary']) ?></span>
        </div>
        <div class="job-detail-item">
            <span class="job-detail-label">Application Deadline</span>
            <span class="job-detail-val" style="color: var(--color-danger);"><?= e($job['last_date']) ?></span>
        </div>
    </div>

    <div class="job-footer">
        <span style="color: var(--text-muted); font-size: 0.75rem;">Source: <?= e($job['source']) ?></span>
        <a href="<?= url('article/' . $job['slug'] . '/') ?>" class="btn btn-sm btn-primary">
            View Notification <?= icon('chevron-right', 'icon-sm') ?>
        </a>
    </div>
</div>
