<?php

/**
 * Standardized API response handler
 */
class Response
{
    public static function success(array $data = [], string $message = 'Success', int $code = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    public static function error(string $message, int $code = 400, array $errors = []): void
    {
        http_response_code($code);
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
    
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        if (!headers_sent()) {
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    public static function unauthorized(string $message = 'Not authenticated'): void
    {
        self::error($message, 401);
    }
    
    public static function forbidden(string $message = 'Access denied'): void
    {
        self::error($message, 403);
    }
    
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }
    
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors);
    }
}

