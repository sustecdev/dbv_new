-- Migration: Change Binance and Ethereum amount columns from DECIMAL(20,8) to DECIMAL(20,2)
-- This matches the display format and Stellar's 2-decimal precision

-- Binance Deposits
ALTER TABLE `binance_deposit` 
MODIFY COLUMN `amount` DECIMAL(20,2) NOT NULL;

-- Binance Withdrawals
ALTER TABLE `binance_withdraw` 
MODIFY COLUMN `amount` DECIMAL(20,2) NOT NULL;

-- Ethereum Deposits
ALTER TABLE `ethereum_deposit` 
MODIFY COLUMN `amount` DECIMAL(20,2) NOT NULL;

-- Ethereum Withdrawals
ALTER TABLE `ethereum_withdraw` 
MODIFY COLUMN `amount` DECIMAL(20,2) NOT NULL;

