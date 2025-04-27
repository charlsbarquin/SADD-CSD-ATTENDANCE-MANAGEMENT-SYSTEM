<?php
// admin/admin-login.php
session_start();
require_once '../config/database.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token!';
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Get admin data including lock status
        $stmt = $conn->prepare("
            SELECT id, username, password, login_attempts, last_attempt, account_locked, lock_until 
            FROM admins 
            WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        // Always show generic error (prevent username enumeration)
        $generic_error = "Invalid username or password";

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Check if account is locked
            if ($admin['account_locked'] && strtotime($admin['lock_until']) > time()) {
                $lock_time = ceil((strtotime($admin['lock_until']) - time()) / 60);
                $error = "Account locked. Try again in {$lock_time} minutes.";
            } else {
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    // Successful login - reset attempts
                    $resetStmt = $conn->prepare("
                        UPDATE admins 
                        SET login_attempts = 0, 
                            last_attempt = NULL,
                            account_locked = 0,
                            lock_until = NULL 
                        WHERE id = ?
                    ");
                    $resetStmt->bind_param("i", $admin['id']);
                    $resetStmt->execute();

                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['last_activity'] = time();

                    // Regenerate session ID
                    session_regenerate_id(true);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Failed login - increment attempts
                    $new_attempts = $admin['login_attempts'] + 1;
                    $max_attempts = 5; // Could fetch from security_policies table
                    $lock_duration = 30; // Minutes

                    // Check if we should lock the account
                    if ($new_attempts >= $max_attempts) {
                        $lock_until = date('Y-m-d H:i:s', strtotime("+{$lock_duration} minutes"));
                        $error = "Account locked for {$lock_duration} minutes due to too many failed attempts.";
                        
                        $lockStmt = $conn->prepare("
                            UPDATE admins 
                            SET login_attempts = ?,
                                last_attempt = NOW(),
                                account_locked = 1,
                                lock_until = ?,
                                last_failed_attempt_ip = ?
                            WHERE id = ?
                        ");
                        $lockStmt->bind_param("issi", $new_attempts, $lock_until, $_SERVER['REMOTE_ADDR'], $admin['id']);
                    } else {
                        $remaining = $max_attempts - $new_attempts;
                        $error = "{$generic_error}. Attempts remaining: {$remaining}";
                        
                        $lockStmt = $conn->prepare("
                            UPDATE admins 
                            SET login_attempts = ?,
                                last_attempt = NOW(),
                                last_failed_attempt_ip = ?
                            WHERE id = ?
                        ");
                        $lockStmt->bind_param("isi", $new_attempts, $_SERVER['REMOTE_ADDR'], $admin['id']);
                    }
                    $lockStmt->execute();
                }
            }
        } else {
            // Invalid username (but show generic message)
            $error = $generic_error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bicol University Polangui Admin Portal">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login | Bicol University Polangui</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0056b3;
            --secondary-color: #003d7a;
            --accent-color: #0099CC;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header img {
            height: 70px;
            margin: 0 10px 15px;
            transition: transform 0.3s ease;
        }

        .login-header img:hover {
            transform: scale(1.05);
        }

        .login-header h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 153, 204, 0.25);
        }

        .form-floating label {
            padding: 12px 15px;
            color: #6c757d;
        }

        .btn-login {
            background-color: var(--accent-color);
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .alert {
            border-radius: 8px;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header img {
                height: 60px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <img src="../assets/images/bu-logo.png" alt="Bicol University Logo" class="img-fluid">
                <img src="../assets/images/polangui-logo.png" alt="Polangui Logo" class="img-fluid">
                <h3>Admin Portal</h3>
                <p>Automated Time In/Out System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>

                <div class="form-floating mb-4 password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <span class="toggle-password" onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </div>
            </form>

            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> Bicol University Polangui. All rights reserved.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Clear error message when user starts typing
        document.getElementById('username').addEventListener('input', clearError);
        document.getElementById('password').addEventListener('input', clearError);

        function clearError() {
            const errorAlert = document.querySelector('.alert');
            if (errorAlert) {
                errorAlert.remove();
            }
        }

        // Disable form submissions if there are invalid fields
        (function() {
            'use strict';

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('form');

            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }

                        form.classList.add('was-validated');
                    }, false);
                });
        })();
    </script>
</body>

</html>