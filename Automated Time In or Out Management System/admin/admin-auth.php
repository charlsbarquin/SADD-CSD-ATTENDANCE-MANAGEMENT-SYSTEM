<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Optional: Verify admin still exists in database
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT id FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    header('Location: admin-login.php');
    exit;
}
?>