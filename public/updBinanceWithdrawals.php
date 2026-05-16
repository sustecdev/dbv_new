<?php
// Secure endpoint to update Binance withdrawal status
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

// Verify HMAC (required - no bypass)
$dataToSign = $timestamp . ($_SERVER['REQUEST_URI'] ?? '');
if (!$timestamp || abs(time() - (int)$timestamp) > 300 || !Security::verifyWorkerSignature($dataToSign, $signature, $secret)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$pdo = Database::pdo($config['db']);

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$status = isset($_REQUEST['status']) ? (int)$_REQUEST['status'] : 0;
$hash = isset($_REQUEST['hash']) ? trim($_REQUEST['hash']) : '';
$errorMessage = isset($_REQUEST['error']) ? substr(trim((string)$_REQUEST['error']), 0, 500) : null;
$hashOnly = isset($_REQUEST['hash_only']) && $_REQUEST['hash_only'] === '1';

if ($id <= 0 || $status < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// hash_only: persist tx hash without changing status (prevents double-send on retry)
if ($hashOnly && $hash) {
    $stmt = $pdo->prepare('UPDATE binance_withdraw SET txn_hash_bsc = :h, error_message = NULL WHERE id = :id');
    $result = $stmt->execute(['h' => $hash, 'id' => $id]);
} elseif ($hash) {
    $stmt = $pdo->prepare('UPDATE binance_withdraw SET status = :s, txn_hash_bsc = :h, error_message = NULL WHERE id = :id');
    $result = $stmt->execute(['s' => $status ?: 3, 'h' => $hash, 'id' => $id]);
} elseif ($status === 2) {
    $stmt = $pdo->prepare('UPDATE binance_withdraw SET status = 2, error_message = :e WHERE id = :id');
    $result = $stmt->execute(['e' => $errorMessage ?: null, 'id' => $id]);
} else {
    $stmt = $pdo->prepare('UPDATE binance_withdraw SET status = :s, error_message = NULL WHERE id = :id AND status != :s2');
    $result = $stmt->execute(['s' => $status, 's2' => $status, 'id' => $id]);
}

header('Content-Type: application/json');
echo json_encode(['success' => $result, 'rows' => $stmt->rowCount()]);

