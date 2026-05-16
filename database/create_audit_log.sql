-- Audit log for admin actions
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_uid INT NOT NULL COMMENT 'Admin user ID who performed the action',
    action VARCHAR(100) NOT NULL COMMENT 'e.g. reversal, backup, clear_sessions',
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'e.g. withdrawal, database',
    entity_id VARCHAR(100) DEFAULT NULL COMMENT 'e.g. withdrawal ID',
    details JSON DEFAULT NULL COMMENT 'Additional context',
    ip VARCHAR(45) DEFAULT NULL,
    INDEX idx_admin_uid (admin_uid),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for admin actions';
