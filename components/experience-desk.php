<?php
/**
 * Sarkari.online - Aspirant Experience & Verification Signal Component
 * 
 * Demonstrates E-E-A-T "Experience" through authentic ground-level candidate feedback
 * and rapid crowd-sourced official circular corrections.
 * Submissions land strictly in editorial review queue before any public display.
 */

use App\Database\Database;

$deskArticleId = (int)($article['id'] ?? 0);
if ($deskArticleId <= 0) {
    return;
}

// Fetch any editorially verified community notes for this article
$verifiedNotes = [];
try {
    $verifiedNotes = Database::fetchAll(
        "SELECT candidate_name, exam_center_city, reporting_time_observed, biometric_status, message, created_at 
         FROM reader_signals 
         WHERE article_id = :aid AND status = 'verified' 
         ORDER BY id DESC LIMIT 3",
        ['aid' => $deskArticleId]
    );
} catch (\Throwable $e) {
    // Gracefully ignore if table not initialized
}
?>

<section class="aspirant-experience-desk" style="margin: 2.5rem 0; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div class="desk-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: #ffffff; padding: 1.25rem 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #ffffff;">Aspirant Verification &amp; Experience Desk</h3>
            </div>
            <p style="margin: 0; font-size: 0.85rem; color: #cbd5e1;">Real-time candidate ground signals, center checks, and official circular reports.</p>
        </div>
        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.12); padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: #93c5fd; border: 1px solid rgba(255,255,255,0.15);">
            <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></span>
            Editorial Review Active
        </span>
    </div>

    <div class="desk-body" style="padding: 1.5rem;">

        <?php if (!empty($verifiedNotes)): ?>
            <!-- Verified Ground Notes Feed -->
            <div class="verified-signals-list" style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 700; margin: 0 0 0.75rem 0;">Verified Candidate Notes</h4>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($verifiedNotes as $note): ?>
                        <div style="background: #f8fafc; border-left: 3px solid #3b82f6; border-radius: 0 8px 8px 0; padding: 0.875rem 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <span style="font-size: 0.8125rem; font-weight: 700; color: #0f172a;">
                                    <?= e($note['candidate_name'] ?: 'Verified Aspirant') ?>
                                    <?php if (!empty($note['exam_center_city'])): ?>
                                        <span style="font-weight: 400; color: #64748b;">(<?= e($note['exam_center_city']) ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <span style="font-size: 0.75rem; color: #94a3b8;"><?= date('M j, Y', strtotime($note['created_at'])) ?></span>
                            </div>
                            <p style="margin: 0; font-size: 0.875rem; color: #334155; line-height: 1.5;"><?= nl2br(e($note['message'])) ?></p>
                            <?php if (!empty($note['biometric_status']) || !empty($note['reporting_time_observed'])): ?>
                                <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 8px; font-size: 0.75rem; color: #475569;">
                                    <?php if (!empty($note['biometric_status'])): ?>
                                        <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">Biometric: <?= e($note['biometric_status']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($note['reporting_time_observed'])): ?>
                                        <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">Gate Time: <?= e($note['reporting_time_observed']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signal Submission Form -->
        <div class="signal-submission-wrapper">
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                <button type="button" id="tabBtnCenter" onclick="switchDeskTab('center')" style="background: #eff6ff; color: #1e40af; border: none; padding: 0.5rem 0.875rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    📍 Center Experience
                </button>
                <button type="button" id="tabBtnCorrection" onclick="switchDeskTab('correction')" style="background: transparent; color: #64748b; border: none; padding: 0.5rem 0.875rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    📄 Report Circular / Update
                </button>
            </div>

            <form id="readerSignalForm" onsubmit="handleSignalSubmit(event)">
                <input type="hidden" name="article_id" value="<?= $deskArticleId ?>">
                <input type="hidden" name="signal_type" id="signalTypeInput" value="center_experience">
                
                <!-- Honeypot anti-spam (hidden from users) -->
                <input type="text" name="website_hp_guard" style="display: none !important;" tabindex="-1" autocomplete="off">

                <!-- Center Specific Fields -->
                <div id="centerSpecificFields" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Exam Center City</label>
                        <input type="text" name="exam_center_city" placeholder="e.g. Lucknow, Patna, Bhopal" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Entry Gate Closing Time</label>
                        <input type="text" name="reporting_time_observed" placeholder="e.g. Strict 8:30 AM close" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Biometric / ID Check</label>
                        <select name="biometric_status" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; background: #ffffff; box-sizing: border-box;">
                            <option value="Smooth Entry">Smooth Biometric Entry</option>
                            <option value="Aadhaar OTP Required">Original Aadhaar Mandatory</option>
                            <option value="Strict Dress Code Enforcement">Strict Dress Code Enforced</option>
                            <option value="Minor Delay at Gate">Biometric Delay Observed</option>
                        </select>
                    </div>
                </div>

                <!-- Correction Specific Fields -->
                <div id="correctionSpecificFields" style="display: none; margin-bottom: 0.75rem;">
                    <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Official Circular / Notice URL (.gov.in / .nic.in)</label>
                    <input type="url" name="source_circular_url" placeholder="https://ssc.gov.in/notice-link.pdf" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; box-sizing: border-box;">
                </div>

                <!-- Shared Fields -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Your Name / Handle (Optional)</label>
                        <input type="text" name="candidate_name" placeholder="Aspirant" style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; box-sizing: border-box;">
                    </div>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label style="display: block; font-size: 0.78125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Observation or Correction Details *</label>
                    <textarea name="message" id="signalMessageInput" rows="3" required placeholder="Describe what you observed at the center (prohibited items, bag storage, check-in flow) or detail the official update..." style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; line-height: 1.5; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
                    <span style="font-size: 0.75rem; color: #64748b;">
                        🔒 All submissions undergo editorial verification before being cited.
                    </span>
                    <button type="submit" id="signalSubmitBtn" style="background: #1e3a8a; color: #ffffff; border: none; padding: 0.55rem 1.25rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                        Submit Signal
                    </button>
                </div>

                <div id="signalFeedback" style="display: none; margin-top: 0.75rem; padding: 0.75rem; border-radius: 6px; font-size: 0.875rem;"></div>
            </form>
        </div>

    </div>
</section>

<script>
function switchDeskTab(tab) {
    const centerBtn = document.getElementById('tabBtnCenter');
    const corrBtn   = document.getElementById('tabBtnCorrection');
    const centerDiv = document.getElementById('centerSpecificFields');
    const corrDiv   = document.getElementById('correctionSpecificFields');
    const typeInput = document.getElementById('signalTypeInput');
    const msgInput  = document.getElementById('signalMessageInput');

    if (tab === 'center') {
        centerBtn.style.background = '#eff6ff';
        centerBtn.style.color = '#1e40af';
        corrBtn.style.background = 'transparent';
        corrBtn.style.color = '#64748b';
        centerDiv.style.display = 'grid';
        corrDiv.style.display = 'none';
        typeInput.value = 'center_experience';
        msgInput.placeholder = 'Describe what you observed at the center (prohibited items, bag storage, check-in flow)...';
    } else {
        corrBtn.style.background = '#eff6ff';
        corrBtn.style.color = '#1e40af';
        centerBtn.style.background = 'transparent';
        centerBtn.style.color = '#64748b';
        centerDiv.style.display = 'none';
        corrDiv.style.display = 'block';
        typeInput.value = 'correction';
        msgInput.placeholder = 'Detail the discrepancy or updated milestone (include paragraph/table reference)...';
    }
}

async function handleSignalSubmit(event) {
    event.preventDefault();
    const form = document.getElementById('readerSignalForm');
    const btn = document.getElementById('signalSubmitBtn');
    const feedback = document.getElementById('signalFeedback');

    btn.disabled = true;
    btn.textContent = 'Submitting...';
    feedback.style.display = 'none';

    try {
        const formData = new FormData(form);
        const res = await fetch('<?= url('api/submit-signal.php') ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        feedback.style.display = 'block';
        if (data.success) {
            feedback.style.background = '#f0fdf4';
            feedback.style.color = '#166534';
            feedback.style.border = '1px solid #bbf7d0';
            feedback.textContent = data.message;
            form.reset();
        } else {
            feedback.style.background = '#fef2f2';
            feedback.style.color = '#991b1b';
            feedback.style.border = '1px solid #fecaca';
            feedback.textContent = data.message || 'Submission failed. Please try again.';
        }
    } catch (err) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = 'Unable to connect to submission server. Please try again later.';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Signal';
    }
}
</script>
