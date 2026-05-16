<?php
// Load configuration first to check debug mode
$config = require __DIR__ . '/../app/Config/config.php';

// Set error reporting based on config
$debugMode = $config['app']['debug'] ?? false;
if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

// Suppress output before headers to prevent "headers already sent" errors
ob_start();

// Set error handler to catch fatal errors (never expose details to user)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
        ob_end_clean();
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<h1>An error occurred</h1>';
        echo '<p>Please try again later or contact support if the problem persists.</p>';
        echo '<p><a href="safezone.php">← Return to Login</a></p>';
        echo '</body></html>';
    }
});

try {
    
    // Validate database configuration before proceeding
    if (empty($config['db']['host']) || empty($config['db']['name'])) {
        throw new Exception('Database configuration is incomplete. Please check your .env file.');
    }
    
    require_once __DIR__ . '/../app/Controllers/DashboardController.php';
    ob_end_clean(); // Clear any output before headers
    (new DashboardController($config))->show();
} catch (Throwable $e) {
    ob_end_clean(); // Clear any output before headers

    // Log full error server-side only (never expose to user)
    error_log("Dashboard error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Never expose raw errors, stack traces, or file paths to users
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error - Dashboard</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .error-box { background: white; border: 2px solid #dc2626; border-radius: 8px; padding: 20px; max-width: 600px; margin: 50px auto; }
            .error-title { color: #dc2626; font-size: 24px; margin-bottom: 10px; }
            .error-message { color: #333; font-size: 16px; margin: 10px 0; }
            .back-link { margin-top: 20px; }
            .back-link a { color: #2563eb; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1 class="error-title">Error Loading Dashboard</h1>
            <p class="error-message">An unexpected error occurred. Please try again later or contact support if the problem persists.</p>
            <div class="back-link">
                <a href="safezone.php">← Return to Login</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
