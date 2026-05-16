<?php
session_start();
require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../app/Config/config.php';
$pdo = Database::pdo($config['db']);

if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
$uid = (int)$_SESSION['uid'];

$stmt = $pdo->prepare('SELECT id, uid, address, amount, trustline, status, txn_hash_yemchain, txn_hash_stellar, created_at FROM stellar_withdraw WHERE uid = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$uid]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'uid' => $uid,
    'withdrawals' => $withdrawals,
    'status_codes' => [
        '0' => 'Pending',
        '1' => 'Processing',
        '2' => 'Failed',
        '3' => 'Completed',
        '8' => 'Pre-complete',
        '9' => 'Cancelled'
    ]
], JSON_PRETTY_PRINT);

