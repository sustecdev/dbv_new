<?php

require_once __DIR__ . '/../Support/Database.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/Response.php';
require_once __DIR__ . '/../Services/EthereumService.php';
require_once __DIR__ . '/../Services/YEMChainService.php';
require_once __DIR__ . '/../Services/SafeIdentService.php';

class EthereumDepositController
{
    private PDO $pdo;
    private EthereumService $ethereum;
    private YEMChainService $yem;
    private array $cfg;

    public function __construct(array $cfg)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        $this->cfg = $cfg;
        $this->pdo = Database::pdo($cfg['db']);
        $this->ethereum = new EthereumService($cfg['ethereum']);
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
            Security::logSecurityEvent('ethereum_deposit_unauthorized', ['ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        // Rate limiting
        $rateLimitKey = 'ethereum_deposit_' . $_SESSION['uid'];
        if (!Security::checkRateLimit($rateLimitKey, 20, 60)) {
            Security::logSecurityEvent('ethereum_deposit_rate_limit', ['uid' => $_SESSION['uid']]);
            echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait before trying again.']);
            return;
        }
        
        // CSRF protection
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            Security::logSecurityEvent('ethereum_deposit_csrf_failed', ['uid' => $_SESSION['uid']]);
            echo json_encode(['success' => false, 'message' => 'Security token invalid']);
            return;
        }
        
        $uid = (int)$_SESSION['uid'];
        
        // SafeIdent verification check
        $safeident = new SafeIdentService($this->cfg['safeident']);
        $canTransact = $safeident->canUserTransact($uid);
        if (!$canTransact['allowed']) {
            Security::logSecurityEvent('ethereum_deposit_verification_failed', [
                'uid' => $uid,
                'reason' => $canTransact['reason'] ?? 'unknown'
            ]);
            echo json_encode(['success' => false, 'message' => $canTransact['message']]);
            return;
        }
        
        $txnHash = trim($_POST['txn_hash'] ?? '');
        
        // Validation
        if (empty($txnHash)) {
            echo json_encode(['success' => false, 'message' => 'Transaction hash is required']);
            return;
        }
        
        if (!$this->ethereum->isValidTransactionHash($txnHash)) {
            echo json_encode(['success' => false, 'message' => 'Invalid transaction hash format']);
            return;
        }
        
        // Duplicate check
        $stmt = $this->pdo->prepare('SELECT id FROM ethereum_deposit WHERE txn_hash_eth = ?');
        $stmt->execute([$txnHash]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Transaction already recorded']);
            return;
        }
        
        // Verify transaction
        $vaultAddress = $this->cfg['ethereum']['vault_address'] ?? '';
        if (empty($vaultAddress)) {
            echo json_encode(['success' => false, 'message' => 'Vault address not configured']);
            return;
        }
        
        $depositDetails = $this->ethereum->verifyDeposit($txnHash, $vaultAddress);
        if (!$depositDetails) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found or invalid. Please ensure the transaction is confirmed and is a DBV token transfer to the vault address.']);
            return;
        }
        
        $amount = (float)$depositDetails['amount'];
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid deposit amount']);
            return;
        }
        
        // Calculate value in USD
        $valueUSD = round($amount * 0.01, 2);
        $currencyTo = $_SESSION['currencycode'] ?? 'USD';
        $reason = 'Bridge: Transfer from Ethereum (' . $this->cfg['ethereum']['network'] . ')';
        
        // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
        $yemchainBypass = $this->cfg['yemchain_bypass'] ?? false;
        if ($yemchainBypass) {
            // Generate fake YEMChain hash for testing
            $yemHash = 'BYPASS_' . strtoupper(substr(md5($txnHash . $uid . time()), 0, 32));
            Logger::warning('YEMChain bypassed for Ethereum deposit', ['uid' => $uid, 'txn_hash' => $txnHash, 'fake_hash' => $yemHash]);
        } else {
            // Create YEMChain voucher (vault -> user)
            // YEMChain API expects 'public' or 'testnet', not 'mainnet'
            $yemNetwork = ($this->cfg['ethereum']['network'] === 'mainnet') ? 'public' : 'testnet';
            $resp = $this->yem->createVoucher([
                'network' => $yemNetwork,
                'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                'accountTo' => $uid,
                'asset' => 'DBV',
                'txnAmount' => $amount,
                'valueUSD' => $valueUSD,
                'currencyCodeFrom' => 'USD',
                'currencyCodeTo' => $currencyTo,
                'reason' => $reason,
            ]);
            
            if (($resp['status'] ?? 'error') !== 'success') {
                $errorMsg = $resp['message'] ?? 'Unknown error';
                Logger::error('Ethereum deposit YEMChain voucher failed', ['uid' => $uid, 'txn_hash' => $txnHash, 'error' => $errorMsg]);
                echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $errorMsg]);
                return;
            }
            
            $yemHash = $resp['txnID'] ?? '';
            if (empty($yemHash)) {
                echo json_encode(['success' => false, 'message' => 'YEMChain transaction ID not received']);
                return;
            }
        }
        
        // Record deposit (round to 8 decimals)
        try {
            $amount = round($amount, 8);
            $ins = $this->pdo->prepare('INSERT INTO ethereum_deposit (uid, txn_hash_eth, txn_hash_yemchain, amount, status, created_at) VALUES (?, ?, ?, ?, 3, NOW())');
            $ins->execute([$uid, $txnHash, $yemHash, $amount]);
            
            if ($ins->rowCount() === 0) {
                Logger::error('Ethereum deposit - Failed to insert record', ['uid' => $uid, 'txn_hash' => $txnHash]);
                echo json_encode(['success' => false, 'message' => 'Failed to record deposit. Please contact support.']);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Deposit successful. DBV has been added to your account.',
                'txn_hash' => $txnHash,
                'amount' => number_format($amount, 8, '.', ''),
                'yemchain_hash' => $yemHash,
            ]);
        } catch (PDOException $e) {
            Logger::error('Ethereum deposit - Database error', ['uid' => $uid, 'txn_hash' => $txnHash, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please contact support.']);
            return;
        } catch (Exception $e) {
            Logger::error('Ethereum deposit - Unexpected error', ['uid' => $uid, 'txn_hash' => $txnHash, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
            return;
        }
    }
}

