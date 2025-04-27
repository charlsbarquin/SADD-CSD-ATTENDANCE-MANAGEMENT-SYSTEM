<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $status = 'active'; // Default status for new professors

        // Basic validation
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required fields');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }

        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM professors WHERE email = ?");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            throw new Exception('A professor with this email already exists');
        }

        // Insert professor data
        $stmt = $conn->prepare("INSERT INTO professors (name, email, phone, department, designation, status) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $name, $email, $phone, $department, $designation, $status);

        if ($stmt->execute()) {
            $success = 'Professor added successfully!';
            // Clear form
            $_POST = array();
        } else {
            throw new Exception('Error adding professor: ' . $stmt->error);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Professor | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
        }

        .form-container {
            max-width: 650px;
            /* Reduced from 800px */
            margin: 0 auto;
            padding: 1.75rem;
            /* Reduced from 2.5rem */
            background: white;
            border-radius: 10px;
            /* Slightly smaller radius */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            /* More subtle shadow */
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            /* Smaller radius */
            padding: 0.6rem 0.9rem;
            /* More compact padding */
            border: 1px solid #e0e0e0;
            font-size: 0.9rem;
            /* Slightly smaller font */
            height: auto;
            /* Remove fixed height */
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
            /* Lighter focus shadow */
        }

        .btn {
            padding: 0.6rem 1.25rem;
            /* More compact buttons */
            font-size: 0.9rem;
            border-radius: 6px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .page-title {
            font-size: 1.5rem;
            /* Slightly smaller title */
            margin-bottom: 0.4rem;
        }

        .breadcrumb {
            font-size: 0.85rem;
            /* Smaller breadcrumb */
        }

        .alert {
            padding: 0.75rem 1rem;
            /* More compact alerts */
            font-size: 0.9rem;
        }

        .alert i {
            font-size: 1.1rem;
            /* Slightly smaller icons */
            margin-right: 0.75rem;
        }

        .form-label {
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .row.g-4 {
            --bs-gutter-y: 1rem;
            /* Reduced vertical gap */
        }

        .mt-4.pt-2 {
            margin-top: 1.25rem !important;
            /* Reduced top margin */
            padding-top: 0.5rem !important;
        }
    </style>
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-3"> <!-- Reduced padding -->
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3"> <!-- Reduced margin -->
                <div>
                    <h1 class="page-title">Add New Professor</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-users.php"><i class="fas fa-users me-1"></i> Professors</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-plus me-1"></i> Add Professor</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-3 d-flex align-items-center"> <!-- Reduced margin -->
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="ms-2"><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success mb-3 d-flex align-items-center"> <!-- Reduced margin -->
                        <i class="fas fa-check-circle"></i>
                        <div class="ms-2">
                            <div class="fw-bold"><?= htmlspecialchars($success) ?></div>
                            <div class="mt-1"> <!-- Reduced margin -->
                                <a href="add-professor.php" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-plus me-1"></i> Add Another
                                </a>
                                <a href="manage-users.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list me-1"></i> View All
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="professorForm" method="POST">
                    <div class="row g-3"> <!-- Reduced gap -->
                        <div class="col-md-6">
                            <label for="name" class="form-label required-field">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>">
                        </div>

                        <div class="col-12 mt-3 pt-1"> <!-- Reduced margins -->
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Save
                                <span class="spinner-border spinner-border-sm d-none ms-1"></span>
                            </button>
                            <a href="manage-users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#professorForm').submit(function() {
                const btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true);
                btn.find('.spinner-border').removeClass('d-none');
                return true;
            });

            <?php if ($success): ?>
                $('#professorForm')[0].reset();
            <?php endif; ?>
        });
    </script>
</body>

</html>