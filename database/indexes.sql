-- Database performance indexes
-- Run this to improve query performance

-- Indexes for stellar_deposit table
CREATE INDEX IF NOT EXISTS idx_deposit_uid_created ON stellar_deposit(uid, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_deposit_hash ON stellar_deposit(txn_hash_stellar);
CREATE INDEX IF NOT EXISTS idx_deposit_status ON stellar_deposit(status);
CREATE INDEX IF NOT EXISTS idx_deposit_uid_status ON stellar_deposit(uid, status);

-- Indexes for stellar_withdraw table
CREATE INDEX IF NOT EXISTS idx_withdraw_uid_created ON stellar_withdraw(uid, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_withdraw_status_trustline ON stellar_withdraw(status, trustline);
CREATE INDEX IF NOT EXISTS idx_withdraw_status_created ON stellar_withdraw(status, created_at);
CREATE INDEX IF NOT EXISTS idx_withdraw_uid_status ON stellar_withdraw(uid, status);

-- Composite index for worker queries (status=0, trustline=1)
CREATE INDEX IF NOT EXISTS idx_withdraw_worker ON stellar_withdraw(status, trustline, created_at);

