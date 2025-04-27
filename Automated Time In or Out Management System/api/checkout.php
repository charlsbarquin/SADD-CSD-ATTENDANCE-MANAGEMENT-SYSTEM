<?php
require_once __DIR__ . '/../includes/session.php';
include '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die(json_encode([
        "status" => "error",
        "message" => "Only POST requests are allowed"
    ]));
}

if (!isset($_POST['professor_id']) || empty($_POST['professor_id'])) {
    http_response_code(400);
    die(json_encode([
        "status" => "error",
        "message" => "Professor ID is required"
    ]));
}

$professor_id = (int)$_POST['professor_id'];
$session_type = $_POST['session_type'] ?? 'am';
$image_data = $_POST['image_data'] ?? null;

if ($professor_id <= 0) {
    http_response_code(400);
    die(json_encode([
        "status" => "error",
        "message" => "Invalid Professor ID"
    ]));
}

try {
    $conn->begin_transaction();

    // Get professor name for logging
    $stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Professor not found");
    }
    $professor = $result->fetch_assoc();
    $professorName = ucfirst($professor['name']);

    $photo_path = null;
    if ($image_data) {
        $upload_dir = '../uploads/checkins/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = 'checkout_' . $professor_id . '_' . time() . '.jpg';
        $photo_path = $upload_dir . $filename;
        
        if (!file_put_contents($photo_path, base64_decode($image_data))) {
            error_log("Failed to save checkout image for professor $professor_id");
        }
    }

    $time_field = ($session_type === 'am') ? 'am_check_out' : 'pm_check_out';
    
    $query = "SELECT id, $time_field FROM attendance 
              WHERE professor_id = ? 
              AND DATE(checkin_date) = CURDATE()
              ORDER BY id DESC 
              LIMIT 1 FOR UPDATE";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No active time in found for today");
    }

    $row = $result->fetch_assoc();
    $check_out = new DateTime();

    // Calculate hours worked
    $check_in_field = ($session_type === 'am') ? 'am_check_in' : 'pm_check_in';
    $check_in_time = new DateTime($row[$check_in_field]);
    $duration = $check_out->diff($check_in_time);
    $hours_worked = $duration->h + ($duration->i / 60);
    $hours_formatted = number_format($hours_worked, 2) . ' hours';

    $update = "UPDATE attendance SET 
               $time_field = ?,
               status = 'present'
               WHERE id = ?";
    
    $stmt = $conn->prepare($update);
    $check_out_str = $check_out->format('Y-m-d H:i:s');
    $stmt->bind_param("si", $check_out_str, $row['id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update attendance record");
    }

    // Create properly formatted notification
    $session_display = strtoupper($session_type) . ' session';
    $current_time = date('h:i A');
    $notification_message = "$professorName has timed out from $session_display after $hours_formatted";
    $notification_type = "time-out";
    
    // Insert notification
    $notif_stmt = $conn->prepare("INSERT INTO notifications 
                                (message, type, created_at) 
                                VALUES (?, ?, NOW())");
    $notif_stmt->bind_param("ss", $notification_message, $notification_type);
    $notif_stmt->execute();

    // Log action
    $action = "Time Out - " . $session_display;
    $log_stmt = $conn->prepare("INSERT INTO logs 
                               (action, user, timestamp)
                               VALUES (?, ?, NOW())");
    $log_stmt->bind_param("ss", $action, $professorName);
    if (!$log_stmt->execute()) {
        throw new Exception("Failed to log action");
    }

    $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Time out recorded successfully",
        "check_out" => $check_out_str,
        "professor_id" => $professor_id,
        "session_type" => $session_type,
        "hours_worked" => $hours_worked
    ]);

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    error_log("Time Out Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>