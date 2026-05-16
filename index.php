<?php
/**
 * Root index.php - Redirects to landing page
 * This file handles root domain access (e.g., https://digitalbenefits.exchange/)
 */

require_once __DIR__ . '/app/Support/PathHelper.php';

// Redirect to the landing page
header('Location: ' . PathHelper::url('public/landing.php'));
exit;
