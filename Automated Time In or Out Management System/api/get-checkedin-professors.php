<?php
include '../config/database.php';
header('Content-Type: application/json');

// Query to get professors who are checked in but not checked out
$query = "SELECT p.id, p.name
          FROM professors p
          JOIN attendance a ON a.professor_id = p.id
          WHERE a.check_out IS NULL AND DATE(a.check_in) = CURDATE()"; 

$result = $conn->query($query);

$professors = [];
while ($row = $result->fetch_assoc()) {
    $professors[] = $row; // Add each professor's data
}

echo json_encode($professors); // Return JSON
?>
