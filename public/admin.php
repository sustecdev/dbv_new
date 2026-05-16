<?php
/**
 * Admin Panel Entry Point
 * Accessible only to admin users (UIDs from app_settings + bootstrap 1290033)
 */

session_start();

$config = require_once __DIR__ . '/../app/Config/config.php';

// Set error reporting based on config
$debugMode = $config['app']['debug'] ?? false;
if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
require_once __DIR__ . '/../app/Support/Database.php';
require_once __DIR__ . '/../app/Support/Logger.php';
require_once __DIR__ . '/../app/Support/Security.php';
require_once __DIR__ . '/../app/Controllers/AdminController.php';

// Check if user is logged in
if (!isset($_SESSION['uid'])) {
    require_once __DIR__ . '/../app/Support/PathHelper.php';
    header('Location: ' . PathHelper::url('safezone.php'));
    exit;
}

// Initialize database
try {
    if (!isset($config['db'])) {
        throw new Exception('DB config missing');
    }
    $pdo = Database::pdo($config['db']);
} catch (Exception $e) {
    error_log('Admin DB init error: ' . $e->getMessage());
    http_response_code(500);
    die('Database error. Please try again later.');
}

$admin = new AdminController($pdo, $config);

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'stats':
            $admin->getStats();
            break;
            
        case 'transactions':
            $admin->getTransactions();
            break;
            
        case 'logs':
            $admin->getLogs();
            break;

        case 'worker_logs':
            $admin->getWorkerLogs();
            break;
            
        case 'user':
            $admin->getUser();
            break;

        case 'failed_by_network':
            $admin->getFailedByNetwork();
            break;

        case 'commissions':
            $admin->getCommissions();
            break;

        case 'audit':
            $admin->getAuditLog();
            break;

        case 'clear_sessions':
            $admin->clearAllSessions();
            break;

        case 'manual_withdrawals':
            $admin->getManualWithdrawals();
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Show admin dashboard
$admin->index();
