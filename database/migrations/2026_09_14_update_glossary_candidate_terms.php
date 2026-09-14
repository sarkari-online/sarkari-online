<?php
/**
 * Sarkari.online - Migration: Update glossary_candidate_terms and Seed High-Demand Acronyms
 */
require_once dirname(__DIR__, 2) . '/config.php';

use App\Database\Database;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Starting migration: update_glossary_candidate_terms...\n";

try {
    $db = Database::getConnection();

    // 1. Ensure table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `glossary_candidate_terms` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `acronym` VARCHAR(60) NOT NULL,
        `source_article_ids` TEXT NULL,
        `occurrence_count` INT NOT NULL DEFAULT 1,
        `surrounding_context` TEXT NULL,
        `proposed_full_form_en` VARCHAR(255) NULL,
        `proposed_source_url` VARCHAR(500) NULL,
        `category` VARCHAR(60) NULL,
        `conducting_body` VARCHAR(255) NULL,
        `priority_score` INT NOT NULL DEFAULT 50,
        `status` ENUM('pending','verified','rejected','duplicate') NOT NULL DEFAULT 'pending',
        `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reviewed_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_candidate_acronym` (`acronym`),
        INDEX `idx_candidate_status` (`status`),
        INDEX `idx_candidate_priority` (`priority_score`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Add columns if table already existed without them
    $columns = [
        'category' => "ALTER TABLE `glossary_candidate_terms` ADD COLUMN `category` VARCHAR(60) NULL AFTER `proposed_source_url`",
        'conducting_body' => "ALTER TABLE `glossary_candidate_terms` ADD COLUMN `conducting_body` VARCHAR(255) NULL AFTER `category`",
        'priority_score' => "ALTER TABLE `glossary_candidate_terms` ADD COLUMN `priority_score` INT NOT NULL DEFAULT 50 AFTER `conducting_body`",
    ];

    foreach ($columns as $col => $alterSql) {
        try {
            $colExists = Database::fetchOne("SHOW COLUMNS FROM `glossary_candidate_terms` LIKE '{$col}'");
            if (!$colExists) {
                $db->exec($alterSql);
                echo "-> Added column `{$col}` to `glossary_candidate_terms`.\n";
            }
        } catch (\Throwable $e) {}
    }

    echo "-> Table structure verified.\n";

    // 3. Seed High-Demand Indian Government / Exam Candidate Acronyms
    $seedCandidates = [
        ['acronym' => 'UPPRPB', 'full_form' => 'Uttar Pradesh Police Recruitment and Promotion Board', 'category' => 'police', 'body' => 'Government of Uttar Pradesh', 'priority' => 99],
        ['acronym' => 'CEPTAM', 'full_form' => 'Centre for Personnel Assessment and Management', 'category' => 'engineering', 'body' => 'Defence Research and Development Organisation (DRDO)', 'priority' => 98],
        ['acronym' => 'NORCET', 'full_form' => 'Nursing Officer Recruitment Common Eligibility Test', 'category' => 'medical', 'body' => 'All India Institute of Medical Sciences (AIIMS)', 'priority' => 98],
        ['acronym' => 'MPESB', 'full_form' => 'Madhya Pradesh Employees Selection Board', 'category' => 'civil_services', 'body' => 'Government of Madhya Pradesh', 'priority' => 95],
        ['acronym' => 'REET', 'full_form' => 'Rajasthan Eligibility Examination for Teachers', 'category' => 'teaching', 'body' => 'Board of Secondary Education Rajasthan (RBSE)', 'priority' => 95],
        ['acronym' => 'UPTET', 'full_form' => 'Uttar Pradesh Teacher Eligibility Test', 'category' => 'teaching', 'body' => 'UP Basic Education Board (UPBEB)', 'priority' => 94],
        ['acronym' => 'BTET', 'full_form' => 'Bihar Teacher Eligibility Test', 'category' => 'teaching', 'body' => 'Bihar School Examination Board (BSEB)', 'priority' => 93],
        ['acronym' => 'HTET', 'full_form' => 'Haryana Teacher Eligibility Test', 'category' => 'teaching', 'body' => 'Board of School Education Haryana (BSEH)', 'priority' => 92],
        ['acronym' => 'BSPHCL', 'full_form' => 'Bihar State Power Holding Company Limited', 'category' => 'engineering', 'body' => 'Energy Department, Government of Bihar', 'priority' => 90],
        ['acronym' => 'UPPCL', 'full_form' => 'Uttar Pradesh Power Corporation Limited', 'category' => 'engineering', 'body' => 'Energy Department, Government of Uttar Pradesh', 'priority' => 90],
        ['acronym' => 'DMRC', 'full_form' => 'Delhi Metro Rail Corporation', 'category' => 'railway', 'body' => 'Ministry of Housing and Urban Affairs & Govt of NCT Delhi', 'priority' => 89],
        ['acronym' => 'BEL', 'full_form' => 'Bharat Electronics Limited', 'category' => 'engineering', 'body' => 'Ministry of Defence, Government of India', 'priority' => 88],
        ['acronym' => 'SAIL', 'full_form' => 'Steel Authority of India Limited', 'category' => 'engineering', 'body' => 'Ministry of Steel, Government of India', 'priority' => 88],
        ['acronym' => 'IOCL', 'full_form' => 'Indian Oil Corporation Limited', 'category' => 'engineering', 'body' => 'Ministry of Petroleum and Natural Gas', 'priority' => 87],
        ['acronym' => 'HPCL', 'full_form' => 'Hindustan Petroleum Corporation Limited', 'category' => 'engineering', 'body' => 'Ministry of Petroleum and Natural Gas', 'priority' => 86],
        ['acronym' => 'PGCIL', 'full_form' => 'Power Grid Corporation of India Limited', 'category' => 'engineering', 'body' => 'Ministry of Power, Government of India', 'priority' => 85],
        ['acronym' => 'CSIR', 'full_form' => 'Council of Scientific and Industrial Research', 'category' => 'entrance', 'body' => 'Ministry of Science and Technology', 'priority' => 85],
        ['acronym' => 'ICAR', 'full_form' => 'Indian Council of Agricultural Research', 'category' => 'entrance', 'body' => 'Ministry of Agriculture and Farmers Welfare', 'priority' => 85],
        ['acronym' => 'ICMR', 'full_form' => 'Indian Council of Medical Research', 'category' => 'medical', 'body' => 'Ministry of Health and Family Welfare', 'priority' => 84],
        ['acronym' => 'CLAT', 'full_form' => 'Common Law Admission Test', 'category' => 'entrance', 'body' => 'Consortium of National Law Universities', 'priority' => 84],
        ['acronym' => 'ICG', 'full_form' => 'Indian Coast Guard', 'category' => 'defence', 'body' => 'Ministry of Defence, Government of India', 'priority' => 83],
        ['acronym' => 'BRO', 'full_form' => 'Border Roads Organisation', 'category' => 'defence', 'body' => 'Ministry of Defence, Government of India', 'priority' => 82],
        ['acronym' => 'Kanoongo', 'full_form' => 'Revenue Inspector (Kanoongo)', 'category' => 'civil_services', 'body' => 'Board of Revenue (State Governments)', 'priority' => 82],
        ['acronym' => 'Naib Tehsildar', 'full_form' => 'Naib Tehsildar (Executive Magistrate / Revenue Officer)', 'category' => 'civil_services', 'body' => 'State Revenue Department & Public Service Commissions', 'priority' => 81],
        ['acronym' => 'Tehsildar', 'full_form' => 'Tehsildar (Sub-Divisional Revenue Executive Officer)', 'category' => 'civil_services', 'body' => 'State Civil Services / Revenue Department', 'priority' => 80],
        ['acronym' => 'Amin', 'full_form' => 'Revenue Surveyor & Land Demarcation Officer (Amin)', 'category' => 'civil_services', 'body' => 'Department of Revenue & Land Reforms', 'priority' => 80],
        ['acronym' => 'CHO', 'full_form' => 'Community Health Officer', 'category' => 'medical', 'body' => 'National Health Mission (NHM)', 'priority' => 79],
        ['acronym' => 'ANM', 'full_form' => 'Auxiliary Nurse Midwife', 'category' => 'medical', 'body' => 'State Health and Family Welfare Departments', 'priority' => 78],
        ['acronym' => 'GNM', 'full_form' => 'General Nursing and Midwifery', 'category' => 'medical', 'body' => 'Indian Nursing Council & State Nursing Councils', 'priority' => 78],
        ['acronym' => 'FSO', 'full_form' => 'Food Safety Officer', 'category' => 'civil_services', 'body' => 'Food Safety and Standards Authority of India (FSSAI) / State FDA', 'priority' => 77],
        ['acronym' => 'CDPO', 'full_form' => 'Child Development Project Officer', 'category' => 'civil_services', 'body' => 'Women and Child Development Department (State PSCs)', 'priority' => 76],
        ['acronym' => 'BEO', 'full_form' => 'Block Education Officer', 'category' => 'teaching', 'body' => 'State School Education Department', 'priority' => 75],
        ['acronym' => 'SET', 'full_form' => 'State Eligibility Test (Assistant Professorship)', 'category' => 'teaching', 'body' => 'State Nodal Higher Education Agencies / UGC', 'priority' => 75],
        ['acronym' => 'ASRB', 'full_form' => 'Agricultural Scientists Recruitment Board', 'category' => 'entrance', 'body' => 'Ministry of Agriculture and Farmers Welfare', 'priority' => 74],
        ['acronym' => 'NHM', 'full_form' => 'National Health Mission', 'category' => 'medical', 'body' => 'Ministry of Health and Family Welfare', 'priority' => 74],
        ['acronym' => 'BIS', 'full_form' => 'Bureau of Indian Standards', 'category' => 'engineering', 'body' => 'Ministry of Consumer Affairs, Food and Public Distribution', 'priority' => 73],
        ['acronym' => 'IMD', 'full_form' => 'India Meteorological Department', 'category' => 'engineering', 'body' => 'Ministry of Earth Sciences, Government of India', 'priority' => 72],
        ['acronym' => 'DGCA', 'full_form' => 'Directorate General of Civil Aviation', 'category' => 'civil_services', 'body' => 'Ministry of Civil Aviation, Government of India', 'priority' => 71],
        ['acronym' => 'NID', 'full_form' => 'National Institute of Design', 'category' => 'entrance', 'body' => 'Department for Promotion of Industry and Internal Trade (DPIIT)', 'priority' => 70],
        ['acronym' => 'NIFT', 'full_form' => 'National Institute of Fashion Technology', 'category' => 'entrance', 'body' => 'Ministry of Textiles, Government of India', 'priority' => 70],
    ];

    $inserted = 0;
    foreach ($seedCandidates as $c) {
        // Skip if already in published glossary
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($c['acronym'])));
        $inGlossary = Database::fetchOne("SELECT id FROM `glossary_terms` WHERE slug = :s OR acronym = :a LIMIT 1", [
            's' => $slug,
            'a' => $c['acronym']
        ]);
        if ($inGlossary) {
            continue;
        }

        // Insert or ignore into candidate terms
        $inQueue = Database::fetchOne("SELECT id FROM `glossary_candidate_terms` WHERE acronym = :a LIMIT 1", ['a' => $c['acronym']]);
        if (!$inQueue) {
            Database::insert('glossary_candidate_terms', [
                'acronym' => $c['acronym'],
                'proposed_full_form_en' => $c['full_form'],
                'category' => $c['category'],
                'conducting_body' => $c['body'],
                'priority_score' => $c['priority'],
                'status' => 'pending',
                'occurrence_count' => 5,
                'first_seen_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }
    }

    echo "-> Successfully seeded {$inserted} high-demand government candidate terms into queue.\n";
    Logger::info("Migration update_glossary_candidate_terms executed successfully. Seeded {$inserted} candidates.");

} catch (Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    Logger::error("Migration update_glossary_candidate_terms failed: " . $e->getMessage());
    exit(1);
}
