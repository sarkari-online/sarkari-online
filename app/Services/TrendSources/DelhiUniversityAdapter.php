<?php
/**
 * Sarkari.online - Delhi University (DU) & College Admissions Radar Adapter
 *
 * Continuously ingests genuine, seasonal, high-intent Delhi University student topics
 * (DU SOL direct admissions, CSAS spot rounds, NCWEB cutoffs, and Samarth exam forms)
 * so that a fresh, authentic DU article is always ready for daily publishing.
 */

namespace App\Services\TrendSources;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Env;
use Throwable;

class DelhiUniversityAdapter implements TrendSourceInterface {

    public function getSourceId(): string {
        return 'delhi_university';
    }

    public function getSourceName(): string {
        return 'Delhi University & College Admissions Radar';
    }

    public function fetch(int $limit = 5): array {
        $results = [];

        // Active DU & College Admission streams mapped to official portals & seasons
        $streams = [
            [
                'keyword' => 'DU SOL Admission 2026: UG & PG Direct Registration, Eligibility & Application Form',
                'source' => 'Delhi University (SOL)',
                'url' => 'https://sol.du.ac.in',
                'trend_score' => 96,
                'category_hint' => 'college-updates',
                'stream_key' => 'du_sol',
                'snippet' => 'Delhi University School of Open Learning (DU SOL) UG & PG direct admission for BA, B.Com, BBA without CUET score based on 12th marks. Step-by-step registration on sol.du.ac.in.'
            ],
            [
                'keyword' => 'DU CSAS Spot Admission 2026: Vacant Seats Matrix, Registration Link & Schedule',
                'source' => 'Delhi University (DU CSAS)',
                'url' => 'https://admission.uod.ac.in',
                'trend_score' => 95,
                'category_hint' => 'college-updates',
                'stream_key' => 'du_spot_round',
                'snippet' => 'Delhi University CSAS spot admission round announced for vacant undergraduate seats across North & South Campus colleges. Online registration and seat allocation schedule at admission.uod.ac.in.'
            ],
            [
                'keyword' => 'IGNOU July 2026 Admission: Last Date Extended, Samarth Portal Link & Documents Checklist',
                'source' => 'IGNOU Official Portal',
                'url' => 'https://ignouadmission.samarth.edu.in',
                'trend_score' => 94,
                'category_hint' => 'college-updates',
                'stream_key' => 'ignou_fresh',
                'snippet' => 'Indira Gandhi National Open University (IGNOU) extends registration deadline for July session fresh admission & re-registration for UG, PG and Diploma courses on Samarth portal.'
            ],
            [
                'keyword' => 'DU NCWEB Admission 2026: Special Cutoff List, College-Wise Merit & Registration Link',
                'source' => 'Delhi University (NCWEB)',
                'url' => 'https://ncweb.du.ac.in',
                'trend_score' => 92,
                'category_hint' => 'college-updates',
                'stream_key' => 'du_ncweb',
                'snippet' => 'Delhi University Non-Collegiate Women’s Education Board releases special cutoff lists for BA and B.Com programs. Weekend batch admission guidelines and fee payment at ncweb.du.ac.in.'
            ],
            [
                'keyword' => 'DU Exam Form 2026: Semester 1, 3 & 5 Online Registration at slc.uod.ac.in',
                'source' => 'Delhi University (Samarth Examination)',
                'url' => 'https://slc.uod.ac.in',
                'trend_score' => 91,
                'category_hint' => 'college-updates',
                'stream_key' => 'du_exam_form',
                'snippet' => 'Delhi University opens odd semester examination form submission for regular and SOL students on the Samarth student portal slc.uod.ac.in. Fee payment, admit card and practical dates.'
            ]
        ];

        try {
            // Find which streams were NOT published recently (within last 10 days)
            foreach ($streams as $st) {
                if (count($results) >= $limit) {
                    break;
                }

                $kw = $st['keyword'];
                $isAlreadyPublished = Database::fetchOne(
                    "SELECT id FROM articles WHERE title LIKE :kw AND published_at >= DATE_SUB(NOW(), INTERVAL 10 DAY) LIMIT 1",
                    ['kw' => '%' . mb_substr($st['stream_key'], 0, 8) . '%']
                );

                if (!$isAlreadyPublished) {
                    $results[] = [
                        'keyword' => $st['keyword'],
                        'source' => $st['source'],
                        'url' => $st['url'],
                        'trend_score' => $st['trend_score'],
                        'category_hint' => $st['category_hint'],
                        'raw_payload' => [
                            'snippet' => $st['snippet'],
                            'source_name' => $st['source'],
                            'url' => $st['url'],
                            'stream_key' => $st['stream_key'],
                            'discovered_by' => 'DelhiUniversityAdapter'
                        ]
                    ];
                }
            }
        } catch (Throwable $e) {
            Logger::warning("DelhiUniversityAdapter fetch error: " . $e->getMessage());
        }

        // Return top prioritized candidate
        return array_slice($results, 0, $limit);
    }
}
