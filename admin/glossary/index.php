<?php
/**
 * Sarkari.online - Admin Full Forms & Examination Glossary Control Panel
 * Dual Mode: Manual 1-Click Instant AI Generator + Autonomous 5-Slot Daily Scheduler.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Services\GlossaryPipelineService;
use App\Services\GlossaryService;

Auth::requireAuth();

$adminPageTitle = 'Govt Full Forms & Glossary Hub';
$adminPageKey = 'glossary';

$message = null;
$messageType = 'success';

$pipeline = new GlossaryPipelineService();
$schedulerState = GlossaryPipelineService::getSchedulerState();

// Handle Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Toggle Autonomous Scheduler
        if ($action === 'toggle_scheduler') {
            $currentStatus = !empty($schedulerState['enabled']);
            GlossaryPipelineService::updateSchedulerSettings(!$currentStatus);
            $schedulerState = GlossaryPipelineService::getSchedulerState();
            $message = !$currentStatus 
                ? "▶️ Autonomous 5-Slot Glossary Scheduler RESUMED! Terms will publish at 09:30, 13:00, 16:30, 19:30, 22:00 IST." 
                : "⏸️ Autonomous 5-Slot Glossary Scheduler PAUSED. Manual 1-click publishing remains 100% active.";
            $messageType = !$currentStatus ? 'success' : 'warning';
        }

        // 2. Publish Candidate Term (1-Click)
        elseif ($action === 'publish_candidate') {
            $acronym = trim($_POST['acronym'] ?? '');
            $hintFull = trim($_POST['hint_full_form'] ?? '');
            $category = trim($_POST['category'] ?? '');

            if (!empty($acronym)) {
                @set_time_limit(180);
                $res = $pipeline->generateAndPublish($acronym, $hintFull ?: null, $category ?: null);
                if (!empty($res['success'])) {
                    $slug = $res['slug'] ?? strtolower($acronym);
                    $viewUrl = url("full-forms/{$slug}/");
                    $message = "🎉 Successfully published <strong>{$acronym}</strong> ({$res['full_form_en']})! <a href=\"{$viewUrl}\" target=\"_blank\" style=\"color: #1e3a8a; text-decoration: underline; font-weight: bold;\">View Live Page &rarr;</a>";
                    $messageType = 'success';
                } else {
                    $message = "❌ Generation failed: " . ($res['error'] ?? 'Unknown AI error.');
                    $messageType = 'danger';
                }
            }
        }

        // 3. Instant Manual Generation
        elseif ($action === 'instant_generate') {
            $acronym = trim($_POST['custom_acronym'] ?? '');
            $hint = trim($_POST['custom_hint'] ?? '');
            $category = trim($_POST['custom_category'] ?? '');

            if (!empty($acronym)) {
                @set_time_limit(180);
                $res = $pipeline->generateAndPublish($acronym, $hint ?: null, $category ?: null);
                if (!empty($res['success'])) {
                    $slug = $res['slug'] ?? strtolower($acronym);
                    $viewUrl = url("full-forms/{$slug}/");
                    $message = "🚀 Instant Term Published! <strong>{$acronym}</strong> is live. <a href=\"{$viewUrl}\" target=\"_blank\" style=\"color: #1e3a8a; text-decoration: underline; font-weight: bold;\">Open Public URL &rarr;</a>";
                    $messageType = 'success';
                } else {
                    $message = "❌ Error: " . ($res['error'] ?? 'Failed to generate term.');
                    $messageType = 'danger';
                }
            } else {
                $message = "Please provide an acronym (e.g., UPPRPB, CEPTAM, NORCET).";
                $messageType = 'warning';
            }
        }

        // 4. Dismiss Candidate Term
        elseif ($action === 'dismiss_candidate') {
            $candId = (int)($_POST['candidate_id'] ?? 0);
            if ($candId > 0) {
                GlossaryPipelineService::dismissCandidate($candId);
                $message = "Candidate term dismissed from queue.";
                $messageType = 'info';
            }
        }
    }
}

// Data Queries
$candidates = GlossaryPipelineService::getPendingCandidates(60);
$publishedCount = 0;
try {
    $publishedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM glossary_terms");
} catch (\Throwable $e) {}

$activeTab = $_GET['tab'] ?? 'queue';

// Published Terms query for Tab 3
$publishedTerms = [];
$searchQuery = trim($_GET['q'] ?? '');
if ($activeTab === 'published') {
    $sql = "SELECT g.*, f.pay_level_7cpc, f.basic_pay_min 
            FROM glossary_terms g 
            LEFT JOIN full_form_entity_facts f ON g.id = f.full_form_id";
    $params = [];
    if (!empty($searchQuery)) {
        $sql .= " WHERE g.acronym LIKE :q OR g.full_form_en LIKE :q OR g.full_form_hi LIKE :q";
        $params['q'] = "%{$searchQuery}%";
    }
    $sql .= " ORDER BY g.id DESC LIMIT 100";
    try {
        $publishedTerms = Database::fetchAll($sql, $params);
    } catch (\Throwable $e) {}
}

$isAutoActive = !empty($schedulerState['enabled']);
$todayPublishedCount = count($schedulerState['published_today'] ?? []);
$dailyLimit = (int)($schedulerState['daily_limit'] ?? 5);

include dirname(__DIR__) . '/components/header.php';
?>

<div class="admin-content-body">

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.95rem;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Control Header & Master Stats -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.75rem; background: #ffffff; padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
        <div>
            <h1 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; display: flex; align-items: center; gap: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <span>Govt Full Forms &amp; Glossary Engine</span>
            </h1>
            <p style="font-size: 0.85rem; color: #64748b; margin: 0;">
                Evergreen authority lexicography &middot; 100% verified Indian statutory exam acronyms &middot; Schema-locked DefinedTerms
            </p>
        </div>

        <!-- Auto-Publish Toggle Switch -->
        <div style="display: flex; align-items: center; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.6rem 1rem; border-radius: 8px;">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Autonomous 5-Slot Mode</div>
                <div style="font-size: 0.88rem; font-weight: 800; color: <?= $isAutoActive ? '#16a34a' : '#dc2626' ?>;">
                    <?= $isAutoActive ? 'ACTIVE (5 Slots/Day)' : 'PAUSED (Manual Only)' ?>
                </div>
            </div>
            <form method="POST" style="margin: 0;">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="toggle_scheduler">
                <button type="submit" class="btn btn-sm <?= $isAutoActive ? 'btn-outline' : 'btn-success' ?>" style="font-size: 0.8rem; font-weight: 700; padding: 0.4rem 0.8rem;">
                    <?= $isAutoActive ? '⏸️ Pause Auto' : '▶️ Resume Auto' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Daily Slots Tracker Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 0.85rem; color: #334155; font-weight: 700;">
                Today's Slot Progress: <span style="color: #2563eb; font-size: 1.1rem;"><?= $todayPublishedCount ?></span> / <?= $dailyLimit ?>
            </div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <?php foreach (($schedulerState['slots'] ?? GlossaryPipelineService::DEFAULT_SLOTS) as $slot): 
                    $isExecuted = in_array($slot, $schedulerState['executed_slots'] ?? [], true);
                ?>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; border: 1px solid <?= $isExecuted ? '#86efac' : '#cbd5e1' ?>; background: <?= $isExecuted ? '#f0fdf4' : '#f8fafc' ?>; color: <?= $isExecuted ? '#166534' : '#64748b' ?>;">
                        <?= $isExecuted ? '✓' : '⏰' ?> <?= $slot ?> IST
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="font-size: 0.82rem; color: #64748b;">
            Total Live Published: <strong style="color: #0f172a;"><?= $publishedCount ?> terms</strong>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem;">
        <a href="<?= url('admin/glossary/?tab=queue') ?>" style="padding: 0.65rem 1.25rem; font-size: 0.9rem; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === 'queue' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'queue' ? '#2563eb' : '#64748b' ?>; display: flex; align-items: center; gap: 6px;">
            <span>Candidate Queue</span>
            <span style="background: #eff6ff; color: #2563eb; padding: 2px 7px; border-radius: 9999px; font-size: 0.72rem;"><?= count($candidates) ?></span>
        </a>
        <a href="<?= url('admin/glossary/?tab=instant') ?>" style="padding: 0.65rem 1.25rem; font-size: 0.9rem; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === 'instant' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'instant' ? '#2563eb' : '#64748b' ?>; display: flex; align-items: center; gap: 6px;">
            <span>⚡ Instant Term Generator</span>
        </a>
        <a href="<?= url('admin/glossary/?tab=published') ?>" style="padding: 0.65rem 1.25rem; font-size: 0.9rem; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === 'published' ? '#2563eb' : 'transparent' ?>; color: <?= $activeTab === 'published' ? '#2563eb' : '#64748b' ?>; display: flex; align-items: center; gap: 6px;">
            <span>Published Directory (<?= $publishedCount ?>)</span>
        </a>
    </div>

    <!-- TAB 1: CANDIDATE QUEUE -->
    <?php if ($activeTab === 'queue'): ?>
        <div class="admin-table-box">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem 0;">Pre-Screened High-Demand Government Terms</h2>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 0;">Ranked by search volume &amp; statutory exam priority. Click 'Publish Now' to generate instant full details.</p>
                </div>
                <span style="font-size: 0.78rem; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 6px;">
                    <?= count($candidates) ?> Pending
                </span>
            </div>

            <?php if (empty($candidates)): ?>
                <div style="padding: 3rem; text-align: center; color: #64748b;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="margin-bottom: 0.75rem;"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    <p style="margin: 0 0 0.5rem 0; font-weight: 600;">Queue is currently clear!</p>
                    <p style="font-size: 0.85rem; margin: 0;">Use the <strong>Instant Term Generator</strong> tab to add new terms or let the background harvester discover them.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" style="margin: 0; width: 100%; font-size: 0.88rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.78rem; text-transform: uppercase;">
                                <th style="padding: 0.75rem 1rem;">Priority</th>
                                <th style="padding: 0.75rem 1rem;">Acronym</th>
                                <th style="padding: 0.75rem 1rem;">Proposed Full Form</th>
                                <th style="padding: 0.75rem 1rem;">Category</th>
                                <th style="padding: 0.75rem 1rem;">Conducting Body</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $cand): 
                                $pri = (int)($cand['priority_score'] ?? 50);
                                $priColor = $pri >= 90 ? '#ef4444' : ($pri >= 80 ? '#f59e0b' : '#3b82f6');
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="display: inline-block; font-size: 0.75rem; font-weight: 800; color: #ffffff; background: <?= $priColor ?>; padding: 2px 7px; border-radius: 4px;">
                                            <?= $pri ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <strong style="color: #0f172a; font-size: 1rem;"><?= e($cand['acronym']) ?></strong>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #334155;">
                                        <?= e($cand['proposed_full_form_en'] ?? 'Statutory Examination') ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">
                                            <?= e($cand['category'] ?? 'civil_services') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.82rem; color: #64748b;">
                                        <?= e($cand['conducting_body'] ?? 'Statutory Board') ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; text-align: right;">
                                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                                            <!-- 1-Click Publish Form -->
                                            <form method="POST" style="margin: 0;" onsubmit="this.querySelector('button').innerText='Publishing...'; this.querySelector('button').disabled=true;">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="publish_candidate">
                                                <input type="hidden" name="acronym" value="<?= e($cand['acronym']) ?>">
                                                <input type="hidden" name="hint_full_form" value="<?= e($cand['proposed_full_form_en'] ?? '') ?>">
                                                <input type="hidden" name="category" value="<?= e($cand['category'] ?? '') ?>">
                                                <button type="submit" class="btn btn-sm btn-primary" style="font-size: 0.8rem; font-weight: 700; padding: 0.35rem 0.75rem;">
                                                    Publish Now &rarr;
                                                </button>
                                            </form>

                                            <!-- Dismiss Form -->
                                            <form method="POST" style="margin: 0;">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="dismiss_candidate">
                                                <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 0.35rem 0.6rem; color: #64748b;" onclick="return confirm('Dismiss this candidate?');" title="Dismiss">
                                                    &times;
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <!-- TAB 2: INSTANT GENERATOR -->
    <?php elseif ($activeTab === 'instant'): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; max-width: 680px; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">Instant AI Government Full Form Publisher</h2>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">
                    Type any government examination acronym, board or post name. The Gemini AI engine will fetch the official expansion, Hindi meaning, eligibility, selection stages, 7th CPC salary, and publish it live instantly.
                </p>
            </div>

            <form method="POST" onsubmit="document.getElementById('instantBtn').innerText='⏳ Fetching & Generating Facts...'; document.getElementById('instantBtn').disabled=true;">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="instant_generate">

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Target Acronym / Abbreviation *
                    </label>
                    <input type="text" name="custom_acronym" placeholder="e.g. UPPRPB, CEPTAM, NORCET, BSPHCL, REET" required style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; font-weight: 600; text-transform: uppercase;">
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Must be an official Indian Government, State PSC, Police, Defense, or Academic acronym.</div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Full Form Hint (Optional)
                    </label>
                    <input type="text" name="custom_hint" placeholder="e.g. Uttar Pradesh Police Recruitment and Promotion Board" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 1.75rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Category (Optional)
                    </label>
                    <select name="custom_category" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; background: #ffffff;">
                        <option value="">Auto-Detect from Conducting Body</option>
                        <option value="police">Police &amp; Law Enforcement</option>
                        <option value="civil_services">Civil Services &amp; State PSC</option>
                        <option value="defence">Defence &amp; Armed Forces</option>
                        <option value="banking">Banking &amp; Insurance</option>
                        <option value="railway">Railways &amp; Metro</option>
                        <option value="teaching">Teaching &amp; Academic Tests</option>
                        <option value="engineering">Engineering &amp; PSUs</option>
                        <option value="medical">Medical &amp; Healthcare</option>
                        <option value="entrance">National Entrance Exams</option>
                    </select>
                </div>

                <button type="submit" id="instantBtn" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    <span>Generate &amp; Publish Live &rarr;</span>
                </button>
            </form>
        </div>

    <!-- TAB 3: PUBLISHED DIRECTORY -->
    <?php elseif ($activeTab === 'published'): ?>
        <div class="admin-table-box">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem 0;">Published Lexicon Terms</h2>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 0;">Currently live on Sarkari.online with full detail &amp; salary matrix.</p>
                </div>

                <!-- Search Bar -->
                <form method="GET" action="<?= url('admin/glossary/') ?>" style="margin: 0; display: flex; gap: 6px;">
                    <input type="hidden" name="tab" value="published">
                    <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search acronym or full name..." style="padding: 0.45rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; width: 220px;">
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="<?= url('admin/glossary/?tab=published') ?>" class="btn btn-sm btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table" style="margin: 0; width: 100%; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.78rem; text-transform: uppercase;">
                            <th style="padding: 0.75rem 1rem;">Acronym</th>
                            <th style="padding: 0.75rem 1rem;">English Expansion</th>
                            <th style="padding: 0.75rem 1rem;">Hindi Expansion</th>
                            <th style="padding: 0.75rem 1rem;">Category</th>
                            <th style="padding: 0.75rem 1rem;">7th CPC Scale</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Live URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($publishedTerms)): ?>
                            <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #64748b;">No matching terms found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($publishedTerms as $term): 
                                $publicUrl = url("full-forms/{$term['slug']}/");
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.85rem 1rem;">
                                        <strong style="color: #1e3a8a; font-size: 0.95rem;"><?= e($term['acronym']) ?></strong>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;">
                                        <?= e($term['full_form_en']) ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.82rem;">
                                        <?= e($term['full_form_hi'] ?? '—') ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">
                                            <?= e($term['category']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8rem; color: #166534;">
                                        <?= !empty($term['pay_level_7cpc']) ? e($term['pay_level_7cpc']) : '<span style="color: #94a3b8;">Statutory Board</span>' ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; text-align: right;">
                                        <a href="<?= $publicUrl ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 0.3rem 0.65rem; color: #2563eb;">
                                            View Page &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
