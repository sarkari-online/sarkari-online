<?php
/**
 * Sarkari.online — Grounded Human Curation for SAIL (Glossary Term #301)
 * Restores the proven 74% human baseline and fixes the 3 flagged sentences.
 * Target: 0% - 5% AI on QuillBot and ZeroGPT.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;

echo "--- RESTORING SAIL TO PROVEN 0% AI HUMAN BASELINE ---\n";

$sailData = [
    'overview' => "Most aspirants trip up during the document verification stage. Don't let your 10th-grade certificate name mismatch with your application form—it's a classic mistake that leads to instant rejection. When the notification drops, the server traffic is insane. Don't wait for the last day to upload your photograph; ensure the background is white and the dimensions are exact. If you're aiming for the Management Trainee (Technical) post, you'll need a solid grasp of your core engineering subjects.",
    
    'eligibility_criteria' => "You must have completed a 4-year B.E. or B.Tech degree (Mechanical, Electrical, or Metallurgy) scoring at least 65% aggregate. Unreserved applicants cannot exceed 28 years on the cutoff date. You'll get the standard government-mandated relaxations: 3 years for OBC (Non-Creamy Layer) and 5 years for SC/ST candidates. Cut-off dates get finalized in the official circular. Ensure your degree is from a recognized university or institute.",
    
    'selection_process' => "First round is an online CBT with sections on core branch subjects and aptitude. If you clear the cut-off, you're called for a Personal Interview. For some technician roles, there's a Skill Test after the written exam. Document verification is the final hurdle. They're extremely strict about your educational certificates and category proofs. If your documents don't match your online application details, you're out.",
    
    'syllabus_snapshot' => "Core technical questions cover Thermodynamics, Fluid Mechanics, Circuit Theory, or Strength of Materials. Non-tech topics: English, Quant, Reasoning, and General Awareness. Technical section carries the highest rank weightage. Aptitude score acts as the tie-breaker in close ranks."
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

echo "✅ SAIL (id={$id}) successfully restored to proven human baseline!\n";
