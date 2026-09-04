<?php
/**
 * Sarkari.online - Hot-Grid Matrix with Official Agency Watermarks
 * 8-Box High-Impact Color Matrix featuring subtle vector security watermarks
 * (Ashoka Lion, Railway Locomotive, Police Shield, Stethoscope, Bank Pillars, AICTE Gear, CBSE Seal).
 * Strictly zero emojis, 100% vector SVG, instant load time, fully responsive.
 */

if (!isset($hotArticles)) {
    $dbHero = App\Services\ArticleService::getLatestPublished(8);
    if (!empty($dbHero)) {
        $hotArticles = $dbHero;
    } else {
        $heroData = MockData::getHeroArticles();
        $hotArticles = MockData::getLatestArticles(8);
    }
}

if (!function_exists('get_agency_watermark_svg')) {
    function get_agency_watermark_svg(string $type): string {
        switch ($type) {
            case 'railway':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M25 22C25 15.37 30.37 10 37 10H63C69.63 10 75 15.37 75 22V62C75 66.42 71.42 70 67 70H33C28.58 70 25 66.42 25 62V22ZM32 20C30.9 20 30 20.9 30 22V38C30 39.1 30.9 40 32 40H68C69.1 40 70 39.1 70 38V22C70 20.9 69.1 20 68 20H32ZM37 54C37 57.31 39.69 60 43 60C46.31 60 49 57.31 49 54C49 50.69 46.31 48 43 48C39.69 48 37 50.69 37 54ZM51 54C51 57.31 53.69 60 57 60C60.31 60 63 57.31 63 54C63 50.69 60.31 48 57 48C53.69 48 51 50.69 51 54ZM20 78L28 72H72L80 78H20ZM14 88L24 82H76L86 88H14Z"/></svg>';

            case 'police':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 5L18 19V45C18 68 31.8 89.2 50 95C68.2 89.2 82 68 82 45V19L50 5ZM50 16L74 26.5V45C74 63.8 63.5 81.3 50 86.8C36.5 81.3 26 63.8 26 45V26.5L50 16ZM50 30L53.7 41.4H65.7L56 48.4L59.7 59.8L50 52.8L40.3 59.8L44 48.4L34.3 41.4H46.3L50 30Z"/></svg>';

            case 'medical':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M44 12V24H32V36H44V48H56V36H68V24H56V12H44ZM28 16C28 13.8 29.8 12 32 12H36V18H32V38C32 47.9 40.1 56 50 56C59.9 56 68 47.9 68 38V18H64V12H68C70.2 12 72 13.8 72 16V38C72 50.1 62.1 60 50 60C37.9 60 28 50.1 28 38V16ZM50 60V72C50 76.4 46.4 80 42 80H40C35.6 80 32 83.6 32 88C32 92.4 35.6 96 40 96H44C48.4 96 52 92.4 52 88V60H50ZM68 76C63.6 76 60 79.6 60 84C60 88.4 63.6 92 68 92C72.4 92 76 88.4 76 84C76 79.6 72.4 76 68 76Z"/></svg>';

            case 'bank':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 8L10 28V34H90V28L50 8ZM18 40V74H28V40H18ZM38 40V74H48V40H38ZM58 40V74H68V40H58ZM78 40V74H88V40H78ZM8 80V90H92V80H8Z"/></svg>';

            case 'aicte':
            case 'technical':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 32C40.06 32 32 40.06 32 50C32 59.94 40.06 68 50 68C59.94 68 68 59.94 68 50C68 40.06 59.94 32 50 32ZM50 42C54.42 42 58 45.58 58 50C58 54.42 54.42 58 50 58C45.58 58 42 54.42 42 50C42 45.58 45.58 42 50 42ZM90 44V36L78.6 34.1C77.8 31.4 76.5 28.9 74.9 26.6L81.7 17.2L74.8 10.3L65.4 17.1C63.1 15.5 60.6 14.2 57.9 13.4L56 2H44L42.1 13.4C39.4 14.2 36.9 15.5 34.6 17.1L25.2 10.3L18.3 17.2L25.1 26.6C23.5 28.9 22.2 31.4 21.4 34.1L10 36V44L21.4 45.9C22.2 48.6 23.5 51.1 25.1 53.4L18.3 62.8L25.2 69.7L34.6 62.9C36.9 64.5 39.4 65.8 42.1 66.6L44 78H56L57.9 66.6C60.6 65.8 63.1 64.5 65.4 62.9L74.8 69.7L81.7 62.8L74.9 53.4C76.5 51.1 77.8 48.6 78.6 45.9L90 44Z"/></svg>';

            case 'cbse':
            case 'education':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 32C42 22 28 20 12 20V74C28 74 42 76 50 86C58 76 72 74 88 74V20C72 20 58 22 50 32ZM46 72C38.6 64.6 26.8 62 18 62V28C26.8 28 38.6 30.6 46 38V72ZM82 62C73.2 62 61.4 64.6 54 72V38C61.4 30.6 73.2 28 82 28V62ZM50 8L47 16H53L50 8ZM32 14L28 20L34 22L32 14ZM68 14L66 22L72 20L68 14Z"/></svg>';

            case 'scholarship':
            case 'degree':
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 14L6 34L50 54L86 37.6V62H94V34L50 14ZM50 62L22 49.3V66C22 76 34.5 86 50 86C65.5 86 78 76 78 66V49.3L50 62Z"/></svg>';

            case 'ashoka':
            default:
                return '<svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M50 8C43.5 8 38 13.5 38 20C38 24.2 40.2 27.8 43.5 29.8C40 33 34 38 34 46C34 52 38 56 42 58C41 62 40 68 40 72H60C60 68 59 62 58 58C62 56 66 52 66 46C66 38 60 33 56.5 29.8C59.8 27.8 62 24.2 62 20C62 13.5 56.5 8 50 8ZM22 30C18 30 14 34 14 38C14 42 16 45 18 47C16 51 14 56 16 62C18 68 22 70 26 72H34C34 66 34 58 36 54C32 52 28 48 28 42C28 36 32 32 36 30C32 30 26 30 22 30ZM78 30C74 30 68 30 64 30C68 32 72 36 72 42C72 48 68 52 64 54C66 58 66 66 66 72H74C78 70 82 68 84 62C86 56 84 51 82 47C84 45 86 42 86 38C86 34 82 30 78 30ZM20 78H80V84H20V78ZM14 88H86V94H14V88Z"/></svg>';
        }
    }
}

if (!function_exists('detect_agency_meta')) {
    function detect_agency_meta(string $title, string $categorySlug, string $agencyName = ''): array {
        $haystack = strtolower($title . ' ' . $categorySlug . ' ' . $agencyName);

        if (str_contains($haystack, 'rrb') || str_contains($haystack, 'railway') || str_contains($haystack, 'rrc') || str_contains($haystack, 'ntpc')) {
            return [
                'type' => 'railway',
                'tag' => 'RRB RAILWAY',
                'color' => '#059669',
                'bg_tint' => '#ecfdf5',
                'action' => 'Apply Online',
                'action_icon' => 'briefcase'
            ];
        }

        if (str_contains($haystack, 'police') || str_contains($haystack, 'uppbpb') || str_contains($haystack, 'constable') || str_contains($haystack, 'cisf') || str_contains($haystack, 'bsf') || str_contains($haystack, 'crpf') || str_contains($haystack, 'itbp') || str_contains($haystack, 'ssb') || str_contains($haystack, 'army') || str_contains($haystack, 'defence')) {
            return [
                'type' => 'police',
                'tag' => 'POLICE / DEFENCE',
                'color' => '#d97706',
                'bg_tint' => '#fffbeb',
                'action' => 'Check Update',
                'action_icon' => 'shield-check'
            ];
        }

        if (str_contains($haystack, 'neet') || str_contains($haystack, 'medical') || str_contains($haystack, 'aiims') || str_contains($haystack, 'mcc') || str_contains($haystack, 'nbems') || str_contains($haystack, 'nursing')) {
            return [
                'type' => 'medical',
                'tag' => 'NTA / NEET',
                'color' => '#0284c7',
                'bg_tint' => '#f0f9ff',
                'action' => 'Check Status',
                'action_icon' => 'award'
            ];
        }

        if (str_contains($haystack, 'ibps') || str_contains($haystack, 'sbi') || str_contains($haystack, 'bank') || str_contains($haystack, 'rbi') || str_contains($haystack, 'po / mt')) {
            return [
                'type' => 'bank',
                'tag' => 'BANKING',
                'color' => '#0f766e',
                'bg_tint' => '#f0fdfa',
                'action' => 'Apply Online',
                'action_icon' => 'briefcase'
            ];
        }

        if (str_contains($haystack, 'aicte') || str_contains($haystack, 'fellowship') || str_contains($haystack, 'technical') || str_contains($haystack, 'diploma') || str_contains($haystack, 'polytechnic')) {
            return [
                'type' => 'aicte',
                'tag' => 'AICTE',
                'color' => '#7c3aed',
                'bg_tint' => '#f5f3ff',
                'action' => 'Online Form',
                'action_icon' => 'file-text'
            ];
        }

        if (str_contains($haystack, 'cbse') || str_contains($haystack, 'icse') || str_contains($haystack, 'board') || str_contains($haystack, '10th') || str_contains($haystack, '12th') || str_contains($haystack, 'revaluation') || str_contains($haystack, 'bseb') || str_contains($haystack, 'upmsp')) {
            return [
                'type' => 'cbse',
                'tag' => 'BOARD EXAMS',
                'color' => '#e11d48',
                'bg_tint' => '#fff1f2',
                'action' => 'Check Result',
                'action_icon' => 'check-circle'
            ];
        }

        if (str_contains($haystack, 'upsc') || str_contains($haystack, 'nda') || str_contains($haystack, 'cds') || str_contains($haystack, 'civil services') || str_contains($haystack, 'ias')) {
            return [
                'type' => 'ashoka',
                'tag' => 'UPSC',
                'color' => '#1e3a8a',
                'bg_tint' => '#eff6ff',
                'action' => 'View Notice',
                'action_icon' => 'file-text'
            ];
        }

        if (str_contains($haystack, 'ssc') || str_contains($haystack, 'cgl') || str_contains($haystack, 'chsl') || str_contains($haystack, 'mts') || str_contains($haystack, 'cpo') || str_contains($haystack, 'je ')) {
            return [
                'type' => 'ashoka',
                'tag' => 'SSC',
                'color' => '#dc2626',
                'bg_tint' => '#fef2f2',
                'action' => 'Download Slip',
                'action_icon' => 'id-card'
            ];
        }

        if (str_contains($haystack, 'scholarship') || str_contains($haystack, 'nsp') || str_contains($haystack, 'scheme')) {
            return [
                'type' => 'scholarship',
                'tag' => 'SCHOLARSHIP',
                'color' => '#2563eb',
                'bg_tint' => '#eff6ff',
                'action' => 'Apply Online',
                'action_icon' => 'external-link'
            ];
        }

        return [
            'type' => 'ashoka',
            'tag' => 'STATUTORY NOTICE',
            'color' => '#1e40af',
            'bg_tint' => '#eff6ff',
            'action' => 'View Circular',
            'action_icon' => 'file-text'
        ];
    }
}
?>
<section class="sarkari-hot-section" aria-label="Top Trending Official Examinations and Forms">
    <div class="sarkari-hot-header">
        <div class="sarkari-hot-title">
            <?= icon('bolt', 'hot-header-icon') ?>
            <span>Trending Official Updates</span>
        </div>
        <div class="sarkari-hot-badge">
            <span class="hot-live-dot"></span>
            <span>Live Official Gazettes</span>
        </div>
    </div>

    <div class="sarkari-hot-grid">
        <?php foreach ($hotArticles as $item): 
            $itemAuth = App\Services\AuthorityFactFetcherService::resolveAuthority($item['title'] ?? '', $item['source_url'] ?? '');
            $agencyName = $itemAuth['name'] ?? '';
            $catSlug = $item['category_slug'] ?? $item['category'] ?? 'exam-results';
            
            $meta = detect_agency_meta($item['title'] ?? '', $catSlug, $agencyName);

            // Refine action by title keyword
            $itemTitleLower = strtolower($item['title'] ?? '');
            $actionLabel = $meta['action'];
            $actionIcon = $meta['action_icon'];
            if (str_contains($itemTitleLower, 'result')) {
                $actionLabel = 'Check Result';
                $actionIcon = 'award';
            } elseif (str_contains($itemTitleLower, 'admit card') || str_contains($itemTitleLower, 'hall ticket') || str_contains($itemTitleLower, 'slip')) {
                $actionLabel = 'Download Slip';
                $actionIcon = 'id-card';
            } elseif (str_contains($itemTitleLower, 'answer key')) {
                $actionLabel = 'Check Key';
                $actionIcon = 'check-circle';
            } elseif (str_contains($itemTitleLower, 'apply') || str_contains($itemTitleLower, 'form') || str_contains($itemTitleLower, 'recruitment')) {
                $actionLabel = 'Apply Online';
                $actionIcon = 'briefcase';
            }

            $watermarkSvg = get_agency_watermark_svg($meta['type']);
        ?>
            <article class="sarkari-hot-card" style="--card-accent: <?= e($meta['color']) ?>; --card-tint: <?= e($meta['bg_tint']) ?>;">
                <!-- Subtle Vector Watermark -->
                <div class="card-watermark" aria-hidden="true">
                    <?= $watermarkSvg ?>
                </div>

                <div class="hot-card-top">
                    <span class="hot-agency-tag" style="color: <?= e($meta['color']) ?>; background-color: <?= e($meta['bg_tint']) ?>; border: 1px solid <?= e($meta['color']) ?>25;">
                        <?= e($meta['tag']) ?>
                    </span>
                    <time datetime="<?= e($item['published_at'] ?? '') ?>" class="hot-card-time notranslate">
                        <?= time_ago($item['published_at'] ?? 'now') ?>
                    </time>
                </div>

                <h2 class="hot-card-title">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>" title="<?= e($item['title']) ?>">
                        <?= e($item['title']) ?>
                    </a>
                </h2>

                <div class="hot-card-bottom">
                    <a href="<?= url('article/' . $item['slug'] . '/') ?>" class="hot-card-action" style="color: <?= e($meta['color']) ?>;">
                        <span><?= e($actionLabel) ?></span>
                        <?= icon('arrow-right', 'hot-icon-xs') ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
