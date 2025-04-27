<?php
session_start();
require_once '../config/database.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Redirect if already logged in
if (isset($_SESSION['professor_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Add link back to role selection
$back_link = '<div class="text-center mt-3">
    <p>Not a professor? <a href="../role-selection.php">Select different role</a></p>
</div>';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token'])) {
        $error = 'Invalid request!';
    } else {
        $csrf_token = $_POST['csrf_token'];
        if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            $error = 'Invalid CSRF token!';
        } else {
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);

            if (empty($email) || empty($password)) {
                $error = 'Email and password are required!';
            } else {
                // Rate limiting - simple implementation
                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['last_login_attempt'] = time();
                }

                if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_login_attempt']) < 300) {
                    $error = 'Too many login attempts. Please try again in 5 minutes.';
                } else {
                    // Check in professors table
                    $stmt = $conn->prepare("SELECT id, name, email, status FROM professors WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $professor = $result->fetch_assoc();

                        // Check if account is approved
                        if ($professor['status'] !== 'active') {
                            $error = 'Your account is not yet approved. Please contact the administrator.';
                        } else {
                            // In a real system, you would verify the password here
                            // Since your professors table doesn't have passwords yet, we'll just log them in
                            // You should add password fields to the professors table and implement proper authentication

                            $_SESSION['professor_logged_in'] = true;
                            $_SESSION['professor_id'] = $professor['id'];
                            $_SESSION['professor_name'] = $professor['name'];
                            $_SESSION['professor_email'] = $professor['email'];
                            $_SESSION['last_activity'] = time();

                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);

                            header('Location: index.php');
                            exit;
                        }
                    } else {
                        // Generic error message to prevent email enumeration
                        $error = 'Invalid credentials!';
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_login_attempt'] = time();
                    }
                }
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bicol University Polangui Professor Portal">
    <meta name="robots" content="noindex, nofollow">
    <title>Professor Login | Bicol University Polangui</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <h3>Professor Portal</h3>
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
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" required autofocus>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
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

            <div class="text-center mt-3">
                <p>Don't have an account? <a href="signup.php">Register here</a></p>
            </div>

            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> Bicol University Polangui. All rights reserved.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
        document.getElementById('email').addEventListener('input', clearError);
        document.getElementById('password').addEventListener('input', clearError);

        function clearError() {
            const errorAlert = document.querySelector('.alert');
            if (errorAlert) {
                errorAlert.remove();
            }
        }
    </script>
</body>

</html>