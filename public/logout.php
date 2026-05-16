<?php
/**
 * Logout Handler
 * Destroys session and redirects to login page
 */

require_once __DIR__ . '/../app/Support/PathHelper.php';

session_start();

// Log security event if user was logged in
if (isset($_SESSION['uid'])) {
    // You can add logging here if needed
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to landing page
header('Location: ' . PathHelper::url('public/landing.php'));
exit();

