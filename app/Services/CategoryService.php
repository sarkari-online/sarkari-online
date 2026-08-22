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
}
