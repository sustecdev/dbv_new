-- Add error_message column to store failure reason for failed withdrawals
-- Run: php database/add_error_message_migration.php
-- Or manually (MySQL does not support IF NOT EXISTS for ADD COLUMN):

-- ALTER TABLE stellar_withdraw ADD COLUMN error_message VARCHAR(500) DEFAULT NULL AFTER status;
-- ALTER TABLE binance_withdraw ADD COLUMN error_message VARCHAR(500) DEFAULT NULL AFTER status;
-- ALTER TABLE ethereum_withdraw ADD COLUMN error_message VARCHAR(500) DEFAULT NULL AFTER status;
