<?php
// Suppress warnings/notices to ensure clean JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../../../app/Config/config.php';
    require_once __DIR__ . '/../../../app/Controllers/DepositController.php';
    
    // Clear any buffered output
    ob_clean();
    
    (new DepositController($config))->postCreate();
} catch (Exception $e) {
    error_log('Stellar deposit error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
} catch (Error $e) {
    error_log('Fatal error in stellar/deposit: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}

// End output buffering
ob_end_flush();
