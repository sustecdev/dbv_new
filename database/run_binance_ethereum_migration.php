<?php
/**
 * Database Migration Script
 * Creates Binance and Ethereum tables
 * 
 * Usage: php run_binance_ethereum_migration.php
 */

// Load config only once
$configPath = __DIR__ . '/../app/Config/config.php';
if (!isset($GLOBALS['dbnew_config_loaded'])) {
    $config = require $configPath;
    $GLOBALS['dbnew_config_loaded'] = true;
} else {
    $config = $GLOBALS['dbnew_config'];
}

require_once __DIR__ . '/../app/Support/Database.php';

try {
    $pdo = Database::pdo($config['db']);
    
    echo "Starting database migration...\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/binance_ethereum_schema.sql';
    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed statement\n";
        } catch (PDOException $e) {
            // Ignore "table already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Error: " . $e->getMessage() . "\n";
            } else {
                echo "⚠ Table already exists (skipping)\n";
            }
        }
    }
    
    // Verify tables were created
    echo "\nVerifying tables...\n";
    $tables = ['binance_deposit', 'binance_withdraw', 'ethereum_deposit', 'ethereum_withdraw'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Table '$table' exists\n";
            
            // Show table structure
            $desc = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            echo "  Columns: " . count($desc) . "\n";
        } else {
            echo "✗ Table '$table' NOT found\n";
        }
    }
    
    echo "\nMigration completed!\n";
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

