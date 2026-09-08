<?php
/**
 * Sarkari.online - Candidate Center Reports & Circular Desk
 * 
 * Clean, high-density 50/50 responsive layout with pure SVG icons.
 * Zero emojis, authentic human UI, and robust anti-spam security.
 */

use App\Database\Database;

$deskArticleId = (int)($article['id'] ?? 0);
if ($deskArticleId <= 0) {
    return;
}

$formTime = time();
$formToken = hash('sha256', $formTime . '_salt_sarkari_signal_security');

$verifiedNotes = [];
try {
    $verifiedNotes = Database::fetchAll(
        "SELECT candidate_name, exam_center_city, reporting_time_observed, biometric_status, message, created_at 
         FROM reader_signals 
         WHERE article_id = :aid AND status = 'verified' 
         ORDER BY id DESC LIMIT 4",
        ['aid' => $deskArticleId]
    );
} catch (\Throwable $e) {
    // Graceful fallback
}
?>

<section class="aspirant-experience-desk" style="margin: 2.25rem 0; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <!-- Header -->
    <div style="background: #0f172a; color: #ffffff; padding: 1rem 1.25rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div>
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #ffffff; letter-spacing: -0.01em;">
                    Candidate Center Reports &amp; Circular Desk
                </h3>
                <p style="margin: 2px 0 0 0; font-size: 0.8125rem; color: #94a3b8;">
                    Contribute ground-level exam center feedback or report an official notification update.
                </p>
            </div>
        </div>
        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #38bdf8;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            Moderated Feed
        </span>
    </div>

    <div style="padding: 1.25rem;">

        <?php if (!empty($verifiedNotes)): ?>
            <!-- Verified Community Notes Feed -->
            <div style="margin-bottom: 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                <div style="font-size: 0.8125rem; color: #0f172a; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.65rem; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Verified Candidate Notes
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <?php foreach ($verifiedNotes as $note): ?>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 3px solid #2563eb; border-radius: 4px; padding: 0.65rem 0.85rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; font-size: 0.8125rem;">
                                <span style="font-weight: 700; color: #0f172a;">
                                    <?= e($note['candidate_name'] ?: 'Verified Candidate') ?>
                                    <?php if (!empty($note['exam_center_city'])): ?>
                                        <span style="font-weight: 500; color: #64748b;">(<?= e($note['exam_center_city']) ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <span style="font-size: 0.725rem; color: #94a3b8;"><?= date('d M Y', strtotime($note['created_at'])) ?></span>
                            </div>
                            <p style="margin: 0; font-size: 0.85rem; color: #334155; line-height: 1.45;"><?= nl2br(e($note['message'])) ?></p>
                            <?php if (!empty($note['biometric_status']) || !empty($note['reporting_time_observed'])): ?>
                                <div style="margin-top: 0.4rem; display: flex; flex-wrap: wrap; gap: 5px; font-size: 0.725rem;">
                                    <?php if (!empty($note['reporting_time_observed'])): ?>
                                        <span style="background: #f1f5f9; color: #475569; padding: 1px 6px; border-radius: 3px;">Gate: <?= e($note['reporting_time_observed']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($note['biometric_status'])): ?>
                                        <span style="background: #eff6ff; color: #1e40af; padding: 1px 6px; border-radius: 3px;"><?= e($note['biometric_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Clean Segmented Mode Selector -->
        <div style="display: inline-flex; background: #f1f5f9; padding: 3px; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #e2e8f0;">
            <button type="button" id="tabBtnCenter" onclick="switchDeskTab('center')" style="background: #ffffff; color: #0f172a; border: none; padding: 0.45rem 0.85rem; border-radius: 4px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Center Observations
            </button>
            <button type="button" id="tabBtnCorrection" onclick="switchDeskTab('correction')" style="background: transparent; color: #64748b; border: none; padding: 0.45rem 0.85rem; border-radius: 4px; font-size: 0.8125rem; font-weight: 500; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Official Notice Update
            </button>
        </div>

        <form id="readerSignalForm" onsubmit="handleSignalSubmit(event)">
            <input type="hidden" name="article_id" value="<?= $deskArticleId ?>">
            <input type="hidden" name="signal_type" id="signalTypeInput" value="center_experience">
            <input type="hidden" name="form_time" value="<?= $formTime ?>">
            <input type="hidden" name="form_token" value="<?= $formToken ?>">
            
            <!-- Honeypot anti-spam -->
            <input type="text" name="website_hp_guard" style="display: none !important;" tabindex="-1" autocomplete="off">
            <input type="email" name="user_email_hp" style="display: none !important;" tabindex="-1" autocomplete="off">

            <!-- 50% / 50% Center Grid Fields -->
            <div id="centerSpecificFields" class="desk-grid-row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Exam City / Center
                    </label>
                    <input type="text" name="exam_center_city" placeholder="e.g. Lucknow, TCS iON Patna" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; outline: none; transition: border-color 0.15s;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Gate Closing Observed
                    </label>
                    <input type="text" name="reporting_time_observed" placeholder="e.g. Strict 8:30 AM close" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; outline: none; transition: border-color 0.15s;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Document &amp; Biometric Protocol
                    </label>
                    <select name="biometric_status" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: #ffffff; box-sizing: border-box; outline: none;">
                        <option value="Smooth Entry">Standard Entry (No unexpected delays)</option>
                        <option value="Original Aadhaar Mandatory">Original Aadhaar Mandatory (Photocopy barred)</option>
                        <option value="Bags & Mobile Locker Available">Paid Locker / Bag Storage Available</option>
                        <option value="Strict Checking / Shoes Outside">Strict Dress Code / Shoes Inspected</option>
                        <option value="Biometric Delay Observed">Biometric Queue Delay Observed</option>
                        <option value="Other">Other (Specify below)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Your Name <span style="font-weight: 400; color: #64748b;">(Optional)</span>
                    </label>
                    <input type="text" name="candidate_name" placeholder="Anonymous or Candidate name" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; outline: none;">
                </div>
            </div>

            <!-- 50% / 50% Notice/Correction Grid Fields -->
            <div id="correctionSpecificFields" class="desk-grid-row" style="display: none; grid-template-columns: repeat(2, 1fr); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Official Notice Link (.gov.in / .nic.in)
                    </label>
                    <input type="url" name="source_circular_url" placeholder="https://ssc.gov.in/notice.pdf" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                        Your Name <span style="font-weight: 400; color: #64748b;">(Optional)</span>
                    </label>
                    <input type="text" name="candidate_name_alt" oninput="document.querySelector('[name=candidate_name]').value=this.value" placeholder="Anonymous or Candidate name" style="width: 100%; height: 38px; padding: 0.45rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; outline: none;">
                </div>
            </div>

            <!-- Compact Notes Textarea -->
            <div style="margin-bottom: 0.85rem;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.3rem;">
                    Observations or Notice Notes <span style="color: #dc2626;">*</span>
                </label>
                <textarea name="message" id="signalMessageInput" rows="2" required placeholder="Brief note on entry verification, rough sheet availability, locker fee, or circular revision..." style="width: 100%; min-height: 56px; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; line-height: 1.45; font-family: inherit; box-sizing: border-box; outline: none; resize: vertical;"></textarea>
            </div>

            <!-- Submit Strip -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
                <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; color: #64748b;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span>All submissions are reviewed by the editorial desk before publishing.</span>
                </div>
                <button type="submit" id="signalSubmitBtn" style="background: #1e3a8a; color: #ffffff; border: none; padding: 0.5rem 1.15rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: background 0.15s; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Submit Report
                </button>
            </div>

            <div id="signalFeedback" style="display: none; margin-top: 0.75rem; padding: 0.75rem 0.85rem; border-radius: 6px; font-size: 0.8125rem; line-height: 1.45;"></div>
        </form>

    </div>
</section>

<style>
@media (max-width: 620px) {
    .desk-grid-row {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
function switchDeskTab(tab) {
    const centerBtn = document.getElementById('tabBtnCenter');
    const corrBtn   = document.getElementById('tabBtnCorrection');
    const centerDiv = document.getElementById('centerSpecificFields');
    const corrDiv   = document.getElementById('correctionSpecificFields');
    const typeInput = document.getElementById('signalTypeInput');
    const msgInput  = document.getElementById('signalMessageInput');

    if (tab === 'center') {
        centerBtn.style.background = '#ffffff';
        centerBtn.style.color = '#0f172a';
        centerBtn.style.fontWeight = '600';
        centerBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
        corrBtn.style.background = 'transparent';
        corrBtn.style.color = '#64748b';
        corrBtn.style.fontWeight = '500';
        corrBtn.style.boxShadow = 'none';
        centerDiv.style.display = 'grid';
        corrDiv.style.display = 'none';
        typeInput.value = 'center_experience';
        msgInput.placeholder = 'Brief note on entry verification, rough sheet availability, locker fee...';
    } else {
        corrBtn.style.background = '#ffffff';
        corrBtn.style.color = '#0f172a';
        corrBtn.style.fontWeight = '600';
        corrBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
        centerBtn.style.background = 'transparent';
        centerBtn.style.color = '#64748b';
        centerBtn.style.fontWeight = '500';
        centerBtn.style.boxShadow = 'none';
        centerDiv.style.display = 'none';
        corrDiv.style.display = 'grid';
        typeInput.value = 'correction';
        msgInput.placeholder = 'Summary of circular update, revised date/milestone, or official notice details...';
    }
}

async function handleSignalSubmit(event) {
    event.preventDefault();
    const form = document.getElementById('readerSignalForm');
    const btn = document.getElementById('signalSubmitBtn');
    const feedback = document.getElementById('signalFeedback');

    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block; animation: spin 1s linear infinite;">⟳</span> Submitting...';
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
            feedback.textContent = data.message || 'Submission failed. Please check your entries.';
        }
    } catch (err) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = 'Server connection error. Please try again later.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Submit Report';
    }
}
</script>
