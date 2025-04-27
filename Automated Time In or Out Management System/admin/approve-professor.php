<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate professor ID
$professorId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$professorId) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid professor ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // 1. Check if professor exists and is pending
    $stmt = $conn->prepare("SELECT id FROM professors WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $professorId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Professor not found or already approved');
    }

    // 2. Check if columns exist (for backward compatibility)
    $columnsExist = false;
    $checkColumns = $conn->query("SHOW COLUMNS FROM professors LIKE 'approved_at'");
    if ($checkColumns->num_rows > 0) {
        $columnsExist = true;
    }

    // 3. Update professor status to active
    if ($columnsExist) {
        $updateStmt = $conn->prepare("UPDATE professors SET 
                                    status = 'active', 
                                    approved_at = NOW(), 
                                    approved_by = ? 
                                    WHERE id = ?");
        $adminId = $_SESSION['admin_id'];
        $updateStmt->bind_param("ii", $adminId, $professorId);
    } else {
        // Fallback if columns don't exist
        $updateStmt = $conn->prepare("UPDATE professors SET 
                                    status = 'active' 
                                    WHERE id = ?");
        $updateStmt->bind_param("i", $professorId);
    }
    $updateStmt->execute();

    // 4. Log this action
    if ($conn->query("SHOW TABLES LIKE 'admin_logs'")->num_rows > 0) {
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_id) VALUES (?, ?, ?)");
        $action = "Approved professor ID $professorId";
        $logStmt->bind_param("isi", $_SESSION['admin_id'], $action, $professorId);
        $logStmt->execute();
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Professor approved successfully',
        'professorId' => $professorId,
        'columnsExist' => $columnsExist // For debugging
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Error approving professor: ' . $e->getMessage()
    ]);
}