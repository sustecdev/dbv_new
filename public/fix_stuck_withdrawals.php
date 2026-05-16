<?php
/**
 * Fix Stuck Withdrawals Script
 * Resets withdrawals stuck in processing (status=8) back to pending (status=0)
 * if they've been stuck for more than 10 minutes
 * Requires admin session.
 */
session_start();
require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/AdminHelper.php';

$config = require __DIR__ . '/../app/Config/config.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}
require_once __DIR__ . '/../app/Support/Security.php';

try {
    // Find withdrawals stuck in processing (status=8) for more than 10 minutes
    $timeoutMinutes = 10;
    $cutoffTime = date('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));
    
    // Stellar withdrawals
    $stmt = $pdo->prepare("
        SELECT id, uid, address, amount, status, created_at, 
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_stuck
        FROM stellar_withdraw 
        WHERE status = 8 
        AND created_at < ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$cutoffTime]);
    $stuckStellar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Binance withdrawals
    $stmt = $pdo->prepare("
        SELECT id, uid, address, amount, status, created_at, 
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_stuck
        FROM binance_withdraw 
        WHERE status = 8 
        AND created_at < ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$cutoffTime]);
    $stuckBinance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ethereum withdrawals
    $stmt = $pdo->prepare("
        SELECT id, uid, address, amount, status, created_at, 
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_stuck
        FROM ethereum_withdraw 
        WHERE status = 8 
        AND created_at < ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$cutoffTime]);
    $stuckEthereum = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalStuck = count($stuckStellar) + count($stuckBinance) + count($stuckEthereum);
    
    header('Content-Type: application/json');
    
    // If no stuck withdrawals, return success
    if ($totalStuck === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No stuck withdrawals found',
            'stuck_count' => 0
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Reset stuck withdrawals back to pending (status=0)
    $reset = isset($_GET['reset']) && $_GET['reset'] === '1';
    
    if (!$reset) {
        // Show stuck withdrawals without resetting
        echo json_encode([
            'success' => true,
            'message' => 'Found stuck withdrawals. Add ?reset=1 to reset them.',
            'stuck_count' => $totalStuck,
            'stellar' => [
                'count' => count($stuckStellar),
                'withdrawals' => $stuckStellar
            ],
            'binance' => [
                'count' => count($stuckBinance),
                'withdrawals' => $stuckBinance
            ],
            'ethereum' => [
                'count' => count($stuckEthereum),
                'withdrawals' => $stuckEthereum
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Reset stuck withdrawals
    $resetCount = 0;
    
    // Reset Stellar
    if (count($stuckStellar) > 0) {
        $ids = array_column($stuckStellar, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE stellar_withdraw SET status = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $resetCount += $stmt->rowCount();
        
        // Log the reset
        foreach ($stuckStellar as $w) {
            Security::logSecurityEvent('withdrawal_reset_stuck', [
                'network' => 'stellar',
                'withdrawal_id' => $w['id'],
                'uid' => $w['uid'],
                'minutes_stuck' => $w['minutes_stuck']
            ]);
        }
    }
    
    // Reset Binance
    if (count($stuckBinance) > 0) {
        $ids = array_column($stuckBinance, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE binance_withdraw SET status = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $resetCount += $stmt->rowCount();
        
        foreach ($stuckBinance as $w) {
            Security::logSecurityEvent('withdrawal_reset_stuck', [
                'network' => 'binance',
                'withdrawal_id' => $w['id'],
                'uid' => $w['uid'],
                'minutes_stuck' => $w['minutes_stuck']
            ]);
        }
    }
    
    // Reset Ethereum
    if (count($stuckEthereum) > 0) {
        $ids = array_column($stuckEthereum, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE ethereum_withdraw SET status = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $resetCount += $stmt->rowCount();
        
        foreach ($stuckEthereum as $w) {
            Security::logSecurityEvent('withdrawal_reset_stuck', [
                'network' => 'ethereum',
                'withdrawal_id' => $w['id'],
                'uid' => $w['uid'],
                'minutes_stuck' => $w['minutes_stuck']
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Reset $resetCount stuck withdrawal(s) back to pending status",
        'reset_count' => $resetCount,
        'stellar_count' => count($stuckStellar),
        'binance_count' => count($stuckBinance),
        'ethereum_count' => count($stuckEthereum)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log('fix_stuck_withdrawals error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred. Please try again later.'
    ], JSON_PRETTY_PRINT);
}

