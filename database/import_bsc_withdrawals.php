<?php
/**
 * Import BSC Withdrawal Records from SQL dump
 * Maps columns from bsc_withdraw to binance_withdraw table structure
 */

// Load config directly without function redeclaration
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

require_once __DIR__ . '/../app/Support/Database.php';

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
    $pdo = Database::pdo($config['db']);
    
    echo "🔄 Starting BSC withdrawal import...\n\n";
    
    // Check if binance_withdraw table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'binance_withdraw'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Check if bsc_withdraw exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'bsc_withdraw'");
        if ($stmt->rowCount() > 0) {
            echo "⚠️  Found 'bsc_withdraw' table. Using it.\n";
            $tableName = 'bsc_withdraw';
        } else {
            die("❌ Neither 'binance_withdraw' nor 'bsc_withdraw' table exists!\n");
        }
    } else {
        $tableName = 'binance_withdraw';
        echo "✅ Found 'binance_withdraw' table.\n";
    }
    
    // Get table structure to determine column mapping
    $stmt = $pdo->query("DESCRIBE `{$tableName}`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Table columns: " . implode(', ', $columns) . "\n\n";
    
    // Read SQL file
    $sqlFile = 'C:/Users/Dell/Downloads/bsc_withdraw.sql';
    if (!file_exists($sqlFile)) {
        die("❌ SQL file not found: {$sqlFile}\n");
    }
    
    $sqlContent = file_get_contents($sqlFile);
    
    // Extract INSERT statements (lines 52-108)
    $lines = explode("\n", $sqlContent);
    $insertStatements = [];
    $inInsertBlock = false;
    $insertSql = '';
    
    for ($i = 51; $i < 108; $i++) { // Lines 52-108 (0-indexed)
        $line = trim($lines[$i]);
        
        if (empty($line)) continue;
        
        if (strpos($line, 'INSERT INTO') !== false) {
            $inInsertBlock = true;
            $insertSql = $line;
        } elseif ($inInsertBlock) {
            $insertSql .= ' ' . $line;
            
            // Check if this line ends the INSERT statement
            if (substr(rtrim($line), -1) === ';') {
                $insertStatements[] = $insertSql;
                $insertSql = '';
                $inInsertBlock = false;
            }
        }
    }
    
    if (!empty($insertSql)) {
        $insertStatements[] = $insertSql;
    }
    
    echo "📊 Found " . count($insertStatements) . " INSERT statement(s)\n\n";
    
    // Process each INSERT statement
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($insertStatements as $index => $insertSql) {
        // Parse the INSERT statement
        // Extract values
        if (preg_match('/INSERT INTO `bsc_withdraw`[^)]+VALUES\s+(.+);/is', $insertSql, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual value sets
            $valueSets = [];
            $currentSet = '';
            $parenDepth = 0;
            $inString = false;
            $stringChar = '';
            
            for ($i = 0; $i < strlen($valuesString); $i++) {
                $char = $valuesString[$i];
                
                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($inString && $char === $stringChar && ($i === 0 || $valuesString[$i-1] !== '\\')) {
                    $inString = false;
                } elseif (!$inString) {
                    if ($char === '(') $parenDepth++;
                    if ($char === ')') $parenDepth--;
                }
                
                $currentSet .= $char;
                
                if (!$inString && $parenDepth === 0 && $char === ')') {
                    // Check if next non-whitespace is comma or end
                    $nextChars = trim(substr($valuesString, $i + 1, 10));
                    if (empty($nextChars) || $nextChars[0] === ',' || $nextChars[0] === ';') {
                        $valueSets[] = trim($currentSet);
                        $currentSet = '';
                        // Skip comma if present
                        if (!empty($nextChars) && $nextChars[0] === ',') {
                            $i += strpos($nextChars, ',') + 1;
                        }
                    }
                }
            }
            
            if (!empty($currentSet)) {
                $valueSets[] = trim($currentSet);
            }
            
            // Insert each row
            foreach ($valueSets as $valueSet) {
                // Remove parentheses
                $valueSet = trim($valueSet, '(),');
                $values = [];
                
                // Parse values (handle NULL, strings, numbers)
                $currentValue = '';
                $inString = false;
                $stringChar = '';
                
                for ($i = 0; $i < strlen($valueSet); $i++) {
                    $char = $valueSet[$i];
                    
                    if (!$inString && ($char === '"' || $char === "'")) {
                        $inString = true;
                        $stringChar = $char;
                        $currentValue .= $char;
                    } elseif ($inString && $char === $stringChar && ($i === 0 || $valueSet[$i-1] !== '\\')) {
                        $inString = false;
                        $currentValue .= $char;
                    } elseif (!$inString && $char === ',' && ($i === strlen($valueSet) - 1 || strpos(substr($valueSet, $i + 1), ',') !== false || $i + 1 >= strlen($valueSet))) {
                        $values[] = trim($currentValue);
                        $currentValue = '';
                    } else {
                        $currentValue .= $char;
                    }
                }
                
                if (!empty($currentValue)) {
                    $values[] = trim($currentValue);
                }
                
                // Map columns
                // bsc_withdraw: id, user_id, amount, recipient_address, txn_hash, txn_hash_yemchain, usdd_fee_hash, status, created_at, request_id, error_message, completed_at, failed_at
                // binance_withdraw: id, uid, address, amount, txn_hash_bsc, txn_hash_yemchain, status, created_at, processed_at
                
                if (count($values) >= 13) {
                    $id = trim($values[0], "'\"");
                    $userId = trim($values[1], "'\"");
                    $amount = trim($values[2], "'\"");
                    $recipientAddress = trim($values[3], "'\"");
                    $txnHash = trim($values[4], "'\"");
                    $txnHashYemchain = trim($values[5], "'\"");
                    $usddFeeHash = trim($values[6], "'\"");
                    $status = trim($values[7], "'\"");
                    $createdAt = trim($values[8], "'\"");
                    $requestId = trim($values[9], "'\"");
                    $errorMessage = trim($values[10], "'\"");
                    $completedAt = trim($values[11], "'\"");
                    $failedAt = trim($values[12], "'\"");
                    
                    // Check if record already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM `{$tableName}` WHERE id = ?");
                    $checkStmt->execute([$id]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        echo "⏭️  Skipping ID {$id} (already exists)\n";
                        $skipped++;
                        continue;
                    }
                    
                    // Determine column mapping based on table structure
                    if (in_array('uid', $columns)) {
                        $uidCol = 'uid';
                    } elseif (in_array('user_id', $columns)) {
                        $uidCol = 'user_id';
                    } else {
                        echo "❌ Error: Cannot find uid/user_id column\n";
                        $errors++;
                        continue;
                    }
                    
                    if (in_array('address', $columns)) {
                        $addrCol = 'address';
                    } elseif (in_array('recipient_address', $columns)) {
                        $addrCol = 'recipient_address';
                    } else {
                        echo "❌ Error: Cannot find address/recipient_address column\n";
                        $errors++;
                        continue;
                    }
                    
                    if (in_array('txn_hash_bsc', $columns)) {
                        $txnCol = 'txn_hash_bsc';
                    } elseif (in_array('txn_hash', $columns)) {
                        $txnCol = 'txn_hash';
                    } else {
                        echo "❌ Error: Cannot find txn_hash_bsc/txn_hash column\n";
                        $errors++;
                        continue;
                    }
                    
                    // Build INSERT query
                    $insertCols = ['id', $uidCol, $addrCol, 'amount', $txnCol, 'txn_hash_yemchain', 'status', 'created_at'];
                    $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?'];
                    
                    // Add optional columns if they exist
                    if (in_array('fee_usdd', $columns)) {
                        $insertCols[] = 'fee_usdd';
                        $placeholders[] = '?';
                    }
                    if (in_array('fee_hash_yemchain', $columns)) {
                        $insertCols[] = 'fee_hash_yemchain';
                        $placeholders[] = '?';
                    } elseif (in_array('usdd_fee_hash', $columns)) {
                        $insertCols[] = 'usdd_fee_hash';
                        $placeholders[] = '?';
                    }
                    if (in_array('processed_at', $columns)) {
                        $insertCols[] = 'processed_at';
                        $placeholders[] = '?';
                    } elseif (in_array('completed_at', $columns)) {
                        $insertCols[] = 'completed_at';
                        $placeholders[] = '?';
                    }
                    
                    $sql = "INSERT INTO `{$tableName}` (`" . implode('`, `', $insertCols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                    
                    // Prepare values
                    $insertValues = [
                        $id,
                        $userId,
                        $recipientAddress,
                        $amount,
                        $txnHash === 'NULL' ? null : $txnHash,
                        $txnHashYemchain === 'NULL' ? null : $txnHashYemchain,
                        $status,
                        $createdAt === 'NULL' ? date('Y-m-d H:i:s') : $createdAt
                    ];
                    
                    // Add optional values
                    if (in_array('fee_usdd', $columns) || in_array('fee_hash_yemchain', $columns) || in_array('usdd_fee_hash', $columns)) {
                        if (in_array('fee_usdd', $columns)) {
                            $insertValues[] = 0.00; // Default fee
                        }
                        if (in_array('fee_hash_yemchain', $columns)) {
                            $insertValues[] = $usddFeeHash === 'NULL' ? null : $usddFeeHash;
                        } elseif (in_array('usdd_fee_hash', $columns)) {
                            $insertValues[] = $usddFeeHash === 'NULL' ? null : $usddFeeHash;
                        }
                    }
                    
                    if (in_array('processed_at', $columns)) {
                        $insertValues[] = $completedAt === 'NULL' ? null : $completedAt;
                    } elseif (in_array('completed_at', $columns)) {
                        $insertValues[] = $completedAt === 'NULL' ? null : $completedAt;
                    }
                    
                    // Execute insert
                    $stmt = $pdo->prepare($sql);
                    
                    try {
                        $stmt->execute($insertValues);
                        echo "✅ Imported ID {$id} (User: {$userId}, Amount: {$amount})\n";
                        $imported++;
                    } catch (PDOException $e) {
                        echo "❌ Error importing ID {$id}: " . $e->getMessage() . "\n";
                        $errors++;
                    }
                }
            }
        }
    }
    
    echo "\n";
    echo "📊 Import Summary:\n";
    echo "  ✅ Imported: {$imported}\n";
    echo "  ⏭️  Skipped: {$skipped}\n";
    echo "  ❌ Errors: {$errors}\n";
    echo "\n✨ Import completed!\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
