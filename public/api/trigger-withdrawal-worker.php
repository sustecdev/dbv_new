<?php
// API endpoint to trigger immediate withdrawal processing
header('Content-Type: application/json');

// Load configuration
$config = require __DIR__ . '/../../app/Config/config.php';

// Simple authentication - you can improve this
$authToken = (string)($_SERVER['HTTP_X_WORKER_TOKEN'] ?? $_POST['token'] ?? '');
$expectedToken = (string)($config['worker']['secret'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $authToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Trigger worker to process immediately
$withdrawalId = $_POST['id'] ?? $_GET['id'] ?? null;

// Try HTTP trigger first (better for improved worker)
$httpPort = 3001;
$triggerUrl = "http://localhost:$httpPort/process";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $triggerUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $withdrawalId]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Don't wait
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
$httpResult = curl_exec($ch);
$httpError = curl_error($ch);
curl_close($ch);

// If HTTP trigger fails, fallback to direct exec
if ($httpError || !$httpResult) {
    $scriptPath = __DIR__ . '/../../scripts/node/withdrawal_to_stellar.js';
    $token = $config['worker']['secret'];
    $nodePath = 'node';

    $command = escapeshellarg($nodePath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($token);

    if (PHP_OS_FAMILY === 'Windows') {
        $command = "start /B $command >nul 2>&1";
    } else {
        $command .= ' > /dev/null 2>&1 &';
    }

    exec($command, $output, $returnVar);
}

echo json_encode([
    'success' => true,
    'message' => 'Worker triggered',
    'withdrawal_id' => $withdrawalId,
    'triggered_at' => date('Y-m-d H:i:s')
]);

