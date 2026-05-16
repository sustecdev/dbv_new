<?php
/**
 * Admin endpoint to mark a manual withdrawal as complete.
 * Verifies the tx hash on-chain (recipient + amount) before updating unless
 * ADMIN_ALLOW_SKIP_EVM_ONCHAIN_VERIFY is enabled and skip_onchain_verify is sent for BSC/Ethereum.
 */

ob_start();
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/Logger.php';
require_once __DIR__ . '/../../../app/Support/AuditService.php';
require_once __DIR__ . '/../../../app/Services/StellarService.php';
require_once __DIR__ . '/../../../app/Services/BinanceService.php';
require_once __DIR__ . '/../../../app/Services/EthereumService.php';

$config = require __DIR__ . '/../../../app/Config/config.php';
ob_clean();

require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
require_once __DIR__ . '/../../../app/Support/Security.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$csrfToken = trim($_POST['csrf_token'] ?? '');
if (!Security::verifyCsrfToken($csrfToken)) {
    Security::logSecurityEvent('admin_csrf_failed', ['action' => 'complete_manual_withdrawal', 'uid' => $_SESSION['uid'] ?? 0]);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$rateLimitKey = 'admin_complete_manual_' . ((int)($_SESSION['uid'] ?? 0));
if (!Security::checkRateLimit($rateLimitKey, 30, 60)) {
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait a moment before trying again.']);
    exit;
}

$network = trim($_POST['network'] ?? '');
$withdrawalId = (int)($_POST['withdrawal_id'] ?? 0);
$txnHash = trim($_POST['txn_hash'] ?? '');
$skipOnchainRequested = ($_POST['skip_onchain_verify'] ?? '') === '1' || ($_POST['skip_onchain_verify'] ?? '') === 'true';

if (!in_array($network, ['stellar', 'binance', 'ethereum'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid network']);
    exit;
}

$allowSkipEvmVerify = ($config['admin']['allow_skip_evm_onchain_verify'] ?? false) === true;
if ($skipOnchainRequested && !$allowSkipEvmVerify) {
    echo json_encode(['success' => false, 'message' => 'Skipping on-chain verification is not enabled. Set ADMIN_ALLOW_SKIP_EVM_ONCHAIN_VERIFY=true in server config if you need this option.']);
    exit;
}
if ($skipOnchainRequested && !in_array($network, ['binance', 'ethereum'], true)) {
    echo json_encode(['success' => false, 'message' => 'Skipping on-chain verification is only available for Binance and Ethereum.']);
    exit;
}

if ($withdrawalId <= 0 || $txnHash === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID or transaction hash']);
    exit;
}

try {
    $pdo = Database::pdo($config['db']);
    $audit = new AuditService($pdo);
    $adminUid = (int)$_SESSION['uid'];

    $tables = [
        'stellar' => ['table' => 'stellar_withdraw', 'hash_col' => 'txn_hash_stellar'],
        'binance' => ['table' => 'binance_withdraw', 'hash_col' => 'txn_hash_bsc'],
        'ethereum' => ['table' => 'ethereum_withdraw', 'hash_col' => 'txn_hash_eth'],
    ];
    $def = $tables[$network];
    $table = $def['table'];
    $hashCol = $def['hash_col'];

    $stmt = $pdo->prepare("SELECT id, uid, address, amount, status FROM $table WHERE id = ?");
    $stmt->execute([$withdrawalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit;
    }
    if ((int)$row['status'] !== 0) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal is not pending (status: ' . $row['status'] . ')']);
        exit;
    }

    $address = $row['address'];
    $amount = (float)$row['amount'];

    // Block contract/issuer addresses - do not allow admin to complete
    $blockedAddresses = $config['withdrawal']['blocked_addresses'] ?? [];
    if (Security::isBlockedWithdrawalAddress($address, $blockedAddresses)) {
        $audit->log($adminUid, 'manual_complete_blocked_address', 'withdrawal', $withdrawalId, [
            'network' => $network,
            'address_prefix' => substr($address, 0, 12) . '...',
        ]);
        Security::logSecurityEvent('admin_complete_blocked_address_attempted', ['admin_uid' => $adminUid, 'withdrawal_id' => $withdrawalId]);
        echo json_encode(['success' => false, 'message' => 'This withdrawal address is not allowed.']);
        exit;
    }

    // Normalize hash: Stellar = 64-char hex, BSC/ETH = 0x prefix
    $hash = $txnHash;
    if ($network === 'stellar') {
        $hash = preg_replace('/^0x/i', '', $hash);
        if (strlen($hash) !== 64 || !ctype_xdigit($hash)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Stellar transaction hash format']);
            exit;
        }
    } else {
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $hash)) {
            $hash = '0x' . preg_replace('/^0x/i', '', $hash);
            if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $hash)) {
                echo json_encode(['success' => false, 'message' => 'Invalid BSC/Ethereum transaction hash format']);
                exit;
            }
        }
    }

    // Verify on-chain (EVM skip path: hash format validated above only)
    $verified = false;
    $result = null;
    if ($network === 'stellar') {
        if ($skipOnchainRequested) {
            echo json_encode(['success' => false, 'message' => 'Skipping on-chain verification is only available for Binance and Ethereum.']);
            exit;
        }
        $stellar = new StellarService($config['stellar']);
        $lastError = null;
        $txnDetails = $stellar->fetchTransactionDetails($hash, 3, $lastError);
        if (!$txnDetails) {
            $net = $config['stellar']['network'] ?? 'testnet';
            $msg = 'Transaction not found on Stellar ' . $net . '.';
            if ($lastError) {
                $msg .= ' Connection error: ' . $lastError;
            } else {
                $msg .= ' Ensure HORIZON_NETWORK matches where the tx was sent, and retry after a few seconds.';
            }
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        if (!($txnDetails['successful'] ?? false)) {
            echo json_encode(['success' => false, 'message' => 'Transaction was not successful on Stellar network']);
            exit;
        }
        $ops = $stellar->fetchTxnOperations($hash);
        $payment = $stellar->findOutboundPayment($ops, $address, $amount, false);
        $verified = $payment !== null;
    } elseif (($network === 'binance' || $network === 'ethereum') && $skipOnchainRequested) {
        $verified = true;
    } elseif ($network === 'binance') {
        $bsc = new BinanceService($config['binance']);
        $result = $bsc->verifyWithdrawal($hash, $address, $amount, true);
        $verified = $result !== null;
    } else {
        $eth = new EthereumService($config['ethereum'] ?? []);
        $result = $eth->verifyWithdrawal($hash, $address, $amount, true);
        $verified = $result !== null;
    }

    if (!$verified) {
        $audit->log($adminUid, 'manual_complete_verify_failed', 'withdrawal', $withdrawalId, [
            'network' => $network,
            'hash' => substr($hash, 0, 16) . '...',
        ]);
        $msg = 'Verification failed: transaction does not match (wrong address or amount)';
        $debug = null;
        if ($network === 'stellar' && ($config['app']['debug'] ?? false) && isset($stellar, $ops)) {
            $debug = $stellar->getVerificationDebugInfo($ops, $address, $amount);
        }
        echo json_encode(array_filter([
            'success' => false,
            'message' => $msg,
            'debug' => $debug,
        ]));
        exit;
    }

    $senderAddress = null;
    if (($network === 'binance' || $network === 'ethereum') && is_array($result) && isset($result['from'])) {
        $senderAddress = strtolower(trim($result['from']));
    }

    $walletWhitelistEnabled = $config['admin']['wallet_whitelist_enabled'] ?? false;
    $walletWhitelist = $config['admin']['wallet_whitelist'] ?? [];
    if ($walletWhitelistEnabled && !empty($walletWhitelist) && $senderAddress) {
        if (!in_array($senderAddress, $walletWhitelist, true)) {
            $audit->log($adminUid, 'manual_complete_wallet_not_whitelisted', 'withdrawal', $withdrawalId, [
                'network' => $network,
                'sender' => substr($senderAddress, 0, 10) . '...',
            ]);
            echo json_encode(['success' => false, 'message' => 'This wallet address is not authorized to complete withdrawals.']);
            exit;
        }
    }

    if ($skipOnchainRequested && in_array($network, ['binance', 'ethereum'], true)) {
        $audit->log($adminUid, 'manual_complete_skip_onchain_verify', 'withdrawal', $withdrawalId, [
            'network' => $network,
            'hash_prefix' => substr($hash, 0, 18),
            'amount' => $amount,
        ]);
    }

    // Update to completed (clear error_message, set who processed it)
    $upd = $pdo->prepare("UPDATE $table SET status = 3, $hashCol = ?, error_message = NULL, processed_by_admin_uid = ? WHERE id = ?");
    $upd->execute([$hash, $adminUid, $withdrawalId]);

    $audit->log($adminUid, 'manual_complete', 'withdrawal', $withdrawalId, [
        'network' => $network,
        'hash' => $hash,
        'amount' => $amount,
    ]);

    echo json_encode(['success' => true, 'message' => 'Withdrawal marked complete']);
} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::error('Complete manual withdrawal error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
