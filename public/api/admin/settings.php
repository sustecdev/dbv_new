<?php
/**
 * Admin settings API - GET (list) and POST (update)
 * Used for admin-configurable features like Manual Withdraw Mode and Admin Roles
 */

session_start();

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/Logger.php';
require_once __DIR__ . '/../../../app/Support/AuditService.php';
require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../../../app/Config/config.php';
$pdo = Database::pdo($config['db']);

if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Ensure app_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        k VARCHAR(64) PRIMARY KEY,
        v TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("INSERT IGNORE INTO app_settings (k, v) VALUES ('manual_withdraw_enabled', '0')");
    $pdo->exec("INSERT IGNORE INTO app_settings (k, v) VALUES ('admin_uids', '[]')");
} catch (PDOException $e) {
    error_log('Settings DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT k, v FROM app_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['k']] = $row['v'];
        }
        if (!isset($settings['manual_withdraw_enabled'])) {
            $settings['manual_withdraw_enabled'] = '0';
        }
        $adminUidsRaw = $settings['admin_uids'] ?? '[]';
        $adminUids = [];
        if (is_string($adminUidsRaw)) {
            $decoded = json_decode($adminUidsRaw, true);
            $adminUids = is_array($decoded) ? array_map('intval', array_filter($decoded, 'is_numeric')) : [];
        }
        $adminUids = array_values(array_unique(array_merge([1290033], $adminUids)));
        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'manual_withdraw_enabled' => ($settings['manual_withdraw_enabled'] ?? '0') === '1',
            'admin_uids' => $adminUids,
        ]);
    } catch (Exception $e) {
        Logger::error('Admin get settings error', ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch settings']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim($_POST['key'] ?? $_GET['key'] ?? '');
    $value = trim($_POST['value'] ?? $_GET['value'] ?? '');

    $allowedKeys = ['manual_withdraw_enabled', 'admin_uids_add', 'admin_uids_remove'];
    if (!in_array($key, $allowedKeys)) {
        echo json_encode(['success' => false, 'message' => 'Invalid setting key']);
        exit;
    }

    if ($key === 'manual_withdraw_enabled') {
        $value = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    try {
        if ($key === 'admin_uids_add' || $key === 'admin_uids_remove') {
            $uidToChange = (int)$value;
            if ($uidToChange <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid UID']);
                exit;
            }
            $stmt = $pdo->query("SELECT v FROM app_settings WHERE k = 'admin_uids'");
            $current = [];
            if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $decoded = json_decode($row['v'], true);
                if (is_array($decoded)) {
                    $current = array_map('intval', array_filter($decoded, 'is_numeric'));
                }
            }
            $current = array_unique($current);
            if ($key === 'admin_uids_add') {
                $current[] = $uidToChange;
                $current = array_values(array_unique($current));
            } else {
                if ($uidToChange === 1290033) {
                    echo json_encode(['success' => false, 'message' => 'Cannot remove bootstrap admin (UID 1290033)']);
                    exit;
                }
                $current = array_values(array_diff($current, [$uidToChange]));
            }
            $stmt = $pdo->prepare("INSERT INTO app_settings (k, v) VALUES ('admin_uids', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
            $stmt->execute([json_encode($current)]);
            AdminHelper::clearCache();
            (new AuditService($pdo))->log(
                (int)$_SESSION['uid'],
                $key === 'admin_uids_add' ? 'admin_added' : 'admin_removed',
                'admin_uids',
                null,
                ['uid' => $uidToChange, 'admin_uids' => array_merge([1290033], $current)]
            );
            echo json_encode([
                'success' => true,
                'message' => $key === 'admin_uids_add' ? 'Admin UID added' : 'Admin UID removed',
                'admin_uids' => array_values(array_unique(array_merge([1290033], $current))),
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
            $stmt->execute([$key, $value]);

            (new AuditService($pdo))->log(
                (int)$_SESSION['uid'],
                'setting_updated',
                'app_settings',
                $key,
                ['key' => $key, 'value' => $value]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Setting updated',
                'manual_withdraw_enabled' => $value === '1',
            ]);
        }
    } catch (Exception $e) {
        Logger::error('Admin set setting error', ['error' => $e->getMessage(), 'key' => $key]);
        echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
