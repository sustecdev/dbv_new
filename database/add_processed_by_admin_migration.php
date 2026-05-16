<?php
/**
 * Migration: Add processed_by_admin_uid column to withdrawal tables
 * Stores which admin manually completed the withdrawal (for manual mode)
 *
 * Run: php database/add_processed_by_admin_migration.php
 */

$config = require __DIR__ . '/../app/Config/config.php';
$db = $config['db'] ?? [];
$host = $db['host'] ?? 'localhost';
$dbname = $db['name'] ?? 'digital';
$username = $db['user'] ?? 'root';
$password = $db['pass'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database: $dbname\n\n";

    $tables = ['stellar_withdraw', 'binance_withdraw', 'ethereum_withdraw'];

    foreach ($tables as $table) {
        echo "Processing $table...\n";
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'processed_by_admin_uid'");
        if ($check->rowCount() > 0) {
            echo "  ⚠ processed_by_admin_uid already exists, skipping\n";
            continue;
        }
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `processed_by_admin_uid` INT NULL DEFAULT NULL AFTER `status`");
        echo "  ✓ Added processed_by_admin_uid column\n";
    }

    echo "\n✅ Migration completed.\n";
} catch (Throwable $e) {
    die("Error: " . $e->getMessage() . "\n");
}
