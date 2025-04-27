<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Initialize all variables with default values
$professor = [
    'name' => '',
    'email' => '',
    'designation' => '',
    'department' => '',
    'phone' => ''
];
$first_name = '';
$middle_initial = '';
$last_name = '';
$showModal = false;
$modalTitle = '';
$modalMessage = '';
$modalType = ''; // 'success' or 'danger'

// Check if user is logged in
if (!isset($_SESSION['professor_id'])) {
    header('Location: login.php');
    exit;
}

// Get professor data
$professor_id = $_SESSION['professor_id'];
$stmt = $conn->prepare("SELECT id, name, email, designation, department, phone FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $professor = $result->fetch_assoc();

        // Split full name into parts
        $name_parts = explode(' ', $professor['name']);

        // Last name is always the last part
        $last_name = array_pop($name_parts);

        // Middle initial is the last character of the last part before the surname
        // (assuming format: FirstName [MiddleName] LastName)
        if (count($name_parts) > 1) {
            $middle_initial = substr(end($name_parts), 0, 1); // Get first character of middle name
        }

        // First name is everything before the middle initial
        $first_name = implode(' ', $name_parts);
    } else {
        $showModal = true;
        $modalTitle = 'Error';
        $modalMessage = 'Professor record not found!';
        $modalType = 'danger';
    }
} else {
    $showModal = true;
    $modalTitle = 'Database Error';
    $modalMessage = $conn->error;
    $modalType = 'danger';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    // Combine name parts (include middle initial only if provided)
    $full_name = trim("$first_name " . ($middle_initial ? "$middle_initial. " : "") . "$last_name");

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $showModal = true;
        $modalTitle = 'Validation Error';
        $modalMessage = 'First name, last name, email and phone are required!';
        $modalType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $showModal = true;
        $modalTitle = 'Validation Error';
        $modalMessage = 'Please enter a valid email address';
        $modalType = 'danger';
    } else {
        // Start transaction for atomic update
        $conn->begin_transaction();

        try {
            $update_stmt = $conn->prepare("UPDATE professors SET 
                name = ?, 
                email = ?, 
                designation = ?, 
                phone = ? 
                WHERE id = ?");

            $update_stmt->bind_param("ssssi", $full_name, $email, $designation, $phone, $professor_id);

            if ($update_stmt->execute()) {
                $conn->commit();
                $showModal = true;
                $modalTitle = 'Success';
                $modalMessage = 'Profile updated successfully!';
                $modalType = 'success';

                // Refresh professor data
                $stmt->execute();
                $result = $stmt->get_result();
                $professor = $result->fetch_assoc();

                // Update name parts
                $name_parts = explode(' ', $professor['name']);
                $last_name = array_pop($name_parts);
                $middle_initial = (count($name_parts) > 1) ? substr(end($name_parts), 0, 1) : '';
                $first_name = implode(' ', $name_parts);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $showModal = true;
            $modalTitle = 'Error';
            $modalMessage = 'Error updating profile: ' . $e->getMessage();
            $modalType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Automated Attendance System</title>

    <!-- Bootstrap & Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/attendance-report.css">

    <style>
        .main-container {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
            padding: 2rem 0;
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

        .page-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .profile-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header h3 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            color: #6c757d;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
        }

        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: #3a5ccc;
            border-color: #3a5ccc;
        }

        .button-container {
            text-align: center;
            margin-top: 1.5rem;
        }

        @media (max-width: 768px) {
            .profile-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>


    <div class="container-fluid py-4">
        <div class="main-container">
            <div class="container">
                <!-- Header Section -->
                <div class="page-header">
                    <h1><i class="fas fa-user-circle me-2"></i>My Profile</h1>
                    <p class="page-subtitle">Update your personal information</p>
                </div>

                <!-- Profile Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="profile-card">
                            <div class="profile-header">
                                <h3><?php echo htmlspecialchars($professor['name']); ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars($professor['department']); ?></p>
                            </div>

                            <form method="POST" id="profileForm">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name(s)</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                            value="<?php echo htmlspecialchars($first_name); ?>" required>
                                        <small class="text-muted">Enter all first names (e.g. "Juan Carlos")</small>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="middle_initial" class="form-label">M.I.</label>
                                        <input type="text" class="form-control middle-initial-field" id="middle_initial"
                                            name="middle_initial" maxlength="1"
                                            value="<?php echo htmlspecialchars($middle_initial); ?>">
                                        <small class="text-muted">Middle initial only</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                            value="<?php echo htmlspecialchars($last_name); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($professor['email']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($professor['phone']); ?>" required
                                        pattern="[0-9]{10,15}" title="Please enter a valid phone number">
                                </div>

                                <div class="mb-3">
                                    <label for="designation" class="form-label">Designation</label>
                                    <input type="text" class="form-control" id="designation" name="designation"
                                        value="<?php echo htmlspecialchars($professor['designation']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department"
                                        value="<?php echo htmlspecialchars($professor['department']); ?>" readonly>
                                </div>

                                <div class="button-container">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Messages -->
    <?php if ($showModal): ?>
        <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="messageModalLabel"><?php echo htmlspecialchars($modalTitle); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo htmlspecialchars($modalMessage); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-<?php echo $modalType === 'success' ? 'primary' : 'secondary'; ?>" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Client-side validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            if (!/^[0-9]{10,15}$/.test(phone)) {
                alert('Please enter a valid phone number (10-15 digits)');
                e.preventDefault();
            }
        });

        // Show modal if needed
        <?php if ($showModal): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
                messageModal.show();

                // Redirect on success modal close if update was successful
                <?php if ($modalType === 'success'): ?>
                    document.getElementById('messageModal').addEventListener('hidden.bs.modal', function() {
                        window.location.href = 'profile.php';
                    });
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>