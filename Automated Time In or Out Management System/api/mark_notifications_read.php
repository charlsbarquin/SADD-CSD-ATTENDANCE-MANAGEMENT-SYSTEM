<?php
// Ensure no output before headers
ob_start();

require_once __DIR__ . '/../../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous output
ob_end_clean();

header('Content-Type: application/json');

// Initialize response
$response = ['status' => 'error', 'message' => 'Invalid request'];

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get raw input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No input received');
    }

    // Decode JSON input
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Check if user is logged in as professor
    if (!isset($_SESSION['professor_id'])) {
        throw new Exception('Unauthorized - Please log in');
    }

    // Get professor details
    $professor_id = $_SESSION['professor_id'];
    $professor_name = $_SESSION['professor_name'] ?? '';

    // If professor name not in session, fetch from database
    if (empty($professor_name)) {
        $stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $professor_id);
        if (!$stmt->execute()) {
            throw new Exception('Database execute error: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $professor = $result->fetch_assoc();
            $professor_name = $professor['name'];
            $_SESSION['professor_name'] = $professor_name;
        }
        $stmt->close();
    }

    if (empty($professor_name)) {
        throw new Exception('Could not identify professor');
    }

    // Handle mark all request
    if (isset($data['mark_all']) && $data['mark_all'] === true) {
        $updateQuery = "UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND message LIKE ?";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $likePattern = "%{$professor_name}%";
        $stmt->bind_param("s", $likePattern);
        
        if (!$stmt->execute()) {
            throw new Exception('Database execute error: ' . $stmt->error);
        }
        
        $affectedRows = $stmt->affected_rows;
        $response = [
            'status' => 'success',
            'message' => "Marked $affectedRows notifications as read",
            'affected_rows' => $affectedRows
        ];
        $stmt->close();
    } 
    // Handle single notification request
    elseif (isset($data['notification_id'])) {
        $notificationId = $data['notification_id'];
        $updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $notificationId);
        
        if (!$stmt->execute()) {
            throw new Exception('Database execute error: ' . $stmt->error);
        }
        
        $response = [
            'status' => 'success',
            'message' => 'Notification marked as read'
        ];
        $stmt->close();
    } else {
        throw new Exception('Invalid request parameters');
    }
} catch (Exception $e) {
    // Log the error
    error_log("Notification Error: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
}

// Ensure no other output
ob_clean();
echo json_encode($response);
exit;
?>