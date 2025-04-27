<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid professor ID';
    header('Location: manage-users.php');
    exit;
}

$professor_id = (int)$_GET['id'];

try {
    // Begin transaction
    $conn->begin_transaction();

    // First delete attendance records to maintain referential integrity
    $stmt = $conn->prepare("DELETE FROM attendance WHERE professor_id = ?");
    $stmt->bind_param('i', $professor_id);
    $stmt->execute();

    // Then delete the professor
    $stmt = $conn->prepare("DELETE FROM professors WHERE id = ?");
    $stmt->bind_param('i', $professor_id);
    $stmt->execute();

    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        $_SESSION['success'] = 'Professor and their attendance records deleted successfully';
    } else {
        $conn->rollback();
        $_SESSION['error'] = 'No professor found with that ID';
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Error deleting professor: ' . $e->getMessage();
} finally {
    $stmt->close();
    $conn->close();
}

// Redirect back to manage users page
header('Location: manage-users.php');
exit;
?>