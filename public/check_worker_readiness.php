<?php
/**
 * Check if workers can find and process withdrawals
 * Requires admin authentication.
 */
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

try {
    // Check withdrawals with status 0, 1, or 8
    $stmt = $pdo->query("
        SELECT id, uid, address, amount, status, trustline, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_old
        FROM stellar_withdraw 
        WHERE status IN (0, 1, 8)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $results['stellar_eligible'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['stellar_count'] = count($results['stellar_eligible']);
    
    // Check Binance
    $stmt = $pdo->query("
        SELECT id, uid, address, amount, status, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_old
        FROM binance_withdraw 
        WHERE status IN (0, 1, 8)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $results['binance_eligible'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['binance_count'] = count($results['binance_eligible']);
    
    // Check Ethereum
    $stmt = $pdo->query("
        SELECT id, uid, address, amount, status, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_old
        FROM ethereum_withdraw 
        WHERE status IN (0, 1, 8)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $results['ethereum_eligible'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['ethereum_count'] = count($results['ethereum_eligible']);
    
    // Check recent completed withdrawals
    $stmt = $pdo->query("
        SELECT id, status, txn_hash_stellar, created_at
        FROM stellar_withdraw 
        WHERE status = 3 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $results['stellar_completed'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT id, status, txn_hash_bsc, created_at
        FROM binance_withdraw 
        WHERE status = 3 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $results['binance_completed'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT id, status, txn_hash_eth, created_at
        FROM ethereum_withdraw 
        WHERE status = 3 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $results['ethereum_completed'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary
    $results['summary'] = [
        'stellar_eligible' => $results['stellar_count'],
        'binance_eligible' => $results['binance_count'],
        'ethereum_eligible' => $results['ethereum_count'],
        'stellar_completed_recent' => count($results['stellar_completed']),
        'binance_completed_recent' => count($results['binance_completed']),
        'ethereum_completed_recent' => count($results['ethereum_completed'])
    ];
    
} catch (Exception $e) {
    error_log('check_worker_readiness error: ' . $e->getMessage());
    $results['error'] = 'An error occurred while checking worker readiness.';
}

echo json_encode($results, JSON_PRETTY_PRINT);

