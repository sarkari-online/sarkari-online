<?php
/**
 * Newsletter Component
 * User updates opt-in card for Indian Education Alerts.
 */
?>
<section class="newsletter-card" aria-label="Education Updates Newsletter">
    <div class="newsletter-content">
        <span class="badge badge-pill" style="background: rgba(245, 158, 11, 0.2); color: #fef3c7; margin-bottom: 0.75rem;">
            <?= icon('bolt', 'icon-sm') ?> Free Daily Alert Service
        </span>
        <h2 class="newsletter-title">Never Miss an Official Exam or Recruitment Notice</h2>
        <p class="newsletter-desc">
            Get instant authentic updates on NEET, JEE, UPSC, SSC, state board results, and verified government jobs directly in your inbox.
        </p>
    </div>
    
    <form class="newsletter-form" action="#" method="POST">
        <input type="email" class="newsletter-input" placeholder="Enter your email address..." required aria-label="Email address for education alerts">
        <button type="submit" class="newsletter-btn">
            Get Free Alerts
        </button>
    </form>
</section>
