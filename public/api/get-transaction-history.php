<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../../app/Support/Database.php';
$config = require __DIR__ . '/../../app/Config/config.php';

if (!isset($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$uid = (int)$_SESSION['uid'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50; // Increased default limit to show more transactions

try {
    $pdo = Database::pdo($config['db']);
    $transactions = [];
    
    // Fetch deposits and withdrawals from all networks
    $stmt = $pdo->prepare("
        SELECT 
            'deposit' as type,
            'Deposit' as action,
            amount,
            NULL as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_stellar as txn_hash_network,
            txn_hash_yemchain,
            'in' as direction,
            id,
            NULL as address,
            'Stellar' as network
        FROM stellar_deposit 
        WHERE uid = ?
        
        UNION ALL
        
        SELECT 
            'withdraw' as type,
            'Withdrawal' as action,
            amount,
            COALESCE(fee_usdd, 0) as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_stellar as txn_hash_network,
            txn_hash_yemchain,
            'out' as direction,
            id,
            address,
            'Stellar' as network
        FROM stellar_withdraw 
        WHERE uid = ?
        
        UNION ALL
        
        SELECT 
            'deposit' as type,
            'Deposit' as action,
            amount,
            NULL as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_bsc as txn_hash_network,
            txn_hash_yemchain,
            'in' as direction,
            id,
            NULL as address,
            'Binance' as network
        FROM binance_deposit 
        WHERE uid = ?
        
        UNION ALL
        
        SELECT 
            'withdraw' as type,
            'Withdrawal' as action,
            amount,
            COALESCE(fee_usdd, 0) as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_bsc as txn_hash_network,
            txn_hash_yemchain,
            'out' as direction,
            id,
            address,
            'Binance' as network
        FROM binance_withdraw 
        WHERE uid = ?
        
        UNION ALL
        
        SELECT 
            'deposit' as type,
            'Deposit' as action,
            amount,
            NULL as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_eth as txn_hash_network,
            txn_hash_yemchain,
            'in' as direction,
            id,
            NULL as address,
            'Ethereum' as network
        FROM ethereum_deposit 
        WHERE uid = ?
        
        UNION ALL
        
        SELECT 
            'withdraw' as type,
            'Withdrawal' as action,
            amount,
            COALESCE(fee_usdd, 0) as fee_usdd,
            created_at as created_date,
            status,
            txn_hash_eth as txn_hash_network,
            txn_hash_yemchain,
            'out' as direction,
            id,
            address,
            'Ethereum' as network
        FROM ethereum_withdraw 
        WHERE uid = ?
        
        ORDER BY created_date DESC 
        LIMIT ?
    ");
    
    $stmt->execute([$uid, $uid, $uid, $uid, $uid, $uid, $limit * 2]); // Fetch extra to account for merging with reversals
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch reversals (refunds) for this user
    try {
        $stmtRev = $pdo->prepare("
            SELECT id, network, withdrawal_id, uid, dbv_amount as amount, usdd_amount, dbv_txn_hash as txn_hash_yemchain, usdd_txn_hash, status, created_at
            FROM reversals
            WHERE uid = ? AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmtRev->execute([$uid]);
        $reversals = $stmtRev->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reversals as $r) {
            $r['type'] = 'refund';
            $r['action'] = 'Refund';
            $r['created_date'] = $r['created_at'];
            $r['txn_hash_network'] = null; // Refunds use DigitalChain hashes only
            $r['direction'] = 'in';
            $r['address'] = null;
            $r['network'] = ucfirst(strtolower($r['network']));
            $transactions[] = $r;
        }
    } catch (Exception $e) {
        // reversals table may not exist
    }

    // Sort by date and limit
    usort($transactions, fn($a, $b) => strcmp($b['created_date'] ?? '', $a['created_date'] ?? ''));
    $transactions = array_slice($transactions, 0, $limit);
    
    // Log for debugging (remove in production)
    if (isset($_GET['debug'])) {
        error_log("Transaction query executed. UID: $uid, Found: " . count($transactions) . " transactions");
    }

    // Format transactions for display
    $formattedTransactions = array_map(function($txn) use ($uid) {
        // Status mapping: 0=pending, 1=processing, 2=failed, 3=completed, 8=pre-complete, 9=cancelled, 'completed'=reversal
        $statusText = 'Unknown';
        $statusColor = 'gray';
        $status = $txn['status'];
        $isRefund = ($txn['type'] ?? '') === 'refund';
        if ($status === 'completed' || $status === 'Completed') {
            $statusText = $isRefund ? 'Refunded' : 'Completed';
            $statusColor = 'green';
            $status = 3;
        } else {
            $status = (int)$status;
            switch ($status) {
                case 0: $statusText = 'Pending'; $statusColor = 'yellow'; break;
                case 1: $statusText = 'Processing'; $statusColor = 'blue'; break;
                case 2: $statusText = 'Failed'; $statusColor = 'red'; break;
                case 3: $statusText = 'Completed'; $statusColor = 'green'; break;
                case 8: $statusText = 'Pre-Complete'; $statusColor = 'blue'; break;
                case 9: $statusText = 'Cancelled'; $statusColor = 'red'; break;
            }
        }
        
        // Format amount with 2 decimals for all networks
        $decimals = 2;
        $amount = (float)($txn['amount'] ?? 0);
        
        // Format date safely
        $formattedTime = '';
        if (!empty($txn['created_date'])) {
            $date = strtotime($txn['created_date']);
            $formattedTime = $date !== false ? date('M j, H:i', $date) : '';
        }
        
        // Fee USDD: for withdrawals (fee charged), for refunds see usdd_amount
        $feeUsdd = null;
        if (($txn['type'] ?? '') === 'withdraw' || ($txn['type'] ?? '') === 'withdrawal') {
            $rawFee = $txn['fee_usdd'] ?? null;
            if ($rawFee !== null && $rawFee !== '' && (float)$rawFee > 0) {
                $feeUsdd = (float)$rawFee;
            }
        }
        
        return [
            'type' => $txn['type'] ?? 'deposit',
            'action' => $txn['action'] ?? 'Deposit',
            'amount' => $amount,
            'formatted_amount' => number_format($amount, $decimals),
            'usdd_amount' => isset($txn['usdd_amount']) ? (float)$txn['usdd_amount'] : null,
            'fee_usdd' => $feeUsdd,
            'status' => (int)$status,
            'status_text' => $statusText,
            'status_color' => $statusColor,
            'direction' => $txn['direction'] ?? 'in',
            'formatted_time' => $formattedTime,
            'created_date' => $txn['created_date'] ?? '',
            'txn_hash_stellar' => $txn['txn_hash_network'] ?? null,
            'txn_hash_yemchain' => $txn['txn_hash_yemchain'] ?? null,
            'network' => $txn['network'] ?? 'Stellar',
            'id' => isset($txn['id']) ? (int)$txn['id'] : null,
            'address' => $txn['address'] ?? null,
            'withdrawal_id' => $txn['withdrawal_id'] ?? null,
        ];
    }, $transactions);

    $response = [
        'success' => true,
        'transactions' => $formattedTransactions,
        'count' => count($formattedTransactions)
    ];
    
    // Remove any output before JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log('Transaction history error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.',
        'transactions' => []
    ]);
}

