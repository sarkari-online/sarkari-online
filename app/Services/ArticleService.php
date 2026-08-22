<?php
/**
 * EduPulse - Article Service Layer (Phase 2)
 * Full Article CMS Model & Query Engine.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use PDO;

class ArticleService {

    /**
     * Get published articles for homepage / listings
     */
    public static function getLatestPublished(int $limit = 6, ?int $categoryId = null): array {
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color, c.bg_light AS category_bg,
                       u.username AS author_username
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE a.status = 'published'";
        
        $params = [];
        if ($categoryId !== null) {
            $sql .= " AND a.category_id = :cat_id";
            $params['cat_id'] = $categoryId;
        }

        $sql .= " ORDER BY a.published_at DESC, a.id DESC LIMIT " . (int)$limit;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get single article by slug (with optional draft preview support)
     */
    public static function getBySlug(string $slug, bool $allowDraft = false): ?array {
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color, c.bg_light AS category_bg,
                       u.username AS author_username
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE a.slug = :slug";

        if (!$allowDraft) {
            $sql .= " AND a.status = 'published'";
        }

        $sql .= " LIMIT 1";

        return Database::fetchOne($sql, ['slug' => $slug]);
    }

    /**
     * Get single article by ID
     */
    public static function getById(int $id): ?array {
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.username AS author_username
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE a.id = :id LIMIT 1";

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Get paginated articles for a category archive
     */
    public static function getByCategory(string $categorySlug, int $page = 1, int $perPage = 6): array {
        $category = CategoryService::getBySlug($categorySlug);
        if (!$category) {
            return ['items' => [], 'total' => 0, 'current_page' => $page, 'total_pages' => 0];
        }

        $totalSql = "SELECT COUNT(*) FROM articles WHERE category_id = :cat_id AND status = 'published'";
        $total = (int)Database::fetchColumn($totalSql, ['cat_id' => $category['id']]);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                WHERE a.category_id = :cat_id AND a.status = 'published'
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = Database::fetchAll($sql, ['cat_id' => $category['id']]);

        return [
            'category' => $category,
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Search articles by query string
     */
    public static function search(string $query, int $page = 1, int $perPage = 6): array {
        $clean = trim($query);
        if ($clean === '') {
            return ['query' => '', 'items' => [], 'total' => 0, 'current_page' => $page, 'total_pages' => 0];
        }

        $likeParam = '%' . $clean . '%';
        $totalSql = "SELECT COUNT(*) FROM articles a 
                     JOIN categories c ON a.category_id = c.id
                     WHERE a.status = 'published' 
                       AND (a.title LIKE :q1 OR a.excerpt LIKE :q2 OR c.name LIKE :q3)";
        $total = (int)Database::fetchColumn($totalSql, ['q1' => $likeParam, 'q2' => $likeParam, 'q3' => $likeParam]);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                WHERE a.status = 'published' 
                  AND (a.title LIKE :q1 OR a.excerpt LIKE :q2 OR c.name LIKE :q3)
                ORDER BY a.published_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = Database::fetchAll($sql, ['q1' => $likeParam, 'q2' => $likeParam, 'q3' => $likeParam]);

        return [
            'query' => $clean,
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Get dashboard count statistics
     */
    public static function getStats(): array {
        return [
            'total' => (int)Database::fetchColumn("SELECT COUNT(*) FROM articles"),
            'published' => (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'published'"),
            'drafts' => (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'draft'"),
            'review' => (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'review'"),
            'rejected' => (int)Database::fetchColumn("SELECT COUNT(*) FROM articles WHERE status = 'rejected'"),
            'trends_count' => (int)Database::fetchColumn("SELECT COUNT(*) FROM trends")
        ];
    }

    /**
     * Get paginated articles for Admin panel with filtering
     */
    public static function getAllAdmin(int $page = 1, int $perPage = 15, ?string $status = null, ?int $categoryId = null, ?string $search = null): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($status)) {
            $where[] = "a.status = :status";
            $params['status'] = $status;
        }

        if (!empty($categoryId)) {
            $where[] = "a.category_id = :cat_id";
            $params['cat_id'] = $categoryId;
        }

        if (!empty($search)) {
            $where[] = "(a.title LIKE :search OR a.slug LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM articles a WHERE {$whereClause}";
        $total = (int)Database::fetchColumn($totalSql, $params);

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT a.*, c.name AS category_name, c.color AS category_color, u.username AS author_name
                FROM articles a
                JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE {$whereClause}
                ORDER BY a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = Database::fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Get all published articles for Sitemap generation
     */
    public static function getAllForSitemap(): array {
        $sql = "SELECT slug, published_at, updated_at FROM articles WHERE status = 'published' ORDER BY published_at DESC";
        return Database::fetchAll($sql);
    }

    /**
     * Create a new article with complete SEO fields
     */
    public static function create(array $data): int {
        $slug = !empty($data['slug']) ? Sanitizer::slug($data['slug']) : Sanitizer::slug($data['title']);

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (Database::fetchOne("SELECT id FROM articles WHERE slug = :s LIMIT 1", ['s' => $slug])) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $publishedAt = ($data['status'] === 'published') ? ($data['published_at'] ?? date('Y-m-d H:i:s')) : null;

        $insertData = [
            'trend_id' => !empty($data['trend_id']) ? (int)$data['trend_id'] : null,
            'title' => Sanitizer::string($data['title']),
            'slug' => $slug,
            'excerpt' => Sanitizer::string($data['excerpt'] ?? ''),
            'content' => Sanitizer::html($data['content'] ?? ''),
            'category_id' => (int)$data['category_id'],
            'author_id' => !empty($data['author_id']) ? (int)$data['author_id'] : null,
            'status' => $data['status'] ?? 'draft',
            'quality_score' => (int)($data['quality_score'] ?? 0),
            'ai_generated' => !empty($data['ai_generated']) ? 1 : 0,
            'source_verified' => !empty($data['source_verified']) ? 1 : 0,
            'featured_image' => $data['featured_image'] ?? null,
            'featured_image_alt' => Sanitizer::string($data['featured_image_alt'] ?? ''),
            'meta_title' => Sanitizer::string($data['meta_title'] ?? $data['title']),
            'meta_description' => Sanitizer::string($data['meta_description'] ?? $data['excerpt'] ?? ''),
            'canonical_url' => !empty($data['canonical_url']) ? Sanitizer::string($data['canonical_url']) : null,
            'og_title' => Sanitizer::string($data['og_title'] ?? $data['meta_title'] ?? $data['title']),
            'og_description' => Sanitizer::string($data['og_description'] ?? $data['meta_description'] ?? $data['excerpt'] ?? ''),
            'og_image' => $data['og_image'] ?? $data['featured_image'] ?? null,
            'source_name' => Sanitizer::string($data['source_name'] ?? ''),
            'source_url' => filter_var($data['source_url'] ?? '', FILTER_VALIDATE_URL) ?: null,
            'source_ref' => Sanitizer::string($data['source_ref'] ?? ''),
            'published_at' => $publishedAt,
            'original_published_at' => $publishedAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = (int)Database::insert('articles', $insertData);
        Logger::info('Article created', ['id' => $id, 'title' => $insertData['title'], 'status' => $insertData['status']]);

        return $id;
    }

    /**
     * Update an existing article with complete SEO fields
     */
    public static function update(int $id, array $data): bool {
        $current = self::getById($id);
        if (!$current) {
            return false;
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = Sanitizer::string($data['title']);
        }

        if (isset($data['slug'])) {
            $slug = Sanitizer::slug($data['slug']);
            if ($slug !== $current['slug']) {
                $existing = Database::fetchOne("SELECT id FROM articles WHERE slug = :s AND id != :id LIMIT 1", ['s' => $slug, 'id' => $id]);
                if ($existing) {
                    $slug .= '-' . time();
                }
                $updateData['slug'] = $slug;
            }
        }

        if (isset($data['excerpt'])) $updateData['excerpt'] = Sanitizer::string($data['excerpt']);
        if (isset($data['content'])) $updateData['content'] = Sanitizer::html($data['content']);
        if (isset($data['category_id'])) $updateData['category_id'] = (int)$data['category_id'];
        if (isset($data['author_id'])) $updateData['author_id'] = !empty($data['author_id']) ? (int)$data['author_id'] : null;

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
            if ($data['status'] === 'published' && empty($current['published_at'])) {
                $updateData['published_at'] = date('Y-m-d H:i:s');
                if (empty($current['original_published_at'])) {
                    $updateData['original_published_at'] = date('Y-m-d H:i:s');
                }
            } elseif ($data['status'] !== 'published' && isset($data['unpublish']) && $data['unpublish']) {
                $updateData['published_at'] = null;
            }
        }

        if (isset($data['quality_score'])) $updateData['quality_score'] = (int)$data['quality_score'];
        if (isset($data['source_verified'])) $updateData['source_verified'] = (int)$data['source_verified'];
        if (isset($data['featured_image'])) $updateData['featured_image'] = $data['featured_image'];
        if (isset($data['featured_image_alt'])) $updateData['featured_image_alt'] = Sanitizer::string($data['featured_image_alt']);
        
        // SEO Fields
        if (isset($data['meta_title'])) $updateData['meta_title'] = Sanitizer::string($data['meta_title']);
        if (isset($data['meta_description'])) $updateData['meta_description'] = Sanitizer::string($data['meta_description']);
        if (isset($data['canonical_url'])) $updateData['canonical_url'] = !empty($data['canonical_url']) ? Sanitizer::string($data['canonical_url']) : null;
        if (isset($data['og_title'])) $updateData['og_title'] = Sanitizer::string($data['og_title']);
        if (isset($data['og_description'])) $updateData['og_description'] = Sanitizer::string($data['og_description']);
        if (isset($data['og_image'])) $updateData['og_image'] = $data['og_image'];

        // Source Fields
        if (isset($data['source_name'])) $updateData['source_name'] = Sanitizer::string($data['source_name']);
        if (isset($data['source_url'])) $updateData['source_url'] = filter_var($data['source_url'], FILTER_VALIDATE_URL) ?: null;
        if (isset($data['source_ref'])) $updateData['source_ref'] = Sanitizer::string($data['source_ref']);

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('articles', $updateData, 'id = :id', ['id' => $id]);
        Logger::info('Article updated', ['id' => $id, 'title' => $updateData['title'] ?? $current['title']]);

        return true;
    }

    /**
     * Quick publish / unpublish toggle
     */
    public static function toggleStatus(int $id, string $status): bool {
        if (!in_array($status, ['draft', 'review', 'published', 'rejected'], true)) {
            return false;
        }

        $current = self::getById($id);
        if (!$current) return false;

        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'published' && empty($current['published_at'])) {
            $updateData['published_at'] = date('Y-m-d H:i:s');
            if (empty($current['original_published_at'])) {
                $updateData['original_published_at'] = date('Y-m-d H:i:s');
            }
        }

        Database::update('articles', $updateData, 'id = :id', ['id' => $id]);
        Logger::info('Article status toggled', ['id' => $id, 'new_status' => $status]);

        return true;
    }

    /**
     * Delete an article
     */
    public static function delete(int $id): bool {
        $deleted = Database::delete('articles', 'id = :id', ['id' => $id]);
        if ($deleted) {
            Logger::info('Article deleted', ['id' => $id]);
        }
        return $deleted > 0;
    }
}
