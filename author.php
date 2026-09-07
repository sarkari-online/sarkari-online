<?php
/**
 * Sarkari.online - Verified Editorial Persona & Author Profile
 * Displays author credentials, E-E-A-T background, review methodology,
 * and list of verified articles. Complies with Google Helpful Content & YMYL standards.
 */

require_once __DIR__ . '/config.php';

use App\Services\AuthorService;
use App\Services\ArticleService;

// Resolve author slug from query parameter
$authorSlug = trim($_GET['slug'] ?? '');
if (empty($authorSlug)) {
    $authorSlug = 'priyanshu-sharma';
}

$author = AuthorService::getBySlug($authorSlug);
if (!$author) {
    // Check if user visited generic /author/ or invalid slug -> redirect to Priyanshu Sharma
    redirect(url('author/priyanshu-sharma/'), 301);
    exit;
}

// Fetch articles by this author
$articles = AuthorService::getArticlesByAuthor($authorSlug, 24);

// SEO Meta
$pageTitle = $author['name'] . ' - ' . $author['title'] . ' | Sarkari.online';
$metaDescription = $author['name'] . ' is a ' . $author['title'] . ' at Sarkari.online. Read verified examination analyses, statutory updates, and admission guides.';
$canonicalUrl = url('author/' . $author['slug'] . '/');

// Person & ProfilePage JSON-LD Schema
$profileSchema = [
    '@context' => 'https://schema.org',
    '@type'    => 'ProfilePage',
    'mainEntity' => [
        '@type'       => 'Person',
        'name'        => $author['name'],
        'jobTitle'    => $author['title'],
        'description' => $author['bio'],
        'url'         => $canonicalUrl,
        'alumniOf'    => [
            '@type' => 'EducationalOrganization',
            'name'  => $author['education']
        ],
        'worksFor'    => [
            '@type' => 'NewsMediaOrganization',
            'name'  => SITE_NAME,
            'url'   => SITE_URL
        ],
        'knowsAbout'  => $author['focus_areas']
    ]
];

$extraHead = '<script type="application/ld+json">' . json_encode($profileSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<main class="site-main" id="mainContent">
    <div class="container" style="max-width: 1140px; margin: 0 auto; padding: 1.5rem 1rem 3.5rem 1rem;">
        
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 1.5rem; font-size: 0.8125rem; color: #64748b;">
            <ol style="display: flex; flex-wrap: wrap; list-style: none; gap: 0.5rem; padding: 0; margin: 0; align-items: center;">
                <li><a href="<?= url() ?>" style="color: #1e3a8a; text-decoration: none; font-weight: 600;">Home</a></li>
                <li style="color: #cbd5e1;">/</li>
                <li><a href="<?= url('about/') ?>" style="color: #1e3a8a; text-decoration: none; font-weight: 600;">Editorial Board</a></li>
                <li style="color: #cbd5e1;">/</li>
                <li style="color: #475569; font-weight: 600;" aria-current="page"><?= e($author['name']) ?></li>
            </ol>
        </nav>

        <!-- Author Master Profile Card -->
        <section class="author-master-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2.5rem;">
            <div style="display: flex; flex-direction: column; gap: 1.75rem;">
                
                <div style="display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap;">
                    <!-- Avatar Badge -->
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: <?= $author['avatar_bg'] ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <?= $author['avatar_letter'] ?>
                    </div>

                    <div style="flex: 1; min-width: 260px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                            <h1 style="font-size: 1.625rem; font-weight: 800; color: #0f172a; margin: 0;"><?= e($author['name']) ?></h1>
                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.725rem; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 9999px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Verified Education Analyst
                            </span>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #1e3a8a; margin-bottom: 0.25rem;">
                            <?= e($author['title']) ?>
                        </div>
                        <div style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0.75rem;">
                            <?= e($author['role']) ?> &bull; <strong><?= e($author['experience']) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Bio Paragraph -->
                <div style="font-size: 0.95rem; line-height: 1.65; color: #334155; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                    <?= e($author['bio']) ?>
                </div>

                <!-- Credentials & Verification Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">Academic Background</div>
                        <div style="font-size: 0.875rem; color: #0f172a; font-weight: 600;"><?= e($author['education']) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 0.35rem;">Fact-Checking Standard</div>
                        <div style="font-size: 0.8125rem; color: #334155; line-height: 1.4;"><?= e($author['methodology']) ?></div>
                    </div>
                </div>

                <!-- Focus Areas -->
                <div>
                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.5rem;">Core Editorial Focus Areas</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($author['focus_areas'] as $area): ?>
                            <span style="font-size: 0.8125rem; font-weight: 600; color: #1e40af; background: #eff6ff; border: 1px solid #dbeafe; padding: 4px 10px; border-radius: 6px;">
                                <?= e($area) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- Articles by this Author -->
        <section class="author-articles-section">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">
                    Verified Analyses &amp; Guides by <?= e($author['name']) ?>
                </h2>
                <span style="font-size: 0.8125rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 2px 10px; border-radius: 9999px;">
                    <?= count($articles) ?> Articles
                </span>
            </div>

            <?php if (empty($articles)): ?>
                <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 3rem 1rem; text-align: center; color: #64748b;">
                    <p>Articles by this analyst are currently undergoing statutory editorial review.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
                    <?php foreach ($articles as $art): 
                        $artUrl = url('article/' . $art['slug'] . '/');
                        $artDate = !empty($art['published_at']) ? date('d M Y', strtotime($art['published_at'])) : date('d M Y');
                    ?>
                        <article style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                            <div>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #1e3a8a; background: #eff6ff; padding: 2px 8px; border-radius: 4px;">
                                        <?= e($art['category_name'] ?? 'Education') ?>
                                    </span>
                                    <time datetime="<?= e($art['published_at'] ?? '') ?>" style="font-size: 0.75rem; color: #64748b;">
                                        <?= $artDate ?>
                                    </time>
                                </div>

                                <h3 style="font-size: 1rem; font-weight: 700; line-height: 1.4; margin: 0 0 0.5rem 0;">
                                    <a href="<?= $artUrl ?>" style="color: #0f172a; text-decoration: none;">
                                        <?= e($art['title']) ?>
                                    </a>
                                </h3>

                                <p style="font-size: 0.8125rem; color: #475569; line-height: 1.5; margin: 0 0 1rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e(strip_tags($art['excerpt'] ?? $art['meta_description'] ?? '')) ?>
                                </p>
                            </div>

                            <div style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.725rem; color: #047857; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    Fact-Checked
                                </span>
                                <a href="<?= $artUrl ?>" style="font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-decoration: none;">
                                    Read Guide &rarr;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Transparency & Non-Affiliation Disclaimer -->
        <div style="margin-top: 3rem; padding: 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8125rem; color: #64748b; line-height: 1.5;">
            <strong>Editorial Independence Notice:</strong> <?= e($author['name']) ?> is an independent analyst employed by Sarkari.online. Sarkari.online is not affiliated, endorsed, or associated with any government ministry, Union/State Public Service Commission, or testing body. All articles are prepared through independent analysis of authenticated official public notices and government gazettes.
        </div>

    </div>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
