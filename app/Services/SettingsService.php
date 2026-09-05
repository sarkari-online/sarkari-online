<?php
/**
 * EduPulse - Dynamic System Settings Service
 * Manages key-value configuration overrides in the 'settings' table.
 */

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use Throwable;

class SettingsService {

    private static array $cache = [];

    /**
     * Get a setting value with fallback
     */
    public static function get(string $key, mixed $default = null): mixed {
        try {
            $row = Database::fetchOne("SELECT value FROM settings WHERE `key` = :k LIMIT 1", ['k' => $key]);
            if ($row && isset($row['value'])) {
                self::$cache[$key] = $row['value'];
                return $row['value'];
            }
        } catch (Throwable $e) {
            Logger::error("Failed to read setting '{$key}': " . $e->getMessage());
        }

        return $default;
    }

    /**
     * Set / update a setting value atomically
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $desc = null): bool {
        self::$cache[$key] = $value;

        try {
            $strVal = is_array($value) ? json_encode($value) : (string)$value;
            Database::query(
                "INSERT INTO settings (`key`, `value`, `updated_at`) 
                 VALUES (:k, :v1, NOW())
                 ON DUPLICATE KEY UPDATE `value` = :v2, `updated_at` = NOW()",
                ['k' => $key, 'v1' => $strVal, 'v2' => $strVal]
            );
            return true;
        } catch (Throwable $e) {
            Logger::error("Failed to write setting '{$key}': " . $e->getMessage());
            return false;
        }
    }
}
