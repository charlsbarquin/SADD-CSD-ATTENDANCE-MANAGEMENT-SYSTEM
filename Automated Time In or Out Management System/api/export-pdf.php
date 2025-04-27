<?php
require('../libs/tcpdf/tcpdf.php');
include '../config/database.php';

$filter_date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

// Fetch attendance records
$sql = "SELECT a.*, p.name FROM attendance a 
        JOIN professors p ON a.professor_id = p.id 
        WHERE a.checkin_date = '$filter_date' 
        ORDER BY a.check_in ASC";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No records found for the selected date.");
}

// Create PDF document
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle("Attendance Report - $filter_date");
$pdf->SetHeaderData('', 0, "Attendance Report - $filter_date", '');
$pdf->AddPage();

// Table Headers
$html = '<h2>Attendance Report - ' . $filter_date . '</h2>';
$html .= '<table border="1" cellpadding="4">
            <tr>
                <th><b>Name</b></th>
                <th><b>Time In</b></th>
                <th><b>Time Out</b></th>
                <th><b>Work Duration</b></th>
                <th><b>Status</b></th>
            </tr>';

// Table Data
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
                <td>' . $row['name'] . '</td>
                <td>' . $row['check_in'] . '</td>
                <td>' . ($row['check_out'] ? $row['check_out'] : 'Pending') . '</td>
                <td>' . ($row['work_duration'] ? $row['work_duration'] : 'N/A') . '</td>
                <td>' . $row['status'] . '</td>
              </tr>';
}

$html .= '</table>';

// Output Table to PDF
$pdf->writeHTML($html);
$pdf->Output("attendance_$filter_date.pdf", "D");
?>
