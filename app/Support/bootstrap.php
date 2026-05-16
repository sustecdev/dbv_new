<?php

/**
 * Bootstrap file - Load all support classes
 * Include this at the start of your application
 */

// Load all support classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/ExceptionHandler.php';

// Load configuration
$config = require __DIR__ . '/../Config/config.php';

// Set error reporting based on debug mode
if ($config['app']['debug'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
}

// Initialize logger
Logger::init();

// Register exception handler
ExceptionHandler::register();

