<?php
include '../config/database.php';
header('Content-Type: application/json');

try {
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

    // Updated query to match new message format
    $query = "SELECT 
                p.name as professor_name,
                CASE 
                    WHEN a.am_check_in IS NOT NULL AND a.am_check_out IS NULL THEN 'Time In'
                    WHEN a.am_check_out IS NOT NULL THEN 'Timed Out'
                    WHEN a.pm_check_in IS NOT NULL AND a.pm_check_out IS NULL THEN 'Time In'
                    WHEN a.pm_check_out IS NOT NULL THEN 'Timed Out'
                    ELSE 'Unknown'
                END as action,
                CASE 
                    WHEN a.am_check_in IS NOT NULL THEN TIME_FORMAT(a.am_check_in, '%h:%i %p')
                    WHEN a.pm_check_in IS NOT NULL THEN TIME_FORMAT(a.pm_check_in, '%h:%i %p')
                    WHEN a.am_check_out IS NOT NULL THEN TIME_FORMAT(a.am_check_out, '%h:%i %p')
                    WHEN a.pm_check_out IS NOT NULL THEN TIME_FORMAT(a.pm_check_out, '%h:%i %p')
                    ELSE 'Unknown'
                END as time,
                CASE 
                    WHEN a.am_check_in IS NOT NULL OR a.am_check_out IS NOT NULL THEN 'AM'
                    WHEN a.pm_check_in IS NOT NULL OR a.pm_check_out IS NOT NULL THEN 'PM'
                    ELSE 'Unknown'
                END as session_type,
                a.checkin_date
              FROM attendance a
              JOIN professors p ON a.professor_id = p.id
              WHERE a.checkin_date = CURDATE()
              ORDER BY 
                CASE 
                    WHEN a.am_check_in IS NOT NULL THEN a.am_check_in
                    WHEN a.pm_check_in IS NOT NULL THEN a.pm_check_in
                    WHEN a.am_check_out IS NOT NULL THEN a.am_check_out
                    WHEN a.pm_check_out IS NOT NULL THEN a.pm_check_out
                    ELSE '0000-00-00 00:00:00'
                END DESC
              LIMIT $limit OFFSET $start";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'professor_name' => $row['professor_name'],
            'action' => $row['action'],
            'time' => $row['time'],
            'session_type' => $row['session_type'],
            'date' => date("M j, Y", strtotime($row['checkin_date']))
        ];
    }

    echo json_encode($history);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}