<?php
require_once __DIR__ . '/app/Support/Database.php';
$config = require __DIR__ . '/app/Config/config.php';

try {
    $pdo = Database::pdo($config['db']);
    
    echo "=== TRANSACTION STATISTICS ===\n\n";
    
    // Stellar Deposits
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM stellar_deposit");
    $stellarDeposits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Stellar Withdrawals
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM stellar_withdraw");
    $stellarWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Binance Deposits
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM binance_deposit");
    $binanceDeposits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Binance Withdrawals
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM binance_withdraw");
    $binanceWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ethereum Deposits
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM ethereum_deposit");
    $ethereumDeposits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ethereum Withdrawals
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM ethereum_withdraw");
    $ethereumWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Display results
    echo "STELLAR:\n";
    echo "  Deposits:    {$stellarDeposits['count']} transactions | " . number_format($stellarDeposits['total'], 2) . " DBV\n";
    echo "  Withdrawals: {$stellarWithdrawals['count']} transactions | " . number_format($stellarWithdrawals['total'], 2) . " DBV\n\n";
    
    echo "BINANCE (BSC):\n";
    echo "  Deposits:    {$binanceDeposits['count']} transactions | " . number_format($binanceDeposits['total'], 2) . " DBV\n";
    echo "  Withdrawals: {$binanceWithdrawals['count']} transactions | " . number_format($binanceWithdrawals['total'], 2) . " DBV\n\n";
    
    echo "ETHEREUM:\n";
    echo "  Deposits:    {$ethereumDeposits['count']} transactions | " . number_format($ethereumDeposits['total'], 2) . " DBV\n";
    echo "  Withdrawals: {$ethereumWithdrawals['count']} transactions | " . number_format($ethereumWithdrawals['total'], 2) . " DBV\n\n";
    
    // Totals
    $totalDeposits = $stellarDeposits['count'] + $binanceDeposits['count'] + $ethereumDeposits['count'];
    $totalDepositVolume = $stellarDeposits['total'] + $binanceDeposits['total'] + $ethereumDeposits['total'];
    
    $totalWithdrawals = $stellarWithdrawals['count'] + $binanceWithdrawals['count'] + $ethereumWithdrawals['count'];
    $totalWithdrawalVolume = $stellarWithdrawals['total'] + $binanceWithdrawals['total'] + $ethereumWithdrawals['total'];
    
    echo "=== TOTALS ===\n";
    echo "Total Deposits:    {$totalDeposits} transactions | " . number_format($totalDepositVolume, 2) . " DBV\n";
    echo "Total Withdrawals: {$totalWithdrawals} transactions | " . number_format($totalWithdrawalVolume, 2) . " DBV\n";
    echo "Net Flow:          " . number_format($totalDepositVolume - $totalWithdrawalVolume, 2) . " DBV\n\n";
    
    // Status breakdown for withdrawals
    echo "=== WITHDRAWAL STATUS ===\n";
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count FROM (
            SELECT status FROM stellar_withdraw
            UNION ALL
            SELECT status FROM binance_withdraw
            UNION ALL
            SELECT status FROM ethereum_withdraw
        ) as all_withdrawals
        GROUP BY status
        ORDER BY status
    ");
    
    $statusMap = [
        0 => 'Pending',
        1 => 'Processing',
        2 => 'Failed',
        3 => 'Completed',
        8 => 'Pre-Complete',
        9 => 'Cancelled'
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusName = $statusMap[$row['status']] ?? "Unknown ({$row['status']})";
        echo "  {$statusName}: {$row['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
