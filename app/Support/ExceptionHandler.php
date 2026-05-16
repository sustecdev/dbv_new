<?php

/**
 * Global exception handler
 */
class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    public static function handleException(Throwable $e): void
    {
        Logger::error('Uncaught exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        if (!headers_sent()) {
            http_response_code(500);
            
            // If it's an API request, return JSON
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                $config = require __DIR__ . '/../Config/config.php';
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => ($config['app']['debug'] ?? false)
                        ? $e->getMessage() 
                        : 'Internal server error'
                ]);
                exit;
            }
        }
        
        // For regular pages, show user-friendly error
        if (!headers_sent()) {
            die('An error occurred. Please try again later.');
        }
    }
    
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        Logger::error('PHP Error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);
        
        return true;
    }
    
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            Logger::error('Fatal error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }
}

// Register handlers
ExceptionHandler::register();

