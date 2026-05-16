<?php

$config = require __DIR__ . '/../app/Config/config.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

// Simple router for API
switch ($path) {
    case '/api/stellar/deposit':
        require_once __DIR__ . '/../app/Controllers/DepositController.php';
        (new DepositController($config))->postCreate();
        break;

    case '/api/stellar/withdraw':
        require_once __DIR__ . '/../app/Controllers/WithdrawController.php';
        (new WithdrawController($config))->postCreate();
        break;

    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
}
