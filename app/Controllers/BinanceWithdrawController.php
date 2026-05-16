<?php

require_once __DIR__ . '/../Support/Database.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/Response.php';
require_once __DIR__ . '/../Support/WithdrawalLimits.php';
require_once __DIR__ . '/../Services/YEMChainService.php';
require_once __DIR__ . '/../Services/SafeZoneService.php';
require_once __DIR__ . '/../Services/BinanceService.php';
require_once __DIR__ . '/../Services/SafeIdentService.php';
require_once __DIR__ . '/../Services/ReferralService.php';

class BinanceWithdrawController
{
    private PDO $pdo;
    private YEMChainService $yem;
    private SafeZoneService $safezone;
    private BinanceService $binance;
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
        $this->yem = new YEMChainService($cfg['yemchain']['base'], $cfg['yemchain']['key']);
        $this->safezone = new SafeZoneService($cfg['safezone']['pin_check']);
        $this->binance = new BinanceService($cfg['binance']);
    }

    public function postCreate(): void
    {
        Security::configureSecureSession();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }
        
        if (!isset($_SESSION['uid'])) {
            Security::logSecurityEvent('binance_withdrawal_unauthorized', ['ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        // Rate limiting
        $rateLimitKey = 'binance_withdrawal_' . $_SESSION['uid'];
        if (!Security::checkRateLimit($rateLimitKey, 10, 60)) {
            Security::logSecurityEvent('binance_withdrawal_rate_limit', ['uid' => $_SESSION['uid']]);
            echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait before trying again.']);
            return;
        }
        
        // CSRF protection
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            Security::logSecurityEvent('binance_withdrawal_csrf_failed', ['uid' => $_SESSION['uid']]);
            echo json_encode(['success' => false, 'message' => 'Security token invalid']);
            return;
        }
        
        $uid = (int)$_SESSION['uid'];
        
        // SafeIdent verification check
        $safeident = new SafeIdentService($this->cfg['safeident']);
        $canTransact = $safeident->canUserTransact($uid);
        if (!$canTransact['allowed']) {
            Security::logSecurityEvent('binance_withdrawal_verification_failed', [
                'uid' => $uid,
                'reason' => $canTransact['reason'] ?? 'unknown'
            ]);
            echo json_encode(['success' => false, 'message' => $canTransact['message']]);
            return;
        }
        
        $amount = round((float)($_POST['amount'] ?? 0), 8);
        $address = trim($_POST['address'] ?? '');
        $pin = isset($_POST['pin']) ? trim((string)$_POST['pin']) : '';
        $key = isset($_POST['key']) ? trim((string)$_POST['key']) : '';
        
        // SECURITY: No sensitive data (PIN, key, POST keys) in logs - gate behind APP_DEBUG only
        if ($this->cfg['app']['debug'] ?? false) {
            Logger::debug('Binance withdrawal received', ['uid' => $uid, 'has_pin' => !empty($pin), 'has_key' => !empty($key)]);
        }
        
        // Input validation
        if ($amount <= 0 || $amount > 5000000) {
            echo json_encode(['success' => false, 'message' => 'Invalid amount. Must be between 0.01 and 5,000,000 DBV']);
            return;
        }
        
        if (empty($address) || !$this->binance->isValidAddress($address)) {
            echo json_encode(['success' => false, 'message' => 'Invalid BSC address format']);
            return;
        }

        // Block contract/issuer addresses
        $blockedAddresses = $this->cfg['withdrawal']['blocked_addresses'] ?? [];
        if (Security::isBlockedWithdrawalAddress($address, $blockedAddresses)) {
            Security::logSecurityEvent('binance_withdrawal_blocked_address_attempted', ['uid' => $uid, 'address_prefix' => substr($address, 0, 12) . '...']);
            echo json_encode(['success' => false, 'message' => 'This withdrawal address is not allowed.']);
            return;
        }
        
        // Block withdrawals to restricted addresses
        $blockedAddresses = array_filter(array_map('trim', [
            strtolower($this->cfg['binance']['vault_address'] ?? ''),
            '0xaed72bac1da87a9ed09b1de1a54590ba1124c734',
        ]));
        if (in_array(strtolower($address), $blockedAddresses, true)) {
            echo json_encode(['success' => false, 'message' => 'Withdrawals to this address are not allowed']);
            return;
        }
        
        // PIN validation is mandatory for withdrawals
        if (empty($pin) || empty($key)) {
            echo json_encode(['success' => false, 'message' => 'PIN confirmation is required for withdrawals']);
            return;
        }
        
        if (!$this->safezone->validatePin($uid, $pin, $key)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid PIN. Please check the digits at the specified positions.'
            ]);
            return;
        }
        
        // Balance checks
        $balance = $this->yem->getBalance($uid, 'DBV');
        if ($balance < $amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient DBV balance']);
            return;
        }
        
        // Check daily withdrawal limit
        $dailyLimit = $this->cfg['binance']['daily_withdrawal_limit'] ?? 0;
        if ($dailyLimit > 0) {
            $bypassDaily = $this->cfg['withdrawal']['daily_limit_bypass_uids'] ?? [];
            $limitCheck = WithdrawalLimits::checkLimit($this->pdo, $uid, 'binance', $amount, $dailyLimit, $bypassDaily);
            if (!$limitCheck['allowed']) {
                Logger::warning('Binance daily withdrawal limit exceeded', [
                    'uid' => $uid,
                    'amount' => $amount,
                    'today_total' => $limitCheck['today_total'],
                    'limit' => $dailyLimit
                ]);
                echo json_encode([
                    'success' => false,
                    'message' => $limitCheck['message']
                ]);
                return;
            }
        }
        
        // Resolve referrer for commission (before fee deduction)
        $referralService = new ReferralService(
            $this->cfg['safezone']['getrefby_url'] ?? 'https://safe.zone/api/getrefby.php',
            $this->cfg['safezone']['getrefby_api_key'] ?? '',
            $this->cfg['safezone']['getrefby_curl_cainfo'] ?? null
        );
        $referrerUid = $referralService->getReferrerUid($uid);

        // Check and deduct withdrawal fee if enabled (network-specific)
        $feeEnabled = $this->cfg['binance']['withdrawal_fee_enabled'] ?? ($this->cfg['withdrawal']['fee_enabled'] ?? true);
        $withdrawalFee = $this->cfg['binance']['withdrawal_fee_usdd'] ?? ($this->cfg['withdrawal']['fee_usdd'] ?? 2.50);
        
        // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
        $yemchainBypass = $this->cfg['yemchain_bypass'] ?? false;
        
        if ($feeEnabled && !$yemchainBypass) {
            $usddBalance = $this->yem->getBalance($uid, 'USDD');
            
            // Use epsilon comparison to handle floating-point precision issues
            // Allow a small tolerance (0.001 USDD) for rounding errors
            $epsilon = 0.001;
            if ($usddBalance < ($withdrawalFee - $epsilon)) {
                echo json_encode(['success' => false, 'message' => 'Insufficient USDD balance for withdrawal fee. Required: ' . number_format($withdrawalFee, 2) . ' USDD, Available: ' . number_format($usddBalance, 2) . ' USDD']);
                return;
            }
            
            // Deduct withdrawal fee (user -> vault)
            // YEMChain API expects 'public' or 'testnet', not 'mainnet'
            $yemNetwork = ($this->cfg['binance']['network'] === 'mainnet') ? 'public' : 'testnet';
            $feeResp = $this->yem->createVoucher([
                'network' => $yemNetwork,
                'accountFrom' => $uid,
                'accountTo' => $this->cfg['yemchain']['vault_account_id'],
                'asset' => 'USDD',
                'txnAmount' => $withdrawalFee,
                'valueUSD' => $withdrawalFee,
                'currencyCodeFrom' => 'USD',
                'currencyCodeTo' => 'USD',
                'reason' => 'Withdrawal fee for Binance Smart Chain withdrawal',
            ]);
            
            if (($feeResp['status'] ?? 'error') !== 'success') {
                echo json_encode(['success' => false, 'message' => 'Failed to deduct withdrawal fee']);
                return;
            }
        } elseif ($feeEnabled && $yemchainBypass) {
            Logger::warning('YEMChain fee bypassed for Binance withdrawal', ['uid' => $uid, 'fee' => $withdrawalFee]);
        }
        
        // Per-user cap (includes pending, processing, and completed withdrawals across all networks)
        $perUserCap = $this->cfg['withdrawal']['per_user_cap'] ?? 5000000;
        if ($perUserCap > 0) {
            // Check total withdrawals across all networks (stellar, binance, ethereum)
            $stmt = $this->pdo->prepare('
                SELECT COALESCE(SUM(total), 0) as grand_total FROM (
                    SELECT SUM(amount) as total FROM stellar_withdraw WHERE uid = ? AND status IN (0, 1, 3, 8)
                    UNION ALL
                    SELECT SUM(amount) as total FROM binance_withdraw WHERE uid = ? AND status IN (0, 1, 3, 8)
                    UNION ALL
                    SELECT SUM(amount) as total FROM ethereum_withdraw WHERE uid = ? AND status IN (0, 1, 3, 8)
                ) as combined
            ');
            $stmt->execute([$uid, $uid, $uid]);
            $totalWithdrawn = (float)($stmt->fetchColumn() ?: 0);
            if ($totalWithdrawn + $amount > $perUserCap) {
                echo json_encode(['success' => false, 'message' => 'Maximum withdrawal limit exceeded. Total withdrawals: ' . number_format($totalWithdrawn, 2) . ' DBV, Limit: ' . number_format($perUserCap, 2) . ' DBV']);
                return;
            }
        }
        
        // Referral commission tracking (set when commission paid after DBV success)
        $referrerUidStored = null;
        $referralCommissionUsdd = null;
        $referralCommissionHash = null;

        // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
        if ($yemchainBypass) {
            // Generate fake YEMChain hash for testing
            $yemHash = 'BYPASS_' . strtoupper(substr(md5($uid . $amount . $address . time()), 0, 32));
            $feeHash = $feeEnabled ? 'BYPASS_FEE_' . strtoupper(substr(md5($uid . $withdrawalFee . time()), 0, 28)) : null;
            Logger::warning('YEMChain bypassed for Binance withdrawal', ['uid' => $uid, 'amount' => $amount, 'fake_hash' => $yemHash]);
        } else {
            // Deduct DBV from user (user -> vault)
            $reason = 'Bridge: Transfer to Binance Smart Chain (' . $this->cfg['binance']['network'] . ')';
            $valueUSD = round($amount * 0.01, 2);
            
            // YEMChain API expects 'public' or 'testnet', not 'mainnet'
            // Map 'mainnet' to 'public' for YEMChain compatibility
            $yemNetwork = ($this->cfg['binance']['network'] === 'mainnet') ? 'public' : 'testnet';
            
            $resp = $this->yem->createVoucher([
                'network' => $yemNetwork,
                'accountFrom' => $uid,
                'accountTo' => $this->cfg['yemchain']['vault_account_id'],
                'asset' => 'DBV',
                'txnAmount' => $amount,
                'valueUSD' => $valueUSD,
                'currencyCodeFrom' => 'USD',
                'currencyCodeTo' => 'USD',
                'reason' => $reason,
            ]);
            
            
            if (($resp['status'] ?? 'error') !== 'success') {
                $errorMsg = $resp['message'] ?? 'Unknown error';
                Logger::error('Binance withdrawal YEMChain voucher failed', ['uid' => $uid, 'amount' => $amount, 'error' => $errorMsg]);
                
                // CRITICAL: Refund the fee if it was already deducted
                if ($feeEnabled && isset($feeResp['txnID'])) {
                    Logger::warning('Refunding withdrawal fee due to failed DBV transfer', [
                        'uid' => $uid,
                        'fee' => $withdrawalFee,
                        'original_fee_txn' => $feeResp['txnID']
                    ]);
                    
                    // Refund fee (vault -> user)
                    $refundResp = $this->yem->createVoucher([
                        'network' => 'binance',
                        'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                        'accountTo' => $uid,
                        'asset' => 'USDD',
                        'txnAmount' => $withdrawalFee,
                        'valueUSD' => $withdrawalFee,
                        'currencyCodeFrom' => 'USD',
                        'currencyCodeTo' => 'USD',
                        'reason' => 'Refund: BSC withdrawal fee (DBV transfer failed)',
                    ]);
                    
                    if (($refundResp['status'] ?? 'error') !== 'success') {
                        Logger::error('CRITICAL: Fee refund failed after withdrawal failure', [
                            'uid' => $uid,
                            'fee' => $withdrawalFee,
                            'refund_error' => $refundResp['message'] ?? 'Unknown error'
                        ]);
                    } else {
                        Logger::info('Fee refund successful', [
                            'uid' => $uid,
                            'fee' => $withdrawalFee,
                            'refund_txn' => $refundResp['txnID'] ?? ''
                        ]);
                    }
                }
                
                echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $errorMsg]);
                return;
            }
            
            $yemHash = $resp['txnID'] ?? '';
            if (empty($yemHash)) {
                Logger::error('Binance withdrawal - YEMChain transaction ID missing', ['uid' => $uid, 'amount' => $amount]);
                
                // CRITICAL: Refund the fee if it was already deducted
                if ($feeEnabled && isset($feeResp['txnID'])) {
                    Logger::warning('Refunding withdrawal fee due to missing transaction ID', [
                        'uid' => $uid,
                        'fee' => $withdrawalFee
                    ]);
                    
                    $refundResp = $this->yem->createVoucher([
                        'network' => 'binance',
                        'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                        'accountTo' => $uid,
                        'asset' => 'USDD',
                        'txnAmount' => $withdrawalFee,
                        'valueUSD' => $withdrawalFee,
                        'currencyCodeFrom' => 'USD',
                        'currencyCodeTo' => 'USD',
                        'reason' => 'Refund: BSC withdrawal fee (transaction ID missing)',
                    ]);
                    
                    if (($refundResp['status'] ?? 'error') !== 'success') {
                        Logger::error('CRITICAL: Fee refund failed after missing transaction ID', [
                            'uid' => $uid,
                            'fee' => $withdrawalFee
                        ]);
                    }
                }
                
                echo json_encode(['success' => false, 'message' => 'YEMChain transaction ID not received. Please contact support.']);
                return;
            }
            
            // Get fee hash from fee transaction if fee was deducted
            $feeHash = null;
            if ($feeEnabled && isset($feeResp['txnID'])) {
                $feeHash = $feeResp['txnID'];
            }

            // Pay referral commission after DBV success (vault -> referrer)
            $referrerUidStored = null;
            $referralCommissionUsdd = null;
            $referralCommissionHash = null;
            $commissionAmount = $this->cfg['withdrawal']['referral_commission_usdd'] ?? 0.50;
            if ($referrerUid !== null && $feeEnabled && $commissionAmount > 0 && !$yemchainBypass) {
                $yemNetwork = ($this->cfg['binance']['network'] === 'mainnet') ? 'public' : 'testnet';
                $commissionResp = $this->yem->createVoucher([
                    'network' => $yemNetwork,
                    'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                    'accountTo' => $referrerUid,
                    'asset' => 'USDD',
                    'txnAmount' => $commissionAmount,
                    'valueUSD' => $commissionAmount,
                    'currencyCodeFrom' => 'USD',
                    'currencyCodeTo' => 'USD',
                    'reason' => 'Referral commission for withdrawal',
                ]);
                if (($commissionResp['status'] ?? 'error') === 'success') {
                    $referrerUidStored = $referrerUid;
                    $referralCommissionUsdd = $commissionAmount;
                    $referralCommissionHash = $commissionResp['txnID'] ?? null;
                } else {
                    Logger::warning('Referral commission transfer failed', ['uid' => $uid, 'referrer_uid' => $referrerUid, 'error' => $commissionResp['message'] ?? 'Unknown']);
                }
            }
        }
        
        // Record withdrawal (status 0 = pending; worker processes only when manual_withdraw_enabled is off)
        $manualWithdrawEnabled = $this->cfg['withdrawal']['manual_withdraw_enabled'] ?? false;
        $isManual = $manualWithdrawEnabled ? 1 : 0;
        try {
            $insertCols = 'uid, address, amount, fee_usdd, fee_hash_yemchain, txn_hash_yemchain, is_manual, status, created_at';
            $insertVals = '?, ?, ?, ?, ?, ?, ?, 0, NOW()';
            $insertParams = [$uid, $address, $amount, $withdrawalFee, $feeHash, $yemHash, $isManual];
            if ($this->hasReferralColumns('binance_withdraw')) {
                $insertCols .= ', referrer_uid, referral_commission_usdd, referral_commission_hash';
                $insertVals .= ', ?, ?, ?';
                $insertParams[] = $referrerUidStored;
                $insertParams[] = $referralCommissionUsdd;
                $insertParams[] = $referralCommissionHash;
            }
            $ins = $this->pdo->prepare("INSERT INTO binance_withdraw ($insertCols) VALUES ($insertVals)");
            $ins->execute($insertParams);
            
            if ($ins->rowCount() === 0) {
                Logger::error('Binance withdrawal - Failed to insert record', ['uid' => $uid, 'amount' => $amount, 'address' => $address]);
                echo json_encode(['success' => false, 'message' => 'Failed to record withdrawal. Please contact support.']);
                return;
            }
            
            $withdrawalId = $this->pdo->lastInsertId();
            
            if (!$withdrawalId || $withdrawalId <= 0) {
                Logger::error('Binance withdrawal - Invalid withdrawal ID', ['uid' => $uid, 'amount' => $amount]);
                echo json_encode(['success' => false, 'message' => 'Failed to get withdrawal ID. Please contact support.']);
                return;
            }
            
            // Trigger worker only when not in manual mode
            if (!$manualWithdrawEnabled) {
                try {
                    $this->triggerWorkerProcessing($withdrawalId);
                } catch (Exception $e) {
                    Logger::warning('Binance withdrawal - Worker trigger failed', ['withdrawal_id' => $withdrawalId, 'error' => $e->getMessage()]);
                }
            } else {
                Logger::info('Binance withdrawal - Manual mode: skipping worker trigger', ['withdrawal_id' => $withdrawalId, 'uid' => $uid]);
            }
            
            $msgManual = $manualWithdrawEnabled ? ' Your withdrawal request is being processed. The process can take up to 24 hours to complete.' : ' Your DBV tokens will be sent to your BSC address automatically within seconds.';
            echo json_encode([
                'success' => true,
                'message' => 'Withdrawal initiated.' . $msgManual . ($feeEnabled ? ' Withdrawal fee of ' . number_format($withdrawalFee, 2) . ' USDD has been deducted.' : ''),
                'amount' => number_format($amount, 8, '.', ''),
                'address' => $address,
                'id' => $withdrawalId,
                'txn_hash' => $yemHash,
                'fee_enabled' => $feeEnabled,
                'fee_usdd' => $feeEnabled ? $withdrawalFee : 0,
            ]);
        } catch (PDOException $e) {
            Logger::error('Binance withdrawal - Database error', ['uid' => $uid, 'amount' => $amount, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please contact support.']);
            return;
        } catch (Exception $e) {
            Logger::error('Binance withdrawal - Unexpected error', ['uid' => $uid, 'amount' => $amount, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
            return;
        }
    }
    
    private function hasReferralColumns(string $table): bool
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE 'referrer_uid'");
                $cache[$table] = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $cache[$table] = false;
            }
        }
        return $cache[$table];
    }

    private function triggerWorkerProcessing(int $withdrawalId): void
    {
        // Try HTTP trigger first (port 3002 for Binance)
        $httpPort = 3002;
        $triggerUrl = "http://localhost:$httpPort/process";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $triggerUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $withdrawalId, 'network' => 'binance']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Don't wait for response
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        // If HTTP trigger fails, fallback to PHP API trigger
        if ($error || !$result) {
            require_once __DIR__ . '/../Support/PathHelper.php';
            $path = PathHelper::url('api/trigger-withdrawal-worker.php');
            $secret = $this->cfg['worker']['secret'] ?? '';
            if ($secret !== '') {
                $timestamp = (string)time();
                $dataToSign = $timestamp . $path;
                $signature = Security::generateWorkerSignature($dataToSign, $secret);
                $baseUrl = $this->cfg['worker']['trigger_base_url'] ?? 'http://127.0.0.1';
                $localUrl = rtrim($baseUrl, '/') . $path;
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, $localUrl);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(['id' => $withdrawalId, 'network' => 'binance']));
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'X-Worker-Timestamp: ' . $timestamp,
                    'X-Worker-Signature: ' . $signature
                ]);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 1);
                curl_setopt($ch2, CURLOPT_NOSIGNAL, 1);
                curl_exec($ch2);
                curl_close($ch2);
            }
        }
    }
}

