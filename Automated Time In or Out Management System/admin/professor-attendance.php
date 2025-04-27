<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Get professor ID from URL
$professor_id = $_GET['id'] ?? null;

if (!$professor_id) {
    header('Location: reports.php?report_type=professor_activity');
    exit;
}

// Get professor details
$professor_stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
$professor_stmt->bind_param('i', $professor_id);
$professor_stmt->execute();
$professor = $professor_stmt->get_result()->fetch_assoc();

if (!$professor) {
    header('Location: reports.php?report_type=professor_activity');
    exit;
}

// Get filter parameters
$date_range = $_GET['date_range'] ?? 'this_month';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Calculate date ranges
$today = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

switch ($date_range) {
    case 'today':
        $start_date = $today;
        $end_date = $today;
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = $start_date;
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        $start_date = $custom_start;
        $end_date = $custom_end;
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Get attendance records
$query = "SELECT 
            a.id,
            DATE(a.check_in) as date,
            TIME(a.check_in) as check_in_time,
            TIME(a.check_out) as check_out_time,
            a.status,
            TIMESTAMPDIFF(MINUTE, a.check_in, a.check_out) as duration_minutes
          FROM attendance a
          WHERE a.professor_id = ?
          AND DATE(a.check_in) BETWEEN ? AND ?";

// Apply status filter
$params = [$professor_id, $start_date, $end_date];
$types = 'iss';

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= " ORDER BY a.check_in DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendance_data = $stmt->get_result();

// Get min/max dates for datepicker
$date_bounds = $conn->query("SELECT MIN(DATE(check_in)) as min_date, MAX(DATE(check_in)) as max_date FROM attendance WHERE professor_id = $professor_id")->fetch_assoc();

// Calculate statistics
$stats_query = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                  SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                  SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                  AVG(TIMESTAMPDIFF(MINUTE, check_in, check_out)) as avg_duration
                FROM attendance
                WHERE professor_id = ?
                AND DATE(check_in) BETWEEN ? AND ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param('iss', $professor_id, $start_date, $end_date);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Attendance | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .professor-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }

        .stat-card {
            border-radius: 8px;
            overflow: hidden;
        }

        .stat-card .card-body {
            padding: 1.25rem;
        }

        .stat-card .stat-icon {
            font-size: 1.75rem;
            opacity: 0.8;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }

        .table-attendance {
            font-size: 0.9rem;
        }

        .table-attendance th {
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #495057;
        }

        .badge-present {
            background-color: #28a745;
        }

        .badge-late {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-absent {
            background-color: #dc3545;
        }

        .date-range-btn.active {
            background-color: var(--primary-color);
            color: white;
        }

        /* Export buttons styling */
        .dt-buttons .btn {
            border-radius: 4px !important;
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .dt-buttons .btn i {
            margin-right: 3px;
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
                    <h2 class="mb-1 fw-bold">Professor Attendance</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="reports.php?report_type=professor_activity"><i class="fas fa-chart-bar me-1"></i> Professor Reports</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($professor['name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                </div>
            </div>

            <!-- Professor Information -->
            <div class="card mb-4 professor-card">
                <div class="card-body">
                    <h4 class="mb-3 text-primary font-weight-bold"><?= htmlspecialchars($professor['name']) ?></h4>
                    <div class="row gx-3"> <!-- Horizontal gutter only -->
                        <div class="col-md-4 mb-2"> <!-- Added bottom margin -->
                            <div class="professor-detail">
                                <div class="text-muted small mb-1">Department</div>
                                <div class="font-weight-medium"><?= htmlspecialchars($professor['department']) ?></div>
                            </div>
                            <div class="professor-detail mt-2"> <!-- Smaller top margin -->
                                <div class="text-muted small mb-1">Phone</div>
                                <div class="font-weight-medium"><?= htmlspecialchars($professor['phone']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="professor-detail">
                                <div class="text-muted small mb-1">Designation</div>
                                <div class="font-weight-medium"><?= htmlspecialchars($professor['designation']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="professor-detail">
                                <div class="text-muted small mb-1">Email</div>
                                <div class="font-weight-medium">
                                    <a href="mailto:<?= htmlspecialchars($professor['email']) ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($professor['email']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" id="attendanceForm">
                        <input type="hidden" name="id" value="<?= $professor_id ?>">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="Present" <?= $status_filter === 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Late" <?= $status_filter === 'Late' ? 'selected' : '' ?>>Late</option>
                                    <option value="Absent" <?= $status_filter === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                </select>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Date Range</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="date_range" id="today" value="today" autocomplete="off" <?= $date_range === 'today' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="today">Today</label>

                                    <input type="radio" class="btn-check" name="date_range" id="yesterday" value="yesterday" autocomplete="off" <?= $date_range === 'yesterday' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="yesterday">Yesterday</label>

                                    <input type="radio" class="btn-check" name="date_range" id="this_week" value="this_week" autocomplete="off" <?= $date_range === 'this_week' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="this_week">This Week</label>

                                    <input type="radio" class="btn-check" name="date_range" id="last_week" value="last_week" autocomplete="off" <?= $date_range === 'last_week' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="last_week">Last Week</label>

                                    <input type="radio" class="btn-check" name="date_range" id="this_month" value="this_month" autocomplete="off" <?= $date_range === 'this_month' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="this_month">This Month</label>

                                    <input type="radio" class="btn-check" name="date_range" id="custom" value="custom" autocomplete="off" <?= $date_range === 'custom' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="custom">Custom</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3 <?= $date_range !== 'custom' ? 'd-none' : '' ?>" id="customDateRange">
                            <div class="col-md-6">
                                <label for="custom_start" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="custom_start" name="custom_start"
                                    value="<?= htmlspecialchars($custom_start) ?>"
                                    min="<?= $date_bounds['min_date'] ?>"
                                    max="<?= $date_bounds['max_date'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="custom_end" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="custom_end" name="custom_end"
                                    value="<?= htmlspecialchars($custom_end) ?>"
                                    min="<?= $date_bounds['min_date'] ?>"
                                    max="<?= $date_bounds['max_date'] ?>">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-primary bg-opacity-10 border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="stat-label">Total Records</h6>
                                    <h3 class="stat-value"><?= $stats['total'] ?? 0 ?></h3>
                                </div>
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card stat-card bg-success bg-opacity-10 border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="stat-label">Present</h6>
                                    <h3 class="stat-value"><?= $stats['present'] ?? 0 ?></h3>
                                </div>
                                <div class="stat-icon text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card stat-card bg-warning bg-opacity-10 border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="stat-label">Late Arrivals</h6>
                                    <h3 class="stat-value"><?= $stats['late'] ?? 0 ?></h3>
                                </div>
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card stat-card bg-info bg-opacity-10 border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="stat-label">Avg. Duration</h6>
                                    <h3 class="stat-value">
                                        <?php if ($stats['avg_duration']): ?>
                                            <?= floor($stats['avg_duration'] / 60) ?>h <?= $stats['avg_duration'] % 60 ?>m
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </h3>
                                </div>
                                <div class="stat-icon text-info">
                                    <i class="fas fa-stopwatch"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Attendance Records</h5>
                    <div class="text-muted">
                        <?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="attendanceTable" class="table table-attendance table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($attendance_data->num_rows > 0): ?>
                                    <?php while ($record = $attendance_data->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                            <td><?= date('h:i A', strtotime($record['check_in_time'])) ?></td>
                                            <td>
                                                <?php if ($record['check_out_time']): ?>
                                                    <?= date('h:i A', strtotime($record['check_out_time'])) ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not checked out</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= strtolower($record['status']) ?>">
                                                    <?= $record['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['duration_minutes']): ?>
                                                    <?= floor($record['duration_minutes'] / 60) ?>h <?= $record['duration_minutes'] % 60 ?>m
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="edit-attendance.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            No attendance records found for the selected filters
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable({
                responsive: true,
                dom: '<"top"Bf>rt<"bottom"lip><"clear">',
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: '<?= htmlspecialchars($professor['name']) ?> Attendance',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4] // Exclude Actions column (index 5)
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: '<?= htmlspecialchars($professor['name']) ?> Attendance',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4] // Exclude Actions column (index 5)
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv me-1"></i> CSV',
                        className: 'btn btn-sm btn-secondary',
                        title: '<?= htmlspecialchars($professor['name']) ?> Attendance',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4] // Exclude Actions column (index 5)
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm btn-info',
                        title: '<?= htmlspecialchars($professor['name']) ?> Attendance',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4] // Exclude Actions column (index 5)
                        }
                    },
                ],
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100]
            });
        });

        // Show/hide custom date range fields
        $('input[name="date_range"]').change(function() {
            if ($(this).val() === 'custom') {
                $('#customDateRange').removeClass('d-none');
            } else {
                $('#customDateRange').addClass('d-none');
            }
        });

        // Initialize date pickers
        flatpickr('#custom_start', {
            dateFormat: 'Y-m-d',
            maxDate: $('#custom_end').val() || '<?= $date_bounds['max_date'] ?>',
            defaultDate: '<?= $custom_start ?>'
        });

        flatpickr('#custom_end', {
            dateFormat: 'Y-m-d',
            minDate: $('#custom_start').val() || '<?= $date_bounds['min_date'] ?>',
            defaultDate: '<?= $custom_end ?>'
        });

        // Update min/max dates when custom dates change
        $('#custom_start').change(function() {
            $('#custom_end').attr('min', $(this).val());
        });

        $('#custom_end').change(function() {
            $('#custom_start').attr('max', $(this).val());
        });
    </script>
</body>

</html>