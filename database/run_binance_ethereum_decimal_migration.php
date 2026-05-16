<?php
/**
 * Run migration to change Binance and Ethereum amounts to 2 decimals
 */

// Load config
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    $envFile = __DIR__ . '/../env.example';
}

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $_ENV[$key] = $value;
    }
}

function envv($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

require_once __DIR__ . '/../app/Support/Database.php';

$config = [
    'db' => [
        'host' => envv('DB_HOST', 'localhost'),
        'name' => envv('DB_NAME', 'Digital'),
        'user' => envv('DB_USER', 'root'),
        'pass' => envv('DB_PASS', ''),
        'charset' => envv('DB_CHARSET', 'utf8mb4')
    ]
];

try {
    $pdo = Database::pdo($config['db']);
    
    echo "🔄 Starting Binance/Ethereum Decimal Migration...\n\n";
    
    // Tables to migrate
    $tables = [
        'binance_deposit',
        'binance_withdraw',
        'ethereum_deposit',
        'ethereum_withdraw'
    ];
    
    foreach ($tables as $table) {
        echo "📊 Checking table: {$table}...\n";
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            echo "  ⚠️  Table {$table} does not exist, skipping...\n\n";
            continue;
        }
        
        // Check current column definition
        $stmt = $pdo->query("DESCRIBE `{$table}` `amount`");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$column) {
            echo "  ⚠️  Column 'amount' not found, skipping...\n\n";
            continue;
        }
        
        $currentType = $column['Type'];
        echo "  Current type: {$currentType}\n";
        
        // Check if already DECIMAL(20,2)
        if (strpos($currentType, 'decimal(20,2)') !== false || strpos(strtolower($currentType), 'decimal(20,2)') !== false) {
            echo "  ✅ Already DECIMAL(20,2), no change needed\n\n";
            continue;
        }
        
        // Get current data count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  Records in table: {$count}\n";
        
        // Alter column
        try {
            $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `amount` DECIMAL(20,2) NOT NULL";
            echo "  🔄 Executing: {$sql}\n";
            
            $pdo->exec($sql);
            
            // Verify change
            $stmt = $pdo->query("DESCRIBE `{$table}` `amount`");
            $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);
            $newType = $newColumn['Type'];
            echo "  ✅ New type: {$newType}\n";
            
            // Show sample data to verify rounding
            if ($count > 0) {
                $stmt = $pdo->query("SELECT id, amount FROM `{$table}` ORDER BY id DESC LIMIT 3");
                $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "  Sample amounts (after migration):\n";
                foreach ($samples as $sample) {
                    echo "    - ID {$sample['id']}: {$sample['amount']}\n";
                }
            }
            
            echo "  ✅ Migration completed for {$table}\n\n";
            
        } catch (PDOException $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "✨ Migration completed!\n";
    echo "\n📝 Note: Existing amounts will be rounded to 2 decimal places.\n";
    echo "   This matches the display format already used in the frontend.\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
