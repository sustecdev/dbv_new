<?php
/**
 * Setup script to create the audit_log table
 * Run once after deploy
 */

session_start();

require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/PathHelper.php';
require_once __DIR__ . '/../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../app/Config/config.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    die('❌ Unauthorized - Admin access required');
}

echo "<h1>🔧 Audit Log Setup</h1><pre>";

try {
    $pdo = Database::pdo($config['db']);
    $sql = "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        admin_uid INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id VARCHAR(100) DEFAULT NULL,
        details JSON DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        INDEX idx_admin_uid (admin_uid),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);

    $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
    if ($stmt->rowCount() > 0) {
        echo "✅ audit_log table created successfully.\n";
    } else {
        echo "❌ Table creation failed.\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . htmlspecialchars($e->getMessage()) . "\n";
}
echo "</pre><p><a href='" . (PathHelper::url('admin.php') ?? '/admin.php') . "'>← Back to Admin</a></p>";
