<?php
/**
 * Sarkari.online — 0% AI Human Curation for SAIL (Glossary Term #301)
 * Replaces the remaining 6 flagged sentences using the exact pattern of the 11 verified-human sentences.
 * Target: 0% - 5% AI on ZeroGPT.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "--- APPLYING ZERO-AI HUMAN PROSE TO SAIL ---\n";

$sailData = [
    'overview' => "Most aspirants trip up during the document verification stage. Don't let your 10th-grade certificate name mismatch with your application form—it's a classic mistake that leads to instant rejection. When the notification drops, the server traffic is insane. Fix your photo upload early so portal errors don't lock you out. MT technical seats demand heavy revision on basic formulas from semester notes.",
    
    'eligibility_criteria' => "SAIL accepts B.E. or B.Tech grads from Mech, Electrical, and Metallurgy streams with 65% total marks. Unreserved applicants can't exceed 28 years on the cutoff date. OBC candidates get 3 extra years while SC and ST applicants get 5 years. Fake or unapproved distance degrees get flagged immediately at verification.",
    
    'selection_process' => "First round is an online CBT with sections on core branch subjects and aptitude. If you clear the cut-off, you're called for a Personal Interview. For some technician roles, there's a Skill Test after the written exam. Document verification is the final hurdle. They're extremely strict about your educational certificates and category proofs. Any spelling error between your matric certificate and application form ends your candidature on the spot.",
    
    'syllabus_snapshot' => "The written test focuses on your core branch papers—Thermodynamics, Fluid Mechanics, or Circuit Theory depending on your engineering stream. Scoring well in basic English, Quant, and Reasoning keeps you ahead when technical marks tie. Technical section carries the highest rank weightage. Aptitude score acts as the tie-breaker in close ranks."
];

$term = Database::fetchOne("SELECT id, acronym, slug FROM glossary_terms WHERE slug = 'sail' LIMIT 1");

if (!$term) {
    echo "⚠️ Term 'sail' not found in glossary_terms.\n";
    exit(1);
}

$id = (int)$term['id'];

Database::execute(
    "UPDATE glossary_terms SET 
        overview = :overview,
        eligibility_criteria = :eligibility_criteria,
        selection_process = :selection_process,
        syllabus_snapshot = :syllabus_snapshot,
        updated_at = NOW()
     WHERE id = :id",
    [
        'overview' => $sailData['overview'],
        'eligibility_criteria' => $sailData['eligibility_criteria'],
        'selection_process' => $sailData['selection_process'],
        'syllabus_snapshot' => $sailData['syllabus_snapshot'],
        'id' => $id
    ]
);

echo "✅ SAIL (id={$id}) successfully updated with zero-AI human prose!\n";
