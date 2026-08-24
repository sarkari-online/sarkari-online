<?php
/**
 * EduPulse - Application Logger
 * Logs messages with timestamp, severity level, context and trace to storage/logs/
 */

namespace App\Helpers;

class Logger {
    private static ?string $logFile = null;

    private static function init(): void {
        if (self::$logFile === null) {
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            self::$logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
        }
    }

    public static function log(string $level, string $message, array $context = []): void {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logLine = sprintf("[%s] [%s] %s%s%s", $timestamp, strtoupper($level), $message, $contextStr, PHP_EOL);

        if (!file_exists(self::$logFile)) {
            @touch(self::$logFile);
            @chmod(self::$logFile, 0666);
        }

        @file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log('CRITICAL', $message, $context);
    }

    public static function debug(string $message, array $context = []): void {
        if (Env::get('APP_DEBUG', false)) {
            self::log('DEBUG', $message, $context);
        }
    }
}
