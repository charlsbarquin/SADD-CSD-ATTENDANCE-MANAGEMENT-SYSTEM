<?php
require_once __DIR__ . '/../includes/session.php';
include '../config/database.php';

// Get the logged-in professor's ID
$professorId = $_SESSION['professor_id'] ?? null;
if (!$professorId) {
    header('Location: ../pages/login.php');
    exit;
}

// Get professor's name
$professorName = '';
$stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professorId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $professor = $result->fetch_assoc();
    $professorName = $professor['name'];
}
$stmt->close();

// Get summary statistics
$summaryStmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT checkin_date) AS total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
        SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) AS half_days,
        SEC_TO_TIME(SUM(
            CASE 
                WHEN am_check_in IS NOT NULL AND pm_check_out IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, am_check_in, pm_check_out)
                WHEN am_check_in IS NOT NULL AND am_check_out IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, am_check_in, am_check_out)
                WHEN pm_check_in IS NOT NULL AND pm_check_out IS NOT NULL THEN 
                    TIMESTAMPDIFF(SECOND, pm_check_in, pm_check_out)
                ELSE 0
            END
        )) AS total_work_time,
        SEC_TO_TIME(
            SUM(
                CASE 
                    WHEN am_check_in IS NOT NULL AND pm_check_out IS NOT NULL THEN 
                        TIMESTAMPDIFF(SECOND, am_check_in, pm_check_out)
                    WHEN am_check_in IS NOT NULL AND am_check_out IS NOT NULL THEN 
                        TIMESTAMPDIFF(SECOND, am_check_in, am_check_out)
                    WHEN pm_check_in IS NOT NULL AND pm_check_out IS NOT NULL THEN 
                        TIMESTAMPDIFF(SECOND, pm_check_in, pm_check_out)
                    ELSE 0
                END
            ) / 
            NULLIF(SUM(
                CASE 
                    WHEN (am_check_in IS NOT NULL AND pm_check_out IS NOT NULL) OR 
                         (am_check_in IS NOT NULL AND am_check_out IS NOT NULL) OR 
                         (pm_check_in IS NOT NULL AND pm_check_out IS NOT NULL) 
                    THEN 1 
                    ELSE 0 
                END
            ), 0)
        ) AS avg_duration
    FROM attendance 
    WHERE professor_id = ?
");
$summaryStmt->bind_param("i", $professorId);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summaryData = $summaryResult->fetch_assoc();
$summaryStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Report</title>

    <!-- Bootstrap & Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/attendance-report.css">

    <style>
        /* Original table styling with slight enhancements */
        #attendance-table th,
        #attendance-table td {
            padding: 10px 12px;
            vertical-align: middle;
        }

        #attendance-table th {
            font-weight: 600;
            background-color: #eef2ff;
            color: #343a40;
        }

        #attendance-table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .badge {
            padding: 5px 8px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .table-responsive {
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        /* Summary cards - subtle styling */
        .stat-card {
            padding: 15px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border-left: 4px solid #2c3e50;
        }

        .stat-card h5 {
            font-size: 1.4rem;
            margin-bottom: 5px;
        }

        /* Original header styling */
        .page-header {
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .page-header h1 {
            font-weight: 600;
            color: #2c3e50;
        }

        .professor-info {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {

            #attendance-table th,
            #attendance-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
        }

        /* Professional Card Styling */
        .stat-card {
            padding: 20px;
            border-radius: 8px;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .stat-card h5 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .text-white-80 {
            color: rgba(255, 255, 255, 0.9);
        }

        /* Light Card Styling */
        .stat-card {
            padding: 20px;
            border-radius: 8px;
            background-color: #f8f9fa;
            /* Matching your container bg */
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h5 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Light Color Accents */
        .bg-total-days {
            border-left-color: #4e73df;
            /* Blue accent */
        }

        .bg-present-days {
            border-left-color: #1cc88a;
            /* Green accent */
        }

        .bg-absent-days {
            border-left-color: #e74a3b;
            /* Red accent */
        }

        .bg-avg-duration {
            border-left-color: #36b9cc;
            /* Teal accent */
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="main-container">
            <!-- Header Section -->
            <div class="page-header">
                <h1><i class="fas fa-clipboard-list me-2"></i>My Attendance Report</h1>
                <p class="page-subtitle">View your attendance records</p>
                <div class="page-subtitle">
                    <h5><?php echo htmlspecialchars($professorName); ?></h5>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4 g-3">
                <div class="col-md-3">
                    <div class="stat-card bg-total-days">
                        <h5 class="text-dark"><?= $summaryData['total_days'] ?? 0 ?></h5>
                        <p class="text-muted mb-0">Total Days</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-present-days">
                        <h5 class="text-dark"><?= $summaryData['present_days'] ?? 0 ?></h5>
                        <p class="text-muted mb-0">Present Days</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-absent-days">
                        <h5 class="text-dark"><?= $summaryData['absent_days'] ?? 0 ?></h5>
                        <p class="text-muted mb-0">Absent Days</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-avg-duration">
                        <h5 class="text-dark"><?=
                                                isset($summaryData['avg_duration']) && $summaryData['avg_duration'] !== '00:00:00' ?
                                                    substr($summaryData['avg_duration'], 0, 8) : '0:00:00'
                                                ?></h5>
                        <p class="text-muted mb-0">Avg. Duration</p>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="section mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control form-control-sm" id="start-date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control form-control-sm" id="end-date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select form-select-sm" id="status-filter">
                            <option value="">All Status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="half-day">Half Day</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Session</label>
                        <select class="form-select form-select-sm" id="session-filter">
                            <option value="">All Sessions</option>
                            <option value="AM">AM Session</option>
                            <option value="PM">PM Session</option>
                            <option value="Full">Full Day</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" id="apply-filters">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-outline-secondary w-100" id="reset-filters">
                            <i class="fas fa-sync-alt me-2"></i>Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="table-responsive" style="max-height: 600px;">
                <table class="table" id="attendance-table" style="width:100%">
                    <thead style="background-color: #eef2ff;">
                        <tr>
                            <th>Date</th>
                            <th>Session</th>
                            <th>AM Time In</th>
                            <th>AM Time Out</th>
                            <th>PM Time In</th>
                            <th>PM Time Out</th>
                            <th>AM Duration</th>
                            <th>PM Duration</th>
                            <th>Total Duration</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT a.*, 
                    CASE 
                        WHEN a.am_check_in IS NOT NULL AND a.pm_check_in IS NOT NULL THEN 'Full Day'
                        WHEN a.am_check_in IS NOT NULL THEN 'AM Session'
                        WHEN a.pm_check_in IS NOT NULL THEN 'PM Session'
                        ELSE 'No Session'
                    END as session_type
                    FROM attendance a
                    WHERE a.professor_id = ? 
                    ORDER BY a.checkin_date DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $professorId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            $date = date("M d, Y", strtotime($row['checkin_date']));

                            // AM Session Data
                            $amTimeIn = $row['am_check_in'] ? date("h:i A", strtotime($row['am_check_in'])) : '-';
                            $amTimeOut = $row['am_check_out'] ? date("h:i A", strtotime($row['am_check_out'])) : '-';
                            $amDuration = ($row['am_check_in'] && $row['am_check_out']) ?
                                calculateDuration($row['am_check_in'], $row['am_check_out']) : '-';

                            // PM Session Data
                            $pmTimeIn = $row['pm_check_in'] ? date("h:i A", strtotime($row['pm_check_in'])) : '-';
                            $pmTimeOut = $row['pm_check_out'] ? date("h:i A", strtotime($row['pm_check_out'])) : '-';
                            $pmDuration = ($row['pm_check_in'] && $row['pm_check_out']) ?
                                calculateDuration($row['pm_check_in'], $row['pm_check_out']) : '-';

                            // Total duration calculation - improved logic
                            $totalDuration = '-';
                            $totalSeconds = 0;

                            // Calculate AM duration if available
                            if ($row['am_check_in'] && $row['am_check_out']) {
                                $totalSeconds += strtotime($row['am_check_out']) - strtotime($row['am_check_in']);
                            }

                            // Calculate PM duration if available
                            if ($row['pm_check_in'] && $row['pm_check_out']) {
                                $totalSeconds += strtotime($row['pm_check_out']) - strtotime($row['pm_check_in']);
                            }

                            // Convert total seconds to hours and minutes
                            if ($totalSeconds > 0) {
                                $hours = floor($totalSeconds / 3600);
                                $minutes = floor(($totalSeconds % 3600) / 60);
                                $totalDuration = sprintf('%d hrs %02d mins', $hours, $minutes);
                            } elseif ($row['work_duration'] && $row['work_duration'] !== '0 hrs') {
                                $totalDuration = $row['work_duration'];
                            }

                            $statusClass = '';
                            switch ($row['status']) {
                                case 'present':
                                    $statusClass = 'bg-success';
                                    break;
                                case 'half-day':
                                    $statusClass = 'bg-warning text-dark';
                                    break;
                                case 'absent':
                                    $statusClass = 'bg-danger';
                                    break;
                                default:
                                    $statusClass = 'bg-secondary';
                            }

                            // Location data
                            $latitude = $row['pm_latitude'] ?? $row['am_latitude'] ?? $row['latitude'] ?? null;
                            $longitude = $row['pm_longitude'] ?? $row['am_longitude'] ?? $row['longitude'] ?? null;
                            $locationLink = ($latitude && $longitude) ?
                                "<a href='https://www.google.com/maps?q={$latitude},{$longitude}' target='_blank' class='btn btn-sm btn-outline-primary'>
                        <i class='fas fa-map-marker-alt'></i> View
                    </a>" : 'N/A';

                            echo "<tr>
                    <td>{$date}</td>
                    <td><span class='badge " . ($row['am_check_in'] && $row['pm_check_in'] ? 'bg-primary' : ($row['am_check_in'] ? 'bg-info' : 'bg-warning text-dark')) . "'>
                        " . ($row['am_check_in'] && $row['pm_check_in'] ? 'Full Day' : ($row['am_check_in'] ? 'AM Only' : 'PM Only')) . "
                    </span></td>
                    <td>{$amTimeIn}</td>
                    <td>{$amTimeOut}</td>
                    <td>{$pmTimeIn}</td>
                    <td>{$pmTimeOut}</td>
                    <td>{$amDuration}</td>
                    <td>{$pmDuration}</td>
                    <td>{$totalDuration}</td>
                    <td><span class='badge {$statusClass}'>" . ucfirst($row['status']) . "</span></td>
                    <td>{$locationLink}</td>
                </tr>";
                        }

                        function calculateDuration($start, $end)
                        {
                            $startTime = new DateTime($start);
                            $endTime = new DateTime($end);
                            $interval = $startTime->diff($endTime);
                            return sprintf('%d hrs %02d mins', $interval->h, $interval->i);
                        }

                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with original configuration
            var table = $('#attendance-table').DataTable({
                lengthMenu: [
                    [5, 10, 25, 50, -1],
                    [5, 10, 25, 50, "All"]
                ],
                pageLength: 10,
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row'<'col-sm-12'i>>" +
                    "<'row'<'col-sm-6'B><'col-sm-6'p>>",
                buttons: [{
                        extend: 'copy',
                        className: 'btn btn-sm btn-secondary',
                        text: '<i class="fas fa-copy"></i> Copy'
                    },
                    {
                        extend: 'csv',
                        className: 'btn btn-sm btn-secondary',
                        text: '<i class="fas fa-file-csv"></i> CSV'
                    },
                    {
                        extend: 'excel',
                        className: 'btn btn-sm btn-secondary',
                        text: '<i class="fas fa-file-excel"></i> Excel'
                    },
                    {
                        extend: 'pdf',
                        className: 'btn btn-sm btn-secondary',
                        text: '<i class="fas fa-file-pdf"></i> PDF'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-sm btn-secondary',
                        text: '<i class="fas fa-print"></i> Print'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                initComplete: function() {
                    let searchContainer = $('.dataTables_filter');
                    let searchInput = searchContainer.find('input');

                    // Wrap input with a div for styling
                    searchContainer.html(`
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            ${searchInput.prop('outerHTML')}
                        </div>
                    `);

                    // Select the new input and adjust styling
                    let newSearchInput = searchContainer.find('input');
                    newSearchInput.addClass('form-control')
                        .css({
                            "width": "280px",
                            "height": "40px",
                            "font-size": "16px",
                            "border-left": "0"
                        });

                    // Add search event listener
                    newSearchInput.on('keyup', function() {
                        table.search(this.value).draw();
                    });
                }
            });

            // Set default date range (current month)
            let today = new Date();
            let firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            $('#start-date').val(firstDay.toISOString().split('T')[0]);
            $('#end-date').val(today.toISOString().split('T')[0]);

            // Apply filters with enhanced functionality
            $('#apply-filters').click(function() {
                var startDate = $('#start-date').val();
                var endDate = $('#end-date').val();
                var status = $('#status-filter').val();
                var session = $('#session-filter').val();

                // Clear previous filters
                table.columns().search('').draw();
                $.fn.dataTable.ext.search.pop();

                // Filter by date range
                if (startDate || endDate) {
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        var rowDate = new Date(data[0]);
                        var min = startDate ? new Date(startDate) : null;
                        var max = endDate ? new Date(endDate) : null;

                        if ((min === null || rowDate >= min) &&
                            (max === null || rowDate <= max)) {
                            return true;
                        }
                        return false;
                    });
                }

                // Filter by status (column 9)
                if (status) {
                    table.column(9).search(status, true, false).draw();
                }

                // Filter by session type (column 1)
                if (session) {
                    if (session === 'Full') {
                        table.column(1).search('Full Day', true, false).draw();
                    } else {
                        table.column(1).search(session + ' Session', true, false).draw();
                    }
                }

                table.draw();

                // Show notification
                let filterCount = table.rows({
                    filter: 'applied'
                }).count();
                let notification = `<div class="alert alert-info alert-dismissible fade show alert-notification" role="alert">
                    <i class="fas fa-info-circle me-2"></i> Showing ${filterCount} filtered records
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;

                $('.alert-notification').remove();
                $('.main-container').prepend(notification);

                setTimeout(() => {
                    $('.alert-notification').alert('close');
                }, 3000);
            });

            // Reset filters
            $('#reset-filters').click(function() {
                $('#start-date').val(firstDay.toISOString().split('T')[0]);
                $('#end-date').val(today.toISOString().split('T')[0]);
                $('#status-filter').val('');
                $('#session-filter').val('');
                $('#apply-filters').click();
            });
        });
    </script>
</body>

</html>