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
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

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
     * Set / update a setting value
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $desc = null): bool {
        self::$cache[$key] = $value;

        try {
            $existing = Database::fetchOne("SELECT id FROM settings WHERE `key` = :k LIMIT 1", ['k' => $key]);
            if ($existing) {
                Database::update('settings', [
                    'value' => (string)$value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $existing['id']]);
            } else {
                Database::insert('settings', [
                    'key' => $key,
                    'value' => (string)$value,
                    'type' => $type,
                    'description' => $desc,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            return true;
        } catch (Throwable $e) {
            Logger::error("Failed to write setting '{$key}': " . $e->getMessage());
            return false;
        }
    }
}
