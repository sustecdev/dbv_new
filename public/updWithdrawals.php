<?php
// Secure worker endpoint with HMAC authentication
require_once __DIR__ . '/../app/Support/Security.php';

$authHeader = $_SERVER['HTTP_X_WORKER_AUTH'] ?? '';
$signature = $_SERVER['HTTP_X_WORKER_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WORKER_TIMESTAMP'] ?? '';

require_once __DIR__ . '/../app/Support/Database.php';
$config = require __DIR__ . '/../app/Config/config.php';
if (empty($config['worker']['secret'])) {
    throw new Exception('WORKER_SECRET is not configured in .env file');
}
$secret = $config['worker']['secret'];

// Verify timestamp (prevent replay attacks)
if ($timestamp && abs(time() - (int)$timestamp) > 300) {
    Security::logSecurityEvent('worker_auth_timeout', ['ip' => Security::getClientIp()]);
    http_response_code(403);
    echo 'ERROR: Request expired';
    exit;
}

// Verify HMAC (required - no bypass)
$dataToSign = $timestamp . ($_SERVER['REQUEST_URI'] ?? '');
if (!$timestamp || abs(time() - (int)$timestamp) > 300 || !Security::verifyWorkerSignature($dataToSign, $signature, $secret)) {
    Security::logSecurityEvent('worker_auth_failed', ['ip' => Security::getClientIp()]);
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$pdo = Database::pdo($config['db']);

$trustlineOrStatus = $_REQUEST['trustlineOrStatus'] ?? '';
$idsString = trim((string)($_REQUEST['ids'] ?? ''));
$status = isset($_REQUEST['status']) ? (int)$_REQUEST['status'] : 0;
$hash = trim((string)($_REQUEST['hash'] ?? ''));
$errorMessage = isset($_REQUEST['error']) ? substr(trim((string)$_REQUEST['error']), 0, 500) : null;
$hashOnly = isset($_REQUEST['hash_only']) && $_REQUEST['hash_only'] === '1';

if ($idsString === '') {
    echo 'OK';
    exit;
}
$ids = array_values(array_filter(array_map('intval', explode(',', $idsString)), fn($v) => $v > 0));
if (empty($ids)) {
    echo 'ERROR: No valid IDs provided for update.';
    exit;
}

try {
    if ($trustlineOrStatus === 'trustline') {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE stellar_withdraw SET trustline = 1 WHERE id IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
    } else {
        if ($hashOnly && $hash !== '') {
            $stmt = $pdo->prepare('UPDATE stellar_withdraw SET txn_hash_stellar = :h, error_message = NULL WHERE id = :i');
            $stmt->execute(['h' => $hash, 'i' => $ids[0]]);
        } elseif ($status === 3 && $hash !== '') {
            $stmt = $pdo->prepare('UPDATE stellar_withdraw SET status = 3, txn_hash_stellar = :h, error_message = NULL WHERE id = :i');
            $stmt->execute(['h' => $hash, 'i' => $ids[0]]);
        } elseif ($status === 2) {
            $stmt = $pdo->prepare('UPDATE stellar_withdraw SET status = 2, error_message = :e WHERE id = :i');
            $stmt->execute(['e' => $errorMessage ?: null, 'i' => $ids[0]]);
        } else {
            $stmt = $pdo->prepare('UPDATE stellar_withdraw SET status = :s, error_message = NULL WHERE id = :i');
            $stmt->execute(['s' => $status, 'i' => $ids[0]]);
        }
    }
    echo 'OK';
} catch (Throwable $e) {
    error_log('updWithdrawals error: ' . $e->getMessage());
    http_response_code(500);
    echo 'ERROR: An error occurred. Please try again later.';
}
