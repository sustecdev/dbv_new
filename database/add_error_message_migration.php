<?php
/**
 * Migration: Add error_message column to withdrawal tables
 * Stores failure reason when status = 2 (failed)
 *
 * Run: php database/add_error_message_migration.php
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

    $tables = [
        'stellar_withdraw' => 'Stellar',
        'binance_withdraw' => 'Binance',
        'ethereum_withdraw' => 'Ethereum'
    ];

    foreach ($tables as $table => $network) {
        echo "Processing $network ($table)...\n";

        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'error_message'");
        if ($check->rowCount() > 0) {
            echo "  ⚠ error_message already exists, skipping\n";
            continue;
        }

        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `error_message` VARCHAR(500) DEFAULT NULL AFTER `status`");
        echo "  ✓ Added error_message column\n";
    }

    echo "\n✅ Migration completed.\n";
} catch (Throwable $e) {
    die("Error: " . $e->getMessage() . "\n");
}
