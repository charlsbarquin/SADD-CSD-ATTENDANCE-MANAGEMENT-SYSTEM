<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config/database.php';

// Get professor details from session
$professor_id = $_SESSION['professor_id'] ?? null;
$professor_name = $_SESSION['professor_name'] ?? '';

// Get unread notifications count
$unread_count = 0;
$notifications = [];

if ($professor_id) {
    // Query for notifications (using the notifications table)
    $notification_query = "SELECT n.*, p.name as professor_name 
                         FROM notifications n
                         LEFT JOIN professors p ON n.message LIKE CONCAT('%', p.name, '%')
                         WHERE (p.id = ? OR p.id IS NULL)
                         ORDER BY n.created_at DESC 
                         LIMIT 10";
    $stmt = $conn->prepare($notification_query);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);

    // Count unread notifications for this professor
    $unread_query = "SELECT COUNT(*) as unread FROM notifications 
                    WHERE is_read = 0 AND message LIKE CONCAT('%', ?, '%')";
    $stmt = $conn->prepare($unread_query);
    $stmt->bind_param("s", $professor_name);
    $stmt->execute();
    $unread_result = $stmt->get_result();
    $unread_count = $unread_result->fetch_assoc()['unread'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar | Bicol University Polangui</title>

    <!-- Bootstrap & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Google Fonts (Poppins) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap">

    <style>
        /* ===== General Navbar Styling ===== */
        body {
            font-family: 'Poppins', sans-serif;
            padding-top: 70px;
            transition: background-color 0.3s ease-in-out;
        }

        .navbar {
            width: 100%;
            background-color: white;
            border-bottom: 4px solid #0099CC;
            padding: 10px 20px;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* ===== Logo & Title Styling ===== */
        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 20px;
            font-weight: 700;
            color: #0099CC;
            transition: transform 0.2s ease-in-out;
            margin-right: auto;
            /* Push logo to the left */
        }

        .navbar-brand:hover {
            transform: scale(1.03);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .navbar-brand img {
            height: 60px;
            width: auto;
        }

        .title-text {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
            margin-left: 10px;
            text-align: left;
        }

        /* ===== Navigation Items ===== */
        .navbar-nav {
            align-items: center;
        }

        .right-aligned-nav {
            display: flex;
            align-items: center;
            margin-left: auto;
            /* Push everything to the right */
            gap: 5px;
        }

        .navbar-nav .nav-link {
            font-size: 16px;
            font-weight: 500;
            color: black;
            transition: all 0.3s ease-in-out;
            padding: 8px 12px;
            border-radius: 6px;
            white-space: nowrap;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white;
            background-color: #0099CC;
        }

        /* ===== Dropdown Styling ===== */
        .dropdown-menu {
            border-radius: 8px;
            border: none;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-in-out;
            font-size: 15px;
        }

        .dropdown-item {
            padding: 10px 15px;
            transition: all 0.3s ease-in-out;
        }

        .dropdown-item:hover {
            background-color: #0099CC;
            color: white;
        }

        /* ===== Notification styles ===== */
        .notification-bell {
            position: relative;
            font-size: 1.2rem;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 10px;
        }

        .notification-bell:hover {
            color: #0099CC;
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: 3px;
            right: 3px;
            background-color: #ff6600;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-dropdown {
            width: 350px;
            /* Wider to accommodate more content */
            max-height: 500px;
            /* Limit height with scroll */
            overflow-y: auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .notification-header {
            position: sticky;
            top: 0;
            background-color: #0099CC;
            color: white;
            padding: 12px;
            font-weight: 600;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
            font-weight: 500;
        }

        .notification-item:hover {
            background-color: #e9ecef;
        }

        .notification-message {
            margin-bottom: 4px;
            font-size: 14px;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .notification-footer {
            position: sticky;
            bottom: 0;
            background-color: #f8f9fa;
            padding: 8px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .mark-all-read {
            background: none;
            border: none;
            color: white;
            font-size: 0.8rem;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .mark-all-read:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Pulse animation for new notifications */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .pulse {
            animation: pulse 0.5s ease;
        }

        /* ===== Profile Button ===== */
        .profile-link {
            padding: 8px 12px;
        }

        /* ===== Animations ===== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== Responsive Adjustments ===== */
        @media (max-width: 1199px) {
            .navbar-brand {
                font-size: 18px;
            }

            .title-text {
                font-size: 15px;
            }
        }

        @media (max-width: 991px) {
            .navbar {
                padding: 8px 15px;
            }

            .navbar-brand img {
                height: 55px;
            }

            .navbar-collapse {
                background-color: white;
                padding: 15px;
                border-radius: 8px;
                margin-top: 10px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }

            .right-aligned-nav {
                width: 100%;
                margin: 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #eee;
            }

            .dropdown-menu {
                text-align: center;
                margin-top: 5px;
            }
        }

        @media (max-width: 767px) {
            body {
                padding-top: 60px;
            }

            .navbar-brand {
                flex-direction: row;
                align-items: center;
            }

            .title-text {
                font-size: 14px;
                margin-left: 8px;
            }

            .navbar-brand img {
                height: 45px;
            }

            .navbar-nav .nav-link {
                font-size: 15px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 575px) {
            .navbar {
                padding: 6px 10px;
            }

            .navbar-brand {
                font-size: 16px;
            }

            .title-text {
                display: none;
            }

            .navbar-toggler {
                font-size: 1.25rem;
            }

            .navbar-nav .nav-link {
                font-size: 14px;
                padding: 5px 8px;
            }

            .notification-dropdown {
                width: 260px;
            }
        }

        @media (max-width: 400px) {
            .navbar-brand img {
                height: 40px;
            }

            .navbar-nav .nav-link i {
                margin-right: 5px;
            }
        }

        /* Add to navbar.php's style section */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .notification-bell.pulse {
            animation: pulse 0.5s ease;
            color: #FF6600;
        }

        /* Add transition for smoother badge updates */
        .notification-badge {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <!-- Left: Logos & System Title -->
            <a class="navbar-brand" href="../pages/index.php">
                <div class="logo-container">
                    <img src="../assets/images/bu-logo.png" alt="BU Logo">
                    <img src="../assets/images/polangui-logo.png" alt="Polangui Logo">
                </div>
                <span class="title-text">
                    <span style="color: #0099CC;">Bicol University</span>
                    <span style="color: #FF6600;">Polangui</span>
                    <span style="color: black; display: block;">CSD Professors' Attendance System</span>
                </span>
            </a>

            <!-- Mobile Menu Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- All navigation items moved to right side -->
                <ul class="navbar-nav right-aligned-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/index.php">
                            <i class="fas fa-home"></i> <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/attendance-report.php">
                            <i class="fas fa-file-alt"></i> <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/dashboard.php">
                            <i class="fas fa-chart-line"></i> <span>Statistics</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/settings.php">
                            <i class="fas fa-cog"></i> <span>Settings</span>
                        </a>
                    </li>

                   <!-- Notification Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notificationDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="notification-bell">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?= $unread_count ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <li class="notification-header">
                                <span>Notifications</span>
                            </li>
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $is_read = $notification['is_read'];
                                    $is_user_notification = strpos($notification['message'], $professor_name) !== false;
                                    ?>
                                    <li class="notification-item <?= (!$is_read && $is_user_notification) ? 'unread' : '' ?>"
                                        data-notification-id="<?= $notification['id'] ?>">
                                        <div class="notification-message">
                                            <?php if (!empty($notification['professor_name'])): ?>
                                                <span class="professor-name"><?= htmlspecialchars($notification['professor_name']) ?>:</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </div>
                                        <div class="notification-time">
                                            <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                            <span class="badge <?= $notification['type'] === 'check-in' ? 'bg-success' : ($notification['type'] === 'check-out' ? 'bg-warning' : 'bg-secondary') ?> ms-2">
                                                <?= ucfirst($notification['type']) ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="notification-item">
                                    <div class="notification-message">No notifications yet</div>
                                </li>
                            <?php endif; ?>
                            <li class="notification-footer">
                                <a href="notifications.php" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-list me-1"></i> View All Notifications
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link profile-link dropdown-toggle" href="#" id="profileDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <span>Profile</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="../pages/profile.php">
                                    <i class="fas fa-user me-2"></i> My Profile
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="../pages/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div> 
    </nav>

    <!-- Notification AJAX Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notificationSystem = {
                init() {
                    this.setupDropdownListeners();
                },

                setupDropdownListeners() {
                    // Mark as read when notification is clicked
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.addEventListener('click', (e) => {
                            const notificationId = item.dataset.notificationId;
                            if (notificationId) {
                                this.markAsRead(notificationId);
                                item.classList.remove('unread');
                                this.updateBadgeCount();
                            }
                        });
                    });
                },

                async markAsRead(notificationId) {
                    try {
                        const response = await fetch('../api/mark_notifications_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                notification_id: notificationId
                            })
                        });

                        const data = await response.json();
                        if (data.status !== 'success') {
                            console.error('Error marking notification as read:', data.message);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    }
                },

                updateBadgeCount() {
                    const unreadItems = document.querySelectorAll('.notification-item.unread');
                    const badge = document.querySelector('.notification-badge');

                    if (unreadItems.length > 0) {
                        if (badge) {
                            badge.textContent = unreadItems.length;
                            badge.style.display = 'flex';
                        }
                    } else if (badge) {
                        badge.style.display = 'none';
                    }
                }
            };

            notificationSystem.init();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>