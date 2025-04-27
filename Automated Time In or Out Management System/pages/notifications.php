<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['professor_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

// Determine user type and ID
$userType = isset($_SESSION['professor_id']) ? 'professor' : 'admin';
$userId = $_SESSION['professor_id'] ?? $_SESSION['admin_id'];

// Configuration for number of items to display
$itemsPerPage = 15; // Number of recent items to show
$maxDaysOld = 7;    // Only show items from last 7 days

// Get user name based on type
$userName = '';
if ($userType === 'professor') {
    $stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userName = $user['name'];
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userName = $user['username'];
    }
    $stmt->close();
}

// Get only recent notifications (last 7 days)
$notifications = [];
$notificationQuery = $conn->prepare("
    SELECT * FROM notifications 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY created_at DESC 
    LIMIT ?
");
$notificationQuery->bind_param("ii", $maxDaysOld, $itemsPerPage);
$notificationQuery->execute();
$notificationResult = $notificationQuery->get_result();

while ($row = $notificationResult->fetch_assoc()) {
    // Ensure consistent display formatting
    $row['type'] = str_replace(['time-in', 'time-out'], ['Time In', 'Time Out'], $row['type']);
    $row['message'] = str_replace(
        ['timed in', 'timed out', 'from AM session', 'from PM session'],
        ['Timed In', 'Timed Out', 'from Morning Session', 'from Afternoon Session'],
        $row['message']
    );
    $notifications[] = $row;
}

// Get only recent system logs (last 7 days)
$logs = [];
$logQuery = $conn->prepare("
    SELECT * FROM logs 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY timestamp DESC 
    LIMIT ?
");
$logQuery->bind_param("ii", $maxDaysOld, $itemsPerPage);
$logQuery->execute();
$logResult = $logQuery->get_result();

while ($row = $logResult->fetch_assoc()) {
    // Standardize terminology with proper capitalization
    $row['action'] = str_replace(
        ['time-in', 'time-out', 'timed in', 'timed out', 'AM session', 'PM session'],
        ['Time In', 'Time Out', 'Timed In', 'Timed Out', 'Morning Session', 'Afternoon Session'],
        $row['action']
    );    
    $logs[] = $row;
}

// Get unread count for the current user
$unreadCount = 0;
if ($userType === 'professor') {
    $unreadQuery = $conn->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE message LIKE ? AND is_read = 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $likePattern = "%{$userName}%";
    $unreadQuery->bind_param("si", $likePattern, $maxDaysOld);
    $unreadQuery->execute();
    $unreadCount = $unreadQuery->get_result()->fetch_row()[0];
    $unreadQuery->close();
}

// Handle mark all as read action
if (isset($_POST['mark_all_read']) && $userType === 'professor') {
    $markAllReadQuery = "UPDATE notifications SET is_read = 1 WHERE message LIKE ?";
    $stmt = $conn->prepare($markAllReadQuery);
    $likePattern = "%{$userName}%";
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $stmt->close();

    // Refresh to show updated read status
    header("Location: notifications.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activities</title>

    <!-- Bootstrap & Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/attendance-report.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .main-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .page-header {
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .page-header h1 {
            font-weight: 600;
            color: #2c3e50;
        }

        .notification-card {
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #4e73df;
            transition: all 0.2s ease;
        }

        .notification-card.unread {
            background-color: #f0f7ff;
            border-left-color: #2c3e50;
        }

        .notification-card:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #4e73df;
            color: #2c3e50;
        }

        .badge {
            font-size: 0.75rem;
            padding: 5px 8px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .btn-mark-all {
            background-color: #4e73df;
            color: white;
            border: none;
        }

        .btn-mark-all:hover {
            background-color: #3a5bbf;
        }
        
        .recent-notice {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="main-container">
            <!-- Header Section -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-bell me-2"></i>System Activities</h1>
                        <p class="page-subtitle">View recent system notifications and activities</p>
                    </div>
                    <?php if ($userType === 'professor' && $unreadCount > 0): ?>
                        <form method="POST" action="notifications.php">
                            <button type="submit" name="mark_all_read" class="btn btn-mark-all">
                                <i class="fas fa-check-double me-2"></i>Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4" id="activityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                        <i class="fas fa-bell me-1"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                        <i class="fas fa-history me-1"></i> Activity Logs
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="activityTabsContent">
                <!-- Notifications Tab -->
                <div class="tab-pane fade show active" id="notifications" role="tabpanel">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4>No recent notifications</h4>
                            <p>System notifications will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <?php foreach ($notifications as $notification): 
                                        $isUserNotification = strpos($notification['message'], $userName) !== false;
                                        $isUnread = $isUserNotification && !$notification['is_read'];
                                    ?>
                                        <tr class="notification-card <?= $isUnread ? 'unread' : '' ?>">
                                            <td style="width: 120px;">
                                                <span class="badge <?= $notification['type'] === 'Time In' ? 'bg-success' : 'bg-primary' ?>">
                                                    <?= $notification['type'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <?= htmlspecialchars($notification['message']) ?>
                                                        <?php if ($isUnread): ?>
                                                            <span class="badge bg-danger ms-2">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="notification-time">
                                                        <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="recent-notice">
                            Showing most recent <?= count($notifications) ?> notifications from the last <?= $maxDaysOld ?> days
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Logs Tab -->
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h4>No recent activity logs</h4>
                            <p>System activities will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead style="background-color: #eef2ff;">
                                    <tr>
                                        <th>Action</th>
                                        <th>User</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="notification-card">
                                            <td><?= htmlspecialchars($log['action']) ?></td>
                                            <td><?= $log['user'] ? htmlspecialchars($log['user']) : 'System' ?></td>
                                            <td>
                                                <small class="notification-time">
                                                    <?= date('M j, Y g:i A', strtotime($log['timestamp'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="recent-notice">
                            Showing most recent <?= count($logs) ?> activity logs from the last <?= $maxDaysOld ?> days
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & Font Awesome JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh the page every 2 minutes to check for new notifications
        setTimeout(function() {
            window.location.reload();
        }, 120000);

        // Preserve tab selection on page refresh
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');

            if (activeTab === 'logs') {
                const tab = new bootstrap.Tab(document.getElementById('logs-tab'));
                tab.show();
            }
        });

        // Update URL when switching tabs
        document.getElementById('logs-tab').addEventListener('shown.bs.tab', function() {
            const url = new URL(window.location);
            url.searchParams.set('tab', 'logs');
            window.history.pushState({}, '', url);
        });

        document.getElementById('notifications-tab').addEventListener('shown.bs.tab', function() {
            const url = new URL(window.location);
            url.searchParams.delete('tab');
            window.history.pushState({}, '', url);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>