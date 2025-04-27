<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate input
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$attendanceId = (int)$_POST['id'];
$status = $_POST['status'];

try {
    // Update the attendance record
    $stmt = $conn->prepare("UPDATE attendance SET check_out = NOW(), status = ? WHERE id = ? AND check_out IS NULL");
    $stmt->bind_param("si", $status, $attendanceId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        die(json_encode(['success' => false, 'message' => 'Record not found or already checked out']));
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
}
?>