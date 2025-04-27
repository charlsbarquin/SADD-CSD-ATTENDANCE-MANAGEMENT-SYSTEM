<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['professor_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Check last activity for timeout
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Verify professor still exists in database
if (isset($_SESSION['professor_id'])) {
    $stmt = $conn->prepare("SELECT id FROM professors WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $_SESSION['professor_id']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
?>