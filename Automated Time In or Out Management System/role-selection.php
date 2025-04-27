<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['professor_logged_in']) || isset($_SESSION['admin_logged_in'])) {
    header('Location: ../pages/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Bicol University Polangui</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0056b3;
            --secondary-color: #003d7a;
            --accent-color: #0099CC;
            --admin-color: #0099cc;
            --professor-color: #ff6600;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }

        .selection-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .selection-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .selection-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .selection-header img {
            height: 70px;
            margin: 0 10px 15px;
            transition: transform 0.3s ease;
        }

        .selection-header img:hover {
            transform: scale(1.05);
        }

        .selection-header h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .selection-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .role-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            cursor: pointer;
            text-align: center;
            padding: 25px 15px;
        }

        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .role-card.admin {
            background-color: rgba(0, 153, 204, 0.1);
            /* Updated to match new admin color */
            border: 1px solid var(--admin-color);
        }

        .role-card.professor {
            background-color: rgba(255, 102, 0, 0.1);
            /* Updated to match new professor color */
            border: 1px solid var(--professor-color);
        }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .admin .role-icon {
            color: var(--admin-color);
        }

        .professor .role-icon {
            color: var(--professor-color);
        }

        .role-title {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .admin .role-title {
            color: var(--admin-color);
        }

        .professor .role-title {
            color: var(--professor-color);
        }

        .role-description {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="selection-container">
            <div class="selection-header">
                <img src="assets/images/bu-logo.png" alt="Bicol University Logo" class="img-fluid">
                <img src="assets/images/polangui-logo.png" alt="Polangui Logo" class="img-fluid">
                <h3>Welcome to Automated Time In/Out System</h3>
                <p>Please select your role to continue</p>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="role-card professor" onclick="window.location.href='pages/login.php'">
                        <div class="role-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h4 class="role-title">Professor</h4>
                        <p class="role-description">
                            Access your attendance records and time in/out functionality
                        </p>
                        <div class="mt-3">
                            <a href="pages/login.php" class="btn btn-outline-warning" style="border-color: #ff6600; color: #ff6600;">Login</a>
                            <a href="pages/signup.php" class="btn ms-2" style="background-color: #ff6600; border-color: #ff6600; color: white;">Sign Up</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="role-card admin" onclick="window.location.href='admin/admin-login.php'">
                        <div class="role-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4 class="role-title">Administrator</h4>
                        <p class="role-description">
                            Manage system settings and professor accounts
                        </p>
                        <div class="mt-3">
                            <a href="admin/admin-login.php" class="btn btn-outline-info" style="border-color: #0099cc; color: #0099cc;">Admin Login</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> Bicol University Polangui. All rights reserved.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>