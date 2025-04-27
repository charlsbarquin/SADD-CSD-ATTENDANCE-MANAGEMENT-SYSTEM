<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['professor_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Add link back to role selection
$back_link = '<div class="text-center mt-3">
    <p>Already have an account? <a href="login.php">Login here</a> or <a href="../role-selection.php">select different role</a></p>
</div>';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request!';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        $designation = trim($_POST['designation']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validate inputs
        if (empty($name) || empty($email) || empty($phone) || empty($department) || 
            empty($username) || empty($password) || empty($confirm_password)) {
            $error = 'All fields are required!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format!';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match!';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters!';
        } else {
            // Check if email or username already exists
            $stmt = $conn->prepare("SELECT id FROM professors WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Email or username already exists!';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new professor
                $stmt = $conn->prepare("INSERT INTO professors 
                                      (name, email, phone, department, designation, username, password, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("sssssss", $name, $email, $phone, $department, $designation, $username, $hashed_password);
                
                if ($stmt->execute()) {
                    $success = 'Registration successful! Your account will be activated after admin approval.';
                    // Clear form
                    $_POST = array();
                } else {
                    $error = 'Registration failed. Please try again.';
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
    <title>Professor Registration | Bicol University Polangui</title>
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
        
        .signup-container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background-color: white;
            position: relative;
            overflow: hidden;
        }
        
        .signup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .signup-header img {
            height: 70px;
            margin: 0 10px 15px;
            transition: transform 0.3s ease;
        }
        
        .signup-header img:hover {
            transform: scale(1.05);
        }
        
        .signup-header h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .signup-header p {
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
        
        .btn-signup {
            background-color: var(--accent-color);
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-signup:hover {
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
        
        @media (max-width: 768px) {
            .signup-container {
                padding: 30px 20px;
            }
            
            .signup-header img {
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="signup-header">
                <img src="../assets/images/bu-logo.png" alt="Bicol University Logo" class="img-fluid">
                <img src="../assets/images/polangui-logo.png" alt="Polangui Logo" class="img-fluid">
                <h3>Professor Registration</h3>
                <p>Automated Time In/Out System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Full Name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            <label for="name"><i class="fas fa-user me-2"></i>Full Name</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Email Address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   placeholder="Phone Number" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            <label for="phone"><i class="fas fa-phone me-2"></i>Phone Number</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="department" name="department" 
                                   placeholder="Department" required value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                            <label for="department"><i class="fas fa-building me-2"></i>Department</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   placeholder="Designation" value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>">
                            <label for="designation"><i class="fas fa-briefcase me-2"></i>Designation (Optional)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            <label for="username"><i class="fas fa-user-tag me-2"></i>Username</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3 password-container">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                            <span class="toggle-password" onclick="togglePasswordVisibility('password', 'toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3 password-container">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm Password" required>
                            <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                            <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'toggleIcon2')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-signup">
                        <i class="fas fa-user-plus me-2"></i> Register
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> Bicol University Polangui. All rights reserved.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePasswordVisibility(fieldId, iconId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
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
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', clearError);
        });
        
        function clearError() {
            const errorAlert = document.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.remove();
            }
        }
    </script>
</body>
</html>