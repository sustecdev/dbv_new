<?php
// Secure worker endpoint: returns Ethereum withdrawals by status in last 24h
require_once __DIR__ . '/../app/Support/Security.php';

// Enhanced authentication with HMAC signature
$authHeader = $_SERVER['HTTP_X_WORKER_AUTH'] ?? '';
$signature = $_SERVER['HTTP_X_WORKER_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WORKER_TIMESTAMP'] ?? '';

require_once __DIR__ . '/../app/Support/Database.php';
$config = require __DIR__ . '/../app/Config/config.php';
if (empty($config['worker']['secret'])) {
    throw new Exception('WORKER_SECRET is not configured in .env file');
}
$secret = $config['worker']['secret'];

// Verify timestamp (prevent replay attacks - must be within 5 minutes)
if ($timestamp && abs(time() - (int)$timestamp) > 300) {
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

$pdo = Database::pdo($config['db']);

$status = isset($_REQUEST['status']) ? (int)$_REQUEST['status'] : 0;
$includeStuck = isset($_REQUEST['include_stuck']) ? (int)$_REQUEST['include_stuck'] : 0;

$createdAfter = date('Y-m-d H:i:s', time() - 24 * 3600);

// Worker only processes auto withdrawals (is_manual = 0). Manual withdrawals stay pending for admin.
$manualFilter = ' AND (is_manual = 0 OR is_manual IS NULL)';
// If status=0, also check for stuck status=8 withdrawals
if ($status === 0 && $includeStuck) {
    $stmt = $pdo->prepare('
        SELECT * FROM ethereum_withdraw 
        WHERE status IN (0, 1, 8)' . $manualFilter . '
        ORDER BY status ASC, created_at ASC
        LIMIT 0,10
    ');
    $stmt->execute();
} else {
    $stmt = $pdo->prepare('SELECT * FROM ethereum_withdraw WHERE status = :s' . $manualFilter . ' AND created_at > :c LIMIT 0,10');
    $stmt->execute(['s' => $status, 'c' => $createdAfter]);
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

header('Content-Type: application/json');
echo json_encode($rows ?: []);

