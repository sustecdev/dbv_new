<?php
/**
 * Landing Page Controller
 * Shows landing page for non-authenticated users
 */

require_once __DIR__ . '/../app/Support/Security.php';
require_once __DIR__ . '/../app/Support/PathHelper.php';

Security::configureSecureSession();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['uid'])) {
    header('Location: ' . PathHelper::url('dashboard.php'));
    exit;
}

// Include the landing page view
include __DIR__ . '/../resources/views/landing.php';

