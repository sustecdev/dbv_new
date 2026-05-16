#!/bin/bash
# Transaction Statistics Query Script

echo "=== TRANSACTION STATISTICS ==="
echo ""

# Stellar
echo "STELLAR:"
mysql -u user957432 -p'Xf4IqB3mO*ENTZo' digital -e "
SELECT 'Deposits' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM stellar_deposit
UNION ALL
SELECT 'Withdrawals' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM stellar_withdraw;
" -t

echo ""
echo "BINANCE (BSC):"
mysql -u user957432 -p'Xf4IqB3mO*ENTZo' digital -e "
SELECT 'Deposits' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM binance_deposit
UNION ALL
SELECT 'Withdrawals' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM binance_withdraw;
" -t

echo ""
echo "ETHEREUM:"
mysql -u user957432 -p'Xf4IqB3mO*ENTZo' digital -e "
SELECT 'Deposits' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM ethereum_deposit
UNION ALL
SELECT 'Withdrawals' as Type, COUNT(*) as Count, COALESCE(SUM(amount), 0) as Total FROM ethereum_withdraw;
" -t

echo ""
echo "=== TOTALS ==="
mysql -u user957432 -p'Xf4IqB3mO*ENTZo' digital -e "
SELECT 
    'Deposits' as Type,
    (SELECT COUNT(*) FROM stellar_deposit) + 
    (SELECT COUNT(*) FROM binance_deposit) + 
    (SELECT COUNT(*) FROM ethereum_deposit) as Count,
    (SELECT COALESCE(SUM(amount), 0) FROM stellar_deposit) + 
    (SELECT COALESCE(SUM(amount), 0) FROM binance_deposit) + 
    (SELECT COALESCE(SUM(amount), 0) FROM ethereum_deposit) as Total
UNION ALL
SELECT 
    'Withdrawals' as Type,
    (SELECT COUNT(*) FROM stellar_withdraw) + 
    (SELECT COUNT(*) FROM binance_withdraw) + 
    (SELECT COUNT(*) FROM ethereum_withdraw) as Count,
    (SELECT COALESCE(SUM(amount), 0) FROM stellar_withdraw) + 
    (SELECT COALESCE(SUM(amount), 0) FROM binance_withdraw) + 
    (SELECT COALESCE(SUM(amount), 0) FROM ethereum_withdraw) as Total;
" -t

echo ""
echo "=== WITHDRAWAL STATUS ==="
mysql -u user957432 -p'Xf4IqB3mO*ENTZo' digital -e "
SELECT 
    CASE status
        WHEN 0 THEN 'Pending'
        WHEN 1 THEN 'Processing'
        WHEN 2 THEN 'Failed'
        WHEN 3 THEN 'Completed'
        WHEN 8 THEN 'Pre-Complete'
        WHEN 9 THEN 'Cancelled'
        ELSE CONCAT('Unknown (', status, ')')
    END as Status,
    COUNT(*) as Count
FROM (
    SELECT status FROM stellar_withdraw
    UNION ALL
    SELECT status FROM binance_withdraw
    UNION ALL
    SELECT status FROM ethereum_withdraw
) as all_withdrawals
GROUP BY status
ORDER BY status;
" -t
