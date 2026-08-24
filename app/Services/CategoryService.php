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
        $lower = mb_strtolower($title . ' ' . mb_substr($content, 0, 500));

        // 1. Admit Cards
        if (str_contains($lower, 'admit card') || str_contains($lower, 'hall ticket') || str_contains($lower, 'city slip') || str_contains($lower, 'call letter')) {
            $slug = 'admit-cards';
        }
        // 2. Scholarships
        elseif (str_contains($lower, 'scholarship') || str_contains($lower, 'nsp ') || str_contains($lower, 'pmsss') || str_contains($lower, 'yasasvi') || str_contains($lower, 'fellowship') || str_contains($lower, 'post matric')) {
            $slug = 'scholarships';
        }
        // 3. Student Tech & AI Skilling
        elseif (str_contains($lower, 'nsdc') || str_contains($lower, 'skill initiative') || str_contains($lower, 'cloud certification') || str_contains($lower, 'ai skill') || str_contains($lower, 'edtech')) {
            $slug = 'student-technology';
        }
        // 4. Career Guides (Syllabus, Weightage, Roadmap, Preparation)
        elseif (str_contains($lower, 'syllabus') || str_contains($lower, 'weightage') || str_contains($lower, 'roadmap') || str_contains($lower, 'preparation guide') || str_contains($lower, 'preparation strategy') || str_contains($lower, 'chapter-wise') || str_contains($lower, 'best books') || str_contains($lower, 'study guide')) {
            $slug = 'career-guides';
        }
        // 5. Government Jobs (Recruitment, Vacancies, Notification)
        elseif (str_contains($lower, 'recruitment') || str_contains($lower, 'vacanc') || str_contains($lower, 'agniveer') || str_contains($lower, 'havaldar') || str_contains($lower, 'apply by') || (str_contains($lower, 'notification') && !str_contains($lower, 'result') && !str_contains($lower, 'ctet'))) {
            $slug = 'government-jobs';
        }
        // 6. Exam Results (Result, Cutoff, Merit list, Scorecard, Marksheet, Answer Key)
        elseif (str_contains($lower, 'result') || str_contains($lower, 'cut off') || str_contains($lower, 'cutoff') || str_contains($lower, 'scorecard') || str_contains($lower, 'merit list') || str_contains($lower, 'rank list') || str_contains($lower, 'qualifying marks') || str_contains($lower, 'answer key')) {
            $slug = 'exam-results';
        }
        // 7. Entrance Exams & Admissions (GATE, NEET, JEE, CUET, CTET, UPSC Mains, Counselling, Registration Timeline)
        elseif (str_contains($lower, 'gate 20') || str_contains($lower, 'jee ') || str_contains($lower, 'neet ') || str_contains($lower, 'cuet ') || str_contains($lower, 'ctet ') || str_contains($lower, 'counselling') || str_contains($lower, 'seat allotment') || str_contains($lower, 'entrance') || str_contains($lower, 'registration timeline')) {
            $slug = 'entrance-exams';
        }
        // 8. Exam Dates & Timetables
        elseif (str_contains($lower, 'exam date') || str_contains($lower, 'date sheet') || str_contains($lower, 'time table') || str_contains($lower, 'timeline') || str_contains($lower, 'exam schedule')) {
            $slug = 'exam-dates';
        }
        else {
            $slug = $currentSlug ?: 'entrance-exams';
        }

        $category = self::getBySlug($slug);
        if (!$category) {
            $category = self::getBySlug('entrance-exams') ?: self::getBySlug('exam-results');
        }

        return $category;
    }
}
