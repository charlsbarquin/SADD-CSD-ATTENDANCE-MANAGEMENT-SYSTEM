<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $user = $_GET['user'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Validate input
    if (empty($user)) {
        throw new Exception('User parameter is required');
    }

    // Query for notifications
    $query = "SELECT * FROM logs 
              WHERE (user = 'system' OR user = ?)
              ORDER BY timestamp DESC 
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $user, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Standardize terminology with proper capitalization
        $action = str_replace(
            ['timed in', 'timed out', 'Time-in', 'Time-out', 'AM session', 'PM session'],
            ['Timed In', 'Timed Out', 'Time In', 'Time Out', 'Morning Session', 'Afternoon Session'],
            $row['action']
        );

        $notifications[] = [
            'id' => $row['id'],
            'action' => $action,
            'timestamp' => $row['timestamp'],
            'is_read' => (bool)$row['is_read']
        ];
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>