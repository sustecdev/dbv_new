-- Application settings table for admin-configurable options
-- Used for features like Manual Withdraw Mode toggle
CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(64) PRIMARY KEY,
    v TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default: manual withdraw disabled (0 = auto, 1 = manual)
INSERT INTO app_settings (k, v) VALUES ('manual_withdraw_enabled', '0')
ON DUPLICATE KEY UPDATE k = k;

-- Admin UIDs: JSON array of additional admin UIDs (1290033 is always admin)
INSERT INTO app_settings (k, v) VALUES ('admin_uids', '[]')
ON DUPLICATE KEY UPDATE k = k;
