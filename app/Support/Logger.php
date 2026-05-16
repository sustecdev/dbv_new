<?php

/**
 * Centralized logging system
 */
class Logger
{
    private static string $logDir;
    
    public static function init(?string $logDir = null): void
    {
        self::$logDir = $logDir ?? __DIR__ . '/../../logs';
        if (!is_dir(self::$logDir)) {
            // Try to create directory, suppress warning if permission denied
            @mkdir(self::$logDir, 0755, true);
        }
    }
    
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
    
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }
    
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
    
    public static function debug(string $message, array $context = []): void
    {
        $config = require_once __DIR__ . '/../Config/config.php';
        if ($config['app']['debug'] ?? false) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    private static function log(string $level, string $message, array $context = []): void
    {
        if (empty(self::$logDir)) {
            self::init();
        }
        
        $logFile = self::$logDir . '/app-' . date('Y-m-d') . '.log';
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'cli'
        ];
        
        // Try to write log, but don't fail if permissions are denied
        @file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log if file logging fails
        if (!is_writable($logFile)) {
            error_log("Logger: Cannot write to {$logFile} - check permissions");
        }
    }
}

// Initialize on load
Logger::init();

