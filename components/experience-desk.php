<?php
/**
 * Sarkari.online - Candidate Help & Center Update Desk
 * 
 * Easy, intuitive, and candidate-friendly community verification module.
 * Fully secured with double-honeypot, time-lock defense, and editorial moderation queue.
 */

use App\Database\Database;

$deskArticleId = (int)($article['id'] ?? 0);
if ($deskArticleId <= 0) {
    return;
}

// Generate time-lock token for anti-bot defense
$formTime = time();
$formToken = hash('sha256', $formTime . '_salt_sarkari_signal_security');

// Fetch any editorially approved student notes for this article
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

<section class="aspirant-experience-desk" style="margin: 2.5rem 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
    <!-- Friendly Header -->
    <div class="desk-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); color: #ffffff; padding: 1.25rem 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(255,255,255,0.15); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    💬
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #ffffff; letter-spacing: -0.01em;">
                        Candidate Help &amp; Exam Center Desk
                    </h3>
                    <p style="margin: 3px 0 0 0; font-size: 0.85rem; color: #93c5fd;">
                        Exam diya ya center gaye the? Apne fellow aspirants ki help ke liye ground experience share karein.
                    </p>
                </div>
            </div>
            <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; color: #34d399;">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                Editorial Verified Space
            </span>
        </div>
    </div>

    <div class="desk-body" style="padding: 1.5rem;">

        <?php if (!empty($verifiedNotes)): ?>
            <!-- Verified Community Notes Feed -->
            <div class="verified-signals-list" style="margin-bottom: 1.5rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1.25rem;">
                <h4 style="font-size: 0.9rem; color: #166534; font-weight: 800; margin: 0 0 0.85rem 0; display: flex; align-items: center; gap: 6px;">
                    <span>✅</span> Verified Aspirant Ground Notes:
                </h4>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($verifiedNotes as $note): ?>
                        <div style="background: #ffffff; border: 1px solid #dcfce7; border-left: 4px solid #16a34a; border-radius: 6px; padding: 0.85rem 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                <span style="font-size: 0.85rem; font-weight: 700; color: #0f172a;">
                                    <?= e($note['candidate_name'] ?: 'Verified Student') ?>
                                    <?php if (!empty($note['exam_center_city'])): ?>
                                        <span style="font-weight: 600; color: #2563eb;">&bull; <?= e($note['exam_center_city']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span style="font-size: 0.75rem; color: #64748b;"><?= date('d M Y', strtotime($note['created_at'])) ?></span>
                            </div>
                            <p style="margin: 0; font-size: 0.875rem; color: #334155; line-height: 1.5;"><?= nl2br(e($note['message'])) ?></p>
                            <?php if (!empty($note['biometric_status']) || !empty($note['reporting_time_observed'])): ?>
                                <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 6px; font-size: 0.75rem;">
                                    <?php if (!empty($note['reporting_time_observed'])): ?>
                                        <span style="background: #f1f5f9; color: #334155; padding: 2px 7px; border-radius: 4px; font-weight: 600;">Gate: <?= e($note['reporting_time_observed']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($note['biometric_status'])): ?>
                                        <span style="background: #e0f2fe; color: #0369a1; padding: 2px 7px; border-radius: 4px; font-weight: 600;"><?= e($note['biometric_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Simple 2-Option Selector -->
        <div style="margin-bottom: 1.25rem;">
            <div style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">
                Aap kya share karna chahte hain?
            </div>
            <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                <button type="button" id="tabBtnCenter" onclick="switchDeskTab('center')" style="background: #eff6ff; color: #1e40af; border: 1.5px solid #bfdbfe; padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;">
                    <span>🏫</span> Exam Center Ka Mahoul (Experience)
                </button>
                <button type="button" id="tabBtnCorrection" onclick="switchDeskTab('correction')" style="background: #f8fafc; color: #64748b; border: 1.5px solid #e2e8f0; padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;">
                    <span>📄</span> Naya Notice / Date Update Report Karein
                </button>
            </div>
        </div>

        <form id="readerSignalForm" onsubmit="handleSignalSubmit(event)">
            <input type="hidden" name="article_id" value="<?= $deskArticleId ?>">
            <input type="hidden" name="signal_type" id="signalTypeInput" value="center_experience">
            <input type="hidden" name="form_time" value="<?= $formTime ?>">
            <input type="hidden" name="form_token" value="<?= $formToken ?>">
            
            <!-- Double Anti-Bot Honeypot (Hidden from human users) -->
            <input type="text" name="website_hp_guard" style="display: none !important;" tabindex="-1" autocomplete="off">
            <input type="email" name="user_email_hp" style="display: none !important;" tabindex="-1" autocomplete="off">

            <!-- Center Specific Fields -->
            <div id="centerSpecificFields" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                        Exam City / Center Name
                    </label>
                    <input type="text" name="exam_center_city" placeholder="Jaise: Lucknow, Patna, TCS iON Noida..." style="width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; box-sizing: border-box; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                        Gate Closing Time
                    </label>
                    <input type="text" name="reporting_time_observed" placeholder="Jaise: Strict 8:30 AM gate band hua..." style="width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; box-sizing: border-box; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                        Document &amp; Biometric Checking
                    </label>
                    <select name="biometric_status" style="width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; background: #ffffff; box-sizing: border-box; outline: none;">
                        <option value="Smooth Entry">Normal Checking (Smooth Entry)</option>
                        <option value="Original Aadhaar Mandatory">Original Aadhaar Card Zaroori Tha</option>
                        <option value="Bags & Mobile Locker Available">Bags / Phone Jama Karne Ki Suvidha Thi</option>
                        <option value="Strict Checking / Shoes Outside">Strict Checking (Shoes/Belt Bahar Rakhwaye)</option>
                        <option value="Biometric Delay Observed">Biometric Machine Me Time Laga</option>
                        <option value="Other">Other (Niche Details Me Likhein)</option>
                    </select>
                </div>
            </div>

            <!-- Correction Specific Fields -->
            <div id="correctionSpecificFields" style="display: none; margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                    Official Notice Link (.gov.in / .nic.in / Official Board Link)
                </label>
                <input type="url" name="source_circular_url" placeholder="https://ssc.gov.in/notice-link.pdf" style="width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; box-sizing: border-box; outline: none;">
                <small style="color: #64748b; font-size: 0.75rem; display: block; margin-top: 3px;">Sirf official portal ka link paste karein (Social media ya YouTube links allow nahi hain).</small>
            </div>

            <!-- Optional Name Field -->
            <div style="margin-bottom: 1rem; max-width: 320px;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                    Aapka Naam <span style="font-weight: 400; color: #64748b;">(Optional - Chaahein toh khali chhod dein)</span>
                </label>
                <input type="text" name="candidate_name" placeholder="Aspirant ya apna naam" style="width: 100%; padding: 0.6rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; box-sizing: border-box; outline: none;">
            </div>

            <!-- Details Field -->
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">
                    Aapka Anubhav ya Notice Ki Details <span style="color: #ef4444;">*</span>
                </label>
                <textarea name="message" id="signalMessageInput" rows="3" required placeholder="Bataiye center par checking kaisi thi, pen le jana tha ya andar mila, bag locker charges the, ya notice me kya badlaav hua hai..." style="width: 100%; padding: 0.65rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; line-height: 1.5; font-family: inherit; box-sizing: border-box; outline: none;"></textarea>
            </div>

            <!-- Submit Strip -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.78125rem; color: #64748b;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span>Spam-Free Space: Sabhi updates pehle verify kiye jaate hain.</span>
                </div>
                <button type="submit" id="signalSubmitBtn" style="background: #1e40af; color: #ffffff; border: none; padding: 0.65rem 1.4rem; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; transition: background 0.2s; box-shadow: 0 1px 3px rgba(30,64,175,0.3);">
                    🚀 Post Update / Share
                </button>
            </div>

            <div id="signalFeedback" style="display: none; margin-top: 1rem; padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.875rem; line-height: 1.5;"></div>
        </form>

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
        centerBtn.style.borderColor = '#bfdbfe';
        corrBtn.style.background = '#f8fafc';
        corrBtn.style.color = '#64748b';
        corrBtn.style.borderColor = '#e2e8f0';
        centerDiv.style.display = 'grid';
        corrDiv.style.display = 'none';
        typeInput.value = 'center_experience';
        msgInput.placeholder = 'Bataiye center par checking kaisi thi, pen le jana tha ya andar mila, bag locker charges the...';
    } else {
        corrBtn.style.background = '#eff6ff';
        corrBtn.style.color = '#1e40af';
        corrBtn.style.borderColor = '#bfdbfe';
        centerBtn.style.background = '#f8fafc';
        centerBtn.style.color = '#64748b';
        centerBtn.style.borderColor = '#e2e8f0';
        centerDiv.style.display = 'none';
        corrDiv.style.display = 'block';
        typeInput.value = 'correction';
        msgInput.placeholder = 'Detail karein ki kya date ya schedule update hua hai, aur official notification ka reference dein...';
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
            feedback.style.border = '1.5px solid #86efac';
            feedback.innerHTML = '<strong>Dhanyawad!</strong> ' + data.message;
            form.reset();
        } else {
            feedback.style.background = '#fef2f2';
            feedback.style.color = '#991b1b';
            feedback.style.border = '1.5px solid #fecaca';
            feedback.textContent = data.message || 'Submission failed. Kripya details check karke dobara try karein.';
        }
    } catch (err) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1.5px solid #fecaca';
        feedback.textContent = 'Server connect nahi ho paya. Kripya thodi der baad dobara prayas karein.';
    } finally {
        btn.disabled = false;
        btn.textContent = '🚀 Post Update / Share';
    }
}
</script>
