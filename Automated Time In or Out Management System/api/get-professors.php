<?php
include '../config/database.php';
header('Content-Type: application/json');

// Query to get all professors
$query = "SELECT id, name FROM professors";

$result = $conn->query($query);

$professors = [];
while ($row = $result->fetch_assoc()) {
    $professors[] = $row; // Add each professor's data to the array
}

echo json_encode($professors); // Return JSON
?>
