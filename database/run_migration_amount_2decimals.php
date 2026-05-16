<?php
/**
 * Migration Script: Update amount columns to 2 decimal places
 * 
 * This script updates the stellar_deposit and stellar_withdraw tables
 * to store amounts with exactly 2 decimal places.
 * 
 * Usage: php database/run_migration_amount_2decimals.php
 */

require_once __DIR__ . '/../app/Support/Database.php';
$config = require __DIR__ . '/../app/Config/config.php';

try {
    $pdo = Database::pdo($config['db']);
    
    echo "Starting migration: Update amount columns to 2 decimal places...\n\n";
    
    // Check current column definitions
    $checkDeposit = $pdo->query("SHOW COLUMNS FROM stellar_deposit WHERE Field = 'amount'")->fetch(PDO::FETCH_ASSOC);
    $checkWithdraw = $pdo->query("SHOW COLUMNS FROM stellar_withdraw WHERE Field = 'amount'")->fetch(PDO::FETCH_ASSOC);
    
    echo "Current definitions:\n";
    echo "  stellar_deposit.amount: " . ($checkDeposit['Type'] ?? 'N/A') . "\n";
    echo "  stellar_withdraw.amount: " . ($checkWithdraw['Type'] ?? 'N/A') . "\n\n";
    
    // Update stellar_deposit table
    echo "Updating stellar_deposit.amount to DECIMAL(32,2)...\n";
    $pdo->exec("ALTER TABLE stellar_deposit MODIFY COLUMN amount DECIMAL(32,2) NOT NULL DEFAULT 0");
    echo "  ✓ Updated successfully\n\n";
    
    // Update stellar_withdraw table
    echo "Updating stellar_withdraw.amount to DECIMAL(32,2)...\n";
    $pdo->exec("ALTER TABLE stellar_withdraw MODIFY COLUMN amount DECIMAL(32,2) NOT NULL DEFAULT 0");
    echo "  ✓ Updated successfully\n\n";
    
    // Round existing data to 2 decimals
    echo "Rounding existing data to 2 decimal places...\n";
    
    $countDeposit = $pdo->exec("UPDATE stellar_deposit SET amount = ROUND(amount, 2)");
    echo "  ✓ Updated $countDeposit deposit record(s)\n";
    
    $countWithdraw = $pdo->exec("UPDATE stellar_withdraw SET amount = ROUND(amount, 2)");
    echo "  ✓ Updated $countWithdraw withdrawal record(s)\n\n";
    
    // Verify new definitions
    $checkDeposit = $pdo->query("SHOW COLUMNS FROM stellar_deposit WHERE Field = 'amount'")->fetch(PDO::FETCH_ASSOC);
    $checkWithdraw = $pdo->query("SHOW COLUMNS FROM stellar_withdraw WHERE Field = 'amount'")->fetch(PDO::FETCH_ASSOC);
    
    echo "New definitions:\n";
    echo "  stellar_deposit.amount: " . ($checkDeposit['Type'] ?? 'N/A') . "\n";
    echo "  stellar_withdraw.amount: " . ($checkWithdraw['Type'] ?? 'N/A') . "\n\n";
    
    echo "✓ Migration completed successfully!\n";
    echo "\nNote: All new deposits and withdrawals will automatically be stored with 2 decimal places.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

