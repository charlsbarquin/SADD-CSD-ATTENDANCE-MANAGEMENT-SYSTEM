<?php
session_start();
require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'daily_summary';
$date_range = $_GET['date_range'] ?? 'today';
$department_filter = $_GET['department'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$session_filter = $_GET['session'] ?? 'all';

// Calculate date ranges
$today = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

switch ($date_range) {
    case 'today':
        $start_date = $today;
        $end_date = $today;
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    default:
        $start_date = $today;
        $end_date = $today;
}

// Get departments for filter dropdown
$departments = $conn->query("SELECT DISTINCT department FROM professors ORDER BY department");

// Generate reports based on type
switch ($report_type) {
    case 'daily_summary':
        $report_title = "Daily Attendance Summary";
        $query = "SELECT 
                    DATE(a.checkin_date) as date,
                    COUNT(DISTINCT a.professor_id) as total_professors,
                    SUM(CASE WHEN (a.am_check_in IS NOT NULL OR a.pm_check_in IS NOT NULL) THEN 1 ELSE 0 END) as total_attendance,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_day,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN (a.am_check_in IS NOT NULL AND a.am_check_out IS NULL) OR 
                                (a.pm_check_in IS NOT NULL AND a.pm_check_out IS NULL) THEN 1 ELSE 0 END) as pending_timeouts
                  FROM attendance a
                  JOIN professors p ON a.professor_id = p.id
                  WHERE DATE(a.checkin_date) BETWEEN ? AND ?";

        $params = [$start_date, $end_date];
        $types = 'ss';

        if ($department_filter !== 'all') {
            $query .= " AND p.department = ?";
            $params[] = $department_filter;
            $types .= 's';
        }

        if ($status_filter !== 'all') {
            $query .= " AND a.status = ?";
            $params[] = $status_filter;
            $types .= 's';
        }

        $query .= " GROUP BY DATE(a.checkin_date) ORDER BY date DESC";
        break;

    case 'professor_activity':
        $report_title = "Professor Activity Report";
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.department,
                    p.designation,
                    COUNT(DISTINCT a.checkin_date) as total_days,
                    SUM(CASE WHEN a.am_check_in IS NOT NULL THEN 1 ELSE 0 END) as am_timeins,
                    SUM(CASE WHEN a.pm_check_in IS NOT NULL THEN 1 ELSE 0 END) as pm_timeins,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_day,
                    AVG(
                        CASE 
                            WHEN a.am_check_in IS NOT NULL AND a.pm_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.am_check_in, a.pm_check_out)
                            WHEN a.am_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.am_check_in, a.am_check_out)
                            WHEN a.pm_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.pm_check_in, a.pm_check_out)
                            ELSE 0
                        END
                    ) as avg_duration
                  FROM professors p
                  LEFT JOIN attendance a ON p.id = a.professor_id AND DATE(a.checkin_date) BETWEEN ? AND ?
                  WHERE 1=1";

        $params = [$start_date, $end_date];
        $types = 'ss';

        if ($department_filter !== 'all') {
            $query .= " AND p.department = ?";
            $params[] = $department_filter;
            $types .= 's';
        }

        if ($status_filter !== 'all') {
            $query .= " AND a.status = ?";
            $params[] = $status_filter;
            $types .= 's';
        }

        $query .= " GROUP BY p.id ORDER BY p.name";
        break;

    case 'department_summary':
        $report_title = "Department Summary Report";
        $query = "SELECT 
                    p.department,
                    COUNT(DISTINCT p.id) as total_professors,
                    COUNT(DISTINCT a.checkin_date) as total_days,
                    SUM(CASE WHEN a.am_check_in IS NOT NULL THEN 1 ELSE 0 END) as am_timeins,
                    SUM(CASE WHEN a.pm_check_in IS NOT NULL THEN 1 ELSE 0 END) as pm_timeins,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_day,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    ROUND(AVG(
                        CASE 
                            WHEN a.am_check_in IS NOT NULL AND a.pm_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.am_check_in, a.pm_check_out)
                            WHEN a.am_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.am_check_in, a.am_check_out)
                            WHEN a.pm_check_in IS NOT NULL THEN 
                                TIMESTAMPDIFF(MINUTE, a.pm_check_in, a.pm_check_out)
                            ELSE 0
                        END
                    ), 1) as avg_duration
                  FROM professors p
                  LEFT JOIN attendance a ON p.id = a.professor_id AND DATE(a.checkin_date) BETWEEN ? AND ?
                  WHERE 1=1";

        $params = [$start_date, $end_date];
        $types = 'ss';

        if ($status_filter !== 'all') {
            $query .= " AND a.status = ?";
            $params[] = $status_filter;
            $types .= 's';
        }

        $query .= " GROUP BY p.department ORDER BY p.department";
        break;

    case 'late_attendance':
        $report_title = "Late Attendance Report";
        $query = "SELECT 
                    a.id as attendance_id,
                    p.name,
                    p.department,
                    DATE(a.checkin_date) as date,
                    CASE 
                        WHEN a.am_check_in IS NOT NULL THEN TIME(a.am_check_in)
                        WHEN a.pm_check_in IS NOT NULL THEN TIME(a.pm_check_in)
                    END as time_in,
                    a.status,
                    CASE 
                        WHEN a.am_check_in IS NOT NULL AND a.pm_check_in IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, a.am_check_in, a.pm_check_out)
                        WHEN a.am_check_in IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, a.am_check_in, a.am_check_out)
                        WHEN a.pm_check_in IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, a.pm_check_in, a.pm_check_out)
                        ELSE 0
                    END as duration_minutes,
                    CASE 
                        WHEN a.am_check_in IS NOT NULL THEN 'AM'
                        WHEN a.pm_check_in IS NOT NULL THEN 'PM'
                    END as session_type
                  FROM attendance a
                  JOIN professors p ON a.professor_id = p.id
                  WHERE a.status = 'half-day'
                  AND DATE(a.checkin_date) BETWEEN ? AND ?";

        $params = [$start_date, $end_date];
        $types = 'ss';

        if ($department_filter !== 'all') {
            $query .= " AND p.department = ?";
            $params[] = $department_filter;
            $types .= 's';
        }

        if ($session_filter !== 'all') {
            $query .= " AND (
                (a.am_check_in IS NOT NULL AND ? = 'AM') OR 
                (a.pm_check_in IS NOT NULL AND ? = 'PM')
            )";
            $params[] = $session_filter;
            $params[] = $session_filter;
            $types .= 'ss';
        }

        $query .= " ORDER BY a.checkin_date DESC";
        break;

    default:
        $report_title = "Daily Attendance Summary";
        $report_type = 'daily_summary';
        $query = "SELECT 
                    DATE(a.checkin_date) as date,
                    COUNT(DISTINCT a.professor_id) as total_professors,
                    SUM(CASE WHEN (a.am_check_in IS NOT NULL OR a.pm_check_in IS NOT NULL) THEN 1 ELSE 0 END) as total_attendance,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as half_day,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN (a.am_check_in IS NOT NULL AND a.am_check_out IS NULL) OR 
                                (a.pm_check_in IS NOT NULL AND a.pm_check_out IS NULL) THEN 1 ELSE 0 END) as pending_timeouts
                  FROM attendance a
                  WHERE DATE(a.checkin_date) BETWEEN ? AND ?
                  GROUP BY DATE(a.checkin_date) ORDER BY date DESC";
        $params = [$start_date, $end_date];
        $types = 'ss';
}

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$report_data = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .report-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        .table-report {
            font-size: 0.9rem;
        }
        .table-report th {
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #495057;
        }
        .badge-present {
            background-color: #28a745;
        }
        .badge-half-day {
            background-color: #fd7e14;
        }
        .badge-absent {
            background-color: #dc3545;
        }
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }
        .nav-pills .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .date-range-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        .dt-buttons .btn {
            border-radius: 4px !important;
            margin-right: 5px;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .dt-buttons .btn i {
            margin-right: 3px;
        }
        .dt-buttons .btn.buttons-copy,
        .dt-buttons .btn.buttons-csv {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        .dt-buttons .btn.buttons-excel {
            background-color: #198754;
            color: white;
            border: none;
        }
        .dt-buttons .btn.buttons-pdf {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .dt-buttons .btn.buttons-print {
            background-color: #0dcaf0;
            color: white;
            border: none;
        }
        .dt-buttons .btn:hover {
            opacity: 0.9;
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
                    <h2 class="mb-1 fw-bold">Reports Dashboard</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-chart-bar me-1"></i> Reports</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" id="reportForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type">
                                    <option value="daily_summary" <?= $report_type === 'daily_summary' ? 'selected' : '' ?>>Daily Summary</option>
                                    <option value="professor_activity" <?= $report_type === 'professor_activity' ? 'selected' : '' ?>>Professor Activity</option>
                                    <option value="department_summary" <?= $report_type === 'department_summary' ? 'selected' : '' ?>>Department Summary</option>
                                    <option value="late_attendance" <?= $report_type === 'late_attendance' ? 'selected' : '' ?>>Late Attendance</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="present" <?= $status_filter === 'present' ? 'selected' : '' ?>>Present</option>
                                    <option value="half-day" <?= $status_filter === 'half-day' ? 'selected' : '' ?>>Half-day</option>
                                    <option value="absent" <?= $status_filter === 'absent' ? 'selected' : '' ?>>Absent</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="session" class="form-label">Session</label>
                                <select class="form-select" id="session" name="session">
                                    <option value="all" <?= $session_filter === 'all' ? 'selected' : '' ?>>All Sessions</option>
                                    <option value="AM" <?= $session_filter === 'AM' ? 'selected' : '' ?>>AM</option>
                                    <option value="PM" <?= $session_filter === 'PM' ? 'selected' : '' ?>>PM</option>
                                </select>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Generate Report
                                </button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Date Range</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="date_range" id="today" value="today" autocomplete="off" <?= $date_range === 'today' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="today">Today</label>

                                    <input type="radio" class="btn-check" name="date_range" id="this_week" value="this_week" autocomplete="off" <?= $date_range === 'this_week' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="this_week">This Week</label>

                                    <input type="radio" class="btn-check" name="date_range" id="this_month" value="this_month" autocomplete="off" <?= $date_range === 'this_month' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="this_month">This Month</label>

                                    <input type="radio" class="btn-check" name="date_range" id="this_year" value="this_year" autocomplete="off" <?= $date_range === 'this_year' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="this_year">This Year</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-primary bg-opacity-10 border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="stat-label">Total Records</h6>
                                    <h3 class="stat-value"><?= $report_data->num_rows ?></h3>
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
                                    <h6 class="stat-label">Date Range</h6>
                                    <h3 class="stat-value"><?= date('M j', strtotime($start_date)) ?> - <?= date('M j', strtotime($end_date)) ?></h3>
                                </div>
                                <div class="stat-icon text-success">
                                    <i class="fas fa-calendar-alt"></i>
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
                                    <h6 class="stat-label">Report Type</h6>
                                    <h3 class="stat-value text-capitalize"><?= str_replace('_', ' ', $report_type) ?></h3>
                                </div>
                                <div class="stat-icon text-info">
                                    <i class="fas fa-chart-pie"></i>
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
                                    <h6 class="stat-label">Generated On</h6>
                                    <h3 class="stat-value"><?= date('M j, Y') ?></h3>
                                </div>
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Content -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= $report_title ?></h5>
                    <div class="text-muted">
                        <?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="reportTable" class="table table-report table-hover" style="width:100%">
                            <thead>
                                <?php if ($report_type === 'daily_summary'): ?>
                                    <tr>
                                        <th>Date</th>
                                        <th>Professors</th>
                                        <th>Present</th>
                                        <th>Half-day</th>
                                        <th>Absent</th>
                                        <th>Pending Time Outs</th>
                                        <th>Actions</th>
                                    </tr>
                                <?php elseif ($report_type === 'professor_activity'): ?>
                                    <tr>
                                        <th>Professor</th>
                                        <th>Department</th>
                                        <th>AM Time Ins</th>
                                        <th>PM Time Ins</th>
                                        <th>Present Days</th>
                                        <th>Half-days</th>
                                        <th>Avg. Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                <?php elseif ($report_type === 'department_summary'): ?>
                                    <tr>
                                        <th>Department</th>
                                        <th>Professors</th>
                                        <th>AM Time Ins</th>
                                        <th>PM Time Ins</th>
                                        <th>Present</th>
                                        <th>Half-day</th>
                                        <th>Absent</th>
                                        <th>Avg. Duration</th>
                                    </tr>
                                <?php elseif ($report_type === 'late_attendance'): ?>
                                    <tr>
                                        <th>Professor</th>
                                        <th>Department</th>
                                        <th>Date</th>
                                        <th>Session</th>
                                        <th>Time In</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php if ($report_data->num_rows > 0): ?>
                                    <?php $report_data->data_seek(0); ?>
                                    <?php while ($row = $report_data->fetch_assoc()): ?>
                                        <?php if ($report_type === 'daily_summary'): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                                <td><?= $row['total_professors'] ?></td>
                                                <td><span class="badge bg-success"><?= $row['present'] ?></span></td>
                                                <td><span class="badge bg-warning text-dark"><?= $row['half_day'] ?></span></td>
                                                <td><span class="badge bg-danger"><?= $row['absent'] ?></span></td>
                                                <td><span class="badge bg-secondary"><?= $row['pending_timeouts'] ?></span></td>
                                                <td>
                                                    <a href="manage-attendance.php?date=<?= $row['date'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php elseif ($report_type === 'professor_activity'): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= $row['am_timeins'] ?></td>
                                                <td><?= $row['pm_timeins'] ?></td>
                                                <td><?= $row['present'] ?></td>
                                                <td><?= $row['half_day'] ?></td>
                                                <td>
                                                    <?php if ($row['avg_duration']): ?>
                                                        <?= floor($row['avg_duration'] / 60) ?>h <?= $row['avg_duration'] % 60 ?>m
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="professor-attendance.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php elseif ($report_type === 'department_summary'): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= $row['total_professors'] ?></td>
                                                <td><?= $row['am_timeins'] ?></td>
                                                <td><?= $row['pm_timeins'] ?></td>
                                                <td><?= $row['present'] ?></td>
                                                <td><?= $row['half_day'] ?></td>
                                                <td><?= $row['absent'] ?></td>
                                                <td>
                                                    <?php if ($row['avg_duration']): ?>
                                                        <?= floor($row['avg_duration'] / 60) ?>h <?= $row['avg_duration'] % 60 ?>m
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php elseif ($report_type === 'late_attendance'): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                                <td><?= htmlspecialchars($row['session_type']) ?></td>
                                                <td><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                                                <td><span class="badge bg-warning text-dark"><?= $row['status'] ?></span></td>
                                                <td>
                                                    <?php if ($row['duration_minutes']): ?>
                                                        <?= floor($row['duration_minutes'] / 60) ?>h <?= $row['duration_minutes'] % 60 ?>m
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="edit-attendance.php?id=<?= $row['attendance_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= 
                                            $report_type === 'daily_summary' ? 7 : 
                                            ($report_type === 'professor_activity' ? 8 : 
                                            ($report_type === 'department_summary' ? 8 : 8))
                                        ?>" class="text-center py-4">
                                            No records found for the selected filters
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
    <script>
        $(document).ready(function() {
            // Initialize DataTable with matching export buttons
            const table = $('#reportTable').DataTable({
                responsive: true,
                dom: '<"top"Bf>rt<"bottom"lip><"clear">',
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm buttons-excel',
                        title: '<?= $report_title ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm buttons-pdf',
                        title: '<?= $report_title ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv me-1"></i> CSV',
                        className: 'btn btn-sm buttons-csv',
                        title: '<?= $report_title ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm buttons-print',
                        title: '<?= $report_title ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100]
            });

            // Print button handler
            $('#printReport').click(function() {
                table.button('.buttons-print').click();
            });
        });
    </script>
</body>

</html>