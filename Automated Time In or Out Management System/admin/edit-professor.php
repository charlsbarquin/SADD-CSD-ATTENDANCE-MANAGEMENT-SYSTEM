<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Get professor data
$professor = null;
$error = '';
$success = '';

if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
    $stmt->bind_param('i', $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $professor = $result->fetch_assoc();
    
    if (!$professor) {
        $error = 'Professor not found';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_professor'])) {
    try {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $status = trim($_POST['status']);

        // Validation
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email exists for another professor
        $checkStmt = $conn->prepare("SELECT id FROM professors WHERE email = ? AND id != ?");
        $checkStmt->bind_param('si', $email, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            throw new Exception('Email already exists for another professor');
        }

        // Update professor
        $stmt = $conn->prepare("UPDATE professors SET name = ?, email = ?, phone = ?, department = ?, designation = ?, status = ? WHERE id = ?");
        $stmt->bind_param('ssssssi', $name, $email, $phone, $department, $designation, $status, $id);

        if ($stmt->execute()) {
            $success = 'Professor updated successfully!';
            // Refresh professor data
            $stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $professor = $result->fetch_assoc();
        } else {
            throw new Exception('Error updating professor: ' . $stmt->error);
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
    <title>Edit Professor | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
 
        .form-container {
            max-width: 650px;
            margin: 0 auto;
            padding: 1.75rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.6rem 0.9rem;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 0.6rem 1.25rem;
            font-size: 0.9rem;
            border-radius: 6px;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-3">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="page-title">Edit Professor</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-users.php"><i class="fas fa-users me-1"></i> Professors</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-edit me-1"></i> Edit</li>
                        </ol>
                    </nav>
                </div>
                <a href="manage-users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-3 d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success mb-3 d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($professor): ?>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $professor['id'] ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($professor['name']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($professor['email']) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($professor['phone']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($professor['department']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" value="<?= htmlspecialchars($professor['designation']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $professor['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $professor['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="pending" <?= $professor['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" name="update_professor" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <a href="manage-users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
                <?php elseif ($error === ''): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> No professor selected for editing.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>