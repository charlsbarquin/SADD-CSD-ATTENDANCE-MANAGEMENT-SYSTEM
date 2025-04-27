<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Get attendance record ID
$attendance_id = $_GET['id'] ?? null;

if (!$attendance_id) {
    header('Location: manage-attendance.php');
    exit;
}

// Fetch the attendance record with AM/PM sessions
$stmt = $conn->prepare("SELECT a.*, p.name as professor_name 
                       FROM attendance a
                       JOIN professors p ON a.professor_id = p.id
                       WHERE a.id = ?");
$stmt->bind_param('i', $attendance_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    header('Location: manage-attendance.php');
    exit;
}

// Fetch all professors for dropdown
$professors = $conn->query("SELECT id, name FROM professors ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professor_id = $_POST['professor_id'] ?? null;
    $am_check_in = $_POST['am_check_in'] ?? null;
    $am_check_out = $_POST['am_check_out'] ?? null;
    $pm_check_in = $_POST['pm_check_in'] ?? null;
    $pm_check_out = $_POST['pm_check_out'] ?? null;
    $status = $_POST['status'] ?? 'absent';

    // Validate inputs
    $errors = [];

    if (empty($professor_id)) {
        $errors[] = "Professor is required";
    }

    // Validate AM session times
    if (!empty($am_check_out) && strtotime($am_check_out) < strtotime($am_check_in)) {
        $errors[] = "AM check-out time cannot be before check-in time";
    }

    // Validate PM session times
    if (!empty($pm_check_out) && strtotime($pm_check_out) < strtotime($pm_check_in)) {
        $errors[] = "PM check-out time cannot be before check-in time";
    }

    if (empty($errors)) {
        // Determine status based on check-ins/outs
        $new_status = 'absent';
        if (!empty($am_check_in) || !empty($pm_check_in)) {
            $new_status = 'present';
            if ((!empty($am_check_in) && empty($am_check_out)) || (!empty($pm_check_in) && empty($pm_check_out))) {
                $new_status = 'half-day';
            }
        }

        $stmt = $conn->prepare("UPDATE attendance 
                              SET professor_id = ?, 
                                  am_check_in = ?, 
                                  am_check_out = ?, 
                                  pm_check_in = ?, 
                                  pm_check_out = ?, 
                                  status = ?,
                                  checkin_date = COALESCE(?, checkin_date)
                              WHERE id = ?");
        
        // Use record date if no new date provided
        $checkin_date = $_POST['checkin_date'] ?? $record['checkin_date'];
        
        $stmt->bind_param(
            'issssssi',
            $professor_id,
            $am_check_in,
            $am_check_out,
            $pm_check_in,
            $pm_check_out,
            $new_status,
            $checkin_date,
            $attendance_id
        );

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Attendance record updated successfully";
            header("Location: manage-attendance.php");
            exit;
        } else {
            $errors[] = "Error updating record: " . $conn->error;
        }
    }
}

// Format datetime values for form inputs
$am_check_in_value = $record['am_check_in'] ? date('Y-m-d\TH:i', strtotime($record['am_check_in'])) : '';
$am_check_out_value = $record['am_check_out'] ? date('Y-m-d\TH:i', strtotime($record['am_check_out'])) : '';
$pm_check_in_value = $record['pm_check_in'] ? date('Y-m-d\TH:i', strtotime($record['pm_check_in'])) : '';
$pm_check_out_value = $record['pm_check_out'] ? date('Y-m-d\TH:i', strtotime($record['pm_check_out'])) : '';
$checkin_date_value = $record['checkin_date'] ? date('Y-m-d', strtotime($record['checkin_date'])) : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .time-input-group {
            position: relative;
        }
        .time-input-group .form-control {
            padding-left: 2.5rem;
        }
        .time-input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .session-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .session-header {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
        }
    </style>
</head>

<body>
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1 fw-bold">Edit Attendance Record</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-attendance.php"><i class="fas fa-calendar-check me-1"></i> Attendance</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Record</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Form Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Record for <?= htmlspecialchars($record['professor_name']) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="professor_id" class="form-label">Professor</label>
                                <select class="form-select" id="professor_id" name="professor_id" required>
                                    <option value="">Select Professor</option>
                                    <?php while ($professor = $professors->fetch_assoc()): ?>
                                        <option value="<?= $professor['id'] ?>"
                                            <?= $professor['id'] == $record['professor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($professor['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="checkin_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="checkin_date" name="checkin_date"
                                    value="<?= $checkin_date_value ?>" required>
                            </div>
                        </div>

                        <!-- AM Session -->
                        <div class="session-section">
                            <h5 class="session-header">AM Session</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="am_check_in" class="form-label">Check-In</label>
                                    <div class="time-input-group">
                                        <i class="fas fa-clock"></i>
                                        <input type="datetime-local" class="form-control" id="am_check_in" name="am_check_in"
                                            value="<?= $am_check_in_value ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="am_check_out" class="form-label">Check-Out</label>
                                    <div class="time-input-group">
                                        <i class="fas fa-clock"></i>
                                        <input type="datetime-local" class="form-control" id="am_check_out" name="am_check_out"
                                            value="<?= $am_check_out_value ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PM Session -->
                        <div class="session-section">
                            <h5 class="session-header">PM Session</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pm_check_in" class="form-label">Check-In</label>
                                    <div class="time-input-group">
                                        <i class="fas fa-clock"></i>
                                        <input type="datetime-local" class="form-control" id="pm_check_in" name="pm_check_in"
                                            value="<?= $pm_check_in_value ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pm_check_out" class="form-label">Check-Out</label>
                                    <div class="time-input-group">
                                        <i class="fas fa-clock"></i>
                                        <input type="datetime-local" class="form-control" id="pm_check_out" name="pm_check_out"
                                            value="<?= $pm_check_out_value ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <a href="manage-attendance.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum check-out times based on check-in times
        document.getElementById('am_check_in').addEventListener('change', function() {
            const checkOutField = document.getElementById('am_check_out');
            checkOutField.min = this.value;
            if (checkOutField.value && checkOutField.value < this.value) {
                checkOutField.value = '';
            }
        });

        document.getElementById('pm_check_in').addEventListener('change', function() {
            const checkOutField = document.getElementById('pm_check_out');
            checkOutField.min = this.value;
            if (checkOutField.value && checkOutField.value < this.value) {
                checkOutField.value = '';
            }
        });
    </script>
</body>

</html>