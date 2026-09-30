<?php
/**
 * Sarkari.online - Admin Edit Full Form / Lexicon Term
 * Full CMS editing for official abbreviation, bilingual expansion, eligibility,
 * selection scheme, and 7th CPC salary matrices, with 1-click AI re-generation.
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Auth;
use App\Helpers\CSRF;
use App\Helpers\Sanitizer;
use App\Services\GlossaryPipelineService;
use App\Services\GlossaryService;

Auth::requireAuth();

$adminPageTitle = 'Edit Full Form';
$adminPageKey = 'glossary';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . url('admin/glossary/?tab=published'));
    exit;
}

$term = Database::fetchOne("SELECT * FROM glossary_terms WHERE id = :id LIMIT 1", ['id' => $id]);
if (!$term) {
    header("Location: " . url('admin/glossary/?tab=published'));
    exit;
}

$facts = null;
try {
    $facts = Database::fetchOne("SELECT * FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $id]);
} catch (\Throwable $e) {}

$message = null;
$messageType = 'success';
$pipeline = new GlossaryPipelineService();

// Handle Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::verify($_POST['csrf_token'] ?? '')) {
        $message = "Invalid CSRF security token.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? 'save_manual';

        // 1. AI Re-Generation (Overwrite)
        if ($action === 'regenerate_ai') {
            @set_time_limit(180);
            $res = $pipeline->generateAndPublish($term['acronym'], $term['full_form_en'], $term['category'], true);
            if (!empty($res['success'])) {
                $term = Database::fetchOne("SELECT * FROM glossary_terms WHERE id = :id LIMIT 1", ['id' => $id]);
                $facts = Database::fetchOne("SELECT * FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $id]);
                $message = "⚡ Successfully re-generated and updated <strong>" . e($term['acronym']) . "</strong> with fresh AI facts!";
                $messageType = 'success';
            } else {
                $message = "❌ AI Re-generation failed: " . ($res['error'] ?? 'Unknown error.');
                $messageType = 'danger';
            }
        }

        // 2. Manual Save & Update
        elseif ($action === 'save_manual') {
            $updated = $pipeline->updateTermManually($id, $_POST, $_POST);
            if ($updated) {
                $term = Database::fetchOne("SELECT * FROM glossary_terms WHERE id = :id LIMIT 1", ['id' => $id]);
                $facts = Database::fetchOne("SELECT * FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $id]);
                $message = "🎉 Term <strong>" . e($term['acronym']) . "</strong> updated successfully!";
                $messageType = 'success';
            } else {
                $message = "❌ Failed to update term in database.";
                $messageType = 'danger';
            }
        }
    }
}

$liveUrl = url("full-forms/{$term['slug']}/");

include dirname(__DIR__) . '/components/header.php';
?>

<div class="admin-content-body">

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.95rem;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Header Navigation -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.75rem; background: #ffffff; padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
        <div>
            <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem;">
                <a href="<?= url('admin/glossary/?tab=published') ?>" style="color: #2563eb; text-decoration: none;">&larr; Back to Published Glossary</a>
            </div>
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>Edit Full Form: <?= e($term['acronym']) ?></span>
                <span style="font-size: 0.75rem; font-weight: 700; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 4px;">LIVE</span>
            </h1>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="<?= $liveUrl ?>" target="_blank" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                View Live Page &rarr;
            </a>
        </div>
    </div>

    <!-- Main Editor Form -->
    <form method="POST" id="editGlossaryForm" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" id="formAction" value="save_manual">

        <!-- Left Column: Core Content -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Basic Identification Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.65rem;">
                    1. Identity &amp; Expansions
                </h2>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Acronym / Short Form *
                        </label>
                        <input type="text" name="acronym" value="<?= e($term['acronym']) ?>" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-weight: 700; text-transform: uppercase;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Full Form in English *
                        </label>
                        <input type="text" name="full_form_en" value="<?= e($term['full_form_en']) ?>" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-weight: 600;">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Hindi Translation / Full Form (हिंदी में)
                    </label>
                    <input type="text" name="full_form_hi" value="<?= e($term['full_form_hi'] ?? '') ?>" placeholder="उदा. संघ लोक सेवा आयोग" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Conducting Body / Authority
                        </label>
                        <input type="text" name="conducting_body" value="<?= e($term['conducting_body'] ?? '') ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Official Portal URL
                        </label>
                        <input type="url" name="official_portal" value="<?= e($term['official_portal'] ?? '') ?>" placeholder="https://..." style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>
            </div>

            <!-- Detailed Content Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.65rem;">
                    2. Mandate, Eligibility &amp; Selection Scheme
                </h2>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Official Mandate &amp; Overview (Section 1) *
                    </label>
                    <textarea name="overview" rows="4" style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; line-height: 1.6;"><?= e($term['overview'] ?? '') ?></textarea>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Eligibility Criteria &amp; Age Limit (Section 2)
                    </label>
                    <textarea name="eligibility_criteria" rows="4" placeholder="Citizenship: ... Education: ... Age Limit: ..." style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; line-height: 1.6;"><?= e($term['eligibility_criteria'] ?? '') ?></textarea>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Examination Scheme &amp; Selection Process (Section 3)
                    </label>
                    <textarea name="selection_process" rows="4" placeholder="Stage 1: ... Stage 2: ... Stage 3: ..." style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; line-height: 1.6;"><?= e($term['selection_process'] ?? '') ?></textarea>
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Core Syllabus Snapshot (Section 4)
                    </label>
                    <textarea name="syllabus_snapshot" rows="3" placeholder="General Studies, Numerical Aptitude, Reasoning..." style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; line-height: 1.6;"><?= e($term['syllabus_snapshot'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Salary & 7th CPC Matrix Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.65rem;">
                    3. 7th CPC Salary, Pay Scale &amp; Career Growth
                </h2>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Pay Level (7th CPC)
                    </label>
                    <input type="text" name="pay_level_7cpc" value="<?= e($facts['pay_level_7cpc'] ?? '') ?>" placeholder="e.g. Level 7 (₹44,900 - ₹1,42,400)" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Basic Pay Min (₹)
                        </label>
                        <input type="number" name="basic_pay_min" value="<?= e($facts['basic_pay_min'] ?? '') ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Basic Pay Max (₹)
                        </label>
                        <input type="number" name="basic_pay_max" value="<?= e($facts['basic_pay_max'] ?? '') ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Gross Monthly Salary Min (₹)
                        </label>
                        <input type="number" name="gross_salary_min" value="<?= e($facts['gross_salary_min'] ?? '') ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Gross Monthly Salary Max (₹)
                        </label>
                        <input type="number" name="gross_salary_max" value="<?= e($facts['gross_salary_max'] ?? '') ?>" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Allowances Summary
                    </label>
                    <input type="text" name="allowances_summary" value="<?= e($facts['allowances_summary'] ?? '') ?>" placeholder="DA (50%), HRA (9-27%), Transport Allowance" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Career Growth &amp; Promotion Hierarchy
                    </label>
                    <textarea name="career_growth_summary" rows="3" placeholder="Junior Executive -> Senior Executive -> Assistant Director..." style="width: 100%; padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;"><?= e($facts['career_growth_summary'] ?? '') ?></textarea>
                </div>
            </div>

        </div>

        <!-- Right Column: Meta & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Actions Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">Publish &amp; Save</h3>

                <button type="button" onclick="submitForm('save_manual', this)" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Save Changes Now</span>
                </button>

                <button type="button" onclick="confirmRegenerate(this)" class="btn btn-outline" style="width: 100%; padding: 0.7rem; font-size: 0.85rem; font-weight: 700; color: #d97706; border-color: #f59e0b; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>⚡ Re-Generate with AI</span>
                </button>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.35rem; text-align: center;">Overwrites all fields with newly fetched AI facts.</div>

                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; font-size: 0.8rem; color: #64748b;">
                    <div>Last Reviewed: <strong><?= e($term['last_reviewed_at'] ?? '—') ?></strong></div>
                    <div style="margin-top: 0.25rem;">Last Updated: <strong><?= e($term['updated_at'] ?? '—') ?></strong></div>
                </div>
            </div>

            <!-- Category & Taxonomy Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">Category &amp; Taxonomy</h3>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Category *
                    </label>
                    <select name="category" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; background: #ffffff;">
                        <?php
                        $cats = [
                            'civil_services' => 'Civil Services & State PSC',
                            'defence' => 'Defence & Armed Forces',
                            'banking' => 'Banking & Insurance',
                            'railway' => 'Railways & Metro',
                            'police' => 'Police & Security Forces',
                            'teaching' => 'Teaching & Academic',
                            'engineering' => 'Engineering & PSUs',
                            'medical' => 'Medical & Healthcare',
                            'entrance' => 'National Entrance Exams'
                        ];
                        foreach ($cats as $cKey => $cLabel):
                        ?>
                            <option value="<?= $cKey ?>" <?= $term['category'] === $cKey ? 'selected' : '' ?>><?= $cLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                        Related Article Slug (Internal Link)
                    </label>
                    <input type="text" name="related_article_slug" value="<?= e($term['related_article_slug'] ?? '') ?>" placeholder="e.g. upsc-cse-2026-admit-card" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem;">
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;">Links this full form directly to latest recruitment article.</div>
                </div>
            </div>

        </div>
    </form>

</div>

<script>
function submitForm(action, btn) {
    document.getElementById('formAction').value = action;
    btn.disabled = true;
    btn.innerText = 'Saving...';
    document.getElementById('editGlossaryForm').submit();
}

function confirmRegenerate(btn) {
    if (confirm("Are you sure you want to Re-Generate this full form using Gemini AI? This will overwrite the overview, eligibility, selection process, and salary matrix.")) {
        document.getElementById('formAction').value = 'regenerate_ai';
        btn.disabled = true;
        btn.innerText = '⏳ Re-Generating with AI...';
        document.getElementById('editGlossaryForm').submit();
    }
}
</script>

<?php include dirname(__DIR__) . '/components/footer.php'; ?>
