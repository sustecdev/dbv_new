-- Create reversals table to track transaction reversals
CREATE TABLE IF NOT EXISTS reversals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    network VARCHAR(20) NOT NULL COMMENT 'stellar, binance, or ethereum',
    withdrawal_id INT NOT NULL COMMENT 'ID from the respective withdrawal table',
    uid INT NOT NULL COMMENT 'User ID',
    dbv_amount DECIMAL(20, 8) NOT NULL COMMENT 'DBV amount reversed',
    usdd_amount DECIMAL(20, 8) DEFAULT 0 COMMENT 'USDD fee amount reversed',
    dbv_txn_hash VARCHAR(255) DEFAULT NULL COMMENT 'YEMChain transaction hash for DBV reversal',
    usdd_txn_hash VARCHAR(255) DEFAULT NULL COMMENT 'YEMChain transaction hash for USDD reversal',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, completed, failed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_network_withdrawal (network, withdrawal_id),
    INDEX idx_uid (uid),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks reversals for failed withdrawal transactions';
