<?php
/**
 * Run migration to add fee_usdd and fee_hash_yemchain columns
 */

// Load database config
require_once __DIR__ . '/../app/Config/config.php';

$db = $config['db'] ?? [];
$host = $db['host'] ?? 'localhost';
$dbname = $db['name'] ?? 'digital';
$username = $db['user'] ?? 'root';
$password = $db['pass'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database: $dbname\n\n";
    
    $tables = [
        'stellar_withdraw' => 'Stellar',
        'binance_withdraw' => 'Binance',
        'ethereum_withdraw' => 'Ethereum'
    ];
    
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($tables as $table => $network) {
        echo "Processing $network withdrawals table ($table)...\n";
        
        // Check if columns already exist
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'fee_usdd'");
            if ($check->rowCount() > 0) {
                echo "  ⚠ fee_usdd column already exists, skipping...\n";
                $skippedCount++;
                continue;
            }
        } catch (PDOException $e) {
            // Table might not exist or other error, continue
        }
        
        // Add fee_usdd column
        try {
            $sql = "ALTER TABLE `$table` ADD COLUMN `fee_usdd` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount`";
            echo "  Adding fee_usdd column... ";
            $pdo->exec($sql);
            echo "✓ Success\n";
            $successCount++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Column already exists, skipping\n";
                $skippedCount++;
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
        
        // Check if fee_hash_yemchain already exists
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'fee_hash_yemchain'");
            if ($check->rowCount() > 0) {
                echo "  ⚠ fee_hash_yemchain column already exists, skipping...\n";
                $skippedCount++;
                continue;
            }
        } catch (PDOException $e) {
            // Continue
        }
        
        // Add fee_hash_yemchain column
        try {
            $sql = "ALTER TABLE `$table` ADD COLUMN `fee_hash_yemchain` VARCHAR(255) DEFAULT NULL AFTER `fee_usdd`";
            echo "  Adding fee_hash_yemchain column... ";
            $pdo->exec($sql);
            echo "✓ Success\n";
            $successCount++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Column already exists, skipping\n";
                $skippedCount++;
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
        
        echo "\n";
    }
    
    echo str_repeat("=", 60) . "\n";
    echo "Migration Summary:\n";
    echo "✓ Successful: $successCount\n";
    if ($skippedCount > 0) {
        echo "⚠ Skipped (already exists): $skippedCount\n";
    }
    if ($errorCount > 0) {
        echo "✗ Errors: $errorCount\n";
    }
    echo str_repeat("=", 60) . "\n";
    
    if ($errorCount === 0) {
        echo "\n✅ Migration completed successfully!\n";
        echo "\nNew withdrawals will now store:\n";
        echo "- fee_usdd: The USDD fee amount (DECIMAL(10,2))\n";
        echo "- fee_hash_yemchain: The YEMChain fee transaction hash (VARCHAR(255))\n";
        echo "\nYou can now query fee history from the database!\n";
    } else {
        echo "\n⚠ Migration completed with errors. Please review above.\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
