<?php
/**
 * SafeZone Login Page
 * Handles external authentication via SafeZone
 */

require_once __DIR__ . '/../app/Support/Security.php';
require_once __DIR__ . '/../app/Support/PathHelper.php';

// Configure secure session
Security::configureSecureSession();

// Load configuration
$config = require(__DIR__ . '/../app/Config/config.php');

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['uid'])) {
    header('Location: ' . PathHelper::url('dashboard.php'));
    exit;
}

// Include the login view
include __DIR__ . '/../resources/views/safezone.php';
