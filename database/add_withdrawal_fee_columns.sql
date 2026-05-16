-- Migration: Add fee_usdd and fee_hash_yemchain columns to withdrawal tables
-- This captures the USDD withdrawal fee amount and YEMChain transaction hash

-- Stellar withdrawals
ALTER TABLE `stellar_withdraw` 
ADD COLUMN IF NOT EXISTS `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`,
ADD COLUMN IF NOT EXISTS `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;

-- Binance withdrawals
ALTER TABLE `binance_withdraw` 
ADD COLUMN IF NOT EXISTS `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`,
ADD COLUMN IF NOT EXISTS `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;

-- Ethereum withdrawals
ALTER TABLE `ethereum_withdraw` 
ADD COLUMN IF NOT EXISTS `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`,
ADD COLUMN IF NOT EXISTS `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;

-- Note: If your MySQL version doesn't support IF NOT EXISTS in ALTER TABLE,
-- run these separately and handle errors gracefully:
-- 
-- ALTER TABLE `stellar_withdraw` ADD COLUMN `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`;
-- ALTER TABLE `stellar_withdraw` ADD COLUMN `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;
-- 
-- ALTER TABLE `binance_withdraw` ADD COLUMN `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`;
-- ALTER TABLE `binance_withdraw` ADD COLUMN `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;
-- 
-- ALTER TABLE `ethereum_withdraw` ADD COLUMN `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`;
-- ALTER TABLE `ethereum_withdraw` ADD COLUMN `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`;

