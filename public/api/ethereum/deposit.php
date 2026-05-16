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

// Register shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in ethereum/deposit: {$error['message']} in {$error['file']}:{$error['line']}");
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
        ob_end_flush();
        exit;
    }
});

try {
    $config = require __DIR__ . '/../../../app/Config/config.php';
    require_once __DIR__ . '/../../../app/Controllers/EthereumDepositController.php';
    
    // Clear any buffered output
    ob_clean();
    
    (new EthereumDepositController($config))->postCreate();
} catch (ParseError $e) {
    error_log('Parse error in ethereum/deposit: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('Ethereum deposit error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Error $e) {
    error_log('Fatal error in ethereum/deposit: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Throwable $e) {
    error_log('Ethereum deposit error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}

// End output buffering
ob_end_flush();

