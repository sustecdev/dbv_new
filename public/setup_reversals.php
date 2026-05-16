<?php
/**
 * Setup script to create the reversals table
 * Run this once: https://digitalbenefits.exchange/public/setup_reversals.php
 */

session_start();

require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../app/Config/config.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    die('❌ Unauthorized - Admin access required');
}

echo "<h1>🔧 Reversals Table Setup</h1>";
echo "<pre>";

try {
    $pdo = Database::pdo($config['db']);
    
    echo "📊 Creating reversals table...\n\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS reversals (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks reversals for failed withdrawal transactions'";
    
    $pdo->exec($sql);
    
    echo "✅ SUCCESS! Reversals table created.\n\n";
    
    // Verify table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reversals'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table verified: reversals exists\n\n";
        
        // Show table structure
        echo "📋 Table Structure:\n";
        echo "-------------------\n";
        $stmt = $pdo->query("DESCRIBE reversals");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            echo sprintf("%-20s %-30s %s\n", 
                $col['Field'], 
                $col['Type'], 
                $col['Null'] == 'YES' ? 'NULL' : 'NOT NULL'
            );
        }
        
        echo "\n✅ Setup complete! You can now use the Reverse button in the admin dashboard.\n";
        echo "\n🗑️  You can safely delete this file now: /public/setup_reversals.php\n";
    } else {
        echo "❌ ERROR: Table creation failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='/public/admin.php'>← Back to Admin Dashboard</a></p>";
