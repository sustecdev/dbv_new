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

// Check Stellar withdrawals
$stmt = $pdo->query("SELECT id, status, trustline, created_at, address, amount FROM stellar_withdraw WHERE status IN (0, 8) ORDER BY created_at DESC LIMIT 10");
$results['stellar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check Binance withdrawals
$stmt = $pdo->query("SELECT id, status, created_at, address, amount FROM binance_withdraw WHERE status IN (0, 8) ORDER BY created_at DESC LIMIT 10");
$results['binance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check Ethereum withdrawals
$stmt = $pdo->query("SELECT id, status, created_at, address, amount FROM ethereum_withdraw WHERE status IN (0, 8) ORDER BY created_at DESC LIMIT 10");
$results['ethereum'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count totals
$stmt = $pdo->query("SELECT COUNT(*) as count FROM stellar_withdraw WHERE status = 0");
$results['counts']['stellar_0'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM stellar_withdraw WHERE status = 8");
$results['counts']['stellar_8'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM binance_withdraw WHERE status = 0");
$results['counts']['binance_0'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM binance_withdraw WHERE status = 8");
$results['counts']['binance_8'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM ethereum_withdraw WHERE status = 0");
$results['counts']['ethereum_0'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM ethereum_withdraw WHERE status = 8");
$results['counts']['ethereum_8'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode($results, JSON_PRETTY_PRINT);

