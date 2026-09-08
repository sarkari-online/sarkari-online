<?php
/**
 * EduPulse - Category Service Layer
 * Business logic for category taxonomy management and querying.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;

class CategoryService {

    public static function getAll(): array {
        return Database::fetchAll("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    }

    public static function getBySlug(string $slug): ?array {
        return Database::fetchOne("SELECT * FROM categories WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
    }

    public static function getById(int $id): ?array {
        return Database::fetchOne("SELECT * FROM categories WHERE id = :id LIMIT 1", ['id' => $id]);
    }

    public static function create(array $data): int {
        $slug = !empty($data['slug']) ? Sanitizer::slug($data['slug']) : Sanitizer::slug($data['name']);

        $existing = self::getBySlug($slug);
        if ($existing) {
            $slug .= '-' . time();
        }

        $insertData = [
            'name' => Sanitizer::string($data['name']),
            'slug' => $slug,
            'description' => Sanitizer::string($data['description'] ?? ''),
            'color' => Sanitizer::string($data['color'] ?? '#1e3a8a'),
            'bg_light' => Sanitizer::string($data['bg_light'] ?? '#eff6ff'),
            'icon' => Sanitizer::string($data['icon'] ?? 'award'),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = (int)Database::insert('categories', $insertData);
        Logger::info('Category created', ['id' => $id, 'name' => $insertData['name']]);

        return $id;
    }

    public static function update(int $id, array $data): bool {
        $current = self::getById($id);
        if (!$current) return false;

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = Sanitizer::string($data['name']);
        if (isset($data['slug'])) {
            $slug = Sanitizer::slug($data['slug']);
            if ($slug !== $current['slug']) {
                $existing = Database::fetchOne("SELECT id FROM categories WHERE slug = :s AND id != :id LIMIT 1", ['s' => $slug, 'id' => $id]);
                if ($existing) {
                    $slug .= '-' . time();
                }
                $updateData['slug'] = $slug;
            }
        }
        if (isset($data['description'])) $updateData['description'] = Sanitizer::string($data['description']);
        if (isset($data['color'])) $updateData['color'] = Sanitizer::string($data['color']);
        if (isset($data['bg_light'])) $updateData['bg_light'] = Sanitizer::string($data['bg_light']);
        if (isset($data['icon'])) $updateData['icon'] = Sanitizer::string($data['icon']);
        if (isset($data['sort_order'])) $updateData['sort_order'] = (int)$data['sort_order'];

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('categories', $updateData, 'id = :id', ['id' => $id]);
        Logger::info('Category updated', ['id' => $id]);

        return true;
    }

    public static function delete(int $id): bool {
        // Prevent deletion if articles are attached
        $count = (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE category_id = :id", ['id' => $id]);
        if ($count > 0) {
            return false;
        }

        $deleted = Database::delete('categories', 'id = :id', ['id' => $id]);
        if ($deleted) {
            Logger::info('Category deleted', ['id' => $id]);
        }
        return $deleted > 0;
    }

    /**
     * Intelligently auto-resolve and correct category taxonomy based on title and content
     */
    public static function autoResolveCategory(string $title, string $content = '', ?string $currentSlug = null): ?array {
        $titleLower = mb_strtolower(trim($title));
        $contentLower = mb_strtolower(mb_substr($content, 0, 400));
        $combined = trim($titleLower . ' ' . $contentLower);

        // 1. Admit Cards & Hall Tickets (Highest Priority if in Title or primary topic)
        if (str_contains($titleLower, 'admit card') || str_contains($titleLower, 'hall ticket') || str_contains($titleLower, 'call letter') || str_contains($titleLower, 'city slip') || str_contains($titleLower, 'city intimation')
            || str_contains($combined, 'admit card released') || str_contains($combined, 'download admit card') || str_contains($combined, 'hall ticket download')) {
            $slug = 'admit-cards';
        }
        // 2. Answer Keys & Question Paper Challenges
        elseif (str_contains($titleLower, 'answer key') || str_contains($titleLower, 'response sheet') || str_contains($combined, 'answer key') || str_contains($combined, 'response sheet') || str_contains($combined, 'key challenge') || str_contains($combined, 'omr sheet')) {
            $slug = 'answer-keys';
        }
        // 3. Exam Results, Cutoffs & Scorecards
        elseif (str_contains($titleLower, 'result') || str_contains($titleLower, 'cut off') || str_contains($titleLower, 'cutoff') || str_contains($titleLower, 'scorecard') || str_contains($titleLower, 'merit list')
            || str_contains($combined, 'result declared') || str_contains($combined, 'scorecard download') || str_contains($combined, 'merit list pdf')) {
            $slug = 'exam-results';
        }
        // 4. Government Jobs, Police, Armed Forces & Direct Recruitment
        elseif (str_contains($titleLower, 'recruitment') || str_contains($titleLower, 'vacanc') || str_contains($titleLower, 'sub inspector') || str_contains($titleLower, 'sub-inspector') || str_contains($titleLower, 'cpo') || str_contains($titleLower, 'ssc') || str_contains($titleLower, 'constable') || str_contains($titleLower, 'havaldar') || str_contains($titleLower, 'agniveer') || str_contains($titleLower, 'bharti') || str_contains($titleLower, 'police') || str_contains($titleLower, 'rrb') || str_contains($titleLower, 'ibps') || str_contains($titleLower, 'sbi') || str_contains($titleLower, 'bsf') || str_contains($titleLower, 'crpf') || str_contains($titleLower, 'itbp')
            || str_contains($combined, 'direct recruitment') || str_contains($combined, 'apply online for posts') || str_contains($combined, 'police recruitment') || str_contains($combined, 'constable recruitment') || str_contains($combined, 'sub inspector recruitment')) {
            $slug = 'government-jobs';
        }
        // 5. Scholarships & Fellowships (Financial aid, fellowships, and educational grants)
        elseif (str_contains($combined, 'scholarship') || str_contains($combined, 'fellowship') || str_contains($combined, 'nfsc') || str_contains($combined, 'nsp ') || str_contains($combined, 'pmsss') || str_contains($combined, 'yasasvi') || str_contains($combined, 'stipend') || str_contains($combined, 'financial aid') || str_contains($combined, 'post matric') || str_contains($combined, 'pre matric') || str_contains($combined, 'freeship')) {
            $slug = 'scholarships';
        }
        // 6. Entrance Exams (NEET, JEE, CUET, GATE, WBJEE, CTET, AIBE, CAT, CLAT)
        elseif (str_contains($titleLower, 'gate 20') || str_contains($titleLower, 'jee') || str_contains($titleLower, 'neet') || str_contains($titleLower, 'cuet') || str_contains($titleLower, 'ctet') || str_contains($titleLower, 'aibe') || str_contains($titleLower, 'clat') || str_contains($titleLower, 'cat 20') || str_contains($titleLower, 'entrance') || str_contains($titleLower, 'wbjee')
            || str_contains($combined, 'entrance exam') || str_contains($combined, 'common entrance test')) {
            $slug = 'entrance-exams';
        }
        // 7. Student Tech & AI (DigiLocker, ABC ID, APAAR, Aadhaar, NSDC, OTR)
        elseif (str_contains($combined, 'aadhaar') || str_contains($combined, 'digilocker') || str_contains($combined, 'academic bank of credits') || str_contains($combined, 'abc id') || str_contains($combined, 'apaar') || str_contains($combined, 'otr') || str_contains($combined, 'nsdc') || str_contains($combined, 'cloud certification') || str_contains($combined, 'ai skill') || str_contains($combined, 'student technology')) {
            $slug = 'student-technology';
        }
        // 8. Exam Dates & Timetables
        elseif (str_contains($combined, 'exam date') || str_contains($combined, 'date sheet') || str_contains($combined, 'datesheet') || str_contains($combined, 'time table') || str_contains($combined, 'timetable') || str_contains($combined, 'exam schedule') || str_contains($combined, 'calendar 20')) {
            $slug = 'exam-dates';
        }
        // 9. Career Guides & Preparation Strategy (Syllabus, Weightage, Roadmap, Strategy)
        elseif (str_contains($combined, 'syllabus') || str_contains($combined, 'weightage') || str_contains($combined, 'roadmap') || str_contains($combined, 'preparation strategy') || str_contains($combined, 'preparation guide') || str_contains($combined, 'chapter-wise') || str_contains($combined, 'best books') || str_contains($combined, 'study plan') || str_contains($combined, 'exam pattern') || str_contains($combined, 'how to prepare')) {
            $slug = 'career-guides';
        }
        // 10. College Updates & Higher Education (UGC, PhD, Seat Allotment, JoSAA, CSAB, DU CSAS, Fee Refund, University Admissions)
        elseif (str_contains($combined, 'phd admission') || str_contains($combined, 'academic calendar') || str_contains($combined, 'ugc') || str_contains($combined, 'fee refund') || str_contains($combined, 'gap certificate') || str_contains($combined, 'josaa') || str_contains($combined, 'csab') || str_contains($combined, 'csas') || str_contains($combined, 'seat allotment') || str_contains($combined, 'counselling') || str_contains($combined, 'college update') || str_contains($combined, 'college admission') || str_contains($combined, 'university admission')) {
            $slug = 'college-updates';
        }
        else {
            $slug = $currentSlug ?: 'government-jobs';
        }

        $category = self::getBySlug($slug);
        if (!$category) {
            $category = self::getBySlug('career-guides') ?: self::getBySlug('government-jobs');
        }

        return $category;
    }
}
