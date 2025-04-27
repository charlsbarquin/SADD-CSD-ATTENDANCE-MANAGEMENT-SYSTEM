<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

$response = ['hasNew' => false, 'count' => 0];

try {
    // Check if user is logged in
    if (!isset($_SESSION['admin_id']) && !isset($_SESSION['professor_id'])) {
        throw new Exception('Unauthorized');
    }

    // Get user-specific notifications
    $userName = '';
    if (isset($_SESSION['professor_id'])) {
        $stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['professor_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userName = $user['name'];
        }
    } else {
        $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userName = $user['username'];
        }
    }

    if (empty($userName)) {
        throw new Exception('Could not identify user');
    }

    // Get count of unread notifications for this user
    $query = "SELECT COUNT(*) FROM notifications 
              WHERE is_read = 0 
              AND (message LIKE ? OR user = 'system')";
    
    $stmt = $conn->prepare($query);
    $likePattern = "%{$userName}%";
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];

    $response = [
        'hasNew' => $count > 0,
        'count' => $count
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>