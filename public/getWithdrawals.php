<?php
// Secure worker endpoint: returns withdrawals by status and trustline
// Optimized for speed - minimal overhead

// Worker auth: HMAC signature required (no bypass - X-X_CUSTOM_HEADER was insecure)
require_once __DIR__ . '/../app/Support/Security.php';
$signature = $_SERVER['HTTP_X_WORKER_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WORKER_TIMESTAMP'] ?? '';
$config = require __DIR__ . '/../app/Config/config.php';
if (empty($config['worker']['secret'])) {
    throw new Exception('WORKER_SECRET is not configured in .env file');
}
$secret = $config['worker']['secret'];

// Verify timestamp (prevent replay attacks - must be within 5 minutes)
if (!$timestamp || abs(time() - (int)$timestamp) > 300) {
    Security::logSecurityEvent('worker_auth_timeout', ['ip' => Security::getClientIp()]);
    http_response_code(403);
    echo json_encode(['error' => 'Request expired']);
    exit;
}

// Verify HMAC signature (required - no header bypass)
$dataToSign = $timestamp . ($_SERVER['REQUEST_URI'] ?? '');
if (!Security::verifyWorkerSignature($dataToSign, $signature, $secret)) {
    Security::logSecurityEvent('worker_auth_failed', ['ip' => Security::getClientIp()]);
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Load config and database
require_once __DIR__ . '/../app/Support/Database.php';
$config = require __DIR__ . '/../app/Config/config.php';

try {
    $pdo = Database::pdo($config['db']);
    
    $status = isset($_REQUEST['status']) ? (int)$_REQUEST['status'] : 0;
    $trustline = isset($_REQUEST['trustline']) ? (int)$_REQUEST['trustline'] : 0;
    $includeStuck = isset($_REQUEST['include_stuck']) ? (int)$_REQUEST['include_stuck'] : 0;
    
    // Worker only processes auto withdrawals (is_manual = 0). Manual withdrawals stay pending for admin.
    $manualFilter = ' AND (is_manual = 0 OR is_manual IS NULL)';
    // If status=0, also check for stuck status=8 withdrawals
    if ($status === 0 && $includeStuck) {
        // Include status=0 (pending), status=1 (processing), and status=8 (pre-complete)
        $stmt = $pdo->prepare('
            SELECT * FROM stellar_withdraw 
            WHERE status IN (0, 1, 8)' . $manualFilter . '
            ORDER BY status ASC, created_at ASC
            LIMIT 10
        ');
        $stmt->execute();
    } else {
        $createdAfter = date('Y-m-d H:i:s', time() - 24 * 3600);
        $stmt = $pdo->prepare('SELECT * FROM stellar_withdraw WHERE status = :s AND trustline = :t' . $manualFilter . ' AND created_at > :c LIMIT 10');
        $stmt->execute(['s' => $status, 't' => $trustline, 'c' => $createdAfter]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter out blocked addresses (contract/issuer) - defense in depth
    $blockedAddresses = $config['withdrawal']['blocked_addresses'] ?? [];
    if (!empty($blockedAddresses)) {
        require_once __DIR__ . '/../app/Support/Security.php';
        $rows = array_values(array_filter($rows, function ($r) use ($blockedAddresses) {
            $addr = $r['address'] ?? '';
            return $addr === '' || !Security::isBlockedWithdrawalAddress($addr, $blockedAddresses);
        }));
    }
    
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/json', true);
    
    // Output JSON
    echo json_encode($rows);
    exit;
    
} catch (Exception $e) {
    // Log error to file for debugging
    @file_put_contents(__DIR__ . '/../logs/getWithdrawals_error.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    
    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json', true);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
    exit;
}
