-- Add is_manual column to withdraw tables
-- When manual_withdraw_enabled, new withdrawals get is_manual=1 and are excluded from worker auto-processing
-- Worker only processes rows where is_manual = 0 OR is_manual IS NULL
-- Run once; if column already exists, MySQL will error (safe to ignore).
ALTER TABLE stellar_withdraw ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE binance_withdraw ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE ethereum_withdraw ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0;
