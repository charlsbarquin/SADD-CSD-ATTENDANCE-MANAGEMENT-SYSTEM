<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Fetch professors for dropdown
$professors = $conn->query("SELECT id, name FROM professors WHERE status = 'active' ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professor_id = $_POST['professor_id'];
    $session_type = $_POST['session_type'];
    $status = $_POST['status'];
    $current_time = date('Y-m-d H:i:s');

    try {
        // Check if attendance record exists for today
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE professor_id = ? AND checkin_date = CURDATE()");
        $check_stmt->bind_param("i", $professor_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        // Get professor name for logging
        $prof_stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
        $prof_stmt->bind_param("i", $professor_id);
        $prof_stmt->execute();
        $prof_result = $prof_stmt->get_result();
        $professor = $prof_result->fetch_assoc();
        $professor_name = $professor['name'];

        if ($check_result->num_rows > 0) {
            // Update existing record
            $attendance = $check_result->fetch_assoc();

            if ($session_type === 'am') {
                $stmt = $conn->prepare("UPDATE attendance SET 
    am_check_in = ?,
    status = ?,
    recorded_at = NOW()
    WHERE id = ?");
                $stmt->bind_param("ssi", $current_time, $status, $attendance['id']);
                $log_action = "AM Check-in: $professor_name";
            } else {
                $stmt = $conn->prepare("UPDATE attendance SET 
    pm_check_in = ?,
    status = ?,
    recorded_at = NOW()
    WHERE id = ?");
                $stmt->bind_param("ssi", $current_time, $status, $attendance['id']);
                $log_action = "PM Check-in: $professor_name";
            }
        } else {
            // Create new record
            if ($session_type === 'am') {
                $stmt = $conn->prepare("INSERT INTO attendance 
                (professor_id, checkin_date, am_check_in, status, recorded_at) 
                VALUES (?, CURDATE(), ?, ?, NOW())");
                $stmt->bind_param("iss", $professor_id, $current_time, $status);
                $log_action = "AM Check-in: $professor_name";
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance 
                (professor_id, checkin_date, pm_check_in, status, recorded_at) 
                VALUES (?, CURDATE(), ?, ?, NOW())");
                $stmt->bind_param("iss", $professor_id, $current_time, $status);
                $log_action = "PM Check-in: $professor_name";
            }
        }

        if ($stmt->execute()) {
            // Log the action
            $log_stmt = $conn->prepare("INSERT INTO logs (action, user) VALUES (?, 'system')");
            $log_stmt->bind_param("s", $log_action);
            $log_stmt->execute();

            $_SESSION['success_message'] = "Attendance record added successfully!";
            header('Location: manage-attendance.php');
            exit;
        } else {
            throw new Exception("Error: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Attendance Record | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .form-select,
        .form-control {
            border-radius: 6px;
            padding: 0.6rem 0.9rem;
        }

        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0 fw-bold">Add Attendance Record</h2>
                <a href="manage-attendance.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Records
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="professor_id" class="form-label">Professor</label>
                            <select class="form-select" id="professor_id" name="professor_id" required>
                                <option value="">Select Professor</option>
                                <?php while ($prof = $professors->fetch_assoc()): ?>
                                    <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="session_type" class="form-label">Session Type</label>
                            <select class="form-select" id="session_type" name="session_type" required>
                                <option value="am">AM Session</option>
                                <option value="pm">PM Session</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            Swal.fire({
                title: 'Success!',
                text: '<?= $_SESSION['success_message'] ?>',
                icon: 'success',
                timer: 3000
            });
        </script>
    <?php unset($_SESSION['success_message']);
    endif; ?>
</body>

</html>