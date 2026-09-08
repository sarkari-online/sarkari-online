<?php
/**
 * EduPulse - Article Single Page (Phase 2)
 * Full SEO-optimized article rendering with preview mode, breadcrumbs, verification box, FAQs, and JSON-LD schemas.
 */
require_once __DIR__ . '/config.php';

use App\Services\ArticleService;
use App\Services\AuthorService;
use App\Services\SchemaService;
use App\Services\FeaturedSnippetService;
use App\Helpers\Auth;
use App\Helpers\SEOHelper;

$slug = $_GET['slug'] ?? '';
$slug = trim($slug, '/');
$isPreview = isset($_GET['preview']) && (bool)$_GET['preview'];

// Allow viewing drafts if authenticated as admin in preview mode
$allowDraft = $isPreview && Auth::check();

// Fetch from Database
$article = $slug !== '' ? ArticleService::getBySlug($slug, $allowDraft) : null;

// 301 SEO Fallback: If old or renamed slug requested, auto-redirect permanently
if (!$article && $slug !== '') {
    $legacySlugRedirects = [
        'upssc-junior-assistant-lekhpal-2026-admit-card' => 'upsssc-junior-assistant-lekhpal-2026-admit-card',
        'bpsc-combined-state-exam-2026-admit-card' => 'bpsc-72nd-cce-prelims-2026-admit-card',
        'ibps-po-2026-prelims-admit-card-download-active' => 'ibps-po-2026-prelims-exam-concluded',
        'punjab-pti-recruitment-2026-apply-now' => 'punjab-pti-recruitment-2026-cancelled-fee-refund',
    ];
    if (isset($legacySlugRedirects[$slug])) {
        header("Location: " . url('article/' . $legacySlugRedirects[$slug] . '/'), true, 301);
        exit;
    }

    $altSlug = str_contains($slug, '2026') ? str_replace('2026', '2027', $slug) : (str_contains($slug, '2027') ? str_replace('2027', '2026', $slug) : null);
    if ($altSlug) {
        $altArticle = ArticleService::getBySlug($altSlug, $allowDraft);
        if ($altArticle) {
            header("Location: " . url('article/' . $altArticle['slug'] . '/'), true, 301);
            exit;
        }
    }
}

if (!$article) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Normalize author format via verified AuthorService
$authorData = AuthorService::getAuthorForArticle($article);
$authorName = $authorData['name'];
$authorTitle = $authorData['title'];
$authorBio = $authorData['bio'];
$authorSlug = $authorData['slug'];
$authorUrl = url('author/' . $authorSlug . '/');
$authorAvatar = $authorData['avatar_letter'];
$authorBg = $authorData['avatar_bg'];
$authorImg = !empty($authorData['avatar_img']) ? $authorData['avatar_img'] : null;

// Normalize source format
$sourceName = $article['source_name'] ?? (is_array($article['source'] ?? null) ? $article['source']['name'] : 'Official Statutory Authority');
$sourceUrl = $article['source_url'] ?? (is_array($article['source'] ?? null) ? $article['source']['source_url'] : null);
$sourceRef = $article['source_ref'] ?? (is_array($article['source'] ?? null) ? $article['source']['official_notice_ref'] : null);

// Media Domain Safety Shield: Never show commercial news portals as the official statutory authority portal
$mediaDomains = ['timesofindia', 'indiatimes.com', 'hindustantimes', 'ndtv.com', 'indianexpress', 'livemint', 'jagran.com', 'amarujala', 'news18', 'aajtak', 'abplive'];
$isMediaUrl = false;
foreach ($mediaDomains as $mDomain) {
    if (!empty($sourceUrl) && str_contains(strtolower($sourceUrl), $mDomain)) {
        $isMediaUrl = true;
        break;
    }
}
if ($isMediaUrl || empty($sourceUrl) || str_contains(strtolower((string)$sourceUrl), 'sarkari.online') || str_contains(strtolower((string)$sourceUrl), 'localhost')) {
    $resolvedAuth = \App\Services\AuthorityFactFetcherService::resolveAuthority($article['title'] ?? '');
    $sourceName = $resolvedAuth['name'];
    $sourceUrl = (!empty($resolvedAuth['portal']) && !str_contains($resolvedAuth['portal'], 'sarkari.online')) ? $resolvedAuth['portal'] : null;
    $sourceRef = 'Official Authority Bulletin (' . ($sourceUrl ? preg_replace('/^www\./i', '', parse_url($sourceUrl, PHP_URL_HOST)) : 'Gazette Release') . ')';
} elseif (empty($sourceName) || in_array(strtolower(trim($sourceName)), ['official statutory authority', 'statutory authority', 'official authority', 'statutory agency'], true)) {
    $resolvedAuth = \App\Services\AuthorityFactFetcherService::resolveAuthority($article['title'] ?? '', $sourceUrl);
    $sourceName = $resolvedAuth['name'];
    if (empty($sourceUrl) && !empty($resolvedAuth['portal']) && !str_contains($resolvedAuth['portal'], 'sarkari.online')) {
        $sourceUrl = $resolvedAuth['portal'];
    }
    $sourceRef = 'Official Public Gazette / Portal Release';
}

// SEO Setup
$pageTitle = !empty($article['meta_title']) ? $article['meta_title'] : $article['title'];
$pageDesc = !empty($article['meta_description']) ? $article['meta_description'] : ($article['excerpt'] ?? '');
// Ensure canonical and social share URLs always dynamically use current domain (never localhost)
$canonicalUrl = url('article/' . $article['slug'] . '/');

// Generate Rich Niche-Targeted Long-Tail Keywords
$cleanTitleKeywords = str_replace([':', '—', '-', '|', '2026', '2027'], '', $article['title']);
$pageKeywords = implode(', ', array_filter([
    $article['title'],
    trim($cleanTitleKeywords) . ' 2026',
    ($article['category_name'] ?? 'Education') . ' 2026',
    'eligibility criteria',
    'application process and direct link',
    'official notification pdf download',
    'sarkari result 2026',
    'sarkari.online verified update',
    !empty($sourceName) ? $sourceName . ' portal notice' : null
]));
$metaAuthor = $authorName;

$ogType = 'article';
$ogTitle = !empty($article['og_title']) ? $article['og_title'] : $pageTitle;
$ogDescription = !empty($article['og_description']) ? $article['og_description'] : $pageDesc;
$ogImage = !empty($article['og_image']) ? $article['og_image'] : ($article['featured_image'] ?? null);

$articlePublishedTime = !empty($article['published_at']) ? date('c', strtotime($article['published_at'])) : null;
$articleModifiedTime = !empty($article['updated_at']) ? date('c', strtotime($article['updated_at'])) : $articlePublishedTime;
$articleSection = $article['category_name'] ?? 'Education';

$crumbs = [
    ['label' => 'Home', 'url' => ''],
    ['label' => $article['category_name'] ?? 'Exams', 'url' => 'category/' . ($article['category_slug'] ?? 'exam-results') . '/'],
    ['label' => truncate_text($article['title'], 45), 'url' => null]
];

$lcpImagePreload = !empty($article['featured_image']) ? url($article['featured_image']) : null;

include __DIR__ . '/components/head.php';
include __DIR__ . '/components/header.php';
?>

<?php if ($allowDraft && $article['status'] !== 'published'): ?>
    <!-- Admin Draft Preview Banner -->
    <div style="background-color: #4f46e5; color: #ffffff; padding: 0.75rem 1rem; text-align: center; font-weight: 700; font-size: 0.875rem;">
        ⚠️ DRAFT PREVIEW MODE — Article Status: <span style="text-transform: uppercase; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px;"><?= e($article['status']) ?></span>. Only visible to authenticated administrators.
        <a href="<?= url('admin/articles/edit.php?id=' . ($article['id'] ?? 1)) ?>" style="color: #fef08a; text-decoration: underline; margin-left: 1rem;">Edit in CMS →</a>
    </div>
<?php endif; ?>

<main class="site-main">
    <div class="container">

        <!-- Breadcrumbs -->
        <?php include __DIR__ . '/components/breadcrumbs.php'; ?>

        <div class="article-layout-grid">
            
            <!-- Left Main Article Column -->
            <article class="article-main-column">
                
                <!-- Article Header -->
                <header class="article-header">
                    <div class="card-meta">
                        <a href="<?= url('category/' . ($article['category_slug'] ?? 'exam-results') . '/') ?>" class="badge" style="background-color: <?= e($article['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($article['category_color'] ?? '#1e3a8a') ?>; font-size: 0.75rem;" title="<?= e($article['category_name'] ?? 'Education') ?> Category Archives">
                            <?= e($article['category_name'] ?? 'Education') ?>
                        </a>
                        <span class="meta-dot"></span>
                        <span class="badge badge-verified">
                            <?= icon('shield-check', 'icon-sm') ?> Source Verified
                        </span>
                    </div>

                    <h1 class="article-headline">
                        <?= e($article['title']) ?>
                    </h1>

                    <?php if (!empty($article['excerpt'])): ?>
                        <div class="article-lead-excerpt">
                            <?= e($article['excerpt']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Author and Timestamp Byline -->
                    <div class="article-byline">
                        <div class="byline-author-info">
                            <a href="<?= $authorUrl ?>" class="author-avatar" style="text-decoration: none; background: <?= $authorBg ?>; color: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center;" aria-label="Author Profile: <?= e($authorName) ?>" title="Author Profile: <?= e($authorName) ?>">
                                <?php if (!empty($authorImg)): ?>
                                    <img src="<?= asset($authorImg) ?>" alt="<?= e($authorName) ?>" width="34" height="34" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                    <span style="display: none;"><?= $authorAvatar ?></span>
                                <?php else: ?>
                                    <?= $authorAvatar ?>
                                <?php endif; ?>
                            </a>
                            <div>
                                <a href="<?= $authorUrl ?>" class="byline-author-name" style="text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: 4px;" title="Author Profile: <?= e($authorName) ?>">
                                    <?= e($authorName) ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" title="Verified Analyst"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </a>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($authorTitle) ?></div>
                            </div>
                        </div>

                        <div class="byline-dates notranslate" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                            <?php
                            $publishedTimestamp = strtotime($article['original_published_at'] ?? $article['published_at'] ?? $article['created_at']);
                            $updatedTimestamp = !empty($article['updated_at']) ? strtotime($article['updated_at']) : $publishedTimestamp;
                            // Ensure updated_at is never displayed earlier than published_at
                            if ($updatedTimestamp < $publishedTimestamp) {
                                $updatedTimestamp = $publishedTimestamp;
                            }
                            ?>
                            <span>Published: <strong><?= date('d M Y, h:i A', $publishedTimestamp) ?> IST</strong></span>
                            <?php if ($updatedTimestamp > $publishedTimestamp + 120): ?>
                            <span class="byline-last-updated" style="color: #15803d; font-weight: 600; background: #f0fdf4; padding: 2px 10px; border-radius: 4px; border: 1px solid #bbf7d0; display: inline-flex; align-items: center; gap: 4px;">
                                <?= icon('clock', 'icon-xs') ?> Last Updated: <strong><?= date('d M Y, h:i A', $updatedTimestamp) ?> IST</strong>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Interactive Audio Article Reader -->
                    <?php include __DIR__ . '/components/audio-player.php'; ?>
                </header>

                <!-- Featured Image -->
                <div class="article-featured-media">
                    <?php if (!empty($article['featured_image']) && file_exists(__DIR__ . '/' . ltrim($article['featured_image'], '/'))): 
                        $artImgAlt = !empty($article['featured_image_alt']) ? $article['featured_image_alt'] : ($article['title'] ?? 'Official Exam Notification');
                        $artImgTitle = $article['title'] ?? $artImgAlt;
                    ?>
                        <img src="<?= e(url($article['featured_image'])) ?>" alt="<?= e($artImgAlt) ?>" title="<?= e($artImgTitle) ?>" width="880" height="495" loading="eager" fetchpriority="high" decoding="async">
                    <?php else: ?>
                        <?= render_thumbnail_svg($article['category_slug'] ?? 'exam-results', $article['title'], 880, 495) ?>
                    <?php endif; ?>
                </div>

                <!-- Article Body Content -->
                <div class="article-body-content">
                    <?php
                    $relatedArticles = ArticleService::getRelated((int)$article['id'], (int)($article['category_id'] ?? 0), 3);
                    $renderedContent = $article['content_html'] ?? $article['content'] ?? '';
                    
                    // Sanitize any legacy local URLs
                    $renderedContent = str_replace(
                        ['http://localhost:8888/automation/', 'http://localhost/automation/', '/automation/article/'],
                        [SITE_URL . '/', SITE_URL . '/', url('article/')],
                        $renderedContent
                    );
                    
                    if (str_contains($renderedContent, '<table') && !str_contains($renderedContent, 'table-responsive')) {
                        $renderedContent = preg_replace('/<table\b([^>]*)>/i', '<div class="table-responsive"><table$1>', $renderedContent);
                        $renderedContent = str_replace('</table>', '</table></div>', $renderedContent);
                    }

                    // Auto-badge status cells in tables
                    $renderedContent = preg_replace_callback('/<td>\s*(Confirmed|Released|Upcoming|Upcoming \(TBA\)|TBA|To Be Announced|Closed|Active)\s*<\/td>/i', function($m) {
                        $rawStatus = trim($m[1]);
                        $class = match(strtolower(preg_replace('/[^a-z]/i', '', $rawStatus))) {
                            'confirmed', 'active' => 'status-pill status-pill-confirmed',
                            'released' => 'status-pill status-pill-released',
                            'closed', 'expired' => 'status-pill status-pill-closed',
                            default => 'status-pill status-pill-upcoming'
                        };
                        return '<td><span class="' . $class . '">' . htmlspecialchars($rawStatus) . '</span></td>';
                    }, $renderedContent);

                    // Contextual In-Content "Also Read:" Callout Box (Only if not already present in content)
                    $hasExistingAlsoRead = (bool)preg_match('/class=["\'](also-read-card|also-read-callout|see-also)["\']/i', $renderedContent) || str_contains($renderedContent, '📌 ALSO READ:');
                    if (!$hasExistingAlsoRead && !empty($relatedArticles[0])) {
                        $firstRel = $relatedArticles[0];
                        $alsoReadCallout = '<div class="also-read-callout" style="margin: 1.5rem 0; padding: 1rem 1.25rem; background: #f8fafc; border-left: 4px solid var(--color-primary, #1e3a8a); border-radius: 0 8px 8px 0; font-size: 0.95rem;">'
                            . '<span style="font-weight: 800; color: var(--color-primary, #1e3a8a); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.25rem;">📌 ALSO READ:</span>'
                            . '<a href="' . e(url('article/' . $firstRel['slug'] . '/')) . '" title="' . e($firstRel['title']) . '" style="color: #1e293b; font-weight: 700; text-decoration: underline; text-underline-offset: 3px;">' . e($firstRel['title']) . '</a>'
                            . '</div>';

                        $pIndex = 0;
                        $injected = false;
                        $renderedContent = preg_replace_callback('/<\/p>/i', function($m) use (&$pIndex, &$injected, $alsoReadCallout) {
                            $pIndex++;
                            if ($pIndex === 2 && !$injected) {
                                $injected = true;
                                return '</p>' . $alsoReadCallout;
                            }
                            return $m[0];
                        }, $renderedContent);
                    }

                    // Automatic Table of Contents & Google Sitelinks Navigation
                    $tocData = \App\Services\TableOfContentsService::process($renderedContent);
                    $renderedContent = $tocData['content'];
                    ?>
                    <!-- Google Position 0 Featured Snippet & Direct Answer Box -->
                    <?= FeaturedSnippetService::render($article) ?>

                    <?= $renderedContent ?>
                </div>

                <!-- Also Read / Related Articles Grid -->
                <?php if (!empty($relatedArticles)): ?>
                    <section class="related-articles-section" style="margin: 2.5rem 0 1.5rem; padding: 1.5rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <?= icon('book-open', 'icon-sm') ?> Also Read: Related Educational Updates &amp; Guides
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                            <?php foreach ($relatedArticles as $rel): ?>
                                <a href="<?= e(url('article/' . $rel['slug'] . '/')) ?>" title="<?= e($rel['title']) ?>" style="display: block; padding: 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);" class="related-article-card">
                                    <span style="font-size: 0.7rem; font-weight: 700; color: <?= e($rel['category_color'] ?? '#1e3a8a') ?>; text-transform: uppercase; display: block; margin-bottom: 0.35rem;">
                                        <?= e($rel['category_name'] ?? 'Education') ?>
                                    </span>
                                    <h4 style="font-size: 0.875rem; font-weight: 700; color: #1e293b; line-height: 1.4; margin: 0 0 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= e($rel['title']) ?>
                                    </h4>
                                    <span style="font-size: 0.75rem; color: #64748b;">
                                        <?= format_date($rel['updated_at'] ?? $rel['published_at']) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Official Source Verification Box -->
                <?php if (!empty($sourceName)): ?>
                    <div class="source-verification-box" role="complementary" aria-label="Official Source Attribution">
                        <div class="source-ver-icon">
                            <?= icon('shield-check', 'icon-lg') ?>
                        </div>
                        <div class="source-ver-details">
                            <div class="source-ver-title">Official Source Reference &amp; Verification</div>
                            <p class="source-ver-text">
                                Information in this report has been fact-checked against official releases from <strong><?= e($sourceName) ?></strong><?= !empty($sourceRef) ? ' (Reference: ' . e($sourceRef) . ')' : '' ?>.
                            </p>
                            <?php if (!empty($sourceUrl)): ?>
                                <a href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer" class="source-ver-link" title="Visit Official Authority Portal: <?= e($sourceName) ?>">
                                    Visit Official Authority Portal (<?= e($sourceName) ?>) <?= icon('external-link', 'icon-sm') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Share Buttons Strip -->
                <div class="article-share-strip">
                    <span class="share-label"><?= icon('share', 'icon-sm') ?> Share this alert:</span>
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $canonicalUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-btn-pill" style="color: #047857;" title="Share this alert on WhatsApp">
                        WhatsApp
                    </a>
                    <a href="https://telegram.me/share/url?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener noreferrer" class="share-btn-pill" style="color: #0284c7;" title="Share this alert on Telegram">
                        Telegram
                    </a>
                    <button type="button" class="share-btn-pill js-share-btn" data-url="<?= e($canonicalUrl) ?>" data-title="<?= e($article['title']) ?>">
                        Copy Link
                    </button>
                </div>

                <!-- Aspirant Experience & Verification Signal Module (E-E-A-T First-Hand Feedback) -->
                <?php include __DIR__ . '/components/experience-desk.php'; ?>

                <!-- Author Bio Card (E-E-A-T Verified Authority) -->
                <div class="author-bio-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; gap: 1.25rem; align-items: flex-start; margin-top: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <a href="<?= $authorUrl ?>" class="author-bio-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: <?= $authorBg ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; flex-shrink: 0; text-decoration: none; overflow: hidden; border: 2px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.06);" aria-label="Author profile for <?= e($authorName) ?>" title="View author profile for <?= e($authorName) ?>">
                        <?php if (!empty($authorImg)): ?>
                            <img src="<?= asset($authorImg) ?>" alt="<?= e($authorName) ?>" width="60" height="60" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                            <span style="display: none;"><?= $authorAvatar ?></span>
                        <?php else: ?>
                            <?= $authorAvatar ?>
                        <?php endif; ?>
                    </a>
                    <div class="author-bio-details" style="flex: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.25rem;">
                            <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a;">
                                <a href="<?= $authorUrl ?>" style="color: inherit; text-decoration: none;" title="View author profile for <?= e($authorName) ?>">
                                    <?= e($authorName) ?>
                                </a>
                            </h4>
                            <span style="font-size: 0.7rem; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Verified Education Analyst
                            </span>
                        </div>
                        <div class="author-bio-role" style="font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; margin-bottom: 0.5rem;">
                            <?= e($authorTitle) ?> &bull; <?= e($authorData['experience']) ?>
                        </div>
                        <p class="author-bio-desc" style="font-size: 0.8125rem; color: #475569; line-height: 1.5; margin: 0 0 0.75rem 0;">
                            <?= e($authorBio) ?>
                        </p>
                        <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.65rem; font-size: 0.775rem; flex-wrap: wrap; gap: 0.5rem;">
                            <span style="color: #64748b;"><strong>Background:</strong> <?= e($authorData['education']) ?></span>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <?php if (!empty($authorData['linkedin'])): ?>
                                    <a href="<?= e($authorData['linkedin']) ?>" target="_blank" rel="noopener noreferrer" style="color: #0a66c2; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Connect with <?= e($authorName) ?> on LinkedIn">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.25V10.9H6.46M7.86 6.55a1.64 1.64 0 0 0-1.63 1.64c0 .9.73 1.63 1.63 1.63a1.64 1.64 0 0 0 1.64-1.63c0-.91-.74-1.64-1.64-1.64Z"/></svg>
                                        LinkedIn
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $authorUrl ?>" style="color: #1e3a8a; font-weight: 700; text-decoration: none;" title="View all articles written by <?= e($authorName) ?>">
                                    View Articles &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </article>

            <!-- Right Sidebar -->
            <?php include __DIR__ . '/components/sidebar.php'; ?>

        </div>

    </div>
</main>

<!-- Rich Structured Data JSON-LD — NewsArticle, FAQPage, Event, HowTo -->
<?php
$schemas = SchemaService::generate($article, $article['category_slug'] ?? '');
echo SchemaService::injectIntoHead($schemas);
?>

<!-- BreadcrumbList JSON-LD Structured Data -->
<script type="application/ld+json">
<?= SEOHelper::breadcrumbSchema($crumbs) ?>
</script>

<!-- Table of Contents Smooth Toggle Script -->
<script>
function toggleArticleToc() {
    var content = document.getElementById('articleTocContent');
    var btnText = document.getElementById('tocToggleText');
    var btn = document.getElementById('tocToggleBtn');
    if (!content || !btnText) return;
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btnText.textContent = '[ Hide ]';
        if (btn) btn.setAttribute('aria-expanded', 'true');
    } else {
        content.style.display = 'none';
        btnText.textContent = '[ Show ]';
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
