<?php
include '../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Attendance ID required']);
    exit;
}

$attendanceId = $_GET['id'];
$query = "SELECT 
            a.*, 
            p.name, 
            p.designation,
            p.email,
            p.profile_image,
            a.latitude,
            a.longitude,
            a.work_duration,
            a.notes,
            a.attendance_image,
            CASE 
                WHEN TIME(a.check_in) > '22:00:00' THEN 'Late'
                WHEN a.check_out IS NULL THEN 'Active'
                ELSE 'Present'
            END as status,
            CONCAT(
                FLOOR(a.work_duration / 3600), 'h ',
                FLOOR((a.work_duration % 3600) / 60), 'm'
            ) as formatted_duration
          FROM attendance a
          JOIN professors p ON a.professor_id = p.id
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $attendanceId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $attendance = $result->fetch_assoc();
    
    // Format check_in and check_out times
    if ($attendance['check_in']) {
        $attendance['formatted_check_in'] = date('h:i A', strtotime($attendance['check_in']));
    }
    
    if ($attendance['check_out']) {
        $attendance['formatted_check_out'] = date('h:i A', strtotime($attendance['check_out']));
    }
    
    // Add date in a separate field
    $attendance['date'] = date('F j, Y', strtotime($attendance['check_in']));
    
    echo json_encode($attendance);
} else {
    echo json_encode(['error' => 'Record not found']);
}
?>