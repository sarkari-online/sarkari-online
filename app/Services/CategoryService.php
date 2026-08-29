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
        $t = mb_strtolower(trim($title . ' ' . mb_substr($content, 0, 300)));

        // 1. Scholarships & Fellowships (HIGHEST PRIORITY: Any financial aid, grant, or scholarship overrides university context)
        if (str_contains($t, 'scholarship') || str_contains($t, 'fellowship') || str_contains($t, 'nfsc') || str_contains($t, 'nsp') || str_contains($t, 'pmsss') || str_contains($t, 'yasasvi') || str_contains($t, 'stipend') || str_contains($t, 'financial aid') || str_contains($t, 'post matric') || str_contains($t, 'pre matric') || str_contains($t, 'fee waiver') || str_contains($t, 'freeship')) {
            $slug = 'scholarships';
        }
        // 2. Admit Cards & Hall Tickets
        elseif (str_contains($t, 'admit card') || str_contains($t, 'hall ticket') || str_contains($t, 'city slip') || str_contains($t, 'city intimation') || str_contains($t, 'call letter')) {
            $slug = 'admit-cards';
        }
        // 3. Exam Results, Cutoffs & Scorecards
        elseif (str_contains($t, 'result') || str_contains($t, 'cut off') || str_contains($t, 'cutoff') || str_contains($t, 'scorecard') || str_contains($t, 'merit list') || str_contains($t, 'rank list') || str_contains($t, 'qualifying marks') || str_contains($t, 'answer key')) {
            $slug = 'exam-results';
        }
        // 4. Government Jobs & Direct Recruitment
        elseif (str_contains($t, 'recruitment') || str_contains($t, 'vacanc') || str_contains($t, 'direct recruitment') || str_contains($t, 'bharti') || str_contains($t, 'agniveer') || str_contains($t, 'havaldar') || str_contains($t, 'constable') || str_contains($t, 'sub-inspector') || str_contains($t, 'inspector') || str_contains($t, 'apply by') || str_contains($t, 'ssc cgl') || str_contains($t, 'ssc chsl') || str_contains($t, 'ssc mts') || str_contains($t, 'rrb') || str_contains($t, 'ibps') || str_contains($t, 'sbi') || str_contains($t, 'itbp') || str_contains($t, 'crpf') || str_contains($t, 'bsf') || str_contains($t, 'police') || str_contains($t, 'sarkari job') || str_contains($t, 'govt job')) {
            $slug = 'government-jobs';
        }
        // 5. Entrance Exams (NEET, JEE, CUET, GATE, WBJEE, CTET, AIBE, CAT, CLAT)
        elseif (str_contains($t, 'gate 20') || str_contains($t, 'jee ') || str_contains($t, 'neet ') || str_contains($t, 'cuet ') || str_contains($t, 'ctet ') || str_contains($t, 'aibe') || str_contains($t, 'bar council') || str_contains($t, 'bci') || str_contains($t, 'clat') || str_contains($t, 'cat 20') || str_contains($t, 'entrance') || str_contains($t, 'wbjee')) {
            $slug = 'entrance-exams';
        }
        // 6. Student Tech & AI (DigiLocker, ABC ID, APAAR, Aadhaar, NSDC, OTR)
        elseif (str_contains($t, 'aadhaar') || str_contains($t, 'digilocker') || str_contains($t, 'academic bank of credits') || str_contains($t, 'abc id') || str_contains($t, 'apaar') || str_contains($t, 'otr') || str_contains($t, 'nsdc') || str_contains($t, 'cloud certification') || str_contains($t, 'ai skill') || str_contains($t, 'edtech') || str_contains($t, 'student portal') || str_contains($t, 'student technology')) {
            $slug = 'student-technology';
        }
        // 7. Exam Dates & Timetables
        elseif (str_contains($t, 'exam date') || str_contains($t, 'date sheet') || str_contains($t, 'datesheet') || str_contains($t, 'time table') || str_contains($t, 'timetable') || str_contains($t, 'exam schedule') || str_contains($t, 'calendar 20')) {
            $slug = 'exam-dates';
        }
        // 8. Career Guides & Preparation Strategy (Syllabus, Weightage, Roadmap, Strategy)
        elseif (str_contains($t, 'syllabus') || str_contains($t, 'weightage') || str_contains($t, 'roadmap') || str_contains($t, 'preparation strategy') || str_contains($t, 'preparation guide') || str_contains($t, 'chapter-wise') || str_contains($t, 'best books') || str_contains($t, 'study plan') || str_contains($t, 'exam pattern') || str_contains($t, 'how to prepare') || str_contains($t, 'strategy')) {
            $slug = 'career-guides';
        }
        // 9. College Updates & Higher Education (UGC, PhD, Seat Allotment, JoSAA, CSAB, DU CSAS, Fee Refund, Gap Certificate, University Admissions)
        elseif (str_contains($t, 'phd admission') || str_contains($t, 'academic calendar') || str_contains($t, 'ugc') || str_contains($t, 'fee refund') || str_contains($t, 'gap certificate') || str_contains($t, 'josaa') || str_contains($t, 'csab') || str_contains($t, 'csas') || str_contains($t, 'seat allotment') || str_contains($t, 'counselling') || str_contains($t, 'college update') || str_contains($t, 'college admission') || str_contains($t, 'university admission')) {
            $slug = 'college-updates';
        }
        else {
            $slug = $currentSlug ?: 'career-guides';
        }

        $category = self::getBySlug($slug);
        if (!$category) {
            $category = self::getBySlug('career-guides') ?: self::getBySlug('government-jobs');
        }

        return $category;
    }
}
