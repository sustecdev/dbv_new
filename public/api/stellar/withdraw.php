<?php
// Suppress warnings/notices to ensure clean JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header immediately
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Register shutdown handler to catch fatal errors (do not expose file/path)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in stellar/withdraw: {$error['message']} in {$error['file']}:{$error['line']}");
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        ob_end_flush();
        exit;
    }
});

try {
    // Debug: Log request method (remove in production)
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    
    $config = require __DIR__ . '/../../../app/Config/config.php';
    require_once __DIR__ . '/../../../app/Controllers/WithdrawController.php';
    
    // Clear any buffered output
    ob_clean();
    
    (new WithdrawController($config))->postCreate();
} catch (ParseError $e) {
    error_log('Parse error in stellar/withdraw: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('Stellar withdraw error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Error $e) {
    error_log('Fatal error in stellar/withdraw: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Throwable $e) {
    error_log('Stellar withdraw error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}

// End output buffering
ob_end_flush();
