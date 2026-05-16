<?php
/**
 * Verify that fee columns were added successfully
 */

require_once __DIR__ . '/../app/Config/config.php';

$db = $config['db'] ?? [];
$host = $db['host'] ?? 'localhost';
$dbname = $db['name'] ?? 'digital';
$username = $db['user'] ?? 'root';
$password = $db['pass'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tables = [
        'stellar_withdraw' => 'Stellar',
        'binance_withdraw' => 'Binance',
        'ethereum_withdraw' => 'Ethereum'
    ];
    
    echo "Verifying fee columns in withdrawal tables...\n\n";
    
    $allGood = true;
    
    foreach ($tables as $table => $network) {
        echo "$network ($table):\n";
        
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field IN ('fee_usdd', 'fee_hash_yemchain')");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($columns) === 2) {
                foreach ($columns as $col) {
                    $default = $col['Default'] ?? 'NULL';
                    echo "  ✓ {$col['Field']}: {$col['Type']} (Default: $default)\n";
                }
            } else {
                $existing = array_column($columns, 'Field');
                if (!in_array('fee_usdd', $existing)) {
                    echo "  ✗ fee_usdd column MISSING\n";
                    $allGood = false;
                }
                if (!in_array('fee_hash_yemchain', $existing)) {
                    echo "  ✗ fee_hash_yemchain column MISSING\n";
                    $allGood = false;
                }
            }
        } catch (PDOException $e) {
            echo "  ✗ Error checking columns: " . $e->getMessage() . "\n";
            $allGood = false;
        }
        
        echo "\n";
    }
    
    if ($allGood) {
        echo "✅ All fee columns are present and correct!\n";
        echo "\nYou can now query fee history:\n";
        echo "  SELECT fee_usdd, fee_hash_yemchain FROM stellar_withdraw WHERE fee_usdd > 0;\n";
        echo "  SELECT fee_usdd, fee_hash_yemchain FROM binance_withdraw WHERE fee_usdd > 0;\n";
        echo "  SELECT fee_usdd, fee_hash_yemchain FROM ethereum_withdraw WHERE fee_usdd > 0;\n";
    } else {
        echo "⚠ Some columns are missing. Please check the migration.\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}

