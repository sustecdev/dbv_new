<?php
/**
 * phpMyAdmin Configuration for DBV Admin Panel
 * Optimized for XAMPP on Windows
 */

// Error reporting (enable for debugging, disable for production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
error_reporting(0);
ini_set('display_errors', 0);

// Blowfish secret for cookie encryption (change this to a random 32+ character string)
$cfg['blowfish_secret'] = 'khIkwTiD7rB9bJiMrhz1tHzCuJemW4HAbji7KA5afWw=';

// Server configuration
$i = 0;
$i++;

// Authentication type: 'cookie' requires login, 'config' auto-connects (less secure)
$cfg['Servers'][$i]['auth_type'] = 'cookie'; // Use 'cookie' for security, 'config' for convenience

// Database connection
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['port'] = '3306';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = true; // Set to false if MySQL has a password
$cfg['Servers'][$i]['AllowRoot'] = true;

// For 'config' auth_type (uncomment and configure if using auto-login):
// $cfg['Servers'][$i]['user'] = 'root';
// $cfg['Servers'][$i]['password'] = ''; // Your MySQL password

// Directories (Windows paths)
$cfg['TempDir'] = __DIR__ . '/tmp';
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
// Remove or comment out DataDir if not on Linux
// $cfg['DataDir'] = '/mnt/raid1/mysql';

// UI and behavior settings
$cfg['PmaAbsoluteUri'] = '/dbnew/dbv-admin-panel/';
$cfg['PmaNoRelation_DisableWarning'] = true;
$cfg['SuhosinDisableWarning'] = true;
$cfg['LoginCookieValidity'] = 1800; // 30 minutes
$cfg['MaxRows'] = 100;
$cfg['SendErrorReports'] = 'never';
$cfg['ShowPhpInfo'] = false;
$cfg['ShowChgPassword'] = true;
$cfg['VersionCheck'] = false;

// Default database (optional - auto-selects this database on login)
// $cfg['Servers'][$i]['only_db'] = 'Digital';

// Theme
$cfg['ThemeDefault'] = 'pmahomme';

// Security
$cfg['ForceSSL'] = false; // Set to true if using HTTPS
