<?php
// This file should only be executed if the requested file doesn't exist
// Apache will serve PHP files directly if they exist

require_once __DIR__ . '/../app/Support/PathHelper.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = $uri ?: '/';

// If this is a direct file request (has .php extension), let Apache handle 404
if (preg_match('/\.php$/', $uri)) {
    // If we reach here, the file doesn't exist - let Apache return 404
    http_response_code(404);
    header('Content-Type: text/html');
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>";
    echo "<h1>404 Not Found</h1>";
    echo "<p>The requested file was not found on this server.</p>";
    echo "<p>Requested: " . htmlspecialchars($uri) . "</p>";
    echo "</body></html>";
    exit;
}

// Route API endpoints (non-file paths or routed endpoints)
if (strpos($uri, '/api/') === 0 || 
    strpos($uri, '/dbnew/api/') === 0 || 
    strpos($uri, '/public/api/') === 0 || 
    strpos($uri, '/dbnew/public/api/') === 0) {
    require __DIR__ . '/../routes/api.php';
    exit;
}

// Default redirect - if root access, go to landing page, otherwise dashboard
if ($uri === '/' || $uri === '') {
    header('Location: ' . PathHelper::url('landing.php'));
} else {
    header('Location: ' . PathHelper::url('dashboard.php'));
}
exit;
