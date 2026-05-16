-- Add processed_by_admin_uid to withdrawal tables
-- Stores which admin UID manually completed the withdrawal (for manual mode)
-- Run: mysql -u root -p digital < database/add_processed_by_admin_uid.sql

ALTER TABLE stellar_withdraw ADD COLUMN processed_by_admin_uid INT NULL DEFAULT NULL AFTER status;
ALTER TABLE binance_withdraw ADD COLUMN processed_by_admin_uid INT NULL DEFAULT NULL AFTER status;
ALTER TABLE ethereum_withdraw ADD COLUMN processed_by_admin_uid INT NULL DEFAULT NULL AFTER status;
