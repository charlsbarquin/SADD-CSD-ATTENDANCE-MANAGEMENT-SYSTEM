<?php
include '../config/database.php';

// Get total number of professors
$result = $conn->query("SELECT COUNT(*) AS total FROM professors");
$total_professors = $result->fetch_assoc()['total'];

// Get today's attendance count
$result = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE DATE(recorded_at) = CURDATE()");
$today_attendance = $result->fetch_assoc()['total'];

// Get count of late entries
$result = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE DATE(recorded_at) = CURDATE() AND status='Late'");
$late_entries = $result->fetch_assoc()['total'];

// Prepare data for attendance chart (last 7 days)
$chart_labels = [];
$chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date)); // Get day name (Mon, Tue, etc.)

    $result = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE DATE(recorded_at) = '$date'");
    $chart_data[] = $result->fetch_assoc()['total'];
}

// Return JSON response
echo json_encode([
    "total_professors" => $total_professors,
    "today_attendance" => $today_attendance,
    "late_entries" => $late_entries,
    "attendance_chart" => [
        "labels" => $chart_labels,
        "data" => $chart_data
    ]
]);
?>
