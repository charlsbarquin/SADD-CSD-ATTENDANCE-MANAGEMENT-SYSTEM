<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Check if ID parameter exists (now using POST)
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid attendance record ID']));
}

$attendance_id = (int)$_POST['id'];

try {
    // Begin transaction
    $conn->begin_transaction();

    // Delete the attendance record
    $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ?");
    $stmt->bind_param('i', $attendance_id);
    $stmt->execute();

    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ]);
    } else {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No attendance record found with that ID'
        ]);
    }

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting attendance record: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}