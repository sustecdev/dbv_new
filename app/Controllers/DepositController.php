<?php

require_once __DIR__ . '/../Support/Database.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/Response.php';
require_once __DIR__ . '/../Support/Validator.php';
require_once __DIR__ . '/../Services/StellarService.php';
require_once __DIR__ . '/../Services/YEMChainService.php';
require_once __DIR__ . '/../Services/SafeIdentService.php';

class DepositController
{
    private PDO $pdo;
    private StellarService $stellar;
    private YEMChainService $yem;
    private array $cfg;

    public function __construct(array $cfg)
    {
        // Start session first if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set JSON header
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        $this->cfg = $cfg;
        $this->pdo = Database::pdo($cfg['db']);
        $this->stellar = new StellarService($cfg['stellar']);
        $this->yem = new YEMChainService($cfg['yemchain']['base'], $cfg['yemchain']['key']);
    }

    public function postCreate(): void
    {
        Security::configureSecureSession();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }
        
        if (!isset($_SESSION['uid'])) {
            Security::logSecurityEvent('deposit_unauthorized', ['ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        // Rate limiting
        $rateLimitKey = 'deposit_' . $_SESSION['uid'];
        if (!Security::checkRateLimit($rateLimitKey, 20, 60)) {
            Security::logSecurityEvent('deposit_rate_limit', ['uid' => $_SESSION['uid'], 'ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait before trying again.']);
            return;
        }
        
        // CSRF protection
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            Security::logSecurityEvent('deposit_csrf_failed', ['uid' => $_SESSION['uid'], 'ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Security token invalid']);
            return;
        }
        
        $uid = (int)$_SESSION['uid'];
        
        // SafeIdent verification check
        $safeident = new SafeIdentService($this->cfg['safeident']);
        $canTransact = $safeident->canUserTransact($uid);
        if (!$canTransact['allowed']) {
            Security::logSecurityEvent('deposit_verification_failed', [
                'uid' => $uid,
                'reason' => $canTransact['reason'] ?? 'unknown'
            ]);
            echo json_encode(['success' => false, 'message' => $canTransact['message']]);
            return;
        }
        $txnHash = trim($_POST['txn_hash'] ?? '');
        
        // Input validation using Validator
        $validationErrors = Validator::validateDeposit(['txn_hash' => $txnHash]);
        
        if (!empty($validationErrors)) {
            Logger::warning('Deposit validation failed', ['uid' => $uid, 'errors' => $validationErrors]);
            Response::validationError($validationErrors);
        }

        // Duplicate check
        $stmt = $this->pdo->prepare('SELECT id FROM stellar_deposit WHERE txn_hash_stellar = :h');
        $stmt->execute(['h' => $txnHash]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Transaction already recorded']);
            return;
        }

        // Fetch transaction details and operations
        $txnDetails = $this->stellar->fetchTransactionDetails($txnHash);
        if (!$txnDetails) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found on Stellar network']);
            return;
        }
        
        // Verify transaction was successful
        if (!($txnDetails['successful'] ?? false)) {
            echo json_encode(['success' => false, 'message' => 'Transaction was not successful on Stellar network']);
            return;
        }
        
        // Fetch operations
        $ops = $this->stellar->fetchTxnOperations($txnHash);
        if (empty($ops)) {
            echo json_encode(['success' => false, 'message' => 'No operations found in transaction']);
            return;
        }
        
        // Find inbound payment
        $rec = $this->stellar->findInboundPayment($ops);
        if (!$rec) {
            // Extract operation details for debugging
            $opDetails = [];
            $isClaimableBalance = false;
            foreach ($ops as $op) {
                $opType = $op['type'] ?? $op['type_i'] ?? 'unknown';
                if ($opType === 'create_claimable_balance' || (isset($op['type_i']) && $op['type_i'] == 14)) {
                    $isClaimableBalance = true;
                }
                $opDetails[] = [
                    'type' => $opType,
                    'type_i' => $op['type_i'] ?? null,
                    'asset_type' => $op['asset_type'] ?? null,
                    'asset_code' => $op['asset_code'] ?? null,
                    'asset_issuer' => $op['asset_issuer'] ?? null,
                    'from' => $op['from'] ?? $op['source_account'] ?? null,
                    'to' => $op['to'] ?? $op['destination'] ?? null,
                    'amount' => $op['amount'] ?? null,
                    'claimants' => $op['claimants'] ?? null,
                ];
            }
            
            // Log operations for debugging
            Logger::warning('Deposit payment validation failed', [
                'uid' => $uid,
                'txn_hash' => $txnHash,
                'ops_count' => count($ops),
                'expected_asset' => $this->cfg['stellar']['asset_code'],
                'expected_issuer' => $this->cfg['stellar']['issuer'],
                'expected_vault' => $this->cfg['stellar']['vault'],
                'operations' => $opDetails
            ]);
            
            // Build detailed error message
            $errorMsg = 'Valid payment not found. ';
            
            if ($isClaimableBalance) {
                $errorMsg .= 'You sent a claimable balance. ';
                $errorMsg .= 'For deposits, please send a direct payment (not a claimable balance) to the vault address. ';
                $errorMsg .= 'The vault address is: ' . $this->cfg['stellar']['vault'];
            } else {
                $errorMsg .= 'Expected: Asset=' . $this->cfg['stellar']['asset_code'] . ', ';
                $errorMsg .= 'Issuer=' . substr($this->cfg['stellar']['issuer'], 0, 8) . '..., ';
                $errorMsg .= 'To=' . $this->cfg['stellar']['vault'] . '. ';
                
                if (count($opDetails) > 0) {
                    $errorMsg .= 'Found ' . count($opDetails) . ' operation(s). ';
                    $firstOp = $opDetails[0];
                    $errorMsg .= 'First operation: Type=' . ($firstOp['type'] ?? 'N/A') . ', ';
                    $errorMsg .= 'AssetType=' . ($firstOp['asset_type'] ?? 'N/A') . ', ';
                    $errorMsg .= 'AssetCode=' . ($firstOp['asset_code'] ?? 'N/A') . ', ';
                    $errorMsg .= 'AssetIssuer=' . ($firstOp['asset_issuer'] ? substr($firstOp['asset_issuer'], 0, 8) . '...' : 'N/A') . ', ';
                    $errorMsg .= 'From=' . ($firstOp['from'] ? substr($firstOp['from'], 0, 8) . '...' : 'N/A') . ', ';
                    $errorMsg .= 'To=' . ($firstOp['to'] ? substr($firstOp['to'], 0, 8) . '...' : 'N/A');
                }
            }
            
            echo json_encode([
                'success' => false, 
                'message' => $errorMsg
            ]);
            return;
        }

        $amount = round((float)$rec['amount'], 2);
        $valueUSD = round($amount * 0.01, 2);
        $currencyTo = $_SESSION['currencycode'] ?? 'USD';
        $reason = 'Bridge: Transfer from ' . ucfirst($this->cfg['stellar']['network']) . ' Stellar Network';

        // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
        $yemchainBypass = $this->cfg['yemchain_bypass'] ?? false;
        if ($yemchainBypass) {
            // Generate fake YEMChain hash for testing
            $yemHash = 'BYPASS_' . strtoupper(substr(md5($txnHash . $uid . time()), 0, 32));
            Logger::warning('YEMChain bypassed for Stellar deposit', ['uid' => $uid, 'txn_hash' => $txnHash, 'fake_hash' => $yemHash]);
        } else {
            // Voucher: vault -> user
            $resp = $this->yem->createVoucher([
                'network' => $this->cfg['stellar']['network'],
                'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                'accountTo' => $uid,
                'asset' => 'DBV',
                'txnAmount' => $amount,
                'valueUSD' => $valueUSD,
                'currencyCodeFrom' => 'USD',
                'currencyCodeTo' => $currencyTo,
                'reason' => $reason,
            ]);
            
            // Handle YEMChain API response
            if (($resp['status'] ?? 'error') !== 'success') {
                $errorMsg = $resp['message'] ?? 'Unknown error';
                echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $errorMsg]);
                return;
            }

            $yemHash = $resp['txnID'] ?? '';
            if (empty($yemHash)) {
                echo json_encode(['success' => false, 'message' => 'YEMChain transaction ID not received']);
                return;
            }
        }

        // Record deposit (round amount to 2 decimals)
        $amount = round($amount, 2);
        $ins = $this->pdo->prepare('INSERT INTO stellar_deposit (uid, txn_hash_stellar, txn_hash_yemchain, amount, status, created_at) VALUES (?, ?, ?, ?, 3, NOW())');
        $ins->execute([$uid, $txnHash, $yemHash, $amount]);
        if ($ins->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Failed to record deposit']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Deposit successful. DBV has been added to your account.',
            'txn_hash' => $txnHash,
            'amount' => number_format($amount, 2, '.', ''),
            'yemchain_hash' => $yemHash,
        ]);
    }
}
