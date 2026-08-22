<?php
/**
 * Sarkari.online - Why Choose Us (Sleek Minimalist Edition)
 * Clean, lightweight, professional design with compact typography and zero clutter.
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Why Choose Sarkari.online — Authentic Exam Intelligence';
$pageDesc = 'Discover why students choose Sarkari.online: verified official sources, 10-second exam summaries, 12 regional languages, and zero ad-traps.';
$canonicalUrl = url('why-choose-us/');
$ogType = 'website';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => 'Why Choose Us', 'url' => null]
];

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" style="padding: 1.5rem 0 3rem;">
    <div class="container">
        
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <!-- Page Header -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-xs);">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span class="badge badge-primary" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    Our Standards
                </span>
                <span style="font-size: 0.8125rem; color: var(--text-muted);">Independent • 100% Free • Non-Government</span>
            </div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem; line-height: 1.3;">
                Why Choose Sarkari.online?
            </h1>
            <p style="font-size: 0.95rem; color: var(--text-muted); margin: 0; line-height: 1.6; max-width: 780px;">
                Every year, millions of Indian aspirants struggle through 100-page complex bureaucratic circulars and deceptive ad-traps. 
                <strong>Sarkari.online</strong> is built to deliver rapid, structured, and 100% verified examination updates directly from statutory authorities.
            </p>
        </div>

        <!-- 4 Key Advantages (Compact Minimalist Row) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; box-shadow: var(--shadow-xs);">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <div style="color: #2563eb; background: #eff6ff; padding: 6px; border-radius: 6px; display: inline-flex;">
                        <?= icon('clock', 'icon-sm') ?>
                    </div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--text-main);">10-Sec Summaries</h3>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                    Instant dates, eligibility &amp; fee structure tables without reading 100-page circulars.
                </p>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; box-shadow: var(--shadow-xs);">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <div style="color: #059669; background: #ecfdf5; padding: 6px; border-radius: 6px; display: inline-flex;">
                        <?= icon('globe', 'icon-sm') ?>
                    </div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--text-main);">12 Indian Languages</h3>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                    Read every alert in Hindi, Tamil, Telugu, Marathi, Bengali, Urdu &amp; more with 1 click.
                </p>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; box-shadow: var(--shadow-xs);">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <div style="color: #7c3aed; background: #f5f3ff; padding: 6px; border-radius: 6px; display: inline-flex;">
                        <?= icon('shield-check', 'icon-sm') ?>
                    </div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--text-main);">100% Official Links</h3>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                    Direct portal (.gov.in / .nic.in) links with exact notice references and zero fake dates.
                </p>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; box-shadow: var(--shadow-xs);">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    <div style="color: #ea580c; background: #fff7ed; padding: 6px; border-radius: 6px; display: inline-flex;">
                        <?= icon('bolt', 'icon-sm') ?>
                    </div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--text-main);">Fast &amp; Zero Popups</h3>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                    Clean, mobile-first reading experience with no deceptive download traps or intrusive ads.
                </p>
            </div>

        </div>

        <!-- Compact Comparison Section -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-xs);">
            <div style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.25rem;">
                    Platform Comparison
                </h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    How Sarkari.online solves the core frustrations of Indian aspirants:
                </p>
            </div>

            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0; width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 0.75rem 1rem; text-align: left; font-weight: 700; color: #475569; width: 34%;">Feature</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 800; color: #1d4ed8; background: #eff6ff; width: 24%;">Sarkari.online</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600; color: #64748b; width: 21%;">Govt Websites</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; font-weight: 600; color: #64748b; width: 21%;">Other Private Blogs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--text-main);">Information Format</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #16a34a; font-weight: 700; background: #f0fdf4;">✓ Structured Quick Table</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #64748b;">100-Page Legal PDF</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #dc2626;">Clickbait / Speculative</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--text-main);">Regional Languages</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #16a34a; font-weight: 700; background: #f0fdf4;">✓ 12 Indian Languages</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #64748b;">English / Hindi Only</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #dc2626;">English Only</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--text-main);">Official Direct Links</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #16a34a; font-weight: 700; background: #f0fdf4;">✓ 1-Click Official Portal</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #64748b;">Buried in Archives</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #dc2626;">10 Redirect Ad-Traps</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--text-main);">High-Traffic Uptime</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #16a34a; font-weight: 700; background: #f0fdf4;">✓ 99.9% Global CDN</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #dc2626;">Frequent Result Crashes</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #64748b;">Slow / Heavy Ads</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.8rem 1rem; font-weight: 600; color: var(--text-main);">Reading Experience</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #16a34a; font-weight: 700; background: #f0fdf4;">✓ Zero Intrusive Popups</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #64748b;">Clean but Outdated</td>
                            <td style="padding: 0.8rem 1rem; text-align: center; color: #dc2626;">Popups &amp; Fake Buttons</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3-Step Verification & Trust Manifesto (Compact Grid) -->
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            
            <!-- Left: Our 3-Step Verification Engine -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow-xs);">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem;">
                    How We Verify Every Notification
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="width: 24px; height: 24px; background: #eff6ff; color: #2563eb; font-weight: 800; font-size: 0.75rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">1</span>
                        <div>
                            <strong style="font-size: 0.9rem; color: var(--text-main); display: block;">Statutory Portal Monitoring</strong>
                            <span style="font-size: 0.8125rem; color: var(--text-muted);">Real-time monitoring of NTA, CBSE, UPSC, SSC, RRB &amp; State PSC portals.</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="width: 24px; height: 24px; background: #ecfdf5; color: #059669; font-weight: 800; font-size: 0.75rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">2</span>
                        <div>
                            <strong style="font-size: 0.9rem; color: var(--text-main); display: block;">Fact-Checking &amp; Date Auditing</strong>
                            <span style="font-size: 0.8125rem; color: var(--text-muted);">Every claim is matched against official gazette notices before publication.</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                        <span style="width: 24px; height: 24px; background: #f5f3ff; color: #7c3aed; font-weight: 800; font-size: 0.75rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">3</span>
                        <div>
                            <strong style="font-size: 0.9rem; color: var(--text-main); display: block;">Actionable Guidance in 12 Languages</strong>
                            <span style="font-size: 0.8125rem; color: var(--text-muted);">Published with step-by-step apply steps and direct official portal links.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Our Editorial Values -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow-xs); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.85rem;">
                        Our Editorial Promise
                    </h3>
                    <ul style="margin: 0; padding-left: 1.2rem; font-size: 0.85rem; color: var(--text-muted); line-height: 1.7;">
                        <li><strong>Zero Speculation:</strong> We never post unconfirmed rumor dates.</li>
                        <li><strong>Always 100% Free:</strong> No paywalls or registration barriers.</li>
                        <li><strong>Clear Transparency:</strong> Non-government independent status.</li>
                        <li><strong>Candidate Safety:</strong> Direct verified links to prevent phishing.</li>
                    </ul>
                </div>
                <div style="margin-top: 1rem; pt: 1rem; border-top: 1px solid #f1f5f9;">
                    <a href="<?= url() ?>" class="btn btn-primary btn-block" style="text-align: center; justify-content: center; font-weight: 700; font-size: 0.875rem;">
                        Browse Latest Updates <?= icon('chevron-right', 'icon-xs') ?>
                    </a>
                </div>
            </div>

        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
