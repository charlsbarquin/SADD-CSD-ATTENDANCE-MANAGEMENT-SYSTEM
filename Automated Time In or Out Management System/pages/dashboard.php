<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

// Initialize variables
$showModal = false;
$modalTitle = '';
$modalMessage = '';
$modalType = '';

// Verify database connection
if (!isset($conn) || !$conn) {
    $showModal = true;
    $modalTitle = 'Database Error';
    $modalMessage = 'Database connection failed';
    $modalType = 'danger';
}

// Fetch dashboard data
$today = date('Y-m-d');
$total_professors = 0;
$am_present = 0;
$pm_present = 0;
$absent_today = 0;
$weeklyData = [];

try {
    // Get total professors
    $result = $conn->query("SELECT COUNT(*) FROM professors WHERE status = 'active'");
    $total_professors = $result ? $result->fetch_row()[0] : 0;

    // Get AM present count
    $result = $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                   WHERE DATE(checkin_date) = '$today' AND am_check_in IS NOT NULL");
    $am_present = $result ? $result->fetch_row()[0] : 0;

    // Get PM present count
    $result = $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                   WHERE DATE(checkin_date) = '$today' AND pm_check_in IS NOT NULL");
    $pm_present = $result ? $result->fetch_row()[0] : 0;

    // Calculate absent count
    $result = $conn->query("SELECT COUNT(*) FROM professors p 
                     WHERE p.status = 'active' AND NOT EXISTS (
                         SELECT 1 FROM attendance a 
                         WHERE a.professor_id = p.id AND DATE(a.checkin_date) = '$today'
                     )");
    $absent_today = $result ? $result->fetch_row()[0] : 0;

    // Get weekly attendance data
    $currentDate = new DateTime();
    $currentDate->modify('-6 days');

    for ($i = 0; $i < 7; $i++) {
        $date = $currentDate->format('Y-m-d');
        $dayName = $currentDate->format('D');
        
        $result = $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                           WHERE DATE(checkin_date) = '$date' AND am_check_in IS NOT NULL");
        $am_count = $result ? $result->fetch_row()[0] : 0;
        
        $result = $conn->query("SELECT COUNT(DISTINCT professor_id) FROM attendance 
                           WHERE DATE(checkin_date) = '$date' AND pm_check_in IS NOT NULL");
        $pm_count = $result ? $result->fetch_row()[0] : 0;
        
        $weeklyData[] = [
            'day' => $dayName,
            'am' => $am_count,
            'pm' => $pm_count,
            'total' => $am_count + $pm_count
        ];
        
        $currentDate->modify('+1 day');
    }
} catch (Exception $e) {
    $showModal = true;
    $modalTitle = 'Database Error';
    $modalMessage = 'Error fetching dashboard data: ' . $e->getMessage();
    $modalType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Automated Attendance System</title>

    <!-- Bootstrap & Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

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
            margin-bottom: 2rem;
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

        .stat-card {
            padding: 1.5rem;
            border-radius: 8px;
            background: white;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-card h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .bg-total-professors {
            border-left-color: #4e73df;
        }

        .bg-am-present {
            border-left-color: #1cc88a;
        }

        .bg-pm-present {
            border-left-color: #36b9cc;
        }

        .bg-absent-today {
            border-left-color: #e74a3b;
        }

        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255,255,255,0.9);
        }

        .session-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 1.5rem;
            height: 100%;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        @media (max-width: 768px) {
            .stat-card h2 {
                font-size: 1.5rem;
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
                    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h1>
                    <p class="page-subtitle">Quick summary of today's attendance and weekly trends</p>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="stat-card bg-total-professors">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2><?= $total_professors ?></h2>
                                    <p class="text-muted mb-0">Total Professors</p>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-users text-primary"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="professors.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card bg-am-present">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2><?= $am_present ?></h2>
                                    <p class="text-muted mb-0">AM Present</p>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-sun text-success"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-success session-badge">Morning Session</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card bg-pm-present">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2><?= $pm_present ?></h2>
                                    <p class="text-muted mb-0">PM Present</p>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-moon text-info"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-info session-badge">Afternoon Session</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card bg-absent-today">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2><?= $absent_today ?></h2>
                                    <p class="text-muted mb-0">Absent Today</p>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-user-times text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Weekly Attendance Trend</h5>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="chart-container">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Today's Status</h5>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="todayChart"></canvas>
                            </div>
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
                    <button type="button" class="btn btn-<?php echo $modalType === 'danger' ? 'danger' : 'primary'; ?>" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show modal if needed
            <?php if ($showModal): ?>
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
            <?php endif; ?>

            // Weekly Attendance Line Chart
            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            const weeklyChart = new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($weeklyData, 'day')); ?>,
                    datasets: [
                        {
                            label: 'AM Session',
                            data: <?php echo json_encode(array_column($weeklyData, 'am')); ?>,
                            borderColor: 'rgba(25, 135, 84, 1)',
                            backgroundColor: 'rgba(25, 135, 84, 0.2)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'PM Session',
                            data: <?php echo json_encode(array_column($weeklyData, 'pm')); ?>,
                            borderColor: 'rgba(23, 162, 184, 1)',
                            backgroundColor: 'rgba(23, 162, 184, 0.2)',
                            tension: 0.3,
                            fill: true,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.raw} professors`;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Number of Professors'
                            }
                        }
                    }
                }
            });

            // Today's Status Bar Chart
            const todayCtx = document.getElementById('todayChart').getContext('2d');
            const todayChart = new Chart(todayCtx, {
                type: 'bar',
                data: {
                    labels: ['AM Present', 'PM Present', 'Absent'],
                    datasets: [{
                        label: 'Number of Professors',
                        data: [<?= $am_present ?>, <?= $pm_present ?>, <?= $absent_today ?>],
                        backgroundColor: [
                            'rgba(25, 135, 84, 0.7)',
                            'rgba(23, 162, 184, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderColor: [
                            'rgba(25, 135, 84, 1)',
                            'rgba(23, 162, 184, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.raw} professors`;
                                }
                            }
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Number of Professors'
                            }
                        }
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>