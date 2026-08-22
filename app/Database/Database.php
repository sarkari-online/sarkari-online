<?php
/**
 * EduPulse - Database Abstraction Layer (PDO)
 * Singleton PDO instance with prepared statement helpers and transaction support.
 */

namespace App\Database;

use PDO;
use PDOException;
use PDOStatement;
use App\Helpers\Env;
use App\Helpers\Logger;

class Database {
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection instance
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $driver = Env::get('DB_CONNECTION', 'mysql');
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', 8889);
            $dbname = Env::get('DB_DATABASE', 'automation');
            $username = Env::get('DB_USERNAME', 'root');
            $password = Env::get('DB_PASSWORD', 'root');
            $charset = Env::get('DB_CHARSET', 'utf8mb4');

            $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // If database doesn't exist yet, attempt creation
                if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() === 1049) {
                    try {
                        $tempDsn = "{$driver}:host={$host};port={$port};charset={$charset}";
                        $tempPdo = new PDO($tempDsn, $username, $password, $options);
                        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        self::$instance = new PDO($dsn, $username, $password, $options);
                    } catch (PDOException $createEx) {
                        Logger::critical('Database creation failed', ['error' => $createEx->getMessage()]);
                        throw $createEx;
                    }
                } else {
                    Logger::critical('Database connection failed', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }

        return self::$instance;
    }

    /**
     * Execute a parameterized query and return the statement
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $pdo = self::getConnection();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            Logger::error('SQL Query Error', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch a single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single scalar column value
     */
    public static function fetchColumn(string $sql, array $params = [], int $columnIndex = 0): mixed {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn($columnIndex);
    }

    /**
     * Alias for fetchColumn
     */
    public static function fetchValue(string $sql, array $params = [], int $columnIndex = 0): mixed {
        return self::fetchColumn($sql, $params, $columnIndex);
    }

    /**
     * Insert a record and return the last inserted ID
     */
    public static function insert(string $table, array $data): int|string {
        $columns = array_keys($data);
        $fields = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$placeholders})";
        self::query($sql, $data);

        return self::getConnection()->lastInsertId();
    }

    /**
     * Update records matching conditions
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "`{$column}` = :set_{$column}";
            $params["set_{$column}"] = $value;
        }

        $setString = implode(', ', $setClauses);
        $sql = "UPDATE `{$table}` SET {$setString} WHERE {$where}";

        $mergedParams = array_merge($params, $whereParams);
        $stmt = self::query($sql, $mergedParams);

        return $stmt->rowCount();
    }

    /**
     * Delete records matching conditions
     */
    public static function delete(string $table, string $where, array $whereParams = []): int {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $stmt = self::query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Transaction wrapper
     */
    public static function transaction(callable $callback): mixed {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('Transaction Rolled Back', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
