<?php
session_start();
require_once '../config/database.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit;
}

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: admin-login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Get admin details
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, email, last_attempt FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get cutoff times from settings
$settings_query = $conn->query("SELECT late_cutoff, pm_late_cutoff FROM settings WHERE id = 1");
$settings = $settings_query->fetch_assoc();
$am_late_cutoff = $settings['late_cutoff'] ?? '08:00:00'; // Default to 8 AM if not set
$pm_late_cutoff = $settings['pm_late_cutoff'] ?? '13:00:00'; // Default to 1 PM if not set

// Dashboard Statistics - Enhanced for AM/PM system
$stats = [
    'total_professors' => $conn->query("SELECT COUNT(*) FROM professors WHERE status = 'active'")->fetch_row()[0],

    // On Time: AM check-in before cutoff time
    'on_time' => $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
              WHERE checkin_date = CURDATE() 
              AND TIME(am_check_in) <= '$am_late_cutoff'
              AND (TIME(pm_check_in) <= '$pm_late_cutoff' OR pm_check_in IS NULL)")->fetch_row()[0],

    // Late Arrivals: AM check-in after cutoff time
    'late_arrivals' => $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                    WHERE checkin_date = CURDATE() 
                    AND TIME(am_check_in) > '$am_late_cutoff' 
                    AND am_check_in IS NOT NULL")->fetch_row()[0],

    // Absent: Professors with no attendance record today
    'absent' => $conn->query("SELECT COUNT(*) FROM professors 
             WHERE status = 'active' 
             AND id NOT IN (
                 SELECT professor_id FROM attendance 
                 WHERE checkin_date = CURDATE()
             )")->fetch_row()[0],

    // PM Late Arrivals: PM check-in after cutoff time
    'late_pm' => $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                WHERE checkin_date = CURDATE() 
                AND TIME(pm_check_in) > '$pm_late_cutoff' 
                AND pm_check_in IS NOT NULL")->fetch_row()[0],

    // Active Now: Professors currently checked in (either AM not out or PM not out)
    'active_now' => $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                 WHERE checkin_date = CURDATE() 
                 AND (pm_check_out IS NULL OR (am_check_out IS NULL AND am_check_in IS NOT NULL))")->fetch_row()[0],

    'pending_approvals' => $conn->query("SELECT COUNT(*) FROM professors WHERE status = 'pending'")->fetch_row()[0],

    'avg_work_hours' => $conn->query("SELECT SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND, am_check_in, pm_check_out))) 
                        FROM attendance 
                        WHERE pm_check_out IS NOT NULL 
                        AND checkin_date = CURDATE()")->fetch_row()[0]
];

// Get detailed late arrivals (after AM cutoff)
$late_arrivals_details = $conn->query("
    SELECT p.name, TIME(a.am_check_in) as checkin_time, 
           TIMESTAMPDIFF(MINUTE, '$am_late_cutoff', TIME(a.am_check_in)) as minutes_late,
           a.am_latitude, a.am_longitude
    FROM attendance a
    JOIN professors p ON a.professor_id = p.id
    WHERE a.checkin_date = CURDATE() 
    AND TIME(a.am_check_in) > '$am_late_cutoff'
    ORDER BY minutes_late DESC
");

// Get detailed PM late arrivals (after PM cutoff)
$late_pm_details = $conn->query("
    SELECT p.name, TIME(a.pm_check_in) as checkin_time, 
           TIMESTAMPDIFF(MINUTE, '$pm_late_cutoff', TIME(a.pm_check_in)) as minutes_late,
           a.pm_latitude, a.pm_longitude
    FROM attendance a
    JOIN professors p ON a.professor_id = p.id
    WHERE a.checkin_date = CURDATE() 
    AND TIME(a.pm_check_in) > '$pm_late_cutoff'
    ORDER BY minutes_late DESC
");

// Recent Attendance (Last 10 records) - Updated for AM/PM system
$recent_attendance = $conn->query("
    SELECT 
        a.*, 
        p.name, 
        p.designation,
        p.email,
        CONCAT(a.am_latitude, ',', a.am_longitude) as am_location,
        CONCAT(a.pm_latitude, ',', a.pm_longitude) as pm_location,
        CASE 
            WHEN a.am_check_in IS NULL AND a.pm_check_in IS NULL THEN 'Absent'
            WHEN a.status = 'present' THEN 'Present'
            WHEN a.status = 'absent' THEN 'Absent'
            WHEN a.status = 'half-day' THEN 'Half Day'
            WHEN TIME(a.am_check_in) > '$am_late_cutoff' THEN 'Late AM'
            WHEN TIME(a.pm_check_in) > '$pm_late_cutoff' THEN 'Late PM'
            WHEN a.pm_check_out IS NULL THEN 'Active'
            ELSE 'Present'
        END as status
    FROM attendance a
    JOIN professors p ON a.professor_id = p.id
    ORDER BY a.recorded_at DESC
    LIMIT 10
");

// Recent Notifications
$notifications = $conn->query("
    SELECT * FROM logs 
    ORDER BY timestamp DESC 
    LIMIT 5
");

// Get attendance data for the past 7 days for the chart
$attendance_data = $conn->query("
    SELECT 
        DATE(checkin_date) as day,
        COUNT(*) as total_checkins,
        SUM(CASE WHEN TIME(am_check_in) > '$am_late_cutoff' THEN 1 ELSE 0 END) as late_arrivals,
        SUM(CASE WHEN TIME(pm_check_in) > '$pm_late_cutoff' THEN 1 ELSE 0 END) as late_pm,
        SUM(CASE WHEN TIME(am_check_in) <= '$am_late_cutoff' AND (TIME(pm_check_in) <= '$pm_late_cutoff' OR pm_check_in IS NULL) THEN 1 ELSE 0 END) as on_time,
        (SELECT COUNT(*) FROM professors WHERE status = 'active') - COUNT(DISTINCT professor_id) as absent_count
    FROM attendance
    WHERE checkin_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
    GROUP BY DATE(checkin_date)
    ORDER BY day ASC
");

// Initialize arrays with default values for all 7 days
$days = [];
$on_time_data = [];
$late_arrivals_data = [];
$late_pm_data = [];
$absent_data = [];

// Get the last 7 days including today
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    $days[] = $day_name;
    $on_time_data[$day_name] = 0;
    $late_arrivals_data[$day_name] = 0;
    $late_pm_data[$day_name] = 0;
    $absent_data[$day_name] = 0;
}

// Fill in actual data from the query
if ($attendance_data) {
    while ($row = $attendance_data->fetch_assoc()) {
        $day_name = date('D', strtotime($row['day']));
        $on_time_data[$day_name] = $row['on_time'];
        $late_arrivals_data[$day_name] = $row['late_arrivals'];
        $late_pm_data[$day_name] = $row['late_pm'];
        $absent_data[$day_name] = $row['absent_count'];
    }
}

// Convert to arrays in the correct order for the chart
$on_time_chart_data = array_values($on_time_data);
$late_arrivals_chart_data = array_values($late_arrivals_data);
$late_pm_chart_data = array_values($late_pm_data);
$absent_chart_data = array_values($absent_data);

// Time distribution for histogram - Updated to include both AM and PM check-ins
$time_distribution = $conn->query("
    SELECT 
        SUM(HOUR(am_check_in) BETWEEN 6 AND 8) as '6-8AM',
        SUM(HOUR(am_check_in) BETWEEN 8 AND 10) as '8-10AM',
        SUM(HOUR(am_check_in) BETWEEN 10 AND 12) as '10-12PM',
        SUM(HOUR(pm_check_in) BETWEEN 12 AND 14) as '12-2PM',
        SUM(HOUR(pm_check_in) BETWEEN 14 AND 16) as '2-4PM',
        SUM(HOUR(pm_check_in) BETWEEN 16 AND 18) as '4-6PM',
        SUM(HOUR(pm_check_in) >= 18 OR HOUR(am_check_in) >= 18) as 'After 6PM'
    FROM attendance
    WHERE checkin_date = CURDATE()
")->fetch_row();

// PDF Report Generation Handler
if (isset($_POST['generate_pdf'])) {
    require_once '../tcpdf/tcpdf.php';

    class MYPDF extends TCPDF
    {
        public function Header()
        {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 15, 'Bicol University Polangui - Attendance Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
            $this->Ln(10);
        }

        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('BUP Attendance System');
    $pdf->SetTitle('Attendance Report - ' . date('Y-m-d'));
    $pdf->SetHeaderData('', 0, '', '');
    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();

    // Report content
    $html = '<h2>Attendance Report (' . $_POST['start_date'] . ' to ' . $_POST['end_date'] . ')</h2>';
    $html .= '<table border="1" cellpadding="4">
        <tr style="background-color:#f2f2f2;">
            <th width="20%">Professor</th>
            <th width="10%">Date</th>
            <th width="12%">AM Time In</th>
            <th width="12%">AM Time Out</th>
            <th width="12%">PM Time In</th>
            <th width="12%">PM Time Out</th>
            <th width="10%">Status</th>
            <th width="12%">Hours</th>
        </tr>';

    $report_data = $conn->query("
        SELECT p.name, a.checkin_date, 
               a.am_check_in, a.am_check_out, 
               a.pm_check_in, a.pm_check_out,
               a.work_duration,
               CASE 
                   WHEN a.am_check_in IS NULL AND a.pm_check_in IS NULL THEN 'Absent'
                   WHEN TIME(a.am_check_in) > '08:00:00' THEN 'Late AM'
                   WHEN TIME(a.pm_check_in) > '13:00:00' THEN 'Late PM'
                   WHEN a.pm_check_out IS NULL THEN 'Active'
                   ELSE 'Present'
               END as status
        FROM attendance a
        JOIN professors p ON a.professor_id = p.id
        WHERE a.checkin_date BETWEEN '{$_POST['start_date']}' AND '{$_POST['end_date']}'
        ORDER BY a.checkin_date DESC
    ");

    while ($row = $report_data->fetch_assoc()) {
        $html .= '<tr>
            <td>' . $row['name'] . '</td>
            <td>' . $row['checkin_date'] . '</td>
            <td>' . ($row['am_check_in'] ? date('h:i A', strtotime($row['am_check_in'])) : '--') . '</td>
            <td>' . ($row['am_check_out'] ? date('h:i A', strtotime($row['am_check_out'])) : '--') . '</td>
            <td>' . ($row['pm_check_in'] ? date('h:i A', strtotime($row['pm_check_in'])) : '--') . '</td>
            <td>' . ($row['pm_check_out'] ? date('h:i A', strtotime($row['pm_check_out'])) : '--') . '</td>
            <td>' . $row['status'] . '</td>
            <td>' . $row['work_duration'] . '</td>
        </tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('attendance_report_' . date('Ymd') . '.pdf', 'D');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bicol University Polangui Admin Dashboard">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard | Bicol University Polangui</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .late-details-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .late-details-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .map-link {
            color: #0d6efd;
            text-decoration: none;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        .badge-late-am {
            background-color: #ffc107;
            color: #000;
        }

        .badge-late-pm {
            background-color: #fd7e14;
            color: #000;
        }

        /* Custom orange color classes */
        .bg-orange {
            background-color: #fd7e14 !important;
        }

        .text-orange {
            color: #fd7e14 !important;
        }

        .border-orange {
            border-color: #fd7e14 !important;
        }

        .bg-orange-10 {
            background-color: rgba(253, 126, 20, 0.1) !important;
        }
    </style>
</head>

<body>

    <?php include 'partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title">
                <h1>Dashboard</h1>
                <small class="text-muted"><?= date('F j, Y') ?></small>
            </div>

            <div class="user-menu">
                <div class="user-info">
                    <p class="user-role">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="row">
            <!-- Statistics Cards -->
            <div class="row mb-4 g-4">
                <!-- On Time -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-start border-success border-4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-1">On Time</h6>
                                    <h2 class="mb-0"><?php echo $stats['on_time']; ?></h2>
                                    <small class="text-muted">AM time in before 8 AM</small>
                                </div>
                                <div class="ms-3 bg-success bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-check-circle text-success fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Late Arrivals -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-start border-warning border-4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-1">AM Late Arrivals</h6>
                                    <h2 class="mb-0"><?php echo $stats['late_arrivals']; ?></h2>
                                    <small class="text-muted">AM time in after 8 AM</small>
                                </div>
                                <div class="ms-3 bg-warning bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-clock text-warning fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PM Late Arrivals Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-start border-orange border-4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-1">PM Late Arrivals</h6>
                                    <h2 class="mb-0"><?php echo $stats['late_pm']; ?></h2>
                                    <small class="text-muted">PM time in after 1 PM</small>
                                </div>
                                <div class="ms-3 bg-orange-10 p-3 rounded">
                                    <i class="fas fa-clock text-orange fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Absent -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-start border-danger border-4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-muted mb-1">Absent</h6>
                                    <h2 class="mb-0"><?php echo $stats['absent']; ?></h2>
                                </div>
                                <div class="ms-3 bg-danger bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-user-times text-danger fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <!-- Activity Overview -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-2"></i> Activity Overview
                        </div>
                        <div class="card-body">
                            <canvas id="activityChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-bell me-2"></i> Recent Notifications
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while ($log = $notifications->fetch_assoc()): ?>
                                    <a href="#" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($log['action']); ?></span>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($log['timestamp'])); ?></small>
                                        </div>
                                        <?php if ($log['user']): ?>
                                            <small class="text-muted">By <?php echo htmlspecialchars($log['user']); ?></small>
                                        <?php endif; ?>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Late Arrivals Details -->
            <div class="row mt-4">
                <!-- AM Late Arrivals -->
                <div class="col-md-6">
                    <div class="card border-start border-warning border-4 h-100">
                        <div class="card-header bg-warning text-white">
                            <i class="fas fa-clock me-2"></i> AM Late Arrivals (After 8 AM)
                        </div>
                        <div class="card-body">
                            <?php if ($late_arrivals_details->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Professor</th>
                                                <th>Time In</th>
                                                <th>Location</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($late = $late_arrivals_details->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($late['name']) ?></td>
                                                    <td><?= $late['checkin_time'] ? date('h:i A', strtotime($late['checkin_time'])) : '--' ?></td>
                                                    <td>
                                                        <?php if ($late['am_latitude'] && $late['am_longitude']): ?>
                                                            <a href="https://www.google.com/maps?q=<?= $late['am_latitude'] ?>,<?= $late['am_longitude'] ?>"
                                                                target="_blank" class="map-link">
                                                                <i class="fas fa-map-marker-alt"></i> View
                                                            </a>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No late arrivals for AM session today.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- PM Late Arrivals Section -->
                <div class="col-md-6">
                    <div class="card border-start border-orange border-4 h-100">
                        <div class="card-header bg-orange text-white">
                            <i class="fas fa-clock me-2"></i> PM Late Arrivals (After 1 PM)
                        </div>
                        <div class="card-body">
                            <?php if ($late_pm_details->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Professor</th>
                                                <th>Time In</th>
                                                <th>Location</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($late = $late_pm_details->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($late['name']) ?></td>
                                                    <td><?= $late['checkin_time'] ? date('h:i A', strtotime($late['checkin_time'])) : '--' ?></td>
                                                    <td>
                                                        <?php if ($late['pm_latitude'] && $late['pm_longitude']): ?>
                                                            <a href="https://www.google.com/maps?q=<?= $late['pm_latitude'] ?>,<?= $late['pm_longitude'] ?>"
                                                                target="_blank" class="map-link">
                                                                <i class="fas fa-map-marker-alt"></i> View
                                                            </a>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No late arrivals for PM session today.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance Logs -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-table me-2"></i> Recent Attendance Logs
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Designation</th>
                                                <th>AM Time In</th>
                                                <th>AM Time Out</th>
                                                <th>PM Time In</th>
                                                <th>PM Time Out</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($attendance = $recent_attendance->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($attendance['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($attendance['designation']); ?></td>
                                                    <td><?php echo $attendance['am_check_in'] ? date('h:i A', strtotime($attendance['am_check_in'])) : '--'; ?></td>
                                                    <td><?php echo $attendance['am_check_out'] ? date('h:i A', strtotime($attendance['am_check_out'])) : '--'; ?></td>
                                                    <td><?php echo $attendance['pm_check_in'] ? date('h:i A', strtotime($attendance['pm_check_in'])) : '--'; ?></td>
                                                    <td><?php echo $attendance['pm_check_out'] ? date('h:i A', strtotime($attendance['pm_check_out'])) : '--'; ?></td>
                                                    <td>
                                                        <span class="badge <?php
                                                                            echo $attendance['status'] == 'Late AM' ? 'badge-late-am' : ($attendance['status'] == 'Late PM' ? 'badge-late-pm' : ($attendance['status'] == 'Absent' ? 'bg-danger' : 'bg-success'));
                                                                            ?>">
                                                            <?php echo htmlspecialchars($attendance['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary view-attendance-btn"
                                                            data-id="<?php echo $attendance['id']; ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Generation and Quick Actions -->
                <div class="row mt-4">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <!-- Today's Time In Times Card -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock me-2"></i> Today's Time In Times</span>
                                <small class="text-muted"><?= date('M j, Y') ?></small>
                            </div>
                            <div class="card-body">
                                <canvas id="checkinHistogram" height="180"></canvas>
                            </div>
                        </div>

                        <!-- Generate Report Card -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-file-pdf me-2"></i> Generate Attendance Report
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" name="start_date" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">End Date</label>
                                                <input type="date" name="end_date" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="generate_pdf" class="btn btn-danger w-100">
                                        <i class="fas fa-file-pdf me-1"></i> Generate PDF Report
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Section -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-bolt me-2"></i> Quick Actions
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <!-- Add Professor -->
                                    <div class="col-6">
                                        <div class="quick-action-card" onclick="window.location.href='add-professor.php?action=add'">
                                            <div class="action-icon bg-primary bg-opacity-10 text-primary">
                                                <i class="fas fa-user-plus"></i>
                                            </div>
                                            <h6>Add Professor</h6>
                                            <small class="text-muted">Register new faculty</small>
                                        </div>
                                    </div>

                                    <!-- View Attendance -->
                                    <div class="col-6">
                                        <div class="quick-action-card" onclick="window.location.href='manage-attendance.php'">
                                            <div class="action-icon bg-success bg-opacity-10 text-success">
                                                <i class="fas fa-clipboard-list"></i>
                                            </div>
                                            <h6>View Attendance</h6>
                                            <small class="text-muted">Check daily logs</small>
                                        </div>
                                    </div>

                                    <!-- View Reports -->
                                    <div class="col-6">
                                        <div class="quick-action-card" onclick="window.location.href='reports.php'">
                                            <div class="action-icon bg-info bg-opacity-10 text-info">
                                                <i class="fas fa-chart-pie"></i>
                                            </div>
                                            <h6>View Reports</h6>
                                            <small class="text-muted">Generate analytics</small>
                                        </div>
                                    </div>

                                    <!-- Manual Entry -->
                                    <div class="col-6">
                                        <div class="quick-action-card" onclick="window.location.href='add-attendance.php'">
                                            <div class="action-icon bg-warning bg-opacity-10 text-warning">
                                                <i class="fas fa-plus-circle"></i>
                                            </div>
                                            <h6>Manual Entry</h6>
                                            <small class="text-muted">Create new record</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Approvals Card -->
                                <div class="pending-approvals-card mt-4" onclick="window.location.href='manage-users.php?filter=pending'">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="text-muted mb-1">Pending Approvals</h6>
                                            <h4 class="mb-0"><?php echo $stats['pending_approvals']; ?></h4>
                                            <div class="d-flex align-items-center mt-1">
                                                <span class="text-primary small">Review Now</span>
                                                <i class="fas fa-arrow-right ms-2 small text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="pending-icon">
                                            <i class="fas fa-user-clock"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Attendance Card -->
                                <div class="pending-approvals-card mt-4" onclick="window.location.href='add-attendance.php'">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="text-muted mb-1">Add Attendance</h6>
                                            <h4 class="mb-0"><i class="fas fa-plus"></i></h4>
                                            <div class="d-flex align-items-center mt-1">
                                                <span class="text-primary small">Add Now</span>
                                                <i class="fas fa-arrow-right ms-2 small text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="pending-icon">
                                            <i class="fas fa-calendar-plus"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer mt-4">
                    &copy; <?php echo date('Y'); ?> Bicol University Polangui. All rights reserved.
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                // Activity Chart with four datasets
                const ctx = document.getElementById('activityChart').getContext('2d');
                const activityChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($days); ?>,
                        datasets: [{
                                label: 'On Time',
                                data: <?php echo json_encode($on_time_chart_data); ?>,
                                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                borderColor: 'rgba(40, 167, 69, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Late (After 8 AM)',
                                data: <?php echo json_encode($late_arrivals_chart_data); ?>,
                                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                                borderColor: 'rgba(255, 193, 7, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Late (After 1 PM)',
                                data: <?php echo json_encode($late_pm_chart_data); ?>,
                                backgroundColor: 'rgba(253, 126, 20, 0.7)',
                                borderColor: 'rgba(253, 126, 20, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Absent',
                                data: <?php echo json_encode($absent_chart_data); ?>,
                                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                                borderColor: 'rgba(220, 53, 69, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Professors'
                                }
                            }
                        },
                        animation: {
                            duration: 1000
                        }
                    }
                });

                // Time In Histogram Chart
                const timeCtx = document.getElementById('checkinHistogram').getContext('2d');
                const timeHistogram = new Chart(timeCtx, {
                    type: 'bar',
                    data: {
                        labels: ['6-8AM', '8-10AM', '10-12PM', '12-2PM', '2-4PM', '4-6PM', 'After 6PM'],
                        datasets: [{
                            label: 'Number of Time Ins',
                            data: <?= json_encode($time_distribution) ?>,
                            backgroundColor: 'rgba(54, 185, 204, 0.7)',
                            borderColor: 'rgba(44, 123, 229, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    afterLabel: (context) => {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        return total > 0 ? `${Math.round((context.raw / total) * 100)}% of daily time ins` : '';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    stepSize: 1
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Professors'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Time Blocks'
                                }
                            }
                        },
                        animation: {
                            duration: 1000
                        }
                    }
                });

                // View Attendance Button Functionality
                document.querySelectorAll('.view-attendance-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const attendanceId = this.getAttribute('data-id');
                        const row = this.closest('tr');
                        const name = row.cells[0].textContent;
                        const designation = row.cells[1].textContent;
                        const amTimeIn = row.cells[2].textContent;
                        const amTimeOut = row.cells[3].textContent;
                        const pmTimeIn = row.cells[4].textContent;
                        const pmTimeOut = row.cells[5].textContent;
                        const status = row.cells[6].querySelector('.badge').textContent;

                        // Calculate durations
                        function calculateDuration(start, end) {
                            if (start === '--' || end === '--') return '--';

                            try {
                                const startTime = new Date(`2000-01-01 ${start}`);
                                const endTime = new Date(`2000-01-01 ${end}`);
                                const diffMs = endTime - startTime;
                                const diffMins = Math.round(diffMs / 60000);
                                const hours = Math.floor(diffMins / 60);
                                const mins = diffMins % 60;
                                return `${hours}h ${mins}m`;
                            } catch (e) {
                                return '--';
                            }
                        }

                        const amDuration = calculateDuration(amTimeIn, amTimeOut);
                        const pmDuration = calculateDuration(pmTimeIn, pmTimeOut);

                        // Calculate total duration if both sessions exist
                        let totalDuration = '--';
                        if (amTimeIn !== '--' && pmTimeOut !== '--') {
                            try {
                                const start = new Date(`2000-01-01 ${amTimeIn}`);
                                const end = new Date(`2000-01-01 ${pmTimeOut}`);
                                const diffMs = end - start;
                                const diffMins = Math.round(diffMs / 60000);
                                const hours = Math.floor(diffMins / 60);
                                const mins = diffMins % 60;
                                totalDuration = `${hours}h ${mins}m`;
                            } catch (e) {
                                totalDuration = '--';
                            }
                        }

                        // Create modal with all details
                        const modalHtml = `
                    <div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Attendance Details</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <h6>Professor Information</h6>
                                                <hr>
                                                <p><strong>Name:</strong> ${name}</p>
                                                <p><strong>Designation:</strong> ${designation}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <h6>Attendance Summary</h6>
                                                <hr>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge ${status === 'Late AM' ? 'bg-warning' : (status === 'Late PM' ? 'bg-orange' : (status === 'Absent' ? 'bg-danger' : 'bg-success'))}">
                                                        ${status}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h6>AM Session</h6>
                                            <hr>
                                            <p><strong>Time In:</strong> ${amTimeIn}</p>
                                            <p><strong>Time Out:</strong> ${amTimeOut}</p>
                                            <p><strong>Duration:</strong> ${amDuration}</p>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6>PM Session</h6>
                                            <hr>
                                            <p><strong>Time In:</strong> ${pmTimeIn}</p>
                                            <p><strong>Time Out:</strong> ${pmTimeOut}</p>
                                            <p><strong>Duration:</strong> ${pmDuration}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6>Summary</h6>
                                            <hr>
                                            <p><strong>Total Work Duration:</strong> ${totalDuration}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                        document.body.insertAdjacentHTML('beforeend', modalHtml);
                        const modal = new bootstrap.Modal(document.getElementById('attendanceDetailModal'));
                        modal.show();

                        // Remove modal after it's closed
                        document.getElementById('attendanceDetailModal').addEventListener('hidden.bs.modal', function() {
                            this.remove();
                        });
                    });
                });

                // Real-time notifications functionality
                let lastNotificationTimestamp = null;

                function fetchNewNotifications() {
                    fetch(`realtime-notifications.php?last_timestamp=${lastNotificationTimestamp}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.notifications && data.notifications.length > 0) {
                                updateNotificationsUI(data.notifications);
                                lastNotificationTimestamp = data.last_timestamp;

                                // Show desktop notification for new alerts
                                if (Notification.permission === "granted") {
                                    data.notifications.forEach(notif => {
                                        new Notification("New Notification", {
                                            body: notif.action
                                        });
                                    });
                                }
                            }
                        })
                        .catch(error => console.error('Error fetching notifications:', error));
                }

                function updateNotificationsUI(notifications) {
                    const notificationList = document.querySelector('.list-group');

                    // Prepend new notifications
                    notifications.reverse().forEach(notif => {
                        const notificationItem = document.createElement('a');
                        notificationItem.className = 'list-group-item list-group-item-action';
                        notificationItem.href = '#';
                        notificationItem.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <span>${notif.action}</span>
                        <small class="text-muted">${notif.time_formatted}</small>
                    </div>
                    ${notif.user ? `<small class="text-muted">By ${notif.user}</small>` : ''}
                `;
                        notificationList.prepend(notificationItem);
                    });

                    // Play notification sound
                    const audio = new Audio('../assets/sounds/notification.mp3');
                    audio.play().catch(e => console.log('Audio play failed:', e));

                    // Limit to 5 notifications
                    while (notificationList.children.length > 5) {
                        notificationList.removeChild(notificationList.lastChild);
                    }
                }

                // Request notification permission
                if (window.Notification && Notification.permission !== "granted") {
                    Notification.requestPermission();
                }

                // Initial load and then poll every 5 seconds
                fetchNewNotifications();
                setInterval(fetchNewNotifications, 5000);

                // Also check for notifications when the page becomes visible again
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        fetchNewNotifications();
                    }
                });
            </script>