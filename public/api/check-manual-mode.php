<?php
/**
 * Check if manual withdraw mode is enabled.
 * Used by workers to stop processing when manual mode is ON.
 * No auth required - returns a simple status flag.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../app/Support/Database.php';
$config = require __DIR__ . '/../../app/Config/config.php';

try {
    $pdo = Database::pdo($config['db']);
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (k VARCHAR(64) PRIMARY KEY, v TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO app_settings (k, v) VALUES ('manual_withdraw_enabled', '0')");
    $stmt = $pdo->query("SELECT v FROM app_settings WHERE k = 'manual_withdraw_enabled'");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $enabled = ($row['v'] ?? '0') === '1';
    echo json_encode(['manual_withdraw_enabled' => $enabled]);
} catch (Exception $e) {
    // On error, assume manual mode OFF so workers continue
    echo json_encode(['manual_withdraw_enabled' => false]);
}
