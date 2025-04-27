<?php
// admin-session.php

// Allow session cookies even over HTTP (useful for localhost testing)
ini_set('session.cookie_secure', 0);

// Set session cookie parameters (lifetime, path, domain, secure, httponly)
session_set_cookie_params([
    'lifetime' => 1800, // 30 minutes
    'path' => '/',
    'domain' => '', 
    'secure' => false, // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Start the session
session_start();

// Connect to database
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

// Session timeout: check inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Last request was more than 30 minutes ago
    session_unset();     // Unset $_SESSION variables
    session_destroy();   // Destroy the session
    header('Location: admin-login.php?timeout=1');
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();
?>
