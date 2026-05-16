<?php
/**
 * Admin endpoint to reverse failed withdrawals
 */

// Start output buffering to prevent any accidental output
ob_start();

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/ReverseService.php';
require_once __DIR__ . '/../../../app/Support/Logger.php';
require_once __DIR__ . '/../../../app/Support/Security.php';
require_once __DIR__ . '/../../../app/Support/AuditService.php';

$config = require __DIR__ . '/../../../app/Config/config.php';

// Clear any accidental output
ob_clean();

// Check admin access
require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = Database::pdo($config['db']);
    $reverseService = new ReverseService($pdo, $config);
    
    $network = $_POST['network'] ?? '';
    $withdrawalId = (int)($_POST['withdrawal_id'] ?? 0);
    
    if (!in_array($network, ['stellar', 'binance', 'ethereum'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid network']);
        exit;
    }
    
    if ($withdrawalId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit;
    }

    $refundUsddFee = true;
    if (array_key_exists('refund_usdd_fee', $_POST)) {
        $v = $_POST['refund_usdd_fee'];
        $refundUsddFee = !in_array(strtolower((string)$v), ['0', 'false', 'no', 'off'], true);
    }

    $reversalKind = isset($_POST['reversal_kind']) ? trim((string)$_POST['reversal_kind']) : 'failed';
    if ($reversalKind === 'blocked' || $reversalKind === 'blocked_address') {
        $result = $reverseService->reverseBlockedAddressWithdrawal($network, $withdrawalId, $refundUsddFee);
    } else {
        $result = $reverseService->reverseFailedWithdrawal($network, $withdrawalId, $refundUsddFee);
    }

    $adminUid = (int)$_SESSION['uid'];
    $audit = new AuditService($pdo);
    if ($result['success']) {
        $audit->log($adminUid, 'reversal', 'withdrawal', $withdrawalId, [
            'network' => $network,
            'reversal_kind' => $result['reversal_kind'] ?? $reversalKind,
            'refund_usdd_fee' => $refundUsddFee,
            'dbv_amount' => $result['dbv_amount'] ?? 0,
            'usdd_amount' => $result['usdd_amount'] ?? 0,
            'dbv_txn_hash' => $result['dbv_txn_hash'] ?? null,
            'usdd_txn_hash' => $result['usdd_txn_hash'] ?? null,
        ]);
    } else {
        $audit->log($adminUid, 'reversal_failed', 'withdrawal', $withdrawalId, [
            'network' => $network,
            'reversal_kind' => $reversalKind,
            'message' => $result['message'] ?? 'Unknown',
        ]);
    }

    echo json_encode($result);
    
} catch (Exception $e) {
    Logger::error('Reversal endpoint error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
