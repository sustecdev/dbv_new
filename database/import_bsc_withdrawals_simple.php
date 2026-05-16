<?php
/**
 * Simple BSC Withdrawal Import Script
 * Directly executes the INSERT statements from SQL file
 */

// Load config directly
$config = [];
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

// Build config array
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
    // Connect directly with PDO
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "🔄 Starting BSC withdrawal import...\n\n";
    
    // Check table name
    $stmt = $pdo->query("SHOW TABLES LIKE 'binance_withdraw'");
    if ($stmt->rowCount() > 0) {
        $tableName = 'binance_withdraw';
        echo "✅ Using 'binance_withdraw' table\n";
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'bsc_withdraw'");
        if ($stmt->rowCount() > 0) {
            $tableName = 'bsc_withdraw';
            echo "✅ Using 'bsc_withdraw' table\n";
        } else {
            die("❌ Neither table exists!\n");
        }
    }
    
    // Read SQL file
    $sqlFile = 'C:/Users/Dell/Downloads/bsc_withdraw.sql';
    if (!file_exists($sqlFile)) {
        die("❌ SQL file not found: {$sqlFile}\n");
    }
    
    $sqlContent = file_get_contents($sqlFile);
    $lines = explode("\n", $sqlContent);
    
    // Extract lines 52-108
    $insertLines = [];
    for ($i = 51; $i < 108 && $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (!empty($line)) {
            $insertLines[] = $line;
        }
    }
    
    // Join all lines
    $fullInsert = implode(' ', $insertLines);
    
    // Replace table name if needed
    if ($tableName === 'binance_withdraw') {
        // Map columns: bsc_withdraw -> binance_withdraw
        // bsc_withdraw: id, user_id, amount, recipient_address, txn_hash, txn_hash_yemchain, usdd_fee_hash, status, created_at, request_id, error_message, completed_at, failed_at
        // binance_withdraw: id, uid, address, amount, txn_hash_bsc, txn_hash_yemchain, status, created_at, processed_at, fee_usdd, fee_hash_yemchain
        
        // Extract VALUES part
        if (preg_match('/VALUES\s+(.+?);/is', $fullInsert, $matches)) {
            $valuesPart = $matches[1];
            
            // Parse rows
            $rows = [];
            $currentRow = '';
            $parenDepth = 0;
            $inString = false;
            $stringChar = '';
            
            for ($i = 0; $i < strlen($valuesPart); $i++) {
                $char = $valuesPart[$i];
                
                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($inString && $char === $stringChar && ($i === 0 || $valuesPart[$i-1] !== '\\')) {
                    $inString = false;
                } elseif (!$inString) {
                    if ($char === '(') $parenDepth++;
                    if ($char === ')') $parenDepth--;
                }
                
                $currentRow .= $char;
                
                if (!$inString && $parenDepth === 0 && $char === ')') {
                    $rows[] = trim($currentRow, '(),');
                    $currentRow = '';
                    // Skip comma and whitespace
                    while ($i + 1 < strlen($valuesPart) && (trim($valuesPart[$i+1]) === '' || $valuesPart[$i+1] === ',')) {
                        $i++;
                    }
                }
            }
            
            echo "📊 Found " . count($rows) . " rows to import\n\n";
            
            $imported = 0;
            $skipped = 0;
            $errors = 0;
            
            foreach ($rows as $rowIndex => $rowData) {
                // Parse values using a simple approach: split by comma, handling quoted strings
                $values = [];
                $current = '';
                $inQuote = false;
                $quoteChar = '';
                
                for ($i = 0; $i < strlen($rowData); $i++) {
                    $char = $rowData[$i];
                    
                    if (!$inQuote && ($char === '"' || $char === "'")) {
                        $inQuote = true;
                        $quoteChar = $char;
                    } elseif ($inQuote && $char === $quoteChar && ($i === 0 || $rowData[$i-1] !== '\\')) {
                        $inQuote = false;
                    } elseif (!$inQuote && $char === ',' && substr_count($current, '(') === substr_count($current, ')')) {
                        $values[] = trim($current);
                        $current = '';
                        continue;
                    }
                    
                    $current .= $char;
                }
                
                if (!empty($current)) {
                    $values[] = trim($current);
                }
                
                if (count($values) >= 13) {
                    $id = trim($values[0], "'\"");
                    $userId = trim($values[1], "'\"");
                    $amount = trim($values[2], "'\"");
                    $address = trim($values[3], "'\"");
                    $txnHash = trim($values[4], "'\"");
                    $txnHashYem = trim($values[5], "'\"");
                    $usddFeeHash = trim($values[6], "'\"");
                    $status = trim($values[7], "'\"");
                    $createdAt = trim($values[8], "'\"");
                    
                    // Check if exists
                    $check = $pdo->prepare("SELECT id FROM `{$tableName}` WHERE id = ?");
                    $check->execute([$id]);
                    
                    if ($check->rowCount() > 0) {
                        echo "⏭️  ID {$id} already exists, skipping...\n";
                        $skipped++;
                        continue;
                    }
                    
                    // Prepare insert
                    // Map: user_id -> uid, recipient_address -> address, txn_hash -> txn_hash_bsc
                    $sql = "INSERT INTO `{$tableName}` (`id`, `uid`, `address`, `amount`, `txn_hash_bsc`, `txn_hash_yemchain`, `status`, `created_at`";
                    $placeholders = "VALUES (?, ?, ?, ?, ?, ?, ?, ?";
                    $insertValues = [$id, $userId, $address, $amount, $txnHash === 'NULL' ? null : $txnHash, $txnHashYem === 'NULL' ? null : $txnHashYem, $status, $createdAt === 'NULL' ? date('Y-m-d H:i:s') : $createdAt];
                    
                    // Add fee columns if they exist
                    $stmt = $pdo->query("DESCRIBE `{$tableName}`");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('fee_usdd', $columns)) {
                        $sql .= ", `fee_usdd`";
                        $placeholders .= ", ?";
                        $insertValues[] = 0.00;
                    }
                    
                    if (in_array('fee_hash_yemchain', $columns)) {
                        $sql .= ", `fee_hash_yemchain`";
                        $placeholders .= ", ?";
                        $insertValues[] = $usddFeeHash === 'NULL' ? null : $usddFeeHash;
                    } elseif (in_array('usdd_fee_hash', $columns)) {
                        $sql .= ", `usdd_fee_hash`";
                        $placeholders .= ", ?";
                        $insertValues[] = $usddFeeHash === 'NULL' ? null : $usddFeeHash;
                    }
                    
                    $sql .= ") " . $placeholders . ")";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($insertValues);
                        echo "✅ Imported ID {$id} (UID: {$userId}, Amount: {$amount})\n";
                        $imported++;
                    } catch (PDOException $e) {
                        echo "❌ Error ID {$id}: " . $e->getMessage() . "\n";
                        $errors++;
                    }
                }
            }
            
            echo "\n";
            echo "📊 Import Summary:\n";
            echo "  ✅ Imported: {$imported}\n";
            echo "  ⏭️  Skipped: {$skipped}\n";
            echo "  ❌ Errors: {$errors}\n";
            echo "\n✨ Done!\n";
        }
    } else {
        echo "❌ Could not parse INSERT statement\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
