<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verify the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Method not allowed"]));
}

// Check if professor is logged in
if (!isset($_SESSION['professor_id'])) {
    http_response_code(401);
    die(json_encode(["status" => "error", "message" => "Not logged in"]));
}

$professor_id = $_SESSION['professor_id'];
$current_time = date("Y-m-d H:i:s");

// Determine current session (AM or PM)
$currentHour = date('H');
$isAM = ($currentHour < 12); // AM session is before noon
$session = $isAM ? 'AM' : 'PM';

// Start transaction
$conn->begin_transaction();

try {
    // 1. Get professor details
    $professorQuery = $conn->prepare("SELECT name FROM professors WHERE id = ?");
    $professorQuery->bind_param("i", $professor_id);
    $professorQuery->execute();
    $result = $professorQuery->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Professor not found");
    }
    
    $professor = $result->fetch_assoc();
    $professor_name = $professor['name'];
    $today = date("Y-m-d");

    // 2. Check if there's an open check-in for this session to close
    $checkQuery = $conn->prepare("SELECT id, " . ($isAM ? "am_check_in" : "pm_check_in") . " 
                                FROM attendance 
                                WHERE professor_id = ? AND date = ? AND " . 
                                ($isAM ? "am_check_in IS NOT NULL AND am_check_out IS NULL" : 
                                         "pm_check_in IS NOT NULL AND pm_check_out IS NULL"));
    $checkQuery->bind_param("is", $professor_id, $today);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("No open $session check-in found to check out from");
    }
    
    $attendance = $checkResult->fetch_assoc();
    $attendance_id = $attendance['id'];
    $check_in_time = $isAM ? $attendance['am_check_in'] : $attendance['pm_check_in'];

    // 3. Update check-out record for the current session
    if ($isAM) {
        $query = "UPDATE attendance SET 
                    am_check_out = ?,
                    status = CASE 
                        WHEN pm_check_in IS NOT NULL THEN 'present'
                        ELSE 'half-day'
                    END
                  WHERE id = ?";
    } else {
        $query = "UPDATE attendance SET 
                    pm_check_out = ?,
                    status = CASE 
                        WHEN am_check_in IS NOT NULL THEN 'present'
                        ELSE 'half-day'
                    END
                  WHERE id = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $current_time, $attendance_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update attendance: " . $stmt->error);
    }

    // 4. Calculate duration
    $duration = strtotime($current_time) - strtotime($check_in_time);
    $hours = floor($duration / 3600);
    $minutes = floor(($duration % 3600) / 60);
    $duration_str = sprintf("%02d:%02d", $hours, $minutes);

    // 5. Create notification
    $notif_message = "$professor_name has checked out from $session session after $duration_str hours";
    $notif_type = "check-out";
    
    $query_notif = "INSERT INTO notifications (message, type, created_at, is_read) 
                    VALUES (?, ?, NOW(), 0)";
    $stmt_notif = $conn->prepare($query_notif);
    $stmt_notif->bind_param("ss", $notif_message, $notif_type);
    
    if (!$stmt_notif->execute()) {
        throw new Exception("Failed to insert notification: " . $stmt_notif->error);
    }

    // 6. Log the check-out
    $logQuery = $conn->prepare("INSERT INTO logs (action, user, timestamp) 
                               VALUES (?, ?, NOW())");
    $logAction = "Professor timed out from $session session";
    $logQuery->bind_param("ss", $logAction, $professor_name);
    $logQuery->execute();

    // Commit transaction
    $conn->commit();

    // Success response
    echo json_encode([
        "status" => "success",
        "message" => "$session Time Out recorded successfully",
        "professor_name" => $professor_name,
        "check_out" => $current_time,
        "duration" => $duration_str,
        "session" => $session
    ]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Log the detailed error
    error_log("Time Out Error: " . $e->getMessage());
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Professor ID: " . $_SESSION['professor_id']);
    
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "$session Time Out Failed: " . $e->getMessage(),
        "details" => "Please try again or contact support"
    ]);
}