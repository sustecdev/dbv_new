<?php
/**
 * Migration: Add referral commission columns to withdrawal tables
 * - referrer_uid: UID who received commission
 * - referral_commission_usdd: amount paid (0.50 when applicable)
 * - referral_commission_hash: YEMChain hash for the commission transfer
 *
 * Run: php database/run_add_referral_columns.php
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
    $columns = [
        'referrer_uid' => 'INT NULL DEFAULT NULL',
        'referral_commission_usdd' => 'DECIMAL(10,2) NULL DEFAULT NULL',
        'referral_commission_hash' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];

    $colOrder = ['referrer_uid' => 'fee_hash_yemchain', 'referral_commission_usdd' => 'referrer_uid', 'referral_commission_hash' => 'referral_commission_usdd'];

    foreach ($tables as $table) {
        echo "Processing $table...\n";
        foreach ($columns as $col => $def) {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($check->rowCount() > 0) {
                echo "  ⚠ $col already exists, skipping\n";
                continue;
            }
            $after = $colOrder[$col] ?? 'fee_hash_yemchain';
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def AFTER `$after`");
            echo "  ✓ Added $col\n";
        }
    }

    echo "\n✅ Migration completed.\n";
} catch (Throwable $e) {
    die("Error: " . $e->getMessage() . "\n");
}
