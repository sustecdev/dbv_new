-- Migration: Add referral commission columns to withdrawal tables
-- Used for audit and reconciliation of referral commission payouts
-- Run: mysql -u user -p database < add_referral_columns.sql
-- Or execute via phpMyAdmin / your DB tool

-- Stellar withdrawals
ALTER TABLE `stellar_withdraw` 
ADD COLUMN `referrer_uid` INT NULL DEFAULT NULL AFTER `fee_hash_yemchain`,
ADD COLUMN `referral_commission_usdd` DECIMAL(10,2) NULL DEFAULT NULL AFTER `referrer_uid`,
ADD COLUMN `referral_commission_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `referral_commission_usdd`;

-- Binance withdrawals
ALTER TABLE `binance_withdraw` 
ADD COLUMN `referrer_uid` INT NULL DEFAULT NULL AFTER `fee_hash_yemchain`,
ADD COLUMN `referral_commission_usdd` DECIMAL(10,2) NULL DEFAULT NULL AFTER `referrer_uid`,
ADD COLUMN `referral_commission_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `referral_commission_usdd`;

-- Ethereum withdrawals
ALTER TABLE `ethereum_withdraw` 
ADD COLUMN `referrer_uid` INT NULL DEFAULT NULL AFTER `fee_hash_yemchain`,
ADD COLUMN `referral_commission_usdd` DECIMAL(10,2) NULL DEFAULT NULL AFTER `referrer_uid`,
ADD COLUMN `referral_commission_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `referral_commission_usdd`;
