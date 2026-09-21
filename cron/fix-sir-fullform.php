<?php
/**
 * Sarkari.online - Fix SIR Full Form (Special Intensive Revision)
 * Updates glossary_terms & full_form_entity_facts with authoritative ECI/CEO Delhi definition.
 * 
 * Official Source: https://ceodelhi.gov.in/SIR2026.aspx
 */

if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

require_once dirname(__DIR__) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "--- UPDATING SIR FULL FORM (Special Intensive Revision) ---\n";

$sirData = [
    'acronym' => 'SIR',
    'slug' => 'sir',
    'letter' => 'S',
    'full_form_en' => 'Special Intensive Revision',
    'full_form_hi' => 'विशेष गहन पुनरीक्षण',
    'category' => 'civil_services',
    'conducting_body' => 'Election Commission of India (ECI) / Chief Electoral Officer (CEO) Delhi',
    'official_portal' => 'https://ceodelhi.gov.in/SIR2026.aspx',
    'overview' => "It is an official voter list verification and electoral roll revision exercise conducted by the Election Commission of India and Chief Electoral Officers. Special Intensive Revision (SIR)—officially termed विशेष गहन पुनरीक्षण in Hindi—operates under statutory authority (such as CEO Delhi at ceodelhi.gov.in/SIR2026.aspx). Through intensive house-to-house enumeration by Booth Level Officers (BLOs), SIR authenticates existing electors, enrolls first-time voters turning 18, collects enumeration forms, resolves claims and objections (Forms 6, 7, and 8), and eliminates duplicate or shifted entries before legislative assembly and parliamentary elections.",
    'eligibility_criteria' => "Citizenship: Indian citizen residing within the assembly constituency. Age Criterion: Must have completed 18 years of age on or before the designated qualifying date. Proof of Age: Valid birth certificate, 10th class marksheet, Aadhaar card, or PAN card. Proof of Residence: Current water or electricity bill, bank passbook, Indian passport, or registered rent agreement. New Voter Application: Online or offline submission of Form 6 for fresh registration. Correction or Shifting: Submission of Form 8 for address change or correction of particulars.",
    'selection_process' => "Stage 1: House-to-house physical verification and enumeration by designated Booth Level Officers (BLOs). Stage 2: Publication of Integrated Draft Electoral Roll on the CEO portal and at local polling booths. Stage 3: Statutory window for electors to file claims and objections through Form 6, 7, or 8. Stage 4: Scrutiny, verification, and disposal of claims by the Electoral Registration Officer (ERO). Stage 5: Publication of the authenticated Final Electoral Roll with updated voter records.",
    'syllabus_snapshot' => "Legal Mandate: Governed under the Representation of the People Act 1950 and Registration of Electors Rules 1960. Form 6: Official application for inclusion of name in the electoral roll for first-time voters. Form 6A: Application for registration of overseas and Non-Resident Indian (NRI) electors. Form 7: Application for objection against proposed inclusion or deletion of an existing entry. Form 8: Application for correction of voter particulars, shifting of residence, or duplicate EPIC card. Official Portals: Chief Electoral Officer Delhi at ceodelhi.gov.in/SIR2026.aspx and the National Voter Services Portal at voters.eci.gov.in."
];

// Ensure tables exist
\App\Services\GlossaryService::initTable();

// 1. Find if an article was published about SIR on Sarkari.online
$relatedArticleSlug = null;
try {
    $art = Database::fetchOne(
        "SELECT slug, title FROM articles 
         WHERE status = 'published' 
           AND (title LIKE '%Special Intensive Revision%' OR title LIKE '%SIR%' OR slug LIKE '%sir%') 
         ORDER BY published_at DESC LIMIT 1"
    );
    if ($art) {
        $relatedArticleSlug = $art['slug'];
        echo "Found related article on site: '{$art['title']}' (slug: {$relatedArticleSlug})\n";
    } else {
        echo "No specific published article matched for SIR. Looking for related voter/election update...\n";
    }
} catch (\Throwable $e) {
    echo "Notice checking articles: " . $e->getMessage() . "\n";
}

$sirData['related_article_slug'] = $relatedArticleSlug;

// 2. Check if term exists in glossary_terms
$term = Database::fetchOne("SELECT id, acronym, slug, full_form_en FROM glossary_terms WHERE slug = 'sir' OR acronym = 'SIR' LIMIT 1");

if ($term) {
    $id = (int)$term['id'];
    echo "Found existing term: ID={$id}, Current Full Form: '{$term['full_form_en']}'\n";
    
    Database::execute(
        "UPDATE `glossary_terms` SET 
            `acronym` = :acronym,
            `slug` = :slug,
            `letter` = :letter,
            `full_form_en` = :full_form_en,
            `full_form_hi` = :full_form_hi,
            `category` = :category,
            `conducting_body` = :conducting_body,
            `official_portal` = :official_portal,
            `overview` = :overview,
            `eligibility_criteria` = :eligibility_criteria,
            `selection_process` = :selection_process,
            `syllabus_snapshot` = :syllabus_snapshot,
            `related_article_slug` = :related_article_slug,
            `last_reviewed_at` = CURDATE(),
            `updated_at` = NOW()
         WHERE `id` = :id",
        [
            'acronym' => $sirData['acronym'],
            'slug' => $sirData['slug'],
            'letter' => $sirData['letter'],
            'full_form_en' => $sirData['full_form_en'],
            'full_form_hi' => $sirData['full_form_hi'],
            'category' => $sirData['category'],
            'conducting_body' => $sirData['conducting_body'],
            'official_portal' => $sirData['official_portal'],
            'overview' => $sirData['overview'],
            'eligibility_criteria' => $sirData['eligibility_criteria'],
            'selection_process' => $sirData['selection_process'],
            'syllabus_snapshot' => $sirData['syllabus_snapshot'],
            'related_article_slug' => $sirData['related_article_slug'],
            'id' => $id
        ]
    );
    echo "Updated glossary_terms table for SIR (id={$id}).\n";
} else {
    echo "Term 'SIR' not found in glossary_terms. Inserting new record...\n";
    Database::execute(
        "INSERT INTO `glossary_terms` 
            (`acronym`, `slug`, `letter`, `full_form_en`, `full_form_hi`, `category`, `conducting_body`, `official_portal`, `overview`, `eligibility_criteria`, `selection_process`, `syllabus_snapshot`, `related_article_slug`, `last_reviewed_at`, `created_at`, `updated_at`)
         VALUES 
            (:acronym, :slug, :letter, :full_form_en, :full_form_hi, :category, :conducting_body, :official_portal, :overview, :eligibility_criteria, :selection_process, :syllabus_snapshot, :related_article_slug, CURDATE(), NOW(), NOW())",
        $sirData
    );
    $id = (int)Database::fetchValue("SELECT id FROM glossary_terms WHERE slug = 'sir' LIMIT 1");
    echo "Inserted SIR into glossary_terms (id={$id}).\n";
}

// 3. Update full_form_entity_facts
$faqs = [
    [
        'q' => 'What is the full form of SIR?',
        'a' => 'SIR stands for Special Intensive Revision (हिंदी में: विशेष गहन पुनरीक्षण). It is an intensive door-to-door electoral roll revision exercise conducted by the Election Commission of India and Chief Electoral Officers.'
    ],
    [
        'q' => 'What is the official portal for Special Intensive Revision (SIR) 2026?',
        'a' => 'The official portal for SIR 2026 is the Chief Electoral Officer Delhi portal at https://ceodelhi.gov.in/SIR2026.aspx and the national voter service portal at https://voters.eci.gov.in.'
    ],
    [
        'q' => 'Who is eligible to register during Special Intensive Revision (SIR)?',
        'a' => 'Any Indian citizen residing in the assembly constituency who is 18 years of age or above as of the qualifying cutoff date can register or update their voter details during SIR.'
    ],
    [
        'q' => 'Which voter forms are used during Special Intensive Revision?',
        'a' => 'Key forms include Form 6 (for new voter inclusion), Form 7 (for objection or deletion of names), and Form 8 (for correction of particulars or shifting of residence).'
    ]
];

try {
    $existingFacts = Database::fetchOne("SELECT id FROM full_form_entity_facts WHERE full_form_id = :fid LIMIT 1", ['fid' => $id]);
    if ($existingFacts) {
        Database::execute(
            "UPDATE `full_form_entity_facts` SET 
                `pay_level_7cpc` = NULL,
                `basic_pay_min` = NULL,
                `basic_pay_max` = NULL,
                `gross_salary_min` = NULL,
                `gross_salary_max` = NULL,
                `allowances_summary` = 'Not Applicable. Special Intensive Revision (SIR) is an official statutory electoral roll revision program conducted by the Election Commission of India (ECI) and Chief Electoral Officers rather than a salary-holding employment position.',
                `career_growth_summary` = 'Statutory democratic and electoral governance hierarchy administered by the Election Commission of India (ECI), Chief Electoral Officers (CEO), District Election Officers (DEO), and Electoral Registration Officers (ERO).',
                `faqs_json` = :faqs,
                `evidence_url` = 'https://ceodelhi.gov.in/SIR2026.aspx',
                `confidence` = 'VERIFIED',
                `last_verified_at` = NOW(),
                `updated_at` = NOW()
             WHERE `id` = :fact_id",
            [
                'faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'fact_id' => (int)$existingFacts['id']
            ]
        );
        echo "Updated full_form_entity_facts for SIR (fact_id={$existingFacts['id']}).\n";
    } else {
        Database::execute(
            "INSERT INTO `full_form_entity_facts` 
                (`full_form_id`, `pay_level_7cpc`, `basic_pay_min`, `basic_pay_max`, `gross_salary_min`, `gross_salary_max`, `allowances_summary`, `career_growth_summary`, `faqs_json`, `evidence_url`, `confidence`, `last_verified_at`, `created_at`, `updated_at`)
             VALUES 
                (:fid, NULL, NULL, NULL, NULL, NULL, 'Not Applicable. Special Intensive Revision (SIR) is an official statutory electoral roll revision program conducted by the Election Commission of India (ECI) and Chief Electoral Officers rather than a salary-holding employment position.', 'Statutory democratic and electoral governance hierarchy administered by the Election Commission of India (ECI), Chief Electoral Officers (CEO), District Election Officers (DEO), and Electoral Registration Officers (ERO).', :faqs, 'https://ceodelhi.gov.in/SIR2026.aspx', 'VERIFIED', NOW(), NOW(), NOW())",
            [
                'fid' => $id,
                'faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]
        );
        echo "Inserted full_form_entity_facts for SIR.\n";
    }
} catch (\Throwable $e) {
    echo "Notice on full_form_entity_facts: " . $e->getMessage() . "\n";
}

// 4. Update candidate queue if SIR was queued
try {
    Database::execute(
        "UPDATE glossary_candidate_terms SET 
            proposed_full_form_en = 'Special Intensive Revision',
            proposed_source_url = 'https://ceodelhi.gov.in/SIR2026.aspx',
            status = 'verified',
            reviewed_at = NOW()
         WHERE acronym = 'SIR'"
    );
    echo "Updated candidate queue for SIR.\n";
} catch (\Throwable $e) {}

echo "\nSUCCESS! SIR full form is now updated to 'Special Intensive Revision (विशेष गहन पुनरीक्षण)'!\n";
echo "Official Portal: https://ceodelhi.gov.in/SIR2026.aspx\n";
