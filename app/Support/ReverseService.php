<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../Services/YEMChainService.php';

/**
 * Handles reversals for failed withdrawals and blocked-address withdrawals
 */
class ReverseService
{
    private PDO $pdo;
    private YEMChainService $yemchain;
    private array $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->yemchain = new YEMChainService(
            $config['yemchain']['base'],
            $config['yemchain']['key']
        );
    }

    /**
     * True if destination matches the same blocklist as withdraw controllers (issuer/vault/owner / vault+hardcoded).
     */
    public function isWithdrawalDestinationBlocked(string $network, ?string $address): bool
    {
        $address = trim((string)$address);
        if ($address === '') {
            return false;
        }
        if ($network === 'stellar') {
            $blocked = array_filter(array_map('trim', [
                $this->config['stellar']['issuer'] ?? '',
                $this->config['stellar']['vault'] ?? '',
                $this->config['stellar']['owner'] ?? '',
            ]));
            $upper = strtoupper($address);
            foreach ($blocked as $b) {
                if ($b !== '' && strtoupper($b) === $upper) {
                    return true;
                }
            }
            return false;
        }
        if ($network === 'binance' || $network === 'ethereum') {
            $cfgKey = $network === 'binance' ? 'binance' : 'ethereum';
            $blocked = array_filter(array_map('trim', [
                strtolower($this->config[$cfgKey]['vault_address'] ?? ''),
                '0xaed72bac1da87a9ed09b1de1a54590ba1124c734',
            ]));
            return in_array(strtolower($address), $blocked, true);
        }
        return false;
    }

    /**
     * Process reversal for a failed withdrawal (status = 2)
     *
     * @param bool $refundUsddFee If true (default), refund withdrawal USDD fee via YEMChain; if false, only credit DBV
     */
    public function reverseFailedWithdrawal(string $network, int $withdrawalId, bool $refundUsddFee = true): array
    {
        $allowedNetworks = ['stellar', 'binance', 'ethereum'];
        if (!in_array($network, $allowedNetworks, true)) {
            return ['success' => false, 'message' => 'Invalid network'];
        }
        try {
            $withdrawal = $this->loadWithdrawal($network, $withdrawalId);
            if (!$withdrawal) {
                return ['success' => false, 'message' => 'Withdrawal not found'];
            }
            if ($this->hasExistingReversal($network, $withdrawalId)) {
                return ['success' => false, 'message' => 'Already reversed'];
            }
            if ((int)$withdrawal['status'] !== 2) {
                return ['success' => false, 'message' => 'Transaction is not failed (status must be 2)'];
            }

            [$uid, $amount, $feeToRefund] = $this->computeRefundAmounts($withdrawal, $withdrawalId, $network, $refundUsddFee);

            return $this->runLedgerReversal(
                $network,
                $withdrawalId,
                $withdrawal,
                $uid,
                $amount,
                $feeToRefund,
                $refundUsddFee,
                'Reversal: Failed ' . $network . ' withdrawal #' . $withdrawalId,
                'Reversal: Withdrawal fee for failed ' . $network . ' withdrawal #' . $withdrawalId,
                'failed'
            );
        } catch (Exception $e) {
            return $this->reversalErrorResponse($network, $withdrawalId, $e);
        }
    }

    /**
     * Reverse a withdrawal whose destination is on the blocked-address list,
     * for non-failed statuses (e.g. completed to vault). Failed (status 2) delegations use reverseFailedWithdrawal.
     *
     * Blocked-address (non-failed) reversals always credit DBV only; USDD withdrawal fees are never refunded via this path.
     * The $refundUsddFee argument applies only when delegating to {@see reverseFailedWithdrawal} for status = 2.
     */
    public function reverseBlockedAddressWithdrawal(string $network, int $withdrawalId, bool $refundUsddFee = true): array
    {
        $allowedNetworks = ['stellar', 'binance', 'ethereum'];
        if (!in_array($network, $allowedNetworks, true)) {
            return ['success' => false, 'message' => 'Invalid network'];
        }
        try {
            $withdrawal = $this->loadWithdrawal($network, $withdrawalId);
            if (!$withdrawal) {
                return ['success' => false, 'message' => 'Withdrawal not found'];
            }
            if ((int)$withdrawal['status'] === 2) {
                return $this->reverseFailedWithdrawal($network, $withdrawalId, $refundUsddFee);
            }
            if ($this->hasExistingReversal($network, $withdrawalId)) {
                return ['success' => false, 'message' => 'Already reversed'];
            }
            if ((int)$withdrawal['status'] === 9) {
                return ['success' => false, 'message' => 'Withdrawal is already cancelled / reversed'];
            }
            $addr = $withdrawal['address'] ?? '';
            if (!$this->isWithdrawalDestinationBlocked($network, $addr)) {
                return ['success' => false, 'message' => 'Destination is not a blocked address'];
            }
            $st = (int)$withdrawal['status'];
            if (!in_array($st, [0, 1, 3, 8], true)) {
                return ['success' => false, 'message' => 'This withdrawal status cannot be reversed via blocked-address flow'];
            }

            $refundUsddFee = false;
            [$uid, $amount, $feeToRefund] = $this->computeRefundAmounts($withdrawal, $withdrawalId, $network, $refundUsddFee);

            Logger::info('Reversal: blocked-address withdrawal', [
                'network' => $network,
                'withdrawal_id' => $withdrawalId,
                'uid' => $uid,
                'status' => $st,
                'address' => substr((string)$addr, 0, 12) . '…',
                'refund_usdd_fee' => false,
            ]);

            return $this->runLedgerReversal(
                $network,
                $withdrawalId,
                $withdrawal,
                $uid,
                $amount,
                $feeToRefund,
                $refundUsddFee,
                'Reversal: Blocked-address ' . $network . ' withdrawal #' . $withdrawalId,
                'Reversal: Withdrawal fee for blocked-address ' . $network . ' withdrawal #' . $withdrawalId,
                'blocked_address'
            );
        } catch (Exception $e) {
            return $this->reversalErrorResponse($network, $withdrawalId, $e);
        }
    }

    private function loadWithdrawal(string $network, int $withdrawalId): ?array
    {
        $table = $network . '_withdraw';
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$withdrawalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function hasExistingReversal(string $network, int $withdrawalId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM reversals WHERE network = ? AND withdrawal_id = ? LIMIT 1");
        $stmt->execute([$network, $withdrawalId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return array{0: int, 1: float, 2: float} uid, dbv amount, fee to refund (USDD)
     */
    private function computeRefundAmounts(array $withdrawal, int $withdrawalId, string $network, bool $refundUsddFee): array
    {
        $uid = (int)$withdrawal['uid'];
        $amount = (float)$withdrawal['amount'];
        $feeUsdd = (float)($withdrawal['fee_usdd'] ?? 0);
        if ($feeUsdd > 25) {
            $feeUsdd = 0;
            Logger::warning('Reversal: fee_usdd capped (was > 25, possible corrupt data)', ['withdrawal_id' => $withdrawalId, 'network' => $network]);
        }
        $feeToRefund = $refundUsddFee ? $feeUsdd : 0;
        if (!$refundUsddFee && $feeUsdd > 0) {
            Logger::info('Reversal: USDD fee refund skipped by admin option', [
                'withdrawal_id' => $withdrawalId,
                'network' => $network,
                'uid' => $uid,
                'fee_usdd_on_record' => $feeUsdd,
            ]);
        }
        return [$uid, $amount, $feeToRefund];
    }

    /**
     * @param string $reversalKind 'failed'|'blocked_address' (logging)
     */
    private function runLedgerReversal(
        string $network,
        int $withdrawalId,
        array $withdrawal,
        int $uid,
        float $amount,
        float $feeToRefund,
        bool $refundUsddFee,
        string $dbvReason,
        string $usddReason,
        string $reversalKind
    ): array {
        $vaultId = $this->config['yemchain']['vault_account_id'] ?? '';
        if (empty($vaultId)) {
            return ['success' => false, 'message' => 'VAULT_ACCOUNT_ID not configured'];
        }
        $yemNetwork = ($this->config['stellar']['network'] ?? 'public') === 'public' ? 'public' : 'testnet';

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid withdrawal amount'];
        }

        $table = $network . '_withdraw';
        $this->pdo->beginTransaction();
        try {
            $dbvReversal = $this->yemchain->createVoucher([
                'network' => $yemNetwork,
                'accountFrom' => $vaultId,
                'accountTo' => (string)$uid,
                'asset' => 'DBV',
                'txnAmount' => $amount,
                'valueUSD' => round($amount * 0.01, 2),
                'currencyCodeFrom' => 'USD',
                'currencyCodeTo' => 'USD',
                'reason' => $dbvReason,
            ]);

            if (($dbvReversal['status'] ?? 'error') !== 'success') {
                throw new Exception('Failed to reverse DBV: ' . ($dbvReversal['message'] ?? 'Unknown error'));
            }

            $dbvTxnHash = $dbvReversal['txnID'] ?? $dbvReversal['txn_hash'] ?? $dbvReversal['hash'] ?? null;

            $usddReversalHash = null;
            if ($feeToRefund > 0) {
                $usddReversal = $this->yemchain->createVoucher([
                    'network' => $yemNetwork,
                    'accountFrom' => $vaultId,
                    'accountTo' => (string)$uid,
                    'asset' => 'USDD',
                    'txnAmount' => $feeToRefund,
                    'valueUSD' => $feeToRefund,
                    'currencyCodeFrom' => 'USD',
                    'currencyCodeTo' => 'USD',
                    'reason' => $usddReason,
                ]);

                if (($usddReversal['status'] ?? 'error') !== 'success') {
                    throw new Exception('Failed to reverse USDD fee: ' . ($usddReversal['message'] ?? 'Unknown error'));
                }
                $usddReversalHash = $usddReversal['txnID'] ?? $usddReversal['txn_hash'] ?? $usddReversal['hash'] ?? null;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO reversals
                (network, withdrawal_id, uid, dbv_amount, usdd_amount, dbv_txn_hash, usdd_txn_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $network,
                $withdrawalId,
                $uid,
                $amount,
                $feeToRefund,
                $dbvTxnHash,
                $usddReversalHash,
            ]);

            $stmt = $this->pdo->prepare("UPDATE {$table} SET status = 9 WHERE id = ?");
            $stmt->execute([$withdrawalId]);

            $this->pdo->commit();

            Logger::info('Reversal processed successfully', [
                'network' => $network,
                'withdrawal_id' => $withdrawalId,
                'uid' => $uid,
                'dbv_amount' => $amount,
                'usdd_amount' => $feeToRefund,
                'refund_usdd_fee' => $refundUsddFee,
                'reversal_kind' => $reversalKind,
            ]);

            return [
                'success' => true,
                'message' => 'Reversal processed successfully',
                'dbv_amount' => $amount,
                'usdd_amount' => $feeToRefund,
                'refund_usdd_fee' => $refundUsddFee,
                'reversal_kind' => $reversalKind,
                'dbv_txn_hash' => $dbvTxnHash,
                'usdd_txn_hash' => $usddReversalHash,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function reversalErrorResponse(string $network, int $withdrawalId, Exception $e): array
    {
        Logger::error('Reversal failed', [
            'network' => $network,
            'withdrawal_id' => $withdrawalId,
            'error' => $e->getMessage(),
        ]);
        return [
            'success' => false,
            'message' => 'Reversal failed: ' . $e->getMessage(),
        ];
    }

    /**
     * Get all reversals for a user
     */
    public function getUserReversals(int $uid): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM reversals
            WHERE uid = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get reversal statistics
     */
    public function getReversalStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_reversals,
                SUM(dbv_amount) as total_dbv_reversed,
                SUM(usdd_amount) as total_usdd_reversed
            FROM reversals
            WHERE status = 'completed'
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
