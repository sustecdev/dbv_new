<?php

require_once __DIR__ . '/../Support/Database.php';
require_once __DIR__ . '/../Support/Security.php';
require_once __DIR__ . '/../Support/Logger.php';
require_once __DIR__ . '/../Support/Response.php';
require_once __DIR__ . '/../Support/Validator.php';
require_once __DIR__ . '/../Support/WithdrawalLimits.php';
require_once __DIR__ . '/../Services/YEMChainService.php';
require_once __DIR__ . '/../Services/SafeZoneService.php';
require_once __DIR__ . '/../Services/StellarService.php';
require_once __DIR__ . '/../Services/SafeIdentService.php';
require_once __DIR__ . '/../Services/ReferralService.php';

class WithdrawController
{
    private PDO $pdo;
    private YEMChainService $yem;
    private SafeZoneService $safezone;
    private StellarService $stellar;
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
        $this->yem = new YEMChainService($cfg['yemchain']['base'], $cfg['yemchain']['key']);
        $this->safezone = new SafeZoneService($cfg['safezone']['pin_check']);
        $this->stellar = new StellarService($cfg['stellar']);
    }

    public function postCreate(): void
    {
        Security::configureSecureSession();
        
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }
        
        if (!isset($_SESSION['uid'])) {
            Security::logSecurityEvent('withdrawal_unauthorized', ['ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        // Rate limiting
        $rateLimitKey = 'withdrawal_' . $_SESSION['uid'];
        if (!Security::checkRateLimit($rateLimitKey, 10, 60)) {
            Security::logSecurityEvent('withdrawal_rate_limit', ['uid' => $_SESSION['uid'], 'ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait before trying again.']);
            return;
        }
        
        // CSRF protection
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            Security::logSecurityEvent('withdrawal_csrf_failed', ['uid' => $_SESSION['uid'], 'ip' => Security::getClientIp()]);
            echo json_encode(['success' => false, 'message' => 'Security token invalid']);
            return;
        }
        
        $uid = (int)$_SESSION['uid'];
        
        // SafeIdent verification check
        $safeident = new SafeIdentService($this->cfg['safeident']);
        $canTransact = $safeident->canUserTransact($uid);
        if (!$canTransact['allowed']) {
            Security::logSecurityEvent('withdrawal_verification_failed', [
                'uid' => $uid,
                'reason' => $canTransact['reason'] ?? 'unknown'
            ]);
            echo json_encode(['success' => false, 'message' => $canTransact['message']]);
            return;
        }
        
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $address = trim($_POST['address'] ?? '');
        $pin = $_POST['pin'] ?? '';
        $key = $_POST['key'] ?? '';

        // Input validation using Validator
        $validationErrors = Validator::validateWithdrawal([
            'amount' => $amount,
            'address' => $address
        ]);
        
        // Block withdrawals to issuer, vault, and owner addresses
        $blockedAddresses = array_filter(array_map('trim', [
            $this->cfg['stellar']['issuer'] ?? '',
            $this->cfg['stellar']['vault'] ?? '',
            $this->cfg['stellar']['owner'] ?? '',
        ]));
        $addressUpper = strtoupper($address);
        foreach ($blockedAddresses as $blocked) {
            if ($blocked !== '' && strtoupper($blocked) === $addressUpper) {
                $validationErrors['address'] = 'Withdrawals to this address are not allowed';
                break;
            }
        }
        
        if (!empty($validationErrors)) {
            Logger::warning('Withdrawal validation failed', ['uid' => $uid, 'errors' => $validationErrors]);
            Response::validationError($validationErrors);
        }

        // Block contract/issuer addresses
        $blockedAddresses = $this->cfg['withdrawal']['blocked_addresses'] ?? [];
        if (Security::isBlockedWithdrawalAddress($address, $blockedAddresses)) {
            Security::logSecurityEvent('withdrawal_blocked_address_attempted', ['uid' => $uid, 'address_prefix' => substr($address, 0, 12) . '...']);
            echo json_encode(['success' => false, 'message' => 'This withdrawal address is not allowed.']);
            return;
        }

        // PIN validation is mandatory for withdrawals
        if (empty($pin) || empty($key)) {
            echo json_encode(['success' => false, 'message' => 'PIN confirmation is required for withdrawals']);
            return;
        }

        if (!$this->safezone->validatePin($uid, $pin, $key)) {
            echo json_encode(['success' => false, 'message' => 'Invalid PIN. Please check the digits at the specified positions.']);
            return;
        }

        // Balance checks
        $balance = $this->yem->getBalance($uid, 'DBV');
        if ($balance < $amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient DBV balance']);
            return;
        }

        // Check daily withdrawal limit
        $dailyLimit = $this->cfg['withdrawal']['daily_limit_stellar'] ?? 0;
        if ($dailyLimit > 0) {
            $bypassDaily = $this->cfg['withdrawal']['daily_limit_bypass_uids'] ?? [];
            $limitCheck = WithdrawalLimits::checkLimit($this->pdo, $uid, 'stellar', $amount, $dailyLimit, $bypassDaily);
            if (!$limitCheck['allowed']) {
                Logger::warning('Stellar daily withdrawal limit exceeded', [
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

        // Check and deduct withdrawal fee if enabled
        $feeEnabled = $this->cfg['withdrawal']['fee_enabled'] ?? true;
        $withdrawalFee = $this->cfg['withdrawal']['fee_usdd'] ?? 2.50;
        
        if ($feeEnabled) {
            // Check USDD balance for withdrawal fee
            $usddBalance = $this->yem->getBalance($uid, 'USDD');
            
            // Use epsilon comparison to handle floating-point precision issues
            // Allow a small tolerance (0.001 USDD) for rounding errors
            $epsilon = 0.001;
            if ($usddBalance < ($withdrawalFee - $epsilon)) {
                echo json_encode(['success' => false, 'message' => 'Insufficient USDD balance for withdrawal fee. Required: ' . number_format($withdrawalFee, 2) . ' USDD, Available: ' . number_format($usddBalance, 2) . ' USDD']);
                return;
            }

            // ⚠️ TEMPORARY: YEMChain Bypass for fee (for testing only)
            $yemchainBypass = $this->cfg['yemchain_bypass'] ?? false;
            if (!$yemchainBypass) {
                // Deduct withdrawal fee (user -> vault)
                $feeValueUSD = $withdrawalFee; // 1 USDD = 1 USD
                $feeResp = $this->yem->createVoucher([
                    'network' => $this->cfg['stellar']['network'],
                    'accountFrom' => $uid,
                    'accountTo' => $this->cfg['yemchain']['vault_account_id'],
                    'asset' => 'USDD',
                    'txnAmount' => $withdrawalFee,
                    'valueUSD' => $feeValueUSD,
                    'currencyCodeFrom' => 'USD',
                    'currencyCodeTo' => 'USD',
                    'reason' => 'Withdrawal fee for DBV bridge transfer',
                ]);

                $feeStatus = $feeResp['status'] ?? 'error';
                $feeMessage = $feeResp['message'] ?? '';
                if ($feeStatus !== 'success') {
                    echo json_encode(['success' => false, 'message' => 'Failed to deduct withdrawal fee: ' . ($feeMessage ?: 'Unknown error')]);
                    return;
                }
            } else {
                Logger::warning('YEMChain fee bypassed for Stellar withdrawal', ['uid' => $uid, 'fee' => $withdrawalFee]);
                $feeResp = ['txnID' => 'BYPASS_FEE_' . strtoupper(substr(md5($uid . $withdrawalFee . time()), 0, 28))];
            }
        } else {
            $withdrawalFee = 0; // Set to 0 if fee is disabled
        }

        // Per-user cap (includes pending, processing, and completed withdrawals across all networks)
        $perUserCap = $this->cfg['withdrawal']['per_user_cap'] ?? 0;
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

        // ⚠️ TEMPORARY: YEMChain Bypass (for testing only)
        $yemchainBypass = $this->cfg['yemchain_bypass'] ?? false;
        if ($yemchainBypass) {
            // Generate fake YEMChain hash for testing
            $yemHash = 'BYPASS_' . strtoupper(substr(md5($uid . $amount . $address . time()), 0, 32));
            Logger::warning('YEMChain bypassed for Stellar withdrawal', ['uid' => $uid, 'amount' => $amount, 'fake_hash' => $yemHash]);
        } else {
            // Attempt real YEMChain voucher (user -> vault)
            $valueUSD = round($amount * 0.01, 2);
            $currencyFrom = $_SESSION['currencycode'] ?? 'USD';
            $reason = 'Bridge: Transfer to ' . ucfirst($this->cfg['stellar']['network']) . ' Stellar Network';
            $resp = $this->yem->createVoucher([
                'network' => $this->cfg['stellar']['network'],
                'accountFrom' => $uid,
                'accountTo' => $this->cfg['yemchain']['vault_account_id'],
                'asset' => 'DBV',
                'txnAmount' => $amount,
                'valueUSD' => $valueUSD,
                'currencyCodeFrom' => $currencyFrom,
                'currencyCodeTo' => 'USD',
                'reason' => $reason,
            ]);

            $status = $resp['status'] ?? 'error';
            $message = $resp['message'] ?? '';


            if ($status !== 'success') {
                Logger::error('Stellar withdrawal YEMChain voucher failed', ['uid' => $uid, 'amount' => $amount, 'error' => $message]);
                
                // CRITICAL: Refund the fee if it was already deducted
                if ($feeEnabled && isset($feeResp['txnID'])) {
                    Logger::warning('Refunding withdrawal fee due to failed DBV transfer', [
                        'uid' => $uid,
                        'fee' => $withdrawalFee,
                        'original_fee_txn' => $feeResp['txnID']
                    ]);
                    
                    // Refund fee (vault -> user)
                    $refundResp = $this->yem->createVoucher([
                        'network' => $this->cfg['stellar']['network'],
                        'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                        'accountTo' => $uid,
                        'asset' => 'USDD',
                        'txnAmount' => $withdrawalFee,
                        'valueUSD' => $withdrawalFee,
                        'currencyCodeFrom' => 'USD',
                        'currencyCodeTo' => 'USD',
                        'reason' => 'Refund: Stellar withdrawal fee (DBV transfer failed)',
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
                
                echo json_encode(['success' => false, 'message' => 'Failed to create transaction: ' . ($message ?: 'Unknown error')]);
                return;
            }

            $yemHash = $resp['txnID'] ?? '';
            if (empty($yemHash)) {
                Logger::error('Stellar withdrawal - YEMChain transaction ID missing', ['uid' => $uid, 'amount' => $amount]);
                
                // CRITICAL: Refund the fee if it was already deducted
                if ($feeEnabled && isset($feeResp['txnID'])) {
                    Logger::warning('Refunding withdrawal fee due to missing transaction ID', [
                        'uid' => $uid,
                        'fee' => $withdrawalFee
                    ]);
                    
                    $refundResp = $this->yem->createVoucher([
                        'network' => $this->cfg['stellar']['network'],
                        'accountFrom' => $this->cfg['yemchain']['vault_account_id'],
                        'accountTo' => $uid,
                        'asset' => 'USDD',
                        'txnAmount' => $withdrawalFee,
                        'valueUSD' => $withdrawalFee,
                        'currencyCodeFrom' => 'USD',
                        'currencyCodeTo' => 'USD',
                        'reason' => 'Refund: Stellar withdrawal fee (transaction ID missing)',
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
        }
        
        // Get fee hash from fee transaction if fee was deducted
        $feeHash = null;
        if ($feeEnabled) {
            if (isset($feeResp['txnID'])) {
                $feeHash = $feeResp['txnID'];
            } elseif ($yemchainBypass) {
                $feeHash = 'BYPASS_FEE_' . strtoupper(substr(md5($uid . $withdrawalFee . time()), 0, 28));
            }
        }

        // Pay referral commission after DBV success (vault -> referrer)
        $referrerUidStored = null;
        $referralCommissionUsdd = null;
        $referralCommissionHash = null;
        $commissionAmount = $this->cfg['withdrawal']['referral_commission_usdd'] ?? 0.50;
        if ($referrerUid !== null && $feeEnabled && $commissionAmount > 0 && !$yemchainBypass) {
            $commissionResp = $this->yem->createVoucher([
                'network' => $this->cfg['stellar']['network'],
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
        
        // Check if destination has trustline (YEMChain succeeded, check real trustline)
        $trustline = 0;
        // Check trustline on Stellar network
        // Use config values for asset code and issuer
        $checkAssetCode = $this->cfg['stellar']['asset_code'];
        $checkIssuer = $this->cfg['stellar']['issuer'];
        $trustline = $this->checkTrustlineDirectly($address, $checkAssetCode, $checkIssuer) ? 1 : 0;

        // Record withdrawal (status 0 = pending; worker processes only when manual_withdraw_enabled is off)
        $manualWithdrawEnabled = $this->cfg['withdrawal']['manual_withdraw_enabled'] ?? false;
        $isManual = $manualWithdrawEnabled ? 1 : 0;
        try {
            $insertCols = 'uid, address, txn_hash_yemchain, amount, fee_usdd, fee_hash_yemchain, trustline, is_manual, created_at, status';
            $insertVals = '?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0';
            $insertParams = [$uid, $address, $yemHash, $amount, $withdrawalFee, $feeHash, $trustline, $isManual];
            $hasReferralCols = $this->hasReferralColumns('stellar_withdraw');
            if ($hasReferralCols) {
                $insertCols .= ', referrer_uid, referral_commission_usdd, referral_commission_hash';
                $insertVals .= ', ?, ?, ?';
                $insertParams[] = $referrerUidStored;
                $insertParams[] = $referralCommissionUsdd;
                $insertParams[] = $referralCommissionHash;
            }
            $ins = $this->pdo->prepare("INSERT INTO stellar_withdraw ($insertCols) VALUES ($insertVals)");
            $ins->execute($insertParams);
            
            if ($ins->rowCount() === 0) {
                Logger::error('Stellar withdrawal - Failed to insert record', ['uid' => $uid, 'amount' => $amount, 'address' => $address]);
                echo json_encode(['success' => false, 'message' => 'Failed to record withdrawal. Please contact support.']);
                return;
            }

            $withdrawalId = $this->pdo->lastInsertId();
            
            if (!$withdrawalId || $withdrawalId <= 0) {
                Logger::error('Stellar withdrawal - Invalid withdrawal ID', ['uid' => $uid, 'amount' => $amount]);
                echo json_encode(['success' => false, 'message' => 'Failed to get withdrawal ID. Please contact support.']);
                return;
            }
            
            // Trigger worker only when not in manual mode
            if (!$manualWithdrawEnabled) {
                try {
                    $this->triggerWorkerProcessing($withdrawalId);
                } catch (Exception $e) {
                    Logger::warning('Stellar withdrawal - Worker trigger failed', ['withdrawal_id' => $withdrawalId, 'error' => $e->getMessage()]);
                }
            } else {
                Logger::info('Stellar withdrawal - Manual mode: skipping worker trigger', ['withdrawal_id' => $withdrawalId, 'uid' => $uid]);
            }
        } catch (PDOException $e) {
            Logger::error('Stellar withdrawal - Database error', ['uid' => $uid, 'amount' => $amount, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please contact support.']);
            return;
        } catch (Exception $e) {
            Logger::error('Stellar withdrawal - Unexpected error', ['uid' => $uid, 'amount' => $amount, 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
            return;
        }

        $trustlineMsg = $manualWithdrawEnabled
            ? ' Your withdrawal request is being processed. The process can take up to 24 hours to complete.'
            : ($trustline === 1 ? ' Trustline verified - processing immediately.' : ' Processing withdrawal - trustline will be created if needed.');
        
        $feeMsg = $feeEnabled && $withdrawalFee > 0 
            ? ' Withdrawal fee of ' . number_format($withdrawalFee, 2) . ' USDD has been deducted.' 
            : '';
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal initiated successfully. DBV has been deducted from your account.' . $feeMsg . $trustlineMsg,
            'txn_hash' => $yemHash,
            'amount' => number_format($amount, 2, '.', ''),
            'fee_usdd' => $feeEnabled ? number_format($withdrawalFee, 2, '.', '') : '0.00',
            'fee_enabled' => $feeEnabled,
            'address' => $address,
            'trustline' => $trustline,
            'id' => $withdrawalId,
        ]);
    }

    private function checkTrustlineDirectly(string $address, string $assetCode, string $issuer): bool
    {
        $horizonUrl = $this->cfg['stellar']['network'] === 'public' 
            ? 'https://horizon.stellar.org' 
            : 'https://horizon-testnet.stellar.org';
        
        $url = rtrim($horizonUrl, '/') . '/accounts/' . $address;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http !== 200 || !$response) {
            return false;
        }
        
        $json = json_decode($response, true);
        if (!isset($json['balances'])) {
            return false;
        }
        
        foreach ($json['balances'] as $balance) {
            if ($balance['asset_type'] !== 'native' &&
                isset($balance['asset_code'], $balance['asset_issuer']) &&
                $balance['asset_code'] === $assetCode &&
                $balance['asset_issuer'] === $issuer) {
                return true;
            }
        }
        
        return false;
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
        // Try HTTP trigger first (port 3001)
        $httpPort = 3001;
        $triggerUrl = "http://localhost:$httpPort/process";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $triggerUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $withdrawalId]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Don't wait for response
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        // If HTTP trigger fails, fallback to PHP API trigger (HMAC auth)
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
                curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(['id' => $withdrawalId]));
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
