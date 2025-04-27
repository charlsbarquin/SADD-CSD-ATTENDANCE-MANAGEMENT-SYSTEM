<?php
// realtime-notifications.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the last notification timestamp from the request
$lastTimestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

// Query for new notifications
$query = "SELECT * FROM logs ";
if ($lastTimestamp) {
    $query .= "WHERE timestamp > '" . $conn->real_escape_string($lastTimestamp) . "' ";
}
$query .= "ORDER BY timestamp DESC LIMIT 5";

$notifications = $conn->query($query);
$results = [];
while ($row = $notifications->fetch_assoc()) {
    $results[] = [
        'action' => $row['action'],
        'user' => $row['user'],
        'timestamp' => $row['timestamp'],
        'time_formatted' => date('h:i A', strtotime($row['timestamp']))
    ];
}

echo json_encode([
    'notifications' => $results,
    'last_timestamp' => count($results) > 0 ? $results[0]['timestamp'] : $lastTimestamp
]);
?>