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
$image_data = $_POST['image_data'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

// Validate required fields
if (!$image_data || !$latitude || !$longitude) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

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
    $current_time = date("Y-m-d H:i:s");
    $today = date("Y-m-d");

    // 2. Check if already checked in for this session today
    $checkQuery = $conn->prepare("SELECT id FROM attendance 
                                WHERE professor_id = ? AND date = ? AND " . 
                                ($isAM ? "am_check_in IS NOT NULL" : "pm_check_in IS NOT NULL"));
    $checkQuery->bind_param("is", $professor_id, $today);
    $checkQuery->execute();

    if ($checkQuery->get_result()->num_rows > 0) {
        throw new Exception("You have already checked in for $session session today");
    }

    // 3. Save the image file
    $upload_dir = '../uploads/checkins/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_name = 'checkin_' . $professor_id . '_' . time() . '.jpg';
    $image_path = $upload_dir . $image_name;

    // Remove the "data:image/jpeg;base64," part
    $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    $image_binary = base64_decode($image_data);

    if (!file_put_contents($image_path, $image_binary)) {
        throw new Exception("Failed to save image");
    }

    // 4. Check if attendance record exists for today
    $attendanceQuery = $conn->prepare("SELECT id FROM attendance WHERE professor_id = ? AND date = ?");
    $attendanceQuery->bind_param("is", $professor_id, $today);
    $attendanceQuery->execute();
    $attendanceResult = $attendanceQuery->get_result();

    if ($attendanceResult->num_rows > 0) {
        // Update existing record
        $attendance = $attendanceResult->fetch_assoc();
        $attendance_id = $attendance['id'];
        
        if ($isAM) {
            $query = "UPDATE attendance SET 
                      am_check_in = ?,
                      am_face_scan_image = ?,
                      am_latitude = ?,
                      am_longitude = ?,
                      status = CASE 
                        WHEN pm_check_in IS NOT NULL THEN 'present'
                        ELSE 'half-day'
                      END
                      WHERE id = ?";
        } else {
            $query = "UPDATE attendance SET 
                      pm_check_in = ?,
                      pm_face_scan_image = ?,
                      pm_latitude = ?,
                      pm_longitude = ?,
                      status = CASE 
                        WHEN am_check_in IS NOT NULL THEN 'present'
                        ELSE 'half-day'
                      END
                      WHERE id = ?";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssddi", $current_time, $image_name, $latitude, $longitude, $attendance_id);
    } else {
        // Create new record
        if ($isAM) {
            $query = "INSERT INTO attendance (
                        professor_id, 
                        date,
                        am_check_in, 
                        am_face_scan_image, 
                        am_latitude, 
                        am_longitude,
                        status
                      ) VALUES (?, ?, ?, ?, ?, ?, 'half-day')";
        } else {
            $query = "INSERT INTO attendance (
                        professor_id, 
                        date,
                        pm_check_in, 
                        pm_face_scan_image, 
                        pm_latitude, 
                        pm_longitude,
                        status
                      ) VALUES (?, ?, ?, ?, ?, ?, 'half-day')";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssdd", $professor_id, $today, $current_time, $image_name, $latitude, $longitude);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert attendance: " . $stmt->error);
    }

    // 5. Create notification
    $notif_message = "$professor_name has checked in for $session session at " . date('h:i A');
    $notif_type = "check-in";
    
    $query_notif = "INSERT INTO notifications (message, type, created_at, is_read) 
                    VALUES (?, ?, NOW(), 0)";
    $stmt_notif = $conn->prepare($query_notif);
    $stmt_notif->bind_param("ss", $notif_message, $notif_type);

    if (!$stmt_notif->execute()) {
        throw new Exception("Failed to insert notification: " . $stmt_notif->error);
    }

    // 6. Log the check-in
    $logQuery = $conn->prepare("INSERT INTO logs (action, user, timestamp) 
                               VALUES (?, ?, NOW())");
    $logAction = "Professor checked in for $session session";
    $logQuery->bind_param("ss", $logAction, $professor_name);
    $logQuery->execute();

    // Commit transaction
    $conn->commit();

    // Success response
    echo json_encode([
        "status" => "success",
        "message" => "$session Time In recorded successfully",
        "professor_name" => $professor_name,
        "check_in" => $current_time,
        "session" => $session
    ]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();

    // Log the detailed error
    error_log("Time In Error: " . $e->getMessage());
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Professor ID: " . $_SESSION['professor_id']);

    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "$session Time In Failed: " . $e->getMessage(),
        "details" => "Please try again or contact support"
    ]);
}