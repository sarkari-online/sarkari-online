<?php
/**
 * Sarkari.online - Question Paper & Answer Key Service
 * Manages authentic statutory master question papers, official answer keys, and direct downloads.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class QuestionPaperService {

    /**
     * Get distinct examination groups with available paper counts
     */
    public static function getAllExams(): array {
        try {
            $sql = "
                SELECT 
                    exam_slug,
                    exam_name,
                    conducting_body,
                    COUNT(*) as total_papers,
                    SUM(CASE WHEN file_type = 'question_paper' THEN 1 ELSE 0 END) as question_paper_count,
                    SUM(CASE WHEN file_type = 'answer_key' THEN 1 ELSE 0 END) as answer_key_count,
                    MAX(year) as latest_year,
                    SUM(download_count) as total_downloads
                FROM question_papers
                GROUP BY exam_slug, exam_name, conducting_body
                ORDER BY total_downloads DESC, latest_year DESC
            ";
            return Database::fetchAll($sql);
        } catch (Throwable $e) {
            Logger::error("Failed to fetch all exams for question papers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get papers for a specific exam slug with optional filtering
     */
    public static function getPapersByExam(string $examSlug, ?int $year = null, ?string $fileType = null): array {
        try {
            $where = ["exam_slug = :slug"];
            $params = ['slug' => $examSlug];

            if ($year) {
                $where[] = "year = :year";
                $params['year'] = $year;
            }

            if ($fileType && in_array($fileType, ['question_paper', 'answer_key', 'solved_paper'])) {
                $where[] = "file_type = :type";
                $params['type'] = $fileType;
            }

            $whereStr = implode(' AND ', $where);
            $sql = "SELECT * FROM question_papers WHERE {$whereStr} ORDER BY year DESC, tier_stage ASC, id ASC";

            return Database::fetchAll($sql, $params);
        } catch (Throwable $e) {
            Logger::error("Failed to fetch papers for exam '{$examSlug}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single paper by ID
     */
    public static function getPaperById(int $id): ?array {
        try {
            return Database::fetchOne("SELECT * FROM question_papers WHERE id = :id LIMIT 1", ['id' => $id]) ?: null;
        } catch (Throwable $e) {
            Logger::error("Failed to fetch paper by ID #{$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Increment download count for social proof
     */
    public static function incrementDownloadCount(int $id): void {
        try {
            Database::query("UPDATE question_papers SET download_count = download_count + 1 WHERE id = :id", ['id' => $id]);
        } catch (Throwable $e) {}
    }

    /**
     * Get distinct available years across all papers
     */
    public static function getDistinctYears(): array {
        try {
            return Database::fetchColumnAll("SELECT DISTINCT year FROM question_papers ORDER BY year DESC");
        } catch (Throwable $e) {
            return [2024, 2023, 2022];
        }
    }

    /**
     * Get all papers with filters for main hub
     */
    public static function getAllPapers(?string $examSlug = null, ?int $year = null, ?string $fileType = null, ?string $query = null): array {
        try {
            $where = ["1=1"];
            $params = [];

            if ($examSlug) {
                $where[] = "exam_slug = :slug";
                $params['slug'] = $examSlug;
            }

            if ($year) {
                $where[] = "year = :year";
                $params['year'] = $year;
            }

            if ($fileType) {
                $where[] = "file_type = :type";
                $params['type'] = $fileType;
            }

            if ($query) {
                $where[] = "(paper_title LIKE :q OR exam_name LIKE :q OR conducting_body LIKE :q)";
                $params['q'] = '%' . $query . '%';
            }

            $whereStr = implode(' AND ', $where);
            $sql = "SELECT * FROM question_papers WHERE {$whereStr} ORDER BY year DESC, download_count DESC, id ASC";

            return Database::fetchAll($sql, $params);
        } catch (Throwable $e) {
            Logger::error("Failed to fetch all papers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate LearningResource & CollectionPage JSON-LD schema
     */
    public static function generateSchema(array $papers, ?string $examSlug = null, ?array $currentExam = null): array {
        $baseUrl = rtrim(SITE_URL, '/');

        if ($currentExam) {
            $pageUrl = $baseUrl . '/previous-year-papers/' . $examSlug . '/';
            $pageName = "{$currentExam['exam_name']} Previous Year Question Papers & Official Answer Keys";
            $pageDesc = "Download official {$currentExam['exam_name']} master question papers and final answer keys PDF free. Verified academic archive compiled from {$currentExam['conducting_body']} public records.";
        } else {
            $pageUrl = $baseUrl . '/previous-year-papers/';
            $pageName = "Sarkari Previous Year Question Papers & Official Answer Key Archive 2026";
            $pageDesc = "Official, authentic master question papers and verified answer keys for UPSC, SSC, Railways (RRB), Banking (IBPS), and Defence examinations.";
        }

        $items = [];
        foreach (array_slice($papers, 0, 10) as $idx => $p) {
            $items[] = [
                '@type' => 'LearningResource',
                'position' => $idx + 1,
                'name' => $p['paper_title'],
                'learningResourceType' => $p['file_type'] === 'answer_key' ? 'Answer Key' : 'Examination Paper',
                'educationalLevel' => 'Competitive Examination',
                'author' => [
                    '@type' => 'Organization',
                    'name' => $p['conducting_body']
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => SITE_NAME,
                    'url' => $baseUrl
                ],
                'url' => $baseUrl . '/download/paper/' . $p['id'] . '/'
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $pageName,
            'description' => $pageDesc,
            'url' => $pageUrl,
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => $baseUrl
            ],
            'hasPart' => $items
        ];
    }
}
