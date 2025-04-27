<?php
include '../config/database.php';

$filter_date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

// Ensure data is retrieved
$sql = "SELECT a.*, p.name FROM attendance a 
        JOIN professors p ON a.professor_id = p.id 
        WHERE a.checkin_date = '$filter_date' 
        ORDER BY a.check_in ASC";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No records found for the selected date.");
}

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_' . $filter_date . '.csv"');

$output = fopen("php://output", "w");
fputcsv($output, ["Name", "Time In", "Time Out", "Work Duration", "Status"]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['name'], 
        $row['check_in'], 
        $row['check_out'] ? $row['check_out'] : 'Pending',
        $row['work_duration'] ? $row['work_duration'] : 'N/A',
        $row['status']
    ]);
}

fclose($output);
exit;
?>
