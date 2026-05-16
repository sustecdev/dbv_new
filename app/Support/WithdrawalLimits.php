<?php

/**
 * Withdrawal Limits Helper
 * Provides methods to check daily withdrawal limits for each network
 */
class WithdrawalLimits
{
    /**
     * Get total withdrawals for today for a specific user and network
     * 
     * @param PDO $pdo Database connection
     * @param int $uid User ID
     * @param string $network 'stellar', 'binance', or 'ethereum'
     * @return float Total amount withdrawn today by this user
     */
    public static function getTodayTotal(PDO $pdo, int $uid, string $network): float
    {
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            
            switch ($network) {
                case 'stellar':
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) as total 
                        FROM stellar_withdraw 
                        WHERE uid = :uid
                        AND created_at >= :start 
                        AND created_at <= :end 
                        AND status IN (0, 1, 3, 8)
                    ");
                    break;
                    
                case 'binance':
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) as total 
                        FROM binance_withdraw 
                        WHERE uid = :uid
                        AND created_at >= :start 
                        AND created_at <= :end 
                        AND status IN (0, 1, 3, 8)
                    ");
                    break;
                    
                case 'ethereum':
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) as total 
                        FROM ethereum_withdraw 
                        WHERE uid = :uid
                        AND created_at >= :start 
                        AND created_at <= :end 
                        AND status IN (0, 1, 3, 8)
                    ");
                    break;
                    
                default:
                    return 0.0;
            }
            
            $stmt->execute([
                'uid' => $uid,
                'start' => $todayStart,
                'end' => $todayEnd
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['total'] ?? 0.0);
        } catch (PDOException $e) {
            // Log database errors but don't block withdrawals
            error_log("WithdrawalLimits::getTodayTotal database error: " . $e->getMessage());
            // Return 0 to allow withdrawals if database query fails (fail-open for safety)
            return 0.0;
        } catch (Exception $e) {
            error_log("WithdrawalLimits::getTodayTotal error: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Check if a withdrawal would exceed the daily limit for a specific user
     * 
     * @param PDO $pdo Database connection
     * @param int $uid User ID
     * @param string $network 'stellar', 'binance', or 'ethereum'
     * @param float $amount Amount to withdraw
     * @param float $dailyLimit Daily limit (0 = unlimited)
     * @param int[] $dailyLimitBypassUids User IDs exempt from daily limit (typically from withdrawal.daily_limit_bypass_uids)
     * @return array ['allowed' => bool, 'today_total' => float, 'remaining' => float, 'message' => string]
     */
    public static function checkLimit(PDO $pdo, int $uid, string $network, float $amount, float $dailyLimit, array $dailyLimitBypassUids = []): array
    {
        try {
            // Validate inputs
            if ($amount < 0) {
                return [
                    'allowed' => false,
                    'today_total' => 0.0,
                    'remaining' => 0.0,
                    'message' => 'Invalid withdrawal amount'
                ];
            }

            if ($dailyLimitBypassUids !== [] && in_array($uid, $dailyLimitBypassUids, true)) {
                $todayTotal = self::getTodayTotal($pdo, $uid, $network);
                return [
                    'allowed' => true,
                    'today_total' => $todayTotal,
                    'remaining' => PHP_FLOAT_MAX,
                    'message' => '',
                ];
            }
            
            // If limit is 0 or less, unlimited withdrawals
            if ($dailyLimit <= 0) {
                return [
                    'allowed' => true,
                    'today_total' => 0.0,
                    'remaining' => PHP_FLOAT_MAX,
                    'message' => ''
                ];
            }
            
            $todayTotal = self::getTodayTotal($pdo, $uid, $network);
            $remaining = max(0, $dailyLimit - $todayTotal);
            
            if ($todayTotal + $amount > $dailyLimit) {
                return [
                    'allowed' => false,
                    'today_total' => $todayTotal,
                    'remaining' => $remaining,
                    'message' => sprintf(
                        'Daily withdrawal limit exceeded. Today\'s total: %s DBV, Limit: %s DBV, Remaining: %s DBV',
                        number_format($todayTotal, 2),
                        number_format($dailyLimit, 2),
                        number_format($remaining, 2)
                    )
                ];
            }
            
            return [
                'allowed' => true,
                'today_total' => $todayTotal,
                'remaining' => $remaining - $amount,
                'message' => ''
            ];
        } catch (Exception $e) {
            // On error, log but allow withdrawal (fail-open for safety)
            error_log("WithdrawalLimits::checkLimit error: " . $e->getMessage());
            return [
                'allowed' => true,
                'today_total' => 0.0,
                'remaining' => PHP_FLOAT_MAX,
                'message' => ''
            ];
        }
    }
}

