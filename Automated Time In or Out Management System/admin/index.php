<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Redirect to dashboard if logged in
header('Location: dashboard.php');
exit;
?>