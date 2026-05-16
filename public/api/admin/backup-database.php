<?php
/**
 * Admin endpoint to backup database
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../../../app/Config/config.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../app/Support/Logger.php';
require_once __DIR__ . '/../../../app/Support/AuditService.php';

try {
    $dbHost = $config['db']['host'];
    $dbName = $config['db']['name'];
    $dbUser = $config['db']['user'];
    $dbPass = $config['db']['pass'] ?? '';
    
    // Use 127.0.0.1 instead of localhost on Windows to avoid socket/pipe issues
    if (strtolower($dbHost) === 'localhost') {
        $dbHost = '127.0.0.1';
    }
    
    // Create backups directory if it doesn't exist
    $backupDir = __DIR__ . '/../../../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate filename with timestamp
    $filename = 'backup_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dbName) . '_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;
    
    // Find mysqldump: check config, then common XAMPP path on Windows, then PATH
    $mysqldump = $config['mysqldump_path'] ?? null;
    if (empty($mysqldump)) {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: try XAMPP default path
            $xamppPaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'D:\\xampp\\mysql\\bin\\mysqldump.exe',
                __DIR__ . '/../../../mysql/bin/mysqldump.exe',
            ];
            foreach ($xamppPaths as $p) {
                if (file_exists($p)) {
                    $mysqldump = $p;
                    break;
                }
            }
        }
        if (empty($mysqldump)) {
            $mysqldump = 'mysqldump';
        }
    }
    
    // Use temporary config file for password to avoid shell escaping issues (especially with special chars)
    $tmpCnf = tempnam(sys_get_temp_dir(), 'mysql_backup_');
    $tmpCnfFile = $tmpCnf . '.cnf';
    @unlink($tmpCnf);
    $cnfPass = str_replace(["\r", "\n"], '', $dbPass);
    $cnfPass = '"' . str_replace('"', '\\"', $cnfPass) . '"';
    $cnfContent = "[client]\nuser=" . $dbUser . "\npassword=" . $cnfPass . "\nhost=" . $dbHost . "\n";
    file_put_contents($tmpCnfFile, $cnfContent);
    @chmod($tmpCnfFile, 0600);
    
    $mysqldumpEscaped = escapeshellarg($mysqldump);
    $dbNameEscaped = escapeshellarg($dbName);
    $filepathEscaped = escapeshellarg($filepath);
    $cnfEscaped = escapeshellarg($tmpCnfFile);
    
    // Capture stderr to temp file for error reporting (redirect makes exec() $output empty)
    $errFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_err_' . getmypid() . '.txt';
    $errFileEscaped = escapeshellarg($errFile);
    
    // --defaults-extra-file passes credentials without shell exposure
    // stdout -> backup file, stderr -> err file (so we can show MySQL errors on failure)
    $command = $mysqldumpEscaped . ' --defaults-extra-file=' . $cnfEscaped . ' --no-tablespaces --skip-lock-tables ' . $dbNameEscaped . ' > ' . $filepathEscaped . ' 2> ' . $errFileEscaped;
    
    $output = [];
    $returnCode = -1;
    exec($command, $output, $returnCode);
    
    @unlink($tmpCnfFile);
    
    $errMsg = '';
    if (file_exists($errFile)) {
        $errMsg = trim(file_get_contents($errFile));
        @unlink($errFile);
    }
    if (empty($errMsg) && !empty($output)) {
        $errMsg = implode("\n", $output);
    }
    
    if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        $filesize = filesize($filepath);
        $filesizeFormatted = $filesize > 1024 * 1024 
            ? round($filesize / (1024 * 1024), 2) . ' MB'
            : round($filesize / 1024, 2) . ' KB';
        
        Logger::info('Database backup created', [
            'filename' => $filename,
            'size' => $filesize,
            'admin_uid' => $_SESSION['uid']
        ]);

        $pdo = Database::pdo($config['db']);
        (new AuditService($pdo))->log((int)$_SESSION['uid'], 'backup', 'database', null, [
            'filename' => $filename,
            'size' => $filesize,
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $filesizeFormatted,
            'created_at' => date('Y-m-d H:i:s'),
            'download_url' => '/backups/' . $filename
        ]);
    } else {
        throw new Exception('Backup command failed' . ($errMsg ? ': ' . $errMsg : ' (check mysqldump path and DB credentials)'));
    }
    
} catch (Exception $e) {
    Logger::error('Database backup failed', [
        'error' => $e->getMessage(),
        'admin_uid' => $_SESSION['uid'] ?? null
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Backup failed. Please try again later.'
    ]);
}
