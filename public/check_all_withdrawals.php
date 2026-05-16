<?php
session_start();
require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../app/Config/config.php';
$pdo = Database::pdo($config['db']);

if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

header('Content-Type: application/json');

$results = [];

// Check ALL Stellar withdrawals (all statuses)
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM stellar_withdraw GROUP BY status ORDER BY status");
$results['stellar']['all_statuses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent withdrawals (all statuses)
$stmt = $pdo->query("SELECT id, status, trustline, created_at, address, amount FROM stellar_withdraw ORDER BY created_at DESC LIMIT 20");
$results['stellar']['recent_all'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check ALL Binance withdrawals
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM binance_withdraw GROUP BY status ORDER BY status");
$results['binance']['all_statuses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, status, created_at, address, amount FROM binance_withdraw ORDER BY created_at DESC LIMIT 20");
$results['binance']['recent_all'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check ALL Ethereum withdrawals
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM ethereum_withdraw GROUP BY status ORDER BY status");
$results['ethereum']['all_statuses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, status, created_at, address, amount FROM ethereum_withdraw ORDER BY created_at DESC LIMIT 20");
$results['ethereum']['recent_all'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_PRETTY_PRINT);

