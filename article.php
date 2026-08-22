<?php
/**
 * EduPulse - Article Single Page (Phase 2)
 * Full SEO-optimized article rendering with preview mode, breadcrumbs, verification box, FAQs, and JSON-LD schemas.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Data/MockData.php';

use App\Services\ArticleService;
use App\Helpers\Auth;
use App\Helpers\SEOHelper;

$slug = $_GET['slug'] ?? 'neet-ug-2026-counselling-schedule-released-mcc-nic-in';
$slug = trim($slug, '/');
$isPreview = isset($_GET['preview']) && (bool)$_GET['preview'];

// Allow viewing drafts if authenticated as admin in preview mode
$allowDraft = $isPreview && Auth::check();

// Fetch from Database
$article = ArticleService::getBySlug($slug, $allowDraft);

// Fallback to MockData if not in DB yet (for mock demonstration)
if (!$article) {
    $article = MockData::getArticleBySlug($slug);
}

if (!$article) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Normalize author format
$authorName = 'Sarkari.online Editorial Desk';
$authorTitle = 'Education & Career Analyst';
$authorBio = 'Providing verified updates and guides on Indian examinations, admissions, and recruitment notifications.';

if (!empty($article['author_username']) && strtolower($article['author_username']) !== 'admin') {
    $authorName = $article['author_username'];
} elseif (!empty($article['author']) && is_array($article['author'])) {
    $authorName = ($article['author']['name'] ?? '') !== 'admin' ? ($article['author']['name'] ?? $authorName) : $authorName;
    $authorTitle = $article['author']['title'] ?? $authorTitle;
    $authorBio = $article['author']['bio'] ?? $authorBio;
}

// Normalize source format
$sourceName = $article['source_name'] ?? (is_array($article['source'] ?? null) ? $article['source']['name'] : 'Official Statutory Authority');
$sourceUrl = $article['source_url'] ?? (is_array($article['source'] ?? null) ? $article['source']['source_url'] : null);
$sourceRef = $article['source_ref'] ?? (is_array($article['source'] ?? null) ? $article['source']['official_notice_ref'] : null);

// SEO Setup
$pageTitle = !empty($article['meta_title']) ? $article['meta_title'] : $article['title'];
$pageDesc = !empty($article['meta_description']) ? $article['meta_description'] : ($article['excerpt'] ?? '');
$canonicalUrl = !empty($article['canonical_url']) ? $article['canonical_url'] : url('article/' . $article['slug'] . '/');

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
                        <a href="<?= url('category/' . ($article['category_slug'] ?? 'exam-results') . '/') ?>" class="badge" style="background-color: <?= e($article['category_color'] ?? '#1e3a8a') ?>15; color: <?= e($article['category_color'] ?? '#1e3a8a') ?>; font-size: 0.75rem;">
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
                            <div class="author-avatar">
                                <?= mb_substr($authorName, 0, 1) ?>
                            </div>
                            <div>
                                <div class="byline-author-name"><?= e($authorName) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($authorTitle) ?></div>
                            </div>
                        </div>

                        <div class="byline-dates">
                            <span>Published: <strong><?= format_date($article['original_published_at'] ?? $article['published_at'] ?? $article['created_at'], true) ?></strong></span>
                            <?php if (!empty($article['updated_at']) && $article['updated_at'] > ($article['original_published_at'] ?? $article['published_at'] ?? $article['created_at'])): ?>
                                <span class="byline-last-updated" style="color: #b91c1c; font-weight: 600; background: #fef2f2; padding: 2px 8px; border-radius: 4px; border: 1px solid #fecaca;">
                                    Last Updated: <?= format_date($article['updated_at'], true) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Interactive Audio Article Reader -->
                    <?php include __DIR__ . '/components/audio-player.php'; ?>
                </header>

                <!-- Featured Image -->
                <div class="article-featured-media">
                    <?php if (!empty($article['featured_image']) && file_exists(__DIR__ . '/' . ltrim($article['featured_image'], '/'))): ?>
                        <img src="<?= e(url($article['featured_image'])) ?>" alt="<?= e($article['featured_image_alt'] ?? $article['title']) ?>" width="880" height="495" loading="eager">
                    <?php else: ?>
                        <?= render_thumbnail_svg($article['category_slug'] ?? 'exam-results', $article['title'], 880, 495) ?>
                    <?php endif; ?>
                </div>

                <!-- Article Body Content -->
                <div class="article-body-content">
                    <?php
                    $renderedContent = $article['content_html'] ?? $article['content'] ?? '';
                    
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
                    ?>
                    <?= $renderedContent ?>
                </div>

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
                                <a href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer" class="source-ver-link">
                                    Visit Official Authority Portal (<?= e($sourceName) ?>) <?= icon('external-link', 'icon-sm') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Share Buttons Strip -->
                <div class="article-share-strip">
                    <span class="share-label"><?= icon('share', 'icon-sm') ?> Share this alert:</span>
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $canonicalUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-btn-pill" style="color: #047857;">
                        WhatsApp
                    </a>
                    <a href="https://telegram.me/share/url?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener noreferrer" class="share-btn-pill" style="color: #0284c7;">
                        Telegram
                    </a>
                    <button type="button" class="share-btn-pill js-share-btn" data-url="<?= e($canonicalUrl) ?>" data-title="<?= e($article['title']) ?>">
                        Copy Link
                    </button>
                </div>

                <!-- Author Bio Card -->
                <div class="author-bio-card">
                    <div class="author-bio-avatar">
                        <?= mb_substr($authorName, 0, 1) ?>
                    </div>
                    <div class="author-bio-details">
                        <h4><?= e($authorName) ?></h4>
                        <div class="author-bio-role"><?= e($authorTitle) ?></div>
                        <p class="author-bio-desc"><?= e($authorBio) ?></p>
                    </div>
                </div>

            </article>

            <!-- Right Sidebar -->
            <?php include __DIR__ . '/components/sidebar.php'; ?>

        </div>

    </div>
</main>

<!-- Article JSON-LD Structured Data -->
<script type="application/ld+json">
<?= SEOHelper::articleSchema($article, $canonicalUrl) ?>
</script>

<!-- BreadcrumbList JSON-LD Structured Data -->
<script type="application/ld+json">
<?= SEOHelper::breadcrumbSchema($crumbs) ?>
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
