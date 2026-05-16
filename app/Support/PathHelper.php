<?php

/**
 * Path Helper - Detects base path for deployment flexibility
 * Works for both root domain and subdirectory deployments
 */
class PathHelper
{
    /**
     * Get the base path of the application
     * Detects if running from root or subdirectory (e.g., /dbnew/)
     * 
     * @return string Base path (empty string for root, '/dbnew' for subdirectory, etc.)
     */
    public static function getBasePath(): string
    {
        static $basePath = null;
        
        if ($basePath !== null) {
            return $basePath;
        }
        
        // Get the script name and request URI
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // If REQUEST_URI contains known subdirectory, we're in a subdirectory deployment
        if (preg_match('#^/(dbnew|dbv_work)/#', $requestUri, $m)) {
            $basePath = '/' . $m[1];
            return $basePath;
        }
        
        // Check script name for public/ directory path
        if (strpos($scriptName, '/public/') !== false) {
            // Extract the path before /public/
            $parts = explode('/public/', $scriptName);
            $basePath = $parts[0];
            // If basePath is empty or just '/', we're at root
            if ($basePath === '' || $basePath === '/') {
                $basePath = '';
            }
        } elseif (strpos($scriptName, '\\public\\') !== false) {
            // Windows path handling
            $parts = explode('\\public\\', $scriptName);
            $basePath = str_replace('\\', '/', $parts[0]);
            if ($basePath === '' || $basePath === '/') {
                $basePath = '';
            }
        } else {
            // Script is directly in public/ (DocumentRoot is set to public/)
            // Check if request URI suggests subdirectory
            if (preg_match('#^/(dbnew|dbv_work)/#', $requestUri, $m)) {
                $basePath = '/' . $m[1];
            } else {
                $basePath = '';
            }
        }
        
        return $basePath;
    }

    /**
     * True when the web server's document root is this project's public/ folder
     * (e.g. php -S localhost:8080 -t public). URLs must omit a /public/ segment.
     */
    public static function isPublicDocumentRoot(): bool
    {
        static $is = null;
        if ($is !== null) {
            return $is;
        }
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (strpos($scriptName, '/public/') !== false) {
            return $is = false;
        }
        $sf = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if ($sf === '') {
            return $is = false;
        }
        $real = realpath($sf);
        if ($real === false) {
            return $is = false;
        }
        $dir = str_replace('\\', '/', dirname($real));
        return $is = (bool)preg_match('#/public$#', $dir);
    }

    /**
     * Base URL path for HTTP API scripts under public/api (no trailing slash).
     */
    public static function getApiBasePath(): string
    {
        if (self::isPublicDocumentRoot()) {
            return self::url('api');
        }
        return self::url('public/api');
    }

    /**
     * URL path for a file inside public/ (e.g. js/dashboard.js, logout.php).
     */
    public static function publicAsset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (self::isPublicDocumentRoot()) {
            return self::url($path);
        }
        return self::url('public/' . $path);
    }
    
    /**
     * Get a URL path relative to the base
     * 
     * @param string $path Path relative to base (e.g., 'dashboard.php' or '/dashboard.php')
     * @return string Full path including base
     */
    public static function url(string $path): string
    {
        $base = self::getBasePath();
        $path = ltrim($path, '/');
        
        if ($base === '' || $base === '/') {
            return '/' . $path;
        }
        
        return rtrim($base, '/') . '/' . $path;
    }
}
